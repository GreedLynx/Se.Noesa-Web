# API

Detail pesanan Admin tetap menampilkan `foto_kiriman` setelah status `selesai`.
Form upload hanya tampil saat `diproses`; endpoint dan payload tidak berubah.

## Kuota default dan override tanggal

Endpoint per role tetap di `public/<role>/api/`. Tanggal memakai YYYY-MM-DD dalam
Asia/Jakarta. Kalender bersama mengisi input dan memicu input/change; parameter
filter `tgl_kirim` tidak ditambahkan pada POST.

### Produk Admin

GET `admin/api/products.php` menerima `tgl_kirim` (default hari ini), kategori, q.
Respons `{items,count,tanggal}`. Setiap item menyertakan `kuota_default` (nilai produk),
`kuota` dan alias `kuota_harian` (kapasitas efektif tanggal filter), `terpakai`
(detail pesanan selain ditolak/dibatalkan), serta `sisa` minimal 0.

POST produk menyimpan metadata/gambar/min_pesan dan field `kuota_default`,
integer 0–2147483647 (default 50 bila tidak dikirim). Alias request `kuota_harian` diterima bila canonical tidak
dikirim. Nilai disimpan pada `produk.kuota_harian`; override tidak diubah.
Harga >=1, minimum >=4. Gambar baru wajib untuk produk baru, edit boleh mempertahankan
gambar. Respons sukses `{ok,id,gambar}`; input invalid HTTP 422. Role wajib Admin.

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

### Tanggal libur operasional Admin

Halaman Jadwal mengelola tanggal libur; tanggal operasional lain selalu dianggap
buka. GET `admin/api/holidays.php` mengembalikan
`{items:[{id,tanggal_mulai,tanggal_selesai}]}`. POST menerima `action=save`
(default) dengan `tanggal_mulai` dan `tanggal_selesai` YYYY-MM-DD, atau
`action=delete` dengan `id`. Rentang inklusif dan harus berurutan. Role wajib
Admin. `action=save` dengan `id` integer positif memperbarui rentang libur yang
ada; tanpa `id` menambahkan rentang baru. ID invalid menghasilkan 422 dan ID
yang sudah tidak ditemukan menghasilkan 404. Rentang tidak mengubah pesanan
lama atau kuota produk/override tersimpan.

### Katalog dan checkout pelanggan

GET `pelanggan/api/catalog.php` menerima kategori,q,tgl_kirim (default H+7).
Respons `{items,schedule,tanggal,tanggal_libur,maxKitchen,kitchenUsed,kitchenRemaining}`.
Field kuota item sama dengan produk Admin. Kuota efektif 0 dikecualikan; produk
berkuota positif yang habis tetap ditampilkan dengan sisa 0. Tanggal invalid HTTP 422.
Saat `tanggal_libur` true, semua kuota dan kapasitas efektif 0; UI memberi
peringatan tanggal libur.

POST `pelanggan/api/orders.php` mengambil identitas dari session pelanggan, tgl_kirim,
dan `items:[{id,qty}]`. Produk berulang digabung; maksimal 500 porsi per produk dan
minimal max(4,min_pesan). Harga dari database disimpan sebagai snapshot; alamat
harus milik pelanggan. Kuota dan batas dapur 80% diperiksa dalam transaksi terkunci.
Sukses `{ok,id,kode,total}` HTTP 201, validasi `{error}` HTTP 422.
Penurunan kuota atau tanggal libur mempertahankan pesanan lama dan membatasi
checkout baru; checkout tanggal libur ditolak dengan HTTP 422 meskipun request
melewati UI. `jadwal_po` tetap menjadi referensi FK pesanan, tetapi tidak lagi
dikelola dari halaman Jadwal.
Pesanan berstatus `menunggu_pembayaran` dibatalkan otomatis satu jam sejak
`created_at` bila bukti belum dikirim. Jalankan `php scripts/expire-unpaid-orders.php`
melalui scheduler setiap menit; API katalog
dan pesanan pelanggan juga menjalankan pembersihan untuk menjaga status tetap
mutakhir saat ada aktivitas. Status `dibatalkan` tidak memakai kuota.

### Laporan Admin dan Owner

GET produk/pesanan/dashboard Admin dan produk/pesanan Owner menerima tgl_kirim
(default hari ini) untuk ringkasan, tanpa mengubah filter daftar transaksi.
Kategori kritis memakai kuota efektif. Produk Owner mengembalikan juga
maxKitchen,kitchenUsed,kitchenRemaining dan `tanggal_libur`. Owner tetap GET-only/read-only.
Laporan memakai VIEW; checkout memakai prepared query langsung dengan hasil setara.

