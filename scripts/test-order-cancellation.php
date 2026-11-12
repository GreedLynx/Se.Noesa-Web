<?php
// Database khusus uji dan schema acak; tidak membaca .env atau menyentuh data aplikasi.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('zend.exception_ignore_args', '1');
ini_set('session.save_path', sys_get_temp_dir());

function cancellation_test_config(): array
{
    $port = getenv('SENOESA_TEST_PGPORT');
    if (!$port || !ctype_digit($port)) throw new RuntimeException('Isi SENOESA_TEST_PGPORT untuk PostgreSQL uji; siapkan database senoesa_cancellation_test.');
    return ['db' => ['host' => '127.0.0.1', 'port' => $port, 'name' => 'senoesa_cancellation_test',
        'user' => getenv('SENOESA_TEST_PGUSER') ?: 'postgres', 'pass' => getenv('SENOESA_TEST_PGPASSWORD') ?: '', 'sslmode' => 'disable']];
}

// Gunakan bootstrap asli dengan satu penggantian sumber konfigurasi khusus pengujian.
$bootstrap = file_get_contents(__DIR__ . '/../app/bootstrap.php');
$bootstrap = str_replace("require __DIR__ . '/../config/config.php'", 'cancellation_test_config()', $bootstrap);
eval('?>' . $bootstrap);
require __DIR__ . '/../app/daily-quota.php';

