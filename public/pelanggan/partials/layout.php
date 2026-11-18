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
            body[data-page="catalog"] main > div { overflow-y: auto; }
            body[data-page="catalog"] .catalog-scroll { flex: none; overflow: visible; }
        }
        #calendar-confirm:disabled { opacity: .5; cursor: not-allowed; }
        /* date-picker.css hanya menata trigger di dalam .date-panel; dialog checkout butuh versi ringkasnya. */
        #checkout-dialog .date-trigger { display: flex; align-items: center; gap: 10px; width: 100%; min-height: 52px; padding: 10px 14px; border: 1px solid #c1c8c1; border-radius: 12px; background: #fff; color: #121e16; font: inherit; text-align: left; cursor: pointer; }
        #checkout-dialog .date-trigger svg { width: 22px; height: 22px; flex-shrink: 0; color: #385a45; }
        #checkout-dialog .date-trigger-value { flex: 1; min-width: 0; font-size: 15px; font-weight: 600; }
        #checkout-dialog .date-trigger-action { margin-left: auto; white-space: nowrap; font-size: 12px; font-weight: 600; color: #385a45; }
        #checkout-dialog .date-trigger:hover { border-color: #385a45; background: #fafcf9; }
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
    $active = fn($key) => $page === $key ? 'text-primary-container font-headline-sm' : 'text-on-surface-variant hover:text-on-surface';