GET `admin/api/dashboard.php` menambahkan `production_date`, `tanggal_libur`, dan `production`.
Data dikelompokkan per menu untuk tanggal kirim yang diminta; `total_porsi`
menjumlahkan detail pesanan selain `ditolak`/`dibatalkan`, dan setiap menu
menyertakan daftar `pesanan` berisi id, kode, status, dan jumlah porsinya.
Data bersumber dari VIEW `v_rekap_produksi_harian`. Dashboard menautkan setiap
pesanan ke detail Admin yang sudah memuat checklist dan tindakan status. Filter
tanggal memakai parameter `tgl_kirim` yang sama.

Frontend membedakan `production: []` (tidak ada pesanan pada tanggal pilihan)
dari field rekap yang hilang/tidak valid (gagal dimuat). Rekap yang gagal
ditampilkan sebagai pesan tetap tanpa menghentikan statistik Dashboard lain.
Payload `error` dari server tetap dianggap gagal meskipun HTTP berstatus 200.

Penolakan Admin action=reject tetap mengubah menunggu_validasi ke ditolak; sisa
pulih melalui agregasi tanpa counter. Kalender, status HTTP lama, pembayaran,
dispatch, dan alias respons laporan Owner tetap, kecuali tambahan pembatalan berikut.

### Pembatalan dan bukti pembayaran pelanggan

POST `pelanggan/api/orders.php` dengan `{action:"cancel_order",id}` membatalkan
pesanan milik pelanggan sendiri. ID harus integer positif. Status asal yang sah:
`menunggu_pembayaran`, `menunggu_validasi`, `diproses`. Tanggal kirim harus minimal
dua tanggal kalender dari hari ini dalam Asia/Jakarta (sampai akhir hari H-2).
Sukses `{ok:true,status:"dibatalkan"}`. Payload invalid 422, role login selain
pelanggan 403, belum login 401, tidak ada/bukan milik sendiri 404. Konflik
status/tenggat 409 (sebelumnya cancel mengembalikan 422). Identitas dari session.
Refund penuh ditangani Admin di luar aplikasi; endpoint tidak memindahkan uang.

Multipart `action=upload_proof` mempertahankan field `id`, `nama_pembayar`, `proof`
dan format file yang berlaku. Unpaid hanya menerima upload sebelum
`created_at + 1 jam`, diperiksa memakai waktu database setelah row lock.
Status `menunggu_validasi` boleh mengganti bukti tanpa tenggat satu jam lagi.
Konflik akibat kedaluwarsa/perubahan status menghasilkan 409 dan file baru dibuang.
Bukti lama tetap disimpan. Respons sukses lama tetap `{ok,bukti_bayar}`.

GET detail `pelanggan/api/orders.php?id=...` menambah:

- `can_cancel`, `can_upload_proof`: boolean izin saat respons dibuat; server tetap
  memeriksa ulang pada POST.
- `payment_expires_at`: ISO 8601 ber-offset +07:00, satu jam sejak dibuat, atau NULL.
- `cancel_before`: ISO 8601 +07:00, awal H-1 sebagai batas eksklusif, atau NULL.

Frontend memakai field tersebut untuk tombol/form, menampilkan tenggat pembayaran
dan ketentuan refund sebelum konfirmasi. Tidak ada countdown/pemicu job JavaScript.
Tampilan pembatalan mempertahankan tombol merah penuh, kartu syarat, dan pesan
batas H-2 terlewati yang sudah ada; izin tombol tetap mengikuti `can_cancel`.

GET pesanan Admin menambah `tab=dibatalkan`; `tab=semua` mencakup status batal dan
`counts.cancelled` menghitungnya. Owner menambah `tab=semua|dibatalkan` dan
`count.cancelled`, tetap GET-only. Pendapatan tetap berasal dari selesai.
Upload foto kiriman Admin kini menghasilkan 409 dan membuang foto baru bila
pembatalan sudah menang; tidak menimpa foto pesanan batal.

### Foto kiriman

Admin mengunggah foto saat pesanan berstatus `diproses` melalui POST
`admin/api/orders.php` dengan `action=upload_dispatch`, `id`, dan satu atau
beberapa file `foto_kiriman[]`. Format JPG, PNG, atau WebP didukung, maksimal
2 MB per foto. GET detail Admin dan pelanggan mengembalikan
`foto_kiriman_list`; foto lama di `pesanan.foto_kiriman` tetap disertakan.
Foto tetap tersedia di Admin dan detail riwayat pelanggan setelah status
berubah menjadi `selesai`. Migrasi database yang sudah ada: `016_multiple_dispatch_photos.sql`.
Upload galeri mengunci status `diproses` sebelum INSERT/UPDATE. Jika pembatalan
menang, transaksi dibatalkan, semua file baru dibuang, dan respons 409.
Respons upload menyertakan `foto_kiriman` (file pertama) serta `foto_kiriman_list`.
Katalog/riwayat GET tidak memicu job kedaluwarsa; scheduler CLI memakai `--apply`.
