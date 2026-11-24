# Handoff Se.Noesa-Web

## Respons daftar pesanan Admin (22 November 2026)
- Error sementara `Respons server tidak valid` pada `loadOrders()` dilaporkan pulih oleh pengguna. Status HTTP/body penyebab tidak tersedia; tidak ada perubahan kode, migrasi, atau database yang dipertahankan. Jika berulang, periksa respons Network `api/orders.php?tab=...` dan log PHP sebelum mengubah endpoint.

## Animasi pattern auth dan logo register desktop (20 November 2026)
- Pengguna meminta pattern login/register bergerak mulus dan logo register desktop disamakan dengan login. `pattern.png` berukuran 402 x 260 px; animasi sebelumnya mengubah `background-position` dengan jarak tepat satu tile. `.drift` kini menggerakkan lapisan yang diperluas satu tile memakai `translate3d(-402px, -260px, 0)`, sehingga area hero tetap tertutup dan siklus kembali ke gambar identik. Hero memotong kelebihan lapisan. Logo desktop login/register memakai aturan tengah dan ukuran yang sama; mobile tetap aturan dasar.
- Fixture browser auth kini memeriksa lapisan pattern menutup hero serta logo kedua halaman di tengah pada desktop. Perlu jalankan `node --check`, `git diff --check`, dan render browser jika tersedia. Tidak ada perubahan auth API/DB/aset.

## Logo login desktop (20 November 2026)
- Permintaan terbaru: logo login desktop diperbesar dan dipusatkan pada hero berpola bergerak. `public/assets/css/app.css` kini memiliki override khusus `body[data-page="login"]` pada breakpoint minimal 760px: logo berada di 50% panel dengan tinggi maksimal 300px dan 34vh untuk layar pendek. Posisi login mobile dan semua register tetap memakai aturan dasar kanan atas.
- Dokumentasi peta kode, API, dan database diperbarui. Fixture browser memeriksa pusat dan pembesaran logo login desktop. `node --check` fixture dan `git diff --check` lulus; render browser belum diverifikasi. Tidak ada endpoint, skema, atau aset baru.

## Eksekusi revisi Pelanggan (20 November 2026, status terbaru)
- Pengguna telah meminta menjalankan `docs/rencana-revisi-pelanggan-tersisa.md`. Phase 0–4 selesai pada `feature/perbaikan-ui-pelanggan`; Phase 5 baru sebagian dan verifikasi browser menyeluruh belum selesai. Catatan plan-only di bawah adalah riwayat, bukan status aktif.
- F7: GET detail/metode dan QRIS gagal memberikan pesan lokal pembayaran dan muat ulang; upload menangani validasi/status/sesi/jaringan/respons tidak valid, mencegah submit ganda, dan memisahkan POST sukses dari refresh gagal. Error tidak disimpan ke halaman Notifikasi; tidak ada auto-retry POST. F18: sisa angka hanya <50% kuota efektif; detail memperbarui pembungkus hidden saat jumlah berubah.
- F13/H4: info sebelum tanggal checkout; merek pelanggan/splash dan copy auth terkait memakai se.noesa. Login/register menampilkan `.auth-logo` di kanan atas hero. Shared Owner layout memilih assets/ untuk auth public root dan ../assets/ untuk Owner agar deployment subdirektori tidak kehilangan CSS/JS. Autentikasi/role tidak berubah.
- H7: aset `foto/Pattern_Se.Noesa_Background.png` disalin identik ke `public/assets/img/Pattern_Se.Noesa_Background.png`; `.customer-main::after` beropacity40%, pola402px, pointer-events none; konten di lapisan atas. Pseudo after menghindari konflik before mobile dan mencakup main kosong/panjang. Kartu/form/dialog tetap solid.
- File terkait: `public/pelanggan/assets/customer.js`, `customer.css`, `partials/layout.php`, `index.php`; `public/index.php`, `login.php`, `register.php`, `public/assets/css/app.css`, `public/owner/partials/layout.php`; test payment errors/cart/quota/cancellation serta `scripts/test-customer-revisions-browser.cjs`; docs peta/API/database/plan.
- Lulus: empat suite Node payment-errors/cart-quantity/quota-ui/cancellation-ui, PHP lint6 file, JS syntax, diffcheck, review F7/F18/final tanpa P1/P2. Regresi Chrome `test-catalog-card-layout.cjs`320/390/1280 lulus. Subset Chrome awal API-simulasi320/390/1280 lulus untuk F7 sebelum CSS akhir; screenshot menemukan close button menimpa pesan lalu diperbaiki. Tidak ada hasil/screenshot final terverifikasi untuk suite auth/background/pembayaran menyeluruh; jangan klaim seluruh plan selesai diuji.
- Percobaan gagal: Chrome sandbox GPU crash. Dua permintaan eksekusi/eskalasi browser menyeluruh dibatalkan, terakhir oleh pengguna setelah166detik tanpa hasil/sesi yang dapat diteruskan; tidak ada permintaan Chrome tambahan. Fixture tenggat2099 memicu overflow timer; diganti waktu sekarang+1jam. Tidak membaca .env, menjalankan transaksi DB aplikasi, mengubah spreadsheet, commit/push.
- Berikutnya saat eksekusi browser diizinkan: `node scripts/test-customer-revisions-browser.cjs "C:/Program Files/Google/Chrome/Application/chrome.exe"`, periksa screenshot dan tindak lanjuti kegagalan. Checkbox visual pada plan tetap terbuka; posisi logo/breakpoint, layering/kontras pola, dan error pembayaran akhir masih perlu diperiksa. Uji memakai markup/CSS/JS aktual+API simulasi, bukan login/database aplikasi nyata.

## Pembaruan plan: logo login/register (20 November 2026)
- Pengguna menambah ikon logo login dan register untuk semua tampilan. Referensi aktual ada di `public/assets/img/desain/Login Page - Owner.png` dan `Register Page - Owner.png` (bukan folder asset). Keduanya sudah dilihat: logo vertikal di kanan atas hero berpola.
- Tambahan masuk Phase 3 plan `docs/rencana-revisi-pelanggan-tersisa.md`, bersama informasi/merek; prioritas pembayaran dan stok tetap lebih awal. Reuse `public/assets/img/logo-icon.png`, markup pada `public/login.php`/`register.php`, CSS scoped auth di `public/assets/css/app.css`. Desktop menyesuaikan hero kiri layout dua kolom, mobile mengikuti referensi; cek kedua sisi breakpoint 760px, layar pendek, dan 320/390/1280px.
- Halaman autentikasi bersama berlaku bagi semua pengguna; registrasi tetap pelanggan. Tidak mengubah auth API, role, atau membuat login per role. Belum implementasi/render browser; yang selesai hanya discovery referensi dan pembaruan plan.

