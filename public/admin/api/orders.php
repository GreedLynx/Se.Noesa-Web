<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
require_once __DIR__ . '/../../../app/daily-quota.php';
require_once __DIR__ . '/../../../app/order-cancellation.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'upload_dispatch') {
        $id = (int) ($_POST['id'] ?? 0);
        // SELECT memastikan pesanan berstatus diproses sebelum foto pengiriman disimpan.
        $order = q("SELECT id
            FROM pesanan
            WHERE id = ? AND status = 'diproses'", [$id])->fetch();
        if (!$order) {
            json(['error' => 'Pesanan tidak sedang diproses'], 409);
        }
        $file = $_FILES['foto_kiriman'] ?? null;
        if (!$file || !isset($file['name'], $file['error'], $file['tmp_name'], $file['size'])) {
            json(['error' => 'Pilih foto makanan yang akan dikirim'], 422);
        }
        $uploads = [];
        foreach ((array) $file['name'] as $index => $name) {
            $error = is_array($file['error']) ? ($file['error'][$index] ?? UPLOAD_ERR_NO_FILE) : $file['error'];
            $tmpName = is_array($file['tmp_name']) ? ($file['tmp_name'][$index] ?? '') : $file['tmp_name'];
            $size = is_array($file['size']) ? ($file['size'][$index] ?? 0) : $file['size'];
            if ($error !== UPLOAD_ERR_OK) {
                json(['error' => $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                    ? 'Ukuran foto maksimal 2 MB per foto'
                    : 'Pilih foto makanan yang akan dikirim'], 422);
            }
            if ($size > 2 * 1024 * 1024) {
                json(['error' => 'Ukuran foto maksimal 2 MB per foto'], 422);
            }
            $image = @getimagesize($tmpName);
            $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = $image['mime'] ?? '';
            if (!isset($types[$mime])) {
                json(['error' => 'Format foto harus JPG, PNG, atau WebP'], 422);
            }
            $uploads[] = ['tmp_name' => $tmpName, 'extension' => $types[$mime]];
        }
        if (!$uploads) {
            json(['error' => 'Pilih foto makanan yang akan dikirim'], 422);
        }

        $folder = __DIR__ . '/../../uploads/dispatch';
        if (!is_dir($folder) && !mkdir($folder, 0775, true)) {
            json(['error' => 'Folder foto kiriman tidak dapat dibuat'], 500);
        }
        $savedPaths = [];
        try {
            foreach ($uploads as $upload) {
                $filename = 'dispatch-' . $id . '-' . bin2hex(random_bytes(10)) . '.' . $upload['extension'];
                if (!move_uploaded_file($upload['tmp_name'], $folder . '/' . $filename)) {
                    throw new RuntimeException('Foto makanan gagal diunggah');
                }
                $savedPaths[] = 'uploads/dispatch/' . $filename;
            }

            $db = db();
            $db->beginTransaction();
            // Kunci status sebelum menambahkan galeri agar pembatalan tidak tertimpa.
            $order = q("SELECT id FROM pesanan WHERE id = ? AND status = 'diproses' FOR UPDATE", [$id])->fetch();
            if (!$order) {
                throw new DomainException('Status pesanan sudah berubah');
            }
            foreach ($savedPaths as $path) {
                q('INSERT INTO pesanan_foto_kiriman (pesanan_id, path) VALUES (?, ?)', [$id, $path]);
            }
            // Pertahankan nilai kolom lama sebagai foto pertama untuk kompatibilitas.
            q('UPDATE pesanan SET foto_kiriman = COALESCE(foto_kiriman, ?) WHERE id = ?', [$savedPaths[0], $id]);
            $db->commit();
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($savedPaths as $path) {
                @unlink($folder . '/' . basename($path));
            }
            json(['error' => $e instanceof DomainException ? $e->getMessage() : 'Foto kiriman gagal disimpan'], $e instanceof DomainException ? 409 : 500);
        }
        json(['ok' => true, 'foto_kiriman' => $savedPaths[0], 'foto_kiriman_list' => $savedPaths]);
    }
    $request = body();
    if (($request['action'] ?? '') === 'review_cancellation') {
        $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            json(['error' => 'ID pesanan tidak valid'], 422);
        }
        try {
            $db = db();
            $db->beginTransaction();
            $order = q('SELECT id, status FROM pesanan WHERE id = ? FOR UPDATE', [$id])->fetch();
            if (!$order) {
                throw new OutOfBoundsException('Pesanan tidak ditemukan');
            }
            if ($order['status'] !== 'dibatalkan') {
                throw new DomainException('Hanya pembatalan pesanan yang dapat ditinjau');
            }
            // Tinjauan ulang tidak mengubah peninjau pertama atau waktu pembatalan.
            q('UPDATE pesanan
                SET pembatalan_ditinjau_at = clock_timestamp(), pembatalan_ditinjau_oleh = ?
                WHERE id = ? AND pembatalan_ditinjau_at IS NULL', [(int) me()['id'], $id]);
            $review = cancellation_metadata(q('SELECT * FROM v_pembatalan_pesanan WHERE id = ?', [$id])->fetch());
            $db->commit();
        } catch (Throwable $error) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $code = $error instanceof OutOfBoundsException ? 404 : ($error instanceof DomainException ? 409 : 500);
            json(['error' => $code === 500 ? 'Pembatalan belum dapat ditandai ditinjau' : $error->getMessage()], $code);
        }
        json(array_merge(['ok' => true], $review));
    }
    if (($request['action'] ?? '') === 'check_item') {
        // UPDATE ... FROM mengubah checklist hanya jika item milik pesanan yang sedang diproses.
        $item = q("UPDATE detail_pesanan d
            SET sudah_dicek = ?
            FROM pesanan p
            WHERE d.id = ? AND d.pesanan_id = p.id AND p.status = 'diproses'
            RETURNING d.id", [(bool) ($request['checked'] ?? false), (int) ($request['item_id'] ?? 0)])->fetch();
        if (!$item) {
            json(['error' => 'Item tidak ditemukan atau pesanan belum diproses'], 409);
        }
        json(['ok' => true]);
    }
    $actions = ['validate' => ['menunggu_validasi', 'diproses'], 'reject' => ['menunggu_validasi', 'ditolak'], 'complete' => ['diproses', 'selesai']];
    $transition = $actions[$request['action'] ?? ''] ?? null;
    if (!$transition) {
        json(['error' => 'Aksi tidak valid'], 422);
    }
    if (($request['action'] ?? '') === 'reject') {
        $db = db();
        $db->beginTransaction();
        try {
            // FOR UPDATE mengunci pesanan agar validasi status penolakan tidak berbenturan dengan request lain.
            $current = q("SELECT id
                FROM pesanan
                WHERE id = ? AND status = 'menunggu_validasi' FOR UPDATE", [(int) ($request['id'] ?? 0)])->fetch();
            if (!$current) {
                $db->rollBack();
                json(['error' => 'Status pesanan sudah berubah atau tidak sesuai'], 409);
            }
            // UPDATE mengubah status dan timestamp penolakan dalam transaksi yang sama.
            q("UPDATE pesanan
                SET status = 'ditolak', status_updated_at = now()
                WHERE id = ?", [$current['id']]);
            $db->commit();
            json(['ok' => true, 'status' => 'ditolak']);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            json(['error' => 'Status pesanan belum dapat diperbarui'], 500);
        }
    }
    if (($request['action'] ?? '') === 'complete') {
        // COUNT memastikan seluruh detail pesanan sudah dicek sebelum pesanan diselesaikan.
        $unchecked = (int) q("SELECT COUNT(*)
            FROM detail_pesanan
            WHERE pesanan_id = ? AND sudah_dicek = FALSE", [(int) ($request['id'] ?? 0)])->fetchColumn();
        if ($unchecked) {
            json(['error' => 'Centang semua menu yang sudah disiapkan terlebih dahulu'], 409);
        }
        // SELECT mengambil foto kiriman hanya untuk pesanan yang masih diproses.
        $photo = q("SELECT foto_kiriman
            FROM pesanan
            WHERE id = ? AND status = 'diproses'", [(int) ($request['id'] ?? 0)])->fetch();
        if (empty($photo['foto_kiriman'])) {
            json(['error' => 'Unggah foto makanan yang akan dikirim terlebih dahulu'], 409);
        }
    }
    if (($request['action'] ?? '') === 'validate') {
        // SELECT memeriksa bukti pembayaran sebelum status pesanan divalidasi.
        $proof = q("SELECT bukti_bayar
            FROM pesanan
            WHERE id = ? AND status = 'menunggu_validasi'", [(int) ($request['id'] ?? 0)])->fetch();
        if (!$proof) {
            json(['error' => 'Pesanan tidak ditemukan atau sudah berubah status'], 409);
        }
        if (empty($proof['bukti_bayar'])) {
            json(['error' => 'Pelanggan belum mengunggah bukti pembayaran'], 409);
        }
    }
    // UPDATE status bersyarat: status lama harus cocok agar transisi tidak menimpa perubahan terbaru.
    $affectedRows = q('UPDATE pesanan
        SET status = ?, status_updated_at = now()
        WHERE id = ? AND status = ?', [$transition[1], (int) ($request['id'] ?? 0), $transition[0]])->rowCount();
    json($affectedRows ? ['ok' => true, 'status' => $transition[1]] : ['error' => 'Status pesanan sudah berubah atau tidak sesuai'], $affectedRows ? 200 : 409);
}
if (isset($_GET['id'])) {
    // LEFT JOIN menampilkan nama akun jika terhubung; jika tidak, nama pemesan tetap tersedia.
    $order = q("SELECT p.*, COALESCE(u.username, p.nama_pemesan) akun_pemesan, u.phone,
        c.cancellation_source, c.status_before_cancel, c.cancelled_at, c.reviewed_at,
        c.reviewed_by, c.reviewed_by_name, c.needs_payment_review, c.production_stop_required
        FROM pesanan p
        LEFT JOIN users u
        ON u.id = p.user_id
        LEFT JOIN v_pembatalan_pesanan c ON c.id = p.id
        WHERE p.id = ?", [(int) $_GET['id']])->fetch();
    if (!$order) {
        json(['error' => 'Pesanan tidak ditemukan'], 404);
    }
    // JOIN detail dan produk untuk menyajikan menu, harga, dan status checklist per item.
    $order['menu'] = q("SELECT pr.nama, pr.gambar, d.id item_id, d.sudah_dicek, d.qty, d.harga, d.qty * d.harga subtotal
        FROM detail_pesanan d
        JOIN produk pr
        ON pr.id = d.produk_id
        WHERE d.pesanan_id = ?
        ORDER BY pr.nama", [$order['id']])->fetchAll();
    $order['foto_kiriman_list'] = q(
        'SELECT path FROM pesanan_foto_kiriman WHERE pesanan_id = ? ORDER BY id',
        [$order['id']]
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!$order['foto_kiriman_list'] && !empty($order['foto_kiriman'])) {
        $order['foto_kiriman_list'] = [$order['foto_kiriman']];
    }
    json(cancellation_metadata($order));
}
$tab = $_GET['tab'] ?? 'validasi';
$statusFilter = match ($tab) {
    'semua' => "('menunggu_pembayaran','menunggu_validasi','diproses','selesai','ditolak','dibatalkan')",
    'pembayaran' => "('menunggu_pembayaran')",
    'proses' => "('diproses')",
    'selesai' => "('selesai')",
    'ditolak' => "('ditolak')",
    'dibatalkan' => "('dibatalkan')",
    default => "('menunggu_validasi')"
};
$review = $_GET['review'] ?? '';
if (!in_array($review, ['', 'unreviewed', 'reviewed'], true)) {
    json(['error' => 'Filter tinjauan tidak valid'], 422);
}
$reviewFilter = $tab === 'dibatalkan' && $review !== ''
    ? ($review === 'unreviewed' ? 'AND c.reviewed_at IS NULL' : 'AND c.reviewed_at IS NOT NULL') : '';
$orderBy = $tab === 'dibatalkan' ? 'p.status_updated_at DESC NULLS LAST, p.id DESC'
    : "CASE p.status WHEN 'menunggu_pembayaran' THEN 0 WHEN 'menunggu_validasi' THEN 1 WHEN 'diproses' THEN 2 WHEN 'selesai' THEN 3 ELSE 4 END,
        CASE WHEN p.status = 'selesai' THEN COALESCE(p.status_updated_at, p.created_at) END DESC,
        p.created_at DESC";
$search = '%' . trim($_GET['q'] ?? '') . '%';
// SELECT memfilter pesanan menurut tab/status dan kata kunci nama atau kode, lalu mengurutkan hasil.
$items = q("SELECT p.id, p.kode, p.nama_pemesan, p.keterangan, p.pax, p.total, p.status, p.status_updated_at, p.tgl_kirim, p.created_at,
        c.cancellation_source, c.status_before_cancel, c.cancelled_at, c.reviewed_at,
        c.reviewed_by, c.reviewed_by_name, c.needs_payment_review, c.production_stop_required
    FROM pesanan p
    LEFT JOIN v_pembatalan_pesanan c ON c.id = p.id
    WHERE p.status IN $statusFilter AND(p.nama_pemesan ILIKE ? OR p.kode ILIKE ?) $reviewFilter
    ORDER BY $orderBy", [$search, $search])->fetchAll();
$items = array_map('cancellation_metadata', $items);
// Satu query COUNT dengan FILTER menghitung total dan jumlah untuk setiap status.
$count = q("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE status = 'menunggu_pembayaran') payment, COUNT(*) FILTER (WHERE status = 'menunggu_validasi') pending, COUNT(*) FILTER (WHERE status = 'diproses') processing, COUNT(*) FILTER (WHERE status = 'selesai') completed, COUNT(*) FILTER (WHERE status = 'ditolak') rejected, COUNT(*) FILTER (WHERE status = 'dibatalkan') cancelled,
        COUNT(*) FILTER (WHERE status = 'dibatalkan' AND pembatalan_ditinjau_at IS NULL) cancellation_unreviewed
    FROM pesanan")->fetch();
$date = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
try {
    $quota = daily_quota_snapshot($date, true);
} catch (DomainException $e) {
    json(['error' => $e->getMessage()], 422);
}
$capacity = ['kapasitas' => $quota['maxKitchen'], 'pakai' => $quota['kitchenUsed']];
json(['items' => $items, 'counts' => $count, 'capacity' => $capacity]);
