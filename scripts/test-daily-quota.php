<?php
// Pengujian PostgreSQL menggunakan tabel TEMP; data aplikasi tidak diubah.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('zend.exception_ignore_args', '1');
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/daily-quota.php';
function check_quota(bool $ok, string $message): void
{
    if (!$ok)
        throw new RuntimeException($message);
    echo 'PASS: ' . $message . PHP_EOL;
}
function rejects_quota(array $snapshot, array $quantities, string $message): void
{
    try {
        daily_quota_validate($snapshot, $quantities);
    } catch (DomainException $e) {
        check_quota(str_contains($e->getMessage(), $message), $message);
        return;
    }
    throw new RuntimeException('Transaksi seharusnya ditolak: ' . $message);
}
try {
    $db = db();
    foreach (['idx_pesanan_created_status', 'idx_pesanan_delivery_status', 'idx_detail_pesanan_produk'] as $index) {
        check_quota((bool) q('SELECT 1 FROM pg_indexes WHERE schemaname=current_schema() AND indexname=?', [$index])->fetchColumn(), 'Indeks ' . $index . ' terpasang');
    }
    $db->beginTransaction();
    q('CREATE TEMP TABLE kuota_harian (id INT,produk_id INT,tanggal DATE,kuota INT,UNIQUE(produk_id,tanggal)) ON COMMIT DROP');
    q('CREATE TEMP TABLE produk (id INT PRIMARY KEY,kode TEXT,nama TEXT,kategori TEXT,harga INT,gambar TEXT,min_pesan INT,kuota_harian INT) ON COMMIT DROP');
    q('CREATE TEMP TABLE pesanan (id INT PRIMARY KEY,tgl_kirim TIMESTAMPTZ,status TEXT,created_at TIMESTAMPTZ) ON COMMIT DROP');
    q('CREATE TEMP TABLE detail_pesanan (pesanan_id INT,produk_id INT,qty INT) ON COMMIT DROP');
    q("INSERT INTO produk VALUES (1,'A','Menu A','makanan',10000,NULL,4,10),(2,'B','Menu B','makanan',12000,NULL,4,90),(3,'C','Menu C','makanan',9000,NULL,4,0)");
    q("INSERT INTO pesanan VALUES (1,'2030-01-10 00:00:00+07','menunggu_pembayaran','2029-12-01'),(2,'2030-01-10 23:59:59+07','selesai','2029-12-01'),(3,'2030-01-11 00:00:00+07','diproses','2029-12-01')");
    q('INSERT INTO detail_pesanan VALUES (1,1,8),(2,2,72),(3,1,10)');
    q("INSERT INTO kuota_harian VALUES (1,1,'2030-01-10',10),(2,2,'2030-01-10',90),(3,1,'2030-01-11',10),(4,2,'2030-01-11',50)");
    $viewSql = file_get_contents(__DIR__ . '/../database/migrations/011_daily_quota_report.sql');
    q(str_replace('CREATE OR REPLACE VIEW v_kuota_harian', 'CREATE TEMP VIEW v_kuota_harian', $viewSql));
    $snapshot = daily_quota_snapshot('2030-01-10');
    check_quota($snapshot['maxKitchen'] === 80 && $snapshot['kitchenUsed'] === 80, 'Batas 80%, pesanan belum dibayar/selesai dihitung, tanggal lain dikecualikan');
    check_quota($snapshot === daily_quota_snapshot('2030-01-10', true), 'VIEW laporan sama dengan prepared query checkout');
    check_quota(daily_quota_snapshot('2030-01-11')['maxKitchen'] === 48, 'Kuota tanggal berbeda menghasilkan batas dapur 48');
    check_quota(daily_quota_snapshot('2030-01-12')['maxKitchen'] === 0, 'Tanggal tanpa konfigurasi tidak memakai kuota master');
    rejects_quota($snapshot, [1 => 3], 'Sisa kuota: 2 porsi');
    rejects_quota($snapshot, [2 => 4], '80%');
    rejects_quota($snapshot, [3 => 4], 'tidak tersedia');
    q("UPDATE pesanan SET status='ditolak' WHERE id=1");
    $snapshot = daily_quota_snapshot('2030-01-10');
    check_quota($snapshot['kitchenUsed'] === 72 && (int) $snapshot['items'][0]['sisa'] === 10, 'Penolakan memulihkan kuota tanpa update counter');
    check_quota(count(daily_quota_validate($snapshot, [1 => 8])) === 1, 'Pesanan tepat pada batas dapur diterima');
    rejects_quota($snapshot, [1 => 3], 'minimal dipesan 4');
    check_quota(daily_quota_snapshot('2030-01-11')['kitchenUsed'] === 10, 'Batas hari memakai Asia/Jakarta');
    q("DELETE FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-01-10'");
    check_quota(daily_quota_snapshot('2030-01-10')['kitchenUsed'] === 72, 'Pesanan tetap dihitung setelah konfigurasi kuota dihapus');
    q('UPDATE kuota_harian SET kuota=0');
    rejects_quota(daily_quota_snapshot('2030-01-10'), [1 => 4], 'tidak tersedia');
    try {
        daily_quota_snapshot('2030-02-30');
        throw new RuntimeException('Tanggal tidak valid diterima');
    } catch (DomainException $e) {
        check_quota(true, 'Tanggal tidak valid ditolak');
    }
    $db->rollBack();
    echo 'Seluruh pengujian kuota lulus. Tabel TEMP dibatalkan.' . PHP_EOL;
    // Dua koneksi memakai tabel produksi hanya untuk kunci, tanpa perubahan data.
    $config = cfg()['db'];
    $other = new PDO('pgsql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['name'] . ';sslmode=' . ($config['sslmode'] ?? 'require'), $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->beginTransaction();
    q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');
    $other->beginTransaction();
    $other->prepare("SET LOCAL lock_timeout='500ms'")->execute();
    try {
        $other->prepare('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE')->execute();
        throw new RuntimeException('Checkout kedua tidak menunggu kunci');
    } catch (PDOException $e) {
        check_quota($e->getCode() === '55P03', 'Checkout kedua menunggu kunci checkout pertama');
    } finally {
        $other->rollBack();
        $db->rollBack();
    }
    $other->beginTransaction();
    $other->prepare("SET LOCAL lock_timeout='500ms'")->execute();
    $other->prepare('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE')->execute();
    $other->rollBack();
    check_quota(true, 'Checkout selanjutnya dapat mengunci setelah transaksi pertama berakhir');
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction())
        $db->rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
