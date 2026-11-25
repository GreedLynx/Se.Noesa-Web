# Rencana penuntasan revisi Pelanggan

## Primary goal

Pelanggan mendapat pemberitahuan yang jelas saat proses pembayaran bermasalah, dapat melanjutkan dengan aman, serta melihat informasi pemesanan, ketersediaan, dan identitas se.noesa yang konsisten dan mudah dibaca.

## Sumber, keputusan, dan cakupan

- Sumber revisi: Kumpulan REVISI, Sheet1, https://docs.google.com/spreadsheets/d/1SroL8Zhi4Epptb-3J_RBzjrmvMPU1vlIjwWK5BtTVY4/edit . Audit sumber dilakukan 20 November 2026.
- Pengguna awalnya meminta plan dahulu, lalu mengizinkan eksekusi pada 20 November 2026. Lima item: F7, F18, F13, H4, H7. Phase 0–4 sudah diimplementasikan; Phase 5 baru sebagian karena verifikasi browser akhir belum berhasil dijalankan sampai selesai.
- Tambahan pengguna: ikon logo pada halaman login dan register di semua tampilan, mengikuti referensi `public/assets/img/desain/Login Page - Owner.png` dan `public/assets/img/desain/Register Page - Owner.png`. Path yang disebut pengguna memakai `asset`; lokasi aktual memakai `assets`. Kedua referensi sudah diperiksa visual.
- Login/register adalah halaman autentikasi bersama, meskipun memakai helper layout Owner. Terapkan pada halaman bersama untuk semua pengguna; registrasi publik tetap hanya role pelanggan. Logo berada di kanan atas area hero berpola pada referensi mobile; desktop menyesuaikan panel hero kiri dalam layout dua kolom. Gunakan aset resmi `public/assets/img/logo-icon.png` yang sudah dipakai sidebar/splash.
- F10 dan F21 dinyatakan tuntas oleh pengguna karena perubahan tampilan; jangan dibuka kembali.
- Klarifikasi F7 dari teman pengguna: jika ada error saat proses pembayaran, tampilkan notifikasi/pemberitahuan. F7 kini aktif dan berarti penanganan error pembayaran, bukan indikator tahapan pesanan.
- Cakupan F7: pemuatan detail/metode pembayaran dan gambar QRIS, upload/ganti bukti, serta penyegaran status setelah upload. Aplikasi menerima bukti transfer; tidak mendeteksi transaksi bank atau memproses uang.
- H6 logo dan H9 galeri foto sudah tersedia di kode; tidak perlu dibuat ulang.
- H7 menggunakan aset `foto/Pattern_se.noesa_Background.png`, sudah ditemukan dan diperiksa visualnya. Gambar berpola hijau; tidak perlu generator gambar atau dependency baru.
- Asumsi kerja H7: pola pada area latar konten semua halaman Pelanggan (katalog, riwayat, notifikasi), opacity awal 0.06; sesuaikan sekitar 0.04–0.08 setelah pemeriksaan desktop/mobile. Kartu, header, sidebar, form, dan dialog mempertahankan permukaan solid.
- H4 mencakup teks merek yang ditulis aplikasi pada halaman Pelanggan dan splash bersama. Tidak mengganti nama produk, data pengguna, nama file, logo raster, atau tampilan khusus Admin/Owner.
- F13: pindahkan blok info yang sama ke tepat sebelum field Tanggal kirim dalam dialog checkout. Tidak membuat halaman pembayaran baru atau menambah blok duplikat.
- Asumsi kerja F18: berlaku pada angka sisa di kartu katalog dan detail menu, termasuk perubahan jumlah lokal di detail. Ini menggantikan pilihan terdahulu untuk selalu menampilkan angka sisa. Label Stok habis serta semua validasi tetap ada.
- Tidak mengubah kontrak API, database, perhitungan kuota, minimum 4 porsi, maksimum 50 porsi per pesanan, atau batas H+7. Tidak membaca `.env`, menerapkan migrasi, atau mengubah status spreadsheet dalam tugas ini.

## Phase 0 — Documentation Discovery

**How this serves the primary goal:** memastikan lima revisi memakai alur pembayaran, struktur UI, dan sumber kuota yang sudah tersedia.

