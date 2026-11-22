# Rencana header sticky, akses pencarian, dan animasi UI

## Primary goal

Pengguna dapat tetap mengakses navigasi dan pencarian saat menggulir halaman, sementara sidebar mobile dan kalender terbuka dengan gerakan yang halus, konsisten, serta tetap nyaman untuk pengguna yang mengurangi animasi.

## Keputusan dan batas pekerjaan

- Header `.customer-top` menjadi sticky pada semua halaman Pelanggan, di desktop dan mobile.
- Tombol search mengambang hanya ada di katalog. Tombol muncul setelah search bar asli terlewati, kemudian menggulir ke search bar dan memfokuskan input.
- Sidebar mobile bergerak masuk/keluar dari kiri dan backdrop memudar. Perilaku fokus, Escape, backdrop, serta `inert` yang sudah ada dipertahankan.
- Animasi kalender berlaku pada pemilih tanggal bersama untuk Pelanggan, Admin, dan Owner, di desktop dan mobile.
- Tidak mengubah endpoint, database, aturan katalog, filter, hasil pencarian, kalender native checkout, atau role/hak akses.
- Elemen mengambang harus memperhitungkan safe area dan `.cart-bar` agar tombol search tidak menutup aksi checkout.

## Phase 0 — Documentation Discovery (selesai)

**How this serves the primary goal:** memastikan rencana memakai layout, state, dan komponen bersama yang benar tanpa membuat sistem navigasi atau dialog baru.

Sumber yang dibaca:

- `docs/00-panduan/peta-kode.md`: peta sidebar Pelanggan, katalog, kalender bersama, dan pengujian yang sudah tersedia.
- `docs/01-spesifikasi/aturan-bisnis.md`: memastikan pekerjaan murni UI dan tidak mengubah aturan pemesanan atau kalender ketersediaan.
- `docs/00-panduan/HANDOFF.md`: riwayat hamburger, scroll katalog, elemen fixed, kalender, dan pengujian browser sebelumnya.
- `public/pelanggan/partials/layout.php:31-72`: markup bersama `.customer-rail`, backdrop, `.customer-main`, `.customer-top`, dan `.customer-content`.
- `public/pelanggan/assets/customer.css:142-155,239-295,491-510`: layout utama, header, tombol riwayat, dan cart bar fixed.
- `public/pelanggan/assets/catalog-mobile.css:42-75,146-185,385-445`: override katalog mobile, search bar, sidebar, dan backdrop.
- `public/pelanggan/assets/customer.js:86-113,789-820`: handler pencarian/filter serta state, fokus, Escape, dan focus trap sidebar.
- `public/pelanggan/index.php:26-41`: toolbar dan search bar katalog.
- `public/assets/js/date-picker.js:39-53,201-221`: pembuatan dialog kalender, `showModal()`, close, dan fokus tanggal.
- `public/assets/css/date-picker.css:78-92,154-160`: dialog, backdrop, dan breakpoint kalender bersama.
- `scripts/test-quota-ui.cjs`, `scripts/test-catalog-card-layout.cjs`, `scripts/test-date-picker.cjs`: pola fixture DOM/VM dan Chrome headless yang sudah dipakai.

Allowed APIs dan pola yang sudah tersedia:

- Pakai header bersama dari `customer_head()`; jangan menyalin header ke setiap halaman.
- Pakai `position: sticky`, `top: 0`, dan z-index lokal pada `.customer-top`; header tetap berada dalam alur layout sehingga tidak perlu spacer buatan.
- Pakai `IntersectionObserver` untuk mengetahui search bar sudah terlewati, `Element.scrollIntoView({ behavior: 'smooth' })` untuk kembali ke toolbar, dan `HTMLElement.focus({ preventScroll: true })` agar input mendapat fokus tanpa lompatan kedua.
- Pakai state `.is-open`, `inert`, `aria-expanded`, backdrop, dan focus trap yang sudah ada untuk sidebar; animasi hanya menambah state visual dan penyelesaian transisi keluar.
- Pakai native `<dialog>`, `showModal()`, selector `.date-calendar[open]`, serta `::backdrop` untuk kalender bersama.
- Pakai `@media (prefers-reduced-motion: reduce)` untuk menghilangkan gerakan yang tidak esensial.

