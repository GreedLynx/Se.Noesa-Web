<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/menu-notifications.php';
if (me() && me()['role'] !== 'pelanggan') json(['error' => 'Akses ditolak'], 403);
$customer = require_role('pelanggan');
require_once __DIR__ . '/../../../app/daily-quota.php';
require_once __DIR__ . '/../../../app/proof-storage.php';

// Fitur reschedule ditambahkan oleh migrasi 022. Jika tabel belum terpasang
// pada database target, endpoint tetap harus menyajikan daftar/detail pesanan
// daripada mengembalikan 500 yang membuat riwayat tampak kosong.
function reschedule_table_available(): bool
{
    static $available = null;
    if ($available === null) {
        try {
            $available = (bool) q("SELECT to_regclass('public.penawaran_jadwal_ulang')")->fetchColumn();
        } catch (Throwable $e) {
            $available = false;
        }
    }
    return $available;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $request = body();

    if (($request['action'] ?? '') === 'respond_reschedule') {
        $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
        $decision = $request['decision'] ?? '';
        $version = $request['offer_version'] ?? '';
        if (!$id || $id < 1 || !in_array($decision, ['accept', 'reject'], true) || !is_string($version) || $version === '') {
            json(['error' => 'Respons tawaran jadwal tidak valid'], 422);
        }
        try {
            $db = db();
            $db->beginTransaction();
            if ($decision === 'accept') {
                q('LOCK TABLE produk IN SHARE ROW EXCLUSIVE MODE');
                q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');
                q('LOCK TABLE tanggal_libur_operasional IN SHARE ROW EXCLUSIVE MODE');
            }
            $order = q('SELECT id, status, tgl_kirim FROM pesanan WHERE id = ? AND user_id = ? FOR UPDATE', [$id, $customer['id']])->fetch();
            if (!$order) throw new OutOfBoundsException('Pesanan tidak ditemukan');
            $offer = q('SELECT * FROM penawaran_jadwal_ulang WHERE pesanan_id = ? FOR UPDATE', [$id])->fetch();
            if (!$offer || $offer['status_tawaran'] !== 'menunggu' || $offer['status_asal'] !== $order['status']
                || (string) $offer['dibuat_at'] !== $version) {
                throw new DomainException('Tawaran sudah berubah atau tidak lagi tersedia. Muat ulang detail pesanan.');
            }
            if (!(bool) q('SELECT CASE WHEN CAST(? AS timestamptz) > clock_timestamp() THEN 1 ELSE 0 END', [$offer['berlaku_sampai']])->fetchColumn()) {
                q("UPDATE penawaran_jadwal_ulang SET status_tawaran = 'kedaluwarsa' WHERE id = ?", [$offer['id']]);
                throw new DomainException('Masa berlaku tawaran jadwal sudah berakhir');
            }
            if ($decision === 'reject') {
                q("UPDATE pesanan SET status = 'dibatalkan', status_updated_at = clock_timestamp(),
                        sumber_pembatalan = 'pelanggan', status_sebelum_batal = status
                    WHERE id = ?", [$id]);
                q("UPDATE penawaran_jadwal_ulang SET status_tawaran = 'ditolak', ditanggapi_at = clock_timestamp() WHERE id = ?", [$offer['id']]);
            } else {
                $date = $offer['tanggal_usulan'];
                $today = q("SELECT (clock_timestamp() AT TIME ZONE 'Asia/Jakarta')::date")->fetchColumn();
                if ($date <= $today) throw new DomainException('Tanggal usulan sudah lewat. Minta Admin mengirim tanggal pengganti baru.');
                q('SELECT id FROM kuota_harian WHERE tanggal = ? ORDER BY produk_id FOR UPDATE', [$date])->fetchAll();
                $quantities = [];
                foreach (q('SELECT produk_id, SUM(qty) qty FROM detail_pesanan WHERE pesanan_id = ? GROUP BY produk_id ORDER BY produk_id', [$id])->fetchAll() as $item) {
                    $quantities[(int) $item['produk_id']] = (int) $item['qty'];
                }
                if (!$quantities) throw new DomainException('Rincian pesanan tidak tersedia');
                daily_quota_validate(daily_quota_snapshot($date), $quantities);
                $targetStatus = $order['status'] === 'menunggu_validasi' ? 'diproses' : $order['status'];
                $updated = q("UPDATE pesanan SET tgl_kirim =
                        ((CAST(? AS date) + (tgl_kirim AT TIME ZONE 'Asia/Jakarta')::time) AT TIME ZONE 'Asia/Jakarta'),
                        status = ?, status_updated_at = CASE WHEN status <> ? THEN clock_timestamp() ELSE status_updated_at END
                    WHERE id = ? AND user_id = ? AND status = ?", [$date, $targetStatus, $targetStatus, $id, $customer['id'], $order['status']])->rowCount();
                if (!$updated) throw new DomainException('Status pesanan sudah berubah');
                q("UPDATE penawaran_jadwal_ulang SET status_tawaran = 'diterima', ditanggapi_at = clock_timestamp() WHERE id = ?", [$offer['id']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $code = $e instanceof OutOfBoundsException ? 404 : ($e instanceof DomainException ? 409 : 500);
            json(['error' => $code === 500 ? 'Respons tawaran jadwal gagal disimpan' : $e->getMessage()], $code);
        }
        json(['ok' => true, 'status' => $decision === 'reject' ? 'dibatalkan' : $targetStatus]);
    }
    
    if (($request['action'] ?? '') === 'cancel_order') {
        $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) json(['error' => 'ID pesanan tidak valid'], 422);
        try {
            $db = db();
            $db->beginTransaction();
            $order = q('SELECT id FROM pesanan WHERE id = ? AND user_id = ? FOR UPDATE', [$id, $customer['id']])->fetch();
            if (!$order) {
                throw new OutOfBoundsException('Pesanan tidak ditemukan');
            }
            if ((bool) q("SELECT CASE WHEN EXISTS(SELECT 1 FROM penawaran_jadwal_ulang WHERE pesanan_id = ? AND status_tawaran = 'menunggu' AND berlaku_sampai > clock_timestamp()) THEN 1 ELSE 0 END", [$id])->fetchColumn()) {
                throw new DomainException('Gunakan tombol Tolak jadwal untuk merespons tawaran aktif');
            }
            // Waktu diperiksa setelah lock agar request yang menunggu tidak melewati H-2.
            $affected = q("UPDATE pesanan SET status = 'dibatalkan', status_updated_at = clock_timestamp(),
                    sumber_pembatalan = 'pelanggan', status_sebelum_batal = status
                WHERE id = ? AND user_id = ?
                    AND status IN ('menunggu_pembayaran', 'menunggu_validasi', 'diproses')
                    AND (tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date >=
                        (clock_timestamp() AT TIME ZONE 'Asia/Jakarta')::date + 2", [$id, $customer['id']])->rowCount();
            if ($affected) {
                // Penutupan notifikasi ikut transaksi: kegagalan membatalkan seluruh pembatalan.
                menu_notice_close_for_order(
                    (int) $id,
                    'pembatalan_pesanan',
                    'Pesanan dibatalkan oleh pelanggan.',
                    (int) $customer['id']
                );
            }
            $db->commit();
        } catch (OutOfBoundsException $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            json(['error' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            json(['error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            json(['error' => 'Gagal membatalkan pesanan'], 500);
        }
        if (!$affected) json(['error' => 'Status pesanan tidak dapat dibatalkan atau batas akhir H-2 sudah terlewati'], 409);
        json(['ok' => true, 'status' => 'dibatalkan']);
    }

    if (($request['action'] ?? '') === 'save_address') {
        $label = trim($request['label'] ?? 'Rumah');
        $recipient = trim($request['nama_penerima'] ?? '');
        $phone = trim($request['telepon'] ?? '');
        $address = trim($request['alamat'] ?? '');
        if ($label === '' || strlen($label) > 50 || $recipient === '' || strlen($recipient) > 100 || !preg_match('/^[0-9+() .-]{8,20}$/', $phone) || $address === '' || strlen($address) > 2000) {
            json(['error' => 'Lengkapi label, nama penerima, nomor HP, dan alamat dengan benar'], 422);
        }

        try {
            $db = db();
            $db->beginTransaction();
            $count = (int) q('SELECT COUNT(*) FROM alamat_pelanggan WHERE user_id = ?', [$customer['id']])->fetchColumn();
            if ($count >= 20) {
                throw new DomainException('Maksimal 20 alamat tersimpan.');
            }
            $isDefault = !empty($request['is_default']) || $count === 0;
            if ($isDefault) {
                // UPDATE mengosongkan alamat default lama agar hanya satu alamat yang menjadi default.
                q('UPDATE alamat_pelanggan SET is_default = FALSE WHERE user_id = ?', [$customer['id']]);
            }
            $statement = $db->prepare(
                'INSERT INTO alamat_pelanggan (user_id, label, nama_penerima, telepon, alamat, is_default) VALUES (?, ?, ?, ?, ?, ?) RETURNING id'
            );
            $statement->bindValue(1, $customer['id'], PDO::PARAM_INT);
            $statement->bindValue(2, $label);
            $statement->bindValue(3, $recipient);
            $statement->bindValue(4, $phone);
            $statement->bindValue(5, $address);
            $statement->bindValue(6, $isDefault, PDO::PARAM_BOOL);
            $statement->execute();
            $id = (int) $statement->fetchColumn();
            $db->commit();
            json(['ok' => true, 'id' => $id, 'is_default' => $isDefault], 201);
        } catch (DomainException $e) {
            if (db()->inTransaction()) db()->rollBack();
            json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            json(['error' => 'Alamat belum dapat disimpan'], 500);
        }
    }

    if (($request['action'] ?? '') === 'set_default_address') {
        $id = (int) ($request['id'] ?? 0);
        try {
            $db = db();
            $db->beginTransaction();
            $found = q('SELECT id FROM alamat_pelanggan WHERE id = ? AND user_id = ? FOR UPDATE', [$id, $customer['id']])->fetch();
            if (!$found) {
                throw new DomainException('Alamat tidak ditemukan');
            }

            // UPDATE menjadikan alamat terpilih sebagai default dan alamat lain bukan default.
            q('UPDATE alamat_pelanggan SET is_default = (id = ?) WHERE user_id = ?', [$id, $customer['id']]);
            $db->commit();
            json(['ok' => true]);
        } catch (DomainException $e) {
            if (db()->inTransaction()) db()->rollBack();
            json(['error' => $e->getMessage()], 404);
        }
    }

    if (($_POST['action'] ?? '') === 'upload_proof') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) json(['error' => 'ID pesanan tidak valid'], 422);
        // SELECT memastikan pesanan milik pelanggan yang sedang login sebelum menerima bukti pembayaran.
        $order = q('SELECT id, status, bukti_bayar, created_at FROM pesanan WHERE id = ? AND user_id = ?', [$id, $customer['id']])->fetch();
        if (!$order) json(['error' => 'Pesanan tidak ditemukan'], 404);
        if (!in_array($order['status'], ['menunggu_pembayaran', 'menunggu_validasi'], true)) {
            if ($order['status'] === 'dibatalkan') {
                json(['error' => 'Pesanan sudah dibatalkan dan tidak dapat menerima bukti pembayaran.'], 409);
            }
            json(['error' => 'Bukti hanya dapat diunggah untuk pesanan yang menunggu pembayaran atau verifikasi'], 409);
        }
        if (!(bool) q("SELECT CASE WHEN clock_timestamp() < ?::timestamptz + interval '1 hour' THEN 1 ELSE 0 END", [$order['created_at']])->fetchColumn()) {
            json(['error' => 'Waktu satu jam untuk mengirim atau mengganti bukti pembayaran telah berakhir.'], 409);
        }
        if ((bool) q("SELECT CASE WHEN EXISTS(SELECT 1 FROM penawaran_jadwal_ulang WHERE pesanan_id = ? AND status_tawaran = 'menunggu' AND berlaku_sampai > clock_timestamp()) THEN 1 ELSE 0 END", [$id])->fetchColumn()) {
            json(['error' => 'Selesaikan respons tawaran jadwal sebelum mengunggah bukti'], 409);
        }

        $file = $_FILES['proof'] ?? null;
        if (!$file) json(['error' => 'Pilih file bukti pembayaran terlebih dahulu'], 422);

        $payerName = trim($_POST['nama_pembayar'] ?? '');
        if ($payerName === '' || strlen($payerName) > 100) json(['error' => 'Nama pembayar wajib diisi (maksimal 100 karakter)'], 422);

        $storedPath = null;
        $oldPath = (string) ($order['bukti_bayar'] ?? '');
        try {
            $storedPath = proof_store($file, 'payment-' . $id);
            $db = db();
            $db->beginTransaction();
            // Pisahkan lock dan mutasi agar deadline memakai waktu setelah menunggu lock.
            $locked = q('SELECT id FROM pesanan WHERE id = ? AND user_id = ? FOR UPDATE', [$id, $customer['id']])->fetch();
            if (!$locked) throw new DomainException('Pesanan tidak ditemukan');
            if ((bool) q("SELECT CASE WHEN EXISTS(SELECT 1 FROM penawaran_jadwal_ulang WHERE pesanan_id = ? AND status_tawaran = 'menunggu' AND berlaku_sampai > clock_timestamp()) THEN 1 ELSE 0 END", [$id])->fetchColumn()) {
                throw new DomainException('Tawaran jadwal sedang menunggu respons');
            }
            $affected = q(
                "UPDATE pesanan
                SET bukti_bayar = ?, nama_pembayar = ?,
                    status = CASE WHEN status = 'menunggu_pembayaran' THEN 'menunggu_validasi' ELSE status END,
                    status_updated_at = CASE WHEN status = 'menunggu_pembayaran' THEN clock_timestamp() ELSE status_updated_at END
                WHERE id = ? AND user_id = ?
                    AND status IN ('menunggu_pembayaran', 'menunggu_validasi')
                    AND clock_timestamp() < created_at + interval '1 hour'",
                [$storedPath, $payerName, $id, $customer['id']]
            )->rowCount();
            if (!$affected) throw new DomainException('Status pesanan sudah berubah atau tenggat pembayaran satu jam telah berakhir');
            $db->commit();
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            if ($storedPath !== null) proof_delete_if_unreferenced($storedPath);
            json(['error' => $e->getMessage()], $e instanceof DomainException ? 409 : 500);
        }
        if ($oldPath !== '' && $oldPath !== $storedPath) proof_delete_if_unreferenced($oldPath);
        json(['ok' => true, 'bukti_bayar' => $storedPath]);
    }

    $name = trim($request['nama_pemesan'] ?? '');
    $items = $request['items'] ?? [];
    $date = $request['tgl_kirim'] ?? '';
    $time = trim((string) ($request['waktu_kirim'] ?? ''));
    $time = $time !== '' ? $time : '12:00';
    $note = trim($request['keterangan'] ?? '');
    $addressId = (int) ($request['alamat_id'] ?? 0);
    // SELECT mengambil alamat tersimpan hanya jika memang dimiliki pelanggan yang sedang login.
    $savedAddress = $addressId ? q('SELECT nama_penerima, telepon, alamat FROM alamat_pelanggan WHERE id = ? AND user_id = ?', [$addressId, $customer['id']])->fetch() : false;
    $recipient = trim($request['nama_penerima'] ?? '');
    $phone = trim($request['telepon'] ?? '');
    $address = trim($request['alamat'] ?? '');
    if ($savedAddress) {
        $recipient = $savedAddress['nama_penerima'];
        $phone = $savedAddress['telepon'];
        $address = $savedAddress['alamat'];
    }

    if ($name === '' || strlen($name) > 100 || $recipient === '' || strlen($recipient) > 100 || !preg_match('/^[0-9+() .-]{8,20}$/', $phone) || $address === '' || strlen($address) > 2000 || !is_array($items) || !count($items)) {
        json(['error' => 'Nama pemesan, penerima, nomor HP, alamat, dan pilihan menu wajib diisi dengan benar'], 422);
    }
    if (strlen($note) > 120) json(['error' => 'Catatan maksimal 120 karakter'], 422);

    $zone = new DateTimeZone('Asia/Jakarta');
    $delivery = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $zone);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $minimumDelivery = (new DateTimeImmutable('now', $zone))->modify('+7 days')->format('Y-m-d');
    $deliveryTooSoon = $delivery && $delivery->format('Y-m-d') < $minimumDelivery;
    if (!$delivery || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count'])) || $deliveryTooSoon) {
        json(['error' => 'Tanggal pengiriman harus minimal 7 hari dari tanggal pemesanan'], 422);
    }
    if (count($items) > 50) json(['error' => 'Maksimal 50 jenis menu per pesanan'], 422);

    $quantities = [];
    $requestedTotal = 0;
    foreach ($items as $line) {
        if (!is_array($line)) json(['error' => 'Pilihan menu tidak valid'], 422);
        $productId = filter_var($line['id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($line['qty'] ?? null, FILTER_VALIDATE_INT);
        if (!$productId || $productId < 1 || !$quantity || $quantity < 1 || $quantity > SENOESA_MAX_ORDER_QTY) {
            json(['error' => 'Jumlah menu tidak valid'], 422);
        }
        $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        if ($quantities[$productId] > SENOESA_MAX_ORDER_QTY) {
            json(['error' => 'Satu pesanan maksimal ' . SENOESA_MAX_ORDER_QTY . ' porsi'], 422);
        }
        $requestedTotal += $quantity;
        if ($requestedTotal > SENOESA_MAX_ORDER_QTY) {
            json(['error' => 'Satu pesanan maksimal ' . SENOESA_MAX_ORDER_QTY . ' porsi'], 422);
        }
    }

    ksort($quantities);
    try {
        $db = db();
        $db->beginTransaction();
        // Serialisasi semua checkout, termasuk yang memilih produk berbeda.
        // Urutan lock kanonik menjaga checkout, perubahan kuota/libur, respons
        // pelanggan, dan konfirmasi penggantian tidak saling mendahului.
        daily_quota_lock_tables();
        // SELECT ... FOR UPDATE mengunci baris kuota tanggal pengiriman sampai transaksi selesai.
        q('SELECT id FROM kuota_harian WHERE tanggal = ? ORDER BY produk_id FOR UPDATE', [$date])->fetchAll();
        // SELECT ... FOR UPDATE memilih sekaligus mengunci jadwal PO aktif yang akan dipakai.
        $schedule = q('SELECT id FROM jadwal_po WHERE aktif ORDER BY tgl_buka LIMIT 1 FOR UPDATE')->fetch();
        if (!$schedule) {
            // INSERT membuat jadwal PO default hanya jika belum ada jadwal aktif.
            $id = (int) q("INSERT INTO jadwal_po (nama, tgl_buka, tgl_tutup, kapasitas, aktif) VALUES ('Default', '2026-01-01', '2099-12-31', 50, true) RETURNING id")->fetchColumn();
            $schedule = ['id' => $id];
        }

        $deliveryDate = $delivery->format('Y-m-d');
        $snapshot = daily_quota_snapshot($deliveryDate);
        $lines = daily_quota_validate($snapshot, $quantities);
        $total = 0;
        $portions = 0;
        foreach ($lines as $line) {
            $product = $line['product'];
            $quantity = $line['qty'];
            $total += (int) $product['harga'] * $quantity;
            $portions += $quantity;
            if ($total > 2147483647) throw new DomainException('Total pesanan melebihi batas transaksi.');
        }

        $code = 'SN-' . (new DateTimeImmutable('now', $zone))->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $orderId = (int) q(
            "INSERT INTO pesanan (kode, user_id, jadwal_id, nama_pemesan, keterangan, pax, total, status, tgl_kirim, alamat_pengiriman, telepon_penerima)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'menunggu_pembayaran', ?, ?, ?) RETURNING id",
            [$code, $customer['id'], $schedule['id'], $recipient, $note ?: null, $portions, $total, $delivery->format('Y-m-d H:i:sP'), $address, $phone]
        )->fetchColumn();
        foreach ($lines as $line) {
            $product = $line['product'];
            $quantity = $line['qty'];
            q('INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, ?, ?)', [$orderId, $product['id'], $quantity, $product['harga']]);
        }
        $db->commit();
        json(['ok' => true, 'id' => $orderId, 'kode' => $code, 'total' => $total], 201);
    } catch (DomainException $e) {
        if (db()->inTransaction()) db()->rollBack();
        json(['error' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        error_log('[Se.Noesa order] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        json(['error' => 'Pesanan belum dapat disimpan. Coba beberapa saat lagi.'], 500);
    }
}

if (isset($_GET['addresses'])) {
    json([
        'addresses' => q(
            'SELECT id, label, nama_penerima, telepon, alamat, is_default FROM alamat_pelanggan WHERE user_id = ? ORDER BY is_default DESC, id',
            [$customer['id']]
        )->fetchAll(),
    ]);
}

if (isset($_GET['id'])) {
    $order = q(
        "SELECT id, kode, nama_pemesan, nama_pembayar, telepon_penerima, alamat_pengiriman, keterangan, pax, total, status, status_updated_at, bukti_bayar, foto_kiriman, tgl_kirim, created_at,
            created_at + interval '1 hour' AS payment_expires_at,
            ((tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date - 1)::timestamp AT TIME ZONE 'Asia/Jakarta' AS cancel_before,
            COALESCE(status IN ('menunggu_pembayaran', 'menunggu_validasi', 'diproses') AND
                (tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date >= (clock_timestamp() AT TIME ZONE 'Asia/Jakarta')::date + 2, FALSE) AS can_cancel,
            COALESCE(status IN ('menunggu_pembayaran', 'menunggu_validasi') AND
                clock_timestamp() < created_at + interval '1 hour', FALSE) AS can_upload_proof
        FROM pesanan WHERE id = ? AND user_id = ?",
        [(int) $_GET['id'], $customer['id']]
    )->fetch();
    if (!$order) json(['error' => 'Pesanan tidak ditemukan'], 404);

    $order['reschedule_offer'] = null;
    $order['can_respond_reschedule'] = false;
    if (reschedule_table_available()) {
        $offer = q("SELECT id, status_asal, tanggal_asal, alasan, tanggal_usulan,
                CASE WHEN status_tawaran = 'menunggu' AND berlaku_sampai <= clock_timestamp() THEN 'kedaluwarsa' ELSE status_tawaran END status_tawaran,
                dibuat_at, berlaku_sampai, ditanggapi_at
            FROM penawaran_jadwal_ulang WHERE pesanan_id = ?", [$order['id']])->fetch();
        $order['reschedule_offer'] = $offer ?: null;
        $order['can_respond_reschedule'] = $offer && $offer['status_tawaran'] === 'menunggu'
            && $offer['status_asal'] === $order['status'];
        if ($order['can_respond_reschedule']) $order['can_upload_proof'] = false;
    }

    foreach (['payment_expires_at', 'cancel_before'] as $field) {
        if ($order[$field] !== null) {
            $order[$field] = (new DateTimeImmutable($order[$field]))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format(DateTimeInterface::ATOM);
        }
    }

    // SELECT dengan JOIN menggabungkan detail pesanan dan data produk untuk halaman rincian.
    $order['items'] = q(
        'SELECT pr.nama, pr.gambar, d.qty, d.harga, d.qty * d.harga AS subtotal FROM detail_pesanan d JOIN produk pr ON pr.id = d.produk_id WHERE d.pesanan_id = ? ORDER BY pr.nama',
        [$order['id']]
    )->fetchAll();
    $order['menu_notifications'] = menu_notice_order_notifications(
        (int) $order['id'],
        (int) $customer['id']
    );
    $order['foto_kiriman_list'] = q(
        'SELECT path FROM pesanan_foto_kiriman WHERE pesanan_id = ? ORDER BY id',
        [$order['id']]
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!$order['foto_kiriman_list'] && !empty($order['foto_kiriman'])) {
        $order['foto_kiriman_list'] = [$order['foto_kiriman']];
    }
    json($order);
}

$status = $_GET['status'] ?? '';
// UPDATE: Menambahkan 'dibatalkan' ke dalam status yang diizinkan
$allowedStatuses = ['menunggu_pembayaran', 'menunggu_validasi', 'diproses', 'selesai', 'ditolak', 'dibatalkan'];
$filter = in_array($status, $allowedStatuses, true) ? 'AND status = ?' : '';
$queryArgs = [$customer['id']];
if ($filter) $queryArgs[] = $status;

$rescheduleColumn = reschedule_table_available()
    ? "EXISTS (SELECT 1 FROM penawaran_jadwal_ulang r WHERE r.pesanan_id = pesanan.id AND r.status_tawaran = 'menunggu' AND r.berlaku_sampai > clock_timestamp()) reschedule_pending"
    : "FALSE AS reschedule_pending";
$orders = q(
    "SELECT id, kode, nama_pemesan, total, status, status_updated_at, alasan_penolakan, tgl_kirim, created_at,
        $rescheduleColumn
        FROM pesanan WHERE user_id = ? $filter ORDER BY created_at DESC LIMIT 50",
    $queryArgs
)->fetchAll();
foreach ($orders as &$order) {
    $order['items'] = q(
        'SELECT pr.nama, pr.gambar, d.qty, d.harga FROM detail_pesanan d JOIN produk pr ON pr.id = d.produk_id WHERE d.pesanan_id = ? ORDER BY d.id LIMIT 3',
        [$order['id']]
    )->fetchAll();
}
unset($order);
json(['orders' => $orders]);
