<?php
// Regression test untuk aturan kuota harian & stok menu per tanggal.
// Semua fixture berada di TEMP table dan dibatalkan di akhir; tidak ada DML
// pada tabel produksi.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/daily-quota.php';

function check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . PHP_EOL;
}
function rejects(array $snapshot, array $qty, string $needle, string $message): void
{
    try {
        daily_quota_validate($snapshot, $qty);
    } catch (DomainException $e) {
        check(str_contains($e->getMessage(), $needle), $message . ' (' . $e->getMessage() . ')');
        return;
    }
    throw new RuntimeException('FAIL: expected rejection - ' . $message);
}

$db = db();
$db->beginTransaction();
try {
    q('CREATE TEMP TABLE produk (id INT PRIMARY KEY, kode TEXT, nama TEXT, kategori TEXT, harga INT, gambar TEXT, min_pesan INT, kuota_harian INT) ON COMMIT DROP');
    q('CREATE TEMP TABLE kuota_harian (id SERIAL PRIMARY KEY, produk_id INT, tanggal DATE, kuota INT, UNIQUE(produk_id, tanggal)) ON COMMIT DROP');
    q('CREATE TEMP TABLE pesanan (id SERIAL PRIMARY KEY, status TEXT, tgl_kirim TIMESTAMPTZ, created_at TIMESTAMPTZ, bukti_bayar TEXT) ON COMMIT DROP');
    q('CREATE TEMP TABLE detail_pesanan (id SERIAL PRIMARY KEY, pesanan_id INT, produk_id INT, qty INT, harga INT) ON COMMIT DROP');
    q('CREATE TEMP TABLE tanggal_libur_operasional (id SERIAL PRIMARY KEY, tanggal_mulai DATE, tanggal_selesai DATE) ON COMMIT DROP');

    q("INSERT INTO produk VALUES (1,'A','Menu A','makanan',10000,NULL,4,50),(2,'B','Menu B','makanan',12000,NULL,10,20),(3,'C','Menu C','minuman',9000,NULL,10,50)");

    $date = '2030-02-10';
    $clean = '2030-02-11';
    // Aktif: 40 porsi Menu A. Batal & expired unpaid tidak boleh dihitung.
    q("INSERT INTO pesanan (id,status,tgl_kirim,created_at,bukti_bayar) VALUES
        (1,'diproses','2030-02-10 12:00:00+07',now(),NULL),
        (2,'dibatalkan','2030-02-10 12:00:00+07',now(),NULL),
        (3,'menunggu_pembayaran','2030-02-10 12:00:00+07',now() - interval '2 hour',NULL),
        (4,'menunggu_pembayaran','2030-02-10 12:00:00+07',now(),NULL)");
    q('INSERT INTO detail_pesanan (pesanan_id,produk_id,qty,harga) VALUES (1,1,40,10000),(2,1,20,10000),(3,1,30,10000),(4,2,5,12000)');

    $s = daily_quota_snapshot($date);
    check((int) $s['dailyCapacity'] === 50, 'kapasitas harian 50');
    check((int) $s['dailyUsed'] === 45, 'reservasi aktif = 45 (batal & expired diabaikan)');
    check((int) $s['dailyRemaining'] === 5, 'sisa harian = 5');
    check($s['dailyStatus'] === 'nearly_full', 'status hampir penuh');
    $p1 = array_column($s['items'], null, 'id')[1];
    $p2 = array_column($s['items'], null, 'id')[2];
    check((int) $p1['terpakai'] === 40 && (int) $p1['sisa'] === 10, 'stok per menu A = 10');
    check((int) $p2['terpakai'] === 5 && (int) $p2['sisa'] === 15, 'stok per menu B = 15');
    check((int) $p1['min_pesan'] === 10, 'minimum menu A dinaikkan ke 10 oleh aturan baru');
    rejects($s, [2 => 10], 'Kuota harian', 'checkout ditolak saat sisa harian 5');

    $c = daily_quota_snapshot($clean);
    check((int) $c['dailyUsed'] === 0 && $c['dailyStatus'] === 'available', 'tanggal bersih tersedia');
    rejects($c, [1 => 9], 'Minimal', 'minimal 10 porsi per menu ditolak');
    rejects($c, [1 => 51], 'maksimal 50', 'total 51 porsi ditolak');
    rejects($c, [1 => 30, 2 => 30], 'maksimal 50', 'total 60 porsi ditolak');
    rejects($c, [2 => 21], 'Stok Menu B', 'stok menu B (20) ditolak untuk 21 porsi');
    $ok = daily_quota_validate($c, [1 => 10, 2 => 10]);
    check(count($ok) === 2, 'checkout sah untuk 20 porsi pada tanggal bersih');

    // Pembatalan melepas reservasi.
    q("UPDATE pesanan SET status='dibatalkan' WHERE id=1");
    $s2 = daily_quota_snapshot($date);
    check((int) $s2['dailyUsed'] === 5, 'pembatalan melepas 40 porsi');
    // Expired unpaid dilepas tanpa menjalankan cron.
    q("UPDATE pesanan SET created_at = now() - interval '3 hour' WHERE id=4");
    $s3 = daily_quota_snapshot($date);
    check((int) $s3['dailyUsed'] === 0, 'unpaid kedaluwarsa melepas reservasi tanpa cron');
    check($s3['dailyStatus'] === 'available', 'status kembali tersedia');

    // Penyesuaian stok tidak menghapus reservasi yang sudah ada.
    q("UPDATE pesanan SET status='diproses' WHERE id=1");
    q("UPDATE pesanan SET created_at = now() WHERE id=4");
    q("INSERT INTO kuota_harian (produk_id,tanggal,kuota) VALUES (1,'2030-02-10',5)");
    $s4 = daily_quota_snapshot($date);
    check((int) $s4['dailyUsed'] === 45, 'reservasi tetap dihitung setelah stok diturunkan');
    check((int) array_column($s4['items'], null, 'id')[1]['sisa'] === 0, 'sisa menu A menjadi 0');
    check((int) q('SELECT COUNT(*) FROM detail_pesanan WHERE pesanan_id=1')->fetchColumn() === 1, 'detail pesanan lama tidak dihapus');

    // Tanggal libur menutup semua menu.
    q("INSERT INTO tanggal_libur_operasional (tanggal_mulai,tanggal_selesai) VALUES ('2030-02-12','2030-02-12')");
    $h = daily_quota_snapshot('2030-02-12');
    check($h['tanggal_libur'] && $h['dailyStatus'] === 'closed', 'tanggal libur berstatus closed');
    rejects($h, [1 => 10], 'libur', 'checkout pada tanggal libur ditolak');

    echo 'Seluruh test kuota harian lulus. TEMP table dibatalkan.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    if ($db->inTransaction()) $db->rollBack();
    exit(1);
}
if ($db->inTransaction()) $db->rollBack();
