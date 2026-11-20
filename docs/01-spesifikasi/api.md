# API

Navigasi Pelanggan memakai sidebar dengan tombol Keluar yang memanggil alur logout bersama. Hamburger mobile hanya mengatur visibilitas sidebar dan fokus keyboard.

Keranjang Pelanggan mengubah jumlah satu porsi per klik. Tambah dibatasi sisa kuota dan total 50 porsi per pesanan. Kurangi berhenti di minimum menu, sekurang-kurangnya 4 porsi atau nilai `min_pesan` produk yang lebih tinggi, dan Hapus mengeluarkan menu dari keranjang. Checkout mengirim `items: [{id, qty}]` dan server memeriksa ulang batas serta kuota.

Bootstrap menyembunyikan argumen fungsi dari stack trace PHP untuk melindungi kredensial. Format respons endpoint tetap sama.

## Query navigasi UI Admin

`admin/order-detail.php?id=...&return_to=...` menerima tujuan kembali opsional.
Nilainya hanya nama relatif `dashboard.php` atau `orders.php`, dengan query
`tgl_kirim`; khusus daftar juga `tab`, `review`, `source`, dan `q`. Query di-encode
sebagai satu nilai `return_to`. URL absolut, direktori lain, parameter asing,
duplikat/array, tanggal tidak valid, dan tujuan bersarang kembali ke `orders.php`.
`review` dan `source` hanya dipertahankan untuk tab `dibatalkan`.

Daftar menyimpan tab/tinjauan/sumber/pencarian ke URL tanpa menambah entri history;
tanggal yang sudah dipilih dipertahankan. Tombol kembali memakai href tervalidasi
server; klik biasa mengganti entri detail, sedangkan tab baru tetap native.
Parameter ini bukan parameter API JSON atau POST; kontrak endpoint tidak berubah.

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
default produk 0, kecuali tanggal kirim yang tercakup
`tanggal_libur_operasional`; tanggal libur selalu dianggap tidak tersedia
dengan kuota efektif 0, sama seperti `daily_quota_snapshot()`. Pesanan offline
ikut ditautkan untuk tindak lanjut manual, tetapi ditandai
`notifikasi_in_app=false` karena tidak memiliki `user_id`.

Pembuatan notifikasi memakai advisory lock transaksi PostgreSQL dengan namespace
`0x4D4E4F54` ("MNOT") dan key kedua `produk_id`. Lock diambil sebelum target
dihitung ulang, sehingga dua Admin yang membuat notifikasi untuk produk yang sama
diproses berurutan: Admin pertama menyimpan targetnya, Admin kedua memeriksa
ulang target setelah lock dan mendapat HTTP 422
`Tidak ada pesanan aktif yang belum dinotifikasi untuk menu ini.` beserta
`dilewati_sudah_dinotifikasi`. Lock dilepas otomatis pada commit/rollback dan
tidak menghalangi notifikasi baru setelah notifikasi sebelumnya `selesai`.

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
  bertanda, status respons, dan ringkasan respons. Setiap penerima juga memuat
  `jenis_penutupan`, `alasan_penutupan`, `ditutup_oleh`, dan `ditutup_at` agar
  penolakan penggantian atau penutupan otomatis terlihat oleh Admin/Owner.
  Setiap penerima juga memuat `info_harga` dan `pembayaran` untuk transparansi
  Tahap 4A (lihat di bawah).
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
- POST `{action:"reject_replacement",id,alasan_penolakan}` hanya Admin. Hanya
  respons `menu_pengganti` berstatus `menunggu_konfirmasi` pada pesanan aktif
  yang dapat ditolak. `alasan_penolakan` wajib 1–1000 karakter. Server menyimpan
  `status_respons='selesai'`, `jenis_penutupan='penolakan_pengganti'`,
  `alasan_penolakan`, `ditutup_oleh`, dan `ditutup_at` tanpa mengubah
  `detail_pesanan`, `pesanan.total`, status pesanan, atau bukti pembayaran.
  Penolakan kedua, alasan kosong, opsi bukan pengganti, dan pesanan tidak aktif
  ditolak HTTP 409/422.