## Pembaruan plan: F7 aktif dan prioritas dampak (20 November 2026)
- Klarifikasi pengguna/teman: F7 adalah pemberitahuan ketika proses pembayaran mengalami error. F7 tidak lagi ditunda; bukan perubahan indikator tahap pesanan. F10/F21 tetap tuntas karena perubahan.
- `docs/rencana-revisi-pelanggan-tersisa.md` kini Phase 0–5: discovery, F7 error pembayaran, F18 angka sisa, F13/H4 informasi+merek, H7 pola background, verifikasi akhir. Urutan berdasarkan dampak pada kepastian pembayaran, keputusan jumlah pesanan, informasi, lalu visual.
- Discovery main+reader: submit bukti sudah catch error tetapi POST sukses dan refresh detail berada dalam try yang sama, berisiko pesan menyesatkan saat refresh gagal. GET detail/metode belum catch lokal; QRIS gagal memakai gambar produk. Plan mencakup pesan/retry yang sesuai, tidak auto-retry POST, status tidak pasti saat koneksi terputus, dan test upload sukses+refresh gagal.
- Hanya plan/handoff diperbarui; belum implementasi, suite/browser/DB atau commit/push. Temuan source belum direproduksi di browser. Berikutnya eksekusi Phase 1 F7 terlebih dahulu.

## Plan revisi Pelanggan tersisa (20 November 2026)
- Pengguna menetapkan F10 dan F21 tuntas karena perubahan; F7 dilewati dahulu karena maksud keluhan belum jelas. Keputusan ini menggantikan status belum pasti pada audit di bawah.
- Plan tersedia di `docs/rencana-revisi-pelanggan-tersisa.md`: H4 nama se.noesa lowercase; F13 info sebelum tanggal checkout; F18 angka sisa hanya <50% kuota efektif; H7 background dari `foto/Pattern_Se.Noesa_Background.png` beropasitas rendah.
- Aset sumber benar-benar ada dan sudah dilihat agent utama (sekitar 486 KB); belum ada salinan public bernama sama. Asumsi plan: latar area konten seluruh halaman Pelanggan dengan opacity awal 6%; kartu/dialog solid. Ambang F18 memakai sisa setelah pilihan lokal di detail dan diperbarui saat jumlah berubah; tepat 50% disembunyikan.
- Discovery main + reader selesai. Plan memiliki Phase 0–4, lokasi kode, pola yang dipakai, test edge case, regresi, dan verifikasi visual. Tidak ada perubahan kode/aset aplikasi, spreadsheet, DB, branch, commit, atau push. Belum menjalankan suite/browser; berikutnya implementasi sesuai plan dan evaluasi opacity dari render aktual.

## Audit spreadsheet revisi Pelanggan (20 November 2026)
- Tujuan: kelompokkan item CUSTOMER berstatus kosong dan Perubahan dari spreadsheet Kumpulan REVISI, Sheet1, ID 1SroL8Zhi4Epptb-3J_RBzjrmvMPU1vlIjwWK5BtTVY4. Pembacaan langsung menemukan 7 status kosong dan 2 Perubahan; Done/passed tidak diaudit ulang.
- Kode branch feature/perbaikan-ui-pelanggan: H6 logo resmi dan H9 galeri Foto Siap Kirim sudah tersedia; H4 nama huruf kecil belum konsisten; F13 informasi ada sebagai list sesudah tanggal checkout, belum persis permintaan di atas tanggal; F18 sisa kuota selalu tampil tanpa penyebut, belum aturan di bawah 50%. F7 indikator sudah dipetakan menurut status, tetapi perbaikan keluhan belum dibuktikan dengan uji alur.
- H7 background perlu acuan desain dan verifikasi visual. F10 Perubahan: ikon kiri atas kini logo resmi menuju katalog. F21 Perubahan: susunan empat menu Dashboard lama tidak ditemukan pada halaman katalog/riwayat saat ini.
- File bukti: public/pelanggan/partials/layout.php, index.php, assets/customer.js, assets/customer.css, api/orders.php; public/index.php. Tidak mengubah spreadsheet atau kode aplikasi, tidak menjalankan browser/test/DB. Status kode tidak membuktikan deployment atau kelulusan QA.
- Berikutnya: laporkan hasil; lakukan QA sebelum mencentang item. Path panduan lama docs/peta-kode.md dan HANDOFF.md root tidak ada; gunakan docs/00-panduan/. .opencode/revisi.md juga tidak ditemukan.

## Animasi logo splash
- Efek masuk splash memakai keyframe khusus splash-logo-enter (fade, naik, dan pop) dengan durasi 1.2 detik. URL app.css pada splash diberi versi filemtime untuk menyegarkan cache; redirect tetap mengikuti timer lama. Reduced-motion global tetap dihormati. PHP lint dan diff check dijalankan; visual browser belum diverifikasi.

## Logo resmi sidebar Pelanggan
- Ukuran gambar sidebar dibatasi eksplisit 32x40px dan overflow pembungkus disembunyikan agar tidak menimpa navigasi. Splash `public/index.php` kini memakai logo-icon.png dengan batas tinggi responsif; efek pop dan redirect lama dipertahankan.
- Ikon profil pada `.customer-logo` diganti logo resmi dari `foto/Icon_Logo.png`, disalin ke `public/assets/img/logo-icon.png` agar dapat diakses dari public root. Tautan menuju katalog, label aksesibel diperbarui, gambar memakai object-fit contain tanpa latar tambahan.
- PHP lint layout dan diff check lulus. Tampilan browser belum diperiksa; belum commit/push.

## Kondisi saat ini
- Branch `feature/full-stack`, HEAD sebelum Tahap 4A: `c54b4a9` (hardening notifikasi perubahan menu: closure audit, advisory lock MNOT, guard Owner read-only, `LIMIT 2` dashboard).
- Tahap 4A (data integrity & payment safety: D2, D3, C6) sudah diimplementasi dan seluruh pengujian lulus; perubahan belum di-commit.
- Tidak ada perubahan `.env`; database produksi tidak disentuh. Semua pengujian memakai PostgreSQL uji terisolasi `127.0.0.1:55432` (`SENOESA_TEST_PGPORT`).

## Fitur yang sudah masuk
- Rentang libur operasional dapat ditambah, diedit, dan dihapus; checkout tanggal libur diblokir tanpa mengubah pesanan lama.
- Dashboard Admin menampilkan rekap porsi per menu untuk tanggal pengiriman pilihan dengan tautan ke detail pesanan.
- Admin memiliki tab/filter riwayat pembatalan, antrean Dashboard maksimal dua pesanan di bawah rekap produksi, detail pembatalan, dan aksi `Sudah ditinjau`.
- Metadata sumber/status asal pembatalan dan peninjau pertama tersimpan. Peninjauan tidak mengubah status pembatalan atau menandakan refund telah selesai.
- Pesanan offline dan alasan penolakan pembayaran juga tercakup di `dev`.

