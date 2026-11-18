<?php
require __DIR__ . '/partials/layout.php';

customer_head('Notifikasi Menu', 'notifications'); ?>
<div class="px-margin-mobile pt-space-xs pb-space-sm flex flex-col gap-1">
    <div class="flex items-center justify-between mt-1">
        <div class="flex flex-col">
            <span class="font-label-sm text-label-sm uppercase tracking-widest text-secondary font-semibold">Pemberitahuan</span>
            <h1 class="font-headline-xl-mobile text-headline-xl-mobile text-primary tracking-tight font-extrabold">Notifikasi Menu</h1>
        </div>
        <a href="riwayat.php" class="w-9 h-9 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors" aria-label="Riwayat pesanan">
            <span class="material-symbols-outlined text-[20px]">receipt_long</span>
        </a>
    </div>
    <p class="text-body-sm text-on-surface-variant mt-1">Admin memberi tahu bila menu pada pesananmu berubah.</p>
    <p class="text-body-sm text-on-surface-variant">Perubahan menu baru berlaku setelah Admin mengonfirmasi pilihanmu. Status pesanan dan pembayaran tidak berubah karena notifikasi ini.</p>
</div>
<div id="mnc-page" data-api="api/menu-notifications.php" class="px-margin-mobile mb-space-xl">
    <div class="mnc-toolbar flex items-center justify-between gap-2 mb-space-sm">
        <p class="mnc-summary font-label-md text-label-md text-on-surface-variant" id="mnc-summary">Memuat notifikasi…</p>
        <button type="button" class="px-3 py-1.5 rounded-full bg-surface-container text-primary font-label-md text-label-md font-semibold hover:bg-surface-container-high" id="mnc-refresh">Muat ulang</button>
    </div>
    <section class="mnc-list flex flex-col gap-3" id="mnc-list" aria-live="polite">
        <p class="mnc-empty text-body-sm text-on-surface-variant py-6 text-center">Memuat notifikasi…</p>
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