- POST `{action:"settle_difference",id}` mencatat bahwa tagihan/pengembalian
  manual telah diselesaikan. Endpoint tidak memindahkan uang atau mengubah status
  pembayaran.
- POST `{action:"complete_follow_up",id,catatan_tindak_lanjut}` mencatat hasil
  kontak Admin untuk penerima online dengan status `menunggu_tindak_lanjut` dan
  pilihan `hubungi_admin` atau `tunggu_informasi`, atau untuk penerima offline
  (`pesanan.sumber='offline'` atau `pesanan.user_id IS NULL`) yang masih
  `menunggu_respons`. Catatan wajib dan maksimal 1000 karakter; status lain
  ditolak HTTP 409.

Respons memakai `json_encode` tanpa `JSON_NUMERIC_CHECK` agar nomor telepon
berawalan 0 tetap string. Aksi `confirm` menghitung selisih untuk audit, tetapi
tidak menagih atau memindahkan uang; tindak lanjut selisih dilakukan manual.

Kebijakan harga penggantian (Tahap 4A): harga dikunci pada saat pelanggan
memilih (`MENU_NOTICE_PRICE_POLICY = 'snapshot_saat_pelanggan_memilih'`).
Konfirmasi Admin memakai harga snapshot untuk `detail_pesanan` dan
`pesanan.total`; perubahan harga katalog setelahnya hanya diaudit. Kolom
`harga_katalog_saat_konfirmasi` dan `perbedaan_harga_katalog`
(migrasi 024) mencatat harga katalog saat konfirmasi dan selisihnya terhadap
snapshot. Detail respons memuat `info_harga` (`harga_snapshot`,
`harga_katalog_sekarang`, `perbedaan_harga_katalog`, `kebijakan_harga`,
`catatan_harga`) sehingga Admin melihat perbedaan harga sebelum dan sesudah
menyetujui.

Ringkasan pembayaran pada detail respons (Tahap 4A): field `pembayaran`
memuat `status_pembayaran` (`belum_membayar`, `menunggu_verifikasi`,
`terverifikasi`, `tidak_ada_bukti`, `manual_offline`, atau `tidak_berlaku`),
`tahap_pembayaran` berupa keterangan singkat, `sudah_membayar` (hanya true
untuk `terverifikasi`), `nominal_tagihan` dari `pesanan.total`,
`total_pesanan_lama`, `total_pesanan_baru`, `selisih_total`,
`status_penyelesaian_selisih`, `bukti_bayar`, `nama_pembayar`, dan `sumber`.
Ringkasan ini hanya membaca data yang sudah ada (status pesanan, bukti, total);
tidak ada status pembayaran baru, charge, atau refund otomatis. Field
`status_pembayaran` dan `sudah_membayar` juga diulang di level penerima agar
Admin langsung membedakan pelanggan yang sudah dan belum membayar.

Locking kuota dan tanggal libur (Tahap 4A): urutan lock tabel kanonik
`produk` → `kuota_harian` → `tanggal_libur_operasional`
(SHARE ROW EXCLUSIVE) dibungkus dalam `daily_quota_lock_tables()` di
`app/daily-quota.php` dan dipakai bersama oleh checkout pelanggan, pesanan
offline, respons penggantian pelanggan, dan konfirmasi/penolakan Admin. Urutan
sama pada semua pemanggil mencegah deadlock; pengecekan kuota dan tanggal libur
berlangsung dalam transaksi yang sama dengan penyimpanan pesanan.