Anti-pattern yang harus dihindari:

- Jangan memakai `position: fixed` untuk header atau menambah perhitungan spacer JavaScript.
- Jangan membuat search popup kedua, menduplikasi input, atau menjalankan fetch baru saat tombol search ditekan.
- Jangan mengubah `hidden` menjadi false/true pada saat yang memotong animasi keluar sidebar; selesaikan transisi lebih dulu dan tetap sediakan fallback bila event transisi tidak muncul.
- Jangan mengganti native dialog kalender atau menambah library animasi.
- Jangan menganimasikan `width`, `height`, `top`, atau `left`; gunakan `transform` dan `opacity`.

## Phase 1 — Header sticky lintas halaman Pelanggan

**How this serves the primary goal:** navigasi, identitas halaman, dan tombol hamburger tetap dapat dijangkau saat pengguna menggulir katalog, riwayat, atau notifikasi.

Implementasi:

1. Terapkan pola header bersama pada `.customer-top` di `public/pelanggan/assets/customer.css`: `position: sticky`, `top: 0`, serta z-index yang berada di atas konten tetapi di bawah sidebar, backdrop, dialog, dan elemen overlay yang memang harus lebih tinggi.
2. Pertahankan tinggi, border, dan latar putih agar konten yang lewat di bawah tidak terlihat menembus header.
3. Ubah override katalog mobile di `public/pelanggan/assets/catalog-mobile.css` yang saat ini menetapkan `position: relative`, sehingga tidak membatalkan sticky.
4. Periksa katalog, riwayat, dan `menu-notifications.php`, karena semuanya memakai `customer_head()` dan dapat memiliki struktur konten berbeda.

Rujukan pola: header bersama di `partials/layout.php`; hierarki z-index sidebar/backdrop di `catalog-mobile.css`; cart bar dan tombol riwayat fixed di `customer.css`.

Verifikasi:

- Header tetap menempel pada `top: 0` setelah scroll panjang di desktop dan mobile.
- Konten pertama tidak tertutup, lebar halaman tidak berubah, dan tidak ada overflow horizontal.
- Hamburger masih dapat dibuka dan sidebar/backdrop tetap berada di atas header.
- Dialog, cart bar, tombol riwayat, dan notifikasi tidak tertutup oleh z-index header.

Anti-pattern guards: jangan menambah listener scroll untuk posisi header, spacer manual, atau aturan per halaman bila selector bersama sudah mencukupi.

## Phase 2 — Tombol search mengambang pada katalog

**How this serves the primary goal:** pengguna yang sudah jauh di bawah katalog dapat kembali ke pencarian dengan satu aksi tanpa menggulir manual ke atas.

Implementasi:

1. Tambahkan satu tombol ikon search yang semantik di `public/pelanggan/index.php`, dengan `type="button"`, accessible name, dan SVG yang mengikuti ikon search bar saat ini. Tombol dimulai dalam keadaan `hidden`.
2. Di `catalogPage()` pada `public/pelanggan/assets/customer.js`, amati `.customer-search` dengan `IntersectionObserver`. Tampilkan tombol hanya ketika search bar sudah berada di atas batas header sticky; jangan tampilkan ketika search bar masih di bawah viewport sebelum pengguna mencapainya.
3. Saat tombol ditekan, fokuskan `#catalog-search` dengan `preventScroll`, lalu panggil `scrollIntoView({ behavior: 'smooth', block: 'center' })`. Jangan memanggil `refreshCatalog()` sampai pengguna benar-benar mengubah isi input.
4. Tambahkan gaya tombol fixed di `customer.css`/`catalog-mobile.css`: pojok kanan bawah, touch target minimal 44×44 px, safe-area aware, fokus keyboard terlihat, dan z-index di bawah dialog/sidebar.
5. Ketika `body.has-cart`, naikkan posisi tombol di atas `.cart-bar`; tanpa cart, gunakan jarak bawah normal. Karena tombol hanya ada di katalog, aturan tombol riwayat tidak perlu diubah.
6. Hormati `prefers-reduced-motion`: gunakan scroll instan atau perilaku default ketika pengguna meminta pengurangan gerak.

