<?php
require __DIR__ . '/partials/layout.php';

customer_head('Notifikasi Menu', 'notifications'); ?>
<div class="px-margin-mobile pt-space-xs pb-space-sm flex flex-col gap-1">
    <div class="flex items-center justify-between mt-1">
        <div class="flex flex-col">
            <span class="font-label-sm text-label-sm uppercase tracking-widest text-secondary font-semibold">Pemberitahuan</span>
            <h1 class="font-headline-xl-mobile text-headline-xl-mobile text-primary tracking-tight font-extrabold">Notifikasi Menu</h1>
        </div>
    </div>
    <p class="text-body-sm text-on-surface-variant mt-1">Admin memberi tahu bila menu pada pesananmu berubah.</p>
</div>
<div id="mnc-page" data-api="api/menu-notifications.php" class="px-margin-mobile mb-space-xl">
    <div class="mnc-toolbar flex items-center justify-between gap-2 mb-space-sm">
        <p class="mnc-summary font-label-md text-label-md text-on-surface-variant" id="mnc-summary">Memuat notifikasi…</p>
        <button type="button" class="px-3 py-1.5 rounded-full bg-surface-container text-primary font-label-md text-label-md font-semibold hover:bg-surface-container-high" id="mnc-refresh">Muat ulang</button>
    </div>
    <section class="mnc-list flex flex-col gap-3" id="mnc-list" aria-live="polite">
        <p class="mnc-empty text-body-sm text-on-surface-variant py-6 text-center">Memuat notifikasi…</p>
    </section>
    <section class="mnc-contact" aria-labelledby="mnc-contact-title">
        <div class="mnc-contact-heading">
            <span class="material-symbols-outlined" aria-hidden="true">support_agent</span>
            <div>
                <h2 id="mnc-contact-title">Butuh bantuan?</h2>
                <p>Hubungi Se.Noesa atau ikuti media sosial kami.</p>
            </div>
        </div>
        <div class="mnc-contact-links">
            <?php
            $contactEnv = parse_ini_file(dirname(__DIR__, 2) . '/.env', false, INI_SCANNER_RAW) ?: [];
            $whatsappNumber = preg_replace('/[^0-9]/', '', (string) ($contactEnv['WHATSAPP_NUMBER'] ?? ''));
            $socialLinks = [
                'Instagram' => $contactEnv['INSTAGRAM_URL'] ?? '',
                'TikTok' => $contactEnv['TIKTOK_URL'] ?? '',
                'Facebook' => $contactEnv['FACEBOOK_URL'] ?? '',
            ];
            $hasContactLinks = $whatsappNumber !== '';
            if ($whatsappNumber !== ''): ?>
                <a class="mnc-contact-link" href="https://wa.me/<?= htmlspecialchars($whatsappNumber, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                    <span class="material-symbols-outlined" aria-hidden="true">chat</span>WhatsApp
                </a>
            <?php endif;
            foreach ($socialLinks as $label => $url):
                if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)) continue;
                $hasContactLinks = true; ?>
                <a class="mnc-contact-link" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                    <span class="material-symbols-outlined" aria-hidden="true">open_in_new</span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$hasContactLinks): ?>
            <p class="mnc-contact-unavailable">Kontak dan media sosial belum diatur. Admin dapat menambahkannya melalui konfigurasi aplikasi.</p>
        <?php endif; ?>
    </section>
    <button type="button" class="customer-button secondary mnc-more w-full py-3 rounded-full bg-surface-container text-primary font-label-md text-label-md font-semibold mt-3" id="mnc-more" hidden>Muat lebih banyak</button>
</div>
<dialog id="mnc-detail-dialog" class="customer-dialog mnc-dialog fixed inset-0 m-auto w-[min(94vw,640px)] max-h-[90vh] rounded-2xl bg-surface-container-lowest p-5 shadow-2xl overflow-y-auto" aria-label="Detail notifikasi menu">
    <button class="dialog-close absolute right-3 top-3 w-9 h-9 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-on-surface z-10" type="button" data-mnc-close aria-label="Tutup">
        <span class="material-symbols-outlined text-[20px]">close</span>
    </button>
    <div id="mnc-detail" class="pt-8"></div>
</dialog>
<?php customer_foot(); ?>