## Verifikasi yang sudah dilakukan
- Uji PostgreSQL terisolasi untuk migrasi 001 sampai 021, pembatalan, role, metadata, transaksi bersamaan, kuota, dan job lulus.
- Uji UI Dashboard, Detail Pesanan, daftar/filter pembatalan, dan halaman terkait lulus.
- Uji `scripts/test-order-rejection-reason.php` dan `scripts/test-offline-order.php` lulus; keduanya membungkus data uji dalam transaksi yang di-rollback.
- Lint PHP dan `git diff --check` lulus.
- Migrasi 021 sebelumnya diterapkan secara terarah; pemeriksaan API Admin baca-saja menemukan jumlah pesanan tetap 47 dan 15 pesanan batal. Tidak ada POST tinjauan pada database aplikasi.
- Tahap 4A: seluruh 11 uji Node lulus; seluruh uji PHP lulus di database uji terisolasi (termasuk `test-menu-change-notifications.php` dengan D2/D3/C6 dan `test-menu-notification-concurrency.php` dengan skenario lock kanonik + tanpa deadlock). `test-payment-deadline.php` diverifikasi lewat harness pembatalan; mode standalone tidak dipakai karena menyambung ke DB konfigurasi aplikasi.

## Keputusan dan batas
- Tinjauan berlaku bersama bagi semua Admin, persisten sampai ditinjau, dan bukan konfirmasi refund. Refund penuh tetap ditangani di luar aplikasi.
- Dashboard hanya menampilkan dua pesanan prioritas, tetapi jumlah dan tautan daftar mencakup seluruh antrean.
- Job kedaluwarsa berjalan melalui CLI; endpoint GET Admin tidak menjalankannya.
- Migrasi dengan awalan 019/020 memiliki nama file berbeda untuk fitur libur/rekap dan fitur offline/penolakan. Periksa ledger berdasarkan nama file lengkap sebelum penerapan.
- Belum dilakukan pemeriksaan melalui sesi browser Admin login langsung.
- Tahap 4A (D2/D3/C6):
  - Harga penggantian dikunci saat pelanggan memilih (`MENU_NOTICE_PRICE_POLICY = 'snapshot_saat_pelanggan_memilih'`); konfirmasi Admin memakai harga snapshot untuk detail dan total, dan perubahan harga katalog hanya diaudit (`harga_katalog_saat_konfirmasi`, `perbedaan_harga_katalog` — migrasi `024_menu_change_price_audit.sql`, tersinkron di `database/schema.sql`).
  - Transparansi pembayaran hanya membaca data yang ada: `pembayaran` pada detail respons memuat status pembayaran, bukti, nominal tagihan dari `pesanan.total`, total lama/baru, selisih, dan status penyelesaian selisih. Tidak ada status pembayaran baru, charge, atau refund otomatis.
  - Urutan lock tabel kanonik `produk` → `kuota_harian` → `tanggal_libur_operasional` dibungkus `daily_quota_lock_tables()` (`app/daily-quota.php`) dan dipakai checkout pelanggan, pesanan offline, respons penggantian pelanggan, serta konfirmasi/penolakan Admin.
  - `scripts/test-payment-deadline.php` hanya dijalankan lewat harness `scripts/test-order-cancellation.php` (mode `--existing`); mode standalone menyambung ke DB konfigurasi aplikasi dan tidak boleh dipakai.

## Rujukan implementasi utama
- Aturan dan indeks file fitur: `docs/00-panduan/peta-kode.md` (kalender libur, rekap produksi, pembatalan, tinjauan Admin).
- Kalender libur: `public/admin/schedules.php`, `public/admin/api/holidays.php`, `app/daily-quota.php`; migrasi `019_operational_holidays.sql`.
- Rekap produksi: `public/admin/dashboard.php`, `public/admin/api/dashboard.php`, `public/admin/assets/admin.js`; VIEW pada `020_daily_production_report.sql`.
- Pembatalan pelanggan dan job kedaluwarsa: `public/pelanggan/api/orders.php`, `app/order-cancellation.php`, `app/order-expiration.php`, `scripts/expire-unpaid-orders.php`; migrasi terkait `016_order_cancellation.sql` dan `018_payment_deadline.sql`.
- Tinjauan pembatalan Admin: `public/admin/orders.php`, `public/admin/order-detail.php`, `public/admin/api/orders.php`, `public/admin/api/dashboard.php`; migrasi `021_cancellation_admin_review.sql`.
- Detail skema dan kontrak endpoint: `docs/01-spesifikasi/database.md` dan `docs/01-spesifikasi/api.md`. Nama migrasi harus dicocokkan lengkap karena awalan 019 dan 020 dipakai oleh migrasi fitur lain.

## Pekerjaan berikutnya
- Klarifikasi pengguna: pembatalan/penolakan oleh Admin mencakup status `Menunggu pembayaran`, `Menunggu Validasi`, dan `Diproses`, dilakukan manual per pesanan.
- `Menunggu pembayaran`: penanganan bergantung kondisi; kasus yang sudah ditetapkan adalah pesanan dibuat sebelum Admin melakukan close order pada tanggal pengiriman tersebut. Admin dapat membatalkan dengan catatan dan tawaran jadwal pengiriman pengganti.
- `Menunggu Validasi`: jika pembayaran tidak valid, Admin dapat menolak dengan catatan alasan kegagalan pembayaran. Jika pembayaran valid tetapi tanggal pengiriman ditutup oleh Admin, Admin dapat menolak dengan catatan dan tawaran jadwal pengganti.
- `Diproses`: pembatalan hanya ketika tanggal pengiriman ditutup oleh Admin setelah pesanan dibuat, disertai catatan dan tawaran jadwal pengganti.
- Pengguna menyetujui alur dalam aplikasi: Admin memberi satu tanggal usulan; pelanggan menerima atau menolak. Penerimaan memeriksa ulang tanggal libur dan kuota; penolakan membatalkan pesanan dan refund pembayaran valid tetap manual di luar aplikasi.
- Rencana minimum mempertahankan status pesanan selama tawaran menunggu dan menyimpan tawaran pada tabel terpisah. Tawaran paid/diproses berlaku 24 jam; unpaid tetap mengikuti tenggat pembayaran satu jam. Tanggal tujuan tidak memesan kuota sampai diterima.
- Rencana dan hasil eksekusi ada di `docs/rencana-penolakan-reschedule.md`. Migrasi 022 menambah tabel tawaran terpisah; Admin membuat/mengganti tawaran dan pelanggan menerima/menolak dengan token versi. Accept memeriksa kuota ulang; reject mencatat pembatalan pelanggan. Aksi pembayaran/produksi yang bentrok dijaga di server.
- Verifikasi yang lulus: PHP lint pada API Admin/Pelanggan dan job kedaluwarsa; JavaScript syntax check; `scripts/test-order-cancellation-ui.cjs`, `scripts/test-admin-order-detail.cjs`, `scripts/test-admin-cancellation-list.cjs`, `scripts/test-admin-dashboard.cjs`; `git diff --check`.
- Verifikasi yang belum dilakukan: integrasi PostgreSQL/migrasi dan browser login. Tes `scripts/test-payment-deadline.php` disesuaikan untuk tawaran aktif tetapi tidak dijalankan karena harness tersebut membaca konfigurasi proyek dan variabel koneksi PostgreSQL uji tidak tersedia. Tidak ada akses ke `.env` atau database aplikasi.
- Review sumber akhir selesai: tiga temuan tentang lock, tanggal kedaluwarsa, dan tombol Admin diperbaiki; reviewer tidak menemukan isu tambahan. Berikutnya, jalankan suite PostgreSQL terisolasi dan migrasi fresh/upgrade saat koneksi test tersedia, lalu commit dengan Conventional Commit. Jangan push/merge tanpa langkah eksplisit berikutnya.
- Percobaan awal membuat branch gagal karena sandbox tidak dapat menulis `.git`; operasi diulang dengan izin Git, lalu `dev` berhasil di-fast-forward dan branch fitur berhasil dibuat tanpa kehilangan perubahan handoff.
- Tidak ada perubahan `.env`; tidak ada push yang dilakukan pada tugas ini.

