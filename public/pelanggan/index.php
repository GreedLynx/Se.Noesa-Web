<?php
require __DIR__ . '/partials/layout.php';

$customer = customer_head('Pilih Menu', 'catalog');
// SELECT mengambil nama dan nomor telepon pelanggan untuk mengisi informasi katalog.
$profile = q('SELECT username, phone FROM users WHERE id = ?', [$customer['id']])->fetch() ?: [];
// Tanggal kirim default: H+7, digeser maju bila bertepatan dengan hari libur operasional.
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
$deliveryDate = $now->modify('+7 days');
for ($i = 0; $i < 60; $i++) {
    $candidate = $deliveryDate->format('Y-m-d');
    $holiday = q(
        'SELECT 1 FROM tanggal_libur_operasional WHERE tanggal_mulai <= ? AND tanggal_selesai >= ? LIMIT 1',
        [$candidate, $candidate]
    )->fetchColumn();
    if (!$holiday) break;
    $deliveryDate = $deliveryDate->modify('+1 day');
}
$firstDelivery = $deliveryDate->format('Y-m-d');
// Rentang hari libur operasional dipakai kalender untuk menonaktifkan tanggal.
$holidayRanges = q(
    "SELECT to_char(tanggal_mulai, 'YYYY-MM-DD') AS mulai, to_char(tanggal_selesai, 'YYYY-MM-DD') AS selesai
     FROM tanggal_libur_operasional
     WHERE tanggal_selesai >= ? AND tanggal_mulai <= ?
     ORDER BY tanggal_mulai",
    [$now->format('Y-m-d'), $now->modify('+150 days')->format('Y-m-d')]
)->fetchAll();
?>
<!-- State tersembunyi: tanggal kirim dipakai seluruh alur API/kasir yang sudah ada -->
<input type="hidden" id="catalog-delivery-date" value="<?= $firstDelivery ?>" min="<?= $firstDelivery ?>">

<!-- Top Greeting & Motivation -->
<div class="px-margin-mobile pt-space-xs pb-space-sm flex flex-col gap-1">
    <div class="flex items-center justify-between mt-1">
        <div class="flex flex-col">
            <span class="font-label-sm text-label-sm uppercase tracking-widest text-secondary font-semibold">Katering Rumahan</span>
            <h1 class="font-headline-xl-mobile text-headline-xl-mobile text-primary tracking-tight font-extrabold">Pilih Menu</h1>
        </div>
        <a href="riwayat.php" class="w-9 h-9 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors" type="button" aria-label="Riwayat Pesanan">
            <span class="material-symbols-outlined text-[20px]">history</span>
        </a>
    </div>
</div>

<!-- Jadwal Pengiriman (terhubung ke api/catalog.php + aturan H-7) -->
<div class="px-margin-mobile mb-space-md">
    <div class="bg-surface-container-lowest px-4 py-3 rounded-2xl shadow-[0_2px_12px_rgba(30,43,34,0.04)] flex items-center justify-between border border-surface-container">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-full bg-primary-container/15 flex items-center justify-center text-primary-container shrink-0">
                <span class="material-symbols-outlined text-[18px]">calendar_today</span>
            </div>
            <div class="flex flex-col min-w-0">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-semibold">Jadwal Pengiriman</span>
                <span class="font-headline-sm text-headline-sm text-primary font-bold truncate" id="selected-date-text">Memuat jadwal…</span>
            </div>
        </div>
        <button class="px-3 py-1.5 rounded-full bg-surface-container hover:bg-surface-container-high text-primary font-label-md text-label-md font-semibold flex items-center gap-0.5 transition-all shrink-0" id="open-date-picker" type="button">
            <span>Ubah</span><span class="material-symbols-outlined text-[16px]">expand_more</span>
        </button>
    </div>
    <p id="quota-full-hint" hidden class="mt-2 text-body-sm rounded-xl px-3 py-2 bg-secondary-container text-on-secondary-fixed" role="status"></p>
    <p id="delivery-hint" class="mt-2 text-body-sm text-on-surface-variant"></p>
</div>

<!-- Pencarian & filter kategori (fitur lama tetap dipertahankan) -->
<div class="px-margin-mobile mb-space-sm flex flex-col gap-2.5">
    <label class="bg-surface-container-lowest px-4 py-3 rounded-2xl shadow-[0_2px_12px_rgba(30,43,34,0.04)] flex items-center gap-3 border border-surface-container">
        <span class="material-symbols-outlined text-[20px] text-on-surface-variant shrink-0">search</span>
        <input id="catalog-search" type="search" placeholder="Cari menu" aria-label="Cari menu" class="bg-transparent outline-none w-full text-body-md text-on-surface placeholder:text-on-surface-variant">
    </label>
    <div id="category-chips" class="flex gap-2 overflow-x-auto pb-1" aria-label="Filter kategori menu">
        <button type="button" class="category-chip shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-primary text-on-primary" data-category="" aria-pressed="true">Semua</button>
        <button type="button" class="category-chip shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-surface-container text-on-surface-variant" data-category="makanan" aria-pressed="false">Makanan</button>
        <button type="button" class="category-chip shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-surface-container text-on-surface-variant" data-category="minuman" aria-pressed="false">Minuman</button>
        <button type="button" class="category-chip shrink-0 px-3.5 py-1.5 rounded-full font-label-md text-label-md font-semibold transition-all bg-surface-container text-on-surface-variant" data-category="snack" aria-pressed="false">Snack</button>
    </div>
