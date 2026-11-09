<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
require_once __DIR__ . '/../../../app/daily-quota.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'upload_dispatch') {
        $id = (int) ($_POST['id'] ?? 0);
        $order = q("SELECT id
            FROM pesanan
            WHERE id = ? AND status = 'diproses'", [$id])->fetch();
        if (!$order) {
            json(['error' => 'Pesanan tidak sedang diproses'], 409);
        }
        $file = $_FILES['foto_kiriman'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            json(['error' => 'Pilih foto makanan yang akan dikirim'], 422);
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            json(['error' => 'Ukuran foto maksimal 2 MB'], 422);
        }
        $image = @getimagesize($file['tmp_name']);
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $image['mime'] ?? '';
        if (!isset($types[$mime])) {
            json(['error' => 'Format foto harus JPG, PNG, atau WebP'], 422);
        }
        $folder = __DIR__ . '/../../uploads/dispatch';
        if (!is_dir($folder) && !mkdir($folder, 0775, true)) {
            json(['error' => 'Folder foto kiriman tidak dapat dibuat'], 500);
        }
        $filename = 'dispatch-' . $id . '-' . bin2hex(random_bytes(10)) . '.' . $types[$mime];
        if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $filename)) {
            json(['error' => 'Foto makanan gagal diunggah'], 500);
        }
        $path = 'uploads/dispatch/' . $filename;
        try {
            q('UPDATE pesanan
                SET foto_kiriman = ?
                WHERE id = ?', [$path, $id]);
        } catch (Throwable $e) {
            @unlink($folder . '/' . $filename);
            json(['error' => 'Foto kiriman gagal disimpan'], 500);
        }
        json(['ok' => true, 'foto_kiriman' => $path]);
    }
    $request = body();
    if (($request['action'] ?? '') === 'check_item') {
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
            $current = q("SELECT id
                FROM pesanan
                WHERE id = ? AND status = 'menunggu_validasi' FOR UPDATE", [(int) ($request['id'] ?? 0)])->fetch();
            if (!$current) {
                $db->rollBack();
                json(['error' => 'Status pesanan sudah berubah atau tidak sesuai'], 409);
            }
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
        $unchecked = (int) q("SELECT COUNT(*)
            FROM detail_pesanan
            WHERE pesanan_id = ? AND sudah_dicek = FALSE", [(int) ($request['id'] ?? 0)])->fetchColumn();
        if ($unchecked) {
            json(['error' => 'Centang semua menu yang sudah disiapkan terlebih dahulu'], 409);
        }
        $photo = q("SELECT foto_kiriman
            FROM pesanan
            WHERE id = ? AND status = 'diproses'", [(int) ($request['id'] ?? 0)])->fetch();
        if (empty($photo['foto_kiriman'])) {
            json(['error' => 'Unggah foto makanan yang akan dikirim terlebih dahulu'], 409);
        }
    }
    if (($request['action'] ?? '') === 'validate') {
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
    $affectedRows = q('UPDATE pesanan
        SET status = ?, status_updated_at = now()
        WHERE id = ? AND status = ?', [$transition[1], (int) ($request['id'] ?? 0), $transition[0]])->rowCount();
    json($affectedRows ? ['ok' => true, 'status' => $transition[1]] : ['error' => 'Status pesanan sudah berubah atau tidak sesuai'], $affectedRows ? 200 : 409);
}
if (isset($_GET['id'])) {
    $order = q("SELECT p.*, COALESCE(u.username, p.nama_pemesan) akun_pemesan, u.phone
        FROM pesanan p
        LEFT JOIN users u
        ON u.id = p.user_id
        WHERE p.id = ?", [(int) $_GET['id']])->fetch();
    if (!$order) {
        json(['error' => 'Pesanan tidak ditemukan'], 404);
    }
    $order['menu'] = q("SELECT pr.nama, pr.gambar, d.id item_id, d.sudah_dicek, d.qty, d.harga, d.qty * d.harga subtotal
        FROM detail_pesanan d
        JOIN produk pr
        ON pr.id = d.produk_id
        WHERE d.pesanan_id = ?
        ORDER BY pr.nama", [$order['id']])->fetchAll();
    json($order);
}
$statusFilter = match (($_GET['tab'] ?? 'validasi')) {
    'semua' => "('menunggu_pembayaran','menunggu_validasi','diproses','selesai','ditolak')",
    'pembayaran' => "('menunggu_pembayaran')",
    'proses' => "('diproses')",
    'selesai' => "('selesai')",
    'ditolak' => "('ditolak')",
    default => "('menunggu_validasi')"
};
$search = '%' . trim($_GET['q'] ?? '') . '%';
$items = q("SELECT p.id, p.kode, p.nama_pemesan, p.keterangan, p.pax, p.total, p.status, p.status_updated_at, p.tgl_kirim, p.created_at
    FROM pesanan p
    WHERE p.status IN $statusFilter AND(p.nama_pemesan ILIKE ? OR p.kode ILIKE ?)
    ORDER BY CASE p.status WHEN 'menunggu_pembayaran' THEN 0 WHEN 'menunggu_validasi' THEN 1 WHEN 'diproses' THEN 2 WHEN 'selesai' THEN 3 ELSE 4 END, p.created_at DESC", [$search, $search])->fetchAll();
$count = q("SELECT COUNT(*) total, COUNT(*) FILTER (WHERE status = 'menunggu_pembayaran') payment, COUNT(*) FILTER (WHERE status = 'menunggu_validasi') pending, COUNT(*) FILTER (WHERE status = 'diproses') processing, COUNT(*) FILTER (WHERE status = 'selesai') completed, COUNT(*) FILTER (WHERE status = 'ditolak') rejected
    FROM pesanan")->fetch();
$date = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
try {
    $quota = daily_quota_snapshot($date, true);
} catch (DomainException $e) {
    json(['error' => $e->getMessage()], 422);
}
$capacity = ['kapasitas' => $quota['maxKitchen'], 'pakai' => $quota['kitchenUsed']];
json(['items' => $items, 'counts' => $count, 'capacity' => $capacity]);
