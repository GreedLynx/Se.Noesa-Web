<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/menu-notifications.php';

// Respons lokal tanpa JSON_NUMERIC_CHECK agar nomor telepon tetap string.
function mnc_json($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function mnc_iso(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))
            ->setTimezone(new DateTimeZone('Asia/Jakarta'))
            ->format(DateTimeInterface::ATOM);
    } catch (Throwable $error) {
        return null;
    }
}

$user = me();
if (!$user || ($user['role'] ?? '') !== 'pelanggan') {
    mnc_json(['error' => 'Unauthorized'], 401);
}
$userId = (int) $user['id'];

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $error): void {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log('[Se.Noesa pelanggan menu-notifications API] ' . $error);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Notifikasi menu belum dapat diproses'], JSON_UNESCAPED_UNICODE);
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $request = body();
    $action = (string) ($request['action'] ?? '');

    if ($action === 'read') {
        $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            mnc_json(['error' => 'ID notifikasi tidak valid'], 422);
        }

        $owned = q(
            "SELECT 1
             FROM pemberitahuan_perubahan_menu n
             WHERE n.id = ?
               AND EXISTS (
                   SELECT 1
                   FROM pemberitahuan_perubahan_menu_pesanan r
                   JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
                   JOIN pesanan p ON p.id = d.pesanan_id
                   WHERE r.pemberitahuan_id = n.id
                     AND p.user_id = ?
                     AND p.sumber = 'online'
               )",
            [$id, $userId]
        )->fetchColumn();
        if (!$owned) {
            mnc_json(['error' => 'Notifikasi tidak ditemukan'], 404);
        }

        $updated = q(
            "UPDATE pemberitahuan_perubahan_menu_pesanan r
             SET dibaca_at = now()
             FROM detail_pesanan d
             JOIN pesanan p ON p.id = d.pesanan_id
             WHERE r.detail_pesanan_id = d.id
               AND r.pemberitahuan_id = ?
               AND p.user_id = ?
               AND p.sumber = 'online'
               AND r.dibaca_at IS NULL",
            [$id, $userId]
        )->rowCount();

        mnc_json([
            'ok' => true,
            'dibaca' => $updated,
            'belum_dibaca' => menu_notice_customer_unread_count($userId),
        ]);
    }

    if ($action !== 'respond') {
        mnc_json(['error' => 'Aksi tidak valid'], 422);
    }

    $recipientId = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
    if ($recipientId === false || $recipientId < 1) {
        mnc_json(['error' => 'ID penerima tidak valid'], 422);
    }

    $opsiKode = trim((string) ($request['opsi'] ?? ''));
    if ($opsiKode === '') {
        mnc_json(['error' => 'Pilih tindakan terlebih dahulu'], 422);
    }

    $note = trim((string) ($request['catatan_respons'] ?? ''));
    if (mb_strlen($note) > 1000) {
        mnc_json(['error' => 'Catatan maksimal 1000 karakter'], 422);
    }

    $db = db();
    $db->beginTransaction();
    try {
        q('LOCK TABLE produk IN SHARE ROW EXCLUSIVE MODE');
        q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');

        $row = q(
            "SELECT r.id, r.pemberitahuan_id, r.status_respons,
                    r.produk_lama_id, r.qty_lama, r.harga_lama,
                    p.id AS pesanan_id, p.status AS status_pesanan,
                    p.user_id,
                    (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date AS tanggal_kirim
             FROM pemberitahuan_perubahan_menu_pesanan r
             JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
             JOIN pesanan p ON p.id = d.pesanan_id
             WHERE r.id = ?
               AND p.user_id = ?
               AND p.sumber = 'online'
             FOR UPDATE OF r, p",
            [$recipientId, $userId]
        )->fetch();

        if (!$row) {
            $db->rollBack();
            mnc_json(['error' => 'Notifikasi tidak ditemukan'], 404);
        }
        if ($row['status_respons'] !== 'menunggu_respons') {
            $db->rollBack();
            mnc_json([
                'error' => 'Pilihan sudah dikirim dan sedang menunggu Admin. Hubungi Admin bila perlu perubahan.',
            ], 409);
        }
        if (!in_array($row['status_pesanan'], MENU_NOTICE_ACTIVE_STATUSES, true)) {
            $db->rollBack();
            mnc_json([
                'error' => 'Pesanan ini sudah tidak aktif sehingga pilihan tidak dapat dikirim.',
            ], 409);
        }

        $option = q(
            'SELECT id, kode_opsi, label
             FROM pemberitahuan_perubahan_menu_opsi
             WHERE pemberitahuan_id = ?
               AND kode_opsi = ?',
            [(int) $row['pemberitahuan_id'], $opsiKode]
        )->fetch();
        if (!$option) {
            $db->rollBack();
            mnc_json(['error' => 'Tindakan yang dipilih tidak tersedia'], 422);
        }

        $replacement = null;
        $replacementId = null;
        $snapshotKode = null;
        $snapshotNama = null;
        $snapshotQty = null;
        $snapshotHarga = null;
        $status = 'menunggu_tindak_lanjut';

        if ($opsiKode === 'menu_pengganti') {
            $requestedId = filter_var(
                $request['produk_pengganti_id'] ?? null,
                FILTER_VALIDATE_INT
            );
            if ($requestedId === false || $requestedId < 1) {
                $db->rollBack();
                mnc_json(['error' => 'Pilih menu pengganti terlebih dahulu'], 422);
            }
            if ($row['produk_lama_id'] !== null && (int) $row['produk_lama_id'] === $requestedId) {
                $db->rollBack();
                mnc_json(['error' => 'Menu pengganti harus berbeda dari menu lama'], 422);
            }

            $availableProducts = menu_notice_available_products(
                (string) $row['tanggal_kirim'],
                (int) ($row['produk_lama_id'] ?? 0),
                (int) $row['qty_lama']
            );
            $replacement = null;
            foreach ($availableProducts as $product) {
                if ((int) $product['id'] === $requestedId) {
                    $replacement = $product;
                    break;
                }
            }

            if (!$replacement) {
                $db->rollBack();
                mnc_json([
                    'error' => 'Menu pengganti tidak cukup tersedia atau jumlahnya di bawah minimum pada tanggal kirim.',
                ], 422);
            }

            $replacementId = (int) $replacement['id'];
            $snapshotKode = $replacement['kode'];
            $snapshotNama = $replacement['nama'];
            $snapshotQty = (int) $row['qty_lama'];
            $snapshotHarga = (int) $replacement['harga'];
            $status = 'menunggu_konfirmasi';
        }

        $updated = q(
            "UPDATE pemberitahuan_perubahan_menu_pesanan
             SET opsi_id = ?,
                 catatan_respons = ?,
                 status_respons = ?,
                 produk_pengganti_id = ?,
                 kode_produk_pengganti_snapshot = ?,
                 nama_produk_pengganti_snapshot = ?,
                 qty_pengganti = ?,
                 harga_pengganti = ?,
                 responded_at = now(),
                 dibaca_at = COALESCE(dibaca_at, now())
             WHERE id = ?
               AND status_respons = 'menunggu_respons'",
            [
                (int) $option['id'],
                $note === '' ? null : $note,
                $status,
                $replacementId,
                $snapshotKode,
                $snapshotNama,
                $snapshotQty,
                $snapshotHarga,
                (int) $row['id'],
            ]
        )->rowCount();

        if ($updated !== 1) {
            $db->rollBack();
            mnc_json(['error' => 'Pilihan sudah berubah. Muat ulang notifikasi.'], 409);
        }

        $db->commit();

        mnc_json([
            'ok' => true,
            'id' => (int) $row['id'],
            'status_respons' => $status,
            'opsi' => [
                'kode_opsi' => $option['kode_opsi'],
                'label' => $option['label'],
            ],
            'menu_pengganti' => $replacement ? [
                'produk_id' => $replacementId,
                'kode' => $snapshotKode,
                'nama' => $snapshotNama,
                'qty' => $snapshotQty,
                'harga' => $snapshotHarga,
                'total' => $snapshotQty * $snapshotHarga,
            ] : null,
            'belum_dibaca' => menu_notice_customer_unread_count($userId),
        ]);
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

if ($method !== 'GET') {
    mnc_json(['error' => 'Method tidak didukung'], 405);
}

$action = $_GET['action'] ?? 'list';

if ($action === 'detail') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        mnc_json(['error' => 'ID notifikasi tidak valid'], 422);
    }

    $notice = q(
        "SELECT n.id, n.produk_id, n.kode_produk_snapshot, n.nama_produk_snapshot,
                n.catatan_admin, n.created_at
         FROM pemberitahuan_perubahan_menu n
         WHERE n.id = ?
           AND EXISTS (
               SELECT 1
               FROM pemberitahuan_perubahan_menu_pesanan r
               JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
               JOIN pesanan p ON p.id = d.pesanan_id
               WHERE r.pemberitahuan_id = n.id
                 AND p.user_id = ?
                 AND p.sumber = 'online'
           )",
        [$id, $userId]
    )->fetch();
    if (!$notice) {
        mnc_json(['error' => 'Notifikasi tidak ditemukan'], 404);
    }

    $options = q(
        'SELECT id, kode_opsi, label, urutan
         FROM pemberitahuan_perubahan_menu_opsi
         WHERE pemberitahuan_id = ?
         ORDER BY urutan, id',
        [$id]
    )->fetchAll();
    foreach ($options as &$option) {
        $option['id'] = (int) $option['id'];
        $option['urutan'] = (int) $option['urutan'];
    }
    unset($option);
    $hasReplacementOption = in_array(
        'menu_pengganti',
        array_column($options, 'kode_opsi'),
        true
    );

    $rows = q(
        "SELECT r.id, r.status_respons, r.status_penyelesaian_selisih,
                r.opsi_id, o.kode_opsi, o.label AS opsi_label,
                r.produk_lama_id, r.kode_produk_lama_snapshot,
                r.nama_produk_lama_snapshot, r.qty_lama, r.harga_lama,
                r.produk_pengganti_id, r.kode_produk_pengganti_snapshot,
                r.nama_produk_pengganti_snapshot,
                r.qty_pengganti, r.harga_pengganti,
                r.selisih_total, r.total_pesanan_lama, r.total_pesanan_baru,
                r.catatan_respons,
                r.dibaca_at, r.responded_at,
                p.id AS pesanan_id, p.kode AS kode_pesanan,
                p.status AS status_pesanan, p.tgl_kirim,
                (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date AS tanggal_kirim
         FROM pemberitahuan_perubahan_menu_pesanan r
         JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
         JOIN pesanan p ON p.id = d.pesanan_id
         LEFT JOIN pemberitahuan_perubahan_menu_opsi o ON o.id = r.opsi_id
         WHERE r.pemberitahuan_id = ?
           AND p.user_id = ?
           AND p.sumber = 'online'
         ORDER BY tanggal_kirim, p.id, r.id",
        [$id, $userId]
    )->fetchAll();

    $menuCache = [];
    $recipients = [];
    $unread = 0;
    $responded = 0;

    foreach ($rows as $row) {
        if ($row['dibaca_at'] === null) {
            $unread++;
        }
        if ($row['responded_at'] !== null) {
            $responded++;
        }

        $canRespond = $row['status_respons'] === 'menunggu_respons'
            && in_array($row['status_pesanan'], MENU_NOTICE_ACTIVE_STATUSES, true);

        $choices = [];
        if ($canRespond && $hasReplacementOption) {
            $date = (string) $row['tanggal_kirim'];
            if (!isset($menuCache[$date])) {
                $menuCache[$date] = menu_notice_available_products(
                    $date,
                    (int) ($row['produk_lama_id'] ?? 0),
                    (int) $row['qty_lama']
                );
            }
            $choices = array_map(static function (array $product): array {
                return [
                    'id' => (int) $product['id'],
                    'kode' => $product['kode'],
                    'nama' => $product['nama'],
                    'kategori' => $product['kategori'],
                    'harga' => (int) $product['harga'],
                    'gambar' => $product['gambar'],
                    'kuota_efektif' => (int) $product['kuota_efektif'],
                ];
            }, $menuCache[$date]);
        }

        $replacement = $row['harga_pengganti'] !== null
            ? [
                'produk_id' => $row['produk_pengganti_id'] !== null
                    ? (int) $row['produk_pengganti_id']
                    : null,
                'kode' => $row['kode_produk_pengganti_snapshot'],
                'nama' => $row['nama_produk_pengganti_snapshot'],
                'qty' => (int) $row['qty_pengganti'],
                'harga' => (int) $row['harga_pengganti'],
                'total' => (int) $row['qty_pengganti'] * (int) $row['harga_pengganti'],
            ]
            : null;

        $recipients[] = [
            'id' => (int) $row['id'],
            'status_respons' => $row['status_respons'],
            'status_penyelesaian_selisih' => $row['status_penyelesaian_selisih'],
            'bisa_merespons' => $canRespond,
            'opsi' => $row['opsi_id'] !== null
                ? [
                    'id' => (int) $row['opsi_id'],
                    'kode_opsi' => $row['kode_opsi'],
                    'label' => $row['opsi_label'],
                ]
                : null,
            'menu_lama' => [
                'produk_id' => $row['produk_lama_id'] !== null
                    ? (int) $row['produk_lama_id']
                    : null,
                'kode' => $row['kode_produk_lama_snapshot'],
                'nama' => $row['nama_produk_lama_snapshot'],
                'qty' => (int) $row['qty_lama'],
                'harga' => (int) $row['harga_lama'],
                'total' => (int) $row['qty_lama'] * (int) $row['harga_lama'],
            ],
            'menu_pengganti' => $replacement,
            'selisih_total' => $row['selisih_total'] !== null
                ? (int) $row['selisih_total']
                : null,
            'total_pesanan_lama' => $row['total_pesanan_lama'] !== null
                ? (int) $row['total_pesanan_lama']
                : null,
            'total_pesanan_baru' => $row['total_pesanan_baru'] !== null
                ? (int) $row['total_pesanan_baru']
                : null,
            'catatan_respons' => $row['catatan_respons'],
            'dibaca_at' => mnc_iso($row['dibaca_at']),
            'responded_at' => mnc_iso($row['responded_at']),
            'pilihan_menu' => $choices,
            'pesanan' => [
                'id' => (int) $row['pesanan_id'],
                'kode' => $row['kode_pesanan'],
                'status' => $row['status_pesanan'],
                'tgl_kirim' => mnc_iso($row['tgl_kirim']),
                'tanggal_kirim' => $row['tanggal_kirim'],
            ],
        ];
    }

    mnc_json([
        'id' => (int) $notice['id'],
        'produk' => [
            'id' => $notice['produk_id'] !== null ? (int) $notice['produk_id'] : null,
            'kode' => $notice['kode_produk_snapshot'],
            'nama' => $notice['nama_produk_snapshot'],
        ],
        'catatan_admin' => $notice['catatan_admin'],
        'created_at' => mnc_iso($notice['created_at']),
        'opsi' => $options,
        'penerima' => $recipients,
        'ringkasan' => [
            'jumlah_penerima' => count($recipients),
            'sudah_merespons' => $responded,
            'belum_merespons' => count($recipients) - $responded,
            'belum_dibaca' => $unread,
        ],
        'belum_dibaca' => $unread,
    ]);
}

if ($action !== 'list') {
    mnc_json(['error' => 'Aksi tidak valid'], 422);
}

$limit = filter_var($_GET['limit'] ?? 20, FILTER_VALIDATE_INT);
if ($limit === false || $limit < 1) {
    $limit = 20;
}
$limit = min($limit, 100);
$offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
if ($offset === false || $offset < 0) {
    $offset = 0;
}

$total = (int) q(
    "SELECT COUNT(DISTINCT r.pemberitahuan_id)
     FROM pemberitahuan_perubahan_menu_pesanan r
     JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
     JOIN pesanan p ON p.id = d.pesanan_id
     WHERE p.user_id = ?
       AND p.sumber = 'online'",
    [$userId]
)->fetchColumn();

$items = q(
    "SELECT n.id, n.produk_id, n.kode_produk_snapshot, n.nama_produk_snapshot,
            n.catatan_admin, n.created_at,
            COUNT(r.id) AS jumlah_penerima,
            COUNT(DISTINCT p.id) AS jumlah_pesanan,
            COUNT(r.id) FILTER (WHERE r.dibaca_at IS NULL) AS belum_dibaca,
            COUNT(r.id) FILTER (WHERE r.responded_at IS NOT NULL) AS sudah_merespons,
            array_to_json(array_agg(DISTINCT r.status_respons)) AS status_respons
     FROM pemberitahuan_perubahan_menu n
     JOIN pemberitahuan_perubahan_menu_pesanan r ON r.pemberitahuan_id = n.id
     JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
     JOIN pesanan p ON p.id = d.pesanan_id
     WHERE p.user_id = ?
       AND p.sumber = 'online'
     GROUP BY n.id
     ORDER BY n.created_at DESC, n.id DESC
     LIMIT CAST(? AS INTEGER) OFFSET CAST(? AS INTEGER)",
    [$userId, $limit, $offset]
)->fetchAll();

foreach ($items as &$item) {
    $statuses = json_decode((string) ($item['status_respons'] ?? '[]'), true);
    $item['id'] = (int) $item['id'];
    $item['produk_id'] = $item['produk_id'] !== null ? (int) $item['produk_id'] : null;
    $item['jumlah_penerima'] = (int) $item['jumlah_penerima'];
    $item['jumlah_pesanan'] = (int) $item['jumlah_pesanan'];
    $item['belum_dibaca'] = (int) $item['belum_dibaca'];
    $item['sudah_merespons'] = (int) $item['sudah_merespons'];
    $item['status_respons'] = is_array($statuses) ? array_values($statuses) : [];
    $item['produk'] = [
        'id' => $item['produk_id'],
        'kode' => $item['kode_produk_snapshot'],
        'nama' => $item['nama_produk_snapshot'],
    ];
    $item['created_at'] = mnc_iso($item['created_at']);
}
unset($item);

mnc_json([
    'items' => $items,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'belum_dibaca' => menu_notice_customer_unread_count($userId),
]);
