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
                Beri tahu pelanggan saat menu pesanannya tidak tersedia, lalu tinjau pilihan mereka sampai masalah selesai.
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
        <div class="mn-stepper" aria-label="Langkah membuat notifikasi">
            <span class="is-current" data-mn-step-indicator="1"><b>1</b> Pilih menu &amp; pesanan</span>
            <span data-mn-step-indicator="2"><b>2</b> Tulis pesan &amp; kirim</span>
        </div>
        <section class="mn-step">
            <h3>1. Pilih menu yang bermasalah</h3>
            <p class="mn-step-copy">Kami akan mencari pesanan aktif yang berisi menu ini.</p>
            <div class="mn-form-grid">
                <label>
                    Menu tidak tersedia
                    <select id="mn-product" name="produk_id" required>
                        <option value="">Pilih menu</option>
                    </select>
                </label>
                <label>
                    Tanggal acuan ketersediaan
                    <input id="mn-date" type="date" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>"
                        data-date-picker>
                </label>
            </div>
            <p class="mn-hint" id="mn-product-hint">Pilih menu untuk melihat pesanan yang terdampak.</p>
            <div class="mn-preview-bar">
                <button type="button" class="button secondary" id="mn-preview">Lihat pesanan terdampak</button>
                <span class="mn-hint">Target dihitung dari pesanan aktif dan tanggal kirim masing-masing.</span>
            </div>
        </section>
        <div class="mn-preview-result" id="mn-preview-result" aria-live="polite" hidden></div>
        <section class="mn-step" id="mn-step-two" hidden>
            <h3>2. Siapkan pesan untuk pelanggan</h3>
            <p class="mn-step-copy">Jelaskan situasinya dan pilih cara pelanggan dapat menanggapinya.</p>
            <label>
                Pesan dari Admin
                <textarea id="mn-note" name="catatan_admin" rows="4" maxlength="2000" required
                    placeholder="Contoh: Maaf, menu ini tidak tersedia untuk tanggal pengiriman pesananmu."></textarea>
            </label>
            <p class="mn-hint">Pesan ini akan terlihat oleh pelanggan. Maksimal 2000 karakter.</p>
            <fieldset class="mn-options">
                <legend>Pilihan respons pelanggan</legend>
                <label class="mn-option">
                    <input type="checkbox" name="opsi" value="menu_pengganti" checked>
                    Pilih menu pengganti
                </label>
                <label class="mn-option">
                    <input type="checkbox" name="opsi" value="hubungi_admin" checked>
                    Minta dihubungi Admin
                </label>
                <label class="mn-option">
                    <input type="checkbox" name="opsi" value="tunggu_informasi" checked>
                    Tunggu informasi berikutnya
                </label>
            </fieldset>
            <p class="mn-hint">Menu pengganti baru berlaku setelah disetujui Admin. Pesanan offline perlu dihubungi secara manual.</p>
        </section>

        <p class="form-error" id="mn-create-error" role="alert"></p>
        <div class="dialog-actions">
            <button type="button" class="button secondary" data-mn-close>Batal</button>
            <button type="submit" class="button primary" id="mn-submit" disabled hidden>Kirim notifikasi</button>
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