UI Admin (`admin/menu-notifications.php`) memakai endpoint ini untuk memilih
menu, memuat produk per tanggal acuan, preview target, konfirmasi/penolakan
penggantian, riwayat, dan detail respons. UI Owner
(`owner/menu-notifications.php`) membaca endpoint yang sama secara langsung dan
hanya menampilkan riwayat serta detail tanpa form pembuatan atau aksi
persetujuan/penolakan. Skrip bersama berada di
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
  minimum porsi pada tanggal kirim. Menu lama selalu dikecualikan. Setiap
  penerima juga memuat `jenis_penutupan`, `alasan_penutupan`, `ditutup_oleh`, dan
  `ditutup_at`; `alasan_penutupan` adalah alasan penolakan penggantian atau
  alasan penutupan otomatis yang dapat dibaca pelanggan.
- POST `{action:"respond",...}` menolak HTTP 409 bila notifikasi sudah ditutup
  (`status_respons='selesai'`) atau pesanan sudah tidak aktif. Pelanggan tidak
  dapat mengirim respons baru setelah pesanan dibatalkan atau kedaluwarsa.
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
Field kuota item sama dengan produk Admin. `items` memuat semua produk yang cocok
dengan filter, termasuk yang sisa kuotanya 0 atau di bawah minimum. Produk yang
tidak dapat dipesan memiliki `available:false`; UI menonaktifkan tombol tambah,
tetapi detail dan sisa kuota tetap dapat dilihat. `habis` tetap memuat daftar
produk yang tidak dapat dipesan untuk peringatan. Tanggal invalid HTTP 422.
Produk dengan sisa 0 diberi label `Stok habis` di pojok kanan atas kartu;
label berbentuk pita abu dan tidak mengubah respons API atau aturan checkout.
GET `pelanggan/api/availability.php?start=YYYY-MM-DD&end=YYYY-MM-DD` mengembalikan
status per tanggal: `available`, `nearly_full`, `full`, `menu_empty`, atau `closed`.
Kapasitas dapur adalah 80% jumlah kuota efektif tanggal; kalender menandai status
dengan titik warna di bawah angka. Tombol `Tanggal tersedia` memilih status
`available` terdekat mulai H+7, hingga 12 bulan pencarian. Kapasitas checkout
Admin, Owner, Pelanggan, dan pesanan offline memakai snapshot bersama yang sama.
Saat `tanggal_libur` true, semua kuota dan kapasitas efektif 0; UI memberi
peringatan tanggal libur.
Panel tanggal katalog hanya menampilkan label dan pemilih tanggal saat normal;
pesan libur, kuota penuh/hampir penuh, dan menu habis muncul sesuai respons
katalog. Kontrak endpoint dan batas H+7 tidak berubah.

POST `pelanggan/api/orders.php` mengambil identitas dari session pelanggan, tgl_kirim,
dan `items:[{id,qty}]`. Produk berulang digabung. Total maksimal 50 porsi per pesanan
dan minimum tiap menu mengikuti `max(4, min_pesan)`. Harga dari database disimpan sebagai snapshot; alamat
harus milik pelanggan. Kuota dan batas dapur 80% diperiksa dalam transaksi terkunci.
Sukses `{ok,id,kode,total}` HTTP 201, validasi `{error}` HTTP 422.
Penurunan kuota atau tanggal libur mempertahankan pesanan lama dan membatasi
checkout baru; checkout tanggal libur ditolak dengan HTTP 422 meskipun request
melewati UI. `jadwal_po` tetap menjadi referensi FK pesanan, tetapi tidak lagi
dikelola dari halaman Jadwal.
Pesanan berstatus `menunggu_pembayaran` dibatalkan otomatis satu jam sejak
`created_at` bila bukti belum dikirim. Jalankan `php scripts/expire-unpaid-orders.php`
melalui scheduler setiap menit; GET katalog dan pesanan pelanggan tidak
menjalankan pembersihan atau perubahan status. Status `dibatalkan` tidak memakai kuota.

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

### Visibilitas dan tinjauan pembatalan Admin

Migrasi `021_cancellation_admin_review.sql` harus tersedia sebelum endpoint
membaca metadata ini. Sumber serta status sebelum pembatalan ditentukan server
pada UPDATE pelanggan/job yang berhasil, bukan dari payload pengguna.