Status: selesai. Discovery utama dan laporan reader memetakan renderer, API, fixture, serta referensi logo. Implementasi berlangsung pada branch fitur yang sudah memiliki prasyarat UI; perubahan pembayaran/kuota tiap fase dipertahankan saat fase berikutnya.

Referensi yang dibaca:

- `docs/00-panduan/peta-kode.md`: katalog, layout bersama, kontrol porsi, dan pengujian.
- `docs/00-panduan/HANDOFF.md`: audit spreadsheet, logo, dan perubahan UI sebelumnya.
- `docs/01-spesifikasi/aturan-bisnis.md`: kuota efektif per tanggal, minimum, dan status ketersediaan.
- `docs/01-spesifikasi/api.md` dan `database.md`: payload katalog/checkout dan kuota default/override.
- `public/pelanggan/partials/layout.php:19,35,62,127-136`: merek dan urutan tanggal/info checkout.
- `public/pelanggan/index.php:13` dan `public/index.php:11,19`: teks merek katalog/splash.
- `public/pelanggan/assets/customer.js:58,249,292-318`: logout, merek, kartu, detail, dan pembaruan sisa.
- `public/pelanggan/assets/customer.css:1-24,1960-1974` serta `catalog-mobile.css`: token, background, info, dan override mobile.
- `public/pelanggan/api/catalog.php`: mengambil `daily_quota_snapshot($date)` lalu mengirim produk berisi `kuota` dan `sisa`.
- `scripts/test-customer-cart-quantity.cjs`: fixture VM dan assert sisa yang perlu disesuaikan untuk aturan visibilitas baru.
- `scripts/test-quota-ui.cjs`, `scripts/test-catalog-card-layout.cjs`: regresi kuota dan fixture browser katalog.
- `customer.js:18-38,574-600,780-800`: helper `api()`, `openHistoryDetail(id)`, pemuatan QRIS, dan handler submit bukti. Error upload sudah masuk `#proof-error`, tetapi pemuatan detail/metode dan refresh perlu diperiksa; QRIS gagal kini memakai gambar makanan sebagai fallback.
- `public/login.php`, `public/register.php`, `public/owner/partials/layout.php` (`head(..., true)`), dan `public/assets/css/app.css` (`.hero`, `.hero.short`, breakpoint auth 760px): halaman auth belum menampilkan ikon logo. Referensi memperlihatkan logo vertikal kanan atas pada hero; form login/register berada di bawah pada mobile, di kolom kanan pada desktop existing.
- `public/pelanggan/api/orders.php:194-260`, `api/payment-methods.php`, dan `scripts/test-order-cancellation-ui.cjs`: kontrak upload/error, pembacaan metode, dan fixture detail tanpa database aplikasi.

Allowed APIs/pola:

- Layout bersama `customer_head()`/`customer_foot()` untuk perubahan lintas halaman Pelanggan.
- Produk katalog memakai `product.kuota` sebagai kapasitas efektif tanggal pilihan, bukan konstanta 50/100 atau default produk.
- Tetap gunakan `showProduct(product)`, `setQuantity(value, normalize = false)`, serta elemen `#detail-sisa` yang sudah ada. `setQuantity` harus tetap dapat memperbarui elemen ketika angkanya disembunyikan.
- CSS background pada pseudo-element area konten dengan `pointer-events: none`, opacity hanya pada lapisan dekorasi; gunakan stacking context lokal agar tidak menutup konten atau hilang di belakang body.
- Salin aset saat implementasi ke `public/assets/img/Pattern_se.noesa_Background.png`; dari `public/pelanggan/assets/customer.css` URL-nya `../../assets/img/Pattern_se.noesa_Background.png`.
- Pakai token CSS yang sudah ada; tidak menambah library UI atau menganggap Bootstrap tersedia.
- F7 memakai `#proof-error[role="alert"]`, `textContent`, `toast()`, dan handler yang ada. Periksa seluruh pemanggil sebelum mengubah helper `api()` bersama; utamakan penanganan lokal bila cukup.

Verifikasi discovery: cek semua pemanggil renderer/selector dan fixture terkait sebelum edit. `.opencode/revisi.md` tidak ditemukan; jangan membuat checklist pengganti yang mengklaim status QA.

