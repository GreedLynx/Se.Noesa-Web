<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/menu-notifications.php';

function customer_head(string $title, string $page): array
{
    $customer = require_role('pelanggan', false);
    $GLOBALS['customer_page'] = $page;
    $unreadNotifications = menu_notice_customer_unread_count((int) $customer['id']);
    $GLOBALS['customer_unread'] = $unreadNotifications;
    $css = fn($file) => filemtime(__DIR__ . '/../assets/' . $file);
    $username = htmlspecialchars($customer['username'] ?? 'Pelanggan', ENT_QUOTES, 'UTF-8');
    $initial = htmlspecialchars(strtoupper(substr($customer['username'] ?? 'P', 0, 1)), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($title) ?> · Se.Noesa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "inverse-on-surface": "#e5f5e7", "surface-tint": "#436650",
                        "surface-container-highest": "#d7e6d9", "primary": "#21422f",
                        "secondary-fixed": "#ffdcbd", "on-tertiary-fixed-variant": "#7e2b14",
                        "on-error": "#ffffff", "on-surface": "#121e16",
                        "on-secondary-fixed-variant": "#623f18", "surface-bright": "#eefdef",
                        "surface-variant": "#d7e6d9", "surface-dim": "#cfded0",
                        "outline-variant": "#c1c8c1", "on-secondary-container": "#7a532a",
                        "surface-container-low": "#e8f8e9", "outline": "#727972",
                        "secondary-container": "#ffca98", "surface": "#eefdef",
                        "on-primary-fixed": "#002111", "error": "#ba1a1a",
                        "error-container": "#ffdad6", "on-tertiary-container": "#ffb6a3",
                        "surface-container": "#e2f2e4", "tertiary-fixed-dim": "#ffb4a1",
                        "primary-fixed": "#c5ecd0", "on-surface-variant": "#424843",
                        "on-secondary": "#ffffff", "on-background": "#121e16",
                        "on-secondary-fixed": "#2c1600", "primary-fixed-dim": "#aacfb5",
                        "inverse-primary": "#aacfb5", "secondary-fixed-dim": "#f0bd8b",
                        "surface-container-lowest": "#ffffff", "on-primary-container": "#aad0b5",
                        "on-primary": "#ffffff", "on-tertiary-fixed": "#3c0800",
                        "background": "#eefdef", "surface-container-high": "#ddecde",
                        "tertiary-container": "#8f371f", "tertiary": "#70200b",
                        "secondary": "#7d562d", "on-primary-fixed-variant": "#2c4e39",
                        "on-error-container": "#93000a", "tertiary-fixed": "#ffdbd2",
                        "on-tertiary": "#ffffff", "primary-container": "#385a45",
                        "inverse-surface": "#26332a"
                    },
                    borderRadius: { DEFAULT: "0.25rem", lg: "0.5rem", xl: "0.75rem", full: "9999px" },
                    spacing: {
                        "space-xl": "2.25rem", "space-md": "1rem", "space-sm": "0.5rem",
                        "space-xs": "0.25rem", "space-lg": "1.5rem", "margin-mobile": "1rem",
                        "gutter-mobile": "0.75rem", "margin": "2rem", "gutter": "1rem"
                    },
                    fontFamily: {
                        "body-md": ["Plus Jakarta Sans"], "headline-sm": ["Plus Jakarta Sans"],
                        "headline-xl-mobile": ["Plus Jakarta Sans"], "label-sm": ["Plus Jakarta Sans"],
                        "label-md": ["Plus Jakarta Sans"], "body-lg": ["Plus Jakarta Sans"],
                        "headline-lg": ["Plus Jakarta Sans"], "headline-md": ["Plus Jakarta Sans"],
                        "label-lg": ["Plus Jakarta Sans"], "headline-xl": ["Plus Jakarta Sans"],
                        "headline-lg-mobile": ["Plus Jakarta Sans"], "body-sm": ["Plus Jakarta Sans"]
                    },
                    fontSize: {
                        "body-md": ["14px", { lineHeight: "20px", fontWeight: "400" }],
                        "headline-sm": ["18px", { lineHeight: "24px", fontWeight: "600" }],
                        "headline-xl-mobile": ["26px", { lineHeight: "32px", letterSpacing: "-0.01em", fontWeight: "800" }],
                        "label-sm": ["10px", { lineHeight: "14px", letterSpacing: "0.04em", fontWeight: "700" }],
                        "label-md": ["12px", { lineHeight: "16px", letterSpacing: "0.02em", fontWeight: "600" }],
                        "body-lg": ["16px", { lineHeight: "24px", fontWeight: "400" }],
                        "headline-lg": ["28px", { lineHeight: "34px", letterSpacing: "-0.01em", fontWeight: "700" }],
                        "headline-md": ["20px", { lineHeight: "26px", fontWeight: "700" }],
                        "label-lg": ["14px", { lineHeight: "20px", letterSpacing: "0.01em", fontWeight: "600" }],
                        "headline-xl": ["36px", { lineHeight: "44px", letterSpacing: "-0.02em", fontWeight: "800" }],
                        "headline-lg-mobile": ["22px", { lineHeight: "28px", letterSpacing: "-0.01em", fontWeight: "700" }],
                        "body-sm": ["12px", { lineHeight: "18px", fontWeight: "400" }]
                    }
                }
            }
        };
    </script>
    <style>
        @layer base {
            html, body { width: 100vw; margin: 0; padding: 0; min-height: 100vh; }
            body { overscroll-behavior-y: none; -webkit-tap-highlight-color: transparent; }
            .pb-safe { padding-bottom: env(safe-area-inset-bottom, 0px); }
            .pt-safe { padding-top: env(safe-area-inset-top, 0px); }
            main > :first-child { margin-top: 0 !important; }
            main > :last-child { margin-bottom: 0 !important; }
        }
        ::-webkit-scrollbar { display: none; }
        dialog::backdrop { background: rgba(18, 30, 22, .45); backdrop-filter: blur(4px); }
        dialog[open] { animation: sheetIn .24s cubic-bezier(.2,.8,.25,1) both; }
        @keyframes sheetIn { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: none; } }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .toast.show { opacity: 1 !important; transform: translate(-50%, 0) !important; }
        #customer-toast { left: 50% !important; bottom: 6rem !important; max-width: calc(100% - 2rem) !important; width: max-content; }
        body.cart-visible #customer-toast { bottom: 12rem !important; }
        #menu-container .catalog-card { animation: cardIn .35s cubic-bezier(.2, .8, .25, 1) both; animation-delay: calc(var(--i, 0) * 45ms); }
        @keyframes cardIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { #menu-container .catalog-card { animation: none; } }
        .sheet-open header, .sheet-open main > div { filter: blur(1.5px); opacity: .6; pointer-events: none; }
        .sheet-open { overflow: hidden; }
        #detail-sheet { animation: sheetUp .28s cubic-bezier(.2,.8,.25,1) both; }
        #date-sheet { animation: sheetUp .28s cubic-bezier(.2,.8,.25,1) both; }
        @keyframes sheetUp { from { opacity: 0; transform: translateY(28px); } to { opacity: 1; transform: none; } }
        .calendar-open { overflow: hidden; }
        /* Dashboard katalog: konten atas tetap, hanya area menu yang bergulir. */
        body[data-page="catalog"] { overflow: hidden; }
        body[data-page="catalog"] main { height: 100dvh; min-height: 100dvh; overflow: hidden; padding-bottom: 0; }
        body[data-page="catalog"] main > div { flex: 1 1 auto; min-height: 0; }
        .catalog-pinned { position: relative; z-index: 2; }
        .catalog-pinned::after {
            content: ''; position: absolute; left: 0; right: 0; top: 100%; height: 18px;
            background: linear-gradient(to bottom, rgba(238, 253, 239, .95), rgba(238, 253, 239, 0));
            opacity: 0; transition: opacity .18s ease; pointer-events: none;
        }
        .catalog-pinned.is-scrolled::after { opacity: 1; }
        @media (max-height: 700px) {
            body[data-page="catalog"] main { padding-top: calc(4rem + env(safe-area-inset-top, 0px)); }
            body[data-page="catalog"] main > div { flex: 1 1 0; min-height: 0; overflow: hidden; }
            body[data-page="catalog"] .catalog-pinned { flex: 0 0 auto; }
            body[data-page="catalog"] .catalog-scroll { flex: 1 1 0; min-height: 0; overflow-y: auto; }
        }
        #calendar-confirm:disabled { opacity: .5; cursor: not-allowed; }
        /* Badge angka 0 memakai display:flex, jadi atribut hidden perlu dipaksa. */
        [data-notif-count][hidden] { display: none; }
        @media (prefers-reduced-motion: reduce) { #detail-sheet, #date-sheet { animation: none; } }
    </style>
    <link rel="stylesheet" href="assets/customer.css?v=<?= $css('customer.css') ?>">
    <link rel="stylesheet" href="assets/quantity-input.css?v=<?= $css('quantity-input.css') ?>">
    <link rel="stylesheet" href="../assets/css/date-picker.css?v=<?= filemtime(__DIR__ . '/../../assets/css/date-picker.css') ?>">
    <link rel="stylesheet" href="assets/menu-notifications.css?v=<?= $css('menu-notifications.css') ?>">
</head>

<body class="bg-surface text-on-surface font-body-md text-body-md flex flex-col min-h-screen antialiased selection:bg-primary-fixed selection:text-on-primary-fixed" data-page="<?= htmlspecialchars($page) ?>">
    <header class="fixed top-0 w-full z-50 pt-safe bg-surface/85 backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)]">
        <div class="h-16 px-margin-mobile flex items-center justify-between gap-space-sm">
            <div class="flex items-center gap-space-sm min-w-0 flex-1">
                <div class="w-10 h-10 rounded-full bg-primary-container/15 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-primary-container text-[22px]">restaurant_menu</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <div class="flex items-center gap-1">
                        <span class="font-headline-sm text-headline-sm text-primary tracking-tight truncate">Se.Noesa</span>
                        <span class="px-1.5 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant font-label-sm text-label-sm uppercase tracking-wide">Katering</span>
                    </div>
                    <div class="flex items-center gap-1 text-on-surface-variant font-label-md text-label-md truncate">
                        <span class="material-symbols-outlined text-[14px] text-secondary">waving_hand</span>
                        <span class="truncate">Halo, <?= $username ?></span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-space-xs shrink-0">
                <a href="menu-notifications.php" aria-label="Notifikasi" class="w-11 h-11 rounded-full flex items-center justify-center text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low transition-colors relative">
                    <span class="material-symbols-outlined text-[24px]">notifications</span>
                    <span class="absolute top-1 right-1 min-w-[16px] h-4 px-1 rounded-full bg-tertiary text-on-tertiary font-label-sm text-label-sm leading-none flex items-center justify-center" data-notif-count<?= $unreadNotifications > 0 ? '' : ' hidden' ?>><?= $unreadNotifications ?></span>
                </a>
                <button type="button" data-logout aria-label="Keluar" title="Keluar" class="w-10 h-10 rounded-full flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-surface-container-low transition-colors">
                    <span class="material-symbols-outlined text-[21px]">logout</span>
                </button>
                <a href="riwayat.php" aria-label="Profil pelanggan" class="w-8 h-8 rounded-full bg-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-on-primary text-[18px]">person</span>
                </a>
            </div>
        </div>
    </header>
    <main class="flex flex-col relative w-full pt-16 pb-24 bg-surface min-h-screen">
        <div class="flex flex-col w-full">
<?php
    return $customer;
}

function customer_foot(): void
{
    $unreadNotifications = (int) ($GLOBALS['customer_unread'] ?? 0);
    $page = $GLOBALS['customer_page'] ?? '';
    $active = fn($key) => $page === $key ? 'customer-nav-link is-active' : 'customer-nav-link';
?>
        </div>
    </main>
    <nav class="customer-bottom-nav fixed bottom-0 w-full z-50 pb-safe bg-surface-container-lowest/90 backdrop-blur-xl shadow-[0_-4px_20px_rgba(30,43,34,0.06)]" aria-label="Navigasi pelanggan">
        <div class="h-16 px-space-sm flex items-center justify-around">
            <a href="index.php" aria-label="Beranda" <?= $page === 'catalog' ? 'aria-current="page"' : '' ?> class="flex flex-col items-center justify-center min-w-[56px] min-h-[44px] gap-0.5 transition-all <?= $active('catalog') ?>">
                <span class="material-symbols-outlined text-[22px]">cottage</span>
                <span class="font-label-md text-label-md">Beranda</span>
            </a>
            <a href="riwayat.php" aria-label="Pesanan" <?= in_array($page, ['history', 'detail'], true) ? 'aria-current="page"' : '' ?> class="flex flex-col items-center justify-center min-w-[56px] min-h-[44px] gap-0.5 transition-all <?= in_array($page, ['history', 'detail'], true) ? 'customer-nav-link is-active' : 'customer-nav-link' ?>">
                <span class="material-symbols-outlined text-[22px]">receipt_long</span>
                <span class="font-label-md text-label-md">Pesanan</span>
            </a>
            <button type="button" id="nav-cart" aria-label="Keranjang" class="customer-nav-link flex flex-col items-center justify-center min-w-[56px] min-h-[44px] gap-0.5">
                <span class="relative flex items-center justify-center">
                    <span class="material-symbols-outlined text-[22px]">local_mall</span>
                    <span id="nav-cart-count" class="absolute -top-1 -right-2 px-1.5 rounded-full bg-secondary text-on-secondary font-label-sm text-label-sm leading-none flex items-center justify-center min-w-[16px] h-4 hidden">0</span>
                </span>
                <span class="font-label-md text-label-md">Keranjang</span>
            </button>
            <a href="menu-notifications.php" aria-label="Notifikasi" <?= $page === 'notifications' ? 'aria-current="page"' : '' ?> class="flex flex-col items-center justify-center min-w-[56px] min-h-[44px] gap-0.5 transition-all <?= $active('notifications') ?> relative">
                <span class="relative flex items-center justify-center">
                    <span class="material-symbols-outlined text-[22px]">notifications</span>
                    <span class="absolute -top-1 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-tertiary text-on-tertiary font-label-sm text-label-sm leading-none flex items-center justify-center" data-notif-count<?= $unreadNotifications > 0 ? '' : ' hidden' ?>><?= $unreadNotifications ?></span>
                </span>
                <span class="font-label-md text-label-md">Notifikasi</span>
            </a>
        </div>
    </nav>

    <div id="detail-sheet-root" class="hidden fixed inset-0 z-[45]" role="dialog" aria-modal="true" aria-label="Detail menu" aria-hidden="true">
        <div class="absolute inset-0 bg-inverse-surface/40 backdrop-blur-sm transition-opacity" data-close-detail></div>
        <div class="fixed inset-x-0 z-50 flex justify-center pointer-events-none" style="bottom: calc(4rem + env(safe-area-inset-bottom, 0px));">
            <div id="detail-sheet" tabindex="-1" class="pointer-events-auto w-full max-w-md bg-surface-container-lowest shadow-2xl border border-outline-variant/30 overflow-y-auto overscroll-contain flex flex-col rounded-t-3xl rounded-b-none outline-none" style="max-height: calc(100dvh - 8.5rem - env(safe-area-inset-bottom, 0px));">
                <div id="detail-content"></div>
            </div>
        </div>
    </div>

    <dialog id="cart-dialog" class="customer-dialog cart-dialog" aria-labelledby="cart-title">
        <div class="cart-shell">
            <header class="cart-header">
                <button type="button" class="cart-back" data-close-cart aria-label="Kembali">
                    <span class="material-symbols-outlined text-[22px]">arrow_back</span>
                </button>
                <div class="cart-heading">
                    <h2 id="cart-title">Keranjang Pesanan</h2>
                    <p>Selesaikan pesanan hidangan Anda</p>
                </div>
                <span id="cart-menu-badge" class="cart-count-badge">0 Menu Terpilih</span>
            </header>
            <div class="cart-body">
                <section class="cart-address">
                    <div class="cart-address-head">
                        <div class="cart-address-title">
                            <span class="material-symbols-outlined text-[18px]">location_on</span>
                            <h3>Alamat Pengiriman</h3>
                            <span id="cart-address-badge" class="cart-address-badge" hidden>Utama</span>
                        </div>
                        <button type="button" id="cart-address-change" class="cart-address-change">Ubah Alamat</button>
                    </div>
                    <div id="cart-address-body" class="cart-address-body">
                        <p class="cart-address-empty">Memuat alamat...</p>
                    </div>
                </section>
                <div class="cart-section-head">
                    <div class="cart-section-title">
                        <span class="material-symbols-outlined text-[20px]">skillet</span>
                        <h3>Daftar Hidangan</h3>
                    </div>
                    <span id="cart-summary-badge" class="cart-summary-badge">0 Menu &bull; 0 Porsi</span>
                </div>
                <section id="cart-lines" class="cart-lines"></section>
                <section class="cart-breakdown">
                    <div class="cart-breakdown-head">
                        <h3>Rincian Pembayaran</h3>
                        <span id="cart-breakdown-count">0 Total Porsi</span>
                    </div>
                    <div class="cart-breakdown-row"><span>Subtotal Hidangan</span><span id="cart-subtotal">Rp 0</span></div>
                    <div class="cart-breakdown-row"><span>Ongkos Kirim</span><span id="cart-shipping">Gratis</span></div>
                    <div class="cart-breakdown-divider"></div>
                    <div class="cart-breakdown-total"><span>Total Pembayaran</span><b id="cart-dialog-total">Rp 0</b></div>
                </section>
                <button id="continue-checkout" class="cart-cta" type="button">
                    <span class="cart-cta-label">
                        <span class="cart-cta-title">Lanjut Pembayaran</span>
                        <span id="cart-cta-items" class="cart-cta-sub">0 Porsi Pre-Order</span>
                    </span>
                    <span class="cart-cta-right">
                        <span id="cart-footer-total">Rp 0</span>
                        <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                    </span>
                </button>
            </div>
            <nav class="cart-nav" aria-label="Navigasi pelanggan">
                <a href="index.php" class="cart-nav-item" aria-label="Beranda">
                    <span class="material-symbols-outlined text-[22px]">cottage</span>
                    <span class="cart-nav-label">Beranda</span>
                </a>
                <a href="riwayat.php" class="cart-nav-item" aria-label="Pesanan">
                    <span class="material-symbols-outlined text-[22px]">receipt_long</span>
                    <span class="cart-nav-label">Pesanan</span>
                </a>
                <a href="#" class="cart-nav-item cart-nav-active" aria-current="page" aria-label="Keranjang">
                    <span class="cart-nav-icon">
                        <span class="material-symbols-outlined text-[22px]">local_mall</span>
                        <span id="cart-nav-count" class="cart-nav-badge" hidden>0</span>
                    </span>
                    <span class="cart-nav-label">Keranjang</span>
                </a>
                <a href="menu-notifications.php" class="cart-nav-item" aria-label="Notifikasi">
                    <span class="material-symbols-outlined text-[22px]">notifications</span>
                    <span class="cart-nav-label">Notifikasi</span>
                </a>
            </nav>
        </div>
    </dialog>

    <dialog id="address-sheet" class="customer-dialog address-sheet" aria-labelledby="address-sheet-title">
        <div class="address-sheet-wrap">
            <div class="address-sheet-handle"></div>
            <div class="address-sheet-head">
                <div>
                    <h2 id="address-sheet-title">Pilih Alamat Pengiriman</h2>
                    <p>Pilih alamat tujuan untuk pesanan katering ini</p>
                </div>
                <button type="button" class="address-sheet-close" data-close-address aria-label="Tutup">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <div class="address-sheet-add">
                <button type="button" id="address-add-new" class="address-add-btn">
                    <span class="material-symbols-outlined text-[18px]">add_circle</span><span>Tambah Alamat Baru</span>
                </button>
            </div>
            <div id="address-sheet-list" class="address-sheet-list"></div>
            <div class="address-sheet-foot">
                <button type="button" id="address-use" class="address-use-btn">
                    <span>Gunakan Alamat Ini</span>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </div>
        </div>
    </dialog>

    <dialog id="address-form-sheet" class="customer-dialog address-form-sheet" aria-labelledby="af-title">
        <div class="asf-wrap">
            <div class="asf-handle"></div>
            <div class="asf-head">
                <button type="button" class="asf-close" data-close-af aria-label="Tutup form">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
                <div class="asf-head-text">
                    <h2 id="af-title">Tambah Alamat Baru</h2>
                    <p>Untuk pengantaran hidangan katering Se.Noesa</p>
                </div>
                <button type="button" id="af-reset" class="asf-reset">Reset</button>
            </div>
            <div class="asf-body">
                <div class="asf-field">
                    <label class="asf-label">Kategori Alamat</label>
                    <div class="asf-chips" id="af-chips">
                        <button type="button" class="asf-chip is-active" data-label="Rumah"><span class="material-symbols-outlined text-[16px]">home</span><span>Rumah</span></button>
                        <button type="button" class="asf-chip" data-label="Kantor"><span class="material-symbols-outlined text-[16px]">apartment</span><span>Kantor</span></button>
                        <button type="button" class="asf-chip" data-label="Apartemen"><span class="material-symbols-outlined text-[16px]">domain</span><span>Apartemen</span></button>
                        <button type="button" class="asf-chip" data-label="Lainnya"><span class="material-symbols-outlined text-[16px]">bookmark</span><span>Lainnya</span></button>
                    </div>
                </div>
                <div class="asf-grid">
                    <div class="asf-field">
                        <label class="asf-label" for="af-name">Nama Penerima</label>
                        <input id="af-name" class="asf-input" type="text" placeholder="Nama lengkap penerima" autocomplete="name">
                    </div>
                    <div class="asf-field">
                        <label class="asf-label" for="af-phone">Nomor WhatsApp / HP</label>
                        <div class="asf-input-prefix"><span>+62</span><input id="af-phone" type="tel" placeholder="8xx-xxxx-xxxx" autocomplete="tel"></div>
                    </div>
                </div>
                <div class="asf-field">
                    <label class="asf-label" for="af-address">Alamat Lengkap Detail</label>
                    <textarea id="af-address" class="asf-textarea" rows="2" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, dan kode pos"></textarea>
                </div>
                <div class="asf-field">
                    <label class="asf-label" for="af-notes">Catatan untuk Kurir <span class="asf-optional">Opsional</span></label>
                    <input id="af-notes" class="asf-input" type="text" placeholder="Contoh: Depan warung pandan, rumah cat putih">
                </div>
                <label class="asf-toggle-row" for="af-default">
                    <span class="asf-toggle-text">
                        <span class="asf-toggle-title">Jadikan Alamat Utama</span>
                        <span class="asf-toggle-sub">Alamat default untuk pengantaran pesanan berikutnya</span>
                    </span>
                    <input id="af-default" type="checkbox" class="asf-switch">
                </label>
                <p id="af-error" class="asf-error" role="alert"></p>
            </div>
            <div class="asf-foot">
                <button type="button" id="af-save" class="address-use-btn">
                    <span>Simpan &amp; Gunakan Alamat</span><span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                </button>
                <button type="button" class="asf-cancel" data-close-af>Batalkan</button>
            </div>
        </div>
    </dialog>

    <div id="customer-toast" class="toast fixed left-1/2 bottom-24 z-[60] -translate-x-1/2 translate-y-3 opacity-0 pointer-events-none rounded-full bg-inverse-surface text-inverse-on-surface px-5 py-3 font-label-md text-label-md shadow-xl transition-all" role="status" aria-live="polite"></div>
    <script src="assets/customer.js?v=<?= filemtime(__DIR__ . '/../assets/customer.js') ?>"></script>
    <?php if ($page === 'detail'): ?>
    <script src="assets/order-detail.js?v=<?= filemtime(__DIR__ . '/../assets/order-detail.js') ?>"></script>
    <?php endif; ?>
    <script src="assets/menu-notifications.js?v=<?= filemtime(__DIR__ . '/../assets/menu-notifications.js') ?>"></script>
    <script src="../assets/js/date-picker.js?v=<?= filemtime(__DIR__ . '/../../assets/js/date-picker.js') ?>"></script>
</body>

</html>
<?php
}
