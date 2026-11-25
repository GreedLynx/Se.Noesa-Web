<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/menu-notifications.php';

// Respons lokal sengaja tidak memakai JSON_NUMERIC_CHECK agar nomor telepon
// berawalan 0 tetap berupa string dan tidak berubah menjadi angka.
function api_json($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function menu_notice_iso(?string $value): ?string
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

function menu_notice_shape_customer(array $row): array
{
    $online = $row['user_id'] !== null && $row['sumber'] === 'online';

    return [
        'user_id' => $online ? (int) $row['user_id'] : null,
        'nama' => $online
            ? ($row['username'] ?: $row['nama_pemesan'])
            : $row['nama_pemesan'],
        'username' => $online ? $row['username'] : null,
        'email' => $online ? $row['email'] : null,
        'telepon' => $online ? ($row['phone'] ?: $row['telepon_penerima']) : $row['telepon_penerima'],
        'kanal' => $online ? 'online' : 'offline',
        'notifikasi_in_app' => $online,
    ];
}

function menu_notice_shape_order(array $row): array
{
    return [
        'id' => (int) $row['pesanan_id'],
        'kode' => $row['kode_pesanan'],
        'status' => $row['status_pesanan'],
        'sumber' => $row['sumber'] === 'offline' || $row['user_id'] === null ? 'offline' : 'online',
        'tgl_kirim' => menu_notice_iso($row['tgl_kirim']),
        'tanggal_kirim' => $row['tanggal_kirim'],
    ];
}

function menu_notice_shape_target(array $row): array
{
    return [
        'detail_pesanan_id' => (int) $row['detail_pesanan_id'],
        'qty' => (int) $row['qty'],
        'harga' => (int) $row['harga'],
        'subtotal' => (int) $row['qty'] * (int) $row['harga'],
        'kuota_efektif' => (int) $row['kuota_efektif'],
        'sudah_dinotifikasi' => (bool) $row['sudah_dinotifikasi'],
        'pesanan' => menu_notice_shape_order($row),
        'pelanggan' => menu_notice_shape_customer($row),
    ];
}

function menu_notice_parse_options($input): array
{
    $defaults = menu_notice_default_options();
    $labels = array_column($defaults, 'label', 'kode_opsi');
    if ($input === null || $input === '') {
        $input = array_keys($labels);
    }
    if (!is_array($input)) {
        return [];
    }

    $options = [];
    foreach ($input as $raw) {
        $kode = is_array($raw) ? (string) ($raw['kode_opsi'] ?? '') : (string) $raw;
        if (!isset($labels[$kode]) || isset($options[$kode])) {
            continue;
        }

        $label = is_array($raw) ? trim((string) ($raw['label'] ?? '')) : '';
        if ($label === '') {
            $label = $labels[$kode];
        }
        if (mb_strlen($label) > 100) {
            $label = mb_substr($label, 0, 100);
        }
        $options[$kode] = ['kode_opsi' => $kode, 'label' => $label, 'urutan' => count($options)];
    }

    return array_values($options);
}

function menu_notice_preview_summary(array $targets): array
{
    $orders = [];
    $online = [];
    $offline = [];
    $already = [];
    $dates = [];

    foreach ($targets as $row) {
        $orderId = (int) $row['pesanan_id'];
        $orders[$orderId] = true;
        if ($row['user_id'] !== null && $row['sumber'] === 'online') {
            $online[$orderId] = true;
        } else {
            $offline[$orderId] = true;
        }
        if (!empty($row['sudah_dinotifikasi'])) {
            $already[$orderId] = true;
        }
        $date = (string) $row['tanggal_kirim'];
        $dates[$date] = ($dates[$date] ?? 0) + 1;
    }

    ksort($dates);

    return [
        'jumlah_target' => count($targets),
        'jumlah_pesanan' => count($orders),
        'online' => count($online),
        'offline' => count($offline),
        'sudah_dinotifikasi' => count($already),
        'siap_dinotifikasi' => count($orders) - count($already),
        'tanggal' => $dates,
    ];
}

function menu_notice_summary(array $recipients): array
{
    $orders = [];
    $online = [];
    $offline = [];
    $responded = [];
    $statuses = [];

    foreach ($recipients as $row) {
        $orderId = (int) $row['pesanan_id'];
        $orders[$orderId] = true;
        if ($row['user_id'] !== null && $row['sumber'] === 'online') {
            $online[$orderId] = true;
        } else {
            $offline[$orderId] = true;
        }
        if (!empty($row['responded_at'])) {
            $responded[$orderId] = true;
        }
        $status = (string) ($row['status_respons'] ?? '');
        if ($status !== '') {
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        }
    }

    return [
        'jumlah_target' => count($recipients),
        'jumlah_pesanan' => count($orders),
        'online' => count($online),
        'offline' => count($offline),
        'sudah_merespons' => count($responded),
        'belum_merespons' => count($orders) - count($responded),
        'status_respons' => $statuses,
    ];
}

$user = me();
if (!$user || !in_array($user['role'] ?? '', ['admin', 'owner'], true)) {
    api_json(['error' => 'Unauthorized'], 401);
}
$isOwner = $user['role'] === 'owner';

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
    error_log('[se.noesa menu-notifications API] ' . $error);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Notifikasi perubahan menu belum dapat diproses'], JSON_UNESCAPED_UNICODE);
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    if ($isOwner) {
        api_json(['error' => 'Hanya Admin yang dapat membuat notifikasi'], 403);
    }
    csrf_require();

    $request = body();
    $action = $request['action'] ?? 'create';
    if (in_array($action, ['confirm', 'reject_replacement', 'settle_difference', 'complete_follow_up'], true)) {
        $recipientId = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
        if ($recipientId === false || $recipientId < 1) {
            api_json(['error' => 'ID penerima tidak valid'], 422);
        }

        try {
            if ($action === 'confirm') {
                api_json([
                    'ok' => true,
                    'result' => menu_notice_confirm_replacement((int) $recipientId, (int) $user['id']),
                ]);
            }
            if ($action === 'reject_replacement') {
                $reason = trim((string) ($request['alasan_penolakan'] ?? ''));
                if ($reason === '') {
                    api_json(['error' => 'Alasan penolakan penggantian wajib diisi'], 422);
                }
                api_json([
                    'ok' => true,
                    'result' => menu_notice_reject_replacement(
                        (int) $recipientId,
                        (int) $user['id'],
                        $reason
                    ),
                ]);
            }
            if ($action === 'settle_difference') {
                api_json([
                    'ok' => true,
                    'result' => menu_notice_settle_difference((int) $recipientId, (int) $user['id']),
                ]);
            }

            $followUpNote = trim((string) ($request['catatan_tindak_lanjut'] ?? ''));
            if (mb_strlen($followUpNote) > 1000) {
                api_json(['error' => 'Catatan tindak lanjut maksimal 1000 karakter'], 422);
            }
            api_json([
                'ok' => true,
                'result' => menu_notice_complete_follow_up(
                    (int) $recipientId,
                    (int) $user['id'],
                    $followUpNote
                ),
            ]);
        } catch (DomainException $error) {
            $status = in_array($error->getCode(), [404, 409, 422], true) ? $error->getCode() : 422;
            api_json(['error' => $error->getMessage()], $status);
        }
    }

    if ($action !== 'create') {
        api_json(['error' => 'Aksi tidak valid'], 422);
    }

    $productId = filter_var($request['produk_id'] ?? null, FILTER_VALIDATE_INT);
    if ($productId === false || $productId < 1) {
        api_json(['error' => 'produk_id tidak valid'], 422);
    }

    $note = trim((string) ($request['catatan_admin'] ?? ''));
    if ($note === '') {
        api_json(['error' => 'Catatan Admin wajib diisi'], 422);
    }
    if (mb_strlen($note) > 2000) {
        api_json(['error' => 'Catatan Admin maksimal 2000 karakter'], 422);
    }

    $options = menu_notice_parse_options($request['opsi'] ?? null);
    if (!$options) {
        api_json(['error' => 'Pilih minimal satu tindakan pelanggan yang valid'], 422);
    }

    $product = menu_notice_product($productId);
    if (!$product) {
        api_json(['error' => 'Produk tidak ditemukan'], 404);
    }

    $db = db();
    $db->beginTransaction();
    try {
        // Kunci per produk selalu diambil paling awal. Target dihitung ulang
        // setelah lock agar Admin kedua melihat notifikasi yang baru di-commit
        // Admin pertama, dan urutan lock tidak pernah terbalik.
        menu_notice_lock_creation($productId);
        // Target dihitung ulang di server dan baris pesanan dikunci sampai commit.
        $targets = menu_notice_targets($productId, true);
        $eligible = [];
        $skipped = 0;
        foreach ($targets as $target) {
            if (!empty($target['sudah_dinotifikasi'])) {
                $skipped++;
                continue;
            }
            $eligible[] = $target;
        }

        if (!$eligible) {
            $db->rollBack();
            api_json([
                'error' => 'Tidak ada pesanan aktif yang belum dinotifikasi untuk menu ini.',
                'dilewati_sudah_dinotifikasi' => $skipped,
            ], 422);
        }

        $noticeId = (int) q(
            'INSERT INTO pemberitahuan_perubahan_menu
                (produk_id, kode_produk_snapshot, nama_produk_snapshot, catatan_admin, created_by)
             VALUES (?, ?, ?, ?, ?) RETURNING id',
            [$productId, $product['kode'], $product['nama'], $note, (int) $user['id']]
        )->fetchColumn();

        foreach ($options as $option) {
            q(
                'INSERT INTO pemberitahuan_perubahan_menu_opsi
                    (pemberitahuan_id, kode_opsi, label, urutan)
                 VALUES (?, ?, ?, ?)',
                [$noticeId, $option['kode_opsi'], $option['label'], $option['urutan']]
            );
        }

        foreach ($eligible as $target) {
            q(
                'INSERT INTO pemberitahuan_perubahan_menu_pesanan
                    (pemberitahuan_id, detail_pesanan_id, produk_lama_id,
                     kode_produk_lama_snapshot, nama_produk_lama_snapshot, qty_lama, harga_lama)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $noticeId,
                    (int) $target['detail_pesanan_id'],
                    $productId,
                    $product['kode'],
                    $product['nama'],
                    (int) $target['qty'],
                    (int) $target['harga'],
                ]
            );
        }

        $db->commit();

        $orderIds = array_unique(array_map(static fn(array $target): int => (int) $target['pesanan_id'], $eligible));
        $online = 0;
        foreach ($eligible as $target) {
            if ($target['user_id'] !== null && $target['sumber'] === 'online') {
                $online++;
            }
        }

        api_json([
            'ok' => true,
            'id' => $noticeId,
            'jumlah_target' => count($eligible),
            'jumlah_pesanan' => count($orderIds),
            'online' => $online,
            'offline' => count($eligible) - $online,
            'dilewati_sudah_dinotifikasi' => $skipped,
        ], 201);
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

if ($method !== 'GET') {
    api_json(['error' => 'Method tidak didukung'], 405);
}

$action = $_GET['action'] ?? 'list';

if ($action === 'preview') {
    $productId = filter_var($_GET['produk_id'] ?? null, FILTER_VALIDATE_INT);
    if ($productId === false || $productId < 1) {
        api_json(['error' => 'produk_id tidak valid'], 422);
    }

    $product = menu_notice_product($productId);
    if (!$product) {
        api_json(['error' => 'Produk tidak ditemukan'], 404);
    }

    $targets = menu_notice_targets($productId);

    api_json([
        'produk' => [
            'id' => (int) $product['id'],
            'kode' => $product['kode'],
            'nama' => $product['nama'],
            'kuota_default' => (int) $product['kuota_harian'],
        ],
        'items' => array_map('menu_notice_shape_target', $targets),
        'ringkasan' => menu_notice_preview_summary($targets),
    ]);
}

if ($action === 'detail') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        api_json(['error' => 'ID tidak valid'], 422);
    }

    $notice = q(
        'SELECT n.*, u.username AS created_by_username
         FROM pemberitahuan_perubahan_menu n
         JOIN users u ON u.id = n.created_by
         WHERE n.id = ?',
        [$id]
    )->fetch();
    if (!$notice) {
        api_json(['error' => 'Notifikasi tidak ditemukan'], 404);
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

    $rows = q(
        "SELECT r.id, r.detail_pesanan_id, r.status_respons, r.status_penyelesaian_selisih,
                r.opsi_id, o.kode_opsi, o.label AS opsi_label,
                r.produk_lama_id, r.kode_produk_lama_snapshot, r.nama_produk_lama_snapshot,
                r.qty_lama, r.harga_lama,
                r.produk_pengganti_id, r.kode_produk_pengganti_snapshot,
                r.nama_produk_pengganti_snapshot, r.qty_pengganti, r.harga_pengganti,
                r.selisih_total, r.catatan_respons,
                r.dibaca_at, r.responded_at,
                r.confirmed_by, r.confirmed_at,
                r.selisih_diselesaikan_oleh, r.selisih_diselesaikan_at,
                r.total_pesanan_lama, r.total_pesanan_baru,
                r.harga_katalog_saat_konfirmasi, r.perbedaan_harga_katalog,
                r.tindak_lanjut_oleh, r.tindak_lanjut_at, r.catatan_tindak_lanjut,
                r.jenis_penutupan, r.alasan_penutupan, r.ditutup_oleh, r.ditutup_at,
                p.id AS pesanan_id, p.kode AS kode_pesanan, p.nama_pemesan,
                p.status AS status_pesanan, p.sumber, p.tgl_kirim,
                p.telepon_penerima, p.user_id,
                p.total AS total_pesanan, p.bukti_bayar, p.nama_pembayar,
                u.username, u.email, u.phone,
                hp.harga AS harga_katalog_sekarang,
                (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date AS tanggal_kirim
         FROM pemberitahuan_perubahan_menu_pesanan r
         JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
         JOIN pesanan p ON p.id = d.pesanan_id
         LEFT JOIN users u ON u.id = p.user_id
         LEFT JOIN produk hp ON hp.id = r.produk_pengganti_id
         LEFT JOIN pemberitahuan_perubahan_menu_opsi o ON o.id = r.opsi_id
         WHERE r.pemberitahuan_id = ?
         ORDER BY tanggal_kirim, p.id, r.id",
        [$id]
    )->fetchAll();

    $recipients = [];
    foreach ($rows as $row) {
        $replacement = $row['harga_pengganti'] !== null
            ? [
                'produk_id' => $row['produk_pengganti_id'] !== null ? (int) $row['produk_pengganti_id'] : null,
                'kode' => $row['kode_produk_pengganti_snapshot'],
                'nama' => $row['nama_produk_pengganti_snapshot'],
                'qty' => (int) $row['qty_pengganti'],
                'harga' => (int) $row['harga_pengganti'],
                'total' => (int) $row['qty_pengganti'] * (int) $row['harga_pengganti'],
            ]
            : null;

        $catalogPrice = $row['harga_katalog_saat_konfirmasi'] !== null
            ? $row['harga_katalog_saat_konfirmasi']
            : $row['harga_katalog_sekarang'];
        $paymentInfo = menu_notice_payment_summary($row);

        $recipients[] = [
            'id' => (int) $row['id'],
            'detail_pesanan_id' => (int) $row['detail_pesanan_id'],
            'status_respons' => $row['status_respons'],
            'status_penyelesaian_selisih' => $row['status_penyelesaian_selisih'],
            'opsi' => $row['opsi_id'] !== null
                ? [
                    'id' => (int) $row['opsi_id'],
                    'kode_opsi' => $row['kode_opsi'],
                    'label' => $row['opsi_label'],
                ]
                : null,
            'menu_lama' => [
                'produk_id' => $row['produk_lama_id'] !== null ? (int) $row['produk_lama_id'] : null,
                'kode' => $row['kode_produk_lama_snapshot'],
                'nama' => $row['nama_produk_lama_snapshot'],
                'qty' => (int) $row['qty_lama'],
                'harga' => (int) $row['harga_lama'],
                'total' => (int) $row['qty_lama'] * (int) $row['harga_lama'],
            ],
            'menu_pengganti' => $replacement,
            'info_harga' => menu_notice_price_info(
                $row['harga_pengganti'] !== null ? (int) $row['harga_pengganti'] : null,
                $catalogPrice !== null ? (int) $catalogPrice : null
            ),
            'pembayaran' => $paymentInfo,
            'status_pembayaran' => $paymentInfo['status_pembayaran'],
            'sudah_membayar' => $paymentInfo['sudah_membayar'],
            'selisih_total' => $row['selisih_total'] !== null ? (int) $row['selisih_total'] : null,
            'catatan_respons' => $row['catatan_respons'],
            'dibaca_at' => menu_notice_iso($row['dibaca_at']),
            'responded_at' => menu_notice_iso($row['responded_at']),
            'confirmed_by' => $row['confirmed_by'] !== null ? (int) $row['confirmed_by'] : null,
            'confirmed_at' => menu_notice_iso($row['confirmed_at']),
            'selisih_diselesaikan_oleh' => $row['selisih_diselesaikan_oleh'] !== null
                ? (int) $row['selisih_diselesaikan_oleh']
                : null,
            'selisih_diselesaikan_at' => menu_notice_iso($row['selisih_diselesaikan_at']),
            'total_pesanan_lama' => $row['total_pesanan_lama'] !== null ? (int) $row['total_pesanan_lama'] : null,
            'total_pesanan_baru' => $row['total_pesanan_baru'] !== null ? (int) $row['total_pesanan_baru'] : null,
            'tindak_lanjut_oleh' => $row['tindak_lanjut_oleh'] !== null ? (int) $row['tindak_lanjut_oleh'] : null,
            'tindak_lanjut_at' => menu_notice_iso($row['tindak_lanjut_at']),
            'catatan_tindak_lanjut' => $row['catatan_tindak_lanjut'],
            'jenis_penutupan' => $row['jenis_penutupan'],
            'alasan_penutupan' => $row['alasan_penutupan'],
            'ditutup_oleh' => $row['ditutup_oleh'] !== null ? (int) $row['ditutup_oleh'] : null,
            'ditutup_at' => menu_notice_iso($row['ditutup_at']),
            'pesanan' => menu_notice_shape_order($row),
            'pelanggan' => menu_notice_shape_customer($row),
        ];
    }

    api_json([
        'id' => (int) $notice['id'],
        'produk' => [
            'id' => $notice['produk_id'] !== null ? (int) $notice['produk_id'] : null,
            'kode' => $notice['kode_produk_snapshot'],
            'nama' => $notice['nama_produk_snapshot'],
        ],
        'catatan_admin' => $notice['catatan_admin'],
        'created_by' => [
            'id' => (int) $notice['created_by'],
            'username' => $notice['created_by_username'],
        ],
        'created_at' => menu_notice_iso($notice['created_at']),
        'opsi' => $options,
        'penerima' => $recipients,
        'ringkasan' => menu_notice_summary($rows),
    ]);
}

if ($action !== 'list') {
    api_json(['error' => 'Aksi tidak valid'], 422);
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

$total = (int) q('SELECT COUNT(*) FROM pemberitahuan_perubahan_menu')->fetchColumn();
$items = q(
    "SELECT n.id, n.produk_id, n.kode_produk_snapshot, n.nama_produk_snapshot,
            n.catatan_admin, n.created_at, u.username AS created_by_username,
            COUNT(r.id) AS jumlah_target,
            COUNT(DISTINCT p.id) AS jumlah_pesanan,
            COUNT(DISTINCT p.id) FILTER (WHERE p.user_id IS NOT NULL AND p.sumber = 'online') AS online,
            COUNT(DISTINCT p.id) FILTER (WHERE p.user_id IS NULL OR p.sumber = 'offline') AS offline,
            COUNT(DISTINCT p.id) FILTER (WHERE r.responded_at IS NOT NULL) AS sudah_merespons,
            COUNT(DISTINCT p.id)
                - COUNT(DISTINCT p.id) FILTER (WHERE r.responded_at IS NOT NULL) AS belum_merespons
     FROM pemberitahuan_perubahan_menu n
     JOIN users u ON u.id = n.created_by
     LEFT JOIN pemberitahuan_perubahan_menu_pesanan r ON r.pemberitahuan_id = n.id
     LEFT JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
     LEFT JOIN pesanan p ON p.id = d.pesanan_id
     GROUP BY n.id, u.username
     ORDER BY n.created_at DESC, n.id DESC
     LIMIT CAST(? AS INTEGER) OFFSET CAST(? AS INTEGER)",
    [$limit, $offset]
)->fetchAll();

foreach ($items as &$item) {
    foreach ([
        'id', 'produk_id', 'jumlah_target', 'jumlah_pesanan',
        'online', 'offline', 'sudah_merespons', 'belum_merespons',
    ] as $field) {
        $item[$field] = $item[$field] !== null ? (int) $item[$field] : null;
    }
    $item['created_at'] = menu_notice_iso($item['created_at']);
}
unset($item);

api_json([
    'items' => $items,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
]);