</div>

<!-- Chef Spotlight — diisi dari produk nyata (api/catalog.php) -->
<div class="px-margin-mobile mb-space-lg">
    <div class="relative overflow-hidden rounded-xl bg-primary text-on-primary p-4 shadow-[0_8px_24px_rgba(33,66,47,0.18)]">
        <div class="absolute -right-8 -bottom-8 w-40 h-40 rounded-full bg-primary-container/30 blur-2xl pointer-events-none"></div>
        <div class="relative z-10 flex items-center justify-between gap-3">
            <div class="flex flex-col max-w-[62%]">
                <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-primary-fixed text-on-primary-fixed font-label-sm text-label-sm mb-1.5 w-fit">
                    <span class="material-symbols-outlined text-[12px]">recommend</span><span>Chef's Choice</span>
                </div>
                <h2 class="font-headline-md text-headline-md tracking-tight text-surface-bright leading-snug" id="chef-name">Menu pilihan chef</h2>
                <div class="mt-3 flex items-center gap-3">
                    <span class="font-headline-sm text-headline-sm text-secondary-fixed font-bold" id="chef-price">Rp 0</span>
                    <button type="button" id="chef-order" class="px-3.5 py-1.5 rounded-full bg-surface-container-lowest text-primary font-label-md text-label-md hover:bg-primary-fixed transition-all shadow-sm font-semibold active:scale-95">+ Pesan</button>
                </div>
            </div>
            <div class="relative shrink-0 w-28 h-28">
                <div class="w-28 h-28 rounded-full overflow-hidden shadow-[0_8px_20px_rgba(0,0,0,0.3)] bg-surface-container-highest p-1">
                    <img class="w-full h-full object-cover rounded-full" id="chef-image" src="../assets/img/produk/NK-01.png" alt="Menu pilihan chef">
                </div>
                <div class="absolute -bottom-1 -left-1 px-2 py-0.5 rounded-full bg-tertiary text-on-tertiary font-label-sm text-label-sm shadow-md" id="chef-badge">Rekomendasi</div>
            </div>
        </div>
    </div>
</div>

<!-- Section Header -->
<div class="px-margin-mobile flex items-center justify-between mb-space-sm">
    <div class="flex flex-col">
        <span class="font-label-sm text-label-sm text-secondary uppercase tracking-widest">Pilihan Katering</span>
        <h2 class="font-headline-md text-headline-md text-primary">Menu Siap Pre-Order</h2>
    </div>
    <span class="font-label-md text-label-md text-on-surface-variant" id="menu-count">0 Pilihan</span>
</div>

<!-- Daftar menu dari database (api/catalog.php) -->
<div class="px-margin-mobile flex flex-col gap-3.5 mb-space-xl" id="menu-container">
    <div class="text-body-sm text-on-surface-variant py-6 text-center">Memuat menu…</div>
</div>

<!-- Floating Mini Order Sticky Tray -->
<div class="sticky bottom-2 z-40 px-margin-mobile w-full max-w-md mx-auto pointer-events-none">
    <div class="pointer-events-auto bg-primary text-on-primary p-3 rounded-full shadow-[0_12px_32px_rgba(20,45,30,0.35)] flex items-center justify-between transition-transform duration-300 transform translate-y-0 hidden" id="floating-cart-bar">
        <div class="flex items-center gap-3 pl-2 min-w-0">
            <div class="w-9 h-9 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
            </div>
            <div class="flex flex-col min-w-0">
                <span class="font-label-sm text-label-sm text-primary-fixed-dim uppercase tracking-wider">Subtotal Pesanan</span>
                <div class="flex items-center gap-1.5">
                    <span class="font-headline-sm text-headline-sm text-on-primary" id="cart-total-price">Rp 0</span>
                    <span class="text-primary-fixed-dim text-xs">•</span>
                    <span class="font-label-md text-label-md text-primary-fixed-dim" id="cart-total-items">0 Menu Dipilih (0 Porsi)</span>
                </div>
            </div>
        </div>
        <button type="button" id="checkout-button" class="shrink-0 px-4 py-2 rounded-full bg-secondary-container text-on-secondary-container font-label-lg text-label-lg hover:bg-secondary-fixed transition-all flex items-center gap-1 shadow-sm">
            <span>Keranjang</span><span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
    </div>
</div>