## Catatan lanjutan (16 Nov 2026)
- Saat endpoint `public/pelanggan/api/orders.php` memuat daftar pesanan, koneksi PostgreSQL gagal pada `PDO::__construct` dengan `SSL SYSCALL error: Connection reset by peer`; kegagalan terjadi sebelum query daftar pesanan berjalan, jadi ini belum menunjukkan bug SQL pada fitur reschedule.
- Log yang dibagikan memuat kredensial DB dalam trace argumen PDO. Rotasi password database dan pembaruan secret di konfigurasi/deployment diperlukan; kredensial tidak disimpan di repo/HANDOFF.
- Lingkungan sesi ini tidak menyediakan variabel koneksi PostgreSQL uji dan tidak ada utilitas `psql`/`pg_isready`; verifikasi DB terisolasi dan migrasi 022 tetap belum bisa dijalankan. Jangan menerapkan migrasi ke DB aplikasi sebagai pengganti.
- Syntax PHP endpoint tetap valid (`php -l`); `git diff --check` bersih.

## Pembacaan Notion (18 November 2026)
- Tujuan sesi: membaca Task Management dan halaman pendukung; tidak mengimplementasikan revisi.
- Dibaca: Task Management, seluruh 20 baris Tasks Tracker, rincian REVISI GEDE-GEDEAN, REVISI PAK UNGGUL#2/#3, REVISI MITRAA, Laporan Milestone 2, Manajemen Projek, Project Overview, Scope Statement, Model Kebutuhan (Use Case), serta Notulen & Catatan Evaluasi. Halaman Teknis: Web & Basis Data kosong.
- Tracker: 15 DONE, 4 DOING (Unggul#2, Unggul#3, Mitra, Laporan Milestone 2), 1 REVIEW (Revisi Gede-gedean). Status Notion belum diverifikasi terhadap implementasi.
- Unggul#2: tren pesanan Owner dengan pemilihan tanggal masih terbuka. Unggul#3: seluruh tujuh item terbuka: informasi kuota penuh, sudah bayar belum unggah bukti, WA Admin, libur saat ada pesanan, keterangan penolakan pembayaran pelanggan, tombol kembali Admin mengikuti riwayat, catatan pesanan Owner tidak sinkron.
- Mitra: nama se.noesa huruf kecil, warna, logo, background, dan tampilan foto siap kirim masih terbuka. Foto siap kirim dicentang di revisi besar tetapi belum dicentang di revisi mitra; perlu cek aplikasi sebelum menyimpulkan selesai.
- Notulen historis menyebut katering/minimal 10 sebagai contoh; aturan AGENTS.md tetap PO makanan biasa dan minimal 4 porsi per varian. Tidak ada keputusan bisnis baru dari sesi baca ini.
- Sumber utama: https://app.notion.com/p/f9a22397840283ccba2301273e1a3f99 dan https://app.notion.com/p/772223978402827d95f881b935f8fcca .
- Berikutnya: gunakan konteks ini untuk tugas yang diminta pengguna; pembacaan bukan izin mengerjakan seluruh backlog. Tidak mengubah Notion/kode aplikasi/database atau membaca .env. Lampiran PDF dan embed Drive/Figma tidak dibuka; tidak ada percobaan gagal.

## Plan navigasi kembali Admin (18 November 2026)
- Permintaan terbaru: buat plan dahulu untuk item Unggul#3 tombol kembali mengikuti halaman asal. Teman pengguna sedang mengerjakan navbar tiap role dan bug; Unggul#2 dikerjakan terpisah.
- Plan selesai: docs/rencana-navigasi-kembali-admin.md (Phase 0 discovery, Phase 1 konteks/filter, Phase 2 kembali berdasarkan asal, Phase 3 verifikasi/dokumentasi).
- Discovery read-only main + reader: tombol detail dan fallback error statis orders.php; empat sumber Dashboard, dua tautan daftar desktop/mobile, redirect sukses offline. Filter tab/review/source/q belum disinkronkan lengkap dengan URL. Reload detail setelah aksi harus mempertahankan asal.
- Rancangan diusulkan: return_to eksplisit pada tautan detail, validasi hanya Dashboard/Daftar Pesanan Admin pada origin/direktori instalasi yang sama, fallback orders.php; history.replaceState untuk filter; href server-side aman tanpa JS dan location.replace pada klik biasa agar tidak loop. Native Back/Forward browser tetap normal; tidak membuat stack global dan tidak memakai history.back secara buta.
- Batas: tidak mengubah navbar/layout/CSS/API/DB/role lain, tanpa library baru. admin.js berpotensi bentrok dengan perbaikan teman; cocokkan diff sebelum implementasi. Belum implementasi atau uji browser/aplikasi.
- Branch saat discovery feature/penolakan-reschedule ahead 7; HANDOFF sudah berubah dari tugas baca Notion. Plan dibuat tanpa mengganti branch; ketika implementasi siapkan feature/navigasi-kembali-admin dari dev dan cocokkan prasyarat, jangan membawa fitur lain diam-diam.
- .opencode/revisi.md tidak ditemukan saat dibaca. Tidak membaca .env, tidak mengubah Notion, tidak menjalankan migrasi, tidak commit/push/merge. Langkah selanjutnya adalah meninjau rancangan bersama pengguna sebelum implementasi.

## Implementasi navigasi kembali Admin (18 November 2026, status terbaru)
- Tujuan: pengguna meminta langkah 2 dan 3 sekaligus, beserta progress. Keduanya selesai pada feature/navigasi-kembali-admin, dibuat dari dev 62e3a23. Catatan branch/fitur sebelumnya di atas merupakan riwayat sesi terdahulu, bukan isi branch fitur ini.
- public/admin/assets/admin.js: orderDetailUrl untuk empat sumber Dashboard, dua daftar desktop/mobile, dan redirect offline; syncOrderUrl menyimpan tab/review/q dan mempertahankan tanggal, memulihkan pencarian, serta memperbarui href sebelum debounce. bindOrderBack memakai location.replace hanya untuk klik biasa; modifier/target tab baru tetap native. Error detail memakai tujuan tervalidasi yang sama.
- public/admin/order-detail.php: validasi return_to relatif hanya dashboard.php/orders.php, whitelist query dan validasi nilai, tolak duplikat/array/tujuan asing/control character, fallback orders.php. Href server-side tetap berfungsi tanpa JS. Guard Admin dan mutasi tidak diubah.
- Dev belum memiliki filter source; jangan membawa fitur branch reschedule diam-diam. Saat source terintegrasi, persistensi JS/allowlist PHP/tests perlu diperbarui bersama. Navbar/layout/CSS tidak disentuh.
- Docs api/database/peta dan progress di docs/rencana-navigasi-kembali-admin.md diperbarui. scripts/test-admin-back-navigation.cjs baru; fixture dashboard dan cancellation-list disesuaikan dengan API browser.
- Lulus: test-admin-back-navigation.cjs, test-admin-dashboard.cjs, test-admin-order-detail.cjs, test-admin-cancellation-list.cjs, test-quota-ui.cjs, node --check admin.js, php -l order-detail.php, git diff --check. Tes PHP validator diekstrak tanpa bootstrap/DB.
- Belum: browser login desktop/mobile, native Back/Forward nyata, reload mutasi nyata dan offline end-to-end. Phase 3 masih sebagian. Tidak membaca .env, mengubah DB/Notion, atau commit/push/merge.
- Percobaan gagal: switch branch awal ditolak sandbox; setelah eskalasi tertahan perubahan HANDOFF. Catatan diamankan ke TEMP lalu dikembalikan sesudah switch. Uji awal gagal karena fixture VM belum menyediakan helper/History dan ekstraksi handler CRLF; disesuaikan, kemudian seluruh uji terarah lulus.
- Berikutnya: selesaikan verifikasi browser Phase 3, cocokkan perubahan admin.js dengan pekerjaan teman, dan rangkum sebelum commit. Jangan mencentang Notion sebagai selesai tanpa verifikasi dan instruksi pembaruan.

## Diagnosis koneksi Supabase
- Error pelanggan terjadi pada lookup DNS saat PDO dibuat, sebelum query dan autentikasi. DNS Windows dan PHP CLI berhasil saat diperiksa ulang. Tes TCP 5432 gagal di sandbox dan berhasil di luar sandbox. Login database dan runtime Laragon belum diverifikasi.
- `app/bootstrap.php` mengaktifkan `zend.exception_ignore_args` agar kredensial tidak tampil sebagai argumen dalam stack trace. Dokumentasi API, database, dan peta kode diperbarui. PHP lint dan simulasi kegagalan koneksi tanpa kredensial nyata dijalankan. Percobaan CLI pertama memakai direktori sesi Laragon yang tidak writable di sandbox, lalu memakai TEMP.
- Password asli tampil di error yang dilaporkan. Rotasi password Supabase dan perbarui secret di lingkungan aplikasi tanpa menyalinnya ke repo. `.env` tidak dibaca atau diubah. Perubahan lokal ada di working tree branch navigasi dan belum di-commit.
- Simulasi PDO akhir lulus tanpa warning setelah mengatur path sesi CLI ke workspace melalui ini_set dan menghapus sesi uji. Opsi -d dengan path TEMP Windows sempat gagal diparse karena nama pendek mengandung tilde.

## Verifikasi browser navigasi kembali Admin
- Langkah 4 lulus pada feature/navigasi-kembali-admin. Chrome headless memakai PHP dan aset asli, sesi Admin fixture, dan API mock pada subdirektori /demo/admin/.
- Lulus: empat sumber Dashboard dan tanggal, Back/Forward tanpa loop, mobile 390px dan klik selama debounce, review POST/reload dengan daftar diperbarui, simpan offline dan kembali ke filter, detail gagal, klik tab terpisah tanpa JS, dan fallback asal eksternal. Lima suite regresi Node, syntax PHP/JS, dan git diff HEAD --check lulus.
- Uji awal Chrome di sandbox tidak menghasilkan hasil. Percobaan di luar sandbox yang diizinkan berhasil. Login dan mutasi database aplikasi belum diuji.
- Perbandingan dengan referensi lokal origin/dev 329b4f0 menemukan navbar v1 83b76ca dan filter source yang sudah masuk. Saat integrasi, pertahankan navbar, persist filter source di URL dan validasi PHP, lalu uji ulang hasil integrasi. Working tree dan staging pengguna tetap dipertahankan. Belum commit, merge, push, atau ubah Notion.

## Resolusi konflik merge dev ke navigasi Admin
- Empat konflik dari dev 329b4f0 ditandai selesai di indeks. Catatan dari HANDOFF.md dan bagian API yang khusus navigasi dipindah ke lokasi dokumentasi baru.
- Bootstrap mempertahankan redaksi argumen stack trace dan pengamanan sesi/CSRF dari dev. admin.js mempertahankan tampilan dev dan seluruh tautan asal. Filter sumber pembatalan disimpan di URL serta divalidasi di order-detail.php bersama filter tinjauan.
- Uji navigasi, Dashboard, filter daftar, urutan pesanan, kuota UI, notifikasi UI, dan sintaks PHP/JS lulus. Uji Detail Pesanan gagal pada tawaran jadwal ulang karena JavaScript Admin untuk fitur tersebut sudah tidak ada pada sisi dev. Reviewer mengonfirmasi temuan. Dashboard fixture diperbarui untuk teks dev dan fixture kuota untuk DOMContentLoaded.
- Pengguna menetapkan cakupan hanya resolusi konflik. Pemulihan JavaScript tawaran jadwal ulang ditunda. Merge belum di-commit atau dipush. Belum menguji browser atau database aplikasi setelah resolusi.

## Revisi Pelanggan dari spreadsheet Kumpulan REVISI
- Branch feature/revisi-pelanggan-sheet dari origin/dev. Sheet1 baris 3–22 dibaca. Batch pertama mencakup jumlah satu porsi per klik, ambang sisa porsi yang diperbarui di modal, penghapusan duplikasi jumlah pada popup, dan tombol Keluar yang kembali aktif setelah Escape. Tidak mengedit spreadsheet.
- customer.js menghitung stok tersisa dengan memperhitungkan jumlah yang sudah masuk keranjang. Kurangi berhenti di minimum, Tambah berhenti di stok atau 500 porsi, dan hapus memakai tombol terpisah. Payload checkout tetap id/qty. Tidak mengubah PHP, skema, pembayaran, atau hak akses.
- Empat suite Node lulus: test-customer-cart-quantity, test-quota-ui, test-order-cancellation-ui, dan test-menu-notifications-ui. JS syntax serta diff check lulus. Fixture uji pembatalan diperbaiki untuk timer dan teks tenggat yang sudah ada. Reviewer menjalankan self-check tambahan untuk batas stok/minimum/500. Login browser dan database aplikasi belum diuji.
- Foto kiriman dan pembatalan H-5 pascaverifikasi tersedia di kode. Refund tetap manual. Belum membuktikan data foto atau QRIS aktif di aplikasi. QRIS membutuhkan metode aktif dan gambar yang valid, serta verifikasi pengelolaan di luar scope Pelanggan. Branding, susunan informasi, dan jeda filter kategori masih terbuka. Tidak commit atau push.

## Resolusi merge revisi Pelanggan
- Konflik tunggal customer.js pada feature/revisi-pelanggan-sheet diselesaikan dengan mempertahankan aturan incoming dev (minimum efektif 10 atau minimum menu yang lebih tinggi, total maksimal 50) dan perubahan satu porsi per klik. Tambahan memeriksa stok per menu setelah isi keranjang, dan indikator sisa memakai stok aktual alih-alih ruang total pesanan.
- Skrip uji jumlah menguji dua menu pada batas total 50. Fixture kuota diselaraskan dengan dataset status katalog dari dev. Uji jumlah, kuota UI, pembatalan UI, notifikasi UI, ketersediaan stok PHP tanpa DB, sintaks JS, dan review sumber lulus. Browser/database belum diuji; merge belum di-commit atau didorong.

## Ringkas header notifikasi Pelanggan
- Menghapus paragraf tanpa class di catalog-intro dan paragraf mnc-summary pada menu-notifications.php sesuai permintaan pengguna. Renderer ringkasan JS sudah mengabaikan elemen yang tidak ada. PHP lint dan uji UI notifikasi lulus.

## Header notifikasi responsive
- Tautan history-link pada header notifikasi Pelanggan dihapus di semua viewport. Wrapper mnc-page kini mencakup header. CSS grid mulai 901px menempatkan tombol Muat ulang di kanan header. Mobile tetap memakai alur header lalu toolbar, dengan satu tombol refresh dan handler lama.
- PHP lint, uji UI notifikasi, pemeriksaan struktur dan diff check lulus. Render browser belum dicek.

## Sidebar Pelanggan dan hamburger mobile
- customer-mobile-nav dihapus. Tombol logout dipindah dari header ke bawah customer-rail dengan margin-top:auto. Navigasi Riwayat mobile yang sebelumnya tersembunyi kini tampil dalam sidebar.
- Mobile <=700px memakai hamburger di header. Sidebar awalnya tersembunyi dan inert, dibuka dengan hamburger, ditutup dengan tombol/klik latar/Escape. Fokus berputar di sidebar dan dikembalikan ke hamburger. Desktop menampilkan rail biasa.
- Update layout.php, customer.js, customer.css, catalog-mobile.css, fixture quota-ui dan docs. Self-check VM buka/tutup/Escape/backdrop/desktop, suite kuota, keranjang, pembatalan, notifikasi, PHP lint dan JS syntax lulus. Render browser langsung belum diperiksa. Belum commit/push.

## Perataan katalog dan hamburger
- CSS katalog sebelumnya menyembunyikan customer-top sehingga hamburger tidak tampil. Header kini terlihat dan tidak menyusut dalam flex column. Padding konten dibuat simetris, judul/kategori di tengah, dan offset cart-bar mengikuti tengah viewport tanpa ruang sidebar lama. Padding kiri khusus <=360px dihapus.
- Uji keranjang dan kuota lulus. Chrome headless dengan fixture markup katalog dan CSS aktual mengukur viewport 504px serta 1262px tanpa overflow. Pada mobile hamburger 44x44 tampil dan pusat grid sama dengan pusat viewport. Permintaan window-size 320/390 dibatasi minimum jendela Chrome menjadi 504px sehingga viewport sempit itu belum diverifikasi. Tidak akses database.
- Header katalog mobile diperbarui sesuai arahan: div judul rata kiri, eyebrow dan catalog-description ditampilkan kembali. Ruang kanan 56px tetap disediakan untuk tautan Riwayat. Diff check lulus; render browser belum diulang.

## Scroll katalog dan toolbar mobile
- Kategori toolbar rata kiri. Menghapus penguncian tinggi viewport dan overflow hidden pada pembungkus katalog; grid menu mengikuti scroll dokumen. Saat keranjang ada, padding bawah 110px menjaga menu terakhir dari overlay keranjang.
- Chrome headless fixture katalog berhasil scrollY 400 dengan rentang scroll 968px, kategori flex-start, tanpa horizontal overflow. Uji kuota dan keranjang lulus. Panel tanggal belum diubah; pengguna meminta opsi peringkasan setelah dua perbaikan ini.

## Panel tanggal katalog minimal
- Pengguna memilih opsi 2: saat normal panel hanya menampilkan label dan pemilih tanggal. Eyebrow, judul, dan petunjuk H+7 yang panjang dihapus dari panel; batas H+7 tetap pada atribut input dan validasi server.
- `index.php` mempertahankan tiga area pesan tersembunyi dan relasi aksesibilitas input. `customer.js` menampilkan pesan singkat hanya saat libur, kuota harian penuh/hampir penuh, atau ada menu habis. Tanggal normal menyembunyikan kembali pesan. CSS memberi pesan yang muncul satu baris tersendiri.
- Uji quota-ui dan cart quantity lulus, PHP/JS syntax dan diff check lulus. Kalender DOM Chrome 320/390/1280 lulus pada fixture; login aplikasi dan database asli tidak diuji. Tidak commit/push.

## Katalog stok lengkap dan dialog Keluar
- Permintaan terbaru: pemilih tanggal desktop selebar panel dan pesan di bawahnya; tampilkan semua produk serta sisa kuota pada kartu/detail; ikon peringatan merah dan tombol tambah pudar untuk kuota nol; hapus tautan Riwayat pada katalog desktop/mobile; dialog Keluar berteks lebih besar dan rata tengah.
- `api/catalog.php` sekarang mengirim semua produk yang cocok filter dalam `items`, termasuk unavailable. Field `available` dan validasi checkout tetap membatasi pemesanan; `habis` tetap untuk pesan. Kartu produk tidak tersedia masih dapat membuka detail melalui klik atau Enter/Spasi, tetapi tombol tambah tetap nonaktif. Tidak ada migrasi/penulisan DB.
- `index.php`, `customer.js`, `customer.css`, dan `catalog-mobile.css` diperbarui. Reviewer menemukan detail stok habis awalnya tak terjangkau keyboard; diperbaiki dengan fokus serta Enter/Spasi dan uji fixture. Uji quota-ui, cart quantity, helper stok PHP, PHP/JS lint, diff check, dan kalender Chrome 320/390/1280 lulus. Login aplikasi/DB asli dan visual dialog Keluar belum diuji. Tidak commit/push.

## Kartu katalog mobile dan label stok habis
- Kartu mobile kini memiliki latar putih, batas, radius, bayangan, jarak antarkartu, dan ruang luar/dalam. Gambar, teks, label stok, dan tombol tidak menempel sisi kartu/halaman.
- Ikon peringatan pada kedua ukuran diganti label horizontal `Stok habis` dengan latar abu transparan dan teks abu gelap. Tombol tambah tetap pudar/nonaktif. Tanda kutip berlebih pada atribut kartu yang ditemukan saat pemeriksaan renderer diperbaiki.
- `scripts/test-quota-ui.cjs`, `scripts/test-customer-cart-quantity.cjs`, sintaks JS, diff check, dan Chrome fixture 320/390/1280 lulus. Belum diuji dengan data/login aplikasi asli. Tidak commit/push.

## Label stok habis menimpa foto
- Sesuai permintaan terakhir, label `Stok habis` dipindah dari atas tombol tambah ke sudut kanan atas foto produk pada desktop dan mobile. Tombol tambah tetap pudar/nonaktif dan kartu tetap berjarak dari tepi.
- `customer.js` memakai pembungkus `.menu-photo` agar posisi label mengikuti foto; `customer.css` dan `catalog-mobile.css` mengatur ukuran. Fixture Chrome memastikan label berada dalam batas foto pada 320/390/1280px; tes kuota UI, keranjang, sintaks JS dan diff check lulus. Login aplikasi/DB asli belum diuji. Tidak commit/push.

## Label stok habis di pojok kartu
- Permintaan berikutnya memindahkan label ke pojok kanan atas `.menu-card`, bukan mengikuti foto. Renderer menempatkan label sebagai anak kartu dan CSS memakai posisi absolut 12px dari atas/kanan. Fixture Chrome 320/390/1280px memastikan posisi pojok kartu, tes kuota UI dan keranjang lulus.
- Pengguna memilih opsi 3: pita abu dengan sudut membulat kecil. CSS memakai abu gelap transparan, teks putih tebal, dan bayangan tipis. Chrome 320/390/1280px memeriksa posisi dan gaya; tes kuota UI, keranjang, sintaks JS, dan diff check lulus. Login aplikasi/DB asli belum diuji. Tidak commit/push.

## Status kuota pada kalender pelanggan dan kapasitas dapur
- Kalender memberi titik warna di bawah setiap tanggal dan legend untuk Tersedia, Hampir penuh, Penuh, Menu Habis, dan Libur. Status berasal dari kapasitas dapur dan sisa menu; tombol `Tanggal tersedia` mencari status `available` terdekat mulai H+7, hingga 12 bulan. Jika fetch gagal, tombol menampilkan coba lagi; data tak dikenal tidak dipilih otomatis.
- Kapasitas dapur bersama sekarang floor(0.8 * jumlah kuota efektif produk pada tanggal). Override sudah diterapkan; tanggal libur menurunkan semua kuota efektif ke nol. Perubahan helper berlaku pada checkout, offline, Admin/Owner, dan kalender; tidak ada migrasi. Status Hampir penuh untuk sisa 40% atau kurang (asumsi karena jawaban batas tepat40% belum diterima).
- Lulus: `test-calendar-status.php`, `test-daily-availability.php`, `test-daily-quota.php` (TEMP/rollback, VIEW dan lock), `test-calendar-availability-ui.cjs` Chrome 320/390/1280 termasuk next-month/error, `test-date-picker.cjs`, `test-quota-ui.cjs`, lint, JS syntax, diff check. Tidak uji login aplikasi langsung.
- Reviewer agent kalender gagal berjalan karena limit penggunaan. Reader memetakan konsumen dan mengonfirmasi lock transaksi checkout tetap mengambil produk/kuota sebelum snapshot; belum ada review reviewer final. Tidak commit/push. Working tree juga memiliki file upload JPG baru dan perubahan staged dari pengguna; jangan hapus atau reset.

## Minimum pemesanan 4 porsi
- Menyamakan minimum dasar ke 4 di helper kuota, UI Pelanggan, form/API Admin, schema baru, dan dokumentasi. Minimum khusus produk yang lebih tinggi dari 4 tetap dihormati.
- Migration 026 mengubah nilai 10 menjadi 4 karena migration 025 sebelumnya menaikkan semua nilai di bawah 10; nilai di atas 10 tetap. Migration lama tidak diubah.
- Memperbarui self-check stok, fixture API, dan uji keranjang supaya batas 4 terlihat. Belum menjalankan tes sesuai cakupan permintaan; perlu verifikasi migration/test PostgreSQL pada langkah berikutnya. Tidak commit/push; perubahan staged sebelumnya dan JPG user tetap dipertahankan.

## Lebar katalog mobile dan filter Snack
- Pill kategori pada mobile disamakan lebarnya; kartu produk memakai lebar penuh grid. Intro dan panel tanggal katalog diberi lebar penuh, dan gutter scrollbar stabil mencegah lebar konten berubah ketika kategori berganti.
- Menambahkan filter Snack; handler kategori yang sudah generik menangani filter tanpa perubahan JS.
- Belum menjalankan render/test browser. Perubahan staged sebelumnya dan JPG user tetap dipertahankan.
- Tindak lanjut: `menu-grid` mobile dipaksa `width:100%; min-width:0` agar ukuran min-content item kategori tidak melebarkan flex child. Belum diuji render browser.

## Lebar konten kategori katalog mobile
- `.customer-content` katalog mobile kini selalu selebar `.customer-main`, dapat menyusut tanpa overflow, dan tidak lagi memakai margin auto desktop. Lebar halaman tidak berubah ketika kategori dengan ukuran intrinsik menu berbeda dipilih.
- Fixture `test-catalog-card-layout.cjs` memeriksa lebar pembungkus sebelum dan sesudah nama menu diganti dari pendek ke panjang, selain pemeriksaan kartu, label stok, dan overflow yang sudah ada.
- Chrome headless lulus pada viewport 320, 390, dan 1280px. Percobaan awal di sandbox gagal menjalankan proses GPU Chrome; pengujian ulang di luar sandbox lulus seluruh assertion.

## Pergantian kategori tanpa animasi ulang
- Efek `category-transition` dihapus karena membuat pergantian filter terasa seperti katalog dimuat dua kali. Pemicu JavaScript, parameter `animate`, timer 750ms, aturan CSS, dan keyframe yang terkait ikut dibersihkan; filter dan pemuatan data tetap berjalan langsung.
- Lulus: syntax JavaScript, `test-quota-ui.cjs`, `test-customer-cart-quantity.cjs`, dan fixture Chrome katalog pada viewport 320, 390, dan 1280px.

## Rencana header sticky, akses search, dan animasi UI
- Rencana rinci dibuat di `docs/rencana-ui-pelanggan-scroll-dan-animasi.md`. Keputusan pengguna: header sticky untuk semua halaman Pelanggan di desktop/mobile; tombol search katalog scroll+focus ke input asli; animasi sidebar mobile; animasi pembukaan kalender bersama untuk Pelanggan, Admin, dan Owner.
- Discovery read-only memetakan layout bersama, search katalog, state/fokus sidebar, dialog kalender, konflik cart bar, safe area, reduced motion, dan fixture yang perlu diperluas. Belum ada implementasi kode dari rencana ini.

## Implementasi header sticky dan animasi UI
- Fase 1–4 diterapkan: header bersama sticky desktop/mobile; tombol search katalog muncul setelah input terlewati lalu scroll+focus; sidebar mobile memakai transisi transform/opacity dengan delayed visibility; kalender bersama memakai animasi pembukaan CSS untuk semua role.
- Sidebar mempertahankan state/fokus/keyboard JS yang ada. CSS mempertahankan backdrop dalam render selama fade keluar walau atribut hidden sudah aktif, dengan pointer-events langsung nonaktif. Reduced motion menonaktifkan transisi/animasi.
- Syntax JS/PHP, suite quota-ui dan customer-cart-quantity lulus. Verifikasi browser dikoordinasikan terpisah. API dan skema tidak berubah. Tidak commit/push.

## Aset background pola Pelanggan (22 November 2026)
- H7 sudah direncanakan dan diimplementasikan. CSS kini memakai Pattern_Se.Noesa_Background.png, aset disalin ke public, dan dokumentasi/peta kode diselaraskan. Opasitas lapisan dekorasi diatur ke 40% sesuai permintaan; endpoint dan skema tidak berubah.
- Belum dilakukan render browser untuk menilai pola pada seluruh halaman/ukuran layar.

## Pemeriksaan sumber ikon (23 November 2026)
- Membaca AGENTS.md dan handoff pada docs/00-panduan/. Ikon saat ini campuran: logo PNG lokal public/assets/img/logo-icon.png; navigasi Pelanggan memakai simbol Unicode (lingkaran, menu, amplop); tombol Keluar, navigasi Admin/Owner, dan kalender memakai SVG inline. customer.js juga memakai emoji peringatan libur dan centang ketersediaan.
- Tidak mengubah kode aplikasi. Pemeriksaan sumber saja; tidak menjalankan browser atau tes. Tidak ada percobaan implementasi yang gagal. Berikutnya: tunggu arahan pengguna bila ingin menyeragamkan ikon.

## Komponen ikon FigCraft semua role (24 November 2026)
- Tujuan: membuat pustaka ikon visual untuk layar Pelanggan, Admin, dan Owner. Pengguna menetapkan file FigCraft baru, daftar mengikuti layar yang ada, dan gaya mengikuti identitas Se.Noesa.
- Selesai: `Se.Noesa-Ikon-Semua-Role.figcraft` berisi frame `Component`, tiga kelompok role, dan 30 komponen ikon vektor berukuran 52 px dengan gambar garis 24 px. Palet mengacu pada sage dan coklat kayu di CSS aplikasi. Render FigCraft diperiksa secara visual; file disimpan.
- Berikutnya jika diminta: terapkan komponen ke UI aplikasi dan uji tiap halaman/breakpoint. Tidak ada kode aplikasi, endpoint, atau database yang diubah; tidak ada uji browser. Tidak ada percobaan gagal.

## Revisi pustaka ikon FigCraft siap review (24 November 2026)
- Tujuan: revisi berdasarkan hasil visual, selaraskan karakter logo, perjelas makna, lengkapi simbol Unicode UI, dan ekspor aset tanpa integrasi aplikasi. Setelah jeda atas permintaan pengguna, pekerjaan dilanjutkan dalam sesi yang sama.
- Selesai: dokumen editable `Se.Noesa-Ikon-Semua-Role.figcraft` memiliki halaman `Arsip awal`, `Komponen v2`, `Preview role`, dan `Uji ukuran`. Frame `Component` pada v2 memuat 46 komponen unik (sumber 24 × 24 px, garis 2 px, tanpa latar). Katalog dikelompokkan sebagai Navigasi, Aksi, dan Status. Preview role memakai instance yang sama pada ukuran pakai, dengan state aktif. Matriks memuat semua ikon pada 16/20/24/32 px pada latar terang dan hijau; sisi hijau memakai override garis putih pada instance.
- Keputusan: Menu Pelanggan berbentuk tudung hidangan; Dapur diubah menjadi panci agar berbeda. Terlambat adalah jam dan tanda peringatan; Kuota adalah pengukur kapasitas; Laporan adalah dokumen ringkasan batang; Validasi adalah centang dalam kotak. Tanggal/Jadwal berbagi satu ikon kalender. Sudut dan ujung garis mengikuti lengkung halus logo, tanpa ornamen tambahan pada tiap ikon.
- Aset: 46 SVG transparan per ikon kini di `public/assets/component/`; preview katalog, navigasi 1×, dan matriks di `docs/04-implementasi/preview-ikon/`. Daftar lengkap dan pemetaan ada di `docs/04-implementasi/katalog-ikon.md`. Seluruh SVG berhasil diekspor; fallback PNG 4× tidak diperlukan.
- Verifikasi: 46 file SVG diparse sebagai XML, semuanya memiliki `viewBox 0 0 24 24`, ukuran 24 × 24, `fill=none`, dan tidak memiliki persegi latar. Preview PNG berhasil diekspor dan diperiksa visual. Dokumen FigCraft disimpan setelah revisi. Tidak menjalankan browser aplikasi, API, database, atau uji integrasi karena penerapan ke kode menunggu review pengguna. Tidak ada percobaan ekspor yang gagal.
- Berikutnya: minta review desain/asset; setelah disetujui, integrasikan ke layout dan kontrol UI serta uji aksesibilitas, state, dan breakpoint. Jangan menganggap halaman Arsip awal sebagai sumber akhir.

## Penerapan ikon pada website uji (24 November 2026)
- Pengguna menyetujui desain dan meminta aset di `public/assets/component/`, integrasi website uji, dan laporan. Seluruh 46 SVG dipindah ke sana; stylesheet `icons.css` memakai CSS mask dan `currentColor`. Dokumen FigCraft editable tetap di root. Integrasi presentasi mencakup 34 ikon aktif pada navigasi tiga role, kalender bersama, katalog/keranjang, status/konfirmasi, dan mata password. Dua belas ikon lain menjadi cadangan tanpa mengubah alur bisnis.
- Halaman uji statis `public/assets/component/preview.html` tersedia di server lokal `127.0.0.1:8096`; screenshot desktop/mobile dan login ada di `docs/04-implementasi/preview-ikon/`. Laporan lengkap: `docs/04-implementasi/laporan-uji-ikon-2026-11-24.md`; katalog aset/pemetaan: `docs/04-implementasi/katalog-ikon.md`.
- Lulus: 46/46 aset dan mask di Chrome desktop/mobile, GET aset dan login 200, sesi PHP bersih setelah memakai direktori sementara, `test-icons`, date-picker, cart-quantity, quota-ui, admin-dashboard, admin-back-navigation, PHP/JS lint, dan diff check. Browser suite lama berhenti pada overlap logo landscape/ekspektasi opacity pola; empat suite lain gagal pada fixture/alur di luar patch ikon. Tidak ada pengujian login penuh role dengan data nyata.
- Tidak membaca atau mengubah `.env`, tidak menjalankan migrasi/database, tidak commit/push. Server uji menggunakan session.save_path sementara di folder Temp sistem. Berikutnya: review visual pengguna, lalu uji halaman role dengan sesi uji nyata dan tindak lanjuti kegagalan suite lama secara terpisah.
- Atas koreksi pengguna, laporan, katalog, dan enam preview dipusatkan di `docs/04-implementasi/`; semua tautan terkait diperbarui. Folder `design/` kosong. Dokumen FigCraft editable tetap di root dan SVG runtime tetap di `public/assets/component/`.