Anti-pattern: jangan menyamakan ambang tampilan 50% dengan `stok_status` API atau status kapasitas dapur/kalender; keduanya punya aturan sendiri.

## Prioritas dan dampak

| Urutan | Item | Alasan prioritas dan dampak |
|---|---|---|
| 1 | F7 | Kegagalan pembayaran harus terlihat dan bisa ditindaklanjuti; mengurangi ketidakpastian status bukti serta pengiriman ulang yang tidak perlu. |
| 2 | F18 | Informasi stok memengaruhi keputusan jumlah/menu; angka harus konsisten terhadap tanggal dan perubahan jumlah. |
| 3 | F13, H4, logo login/register | Info sebelum tanggal membantu pelanggan memahami pemesanan; penyamaan merek dan logo memperjelas identitas sejak masuk/daftar. Logo bersifat presentasional sehingga tidak mendahului kepastian pembayaran/stok. |
| 4 | H7 | Penyempurnaan visual setelah alur dan informasi utama beres; tidak menghambat pemesanan. |
| 5 | Verifikasi | Pastikan semua perubahan dan alur lama tetap bekerja sebelum ditandai selesai. |

## Phase 1 — Pemberitahuan kegagalan pembayaran (F7)

**How this serves the primary goal:** pelanggan mengetahui bagian pembayaran yang bermasalah dan langkah berikutnya tanpa salah mengira bukti atau transfer berhasil/gagal.

Status: implementasi selesai, source review tanpa temuan P1/P2. Suite `test-customer-payment-errors.cjs`, cancellation UI, quota UI, dan JS syntax lulus. Uji Chrome awal dengan API simulasi pada 320/390/1280px lulus; screenshot menemukan pesan gagal memuat tertimpa tombol tutup, lalu diperbaiki dengan `.payment-load-error`. Verifikasi browser akhir setelah seluruh fase masih berjalan.

Implementasi:

1. Audit jalur buka detail, muat metode, gambar QRIS, upload/ganti bukti, serta refresh setelah upload. Pertahankan penanganan error yang sudah bekerja; lengkapi jalur yang gagal diam-diam atau menampilkan pesan yang tidak tepat.
2. Bila detail gagal dimuat, tampilkan pemberitahuan yang terlihat melalui pola UI yang ada dan aksi mencoba memuat ulang. Bila hanya metode/QRIS gagal, detail pesanan dan status bukti tetap dapat dilihat; tampilkan pesan pada bagian pembayaran, bukan gambar makanan pengganti QRIS. Pisahkan keadaan metode kosong dari gagal memuat.
3. Untuk validasi file/nama, HTTP 4xx/5xx, jaringan gagal, atau respons tidak valid, tampilkan pesan Indonesia yang dapat ditindaklanjuti. Error form tetap terlihat di area pembayaran dengan `role="alert"`; arahkan fokus/scroll bila perlu agar tidak tersembunyi di dialog panjang. Gunakan pesan server yang aman melalui `textContent`, tanpa trace atau detail internal.
4. Ketika upload berlangsung, cegah submit ganda. Jika gagal dan server belum menerima bukti, pulihkan tombol agar dapat dicoba lagi selama izin/tenggat masih berlaku; jangan mereset form/file tanpa kebutuhan. Pada 401 ikuti alur login existing; pada 409/status atau tenggat berubah, muat ulang status dan tampilkan penjelasan yang sesuai, bukan terus menawarkan upload yang tidak sah.
5. Pisahkan keberhasilan POST upload dari kegagalan refresh. Jika server sudah mengonfirmasi bukti tersimpan tetapi refresh gagal, nyatakan bukti terkirim dan status belum berhasil dimuat; sediakan muat ulang tanpa mengirim ulang upload.
6. Jika koneksi putus sebelum respons diterima, hasil upload belum dapat dipastikan. Jangan menyatakan pembayaran atau upload pasti gagal; minta periksa/muat ulang status sebelum mencoba mengirim ulang. Tidak ada auto-retry POST.
7. Tambahkan uji regresi kecil memakai pola VM detail yang tersedia; buat `scripts/test-customer-payment-errors.cjs` hanya bila suite existing tidak sesuai. Tidak mengubah format/ukuran file yang diizinkan atau aturan pembayaran.

