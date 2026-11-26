# Rencana revisi B7: menutup popup melalui latar

## Primary goal

Pelanggan dan Admin dapat menutup popup yang disebut pada revisi B7 dengan menekan area di luar isi popup, tanpa menutupnya saat berinteraksi dengan formulir.

## Phase 0 — Penelusuran

**How this serves the primary goal:** Memastikan perbaikan mengikuti perilaku dialog yang sudah ada dan mencakup seluruh kasus B7.

- Sumber: spreadsheet Kumpulan REVISI, Sheet1 B7; `docs/00-panduan/peta-kode.md`; `public/pelanggan/assets/customer.js` sekitar `catalogPage()` dan `historyPage()`; `public/admin/products.php`, `orders.php`, dan `assets/admin.js`.
- Dialog detail, keranjang dan checkout Pelanggan sudah memiliki handler `click` yang menutup ketika `event.target === dialog`. Dialog riwayat juga membersihkan timer. Ini pola lokal yang tersedia untuk ditinjau ulang pada browser.
- Tambah dan edit produk memakai `#product-dialog`; pesanan offline memakai `#offline-order-dialog`. Keduanya dibuka dengan `showModal()` dan belum memiliki handler backdrop.
- Pastikan lagi struktur dan perilaku saat implementasi. Jangan memodifikasi alur API, kuota, atau data transaksi.

## Phase 1 — Penanganan Admin

**How this serves the primary goal:** Menambahkan penutupan melalui latar untuk ketiga alur Admin yang dilaporkan.

- Pada `public/admin/assets/admin.js`, pakai pola dialog Pelanggan untuk `#product-dialog` dan `#offline-order-dialog`; tutup hanya saat target klik adalah dialog itu sendiri. Terapkan pada inisialisasi halaman masing-masing, tanpa memasang handler berulang saat membuka popup.
- Pertahankan pembersihan bagian kuota yang dilakukan tombol tutup produk; samakan perilaku penutupan backdrop dengan tombol X/Batal. Pastikan pembukaan ulang menginisialisasi form dengan benar.
- Jangan memasang penutupan global yang mengubah dialog konfirmasi atau dialog lain. Pertahankan perilaku Escape, tombol tutup, validasi dan fokus native.
- Periksa klik pada input, tombol, ruang kosong dalam panel, serta awal drag dalam dialog dan pelepasan di luar agar tidak terjadi penutupan yang tidak disengaja.

## Phase 2 — Verifikasi dan dokumentasi

**How this serves the primary goal:** Membuktikan seluruh kasus B7 bekerja dan tidak merusak interaksi formulir.

- Uji di browser desktop dan mobile: tambah produk, edit produk, pesanan offline, detail menu, keranjang dan checkout Pelanggan; klik backdrop menutup, klik isi tetap terbuka, Escape dan X/Batal tetap bekerja. Periksa pembukaan ulang dan scroll.
- Jalankan pemeriksaan sintaks JavaScript dan uji relevan yang tersedia; gunakan fixture browser terisolasi bila login/database tidak tersedia. Catat dengan jujur batas verifikasinya.
- Perbarui peta kode, dokumentasi API dan database jika diperlukan menurut aturan proyek, serta `docs/00-panduan/HANDOFF.md`; tandai spreadsheet selesai hanya setelah seluruh kasus B7 teruji. Tidak membaca `.env` atau mengubah database.

Status: handler backdrop ditambahkan pada dialog produk dan pesanan offline. Pengujian otomatis/sintaks dan pembaruan dokumentasi dicatat di handoff; pemeriksaan browser login langsung belum dilakukan.
