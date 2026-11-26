# Ikon se.noesa

Dokumen editable: [se.noesa-Ikon-Semua-Role.figcraft](../../se.noesa-Ikon-Semua-Role.figcraft). Halaman **Komponen v2** memuat 46 komponen sumber yang unik dalam frame `Component`; halaman **Arsip awal** menyimpan versi pembanding. Halaman **Preview role** dan **Uji ukuran** memakai instance komponen sumber, termasuk override putih pada latar hijau.

## Preview

- [Katalog v2](preview-ikon/katalog-v2.png)
- [Navigasi Pelanggan, Admin, Owner pada ukuran pakai 1×](preview-ikon/navigasi-role-1x.png)
- [Semua ikon pada 16, 20, 24, dan 32 px di latar terang/hijau](preview-ikon/uji-ukuran-kontras.png)
- [Preview website uji desktop](preview-ikon/website-uji.png)
- [Preview website uji mobile](preview-ikon/website-uji-mobile.png)

Logo acuan: `foto/Icon_Logo.png` dan `public/assets/img/logo-full.png`. Garis 2 px, ujung dan sambungan membulat mengambil karakter lengkung logo tanpa menambahkan motif yang mengganggu pada ukuran kecil. Setiap sumber memakai area 24 × 24 px dan latar transparan. Warna dasar SVG adalah `#3B513E`; contoh latar hijau `#516B55` memakai override putih pada instance.

## Pemetaan aset

Tabel ini memetakan fungsi aset dan lokasi penggunaannya. Aplikasi memakai 34 ikon saat ini; 12 ikon lain tersedia sebagai cadangan untuk fungsi yang belum menampilkan simbol. Katalog sumber tetap memuat semua 46 ikon.

| Kelompok | SVG | Fungsi dan lokasi target |
| --- | --- | --- |
| Navigasi | [menu-makanan](../../public/assets/component/menu-makanan.svg) | Menu Pelanggan: `public/pelanggan/partials/layout.php` |
| Navigasi | [riwayat](../../public/assets/component/riwayat.svg) | Riwayat Pelanggan: layout dan `public/pelanggan/riwayat.php` |
| Navigasi | [notifikasi](../../public/assets/component/notifikasi.svg) | Navigasi ketiga role: masing-masing `partials/layout.php` |
| Navigasi | [dashboard](../../public/assets/component/dashboard.svg) | Navigasi Admin dan Owner: masing-masing `partials/layout.php` |
| Navigasi | [pesanan](../../public/assets/component/pesanan.svg) | Navigasi Admin dan Owner; detail pesanan di `orders.php` |
| Navigasi | [terlambat](../../public/assets/component/terlambat.svg) | Pembayaran Terlambat Admin: `public/admin/partials/layout.php`, `late-payments.php` |
| Navigasi | [produk](../../public/assets/component/produk.svg) | Produk dan Kuota Admin, Produk Owner: layout masing-masing |
| Navigasi | [kalender](../../public/assets/component/kalender.svg) | Tanggal kirim, filter tanggal, dan Jadwal Admin: `public/assets/js/date-picker.js`, layout Admin |
| Navigasi | [cari](../../public/assets/component/cari.svg) | Pencarian Pelanggan, Admin, Owner: katalog/daftar produk/pesanan |
| Navigasi | [keranjang](../../public/assets/component/keranjang.svg) | Katalog dan keranjang: `public/pelanggan/index.php`, `assets/customer.js` |
| Navigasi | [profil](../../public/assets/component/profil.svg) | Akun Pelanggan: `public/pelanggan/partials/layout.php` |
| Navigasi | [pembayaran](../../public/assets/component/pembayaran.svg) | Pembayaran Pelanggan/Owner: `customer.js`, `public/owner/payments.php` |
| Navigasi | [laporan](../../public/assets/component/laporan.svg) | Ringkasan data Owner: `public/owner/dashboard.php` |
| Navigasi | [alamat](../../public/assets/component/alamat.svg) | Alamat checkout Pelanggan: `public/pelanggan/assets/customer.js` |
| Navigasi | [keluar](../../public/assets/component/keluar.svg) | Tombol keluar ketiga role: masing-masing `partials/layout.php` |
| Navigasi | [kapasitas](../../public/assets/component/kapasitas.svg) | Kuota dan kapasitas dapur: `public/admin/dashboard.php`, `orders.php`, `public/owner/orders.php` |
| Navigasi | [dapur](../../public/assets/component/dapur.svg) | Rekap produksi dan kapasitas dapur Admin: `public/admin/dashboard.php` |
| Navigasi | [detail](../../public/assets/component/detail.svg) | Detail pesanan Admin/Owner dan detail menu Pelanggan |
| Navigasi | [menu-navigasi](../../public/assets/component/menu-navigasi.svg) | Tombol buka sidebar Pelanggan dan navigasi mobile Admin |
| Navigasi | [musik-sosial](../../public/assets/component/musik-sosial.svg) | Simbol tautan sosial/TikTok pada detail produk Pelanggan |
| Aksi | [panah-kanan](../../public/assets/component/panah-kanan.svg) | Tautan lanjut/lihat antrean dan langkah pembayaran terlambat Admin |
| Aksi | [panah-kiri](../../public/assets/component/panah-kiri.svg) | Kembali dari detail pesanan Admin/Owner |
| Aksi | [chevron-kanan](../../public/assets/component/chevron-kanan.svg) | Navigasi kalender dan daftar: `public/assets/js/date-picker.js` |
| Aksi | [chevron-kiri](../../public/assets/component/chevron-kiri.svg) | Navigasi kalender dan daftar: `public/assets/js/date-picker.js` |
| Aksi | [chevron-atas](../../public/assets/component/chevron-atas.svg) | Panel yang dapat dibuka/tutup jika digunakan |
| Aksi | [chevron-bawah](../../public/assets/component/chevron-bawah.svg) | Panel yang dapat dibuka/tutup jika digunakan |
| Aksi | [tutup](../../public/assets/component/tutup.svg) | Dialog dan sidebar Pelanggan/Admin/Owner |
| Aksi | [tambah](../../public/assets/component/tambah.svg) | Tambah jumlah porsi Pelanggan dan tambah data Admin |
| Aksi | [kurang](../../public/assets/component/kurang.svg) | Kurangi jumlah porsi Pelanggan: `customer.js` |
| Aksi | [hapus](../../public/assets/component/hapus.svg) | Hapus item keranjang Pelanggan dan aksi hapus Admin |
| Aksi | [mata](../../public/assets/component/mata.svg) | Password terlihat: `public/login.php`, `register.php`, `forgot-password.php` |
| Aksi | [mata-tutup](../../public/assets/component/mata-tutup.svg) | Password tersembunyi: halaman autentikasi yang sama |
| Aksi | [centang](../../public/assets/component/centang.svg) | Pemeriksaan umum dan konfirmasi langkah Admin |
| Aksi | [validasi](../../public/assets/component/validasi.svg) | Perlu verifikasi pesanan: `public/admin/dashboard.php` |
| Aksi | [edit](../../public/assets/component/edit.svg) | Edit produk/jadwal Admin |
| Aksi | [unggah](../../public/assets/component/unggah.svg) | Bukti pembayaran Pelanggan dan foto kiriman Admin |
| Aksi | [segarkan](../../public/assets/component/segarkan.svg) | Muat ulang Pembayaran Terlambat Admin |
| Aksi | [filter](../../public/assets/component/filter.svg) | Filter daftar pesanan dan produk |
| Status | [informasi](../../public/assets/component/informasi.svg) | Info keranjang kosong dan penjelasan checkout Pelanggan |
| Status | [peringatan](../../public/assets/component/peringatan.svg) | Tanggal libur Pelanggan dan peringatan Admin |
| Status | [gagal](../../public/assets/component/gagal.svg) | Produk tidak tersedia, gagal bayar/unggah, error API |
| Status | [berhasil](../../public/assets/component/berhasil.svg) | Produk tersedia, aksi berhasil, dan status positif |
| Status | [menunggu](../../public/assets/component/menunggu.svg) | Pesanan atau pembayaran menunggu tindakan |
| Status | [kritis](../../public/assets/component/kritis.svg) | Produk kritis pada Dashboard Admin |
| Status | [kosong](../../public/assets/component/kosong.svg) | Fallback riwayat/daftar tanpa data Pelanggan |
| Status | [lunas](../../public/assets/component/lunas.svg) | Status lunas pada Dashboard Owner |

