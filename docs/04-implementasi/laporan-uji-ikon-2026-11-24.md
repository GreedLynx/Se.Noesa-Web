# Laporan penerapan ikon pada website uji

Tanggal: 24 November 2026. Cakupan: aset dan presentasi UI; tidak ada perubahan endpoint, transaksi, atau database.

## Hasil

- 46 SVG transparan dipindahkan ke `public/assets/component/`. `icons.css` memakai satu geometri per fungsi dan mewarnainya dengan `currentColor`.
- 34 ikon dipakai pada halaman aplikasi: navigasi Pelanggan/Admin/Owner, kalender bersama, pencarian/keranjang, jumlah porsi, status, konfirmasi, dan mata password. Dua belas aset lain disimpan sebagai cadangan; daftarnya ada di [katalog aset](katalog-ikon.md).
- Halaman uji lokal: `http://127.0.0.1:8096/assets/component/preview.html`. [Screenshot desktop](preview-ikon/website-uji.png), [mobile](preview-ikon/website-uji-mobile.png), dan [login aplikasi](preview-ikon/login-website-uji.png).

## Verifikasi yang lulus

- Browser Chrome headless pada preview desktop dan mobile: **46/46 SVG termuat**, CSS mask aktif, dan state warna tampil sesuai contoh.
- Server PHP lokal: `icons.css`, `menu-makanan.svg`, `kapasitas.svg`, halaman preview, dan `login.php` merespons 200. Setelah `session.save_path` diarahkan ke direktori sementara yang bisa ditulis, halaman login tidak mengeluarkan peringatan sesi; ikon mata terlihat pada screenshot.
- `node scripts/test-icons.cjs`: 46 aset cocok dengan CSS; tombol mata mengubah kelas ikon dan label aksesibel.
- Suite yang lulus: `test-date-picker`, `test-customer-cart-quantity`, `test-quota-ui`, `test-admin-dashboard`, dan `test-admin-back-navigation`.
- Pemeriksaan sintaks PHP/JavaScript pada file yang diubah dan `git diff --check` lulus.

## Batas pengujian

- Login penuh ke halaman Pelanggan/Admin/Owner dengan data nyata tidak dilakukan. Preview role dan pemeriksaan sumber menilai tampilan ikon, bukan seluruh alur aplikasi.
- Suite browser revisi pelanggan berhenti pada pemeriksaan lama: overlap logo di landscape dan ekspektasi opacity pola yang tidak sesuai CSS saat ini. Empat suite lain (`test-customer-payment-errors`, `test-order-cancellation-ui`, `test-admin-cancellation-list`, `test-holidays`) gagal di fixture/alur yang tidak diubah oleh patch ikon. Kegagalan tersebut belum diselesaikan dalam tugas ini; jangan anggap seluruh suite proyek hijau.
- `preview.html` adalah halaman uji statis yang tidak membutuhkan login. URL lokal tersedia selama server PHP uji berjalan; file dapat dibuka lagi melalui server lokal bila sesi server berhenti.
