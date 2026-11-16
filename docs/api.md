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

### Pesanan offline Admin

POST `admin/api/orders.php` menerima `action=create_offline`, identitas
pemesan/penerima, `telepon`, `alamat`, `tgl_kirim`, `waktu_kirim`, catatan
opsional, dan `items:[{id,qty}]`. Pesanan dibuat dengan sumber `offline` dan
status `diproses`; kuota produk serta kapasitas dapur pada tanggal kirim
diperiksa dan dikunci secara atomik seperti checkout online. Respons sukses
`{ok,id,kode,total,status}` HTTP 201; kuota tidak cukup HTTP 422. Hanya Admin.

POST `admin/api/orders.php` dengan `action=reject` wajib menyertakan
`alasan_penolakan` (1–500 karakter). Nilai disimpan bersama status ditolak dan
dikirim ke detail/riwayat pesanan milik pelanggan agar alasan penolakan jelas.

### Notifikasi perubahan menu Admin/Owner

Endpoint `admin/api/menu-notifications.php` dipakai Admin untuk membuat notifikasi
dan Owner untuk membaca. GET tersedia untuk Admin/Owner; POST hanya Admin
(Owner mendapat HTTP 403). Aksi `create` tidak mengubah pesanan. Aksi
`confirm` memperbarui detail menu dan total pesanan hanya setelah pelanggan
memilih pengganti dan Admin menyetujuinya; status pesanan/pembayaran dan
konfigurasi kuota tidak diubah.

Target dihitung ulang oleh server dari `detail_pesanan`. Pesanan aktif adalah
`menunggu_pembayaran`, `menunggu_validasi`, dan `diproses`; status terminal tidak
pernah menjadi target baru. Menu dianggap tidak tersedia bila kuota efektif
`COALESCE(kuota_harian.kuota, produk.kuota_harian)` pada tanggal kirim
Asia/Jakarta bernilai 0. Override positif tetap membuat menu tersedia meskipun
default produk 0. Pesanan offline ikut ditautkan untuk tindak lanjut manual,
tetapi ditandai `notifikasi_in_app=false` karena tidak memiliki `user_id`.

- GET `?action=list&limit=20&offset=0` mengembalikan riwayat
  `{items,total,limit,offset}`. Setiap item memuat snapshot produk, catatan,
  pembuat, jumlah target/pesanan, online/offline, serta jumlah yang sudah dan
  belum merespons. `limit` dibatasi 1–100.
- GET `?action=preview&produk_id=...` mengembalikan produk, daftar target aktif
  yang terdampak, dan ringkasan per tanggal. Setiap target memuat detail pesanan,
  qty/harga snapshot, status pesanan, data pelanggan, kanal online/offline, serta
  `sudah_dinotifikasi`.
- GET `?action=detail&id=...` mengembalikan header notifikasi, opsi, seluruh
  penerima, snapshot menu lama, menu pengganti bila ada, `selisih_total`
  bertanda, status respons, dan ringkasan respons.
- POST `{action:"create",produk_id,catatan_admin,opsi?}` membuat notifikasi
  dalam satu transaksi. `catatan_admin` wajib 1–2000 karakter. `opsi` berisi
  `menu_pengganti`, `hubungi_admin`, dan/atau `tunggu_informasi`; bila kosong
  ketiganya dipakai dengan label bawaan. Target dihitung ulang dan baris
  pesanan/detail dikunci `FOR SHARE` sampai commit. Detail yang masih memiliki
  notifikasi berstatus `menunggu_respons`, `menunggu_konfirmasi`, atau
  `menunggu_tindak_lanjut` dilewati dan dilaporkan sebagai
  `dilewati_sudah_dinotifikasi`. Bila tidak ada target baru, HTTP 422 dan seluruh
  transaksi dibatalkan. Sukses `{ok,id,jumlah_target,jumlah_pesanan,online,offline}`
  HTTP 201.
- POST `{action:"confirm",id}` hanya Admin. Aksi ini hanya menerima respons
  `menu_pengganti` yang masih menunggu konfirmasi dan pesanan yang masih aktif.
  Server mengunci pesanan serta kuota, memvalidasi ulang produk/sisa kuota dan
  minimum porsi, lalu dalam satu transaksi mengubah item dan total pesanan
  memakai harga snapshot yang disetujui pelanggan. Snapshot total lama/baru dan
  selisih bertanda disimpan. Status pesanan dan pembayaran tidak berubah.
  Selisih positif menunggu tagihan manual; selisih
  negatif menunggu pengembalian manual; selisih nol tidak memerlukan penyelesaian.
- POST `{action:"settle_difference",id}` mencatat bahwa tagihan/pengembalian
  manual telah diselesaikan. Endpoint tidak memindahkan uang atau mengubah status
  pembayaran.
