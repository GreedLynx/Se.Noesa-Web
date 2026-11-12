# API

## Kuota default dan override tanggal

Endpoint per role tetap di `public/<role>/api/`. Tanggal memakai YYYY-MM-DD dalam
Asia/Jakarta. Kalender bersama mengisi input dan memicu input/change; parameter
filter `tgl_kirim` tidak ditambahkan pada POST.

### Produk Admin

GET `admin/api/products.php` menerima `tgl_kirim` (default hari ini), kategori, q.
Respons `{items,count,tanggal}`. Setiap item menyertakan `kuota_default` (nilai produk),
`kuota` dan alias `kuota_harian` (kapasitas efektif tanggal filter), `terpakai`
(detail pesanan non-ditolak/non-dibatalkan), serta `sisa` minimal 0.

POST produk menyimpan metadata/gambar/min_pesan dan field `kuota_default`,
integer 0–2147483647 (default 50 bila tidak dikirim). Alias request `kuota_harian` diterima bila canonical tidak
dikirim. Nilai disimpan pada `produk.kuota_harian`; override tidak diubah.
Harga >=1, minimum >=4. Gambar baru wajib untuk produk baru, edit boleh mempertahankan
gambar. Respons sukses `{ok,id,gambar}`; input invalid HTTP 422. Role wajib Admin.

### Pesanan offline Admin

POST `admin/api/orders.php` menerima `action=create_offline`, identitas
pemesan/penerima, `telepon`, `alamat`, `tgl_kirim`, `waktu_kirim`, catatan
opsional, dan `items:[{id,qty}]`. Pesanan dibuat dengan sumber `offline` dan
status `diproses`; kuota produk serta kapasitas dapur pada tanggal kirim
diperiksa dan dikunci secara atomik seperti checkout online. Respons sukses
`{ok,id,kode,total,status}` HTTP 201; kuota tidak cukup HTTP 422. Hanya Admin.

### Override Admin

POST `admin/api/kuota-harian.php` menerima:

- `action=save` (default), produk_id, tanggal_mulai, tanggal_selesai, kuota:
  UPSERT seluruh tanggal inklusif secara atomik. Kuota integer 0–2147483647.
- `action=reset`, produk_id, tanggal_mulai, tanggal_selesai: hapus override
  rentang sehingga kembali memakai default.
- Kompatibilitas: `tanggal` tunggal menggantikan tanggal_mulai; tanggal_selesai
  yang tidak dikirim sama dengan tanggal_mulai.
- `action=delete,id`: hapus satu override lama berdasarkan ID.

Save/reset mengembalikan `{ok,jumlah_tanggal}`, jumlah baris tersimpan/terhapus.
Tanggal harus nyata, urut, maksimal 366 tanggal inklusif. Input invalid/aksi tidak
dikenal HTTP 422; produk tidak ditemukan HTTP 404. Kuota 0 menutup produk pada
tanggal itu. Save terbaru menimpa tanggal overlap. Reset tidak memulihkan histori.

GET dengan produk_id mengembalikan `{items}` override per tanggal urut terbaru;
tambahan tanggal mengembalikan satu baris atau null. Seluruh aksi wajib Admin.

### Katalog dan checkout pelanggan

GET `pelanggan/api/catalog.php` menerima kategori,q,tgl_kirim (default H+7).
Respons `{items,schedule,tanggal,maxKitchen,kitchenUsed,kitchenRemaining}`.
Field kuota item sama dengan produk Admin. Kuota efektif 0 dikecualikan; produk
berkuota positif yang habis tetap ditampilkan dengan sisa 0. Tanggal invalid HTTP 422.

POST `pelanggan/api/orders.php` mengambil identitas dari session pelanggan, tgl_kirim,
dan `items:[{id,qty}]`. Produk berulang digabung; maksimal 500 porsi per produk dan
minimal max(4,min_pesan). Harga dari database disimpan sebagai snapshot; alamat
harus milik pelanggan. Kuota dan batas dapur 80% diperiksa dalam transaksi terkunci.
Sukses `{ok,id,kode,total}` HTTP 201, validasi `{error}` HTTP 422.
Penurunan kuota mempertahankan pesanan lama dan membatasi checkout baru.
Pesanan berstatus `menunggu_pembayaran` dibatalkan otomatis satu jam sejak
`created_at` bila bukti belum dikirim. Jalankan `php scripts/expire-unpaid-orders.php`
melalui scheduler setiap menit; API katalog
dan pesanan pelanggan juga menjalankan pembersihan untuk menjaga status tetap
mutakhir saat ada aktivitas. Status `dibatalkan` tidak memakai kuota.

### Laporan Admin dan Owner

GET produk/pesanan/dashboard Admin dan produk/pesanan Owner menerima tgl_kirim
(default hari ini) untuk ringkasan, tanpa mengubah filter daftar transaksi.
Kategori kritis memakai kuota efektif. Produk Owner mengembalikan juga
maxKitchen,kitchenUsed,kitchenRemaining. Owner tetap GET-only/read-only.
Laporan memakai VIEW; checkout memakai prepared query langsung dengan hasil setara.

Penolakan Admin action=reject tetap mengubah menunggu_validasi ke ditolak; sisa
pulih melalui agregasi tanpa counter. Kalender, status HTTP lama, pembayaran,
dispatch, dan alias respons laporan Owner tetap.

### Foto kiriman

Admin mengunggah foto saat pesanan berstatus `diproses` melalui POST
`admin/api/orders.php` dengan `action=upload_dispatch`, `id`, dan satu atau
beberapa file `foto_kiriman[]`. Format JPG, PNG, atau WebP didukung, maksimal
2 MB per foto. GET detail Admin dan pelanggan mengembalikan
`foto_kiriman_list`; foto lama di `pesanan.foto_kiriman` tetap disertakan.
Foto tetap tersedia di Admin dan detail riwayat pelanggan setelah status
berubah menjadi `selesai`. Migrasi database yang sudah ada: `016_multiple_dispatch_photos.sql`.