<!-- Bottom sheet Jadwal Pengiriman (kalender bulanan, terhubung aturan H-7 + hari libur) -->
<div id="date-sheet-root" class="hidden fixed inset-0 z-[45]" role="dialog" aria-modal="true" aria-labelledby="date-sheet-title" aria-hidden="true">
    <div class="absolute inset-0 bg-inverse-surface/40 backdrop-blur-sm" data-close-date-sheet></div>
    <div class="fixed inset-x-0 bottom-0 z-50 flex justify-center pointer-events-none">
        <div id="date-sheet" tabindex="-1" class="pointer-events-auto w-full max-w-md bg-surface-container-lowest rounded-t-[28px] shadow-[0_-8px_32px_rgba(18,30,22,0.18)] flex flex-col max-h-[88dvh] overflow-y-auto overscroll-contain outline-none">
            <div class="w-full flex items-center justify-center pt-3 pb-1 shrink-0">
                <div class="w-10 h-1 rounded-full bg-outline-variant/70"></div>
            </div>
            <div class="px-margin-mobile pt-1 pb-3 flex items-center justify-between gap-2 border-b border-surface-container/60 shrink-0">
                <div class="flex flex-col min-w-0">
                    <h2 id="date-sheet-title" class="font-headline-md text-headline-md text-primary font-bold">Pilih Tanggal Pengiriman</h2>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="px-2 py-0.5 rounded-full bg-primary-container/10 text-primary-container font-label-sm text-label-sm font-semibold flex items-center gap-1">
                            <span class="material-symbols-outlined text-[13px]" style="font-variation-settings: 'FILL' 1;">eco</span>Pra-Pesan H-7 • Bahan Baku Segar
                        </span>
                    </div>
                </div>
                <button type="button" id="close-date-sheet" aria-label="Tutup jadwal pengiriman" class="w-9 h-9 rounded-full bg-surface-container-low hover:bg-surface-container text-on-surface-variant flex items-center justify-center transition-colors active:scale-95 shrink-0">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <div class="px-margin-mobile py-3.5">
                <div class="rounded-2xl bg-surface-container-low/40 p-3 border border-surface-container/80">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary-container text-[20px]">calendar_month</span>
                            <span id="calendar-month-label" class="font-headline-sm text-headline-sm text-primary font-semibold">—</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" id="calendar-prev" aria-label="Bulan sebelumnya" class="w-7 h-7 rounded-full bg-surface-container-lowest shadow-sm text-on-surface-variant hover:text-primary flex items-center justify-center active:scale-90 transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined text-[16px]">chevron_left</span>
                            </button>
                            <button type="button" id="calendar-next" aria-label="Bulan berikutnya" class="w-7 h-7 rounded-full bg-surface-container-lowest shadow-sm text-on-surface-variant hover:text-primary flex items-center justify-center active:scale-90 transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-1.5">
                        <span class="font-label-sm text-label-sm text-on-surface-variant font-medium">SEN</span>
                        <span class="font-label-sm text-label-sm text-on-surface-variant font-medium">SEL</span>
                        <span class="font-label-sm text-label-sm text-on-surface-variant font-medium">RAB</span>
                        <span class="font-label-sm text-label-sm text-on-surface-variant font-medium">KAM</span>
                        <span class="font-label-sm text-label-sm text-on-surface-variant font-medium">JUM</span>
                        <span class="font-label-sm text-label-sm text-on-surface-variant font-medium">SAB</span>
                        <span class="font-label-sm text-label-sm text-tertiary font-medium">MIN</span>
                    </div>
                    <div id="calendar-days" class="grid grid-cols-7 gap-y-1.5 gap-x-1 text-center items-center justify-items-center"></div>
                    <div class="mt-2.5 pt-2 flex items-center justify-between gap-2 border-t border-surface-container/60 font-label-sm text-label-sm">
                        <div class="flex items-center gap-1.5 text-primary min-w-0">
                            <span class="w-2 h-2 rounded-full bg-primary shrink-0"></span>
                            <span class="truncate">Tanggal Terpilih: <b id="calendar-selected-label">—</b></span>
                        </div>
                        <span id="calendar-slot-badge" class="px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant font-semibold shrink-0">Memeriksa…</span>
                    </div>
                </div>
                <div class="pt-3" style="padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));">
                    <button type="button" id="calendar-confirm" class="w-full py-3.5 px-4 rounded-xl bg-primary hover:bg-primary-container disabled:hover:bg-primary text-on-primary font-headline-sm text-headline-sm font-bold flex items-center justify-center gap-2 shadow-md shadow-primary/20 active:scale-[0.98] transition-all">
                        <div class="flex items-center justify-between w-full">
                            <div class="flex flex-col text-left">
                                <span class="text-[14px] font-bold leading-tight text-on-primary">Pilih Tanggal Ini</span>
                                <span id="calendar-confirm-sub" class="text-[12px] font-medium text-primary-fixed">Pilih tanggal pada kalender</span>
                            </div>
                            <div class="flex items-center gap-1.5 font-semibold text-[13px] bg-white/15 px-3 py-1.5 rounded-lg text-on-primary">
                                <span class="leading-none">Lanjut</span>
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.customerInfo = {
        name: <?= json_encode($profile['username'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        phone: <?= json_encode($profile['phone'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    };
    window.customerCalendar = {
        minDate: <?= json_encode($firstDelivery, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        holidays: <?= json_encode($holidayRanges, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    };
</script>
<?php customer_foot(); ?>