Referensi: `customer.js` fungsi `api()`, `openHistoryDetail(id)`, handler `#proof-form`; `api/orders.php` action `upload_proof`; `api/payment-methods.php`; `scripts/test-order-cancellation-ui.cjs`.

Verifikasi:

- Simulasikan gagal GET detail, gagal GET metode, metode kosong, gambar QRIS 404, validasi upload 422, konflik 409, sesi 401, error 500, jaringan gagal, dan JSON tidak valid. Pesan terlihat, tidak ada error Promise yang terlewat, dan tindakan selanjutnya sesuai.
- Uji upload sukses + refresh gagal terpisah dari upload gagal; tidak ada POST ulang otomatis atau pesan yang menganggap transfer bank gagal.
- Submit ganda ditahan; tombol pulih setelah kegagalan yang dapat dicoba ulang; file/nama tetap ada selama form yang sama masih berlaku; status terminal/tenggat tetap memblokir upload.
- Jalankan uji error pembayaran terarah dan `node scripts/test-order-cancellation-ui.cjs`, `node --check public/pelanggan/assets/customer.js`; uji browser desktop/mobile untuk visibilitas pesan dan fokus.
- Delegasikan review pembayaran ke reviewer jika tersedia; tindak lanjuti temuan sebelum melanjutkan.

Anti-pattern: jangan menambah payment gateway, sistem notifikasi baru, mengubah status pesanan dari client, membuka detail error server, auto-retry upload, atau memperluas pekerjaan menjadi perbaikan seluruh helper/API tanpa bukti kebutuhan.

## Phase 2 — Angka sisa hanya di bawah 50% (F18)

**How this serves the primary goal:** informasi ketersediaan menonjol saat relevan dan tetap akurat saat pelanggan mengubah jumlah.

Status: implementasi selesai, source review tanpa temuan P1/P2. `showRemainingStock()` menggunakan kuota efektif; detail mempertahankan `#detail-sisa` dalam pembungkus `#detail-stock` yang disembunyikan sesuai ambang. Suite cart quantity, quota UI, payment errors, cancellation UI, dan JS syntax lulus; verifikasi browser akhir sedang berjalan.

Implementasi:

1. Gunakan field `kuota`/`sisa` dan renderer yang sudah ada; pakai kondisi konsisten `kuota > 0 && remaining < kuota * 0.5`. Tepat 50% disembunyikan, bukan ditampilkan.
2. Kartu memakai `remaining = product.sisa`. Detail tetap memakai sisa setelah jumlah yang dipilih dan keranjang diperhitungkan, sesuai perilaku `showProduct`/`setQuantity` saat ini; perubahan ini hanya mengatur visibilitas.
3. Pertahankan elemen `#detail-sisa` dan pembungkusnya, ubah `hidden` pada pembungkus saat nilai melewati ambang. Perbarui visibilitas saat modal dibuka dan setiap perubahan jumlah yang valid. Jangan meninggalkan judul Sisa kuota tanpa angka.
4. Angka yang terlihat berbentuk `Sisa kuota: N porsi`, tanpa `/100` atau penyebut lain. Kartu stok nol tetap berlabel Stok habis; pada kuota nol jangan membagi dengan nol atau menampilkan persentase yang tidak valid.
5. Kuota hilang/tidak valid: jangan mengarang kapasitas; sembunyikan angka bersyarat. Pertahankan seluruh guard existing yang menonaktifkan pemesanan.
6. Sesuaikan fixture/asserter yang dahulu mengharapkan sisa selalu terlihat. Gunakan satu predicate kecil bersama hanya bila diperlukan agar kartu dan detail tidak berbeda aturan.

Referensi: `api/catalog.php`, `customer.js:239-250,277-318`, `scripts/test-customer-cart-quantity.cjs:17-44,75-77`, dan suite kuota/layout yang sudah tersedia.

Verifikasi:

