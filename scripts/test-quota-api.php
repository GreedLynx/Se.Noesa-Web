<?php
// Endpoint asli dijalankan terhadap tabel TEMP; respons json ditangkap tanpa exit.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('zend.exception_ignore_args', '1');
ob_start();
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/daily-quota.php';

if (($argv[1] ?? '') === '--role-check') {
    $_SESSION['user'] = ['id'=>1,'role'=>$argv[2]];
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

function quota_api(string $endpoint, array $payload): QuotaApiResponse
{
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    $_POST = $payload;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
    $source = file_get_contents(__DIR__ . '/../public/admin/api/' . $endpoint . '.php');
    $source = preg_replace('/^require(?:_once)? __DIR__ .*;\R/m', '', $source);
    // Hanya respons ditangkap; validasi, query, transaksi, dan role endpoint tetap asli.
    $source = str_replace('json(', 'quota_test_json(', $source);
    try {
        eval('?>' . $source);
    } catch (QuotaApiResponse $response) {
        return $response;
    } finally {
        restore_error_handler();
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
            $process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . ini_get('session.save_path'), __FILE__, '--role-check', $role, $endpoint], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
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
    q("INSERT INTO produk VALUES (1,'A','Menu A','makanan',10000,4,'test.png',50)");
    $product = ['id'=>1,'kode'=>'A','nama'=>'Menu A','kategori'=>'makanan','harga'=>10000,'min_pesan'=>4,'kuota_default'=>60];
    quota_api_check(quota_api('products', $product)->status === 200 && (int) q('SELECT kuota_harian FROM produk WHERE id=1')->fetchColumn() === 60, 'API produk menyimpan default pada edit');
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
    echo 'Seluruh uji API lulus; tabel TEMP hilang saat koneksi berakhir.' . PHP_EOL;
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