?>
        </div>
    </main>
    <nav class="fixed bottom-0 w-full z-50 pb-safe bg-surface-container-lowest/90 backdrop-blur-xl shadow-[0_-4px_20px_rgba(30,43,34,0.06)]" aria-label="Navigasi pelanggan">
        <div class="h-16 px-space-sm flex items-center justify-around">
            <a href="index.php" aria-label="Beranda" class="flex flex-col items-center justify-center min-w-[56px] min-h-[44px] gap-0.5 transition-all <?= $active('catalog') ?>">
                <span class="material-symbols-outlined text-[22px]">cottage</span>
                <span class="font-label-md text-label-md">Beranda</span>
            </a>
            <a href="riwayat.php" aria-label="Pesanan" class="flex flex-col items-center justify-center min-w-[56px] min-h-[44px] gap-0.5 transition-all <?= $active('history') ?>">
                <span class="material-symbols-outlined text-[22px]">receipt_long</span>
                <span class="font-label-md text-label-md">Pesanan</span>
            </a>
            <button type="button" id="nav-cart" aria-label="Keranjang" class="flex flex-col items-center justify-center min-w-[56px] min-h-[44px] gap-0.5 text-on-surface-variant hover:text-on-surface transition-all">
                <span class="relative flex items-center justify-center">
                    <span class="material-symbols-outlined text-[22px]">local_mall</span>
                    <span id="nav-cart-count" class="absolute -top-1 -right-2 px-1.5 rounded-full bg-secondary text-on-secondary font-label-sm text-label-sm leading-none flex items-center justify-center min-w-[16px] h-4 hidden">0</span>
                </span>
                <span class="font-label-md text-label-md">Keranjang</span>
            </button>
            <a href="menu-notifications.php" aria-label="Notifikasi" class="flex flex-col items-center justify-center min-w-[56px] min-h-[44px] gap-0.5 transition-all <?= $active('notifications') ?> relative">
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

    <dialog id="cart-dialog" class="customer-dialog cart-dialog fixed inset-0 m-auto w-[min(92vw,460px)] max-h-[86vh] rounded-2xl bg-surface-container-lowest p-5 shadow-2xl overflow-y-auto" aria-labelledby="cart-title">
        <button type="button" class="dialog-close absolute right-3 top-3 w-9 h-9 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-on-surface" data-close-cart aria-label="Tutup">
            <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
        <p class="font-label-sm text-label-sm uppercase tracking-widest text-secondary font-semibold">Isi keranjang</p>
        <h2 id="cart-title" class="font-headline-md text-headline-md text-primary mt-1">Menu pilihan</h2>
        <div id="cart-lines" class="mt-4 flex flex-col gap-2"></div>
        <div class="cart-total-row flex items-center justify-between border-t border-surface-container mt-4 pt-4 font-headline-sm text-headline-sm text-on-surface">
            <span>Total sementara</span><b id="cart-dialog-total">Rp 0</b>
        </div>
        <button id="continue-checkout" class="customer-button primary w-full mt-4 py-3 rounded-full bg-primary text-on-primary font-label-lg text-label-lg font-semibold hover:brightness-110">Lanjutkan</button>
    </dialog>

    <dialog id="checkout-dialog" class="customer-dialog checkout-dialog fixed inset-0 m-auto w-[min(92vw,520px)] max-h-[90vh] rounded-2xl bg-surface-container-lowest p-5 shadow-2xl overflow-y-auto" aria-labelledby="checkout-title">
        <button type="button" class="dialog-close absolute right-3 top-3 w-9 h-9 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant hover:text-on-surface" data-close-checkout aria-label="Tutup">
            <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
        <div class="dialog-heading">
            <p class="eyebrow font-label-sm text-label-sm uppercase tracking-widest text-secondary font-semibold">Konfirmasi</p>
            <h2 id="checkout-title" class="font-headline-md text-headline-md text-primary mt-1">Data pemesanan</h2>
            <p class="muted text-body-sm text-on-surface-variant mt-1">Pilih alamat tersimpan atau tambahkan alamat baru.</p>
        </div>
        <form id="checkout-form" class="flex flex-col gap-3 mt-4">
            <label for="checkout-name" class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Nama pemesan
                <input id="checkout-name" name="nama_pemesan" required class="border border-outline-variant rounded-xl px-3 py-2.5 bg-surface-container-lowest text-on-surface outline-none focus:border-primary">
            </label>
            <label for="address-select" class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Alamat pengiriman
                <select id="address-select" name="alamat_id" class="border border-outline-variant rounded-xl px-3 py-2.5 bg-surface-container-lowest text-on-surface outline-none focus:border-primary">
                    <option value="">+ Tambah alamat baru</option>
                </select>
            </label>
            <div id="address-fields" class="flex flex-col gap-3">
                <label for="checkout-label" class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Label alamat
                    <input id="checkout-label" name="label_alamat" value="Rumah" maxlength="50" required class="border border-outline-variant rounded-xl px-3 py-2.5 bg-surface-container-lowest text-on-surface outline-none focus:border-primary">
                </label>
                <div class="form-row grid grid-cols-2 gap-3">
                    <label for="checkout-recipient" class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Nama penerima
                        <input id="checkout-recipient" name="nama_penerima" maxlength="100" required class="border border-outline-variant rounded-xl px-3 py-2.5 bg-surface-container-lowest text-on-surface outline-none focus:border-primary">
                    </label>
                    <label for="checkout-phone" class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Nomor HP
                        <input id="checkout-phone" name="telepon" type="tel" maxlength="20" required class="border border-outline-variant rounded-xl px-3 py-2.5 bg-surface-container-lowest text-on-surface outline-none focus:border-primary">
                    </label>
                </div>
                <label for="checkout-address" class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Alamat lengkap
                    <textarea id="checkout-address" name="alamat" rows="3" maxlength="2000" required class="border border-outline-variant rounded-xl px-3 py-2.5 bg-surface-container-lowest text-on-surface outline-none focus:border-primary resize-y"></textarea>
                </label>
                <label class="save-address-option flex items-center gap-2 font-label-md text-label-md text-on-surface-variant" for="checkout-save-address">
                    <input id="checkout-save-address" type="checkbox" name="simpan_alamat" value="1" checked class="accent-primary"> Simpan alamat ini untuk pesanan berikutnya
                </label>
                <label class="save-address-option flex items-center gap-2 font-label-md text-label-md text-on-surface-variant" for="checkout-default-address">
                    <input id="checkout-default-address" type="checkbox" name="jadikan_default" value="1" class="accent-primary"> Jadikan alamat default
                </label>
            </div>
            <div class="date-field flex flex-col gap-1">
                <label for="checkout-date" class="font-label-md text-label-md text-on-surface-variant">Tanggal kirim</label>
                <input id="checkout-date" name="tgl_kirim" type="date" required data-date-picker class="w-full border border-outline-variant rounded-xl px-3 py-2.5 bg-surface-container-lowest text-on-surface outline-none focus:border-primary">
            </div>
            <div class="catalog-policy rounded-xl bg-surface-container p-3 text-body-sm text-on-surface-variant" style="margin-top:10px">
                <ul class="list-disc pl-5">
                    <li>Pastikan tanggal kirim dan jumlah porsi sesuai kebutuhan acara.</li>
                    <li>Kuota menu mengikuti tanggal pengiriman yang dipilih.</li>
                </ul>
            </div>
            <label for="checkout-note" class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Keterangan pesanan (opsional)
                <textarea id="checkout-note" name="keterangan" rows="2" maxlength="120" placeholder="Contoh: tanpa sambal, kirim ke ruang rapat" class="border border-outline-variant rounded-xl px-3 py-2.5 bg-surface-container-lowest text-on-surface outline-none focus:border-primary resize-y"></textarea>
                <small class="field-hint text-body-sm text-on-surface-variant">Maksimal 120 karakter.</small>
            </label>
            <p id="schedule-note" class="schedule-note text-body-sm text-primary-container"></p>
            <p class="field-hint text-body-sm text-on-surface-variant">Batas maksimal pembayaran: 1 jam sejak pesanan dibuat. Jika belum dibayar, pesanan dibatalkan otomatis.</p>
            <p id="checkout-error" class="form-error text-body-sm text-error" role="alert"></p>
            <button id="submit-order" class="customer-button primary w-full py-3 rounded-full bg-primary text-on-primary font-label-lg text-label-lg font-semibold" type="submit">Kirim pesanan</button>
        </form>
    </dialog>

    <div id="customer-toast" class="toast fixed left-1/2 bottom-24 z-[60] -translate-x-1/2 translate-y-3 opacity-0 pointer-events-none rounded-full bg-inverse-surface text-inverse-on-surface px-5 py-3 font-label-md text-label-md shadow-xl transition-all" role="status" aria-live="polite"></div>
    <script src="assets/customer.js?v=<?= filemtime(__DIR__ . '/../assets/customer.js') ?>"></script>
    <script src="assets/menu-notifications.js?v=<?= filemtime(__DIR__ . '/../assets/menu-notifications.js') ?>"></script>
    <script src="../assets/js/date-picker.js?v=<?= filemtime(__DIR__ . '/../../assets/js/date-picker.js') ?>"></script>
</body>

</html>
<?php
}