- Kuota 50: sisa 26/25 tidak terlihat, 24 terlihat. Kuota 80: sisa 40 tidak terlihat, 39 terlihat; membuktikan tidak hardcode 50/100.
- Detail: tambah/kurangi melewati ambang kedua arah; sisa tetap berubah satu porsi dan menghitung pilihan keranjang seperti sebelumnya.
- Kuota nol, stok habis, produk tidak tersedia, pergantian tanggal/kategori, serta produk dengan minimum lebih tinggi tidak merusak UI/guard.
- Jalankan `node scripts/test-customer-cart-quantity.cjs`, `node scripts/test-quota-ui.cjs`, dan `node --check public/pelanggan/assets/customer.js`.
- Review terarah oleh reviewer jika tersedia untuk memastikan tampilan kuota tidak mengubah checkout, batas, atau akses; tindak lanjuti temuan sebelum menutup fase.

Anti-pattern: jangan mengubah `daily-quota.php`, status server, stok tersimpan, atau payload checkout untuk menyembunyikan teks.

## Phase 3 — Urutan informasi, nama merek, dan logo autentikasi (F13, H4, tambahan logo)

**How this serves the primary goal:** pengguna mengenali se.noesa sejak login/register dan pelanggan membaca informasi sebelum menentukan tanggal checkout.

Status: implementasi selesai, source review tanpa temuan P1/P2. Info sekarang sebelum tanggal; merek pada cakupan pelanggan/splash/auth lowercase; login/register memakai `.auth-logo`. Pemeriksaan menemukan URL aset shared auth `../assets/` salah jika aplikasi di subdirektori; helper `head()`/`foot()` kini memakai `assets/` pada auth public root dan mempertahankan `../assets/` pada Owner. PHP lint enam file, empat suite Node, JS syntax, dan diff check lulus. Browser akhir masih berjalan.

Implementasi:

1. Gunakan markup merek di layout bersama, katalog, teks JS, dan splash sebagai pola; ubah teks merek aplikasi menjadi `se.noesa`, termasuk title, aria-label, alt, deskripsi bawaan, pesan logout, dan pesan libur yang tampil di cakupan ini.
2. Periksa `text-transform` agar teks merek tidak tetap dipaksa kapital. Batasi override pada merek bila dibutuhkan, bukan semua heading.
3. Pindahkan `.catalog-policy` beserta list yang sudah ada dari sesudah tanggal ke sebelum label `checkout-date`. Pertahankan label/input, handler tanggal, isi list, dan aturan pengiriman/pembayaran.
4. Tambahkan satu elemen gambar logo resmi pada `.hero` masing-masing halaman login dan register, di atas lapisan `.drift`, mengikuti posisi kanan atas referensi. Tetapkan ukuran responsif, rasio asli, dan `object-fit: contain`; jangan meregangkan logo atau memakai screenshot desain sebagai aset halaman. Jika gambar merupakan satu-satunya identitas pada area itu, gunakan alt `se.noesa`.
5. Scope CSS baru pada `body[data-page="login"]`/`body[data-page="register"]` dan area `.app.auth`, memakai satu kelas logo yang sama. Pertahankan logo di kanan atas hero pada mobile/tablet dan panel hero kiri desktop; sediakan jarak aman agar tidak menimpa Hello/welcome atau heading register desktop. Logo harus tetap terlihat pada kedua sisi breakpoint 760px, layar pendek, dan orientasi landscape.
6. Gunakan URL gambar sesuai lokasi halaman `public/login.php`/`public/register.php`, yakni `assets/img/logo-icon.png`, dan cek aset melalui URL aplikasi aktual termasuk bila dipasang pada subdirektori. Jangan membuat helper/layout auth baru atau menambahkan logo ke semua halaman Owner melalui helper bersama.

Referensi: `partials/layout.php:19,35,62,127-136`, `index.php:13`, `customer.js:58,292,556,877`, `customer.css` bagian `.catalog-policy`, `public/index.php`, `public/login.php`, `public/register.php`, `public/assets/css/app.css` bagian hero/auth dan breakpoint 760px; kedua gambar referensi desain di atas.

Verifikasi:

