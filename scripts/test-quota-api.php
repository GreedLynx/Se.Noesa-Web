<?php
// Endpoint asli dijalankan terhadap tabel TEMP; respons json ditangkap tanpa exit.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('zend.exception_ignore_args', '1');
ini_set('session.save_path', sys_get_temp_dir());
ob_start();
if (getenv('SENOESA_TEST_PGPORT') && !function_exists('db')) {
    function quota_test_config(): array
    {
        $port = getenv('SENOESA_TEST_PGPORT');
        if (!$port || !ctype_digit($port)) {
            throw new RuntimeException('SENOESA_TEST_PGPORT tidak valid.');
        }
        return ['db' => [
            'host' => getenv('SENOESA_TEST_PGHOST') ?: '127.0.0.1',
            'port' => $port,
            'name' => getenv('SENOESA_TEST_PGNAME') ?: 'postgres',
            'user' => getenv('SENOESA_TEST_PGUSER') ?: 'postgres',
            'pass' => getenv('SENOESA_TEST_PGPASSWORD') ?: '',
            'sslmode' => 'disable',
        ]];
    }
    $bootstrap = file_get_contents(__DIR__ . '/../app/bootstrap.php');
    $bootstrap = str_replace("require __DIR__ . '/../config/config.php'", 'quota_test_config()', $bootstrap);
    eval('?>' . $bootstrap);
    unset($bootstrap);
}
if (!function_exists('db')) {
    require __DIR__ . '/../app/bootstrap.php';
}
require __DIR__ . '/../app/daily-quota.php';

if (($argv[1] ?? '') === '--role-check') {
    $_SESSION['user'] = ['id'=>1,'role'=>$argv[2]];
    // Harness memakai sesi tiruan; token CSRF fixture agar pengecekan role tetap teruji.
    $_SESSION['csrf_token'] = 'quota-test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'quota-test-token';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $source = file_get_contents(__DIR__ . '/../public/admin/api/' . $argv[3] . '.php');
    $source = preg_replace('/^require(?:_once)? __DIR__ .*;\R/m', '', $source);
    eval('?>' . $source);
    exit(2);
}

class QuotaApiResponse extends RuntimeException
{
    public function __construct(public array $data, public int $status) {}
}

function quota_test_json($data, int $status = 200): void
{
    throw new QuotaApiResponse($data, $status);
}

function quota_api(string $endpoint, array $payload, array $files = [], ?string $uploadDir = null): QuotaApiResponse
{
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    // Token CSRF fixture: validasi CSRF asli di bootstrap tetap dijalankan.
    $_SESSION['csrf_token'] = 'quota-test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'quota-test-token';
    $_POST = $payload;
    $_FILES = $files;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
    $source = file_get_contents(__DIR__ . '/../public/admin/api/' . $endpoint . '.php');
    $source = preg_replace('/^require(?:_once)? __DIR__ .*;\R/m', '', $source);
    // Hanya respons ditangkap; validasi, query, transaksi, dan role endpoint tetap asli.
    $source = str_replace('json(', 'quota_test_json(', $source);
    if ($files !== []) {
        $source = str_replace('is_uploaded_file(', 'is_file(', $source);
        $source = str_replace('move_uploaded_file(', 'rename(', $source);
        if ($uploadDir !== null) {
            $source = str_replace(
                "dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products'",
                var_export($uploadDir, true),
                $source
            );
        }
    }
    try {
        eval('?>' . $source);
    } catch (QuotaApiResponse $response) {
        return $response;
    } finally {
        restore_error_handler();
        restore_exception_handler();
    }
    throw new RuntimeException('Endpoint tidak menghasilkan respons');
}

function quota_api_check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

