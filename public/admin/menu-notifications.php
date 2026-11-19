<?php
require __DIR__ . '/partials/layout.php';
admin_head('Notifikasi Perubahan Menu', 'menu-notifications');
$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
?>
<div id="mn-page" data-mode="admin" data-api="api/menu-notifications.php"
    data-products="api/products.php" data-today="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">
    <section class="page-intro">
        <div>
            <p class="eyebrow">KOMUNIKASI PELANGGAN</p>
            <h2>Notifikasi perubahan menu</h2>
            <p class="muted">
                Beri tahu pelanggan saat menu yang dipesan tidak tersedia.
        </div>
        <button type="button" class="button primary" id="mn-new">
            <span>+</span>
            Notifikasi
        </button>
    </section>

    <section class="mn-stats" id="mn-stats" aria-live="polite"></section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">RIWAYAT</p>
                <h2>Notifikasi terkirim</h2>
            </div>
            <button type="button" class="button secondary small" id="mn-refresh">Muat ulang</button>
        </div>
        <div class="mn-list" id="mn-list">
            <p class="mn-list-empty">Memuat notifikasi…</p>
        </div>
        <div class="mn-more">
            <button type="button" class="button secondary" id="mn-more" hidden>Muat lagi</button>
        </div>
    </section>
</div>

<dialog id="mn-create-dialog" class="product-dialog mn-dialog" aria-labelledby="mn-create-title">
    <div class="mn-dialog-head">
        <div>
            <small>NOTIFIKASI BARU</small>
            <h2 id="mn-create-title">Kirim notifikasi perubahan menu</h2>
        </div>
        <button type="button" class="mn-close" data-mn-close aria-label="Tutup">&times;</button>
    </div>
    <form id="mn-create-form">
        <div class="mn-form-grid">
            <label>
                Menu yang tidak tersedia
                <select id="mn-product" name="produk_id" required>
                    <option value="">Pilih menu</option>
                </select>
            </label>
            <label>
                Tanggal acuan daftar menu
                <input id="mn-date" type="date" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
                    data-date-picker>
            </label>
        </div>
        <p class="mn-hint" id="mn-product-hint">Pilih menu yang tidak tersedia.</p>

        <label>
            Catatan Admin
            <textarea id="mn-note" name="catatan_admin" rows="4" maxlength="2000" required
                placeholder="Jelaskan alasan perubahan menu dan informasi yang perlu diketahui pelanggan."></textarea>
        </label>
        <p class="mn-hint">Maksimal 2000 karakter. Catatan ini akan dibaca pelanggan.</p>

        <fieldset class="mn-options">
            <legend>Tindakan yang dapat dipilih pelanggan</legend>
            <label class="mn-option">
                <input type="checkbox" name="opsi" value="menu_pengganti" checked>
                Pilih menu pengganti
            </label>
            <label class="mn-option">
                <input type="checkbox" name="opsi" value="hubungi_admin" checked>
                Hubungi Admin
            </label>
            <label class="mn-option">
                <input type="checkbox" name="opsi" value="tunggu_informasi" checked>
                Tunggu informasi
            </label>
        </fieldset>

        <div class="mn-preview-bar">
            <button type="button" class="button secondary" id="mn-preview">Preview target</button>
            <span class="mn-hint">Target dihitung ulang oleh server dari pesanan aktif pada tanggal kirim.
                Cakupan tanggal mengikuti masing-masing pesanan.</span>
        </div>
        <div class="mn-preview-result" id="mn-preview-result" hidden></div>

        <p class="form-error" id="mn-create-error" role="alert"></p>
        <div class="dialog-actions">
            <button type="button" class="button secondary" data-mn-close>Batal</button>
            <button type="submit" class="button primary" id="mn-submit" disabled>Kirim notifikasi</button>
        </div>
    </form>
</dialog>

<dialog id="mn-detail-dialog" class="product-dialog mn-dialog" aria-labelledby="mn-detail-title">
    <div class="mn-dialog-head">
        <div>
            <small>DETAIL NOTIFIKASI</small>
            <h2 id="mn-detail-title">Respons pelanggan</h2>
        </div>
        <button type="button" class="mn-close" data-mn-close aria-label="Tutup">&times;</button>
    </div>
    <div data-mn-detail-body>
        <p class="mn-list-empty">Memuat detail…</p>
    </div>
</dialog>
<?php
admin_foot();
