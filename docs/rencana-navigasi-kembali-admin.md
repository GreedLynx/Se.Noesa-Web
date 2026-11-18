# Rencana navigasi kembali Admin

## Primary goal

Admin yang membuka Detail Pesanan dapat kembali ke halaman asal beserta filter yang dipilih, baik dari Dashboard maupun Daftar Pesanan. Akses langsung tetap menyediakan jalan kembali yang aman ke Daftar Pesanan.

Status: langkah 2 dan 3 (Phase 1 dan 2) diimplementasikan pada 18 November 2026; pengujian otomatis terarah lulus. Verifikasi browser Phase 3 masih terbuka; checklist Notion belum diubah.
Sumber kebutuhan: [REVISI PAK UNGGUL#3](https://app.notion.com/p/41b223978402804d86b3f6651e3e885d).

## Batas pekerjaan dan koordinasi

- Teman pengguna mengerjakan navbar setiap role dan perbaikan bug. Perubahan ini berfokus pada tautan detail, tombol kembali di konten, dan penyimpanan filter daftar.
- Hindari perubahan `public/admin/partials/layout.php`, navbar, CSS, Owner, dan Pelanggan. `public/admin/assets/admin.js` berpotensi disentuh bersama: cocokkan diff terbaru sebelum implementasi, jangan menimpa pekerjaan orang lain.
- Working tree saat discovery: branch `feature/penolakan-reschedule`, ahead 7; `HANDOFF.md` sudah berubah. Jangan reset/stash/commit perubahan tersebut secara massal.
- Saat implementasi, siapkan `feature/navigasi-kembali-admin` dari `dev` sesuai AGENTS.md. Cocokkan prasyarat dengan `dev` saat itu; jangan membawa fitur reschedule atau notifikasi yang belum ada secara diam-diam. Sesi plan ini tidak membuat branch.
- `.opencode/revisi.md` tidak ditemukan. Gunakan item Notion yang disebut pengguna sebagai cakupan; jangan membuat daftar revisi pengganti atau mencentang pekerjaan lain.

## Phase 0 — Documentation Discovery (selesai)

**How this serves the primary goal:** memastikan solusi mencakup semua jalur masuk ke detail dan penyebab filter hilang.

Dokumen dibaca: `docs/peta-kode.md`, `docs/aturan-bisnis.md`, `docs/api.md`, `docs/database.md`, `HANDOFF.md`, rincian Notion Unggul#3. Discovery kode lintas file didelegasikan read-only ke `reader`.

Temuan sumber:

| Sumber | Perilaku sekarang |
|---|---|
| `public/admin/order-detail.php:6` | Tombol kembali selalu `orders.php` |
| `public/admin/assets/admin.js:300,316,352,361` | Empat jalur Dashboard menuju detail: rekap produksi, pembatalan, prioritas, aktivitas |
| `public/admin/assets/admin.js:415,427` | Daftar desktop/mobile menuju detail tanpa konteks asal |
| `public/admin/assets/admin.js:450` (`ordersPage`) | Membaca tab/review/source dari URL; perubahan filter tidak ditulis kembali; pencarian belum dipulihkan dari URL |
| `public/admin/assets/admin.js:81` | Filter tanggal sudah memakai `URL`/`searchParams`, lalu navigasi halaman |
| `public/admin/assets/admin.js:574` | Sukses tambah pesanan offline langsung menuju detail |
| `public/admin/assets/admin.js:1085,1150,1156` | Aksi detail memuat ulang halaman: konteks asal harus bertahan |
| `public/admin/assets/admin.js:1185` | Saat detail gagal dimuat ada tautan kembali statis kedua |
| `scripts/test-admin-cancellation-list.cjs` | Pola uji VM Node untuk filter dan tautan desktop/mobile |
| `scripts/test-admin-dashboard.cjs`, `scripts/test-admin-order-detail.cjs` | Pola fixture Dashboard/detail dan pemeriksaan tautan |

Allowed APIs/pola:

- Pakai `URL`, `URLSearchParams`, dan `esc()` yang sudah dipakai `admin.js` untuk membentuk serta mengamankan atribut tautan.
- `history.replaceState(state, '', url)` menyimpan filter pada entri saat ini tanpa menambah kunjungan dan tanpa reload; pertahankan field state yang sudah ada. [Dokumentasi MDN](https://developer.mozilla.org/en-US/docs/Web/API/History/replaceState).
- `history.back()` kembali satu entri dan tidak melakukan apa pun bila tidak ada entri sebelumnya. Panjang history atau referrer saja tidak membuktikan halaman sebelumnya adalah halaman asal Admin. [Dokumentasi MDN](https://developer.mozilla.org/en-US/docs/Web/API/History/back).
- Endpoint dan skema tidak perlu diubah. Parameter navigasi hanya untuk halaman UI, tidak dikirim sebagai parameter POST API.

Verifikasi discovery: inventaris semua tautan `order-detail.php`, tautan kembali, serta redirect/reload terkait. Jangan menganggap tombol `Kembali ke default` pada kuota sebagai navigasi.

## Keputusan rancangan yang diusulkan

Gunakan URL asal eksplisit pada tautan detail (`return_to`), dengan filter daftar disimpan pada URL. Ini adalah versi minimum riwayat asal: tidak membuat stack global atau menyimpan kunjungan dalam database/localStorage.

Contoh: Dashboard tanggal tertentu → detail → Kembali mengembalikan Dashboard tanggal tersebut; daftar tab Dibatalkan + tinjauan + sumber + pencarian → detail → Kembali memulihkan kombinasi filter itu.

Tombol aplikasi mengikuti asal yang dibawa ketika detail dibuka. Tombol Back/Forward bawaan browser tetap menggunakan history browser. Untuk klik biasa tombol aplikasi, `location.replace()` menuju URL asal mencegah detail yang ditinggalkan menjadi sasaran Back berikutnya; jangkar tetap mempunyai `href` valid untuk tab baru dan tanpa JavaScript. Tidak memakai `history.back()` secara buta, karena tab baru, kunjungan luar aplikasi, serta reload tidak menjamin entri sebelumnya benar.

URL asal hanya boleh sama origin dan tepat ke `dashboard.php` atau `orders.php` di direktori Admin instalasi saat ini. Tolak login/logout, API, role lain, detail itu sendiri, protokol asing, URL tidak valid, dan parameter berulang/array yang tidak sesuai. Batasi query yang diteruskan ke filter yang dikenal; jangan meneruskan `return_to` bersarang. Jika tidak ada/invalid, gunakan `orders.php`. Jangan mengandalkan `document.referrer`, yang dapat kosong.

## Phase 1 — Pertahankan konteks asal dan filter

**How this serves the primary goal:** halaman asal dapat dibuka lagi dengan kondisi filter yang sama meskipun browser memuat ulang dokumen.

Implementasi:

1. Ikuti pola `ordersPage()` dan daftar nilai tab/review/source yang sudah ada. Pulihkan `q` ke input pencarian saat halaman dibuka.
2. Simpan `tab`, `review`, `source`, `q` yang berlaku memakai `history.replaceState`; pertahankan `tgl_kirim` dan parameter lain yang masih diperlukan. Review/source hanya diterapkan pada tab Dibatalkan. Perubahan pencarian harus tercatat sebelum tautan detail diklik, termasuk saat debounce API belum selesai.
3. Gunakan satu pembentuk URL detail kecil di `admin.js` untuk keempat jalur Dashboard, dua daftar desktop/mobile, serta redirect pesanan offline. Bentuk `return_to` dari URL halaman sumber terkini, bukan string filter yang ditulis ulang di setiap pemanggil. Sinkronkan href ketika filter berubah agar hasil render lama tidak membawa asal kedaluwarsa.
4. Nilai query di-encode dengan `URLSearchParams`, dan URL yang dimasukkan ke HTML melewati `esc()`.

Rujukan pola: `admin.js` filter tanggal, `loadOrders()`, `ordersPage()`, penyimpanan offline; uji filter `test-admin-cancellation-list.cjs`.

Verifikasi: URL dan input pulih konsisten; mengubah filter tidak menambah entri history per ketikan; query dengan spasi, `&`, tanda kutip aman; tanggal kuota tetap memengaruhi ringkasan sesuai kontrak API, bukan diam-diam menjadi filter daftar transaksi.

Anti-pattern guards: jangan membuat router, dependensi, sessionStorage lintas halaman, atau listener navbar global; jangan menambah pagination/scroll restoration khusus yang belum diperlukan.

## Phase 2 — Terapkan tombol kembali berdasarkan asal

**How this serves the primary goal:** tombol kembali berhasil dari semua jalur detail, termasuk reload, gagal memuat data, dan tab baru.

Implementasi:

1. Di `order-detail.php`, baca dan validasi `return_to` menggunakan fasilitas parsing URL PHP; tangani nilai non-string. Bentuk href yang hanya menuju halaman sumber yang diizinkan, escape dengan `htmlspecialchars`. Akses tanpa JS tetap memiliki tujuan yang benar.
2. Beri penanda khusus tombol kembali di konten; label `Kembali` atau `Kembali ke Dashboard/Daftar Pesanan` mengikuti tujuan, tanpa mengubah navbar/desain.
3. Tambahkan handler kecil pada `admin.js` untuk klik biasa yang memakai `location.replace()` ke href yang tervalidasi. Jangan cegat Ctrl/Cmd/Shift-click, tombol mouse tengah, download, atau target tab baru.
4. Terapkan tujuan sama pada tautan kembali ketika pemuatan detail gagal. Gunakan tujuan tervalidasi dari markup, bukan parsing URL mentah kedua dengan aturan berbeda.
5. Parameter asal bertahan pada reload setelah validasi pembayaran, tinjauan, dan upload. Tidak ada perubahan payload/aksi/transaksi.

Rujukan: `order-detail.php:6`, blok penanganan kegagalan detail dan reload `admin.js` yang tercatat pada Phase 0. Ikuti escaping PHP di layout tanpa mengedit layout.

Verifikasi: Dashboard/daftar/offline → detail → kembali; detail direload; ID tidak ada/API gagal; URL langsung tanpa asal; tab baru; JS mati; asal berbahaya ditolak; aplikasi dipasang pada subdirektori tetap bekerja.

Anti-pattern guards: jangan redirect PHP otomatis saat GET detail, jangan jadikan `return_to` sebagai otorisasi, jangan memperluas izin role, jangan gunakan `history.length > 1` sebagai satu-satunya pengaman.

## Phase 3 — Verification dan dokumentasi

**How this serves the primary goal:** membuktikan Admin benar-benar kembali ke tempat semula tanpa kehilangan filter atau mengganggu pengerjaan navbar.

- Tambah satu suite kecil `scripts/test-admin-back-navigation.cjs` mengikuti fixture Node/VM yang tersedia; sertakan pemeriksaan PHP lewat harness terisolasi atau uji request lokal tanpa DB untuk validasi URL asal. Uji perilaku tujuan/escaping, bukan hanya keberadaan string implementasi.
- Kasus wajib: semua enam jenis tautan masuk, redirect offline, filter kombinasi/query khusus, klik saat debounce tertunda, reload setelah aksi, error detail, tab baru, direct link, asal invalid/eksternal/role lain/API/self/nested, JS mati, pemasangan subdirektori, serta native Back/Forward sesudah tombol aplikasi tanpa loop detail.
- Jalankan `node scripts/test-admin-back-navigation.cjs`, `node scripts/test-admin-dashboard.cjs`, `node scripts/test-admin-order-detail.cjs`, `node scripts/test-admin-cancellation-list.cjs`, `node --check public/admin/assets/admin.js`, `php -l public/admin/order-detail.php`, dan `git diff --check`. Sesuaikan fixture VM hanya untuk platform API yang benar-benar digunakan.
- Uji browser desktop/mobile untuk Dashboard bertanggal dan daftar dengan filter, tab baru, reload, back/forward; data mutasi hanya fixture/lingkungan uji. Pastikan daftar kembali mengambil data terbaru setelah perubahan status, bukan menampilkan hasil lama dari cache browser.
- Perbarui `docs/peta-kode.md` (helper/callers/tests), `docs/api.md` (kontrak query navigasi UI, API tidak berubah), `docs/database.md` (tidak ada perubahan skema/migrasi), dan `HANDOFF.md` pada perubahan implementasi yang sama.
- Periksa diff terakhir terhadap navbar teman; jangan memasukkan perubahan tidak terkait. Bila diff ternyata menyentuh pembayaran/hak akses, minta review `reviewer` sesuai AGENTS.md.
- Checklist Notion hanya dicentang setelah pengujian selesai dan bila pengguna meminta pembaruan Notion. Tidak commit/push/merge pada sesi plan ini.

## Kriteria selesai dan batas verifikasi saat ini

Selesai ketika semua jalur detail kembali ke sumber yang benar beserta filter, fallback aman berfungsi, tidak ada loop saat navigasi, dan navbar/aksi pesanan tetap berfungsi. `return_to` memberi riwayat asal eksplisit, bukan stack kunjungan global; scroll dipulihkan browser bila tersedia, tanpa janji pemulihan posisi khusus.

## Progress implementasi — 18 November 2026

| Langkah chat / fase | Status | Bukti |
|---|---|---|
| 1 / Phase 0: discovery | Selesai | Inventaris sumber dan jalur masuk di atas |
| 2 / Phase 1: konteks asal | Selesai | `orderDetailUrl()` pada enam tautan + redirect offline; URL daftar menyimpan tab/review/q dan mempertahankan tanggal; href lama diperbarui sebelum debounce selesai |
| 3 / Phase 2: tombol kembali | Selesai | PHP membatasi nama relatif Dashboard/daftar; href bekerja tanpa JS; handler klik biasa replace; modifier/tab baru native; fallback error memakai href yang sama |
| 4 / Phase 3: verifikasi | Sebagian | Lima suite Node, syntax JS/PHP, dan diff check lulus; uji browser login desktop/mobile belum dilakukan |

Branch implementasi: `feature/navigasi-kembali-admin` dari `dev` (`62e3a23`).
`dev` belum memiliki filter sumber pembatalan yang ada di branch reschedule;
filter `source` sengaja tidak ditambahkan, sehingga cakupan mengikuti kontrol
yang tersedia pada `dev`. Saat integrasi branch tersebut, tambahkan `source`
secara konsisten pada persistensi URL, allowlist PHP, dan uji filter jika
fiturnya sudah masuk. Tujuan absolut, meskipun same-origin, juga ditolak:
seluruh pemanggil membentuk nama relatif agar origin/subdirektori tidak bergantung
pada konfigurasi host/proxy.

Pengujian yang benar-benar dijalankan dan lulus:

- `node scripts/test-admin-back-navigation.cjs`: filter round-trip, href terkini sebelum debounce, tujuan Dashboard bertanggal, modifier/tab baru, enam call sites dan redirect offline, serta 23 kasus validator PHP tanpa bootstrap/DB.
- `node scripts/test-admin-dashboard.cjs`.
- `node scripts/test-admin-order-detail.cjs`.
- `node scripts/test-admin-cancellation-list.cjs`.
- `node scripts/test-quota-ui.cjs`.
- `node --check public/admin/assets/admin.js`.
- `php -l public/admin/order-detail.php`.
- `git diff --check`.

Catatan proses: Git awalnya ditolak sandbox; setelah izin diberikan, pindah
branch masih tertahan perubahan HANDOFF. HANDOFF disalin ke direktori TEMP,
dipulihkan sementara untuk checkout, lalu salinannya dikembalikan pada branch
fitur. Tidak ada catatan yang dibuang. Fixture VM lama perlu diberi `URL`,
lokasi browser, dan history karena kini helper memakai API browser tersebut;
setelah disesuaikan semua uji di atas lulus.

Belum dilakukan: browser login, visual desktop/mobile, Back/Forward nyata,
reload setelah mutasi nyata, dan form offline end-to-end. Tidak mengubah
navbar/layout/CSS, endpoint/otorisasi, database, atau Notion; belum commit/push/merge.