- Pencarian terarah variasi `se.noesa`/`se.noesa` pada file cakupan; bedakan teks merek dengan path aset dan konten milik pengguna.
- Render checkout memastikan hanya satu blok info dan urutannya info → tanggal → bagian berikutnya.
- PHP lint file PHP yang berubah, `node --check` bila JS berubah.
- Render login/register di 320, 390, 759, 760, dan 1280px serta landscape pendek: tepat satu logo per halaman, gambar berhasil dimuat, proporsi benar, tidak tertutup/menimpa teks dan form, tidak ada overflow. Bandingkan mobile dengan dua referensi; desktop mempertahankan pola dua kolom existing.
- Periksa urutan fokus serta akses input, tombol password, tautan login/register, dan tombol submit; perubahan dekorasi tidak mengganggu form atau redirect role. Pengujian markup/render cukup untuk logo; jangan menambah tes backend autentikasi hanya untuk penambahan gambar.

Anti-pattern: jangan melakukan global replace seluruh repo, mengubah struktur form/payload checkout/autentikasi, menambah login per role, atau membuat registrasi Admin/Owner. Halaman forgot/reset password dan redesign penuh autentikasi berada di luar tambahan logo ini.

## Phase 4 — Background pola rendah opacity (H7)

**How this serves the primary goal:** mengisi latar kosong dengan identitas se.noesa tanpa mengurangi kenyamanan membaca atau mengakses tombol.

Status: implementasi selesai. Aset `Pattern_se.noesa_Background.png` disalin ke public. Lapisan aktual `.customer-main::after` memakai opacity 0.40, background berulang 402px, dan pointer-events none. `.customer-content` berada di atas dekorasi; main terisolasi agar pola mencakup tinggi halaman kosong/panjang. Verifikasi browser perlu dilakukan.

Implementasi:

1. Salin aset asli ke path public di Phase 0; jangan tautkan langsung ke folder `foto/` di luar public root.
2. Ikuti token/lapisan CSS layout pelanggan. Terapkan pola berulang pada pseudo-element kontainer konten yang mencakup tinggi halaman, dengan opacity 0.40 dan ukuran pola yang nyaman pada mobile/desktop.
3. Pastikan CSS mobile tidak menutup lapisan pola dengan latar lain. Pertahankan kartu/form/dialog solid serta sticky header, sidebar, backdrop, dan tombol mengambang tetap di atas dekorasi.
4. Tidak menambah kontrol opacity, animasi pola, atau library. Jangan menaruh opacity pada body/main karena akan memudarkan teks dan tombol.

Referensi: aset `foto/Pattern_se.noesa_Background.png`, `customer.css:1-24` dan layout `.customer-main`/`.customer-content`, `catalog-mobile.css`, `partials/layout.php`.

Verifikasi: aset berhasil dimuat; pola terlihat lembut pada ruang kosong katalog, riwayat, dan notifikasi di lebar 320/390/1280. Cek halaman panjang/kosong, tidak ada horizontal overflow, tombol tetap dapat diklik, serta kontras teks biasa minimal 4.5:1. Screenshot diperiksa setelah render, bukan sekadar memastikan CSS terpasang.

Anti-pattern: jangan memakai fixed overlay fullscreen yang menutup dialog, mengubah foto sumber, atau menerapkan pola pada Admin/Owner tanpa cakupan baru.

## Phase 5 — Verifikasi akhir dan dokumentasi

**How this serves the primary goal:** memastikan lima revisi benar-benar terlihat dan interaksi pembayaran/pemesanan tetap berjalan sebelum dilaporkan selesai.

Status: sebagian. Pengujian unit/fixture Node dan source review selesai; verifikasi browser akhir belum selesai. Dua permintaan eksekusi/eskalasi Chrome untuk suite menyeluruh dibatalkan; percobaan terakhir dibatalkan pengguna setelah sekitar 166 detik tanpa hasil/sesi yang dapat dilanjutkan. Tidak ada permintaan Chrome tambahan. Checklist visual tetap terbuka sampai eksekusi berikutnya menghasilkan bukti.

Checklist eksekusi:

