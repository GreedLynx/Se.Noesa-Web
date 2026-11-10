# API

Revisi tampilan pemilih tanggal tidak mengubah endpoint atau payload. Kalender
bersama mengisi `catalog-delivery-date`/`quota-date` dan memicu event yang sudah
dipakai katalog serta filter laporan. `tgl_kirim` tetap berformat `YYYY-MM-DD`;
validasi server, H+7 pelanggan, dan hak akses Owner tetap berlaku.
Perbaikan lebar grid pemilih tanggal Owner hanya mengubah CSS; kontrak API tetap.

Perapian SQL mempertahankan nama parameter, status HTTP, serta alias field respons
yang dipakai klien, termasuk alias ringkas laporan Owner. Nama variabel PHP internal
tidak menjadi bagian kontrak API.

Penertiban nomor migrasi reset password menjadi 012–013 tidak mengubah endpoint,
payload, atau respons API.

Constraint numerik migrasi 014 tidak mengubah kontrak API. Validasi aplikasi tetap
memberikan HTTP 422 sebelum nilai tidak valid mencapai constraint database.

## Plan 1 — kontrak aktif

Katalog dan checkout memakai kuota dari tabel `kuota_harian` pada `tgl_kirim`;
tidak ada fallback master. Field item `kuota`/`kuota_harian` adalah alias kapasitas
tanggal itu, `terpakai` dari detail pesanan, `sisa` minimal 0.

- POST produk Admin hanya menyimpan metadata/gambar/minimal pesan, tidak menyimpan kuota.
- POST `admin/api/kuota-harian.php`: `produk_id,tanggal,kuota` untuk upsert kapasitas total;
  `action=delete,id` untuk hapus konfigurasi. Nilai 0 menonaktifkan tanggal itu.
- GET API produk, pesanan, dan dashboard Admin menerima `tgl_kirim` (default hari ini)
  untuk kuota/kapasitas. Daftar transaksi tetap mengikuti tab/pencarian.
- GET produk/pesanan Owner menerima `tgl_kirim`, tetap read-only. Produk Owner juga
  mengembalikan `maxKitchen,kitchenUsed,kitchenRemaining`.
- GET katalog default H+7. Produk tanpa konfigurasi positif dikecualikan.
- Laporan Admin/Owner memakai VIEW; checkout memakai prepared query langsung dalam transaksi.

Bagian berikut adalah kontrak sebelum Plan 1; aturan di atas menggantikan perilaku
kuota master dan pengaturan tanggal yang sebelumnya disembunyikan.

## Revisi kuota stateless

Kontrak checkout dipertahankan dengan perubahan aturan ketersediaan.

- Checkout yang digunakan aplikasi adalah POST `public/pelanggan/api/orders.php`.
- Identitas pelanggan berasal dari session melalui `require_role('pelanggan')`.
- Item menggunakan `items: [{id, qty}]` dan harga diambil dari database.
- Respons berhasil tetap `{ok, id, kode, total}` dengan HTTP 201.
- Kesalahan validasi menggunakan `{error}` dengan HTTP 422.
- Katalog berada di GET `public/pelanggan/api/catalog.php`.

Jangan menyalin contoh `checkout.php` dari dokumen revisi secara langsung:
nama kolomnya berbeda dan contoh tersebut menerima identitas pelanggan dari payload.
Implementasi mempertahankan pemeriksaan session, kepemilikan
alamat, snapshot harga, serta validasi tanggal kirim minimal 7 hari.

### Katalog pelanggan

GET `public/pelanggan/api/catalog.php` menerima `kategori`, `q`, dan `tgl_kirim`
(`YYYY-MM-DD`, default hari ini + 7 dalam Asia/Jakarta).
Respons: `{items, schedule, tanggal, maxKitchen, kitchenUsed, kitchenRemaining}`.
Item memakai `kuota_harian` sebagai master; `kuota` adalah alias untuk kompatibilitas
frontend, `terpakai` dihitung dari detail pesanan, dan `sisa` dibatasi minimal 0.
Produk master 0 tidak ditampilkan; produk aktif yang habis tetap terlihat dengan sisa 0.
Tanggal tidak valid menghasilkan HTTP 422.

### Checkout pelanggan

POST `public/pelanggan/api/orders.php` memakai `tgl_kirim` untuk menentukan kuota.
Baris produk berulang digabung sebelum validasi; maksimal 500 porsi per produk.
Minimal pemesanan adalah nilai terbesar antara 4 dan `produk.min_pesan`.
Sisa produk dan batas dapur 80% diperiksa dalam transaksi yang dikunci.
Kuota master 0 tidak dianggap tanpa batas. Kesalahan menyebut sisa porsi atau batas 80%.
Harga tetap berasal dari database dan disimpan sebagai snapshot pada detail.

### Produk admin

POST `public/admin/api/products.php` menerima `kuota_harian`; field lama `kuota`
tetap diterima sebagai alias. Nilai yang disimpan adalah master `produk.kuota_harian`.
Minimal pesan harus >= 4. GET menerima `tgl_kirim` (default hari ini), `kategori`, dan
`q`; jumlah terpakai/sisa dan kategori kritis dihitung untuk tanggal tersebut.

### Penolakan dan ringkasan admin

POST `public/admin/api/orders.php` dengan `action=reject` tetap mengubah
`menunggu_validasi` menjadi `ditolak`. Kuota langsung tersedia pada agregasi berikutnya,
tanpa mengurangi counter produk. GET daftar menerima `tgl_kirim` untuk ringkasan
kapasitas (default hari ini); filter daftar pesanan tetap mengikuti `tab` dan `q`.
Dashboard admin memakai kuota dan pemakaian tanggal kirim hari ini.

Endpoint kuota per tanggal lama dipertahankan untuk kompatibilitas data, tetapi
pengaturannya tidak menentukan checkout; bagian pengaturan lama disembunyikan
dari editor produk admin. GET `public/owner/api/products.php` juga memakai snapshot
kuota tanggal kirim (`tgl_kirim`, default hari ini) dan tetap read-only.
