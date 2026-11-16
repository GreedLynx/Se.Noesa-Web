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
    if (!$ok) {
        throw new RuntimeException($message);
    }
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
    // SELECT memverifikasi indeks kuota yang dibutuhkan sudah terpasang.
    foreach (['idx_pesanan_created_status', 'idx_pesanan_delivery_status', 'idx_detail_pesanan_produk'] as $index) {
        check_quota((bool) q('SELECT 1 FROM pg_indexes WHERE schemaname=current_schema() AND indexname=?', [$index])->fetchColumn(), 'Indeks ' . $index . ' terpasang');
    }
    $db->beginTransaction();
    // TEMP TABLE membuat skenario uji terisolasi; ROLLBACK di akhir membuang seluruh data uji.
    q('CREATE TEMP TABLE tanggal_libur_operasional (id SERIAL,tanggal_mulai DATE,tanggal_selesai DATE) ON COMMIT DROP');
    q('CREATE TEMP TABLE kuota_harian (id SERIAL,produk_id INT,tanggal DATE,kuota INT,UNIQUE(produk_id,tanggal)) ON COMMIT DROP');
    q('CREATE TEMP TABLE produk (id INT PRIMARY KEY,kode TEXT,nama TEXT,kategori TEXT,harga INT,gambar TEXT,min_pesan INT,kuota_harian INT) ON COMMIT DROP');
    q('CREATE TEMP TABLE pesanan (id INT PRIMARY KEY,tgl_kirim TIMESTAMPTZ,status TEXT,created_at TIMESTAMPTZ) ON COMMIT DROP');
    q('CREATE TEMP TABLE detail_pesanan (pesanan_id INT,produk_id INT,qty INT) ON COMMIT DROP');
    q("INSERT INTO produk VALUES (1,'A','Menu A','makanan',10000,NULL,4,10),(2,'B','Menu B','makanan',12000,NULL,4,90),(3,'C','Menu C','makanan',9000,NULL,4,0)");
    q("INSERT INTO pesanan VALUES (1,'2030-01-10 00:00:00+07','menunggu_pembayaran','2029-12-01'),(2,'2030-01-10 23:59:59+07','selesai','2029-12-01'),(3,'2030-01-11 00:00:00+07','diproses','2029-12-01')");
    q('INSERT INTO detail_pesanan VALUES (1,1,8),(2,2,72),(3,1,10)');
    q("INSERT INTO kuota_harian VALUES (1,1,'2030-01-10',10),(2,2,'2030-01-10',90),(3,1,'2030-01-11',10),(4,2,'2030-01-11',50)");
    // Buat temporary VIEW dari definisi laporan untuk membandingkan hasil query kuota.
    $migrationSql = file_get_contents(__DIR__ . '/../database/migrations/015_default_product_quota.sql');
    q('SAVEPOINT migration_fixture');
    $db->exec(str_replace('CREATE OR REPLACE VIEW v_kuota_harian', 'CREATE TEMP VIEW v_kuota_harian', $migrationSql));
    check_quota((int) q('SELECT kuota_harian FROM produk WHERE id=2')->fetchColumn() === 50, 'Migrasi penuh mengisi default dari tanggal paling akhir, bukan nilai terbesar');
    check_quota((int) q('SELECT kuota_harian FROM produk WHERE id=3')->fetchColumn() === 0, 'Migrasi penuh mempertahankan default produk tanpa override');
    check_quota((int) q('SELECT COUNT(*) FROM kuota_harian')->fetchColumn() === 4, 'Migrasi tidak menghapus override lama');
    check_quota((bool) q('SELECT convalidated FROM pg_constraint WHERE conrelid = ?::regclass AND conname = ?', ['pg_temp.produk', 'produk_kuota_harian_nonnegative_check'])->fetchColumn(), 'CHECK default pada migrasi penuh tervalidasi');
    q('ROLLBACK TO SAVEPOINT migration_fixture');
    $viewSql = substr($migrationSql, strpos($migrationSql, 'CREATE OR REPLACE VIEW'));
    q(str_replace('CREATE OR REPLACE VIEW v_kuota_harian', 'CREATE TEMP VIEW v_kuota_harian', $viewSql));
    $cancellationSql = file_get_contents(__DIR__ . '/../database/migrations/016_order_cancellation.sql');
    $db->exec(str_replace('CREATE OR REPLACE VIEW v_kuota_harian', 'CREATE OR REPLACE TEMP VIEW v_kuota_harian', $cancellationSql));
    $snapshot = daily_quota_snapshot('2030-01-10');
    check_quota($snapshot['maxKitchen'] === 80 && $snapshot['kitchenUsed'] === 80, 'Batas 80%, pesanan belum dibayar/selesai dihitung, tanggal lain dikecualikan');
    check_quota($snapshot === daily_quota_snapshot('2030-01-10', true), 'VIEW laporan sama dengan prepared query checkout');
    check_quota(daily_quota_snapshot('2030-01-11')['maxKitchen'] === 48, 'Kuota tanggal berbeda menghasilkan batas dapur 48');
    check_quota(daily_quota_snapshot('2030-01-12')['maxKitchen'] === 80, 'Tanggal tanpa override memakai kuota default');
    check_quota(daily_quota_snapshot('2030-01-12') === daily_quota_snapshot('2030-01-12', true), 'Laporan tanggal tanpa baris VIEW memakai default dan sisa yang sama');
    rejects_quota($snapshot, [1 => 3], 'Sisa kuota: 2 porsi');
    rejects_quota($snapshot, [2 => 4], '80%');
    rejects_quota($snapshot, [3 => 4], 'tidak tersedia');
    // UPDATE status menjadi ditolak untuk memastikan pemakaian kuotanya tidak lagi dihitung.
    q("UPDATE pesanan SET status='ditolak' WHERE id=1");
    $snapshot = daily_quota_snapshot('2030-01-10');
    check_quota($snapshot['kitchenUsed'] === 72 && (int) $snapshot['items'][0]['sisa'] === 10, 'Penolakan memulihkan kuota tanpa update counter');
    q("UPDATE pesanan SET status='dibatalkan' WHERE id=1");
    check_quota($snapshot === daily_quota_snapshot('2030-01-10') && $snapshot === daily_quota_snapshot('2030-01-10', true), 'Pembatalan memulihkan kuota produk/dapur sama dengan penolakan dan VIEW');
    check_quota(count(daily_quota_validate($snapshot, [1 => 8])) === 1, 'Pesanan tepat pada batas dapur diterima');
    rejects_quota($snapshot, [1 => 3], 'minimal dipesan 4');
    check_quota(daily_quota_snapshot('2030-01-11')['kitchenUsed'] === 10, 'Batas hari memakai Asia/Jakarta');
    // DELETE konfigurasi kuota untuk menguji bahwa transaksi yang sudah ada tetap masuk laporan.
    q("DELETE FROM kuota_harian WHERE produk_id=2 AND tanggal='2030-01-10'");
    check_quota(daily_quota_snapshot('2030-01-10')['kitchenUsed'] === 72, 'Pesanan tetap dihitung setelah konfigurasi kuota dihapus');
    check_quota(daily_quota_snapshot('2030-01-10') === daily_quota_snapshot('2030-01-10', true), 'Transaksi tanpa override tetap memakai default pada VIEW');
    q('UPDATE produk SET kuota_harian=60 WHERE id=1');
    check_quota((int) daily_quota_snapshot('2030-01-12')['items'][0]['kuota'] === 60, 'Edit default memengaruhi tanggal tanpa override');
    check_quota((int) daily_quota_snapshot('2030-01-11')['items'][0]['kuota'] === 10, 'Edit default tidak menimpa override');
    daily_quota_write_range(1, '2030-11-20', '2030-11-25', 30);
    daily_quota_write_range(1, '2030-11-23', '2030-11-28', 40);
    check_quota((int) q("SELECT COUNT(*) FROM kuota_harian WHERE produk_id=1 AND tanggal BETWEEN '2030-11-20' AND '2030-11-28'")->fetchColumn() === 9, 'Rentang inklusif UPSERT tidak membuat tanggal duplikat');
    foreach (['2030-11-19'=>60,'2030-11-20'=>30,'2030-11-22'=>30,'2030-11-23'=>40,'2030-11-25'=>40,'2030-11-28'=>40,'2030-11-29'=>60] as $date=>$quota) {
        check_quota((int) daily_quota_snapshot($date)['items'][0]['kuota'] === $quota, 'Overlap dan batas rentang ' . $date);
    }
    daily_quota_write_range(1, '2030-11-23', '2030-11-24', null);
    check_quota((int) daily_quota_snapshot('2030-11-23')['items'][0]['kuota'] === 60 && (int) daily_quota_snapshot('2030-11-25')['items'][0]['kuota'] === 40, 'Reset sebagian rentang kembali ke default tanpa mengubah tanggal lain');
    daily_quota_write_range(3, '2030-11-23', '2030-11-23', 10);
    check_quota((int) daily_quota_snapshot('2030-11-23')['items'][2]['kuota'] === 10, 'Override positif mengaktifkan produk default nol');
    daily_quota_write_range(1, '2030-01-11', '2030-01-11', 5);
    $lowered = daily_quota_snapshot('2030-01-11');
    check_quota((int) $lowered['items'][0]['sisa'] === 0 && $lowered['kitchenUsed'] === 10, 'Penurunan di bawah pemakaian mempertahankan pesanan dan membatasi sisa nol');
    rejects_quota($lowered, [1=>4], 'Sisa kuota: 0 porsi');
    q('UPDATE kuota_harian SET kuota=0');
    rejects_quota(daily_quota_snapshot('2030-01-10'), [1 => 4], 'tidak tersedia');
    foreach ([['2030-02-30','2030-03-01'],['2030-11-25','2030-11-20'],['2030-01-01','2031-01-02'],['0000-01-01','0000-01-02'],['bad','2030-01-01']] as [$start,$end]) {
        try {
            daily_quota_range($start, $end);
            throw new RuntimeException('Rentang tidak valid diterima');
        } catch (DomainException $e) {
            check_quota(true, 'Rentang invalid ditolak: ' . $start . '/' . $end);
        }
    }
    check_quota(daily_quota_range('2032-01-01','2032-12-31') === ['2032-01-01','2032-12-31'], '366 hari inklusif tahun kabisat diterima');
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
    // LOCK TABLE menguji bahwa checkout kedua tertahan saat checkout pertama memegang kunci.
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
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