try {
    foreach (['pelanggan','owner'] as $role) {
        foreach (['products','kuota-harian'] as $endpoint) {
            $savePath = '"' . str_replace('\\', '/', (string) ini_get('session.save_path')) . '"';
            $process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $savePath, __FILE__, '--role-check', $role, $endpoint], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Tidak dapat menjalankan uji role');
            }
            $response = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            quota_api_check($exitCode === 0 && $errors === '' && json_decode($response,true) === ['error'=>'Unauthorized'], 'Role ' . $role . ' ditolak endpoint ' . $endpoint);
        }
    }
    $db = db();
    q('CREATE TEMP TABLE produk (id SERIAL PRIMARY KEY,kode TEXT UNIQUE,nama TEXT,kategori TEXT,harga INT,min_pesan INT,gambar TEXT,kuota_harian INT CHECK(kuota_harian>=0))');
    q('CREATE TEMP TABLE kuota_harian (id SERIAL PRIMARY KEY,produk_id INT REFERENCES produk(id),tanggal DATE,kuota INT CHECK(kuota>=0),UNIQUE(produk_id,tanggal))');
    q('CREATE TEMP TABLE tanggal_libur_operasional (id SERIAL PRIMARY KEY,tanggal_mulai DATE,tanggal_selesai DATE)');
    q('CREATE TEMP TABLE pesanan (id SERIAL PRIMARY KEY,tgl_kirim TIMESTAMPTZ,status TEXT,created_at TIMESTAMPTZ,bukti_bayar TEXT)');
    q('CREATE TEMP TABLE detail_pesanan (id SERIAL PRIMARY KEY,pesanan_id INT REFERENCES pesanan(id),produk_id INT REFERENCES produk(id),qty INT)');
    q("INSERT INTO produk VALUES (1,'A','Menu A','makanan',10000,4,'test.png',50)");
    $product = ['id'=>1,'kode'=>'A','nama'=>'Menu A','kategori'=>'makanan','harga'=>10000,'min_pesan'=>4,'kuota_default'=>60];
    quota_api_check(quota_api('products', $product)->status === 200 && (int) q('SELECT kuota_harian FROM produk WHERE id=1')->fetchColumn() === 60, 'API produk menyimpan default pada edit');
    quota_api_check(quota_api('products', array_replace($product, ['id' => -1]))->status === 422, 'API produk menolak ID negatif');
    foreach (['',-1,1.5,2147483648] as $invalid) {
        quota_api_check(quota_api('products', array_replace($product,['kuota_default'=>$invalid]))->status === 422, 'API default menolak nilai invalid ' . var_export($invalid,true));
    }
    $range = ['produk_id'=>1,'tanggal_mulai'=>'2030-11-20','tanggal_selesai'=>'2030-11-25','kuota'=>30];
    $response = quota_api('kuota-harian', $range);
    quota_api_check($response->status === 200 && $response->data['jumlah_tanggal'] === 6, 'API rentang menyimpan enam tanggal inklusif');
    quota_api('kuota-harian', array_replace($range,['tanggal_mulai'=>'2030-11-23','tanggal_selesai'=>'2030-11-28','kuota'=>40]));
    quota_api_check((int) q("SELECT COUNT(*) FROM kuota_harian WHERE kuota=30")->fetchColumn() === 3 && (int) q("SELECT COUNT(*) FROM kuota_harian WHERE kuota=40")->fetchColumn() === 6, 'API overlap terbaru menimpa hanya tanggal bertumpuk');
    foreach ([['tanggal_mulai'=>'2030-11-29','tanggal_selesai'=>'2030-11-20'],['tanggal_mulai'=>'2030-02-30'],['tanggal_mulai'=>'0000-01-01','tanggal_selesai'=>'0000-01-02'],['tanggal_mulai'=>'2030-01-01','tanggal_selesai'=>'2031-01-02'],['kuota'=>-1],['kuota'=>1.5],['kuota'=>2147483648],['action'=>'unknown']] as $invalid) {
        quota_api_check(quota_api('kuota-harian',array_replace($range,$invalid))->status === 422 && (int) q('SELECT COUNT(*) FROM kuota_harian')->fetchColumn() === 9, 'API input invalid ditolak tanpa perubahan parsial');
    }
    quota_api_check(quota_api('kuota-harian',array_replace($range,['produk_id'=>999]))->status === 404, 'API menolak produk yang tidak ada');
    quota_api('kuota-harian',['produk_id'=>1,'tanggal'=>'2030-11-20','kuota'=>0]);
    quota_api_check((int) q("SELECT kuota FROM kuota_harian WHERE tanggal='2030-11-20'")->fetchColumn() === 0, 'API tanggal tunggal dan override nol kompatibel');
    $response=quota_api('kuota-harian',array_replace($range,['action'=>'reset','tanggal_mulai'=>'2030-11-23','tanggal_selesai'=>'2030-11-24']));
    quota_api_check($response->data['jumlah_tanggal'] === 2 && (int) q('SELECT COUNT(*) FROM kuota_harian')->fetchColumn() === 7, 'API reset menghapus hanya rentang pilihan');
    $id=q("SELECT id FROM kuota_harian WHERE tanggal='2030-11-20'")->fetchColumn();
    quota_api_check(quota_api('kuota-harian',['action'=>'delete','id'=>$id])->status === 200, 'API delete id lama tetap bekerja');
    quota_api_check((int) q('SELECT kuota_harian FROM produk WHERE id=1')->fetchColumn() === 60, 'Override tidak mengubah default produk');

    // Fixture batas pemakaian aktif 40 porsi (menunggu_validasi, diproses, menunggu_pembayaran aktif).
    q("INSERT INTO produk (id,kode,nama,kategori,harga,min_pesan,gambar,kuota_harian) VALUES (2,'B','Menu B','makanan',12000,4,'test-b.png',50)");
    q("INSERT INTO pesanan (id,tgl_kirim,status,created_at,bukti_bayar) VALUES
        (101,'2030-12-10 10:00:00+07','diproses',now(),'uploads/payments/a.png'),
        (102,'2030-12-10 23:59:59+07','menunggu_validasi',now(),'uploads/payments/b.png'),
        (103,'2030-12-10 00:00:00+07','menunggu_pembayaran',now(),NULL),
        (104,'2030-12-10 12:00:00+07','ditolak',now(),'uploads/payments/c.png'),
        (105,'2030-12-10 12:00:00+07','dibatalkan',now(),NULL),
        (106,'2030-12-10 12:00:00+07','menunggu_pembayaran',now() - interval '2 hours',NULL),
        (107,'2030-12-15 12:00:00+07','ditolak',now(),NULL),
        (108,'2030-12-15 12:00:00+07','dibatalkan',now(),NULL),
        (109,'2030-12-15 12:00:00+07','menunggu_pembayaran',now() - interval '2 hours',NULL)");
    q("INSERT INTO detail_pesanan (pesanan_id,produk_id,qty) VALUES
        (101,2,25),(102,2,10),(103,2,5),
        (104,2,20),(105,2,20),(106,2,20),
        (107,2,15),(108,2,15),(109,2,15)");

    $productB = ['id'=>2,'kode'=>'B','nama'=>'Menu B','kategori'=>'makanan','harga'=>12000,'min_pesan'=>4,'kuota_default'=>50];
    $tmpUploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'senoesa-quota-upload-' . bin2hex(random_bytes(4));
    mkdir($tmpUploadDir, 0700, true);
    $tmpPng = tempnam(sys_get_temp_dir(), 'qimg-');
    file_put_contents($tmpPng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    try {
        $conflictDefault = quota_api(
            'products',
            array_replace($productB, ['nama'=>'Menu B Diubah','harga'=>15000,'kuota_default'=>39]),
            ['gambar_file' => ['name' => 'new.png', 'tmp_name' => $tmpPng, 'size' => filesize($tmpPng), 'error' => UPLOAD_ERR_OK]],
            $tmpUploadDir
        );
        $leftoverUploads = glob($tmpUploadDir . DIRECTORY_SEPARATOR . '*') ?: [];
    } finally {
        if (is_file($tmpPng)) {
            @unlink($tmpPng);
        }
        foreach (glob($tmpUploadDir . DIRECTORY_SEPARATOR . '*') ?: [] as $leftover) {
            @unlink($leftover);
        }
        @rmdir($tmpUploadDir);
    }
    $productRow = q('SELECT nama,harga,gambar,kuota_harian FROM produk WHERE id=2')->fetch();
    quota_api_check(
        $conflictDefault->status === 409
        && ($conflictDefault->data['tanggal'] ?? null) === '2030-12-10'
        && ($conflictDefault->data['terpakai'] ?? null) === 40
        && ($conflictDefault->data['minimum_kuota'] ?? null) === 40
        && str_contains((string) ($conflictDefault->data['error'] ?? ''), '2030-12-10')
        && str_contains((string) ($conflictDefault->data['error'] ?? ''), '40')
        && $leftoverUploads === []
        && $productRow['nama'] === 'Menu B'
        && (int) $productRow['harga'] === 12000
        && $productRow['gambar'] === 'test-b.png'
        && (int) $productRow['kuota_harian'] === 50,
        'Default 39 di bawah 40 terpakai ditolak 409 tanpa mengubah metadata atau meninggalkan gambar baru'
    );
    quota_api_check(
        quota_api('products', array_replace($productB, ['kuota_default'=>40]))->status === 200
        && (int) q('SELECT kuota_harian FROM produk WHERE id=2')->fetchColumn() === 40,
        'Default tepat 40 porsi diterima'
    );
    quota_api_check(
        quota_api('products', array_replace($productB, ['kuota_default'=>41]))->status === 200
        && (int) q('SELECT kuota_harian FROM produk WHERE id=2')->fetchColumn() === 41,
        'Default 41 porsi diterima'
    );

    $conflictOverride = quota_api('kuota-harian', ['produk_id'=>2,'tanggal_mulai'=>'2030-12-10','tanggal_selesai'=>'2030-12-10','kuota'=>39]);
    quota_api_check(
        $conflictOverride->status === 409
        && ($conflictOverride->data['tanggal'] ?? null) === '2030-12-10'
        && ($conflictOverride->data['terpakai'] ?? null) === 40
        && ($conflictOverride->data['minimum_kuota'] ?? null) === 40
        && str_contains((string) ($conflictOverride->data['error'] ?? ''), '2030-12-10')
        && str_contains((string) ($conflictOverride->data['error'] ?? ''), '40')
        && (int) q("SELECT COUNT(*) FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-10'")->fetchColumn() === 0,
        'Override 39 di bawah 40 terpakai ditolak 409'
    );
    quota_api_check(
        quota_api('kuota-harian', ['produk_id'=>2,'tanggal_mulai'=>'2030-12-10','tanggal_selesai'=>'2030-12-10','kuota'=>40])->status === 200
        && (int) q("SELECT kuota FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-10'")->fetchColumn() === 40,
        'Override tepat 40 porsi diterima'
    );
    quota_api_check(
        quota_api('kuota-harian', ['produk_id'=>2,'tanggal_mulai'=>'2030-12-10','tanggal_selesai'=>'2030-12-10','kuota'=>41])->status === 200
        && (int) q("SELECT kuota FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-10'")->fetchColumn() === 41,
        'Override 41 porsi diterima'
    );

    // Tanggal yang sudah punya override tidak membatasi penurunan kuota default produk.
    quota_api_check(
        quota_api('products', array_replace($productB, ['kuota_default'=>39]))->status === 200
        && (int) q('SELECT kuota_harian FROM produk WHERE id=2')->fetchColumn() === 39,
        'Tanggal dengan override tidak memblokir edit kuota default menjadi 39'
    );

    // Reset dan delete yang mengembalikan tanggal 40 terpakai ke default 39 harus ditolak 409.
    $resetConflict = quota_api('kuota-harian', ['action'=>'reset','produk_id'=>2,'tanggal_mulai'=>'2030-12-10','tanggal_selesai'=>'2030-12-10']);
    quota_api_check(
        $resetConflict->status === 409
        && ($resetConflict->data['tanggal'] ?? null) === '2030-12-10'
        && ($resetConflict->data['minimum_kuota'] ?? null) === 40
        && (int) q("SELECT kuota FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-10'")->fetchColumn() === 41,
        'Reset yang kembali ke default 39 ditolak 409 tanpa menghapus override'
    );
    $overrideId = (int) q("SELECT id FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-10'")->fetchColumn();
    $deleteConflict = quota_api('kuota-harian', ['action'=>'delete','id'=>$overrideId]);
    quota_api_check(
        $deleteConflict->status === 409
        && ($deleteConflict->data['tanggal'] ?? null) === '2030-12-10'
        && ($deleteConflict->data['minimum_kuota'] ?? null) === 40
        && (int) q("SELECT kuota FROM kuota_harian WHERE id=?", [$overrideId])->fetchColumn() === 41,
        'Delete override yang kembali ke default 39 ditolak 409 tanpa menghapus override'
    );

    // Konflik pada satu hari di rentang multi-hari membatalkan seluruh rentang secara atomik.
    quota_api_check(
        quota_api('kuota-harian', ['produk_id'=>2,'tanggal_mulai'=>'2030-12-11','tanggal_selesai'=>'2030-12-11','kuota'=>45])->status === 200,
        'Override pendamping pada tanggal tanpa pemakaian tersimpan'
    );
    $atomicSave = quota_api('kuota-harian', ['produk_id'=>2,'tanggal_mulai'=>'2030-12-09','tanggal_selesai'=>'2030-12-12','kuota'=>39]);
    quota_api_check(
        $atomicSave->status === 409
        && (int) q("SELECT COUNT(*) FROM kuota_harian WHERE produk_id=2 AND tanggal IN ('2030-12-09','2030-12-12')")->fetchColumn() === 0
        && (int) q("SELECT kuota FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-10'")->fetchColumn() === 41
        && (int) q("SELECT kuota FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-11'")->fetchColumn() === 45,
        'Konflik satu hari dalam rentang save membatalkan seluruh rentang secara atomik'
    );
    $atomicReset = quota_api('kuota-harian', ['action'=>'reset','produk_id'=>2,'tanggal_mulai'=>'2030-12-10','tanggal_selesai'=>'2030-12-11']);
    quota_api_check(
        $atomicReset->status === 409
        && (int) q("SELECT kuota FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-10'")->fetchColumn() === 41
        && (int) q("SELECT kuota FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-11'")->fetchColumn() === 45,
        'Konflik satu hari dalam rentang reset membatalkan seluruh rentang secara atomik'
    );

    // Setelah default dinaikkan ke 40, delete/reset override kembali diizinkan.
    quota_api_check(quota_api('products', array_replace($productB, ['kuota_default'=>40]))->status === 200, 'Default dinaikkan ke 40');
    quota_api_check(
        quota_api('kuota-harian', ['action'=>'delete','id'=>$overrideId])->status === 200
        && (int) q("SELECT COUNT(*) FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-10'")->fetchColumn() === 0,
        'Delete override berhasil saat default 40 mencukupi pemakaian 40'
    );

    // Nilai 0 diizinkan saat hanya ada pesanan ditolak, dibatalkan, atau unpaid kedaluwarsa.
    quota_api_check(
        quota_api('kuota-harian', ['produk_id'=>2,'tanggal_mulai'=>'2030-12-15','tanggal_selesai'=>'2030-12-15','kuota'=>0])->status === 200
        && (int) q("SELECT kuota FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-12-15'")->fetchColumn() === 0,
        'Nilai 0 diterima saat tanggal hanya memiliki pesanan ditolak, dibatalkan, dan unpaid kedaluwarsa'
    );

    // Produk baru tanpa pemakaian aktif dapat disetel default 0 meski ada pesanan nonaktif.
    q("INSERT INTO produk (id,kode,nama,kategori,harga,min_pesan,gambar,kuota_harian) VALUES (3,'C','Menu C','makanan',10000,4,'test-c.png',20)");
    q("INSERT INTO detail_pesanan (pesanan_id,produk_id,qty) VALUES (107,3,10),(108,3,10),(109,3,10)");
    quota_api_check(
        quota_api('products', ['id'=>3,'kode'=>'C','nama'=>'Menu C','kategori'=>'makanan','harga'=>10000,'min_pesan'=>4,'kuota_default'=>0])->status === 200
        && (int) q('SELECT kuota_harian FROM produk WHERE id=3')->fetchColumn() === 0,
        'Default 0 diterima saat hanya ada pesanan ditolak, dibatalkan, atau unpaid kedaluwarsa'
    );

    echo 'Seluruh uji API lulus; tabel TEMP hilang saat koneksi berakhir.' . PHP_EOL;
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
