<?php
require __DIR__ . '/partials/layout.php';
customer_head('Detail Pesanan', 'detail');
?>
<header class="od-top">
    <button type="button" class="od-top-back" onclick="history.back()" aria-label="Kembali">
        <span class="material-symbols-outlined text-[24px]">arrow_back</span>
    </button>
    <h1 class="od-top-title">Detail Pesanan</h1>
    <span class="od-top-avatar"><span class="material-symbols-outlined text-on-primary text-[18px]">person</span></span>
</header>

<div id="od-root" class="od-root" hidden>
    <div id="od-loading" class="od-loading">Memuat detail pesanan...</div>

    <section class="od-card" id="od-status-card" hidden>
        <div class="od-row od-row-top">
            <div class="od-kode">
                <span class="od-eyebrow">Kode Pesanan</span>
                <div class="od-kode-row">
                    <span id="od-code" class="od-code"></span>
                    <button type="button" id="od-copy" class="od-copy" aria-label="Salin kode">
                        <span class="material-symbols-outlined text-[18px]">content_copy</span>
                    </button>
                </div>
            </div>
            <span id="od-status" class="od-badge"></span>
        </div>
        <div id="od-countdown-wrap" class="od-countdown" hidden>
            <div class="od-countdown-left">
                <span class="material-symbols-outlined text-secondary text-[20px]">timer</span>
                <div>
                    <p class="od-countdown-label">Selesaikan pembayaran dalam</p>
                    <p id="od-countdown" class="od-countdown-text">-</p>
                </div>
            </div>
            <span class="od-countdown-hint">Otomatis Batal</span>
        </div>
        <div id="od-stepper" class="od-stepper"></div>
    </section>

    <section class="od-card" id="od-address-card" hidden>
        <div class="od-row">
            <div class="od-title">
                <span class="material-symbols-outlined text-primary text-[20px]">local_shipping</span>
                <h2>Alamat &amp; Jadwal Kirim</h2>
            </div>
        </div>
        <div class="od-soft">
            <div class="od-addr">
                <span class="material-symbols-outlined text-primary text-[18px]">person_pin_circle</span>
                <div class="od-addr-text">
                    <p class="od-addr-name"><span id="od-recipient"></span> <span id="od-recipient-phone" class="od-muted"></span></p>
                    <p id="od-address" class="od-addr-line"></p>
                </div>
            </div>
            <div class="od-hr"></div>
            <div class="od-schedule">
                <span class="od-schedule-date"><span class="material-symbols-outlined text-primary text-[18px]">calendar_month</span><span id="od-date"></span></span>
                <span id="od-slot" class="od-slot" hidden></span>
            </div>
        </div>
    </section>

    <section class="od-card" id="od-menu-card" hidden>
        <div class="od-row">
            <div class="od-title">
                <span class="material-symbols-outlined text-primary text-[20px]">restaurant</span>
                <h2>Rincian Menu</h2>
            </div>
            <span id="od-menu-count" class="od-muted od-small"></span>
        </div>
        <div id="od-items" class="od-items"></div>
        <div id="od-order-note" class="od-note" hidden>
            <span class="material-symbols-outlined text-primary text-[16px]">edit_note</span>
            <p><span class="od-note-label">Catatan:</span> <span id="od-order-note-text"></span></p>
        </div>
    </section>

    <section class="od-card" id="od-qris-card" hidden>
        <div class="od-row">
            <div class="od-title">
                <span class="material-symbols-outlined text-secondary text-[22px]">qr_code_scanner</span>
                <div>
                    <h2>QRIS Pembayaran</h2>
                    <p class="od-muted od-small" id="od-qris-sub">Pindai untuk membayar</p>
                </div>
            </div>
        </div>
        <div id="od-qris-box" class="od-qrbox" hidden>
            <div class="od-qris-img"><img id="od-qris-img" alt="QRIS pembayaran"></div>
            <button type="button" id="od-save-qr" class="od-save-qr">
                <span class="material-symbols-outlined text-[18px]">download</span><span>Simpan Gambar QR</span>
            </button>
        </div>
    </section>

    <section class="od-card" id="od-proof-card" hidden>
        <div class="od-row">
            <div class="od-title">
                <span class="material-symbols-outlined text-primary text-[20px]">receipt_long</span>
                <h2>Unggah Bukti Pembayaran</h2>
            </div>
            <span id="od-proof-badge" class="od-pill">Wajib</span>
        </div>
        <p class="od-muted od-small">Sudah bayar lewat mobile banking / e-wallet? Unggah tangkapan layar untuk verifikasi.</p>
        <form id="od-proof-form" class="od-proof-form">
            <label class="od-upload" for="od-proof-file">
                <input type="file" id="od-proof-file" name="proof" accept="image/jpeg,image/png,image/webp" hidden>
                <span class="od-upload-icon"><span class="material-symbols-outlined text-[22px]">cloud_upload</span></span>
                <span class="od-upload-title">Ketuk untuk pilih foto struk / tangkapan layar</span>
                <span class="od-muted od-small">Format JPG, PNG, WebP · Maksimal 2MB</span>
            </label>
            <div id="od-proof-preview" class="od-proof-preview" hidden>
                <img id="od-proof-preview-img" alt="Pratinjau bukti pembayaran">
                <button type="button" id="od-proof-preview-clear" class="od-btn od-btn-ghost" aria-label="Hapus pilihan">Ganti</button>
            </div>
            <label class="od-field">
                <span class="od-label">Atas nama pembayar</span>
                <input type="text" id="od-payer-name" name="nama_pembayar" maxlength="100" placeholder="Nama yang membayar">
            </label>
            <input type="hidden" name="id" id="od-proof-id">
            <input type="hidden" name="action" value="upload_proof">
            <p id="od-proof-error" class="od-error" role="alert"></p>
            <button type="submit" id="od-proof-submit" class="od-btn od-btn-primary">Kirim bukti pembayaran</button>
        </form>
        <div id="od-proof-existing" class="od-proof-existing" hidden>
            <img id="od-proof-img" alt="Bukti pembayaran">
            <button type="button" id="od-proof-replace" class="od-btn od-btn-ghost">Ganti Bukti</button>
        </div>
        <p id="od-proof-expired" class="od-expired" hidden></p>
    </section>

    <section class="od-card" id="od-billing-card" hidden>
        <h2 class="od-h2">Ringkasan Tagihan</h2>
        <div class="od-billing">
            <div class="od-billing-row"><span id="od-subtotal-label">Subtotal Menu</span><span id="od-subtotal" class="od-money"></span></div>
            <div class="od-billing-row"><span>Biaya Pengiriman</span><span class="od-free">Gratis Ongkir</span></div>
            <div class="od-billing-row"><span>Biaya Layanan</span><span class="od-money">Rp 0</span></div>
        </div>
        <div class="od-billing-total">
            <div>
                <p class="od-eyebrow">Total Tagihan</p>
                <p id="od-total" class="od-total"></p>
            </div>
            <span class="od-total-icon"><span class="material-symbols-outlined text-[18px]">payments</span></span>
        </div>
    </section>

    <div id="od-reschedule"></div>
    <div id="od-delivery"></div>
    <div id="od-notices"></div>

    <section class="od-card od-contact" id="od-contact-card">
        <button type="button" class="od-contact-head" id="od-contact-toggle" aria-expanded="false" aria-controls="od-contact-body">
            <span class="od-contact-title"><span class="material-symbols-outlined text-primary text-[18px]">support_agent</span>Kontak &amp; Media Sosial Se.Noesa</span>
            <span id="od-contact-icon" class="material-symbols-outlined text-on-surface-variant text-[20px]">expand_more</span>
        </button>
        <div id="od-contact-body" class="od-contact-body" hidden>
            <p class="od-muted od-small">Hubungi kami atau ikuti kabar terbaru Se.Noesa.</p>
            <div class="od-contact-links">
                <?php
                $contactEnv = parse_ini_file(dirname(__DIR__, 2) . '/.env', false, INI_SCANNER_RAW) ?: [];
                $whatsappNumber = preg_replace('/[^0-9]/', '', (string) ($contactEnv['WHATSAPP_NUMBER'] ?? ''));
                $socialLinks = [
                    'Instagram' => $contactEnv['INSTAGRAM_URL'] ?? '',
                    'TikTok' => $contactEnv['TIKTOK_URL'] ?? '',
                    'Facebook' => $contactEnv['FACEBOOK_URL'] ?? '',
                ];
                if ($whatsappNumber !== ''): ?>
                    <a class="od-contact-link" href="https://wa.me/<?= htmlspecialchars($whatsappNumber, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><span class="material-symbols-outlined">chat</span>WhatsApp</a>
                <?php endif;
                foreach ($socialLinks as $label => $url):
                    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)) continue; ?>
                    <a class="od-contact-link" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><span class="material-symbols-outlined">open_in_new</span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
            <?php if ($whatsappNumber === '' && count(array_filter($socialLinks, fn($url) => filter_var($url, FILTER_VALIDATE_URL))) === 0): ?>
                <p class="od-muted od-small">Informasi kontak belum tersedia.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="od-card od-policy" id="od-policy-card" hidden>
        <button type="button" class="od-policy-head" id="od-policy-toggle">
            <span class="od-policy-title"><span class="material-symbols-outlined text-on-surface-variant text-[18px]">policy</span>Syarat &amp; Kebijakan Pembatalan</span>
            <span id="od-policy-icon" class="material-symbols-outlined text-on-surface-variant text-[20px]">expand_more</span>
        </button>
        <div id="od-policy-body" class="od-policy-body" hidden>
            <p>Pesanan katering pre-order hanya dapat dibatalkan maksimal <b>H-2</b> sebelum jadwal kirim.</p>
            <p id="od-policy-limit" class="od-muted"></p>
        </div>
    </section>

    <div class="od-actions" id="od-actions">
        <button type="button" id="od-check" class="od-btn od-btn-primary">
            <span class="material-symbols-outlined text-[18px]">sync</span><span>Cek Status Pembayaran</span>
        </button>
        <button type="button" id="od-cancel" class="od-btn od-btn-danger" hidden>
            <span class="material-symbols-outlined text-[16px]">cancel</span><span>Batalkan Pesanan</span>
        </button>
    </div>
</div>

<div class="od-cancel-modal" id="od-cancel-modal" hidden>
    <div class="od-cancel-card" role="dialog" aria-modal="true" aria-label="Batalkan pesanan">
        <div class="od-cancel-icon"><span class="material-symbols-outlined text-[28px]">warning</span></div>
        <h3>Batalkan Pesanan?</h3>
        <p id="od-cancel-desc">Slot pesanan Anda akan dibatalkan.</p>
        <div class="od-cancel-actions">
            <button type="button" id="od-cancel-confirm" class="od-btn od-btn-danger">Ya, Batalkan Pesanan</button>
            <button type="button" id="od-cancel-dismiss" class="od-btn od-btn-ghost">Kembali</button>
        </div>
    </div>
</div>

<?php customer_foot(); ?>