- [x] F7: skenario error pembayaran terarah lulus suite Node, termasuk upload sukses tetapi refresh gagal; browser pembayaran awal juga lulus sebelum perubahan CSS akhir. Pemeriksaan visual akhir tetap terbuka di bawah.
- [x] H4: source teks merek aplikasi dalam cakupan sudah `se.noesa`; pemeriksaan render akhir tetap terbuka.
- [ ] Tambahan logo: login/register menampilkan satu logo resmi di hero pada mobile, tablet, desktop, dan landscape; sesuai posisi referensi serta tidak menghalangi form.
- [x] F13: markup hanya memiliki satu info pemesanan, tepat sebelum tanggal checkout; pemeriksaan render akhir tetap terbuka.
- [x] F18: batas <50%, kuota tanggal, update jumlah, dan stok habis lulus suite Node; pemeriksaan render akhir tetap terbuka.
- [ ] H7: pola dari aset yang diminta tampil lembut dan terbaca pada tiga halaman pelanggan di desktop/mobile.
- [ ] Suite cart quantity/kuota dan browser layout yang relevan lulus; fixture hanya disesuaikan untuk perilaku yang memang berubah.
- [x] PHP/JS syntax checks dan `git diff --check` bersih.
- [ ] Uji singkat checkout di browser: pilih tanggal/kategori/menu, ubah jumlah, buka keranjang dan checkout; tidak harus membuat pesanan di database aplikasi untuk memverifikasi UI ini.
- [x] Perbarui `docs/00-panduan/peta-kode.md`, `docs/01-spesifikasi/api.md`, dan `database.md` dengan dampak aktual (kontrak/skema tetap), serta `docs/00-panduan/HANDOFF.md` dengan hasil uji dan keterbatasan. Hasil browser akhir belum tersedia dan tidak diklaim lulus.
- [ ] Laporkan F10/F21 selesai karena keputusan pengguna; F7 ditutup hanya setelah uji error lulus. Jangan menandai spreadsheet sebagai passed tanpa QA dan instruksi pembaruan.

Sebelum implementasi, periksa branch dan working tree. Saat plan ditulis branch `feature/perbaikan-ui-pelanggan`; hanya HANDOFF audit yang berubah. Ikuti workflow fitur dari dev, periksa prasyarat UI yang ada, dan jangan membuang perubahan sesi lain. Tidak perlu switch/merge branch untuk tugas plan ini.

## Status eksekusi terkini

Phase 0–4 sudah selesai pada `feature/perbaikan-ui-pelanggan`. Empat suite Node (payment errors, cart quantity, quota UI, cancellation UI), PHP lint enam file, JS syntax, source review, dan diff check lulus. Regresi Chrome `test-catalog-card-layout.cjs` pada 320/390/1280px juga lulus. Tidak ada perubahan backend/database/kontrak API atau pembaruan spreadsheet; belum commit/push.

Phase 5 belum selesai. Regresi kartu katalog Chrome320/390/1280 lulus; subset browser pembayaran sebelumnya lulus sebelum perubahan CSS akhir. Suite browser menyeluruh auth/background/pembayaran akhir belum menghasilkan hasil atau screenshot final yang terverifikasi karena dua permintaan eksekusi/eskalasi dibatalkan; pembatalan terakhir oleh pengguna setelah sekitar166 detik. Tidak ada sesi proses yang dapat diteruskan, dan tidak dilakukan permintaan Chrome lagi.

Uji browser menggunakan markup/CSS/JS aplikasi dan API simulasi, bukan akun/database aplikasi nyata. Percobaan awal Chrome dalam sandbox gagal karena proses GPU. Masalah pesan error tertimpa tombol tutup ditemukan dari screenshot awal dan diperbaiki; fixture tenggat2099 diperbaiki menjadi satu jam agar tidak memicu overflow timer buatan pengujian. Risiko tersisa: posisi logo/teks pada breakpoint, layering dan keterbacaan pola, serta tampilan akhir error pembayaran belum diverifikasi menyeluruh.

Langkah berikutnya ketika eksekusi browser diizinkan: jalankan `node scripts/test-customer-revisions-browser.cjs "C:/Program Files/Google/Chrome/Application/chrome.exe"`, periksa screenshot hasilnya, tindak lanjuti kegagalan, kemudian tutup checkbox visual yang benar-benar lulus. Implementasi tidak boleh dilaporkan sebagai seluruh plan terverifikasi sebelum langkah ini selesai.
