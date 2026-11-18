<?php
require __DIR__ . '/partials/layout.php';
customer_head('Riwayat Pesanan', 'history');
?>
<div class="px-margin-mobile pt-space-xs pb-space-sm flex flex-col gap-1">
    <div class="flex items-center justify-between mt-1">
        <div class="flex flex-col">
            <span class="font-label-sm text-label-sm uppercase tracking-widest text-secondary font-semibold">Pesanan Saya</span>
            <h1 class="font-headline-xl-mobile text-headline-xl-mobile text-primary tracking-tight font-extrabold">Riwayat Pesanan</h1>
        </div>
        <a href="index.php" class="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-on-primary hover:brightness-110 transition-all" type="button" aria-label="Tambah pesanan">
            <span class="material-symbols-outlined text-[20px]">add</span>
        </a>
    </div>
</div>
<div class="px-margin-mobile mb-space-md flex gap-2 overflow-x-auto pb-1" id="history-tabs" aria-label="Filter status pesanan">
    <button type="button" class="active shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-primary text-on-primary" data-status="">Semua</button>
    <button type="button" class="shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-surface-container text-on-surface-variant" data-status="menunggu_pembayaran">Menunggu pembayaran</button>
    <button type="button" class="shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-surface-container text-on-surface-variant" data-status="menunggu_validasi">Menunggu verifikasi</button>
    <button type="button" class="shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-surface-container text-on-surface-variant" data-status="diproses">Diproses</button>
    <button type="button" class="shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-surface-container text-on-surface-variant" data-status="selesai">Selesai</button>
    <button type="button" class="shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-surface-container text-on-surface-variant" data-status="ditolak">Ditolak</button>
    <button type="button" class="shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-surface-container text-on-surface-variant" data-status="dibatalkan">Dibatalkan</button>
</div>
<section class="px-margin-mobile flex flex-col gap-3 mb-space-xl" id="history-list" aria-live="polite">
    <div class="text-body-sm text-on-surface-variant py-6 text-center">Memuat riwayat pesanan...</div>
</section>
<?php customer_foot(); ?>