class CancellationResponse extends RuntimeException
{
    public function __construct(public array $data, public int $status) {}
}
function cancellation_json($data, int $status = 200): void
{
    throw new CancellationResponse($data, $status);
}
function cancellation_check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
    echo 'PASS: ' . $message . PHP_EOL;
}
function cancellation_source(string $path): string
{
    $source = file_get_contents($path);
    $source = preg_replace('/^require(?:_once)? __DIR__ .*;\R/m', '', $source);
    $now = getenv('SENOESA_TEST_NOW');
    if ($now) {
        $time = (new DateTimeImmutable($now))->format('Y-m-d H:i:s.uP');
        $source = str_replace(['clock_timestamp()', 'statement_timestamp()'], "TIMESTAMPTZ '$time'", $source);
    }
    $resolved = '';
    foreach (token_get_all($source) as $token) {
        $resolved .= is_array($token) ? match ($token[0]) {
            T_DIR => var_export(dirname($path), true),
            T_FILE => var_export($path, true),
            default => $token[1],
        } : $token;
    }
    return $resolved;
}
function cancellation_upload_dir(): string
{
    $folder = sys_get_temp_dir() . '/senoesa-cancel-' . getenv('SENOESA_TEST_SCHEMA');
    if (!is_dir($folder)) mkdir($folder, 0700);
    return $folder;
}
function cancellation_file(): array
{
    $path = tempnam(cancellation_upload_dir(), 'proof-');
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    return ['tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK];
}
function cancellation_api(string $role, string $method, array $payload, array $files = []): CancellationResponse
{
    $_SESSION['user'] = ['id' => $role === 'other' ? 2 : 1, 'role' => $role === 'other' ? 'pelanggan' : $role];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = $method === 'GET' ? $payload : [];
    $_POST = $method === 'POST' ? $payload : [];
    $_FILES = $files;
    $area = in_array($role, ['admin', 'owner'], true) ? $role : 'pelanggan';
    if (($payload['action'] ?? '') === 'cancel_order') $area = 'pelanggan';
    $source = cancellation_source(__DIR__ . '/../public/' . $area . '/api/orders.php');
    if ($GLOBALS['cancellation_fail_db'] ?? false) $source = str_replace('db()', 'cancellation_failed_db()', $source);
    $source = str_replace('json(', 'cancellation_json(', $source);
    // Seperti harness kuota: tangkap respons tanpa exit; validasi dan SQL tetap asli.
    $source = str_replace('catch (Throwable $e) {', 'catch (Throwable $e) { if ($e instanceof CancellationResponse) throw $e;', $source);
    // CLI tidak memiliki HTTP uploaded-file; hanya perpindahan file PHP yang disimulasikan.
    $source = str_replace('move_uploaded_file(', 'rename(', $source);
    $source = preg_replace('~\$folder = .*\'/../../uploads/(payments|dispatch)\';~', '\$folder = cancellation_upload_dir();', $source);
    try {
        eval('?>' . $source);
    } catch (CancellationResponse $response) {
        return $response;
    }
    throw new RuntimeException('Endpoint tidak menghasilkan respons');
}
function cancellation_child(array $args): array
{
    $process = proc_open([PHP_BINARY, __FILE__, ...$args],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Gagal menjalankan proses uji');
    return [$process, $pipes];
}
function cancellation_finish(array $child): array
{
    [$process, $pipes] = $child;
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output, $errors];
}
function cancellation_job(string $mode): array
{
    return cancellation_finish(cancellation_child(['--job', $mode]));
}
function cancellation_config_failure(): void
{
    die('Simulasi konfigurasi tidak tersedia');
}
function cancellation_failed_db(): PDO
{
    throw new PDOException('Simulasi database tidak tersedia');
}

$schema = getenv('SENOESA_TEST_SCHEMA');
if ($schema) {
    if (!preg_match('/^cancel_test_[a-f0-9]+$/D', $schema)) throw new RuntimeException('Schema uji tidak valid');
    q('SELECT set_config(?, ?, false)', ['search_path', $schema]);
}
if (in_array($argv[1] ?? '', ['--job', '--job-config-failure'], true)) {
    $source = cancellation_source(__DIR__ . '/expire-unpaid-orders.php');
    if ($argv[1] === '--job-config-failure') $source = str_replace('q(', 'cancellation_config_failure(', $source);
    $argv = ['expire-unpaid-orders.php', $argv[2]];
    eval('?>' . $source);
    exit;
}
if (($argv[1] ?? '') === '--existing') {
    if (!in_array($argv[2], ['test-daily-quota.php', 'test-quota-api.php'], true)) exit(2);
    $source = cancellation_source(__DIR__ . '/' . $argv[2]);
    // PHP -d memerlukan nilai INI berpetik untuk path Windows yang mengandung spasi/tilde.
    $source = str_replace("'session.save_path=' . ini_get('session.save_path')",
        "'session.save_path=' . chr(34) . str_replace(chr(92), '/', ini_get('session.save_path')) . chr(34)", $source);
    $source = str_replace('[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);',
        '[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $other->prepare(\'SELECT set_config(?, ?, false)\')->execute([\'search_path\', getenv(\'SENOESA_TEST_SCHEMA\')]);', $source);
    eval('?>' . $source);
    exit;
}
if (($argv[1] ?? '') === '--race') {
    q('SELECT set_config(?, ?, false)', ['application_name', $schema]);
    echo "READY\n";
    flush();
    $kind = $argv[2];
    $payload = match ($kind) {
        'upload' => ['action' => 'upload_proof', 'id' => 1, 'nama_pembayar' => 'Penguji'],
        'validate' => ['action' => 'validate', 'id' => 1],
        'complete' => ['action' => 'complete', 'id' => 1],
        'dispatch' => ['action' => 'upload_dispatch', 'id' => 1],
        default => ['action' => 'cancel_order', 'id' => 1],
    };
    $response = cancellation_api(in_array($kind, ['validate', 'complete', 'dispatch'], true) ? 'admin' : 'pelanggan', 'POST', $payload,
        in_array($kind, ['upload', 'dispatch'], true) ? [$kind === 'upload' ? 'proof' : 'foto_kiriman' => cancellation_file()] : []);
    echo json_encode(['status' => $response->status, 'data' => $response->data]);
    exit;
}

try {
    $schema = 'cancel_test_' . bin2hex(random_bytes(6));
    putenv('SENOESA_TEST_SCHEMA=' . $schema);
    putenv('SENOESA_TEST_NOW=2030-11-18T23:59:59.999999+07:00');
    q('CREATE SCHEMA ' . $schema);
    q('SELECT set_config(?, ?, false)', ['search_path', $schema]);
    $schemaSql = file_get_contents(__DIR__ . '/../database/schema.sql');
    $schemaSql = substr($schemaSql, 0, strpos($schemaSql, '-- SEED'));
    db()->exec($schemaSql);
    foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $file) {
        db()->exec(file_get_contents($file));
    }
    cancellation_check(true, 'Skema baru dan seluruh migrasi 001–016 berhasil');
    q("INSERT INTO users (id,username,email,password_hash) VALUES (1,'penguji','uji@example.invalid','x'),(2,'lain','lain@example.invalid','x')");
    q("INSERT INTO produk (id,kode,nama,kategori,harga,kuota_harian) VALUES (1,'UJI','Menu Uji','makanan',10000,50)");
    q("INSERT INTO jadwal_po (id,nama,tgl_buka,tgl_tutup,kapasitas) VALUES (1,'Jadwal Uji','2030-01-01','2030-12-31',50)");
    q("INSERT INTO pesanan (id,kode,user_id,jadwal_id,nama_pemesan,pax,total,tgl_kirim,created_at)
        VALUES (1,'UJI-1',1,1,'Penguji',4,40000,'2030-11-20 12:00+07','2030-11-18 23:30+07')");
    q('INSERT INTO detail_pesanan (pesanan_id,produk_id,qty,harga) VALUES (1,1,4,10000)');

    // Upgrade 015 -> 016 tetap memvalidasi status lama dan mempertahankan kolom VIEW.
    q("ALTER TABLE pesanan DROP CONSTRAINT pesanan_status_check,
        ADD CONSTRAINT pesanan_status_check CHECK (status IN ('menunggu_pembayaran','menunggu_validasi','diproses','selesai','ditolak'))");
    db()->exec(substr($sql = file_get_contents(__DIR__ . '/../database/migrations/015_default_product_quota.sql'), strpos($sql, 'CREATE OR REPLACE VIEW')));
    db()->exec(file_get_contents(__DIR__ . '/../database/migrations/016_order_cancellation.sql'));
    foreach (['menunggu_pembayaran', 'menunggu_validasi', 'diproses', 'selesai', 'ditolak', 'dibatalkan'] as $status) {
        q('UPDATE pesanan SET status=? WHERE id=1', [$status]);
    }
    try {
        q("UPDATE pesanan SET status='asing' WHERE id=1");
        throw new RuntimeException('Status asing diterima');
    } catch (PDOException $e) {
        cancellation_check($e->getCode() === '23514', 'Upgrade menerima enam status sah dan menolak status asing');
    }
    $reset = function (string $status = 'menunggu_pembayaran'): void {
        q("UPDATE pesanan SET status=?, bukti_bayar=NULL, foto_kiriman=NULL, nama_pembayar=NULL,
            tgl_kirim='2030-11-20 12:00+07', created_at='2030-11-18 23:30+07' WHERE id=1", [$status]);
        q('UPDATE detail_pesanan SET sudah_dicek=FALSE');
    };
    $cancel = fn(string $role = 'pelanggan', $id = 1) => cancellation_api($role, 'POST', ['action' => 'cancel_order', 'id' => $id]);
    $proof = fn() => cancellation_api('pelanggan', 'POST', ['action' => 'upload_proof', 'id' => 1, 'nama_pembayar' => 'Penguji'], ['proof' => cancellation_file()]);
    $reset();
    $GLOBALS['cancellation_fail_db'] = true;
    cancellation_check($cancel()->status === 500, 'Koneksi awal cancel gagal tetap mengembalikan JSON 500 tanpa koneksi ulang');
    unset($GLOBALS['cancellation_fail_db']);
    foreach (['menunggu_pembayaran', 'menunggu_validasi', 'diproses'] as $status) {
        $reset($status);
        q("UPDATE pesanan SET bukti_bayar='uploads/payments/old.png', foto_kiriman='uploads/dispatch/old.png' WHERE id=1");
        cancellation_check($cancel()->status === 200, 'Pembatalan ' . $status . ' sampai akhir H-2 diterima');
        $row = q('SELECT * FROM pesanan WHERE id=1')->fetch();
        cancellation_check($row['status'] === 'dibatalkan' && $row['bukti_bayar'] === 'uploads/payments/old.png' &&
            $row['foto_kiriman'] === 'uploads/dispatch/old.png' && (int) q('SELECT COUNT(*) FROM detail_pesanan')->fetchColumn() === 1, 'Riwayat dan bukti tetap tersimpan');
    }
    foreach (['selesai', 'ditolak', 'dibatalkan'] as $status) {
        $reset($status);
        cancellation_check($cancel()->status === 409, 'Status terminal ' . $status . ' ditolak');
    }
    $reset();
    cancellation_check($cancel('other')->status === 404 && $cancel('pelanggan', 999)->status === 404, 'Pesanan orang lain dan ID tidak ada menghasilkan 404');
    foreach (['admin', 'owner'] as $role) cancellation_check($cancel($role)->status === 403, 'Role ' . $role . ' tidak dapat membatalkan sebagai pelanggan');
    foreach ([0, -1, '1x', [], null] as $id) cancellation_check($cancel('pelanggan', $id)->status === 422, 'ID tidak valid ditolak');
    putenv('SENOESA_TEST_NOW=2030-11-19T00:00:00+07:00');
    cancellation_check($cancel()->status === 409, 'Awal H-1 ditolak');
    foreach (['2030-11-17 12:00+07', null] as $date) {
        q('UPDATE pesanan SET tgl_kirim=? WHERE id=1', [$date]);
        cancellation_check($cancel()->status === 409, 'Tanggal lampau/NULL ditolak');
    }
    $reset();
    putenv('SENOESA_TEST_NOW=2030-11-18T23:59:59.999999+07:00');
    q("SET TIME ZONE 'America/New_York'");
    $detail = cancellation_api('pelanggan', 'GET', ['id' => 1])->data;
    cancellation_check($detail['can_cancel'] === true && $detail['can_upload_proof'] === true &&
        $detail['cancel_before'] === '2030-11-19T00:00:00+07:00', 'Kontrak waktu ISO dan H-2 tetap Jakarta saat zona DB berbeda');
    cancellation_check(daily_quota_snapshot('2030-11-20')['kitchenUsed'] === 4, 'Pesanan aktif memakai kuota');
    $cancel();
    $snapshot = daily_quota_snapshot('2030-11-20');
    cancellation_check($snapshot['kitchenUsed'] === 0 && (int) $snapshot['items'][0]['sisa'] === 50 &&
        $snapshot === daily_quota_snapshot('2030-11-20', true), 'Pembatalan memulihkan kuota produk/dapur; VIEW dan checkout sama');

    $reset();
    putenv('SENOESA_TEST_NOW=2030-11-19T00:29:59+07:00');
    cancellation_check($proof()->status === 200, 'Upload usia 59:59 diterima');
    putenv('SENOESA_TEST_NOW=2030-11-19T05:00:00+07:00');
    cancellation_check($proof()->status === 200 && cancellation_job('--apply')[0] === 0 &&
        q('SELECT status FROM pesanan WHERE id=1')->fetchColumn() === 'menunggu_validasi', 'Ganti bukti dan antrean validasi tidak terkena job satu jam');
    foreach (['2030-11-19T00:30:00+07:00', '2030-11-19T00:30:01+07:00'] as $time) {
        $reset();
        putenv('SENOESA_TEST_NOW=' . $time);
        $count = count(glob(cancellation_upload_dir() . '/*'));
        cancellation_check($proof()->status === 409 && count(glob(cancellation_upload_dir() . '/*')) === $count,
            'Upload tepat/lewat satu jam ditolak dan file baru dibersihkan');
        cancellation_check(cancellation_api('pelanggan', 'GET', ['id' => 1])->data['can_upload_proof'] === false, 'Detail menutup form upload kedaluwarsa tanpa scheduler');
    }
    $reset();
    putenv('SENOESA_TEST_NOW=2030-11-19T00:29:59+07:00');
    cancellation_check(cancellation_job('--apply')[0] === 0 && q('SELECT status FROM pesanan')->fetchColumn() === 'menunggu_pembayaran', 'Job usia 59:59 tidak membatalkan');
    putenv('SENOESA_TEST_NOW=2030-11-19T00:30:00+07:00');
    foreach (['--inventory', '--dry-run'] as $mode) {
        [$exit, $output] = cancellation_job($mode);
        cancellation_check($exit === 0 && str_contains($output, '"eligible":1') && q('SELECT status FROM pesanan')->fetchColumn() === 'menunggu_pembayaran', $mode . ' read-only melaporkan kandidat lama');
    }
    cancellation_check(cancellation_job('--apply')[0] === 0 && q('SELECT status FROM pesanan')->fetchColumn() === 'dibatalkan', 'Job membatalkan tepat satu jam tanpa traffic web');
    cancellation_check(str_contains(cancellation_job('--apply')[1], 'Dibatalkan: 0'), 'Job aman dijalankan ulang');
    foreach (['menunggu_validasi', 'diproses', 'selesai', 'ditolak', 'dibatalkan'] as $status) {
        $reset($status);
        cancellation_job('--apply');
        cancellation_check(q('SELECT status FROM pesanan')->fetchColumn() === $status, 'Job tidak mengubah ' . $status);
    }
    foreach (['bukti', 'waktu'] as $bad) {
        $reset();
        q($bad === 'bukti' ? "UPDATE pesanan SET bukti_bayar='uploads/payments/old.png'" : 'UPDATE pesanan SET created_at=NULL');
        [$exit, , $errors] = cancellation_job('--apply');
        cancellation_check($exit === 1 && str_contains($errors, 'dilewati') && q('SELECT status FROM pesanan')->fetchColumn() === 'menunggu_pembayaran', 'Job melewati dan melaporkan unpaid inkonsisten: ' . $bad);
    }
    $reset();
    putenv('SENOESA_TEST_SCHEMA=cancel_test_000000000000');
    cancellation_check(cancellation_job('--apply')[0] === 1, 'Job melaporkan kegagalan database dengan exit nonzero');
    putenv('SENOESA_TEST_SCHEMA=' . $schema);
    cancellation_check(cancellation_job('--apply')[0] === 0, 'Job pulih setelah koneksi/schema diperbaiki');
    [$exit, , $errors] = cancellation_finish(cancellation_child(['--job-config-failure', '--apply']));
    cancellation_check($exit === 1 && str_contains($errors, 'Job tidak selesai'), 'Konfigurasi yang berhenti dengan die tetap menghasilkan exit nonzero');

    // Schema khusus dibagi dua koneksi untuk balapan, bukan tabel aplikasi.
    putenv('SENOESA_TEST_NOW=2030-11-18T23:59:59+07:00');
    foreach (['upload', 'validate', 'complete', 'dispatch', 'cancel'] as $kind) {
        $reset($kind === 'validate' ? 'menunggu_validasi' : (in_array($kind, ['complete', 'dispatch'], true) ? 'diproses' : 'menunggu_pembayaran'));
        if (in_array($kind, ['validate', 'complete'], true)) {
            q("UPDATE pesanan SET bukti_bayar='uploads/payments/old.png', foto_kiriman='uploads/dispatch/old.png'");
            q('UPDATE detail_pesanan SET sudah_dicek=TRUE');
        }
        $count = count(glob(cancellation_upload_dir() . '/*'));
        db()->beginTransaction();
        q('SELECT id FROM pesanan WHERE id=1 FOR UPDATE');
        $child = cancellation_child(['--race', $kind]);
        if (trim(fgets($child[1][1])) !== 'READY') throw new RuntimeException('Child tidak siap');
        $waiting = false;
        $limit = microtime(true) + 5;
        do {
            $waiting = (bool) q("SELECT 1 FROM pg_stat_activity WHERE application_name=? AND wait_event_type='Lock'", [$schema])->fetchColumn();
            if (!$waiting) usleep(20000);
        } while (!$waiting && microtime(true) < $limit);
        if (!$waiting) throw new RuntimeException('Mutasi kedua tidak menunggu row lock');
        q("UPDATE pesanan SET status='dibatalkan' WHERE id=1");
        db()->commit();
        [$exit, $output, $errors] = cancellation_finish($child);
        cancellation_check($exit === 0 && $errors === '' && (json_decode($output, true)['status'] ?? null) === 409 &&
            q('SELECT status FROM pesanan')->fetchColumn() === 'dibatalkan', 'Balapan cancel vs ' . $kind . ': mutasi kedua ditolak tanpa menghidupkan pesanan');
        cancellation_check(count(glob(cancellation_upload_dir() . '/*')) === $count, 'Balapan tidak menyisakan file upload gagal');
    }
    // Job yang sudah memilih unpaid wajib mengecek ulang setelah upload memegang lock.
    $reset();
    putenv('SENOESA_TEST_NOW=2030-11-19T00:30:00+07:00');
    db()->beginTransaction();
    q('SELECT id FROM pesanan WHERE id=1 FOR UPDATE');
    $child = cancellation_child(['--job', '--apply']);
    $inventory = fgets($child[1][1]);
    cancellation_check(str_contains($inventory, '"eligible":1'), 'Job melihat kandidat unpaid saat upload belum commit');
    q("UPDATE pesanan SET status='menunggu_validasi', bukti_bayar='uploads/payments/old.png' WHERE id=1");
    db()->commit();
    [$exit, $output] = cancellation_finish($child);
    cancellation_check($exit === 0 && str_contains($output, 'Dibatalkan: 0') && q('SELECT status FROM pesanan')->fetchColumn() === 'menunggu_validasi', 'Upload yang lebih dahulu memegang lock selamat dari job');
    // Dua job berjalan bersamaan aman: total perubahan tetap satu.
    $reset();
    $first = cancellation_child(['--job', '--apply']);
    $second = cancellation_child(['--job', '--apply']);
    [$exit1, $output1] = cancellation_finish($first);
    [$exit2, $output2] = cancellation_finish($second);
    preg_match('/Dibatalkan: (\d+)/', $output1, $count1);
    preg_match('/Dibatalkan: (\d+)/', $output2, $count2);
    cancellation_check($exit1 === 0 && $exit2 === 0 && (int) $count1[1] + (int) $count2[1] === 1, 'Dua job overlap hanya membatalkan satu kali');
    $reset();
    putenv('SENOESA_TEST_NOW=2030-11-18T23:59:59+07:00');
    $cancel();
    foreach (['admin', 'owner'] as $role) {
        $response = cancellation_api($role, 'GET', ['tab' => 'semua', 'tgl_kirim' => '2030-11-20'])->data;
        $items = $response[$role === 'admin' ? 'items' : 'orders'];
        cancellation_check(count($items) === 1 && $items[0]['status'] === 'dibatalkan', 'Daftar semua ' . $role . ' memuat pembatalan');
    }
    cancellation_check(cancellation_api('owner', 'POST', ['action' => 'validate', 'id' => 1])->status === 405, 'Owner tetap read-only');
    foreach (['test-daily-quota.php', 'test-quota-api.php'] as $file) {
        [$exit, $output, $errors] = cancellation_finish(cancellation_child(['--existing', $file]));
        if ($exit !== 0 || $errors !== '') throw new RuntimeException($file . ': ' . $output . $errors);
        cancellation_check(true, $file . ' lulus dengan konfigurasi database uji tanpa .env');
    }
    echo "Seluruh uji pembatalan lulus.\n";
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    if (isset($schema) && preg_match('/^cancel_test_[a-f0-9]+$/D', $schema)) {
        q('DROP SCHEMA IF EXISTS ' . $schema . ' CASCADE');
        foreach (glob(cancellation_upload_dir() . '/*') as $file) unlink($file);
        rmdir(cancellation_upload_dir());
    }
}
exit(isset($failed) ? 1 : 0);
