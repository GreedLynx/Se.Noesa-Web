<?php
// Regression tests untuk Admin Late Payment Reconciliation.
// Semua tabel uji adalah TEMP dan dibatalkan; tidak menyentuh data produksi.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/daily-quota.php';
require_once __DIR__ . '/../app/proof-storage.php';
require_once __DIR__ . '/../app/late-payment.php';

function check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . PHP_EOL;
}
function expect_error(callable $fn, string $needle, string $message): void
{
    try { $fn(); } catch (Throwable $e) {
        check(str_contains($e->getMessage(), $needle), $message . ' (' . $e->getMessage() . ')');
        return;
    }
    throw new RuntimeException('FAIL: expected error - ' . $message);
}
function png_file(): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'snoesa-png-');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');
    file_put_contents($tmp, $png);
    return ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($png), 'name' => 'bukti.png'];
}
function text_file(): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'snoesa-txt-');
    file_put_contents($tmp, 'bukan gambar');
    return ['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'name' => 'bukti.txt'];
}

$db = db();
$db->beginTransaction();
$createdFiles = [];
try {
    q('CREATE TEMP TABLE users (id INT PRIMARY KEY, username TEXT, role TEXT) ON COMMIT DROP');
    q('CREATE TEMP TABLE produk (id INT PRIMARY KEY, kode TEXT, nama TEXT, kategori TEXT, harga INT, gambar TEXT, min_pesan INT, kuota_harian INT) ON COMMIT DROP');
    q('CREATE TEMP TABLE kuota_harian (id SERIAL PRIMARY KEY, produk_id INT, tanggal DATE, kuota INT) ON COMMIT DROP');
    q('CREATE TEMP TABLE pesanan (id SERIAL PRIMARY KEY, kode TEXT, user_id INT, nama_pemesan TEXT, telepon_penerima TEXT, total INT, status TEXT, tgl_kirim TIMESTAMPTZ, created_at TIMESTAMPTZ, bukti_bayar TEXT) ON COMMIT DROP');
    q('CREATE TEMP TABLE detail_pesanan (id SERIAL PRIMARY KEY, pesanan_id INT, produk_id INT, qty INT, harga INT) ON COMMIT DROP');
    q('CREATE TEMP TABLE pembayaran_terlambat (
        id SERIAL PRIMARY KEY, pesanan_id INT NOT NULL UNIQUE, bukti_path TEXT NOT NULL,
        nominal_dilaporkan BIGINT NOT NULL CHECK (nominal_dilaporkan >= 0), waktu_transaksi TIMESTAMPTZ NOT NULL,
        referensi_transaksi TEXT, status_rekonsiliasi TEXT NOT NULL DEFAULT \'menunggu_verifikasi\'
          CHECK (status_rekonsiliasi IN (\'menunggu_verifikasi\',\'terverifikasi\',\'tidak_valid\')),
        catatan_admin TEXT, dicatat_oleh INT NOT NULL, dicatat_at TIMESTAMPTZ NOT NULL DEFAULT now(),
        diverifikasi_oleh INT, diverifikasi_at TIMESTAMPTZ, hasil_catatan TEXT,
        tindak_lanjut TEXT, tindak_lanjut_order_id INT, tindak_lanjut_catatan TEXT,
        refund_nominal BIGINT, refund_tanggal TIMESTAMPTZ, refund_referensi TEXT,
        tindak_lanjut_oleh INT, tindak_lanjut_at TIMESTAMPTZ) ON COMMIT DROP');
    q('CREATE TEMP TABLE pembayaran_terlambat_log (
        id SERIAL PRIMARY KEY, rekonsiliasi_id INT NOT NULL, aksi TEXT NOT NULL,
        status_sebelum TEXT, status_sesudah TEXT, alasan TEXT, actor_id INT NOT NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now()) ON COMMIT DROP');

    q("INSERT INTO users VALUES (6,'pelanggan','pelanggan'),(99,'admin','admin'),(100,'admin2','admin')");
    q("INSERT INTO produk VALUES (1,'A','Menu A','makanan',10000,NULL,10,50)");
    q("INSERT INTO kuota_harian VALUES (1,1,'2030-03-01',50)");
    q("INSERT INTO pesanan (id,kode,user_id,nama_pemesan,telepon_penerima,total,status,tgl_kirim,created_at,bukti_bayar)
        VALUES (9001,'SN-OLD-1',6,'Pelanggan Uji','0812345678',60000,'dibatalkan','2030-03-01 12:00:00+07',now(),NULL),
               (9002,'SN-OLD-2',6,'Pelanggan Uji','0812345678',40000,'dibatalkan','2030-03-02 12:00:00+07',now(),NULL),
               (9003,'SN-ACTIVE',6,'Pelanggan Uji','0812345678',50000,'menunggu_pembayaran','2030-03-03 12:00:00+07',now(),NULL)");

    $quotaBefore = (int) q('SELECT COUNT(*) FROM kuota_harian')->fetchColumn();
    $statusBefore = q('SELECT status FROM pesanan WHERE id=9001')->fetchColumn();

    // 1. Admin mencatat bukti pada pesanan kedaluwarsa/dibatalkan.
    $file = png_file();
    $row = late_payment_record(9001, [
        'nominal_dilaporkan' => 60000,
        'waktu_transaksi' => '2030-02-28T10:30',
        'referensi_transaksi' => 'TRX-001',
        'catatan_admin' => 'Bukti diterima via email',
    ], $file, 99, false);
    $id1 = (int) $row['id'];
    check($id1 > 0, 'admin mencatat bukti pada pesanan dibatalkan');
    check(q('SELECT status_rekonsiliasi FROM pembayaran_terlambat WHERE id=?', [$id1])->fetchColumn() === 'menunggu_verifikasi', 'status awal menunggu verifikasi');
    $createdFiles[] = q('SELECT bukti_path FROM pembayaran_terlambat WHERE id=?', [$id1])->fetchColumn();
    @unlink($file['tmp_name']);

    // 3. ID pesanan tidak valid.
    expect_error(fn() => late_payment_record(999999, ['nominal_dilaporkan'=>1,'waktu_transaksi'=>'2030-02-28T10:30'], png_file(), 99, false),
        'tidak ditemukan', 'ID pesanan tidak valid ditolak');

    // 4. Format tidak diizinkan.
    $bad = text_file();
    expect_error(fn() => late_payment_record(9002, ['nominal_dilaporkan'=>1,'waktu_transaksi'=>'2030-02-28T10:30'], $bad, 99, false),
        'JPG', 'file format tidak diizinkan ditolak');
    @unlink($bad['tmp_name']);

    // 5. Ukuran melebihi batas.
    $big = tempnam(sys_get_temp_dir(), 'snoesa-big-');
    file_put_contents($big, str_repeat('x', 2 * 1024 * 1024 + 1));
    $bigFile = ['tmp_name'=>$big,'error'=>UPLOAD_ERR_OK,'size'=>filesize($big),'name'=>'big.png'];
    expect_error(fn() => late_payment_record(9002, ['nominal_dilaporkan'=>1,'waktu_transaksi'=>'2030-02-28T10:30'], $bigFile, 99, false),
        '2 MB', 'file terlalu besar ditolak');
    @unlink($big);

    // 6. Akses bukti dengan autentikasi: resolver hanya menerima prefix yang diizinkan.
    $stored = $createdFiles[0];
    check(proof_resolve($stored) !== null, 'bukti tersimpan dapat di-resolve oleh endpoint berautentikasi');
    check(proof_resolve('../config/config.php') === null, 'path traversal ditolak');

    // 7. Verifikasi tercatat dalam audit trail.
    $logsBefore = (int) q('SELECT COUNT(*) FROM pembayaran_terlambat_log WHERE rekonsiliasi_id=?', [$id1])->fetchColumn();
    late_payment_verify($id1, 'terverifikasi', 'Transfer cocok', 100);
    check((int) q('SELECT COUNT(*) FROM pembayaran_terlambat_log WHERE rekonsiliasi_id=?', [$id1])->fetchColumn() === $logsBefore + 1, 'verifikasi tercatat di audit trail');
    check(q('SELECT status_sebelum FROM pembayaran_terlambat_log WHERE rekonsiliasi_id=? ORDER BY id DESC LIMIT 1', [$id1])->fetchColumn() === 'menunggu_verifikasi', 'status sebelum tercatat');
    check(q('SELECT status_sesudah FROM pembayaran_terlambat_log WHERE rekonsiliasi_id=? ORDER BY id DESC LIMIT 1', [$id1])->fetchColumn() === 'terverifikasi', 'status sesudah tercatat');

    // 9. Verifikasi ganda tidak merusak catatan.
    $logsAfter = (int) q('SELECT COUNT(*) FROM pembayaran_terlambat_log WHERE rekonsiliasi_id=?', [$id1])->fetchColumn();
    expect_error(fn() => late_payment_verify($id1, 'tidak_valid', 'coba lagi', 99), 'sudah diverifikasi', 'verifikasi ganda ditolak');
    check((int) q('SELECT COUNT(*) FROM pembayaran_terlambat_log WHERE rekonsiliasi_id=?', [$id1])->fetchColumn() === $logsAfter, 'verifikasi ganda tidak menambah/rusak audit');

    // Tindak lanjut setelah verifikasi.
    late_payment_follow_up($id1, ['tindak_lanjut' => 'refund', 'refund_nominal' => 60000,
        'refund_tanggal' => '2030-03-05T09:00', 'refund_referensi' => 'REFUND-1',
        'tindak_lanjut_catatan' => 'Refund menunggu konfirmasi manual'], 99);
    check(q('SELECT tindak_lanjut FROM pembayaran_terlambat WHERE id=?', [$id1])->fetchColumn() === 'refund', 'tindak lanjut refund tercatat');
    check(q('SELECT COUNT(*) FROM pembayaran_terlambat_log WHERE rekonsiliasi_id=? AND aksi=\'tindak_lanjut\'', [$id1])->fetchColumn() == 1, 'tindak lanjut tercatat di audit');

    // 8. Penolakan dengan alasan.
    $file2 = png_file();
    $row2 = late_payment_record(9002, ['nominal_dilaporkan'=>40000,'waktu_transaksi'=>'2030-02-28T11:00'], $file2, 99, false);
    $id2 = (int) $row2['id'];
    $createdFiles[] = q('SELECT bukti_path FROM pembayaran_terlambat WHERE id=?', [$id2])->fetchColumn();
    @unlink($file2['tmp_name']);
    expect_error(fn() => late_payment_verify($id2, 'tidak_valid', '', 99), 'Alasan penolakan', 'alasan penolakan wajib');
    late_payment_verify($id2, 'tidak_valid', 'Bukti tidak sesuai', 100);
    check(q('SELECT hasil_catatan FROM pembayaran_terlambat WHERE id=?', [$id2])->fetchColumn() === 'Bukti tidak sesuai', 'penolakan tercatat dengan alasan');

    // 10 & 11. Pesanan lama dan kuota tidak berubah.
    check(q('SELECT status FROM pesanan WHERE id=9001')->fetchColumn() === $statusBefore, 'status pesanan lama tetap dibatalkan');
    check((int) q('SELECT COUNT(*) FROM kuota_harian')->fetchColumn() === $quotaBefore, 'kuota harian tidak berubah karena rekonsiliasi');
    check((int) q('SELECT SUM(kuota) FROM kuota_harian')->fetchColumn() === 50, 'kapasitas harian tetap 50');

    // 12. Checkout normal tetap berjalan.
    $snapshot = daily_quota_snapshot('2030-03-01');
    check(count(daily_quota_validate($snapshot, [1 => 10])) === 1, 'validasi checkout normal masih berfungsi');

    // 13. Bukti lama tidak dihapus bila masih direferensikan.
    $refPath = proof_store(png_file(), 'ref-test', false);
    $createdFiles[] = $refPath;
    q("UPDATE pesanan SET bukti_bayar=? WHERE id=9003", [$refPath]);
    proof_delete_if_unreferenced($refPath);
    check(proof_resolve($refPath) !== null, 'file yang masih direferensikan tidak dihapus');
    q("UPDATE pesanan SET bukti_bayar=NULL WHERE id=9003");
    proof_delete_if_unreferenced($refPath);
    check(proof_resolve($refPath) === null, 'file yang tidak direferensikan dihapus');

    // 2. Role check pada endpoint (verifikasi statis kontrak akses).
    $adminApi = file_get_contents(__DIR__ . '/../public/admin/api/late-payments.php');
    check(str_contains($adminApi, "require_role('admin')"), 'endpoint rekonsiliasi hanya untuk Admin');
    $customerProof = file_get_contents(__DIR__ . '/../public/pelanggan/api/proof.php');
    check(str_contains($customerProof, "require_role('pelanggan')"), 'endpoint bukti Pelanggan hanya untuk Pelanggan');

    echo 'Seluruh pengujian rekonsiliasi pembayaran terlambat lulus.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    if ($db->inTransaction()) $db->rollBack();
    exit(1);
}
if ($db->inTransaction()) $db->rollBack();
foreach ($createdFiles as $path) { $p = proof_resolve($path); if ($p) @unlink($p); }