Simbol Unicode yang dicakup meliputi `◯`, `☰`, `✉`, `≡`, `🛒`, `☕`, `✓`, `⚠`, `ℹ`, `❌`, `✅`, `□`, `×`, `+`, `−`, `‹`, `›`, `→`, `↻`, `👁`, `⌲`, `🔍`, dan `♪`. Simbol tindakan yang sama dipetakan ke satu komponen sumber meski muncul di beberapa role.

## Pemeriksaan

- 46 SVG berhasil diekspor dari komponen sumber ke `public/assets/component/`; seluruhnya XML valid dengan `viewBox="0 0 24 24"`, ukuran 24 × 24, dan tanpa persegi latar.
- Tiga preview PNG diekspor dan diperiksa. Matriks menampilkan setiap ikon pada 16, 20, 24, dan 32 px di latar putih dan hijau.
- Preview navigasi menampilkan instance pada 24 px untuk Pelanggan dan 20 px untuk Admin/Owner, termasuk state aktif.
- Penerapan aplikasi dan uji website lokal dicatat di bawah; pengujian login penuh setiap role dengan data nyata belum dilakukan.

## Penerapan website uji

- SVG berada di `public/assets/component/`; `icons.css` merender geometri yang sama memakai CSS mask dan `currentColor`, sehingga warna mengikuti state komponen.
- Navigasi tiga role, kalender bersama, pencarian/keranjang, kontrol jumlah, konfirmasi, status, serta mata password telah memakai aset. Halaman [preview.html](../../public/assets/component/preview.html) memeriksa semua 46 aset dalam browser.
- Cadangan yang belum dipasang pada alur aplikasi: `alamat`, `centang`, `chevron-atas`, `dapur`, `detail`, `edit`, `filter`, `laporan`, `menunggu`, `pembayaran`, `profil`, dan `unggah`. `mata-tutup` dipakai saat password ditampilkan.
- Hasil uji dan batasnya ada di [laporan uji](laporan-uji-ikon-2026-11-24.md).