Rujukan pola: SVG search dan input di `index.php`; debounce pencarian di `customer.js`; posisi fixed/safe area pada `.cart-bar` dan `.history-link` di `customer.css`.

Verifikasi:

- Tombol tidak terlihat sebelum search bar dilewati, muncul setelah scroll ke bawah, dan hilang lagi setelah search bar terlihat.
- Klik/tap dan Enter/Spasi menggulir ke search bar serta meletakkan fokus pada input.
- Nilai pencarian dan hasil katalog tidak berubah hanya karena tombol ditekan.
- Tombol tidak bertumpuk dengan cart bar pada desktop/mobile dan tetap berada di viewport dengan safe area.
- Tanpa `IntersectionObserver`, search bar asli tetap berfungsi dan tidak ada error JavaScript; tombol dapat tetap tersembunyi sebagai progressive enhancement.

Anti-pattern guards: jangan membuat input kedua, overlay search, polling scroll, handler per kartu, atau fetch katalog dari tombol mengambang.

## Phase 3 — Animasi sidebar mobile

**How this serves the primary goal:** pembukaan dan penutupan navigasi terasa jelas tanpa mengorbankan akses keyboard, fokus, atau kemampuan menutup sidebar.

Implementasi:

1. Tambahkan transition `transform` pada `.customer-rail` mobile dan `opacity` pada backdrop di `catalog-mobile.css`. Gunakan durasi/timing yang sama agar panel dan backdrop terasa satu gerakan.
2. Pertahankan `.is-open` sebagai sumber state. Untuk membuka, lepaskan `hidden` backdrop sebelum class masuk diterapkan; untuk menutup, mulai state keluar lalu set `hidden` setelah `transitionend`, dengan fallback pendek bila transisi dinonaktifkan atau event tidak muncul.
3. Pertahankan urutan aksesibilitas `setOpen()`: `aria-expanded`, `inert`, fokus ke tombol tutup saat selesai membuka, fokus kembali ke hamburger saat menutup, Escape, klik backdrop, dan focus trap.
4. Saat breakpoint berubah ke desktop, bersihkan state transisi sementara, tampilkan rail desktop, lepaskan `inert`, sembunyikan backdrop, dan jangan meninggalkan timer/listener penutupan.
5. Pada `prefers-reduced-motion: reduce`, hilangkan durasi; state dan fokus harus tetap selesai pada frame yang sama.

Rujukan pola: markup/state sidebar di `layout.php` dan `customer.js`; selector transform/backdrop mobile di `catalog-mobile.css`.

Verifikasi:

- Sidebar masuk dari kiri dan keluar ke kiri tanpa berkedip atau hilang sebelum animasi selesai.
- Backdrop memudar masuk/keluar dan satu klik tetap menutup sidebar.
- Escape, tombol tutup, focus trap, pengembalian fokus, `aria-expanded`, dan `inert` tetap benar.
- Resize mobile→desktop dan desktop→mobile tidak meninggalkan sidebar tersembunyi atau backdrop aktif.
- Animasi tidak berjalan pada reduced motion dan semua kontrol tetap berfungsi.

Anti-pattern guards: jangan menganimasikan `display`, jangan menunda `inert` saat panel sudah tertutup, dan jangan menghapus focus trap/native keyboard behavior.

## Phase 4 — Animasi pembukaan kalender bersama

**How this serves the primary goal:** kalender memberi umpan balik visual yang konsisten di semua role dan ukuran layar saat dibuka.

Implementasi:

1. Tambahkan animasi masuk berbasis `opacity` dan `transform: translateY(...) scale(...)` pada `.date-calendar[open]` di `public/assets/css/date-picker.css`.
2. Tambahkan animasi/fade pada `.date-calendar[open]::backdrop` tanpa mengubah blur dan warna backdrop yang ada.
3. Gunakan keyframe dan durasi yang sama untuk desktop/mobile; breakpoint `max-width: 600px` hanya mempertahankan penyesuaian padding.
4. Tambahkan reduced-motion override yang menghapus durasi/transform. Fokus tanggal setelah `showModal()` di `date-picker.js` tetap dipertahankan.
5. Batasi implementasi pada animasi membuka sesuai kebutuhan; alur `dialog.close()`, pemilihan tanggal, Escape, klik luar, status ketersediaan, dan event input/change tidak diubah.

Rujukan pola: native dialog dan fokus di `date-picker.js`; `.date-calendar`, `::backdrop`, serta breakpoint di `date-picker.css`.

Verifikasi:

- Kalender Pelanggan, Admin, dan Owner muncul dengan animasi yang sama di desktop/mobile.
- Dialog tetap berada di tengah, tidak bergeser setelah animasi, dan tidak menimbulkan overflow.
- Fokus langsung berada pada tanggal aktif/tersedia; Escape, tombol tutup, klik luar, pemilihan tanggal, dan navigasi keyboard tetap bekerja.
- Reduced motion menampilkan dialog tanpa gerakan yang terlihat.

Anti-pattern guards: jangan menganimasikan ukuran grid tanggal, jangan menambah timer JavaScript untuk animasi pembukaan, dan jangan mengganti `showModal()` dengan overlay buatan.

## Phase 5 — Verifikasi terpadu dan dokumentasi

**How this serves the primary goal:** membuktikan keempat perubahan bekerja bersama pada halaman nyata dan tidak merusak navigasi, pencarian, cart, maupun kalender lintas role.

Implementasi/verifikasi:

1. Perluas fixture yang sudah ada, bukan membuat duplikasi: `test-quota-ui.cjs` untuk state/fokus sidebar dan tombol search; `test-catalog-card-layout.cjs` untuk sticky header, posisi tombol search, cart bar, safe area, dan overflow; `test-date-picker.cjs` untuk state open, animasi, fokus, reduced motion, serta role Pelanggan/Admin/Owner.
2. Jalankan lint/syntax PHP dan JavaScript pada file yang disentuh, `git diff --check`, suite katalog/keranjang, fixture sidebar, dan Chrome headless pada 320, 390, serta 1280 px.
3. Uji manual di browser: katalog panjang tanpa/dengan cart, riwayat panjang, notifikasi panjang, sidebar open/close/resize, pencarian setelah scroll, serta kalender setiap role.
4. Perbarui `docs/00-panduan/peta-kode.md`, `docs/01-spesifikasi/api.md`, `docs/01-spesifikasi/database.md`, dan `docs/00-panduan/HANDOFF.md` dalam perubahan yang sama. API/database harus mencatat bahwa kontrak endpoint dan skema tidak berubah; peta kode mencatat selector, handler, dan fixture baru.
5. Centang `.opencode/revisi.md` hanya bila file tersedia, item memang sesuai, dan seluruh verifikasi selesai. Gunakan commit terpisah yang masuk akal, misalnya sticky/search, sidebar animation, dan calendar animation.

Checklist akhir:

- Tidak ada listener scroll berfrekuensi tinggi, library baru, input search duplikat, atau perubahan endpoint/schema.
- Z-index berurutan: konten < header/tombol search < cart bar sesuai kebutuhan < sidebar/backdrop < dialog.
- Touch target, focus-visible, `aria-expanded`, `inert`, focus return, dan reduced motion terverifikasi.
- Header dan tombol mengambang tidak menutupi konten/aksi pada semua viewport uji.
- Seluruh test terkait lulus dan hasil uji nyata dicatat di HANDOFF.