GET `admin/api/orders.php?tab=dibatalkan` menerima `review=unreviewed` atau
`review=reviewed`; tanpa parameter/nilai kosong mencakup keduanya. Filter tinjauan
hanya diterapkan pada tab batal, sedangkan `q` tetap mencari kode/nama pemesan.
Nilai review tidak dikenal menghasilkan 422. Tab batal diurutkan berdasarkan
`status_updated_at DESC NULLS LAST, id DESC`; tab lainnya mempertahankan urutan lama.
`counts.cancelled` adalah total seluruh pembatalan, dan
`counts.cancellation_unreviewed` adalah total yang belum ditinjau; keduanya tidak
dibatasi pencarian atau filter tinjauan.

Daftar/detail Admin serta item pemberitahuan Dashboard menambahkan:

| Field | Isi |
|---|---|
| `cancellation_source` | `pelanggan`, `otomatis`, atau NULL untuk riwayat yang belum tercatat. |
| `status_before_cancel` | Status asal pembatalan, atau NULL bila tidak tercatat. |
| `cancelled_at` | Waktu status pembatalan tercatat, ISO 8601 +07:00 atau NULL. |
| `reviewed_at` | Waktu tinjauan pertama, ISO 8601 +07:00 atau NULL. |
| `reviewed_by` | ID Admin peninjau pertama; NULL jika belum ditinjau/akun sudah dihapus. |
| `reviewed_by_name` | Nama peninjau yang masih tersedia, atau NULL. |
| `needs_payment_review` | Boolean: bukti tersimpan atau status asal menunggu_validasi/diproses. Ini bukan bukti pembayaran/refund sudah selesai. |
| `production_stop_required` | Boolean: status asal diproses, sehingga persiapan perlu dihentikan. |

Pada pesanan bukan batal, metadata tinjauan/pembatalan NULL dan kedua flag false.
Pesanan batal lama tetap memiliki sumber/status asal NULL dan tidak ditebak dari
bukti transfer; waktu status yang tercatat tetap dapat ditampilkan sebagai riwayat.

POST `admin/api/orders.php` dengan `{action:"review_cancellation",id}` hanya
dapat dijalankan Admin. ID wajib integer positif; invalid 422, pesanan tidak ada
404, status bukan batal 409, kegagalan penyimpanan 500. Sukses mengembalikan
`{ok:true,...metadata_pembatalan}` beserta id/kode/rincian VIEW yang sama. Row lock
menjamin hanya peninjau pertama yang mengisi waktu/ID; permintaan ulang berhasil
tanpa menimpa keduanya. Penanda berlaku bersama untuk semua Admin.

Tinjauan tidak mengubah status, `status_updated_at`, bukti, kuota, atau refund.
GET tidak pernah menandai pesanan ditinjau. Bukti/foto dan daftar menu pada detail
batal tetap tersedia read-only untuk pemeriksaan.
Pada detail pesanan batal, sumber/waktu/status sebelumnya ditampilkan dalam
blok ringkasan terpisah dari judul kartu; badge status utama ditampilkan sekali.

### Penutupan notifikasi perubahan menu saat pesanan berakhir

Migrasi `023_menu_change_closure_audit.sql` menambahkan kolom audit
`jenis_penutupan`, `alasan_penutupan`, `ditutup_oleh`, dan `ditutup_at` pada
`pemberitahuan_perubahan_menu_pesanan`. Kolom ini terpisah dari
`catatan_tindak_lanjut` agar jenis penutupan tidak tercampur dengan catatan
manual.

Saat pelanggan membatalkan pesanan atau job `expire-unpaid-orders.php`
membatalkan pesanan kedaluwarsa, seluruh respons berstatus
`menunggu_respons`, `menunggu_konfirmasi`, dan `menunggu_tindak_lanjut` untuk
pesanan itu ditutup dalam transaksi yang sama dengan pembatalan. Penutupan
memakai `status_respons='selesai'`, `jenis_penutupan='pembatalan_pesanan'` atau
`'kedaluwarsa_pesanan'`, alasan yang sesuai, serta pelaku (`ditutup_oleh`) untuk
pembatalan pelanggan dan NULL untuk job otomatis. Respons `selesai` dan
`dikonfirmasi` tidak pernah ditimpa, dan riwayat tidak dihapus.

