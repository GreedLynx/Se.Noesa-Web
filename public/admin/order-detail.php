<?php
require __DIR__ . '/partials/layout.php';
admin_head('Detail Pesanan', 'order-detail');
$backUrl = 'orders.php';
$returnTo = $_GET['return_to'] ?? '';
// Hanya nama halaman relatif; origin dan direktori selalu mengikuti halaman Admin ini.
if (is_string($returnTo) && !preg_match('/[\x00-\x20\x7f\\\\#]/', $returnTo)) {
    $parts = parse_url($returnTo);
    if ($parts !== false && count(array_diff(array_keys($parts), ['path', 'query'])) === 0
        && in_array($parts['path'] ?? '', ['dashboard.php', 'orders.php'], true)) {
        $filters = [];
        $valid = true;
        $allowed = $parts['path'] === 'orders.php' ? ['tgl_kirim', 'tab', 'review', 'q'] : ['tgl_kirim'];
        foreach (explode('&', $parts['query'] ?? '') as $pair) {
            if ($pair === '') continue;
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            $value = urldecode($value);
            if (!in_array($key, $allowed, true) || array_key_exists($key, $filters) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                $valid = false;
                break;
            }
            if ($key === 'tab' && !in_array($value, ['semua', 'pembayaran', 'validasi', 'proses', 'selesai', 'ditolak', 'dibatalkan'], true)) $valid = false;
            if ($key === 'review' && !in_array($value, ['unreviewed', 'reviewed'], true)) $valid = false;
            if ($key === 'tgl_kirim') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) $valid = false;
            }
            $filters[$key] = $value;
        }
        if ($valid) {
            if (($filters['tab'] ?? '') !== 'dibatalkan') unset($filters['review']);
            $backUrl = $parts['path'] . ($filters ? '?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986) : '');
        }
    }
}
?>
<div class="detail-heading">
    <a class="back-link" id="order-back" data-order-back href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">
        &larr; Kembali <?= str_starts_with($backUrl, 'dashboard.php') ? 'ke Dashboard' : 'ke daftar pesanan' ?>
    </a>
    <span
        class="status-badge" id="detail-status">
        Memuat
    </span>
</div>
<div class="detail-grid" id="order-detail">
    <article class="panel loading">
        Memuat detail pesanan...
    </article>
</div>
<div class="detail-actions" id="detail-actions" hidden>
    <button class="button primary" data-order-action="review_cancellation">
        Sudah ditinjau
    </button>
    <button class="button danger-outline"
        data-order-action="reject">
        Tolak pembayaran
    </button>
    <button class="button primary"
        data-order-action="validate">
        Konfirmasi pembayaran
    </button>
    <button class="button primary"
        data-order-action="complete">
        Tandai selesai &amp; kirim
    </button>
    <p class="form-error" id="review-cancellation-error" role="alert"></p>
</div>
<dialog id="reject-order-dialog" class="product-dialog reject-order-dialog">
    <form id="reject-order-form">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">PENOLAKAN PEMBAYARAN</p>
                <h2>Berikan alasan</h2>
            </div>
            <button type="button" class="icon-close" data-cancel-rejection aria-label="Tutup">&times;</button>
        </div>
        <p class="muted">Alasan ini akan ditampilkan kepada pelanggan pada detail pesanan.</p>
        <label for="rejection-reason">Alasan penolakan
            <textarea id="rejection-reason" name="alasan_penolakan" rows="4" maxlength="500" required
                placeholder="Contoh: Nominal transfer tidak sesuai dengan total pesanan."></textarea>
        </label>
        <p id="rejection-reason-error" class="form-error" role="alert"></p>
        <div class="dialog-actions">
            <button type="button" class="button secondary" data-cancel-rejection>Batal</button>
            <button type="submit" class="button danger-outline">Tolak pembayaran</button>
        </div>
    </form>
</dialog>
<?php admin_foot(); ?>