- POST `{action:"complete_follow_up",id,catatan_tindak_lanjut}` mencatat hasil
  kontak Admin untuk pilihan `hubungi_admin` atau `tunggu_informasi`. Catatan
  wajib dan maksimal 1000 karakter.

Respons memakai `json_encode` tanpa `JSON_NUMERIC_CHECK` agar nomor telepon
berawalan 0 tetap string. Aksi `confirm` menghitung selisih untuk audit, tetapi
tidak menagih atau memindahkan uang; tindak lanjut selisih dilakukan manual.

UI Admin (`admin/menu-notifications.php`) memakai endpoint ini untuk memilih
menu, memuat produk per tanggal acuan, preview target, konfirmasi, riwayat, dan
detail respons. UI Owner (`owner/menu-notifications.php`) membaca endpoint yang
sama secara langsung dan hanya menampilkan riwayat serta detail tanpa form
pembuatan atau aksi persetujuan. Skrip bersama berada di
`assets/js/menu-notifications.js` dengan mode `admin`/`owner`.

### Notifikasi perubahan menu pelanggan

Endpoint `pelanggan/api/menu-notifications.php` hanya untuk sesi pelanggan.
Pengguna lain mendapat HTTP 401. Semua query membatasi data dengan
`pesanan.user_id` milik sesi dan `pesanan.sumber = 'online'`; ID dari browser
tidak pernah dipakai sebagai bukti kepemilikan. Pesanan offline tidak pernah
tampil di endpoint ini.

- GET `?action=list&limit=20&offset=0` mengembalikan `{items,total,limit,offset,
  belum_dibaca}`. Setiap item memuat snapshot produk, catatan Admin, jumlah
  pesanan/penerima, jumlah yang sudah merespons, status respons, dan
  `belum_dibaca` per notifikasi. `limit` dibatasi 1–100.
- GET `?action=detail&id=...` mengembalikan header notifikasi, opsi respons, dan
  hanya baris penerima milik pelanggan. Setiap penerima memuat snapshot menu
  lama, menu pengganti yang sudah dipilih, catatan pelanggan, status, dan
  `bisa_merespons`. Bila masih boleh merespons, `pilihan_menu` berisi produk
  lain yang sisa kuotanya cukup untuk jumlah lama dan jumlah lama memenuhi
  minimum porsi pada tanggal kirim. Menu lama selalu dikecualikan.
- POST `{action:"read",id}` menandai seluruh baris penerima pelanggan pada
  notifikasi itu sebagai dibaca. Respons `{ok,dibaca,belum_dibaca}`. Notifikasi
  milik pelanggan lain menghasilkan HTTP 404.
- POST `{action:"respond",id,opsi,catatan_respons?,produk_pengganti_id?}`
  menyimpan pilihan pada satu baris penerima. `id` adalah ID penerima, bukan ID
  notifikasi. Hanya `status_respons='menunggu_respons'` dengan pesanan masih
  aktif (`menunggu_pembayaran`, `menunggu_validasi`, `diproses`) yang dapat
  merespons; selain itu HTTP 409. Opsi harus terdaftar pada notifikasi. Untuk
  `menu_pengganti`, produk wajib berbeda dari menu lama, tersedia pada tanggal
  kirim, memiliki sisa kuota cukup, dan memenuhi minimum porsi; snapshot
  kode/nama/qty/harga disimpan tanpa menghitung `selisih_total`. Status menjadi `menunggu_konfirmasi` untuk
  `menu_pengganti`, atau `menunggu_tindak_lanjut` untuk `hubungi_admin` dan
  `tunggu_informasi`. `catatan_respons` maksimal 1000 karakter. Setelah respons
  terkirim, pilihan terkunci sampai Admin memprosesnya. Respons sukses memuat
  `{ok,id,status_respons,opsi,menu_pengganti,belum_dibaca}`.

GET `pelanggan/api/orders.php?id=...` juga memuat `menu_notifications` milik
pesanan tersebut. Data ini dibatasi oleh `pesanan.user_id` sesi pelanggan dan
menyediakan tautan langsung ke detail notifikasi.

Halaman pelanggan memakai `pelanggan/menu-notifications.php` dengan
`pelanggan/assets/menu-notifications.js` dan `menu-notifications.css`. Jumlah
belum dibaca dirender server pada lonceng topbar dan navigasi bawah, lalu
diperbarui dari respons API. Membuka detail memanggil aksi `read` secara
terpisah, bukan sebagai efek samping GET. UI menjelaskan bahwa perubahan menu
belum berlaku dan status pesanan/pembayaran tidak berubah sampai Admin
mengonfirmasi.

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