Bila penutupan notifikasi gagal, seluruh transaksi pembatalan/job di-rollback;
pesanan tidak boleh berakhir batal sebagian. Setelah penutupan, pelanggan tidak
dapat mengirim respons baru dan Admin tidak dapat menyetujui atau menolak
penggantian pada pesanan yang sudah batal/kedaluwarsa (HTTP 409).

GET `admin/api/dashboard.php` menambah:
`cancellations:{unreviewed_count,urgent_count,items}`. `items` maksimal 2 dari
VIEW `v_pembatalan_pesanan`, seluruhnya belum ditinjau, dengan urutan perlu
hentikan produksi, perlu periksa pembayaran, lalu waktu pembatalan terbaru/ID.
Tidak ada batas tujuh hari dan filter `tgl_kirim` tidak membatasi pemberitahuan.
`urgent_count` menghitung yang memiliki salah satu flag tindak lanjut; penanda
Sudah ditinjau mengeluarkan item dari pemberitahuan, bukan dari riwayat pesanan.
Data diperbarui saat halaman dimuat ulang, tanpa push realtime.

Dashboard menampilkan panel pembatalan di bawah rekap menu per tanggal kirim.
`items` dibatasi maksimal 2 pesanan; total belum ditinjau tetap menyeluruh dan
tautan daftar membuka seluruh antrean. Dashboard menjelaskan alasan prioritas tiap
pesanan dan menautkan ke detail untuk memeriksa bukti/status. Tombol Sudah ditinjau
hanya tersedia pada detail dan hanya mencatat pemeriksaan Admin; pembayaran/refund
tetap perlu diverifikasi manual.

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

### Tawaran jadwal pengganti

Admin dapat mengirim POST `admin/api/orders.php` dengan
`{action:"offer_reschedule",id,alasan,tanggal_usulan}` untuk pesanan online
berstatus `menunggu_pembayaran`, `menunggu_validasi`, atau `diproses` yang
tanggal pengirimannya ditutup. Pesanan offline tanpa akun pelanggan tidak dapat
menerima tawaran. Untuk `menunggu_validasi`, adanya bukti menjadi syarat dan
tawaran berarti Admin menyatakan pembayaran valid. Alasan wajib 1–500 karakter;
tanggal tujuan harus tanggal nyata setelah hari ini, bukan tanggal libur, serta
kuota produk dan dapur harus tersedia. Kuota tujuan diperiksa lagi saat pelanggan
menerima dan tidak dipesan saat tawaran dibuat.

Tawaran disimpan terpisah pada satu baris per pesanan. Status pesanan/tanggal lama
tidak berubah saat tawaran dikirim. Tawaran unpaid berakhir pada tenggat asli
`created_at + 1 jam`; tawaran `menunggu_validasi`/`diproses` berlaku 24 jam.
Detail Admin dan pelanggan menambahkan `reschedule_offer` dengan tanggal asal,
tanggal usulan, alasan, status, `dibuat_at` (token versi), dan batas waktu.

Pelanggan pemilik pesanan menjawab lewat POST `pelanggan/api/orders.php` dengan
`{action:"respond_reschedule",id,decision:"accept"|"reject",offer_version}`.
Versi harus sama dengan `dibuat_at` yang ditampilkan. Accept memeriksa ulang
tanggal libur/kuota secara atomik, mengganti tanggal sambil mempertahankan jam,
dan mengubah `menunggu_validasi` menjadi `diproses`. Reject membatalkan pesanan
serta mencatat sumber/status asal pembatalan; refund yang sudah dibayar tetap
ditangani Admin di luar aplikasi. Versi lama, tawaran kedaluwarsa, atau status
pesanan yang berubah menghasilkan HTTP 409. Endpoint GET hanya membaca tawaran.
