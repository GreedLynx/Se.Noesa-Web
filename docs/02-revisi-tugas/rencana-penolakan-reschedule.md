# Rencana penolakan pesanan dan tawaran jadwal ulang

Status: implementasi selesai di branch `feature/penolakan-reschedule`; verifikasi PostgreSQL terisolasi dan review akhir masih berjalan.

## Tujuan utama

Admin dapat menangani pesanan yang tanggal pengirimannya ditutup dengan memberi
alasan dan satu tanggal pengganti. Pelanggan dapat menerima atau menolak tawaran
di aplikasi, dengan pembayaran, kuota, dan riwayat pesanan tetap konsisten.

## Hasil yang diinginkan

| Kondisi | Hasil |
|---|---|
| `menunggu_pembayaran`, tanggal lama ditutup | Admin menawarkan tanggal baru; pelanggan tetap harus membayar dalam tenggat asli. |
| `menunggu_validasi`, pembayaran tidak valid | Admin memakai penolakan pembayaran yang sudah ada dan wajib memberi alasan; tidak ada tawaran jadwal. |
| `menunggu_validasi`, pembayaran valid dan tanggal lama ditutup | Admin menawarkan tanggal baru; jika diterima, pesanan menjadi `diproses` tanpa meminta pembayaran ulang. |
| `diproses`, tanggal lama ditutup | Admin menawarkan tanggal baru dan proses produksi dijeda secara operasional sampai pelanggan menjawab. |
| Pelanggan menerima | Server memeriksa ulang tanggal libur dan kuota, lalu memindahkan tanggal dengan tetap mempertahankan jam pengiriman. |
| Pelanggan menolak | Pesanan menjadi `dibatalkan`; jika pembayaran telah diterima, refund penuh tetap ditangani Admin di luar aplikasi. |

## Keputusan rancangan

1. Tawaran jadwal disimpan terpisah dari status pesanan. Status pesanan tidak
   ditambah dengan nilai baru sehingga constraint, filter, VIEW, job, dan laporan
   yang sudah ada tidak perlu diubah hanya untuk menampilkan masa tunggu.
2. Hanya satu tawaran aktif per pesanan. Admin dapat mengganti tanggal/alasan
   tawaran yang masih menunggu; row lock mencegah pelanggan menerima versi lama
   bersamaan dengan perubahan Admin.
3. Tawaran hanya tersedia untuk `menunggu_pembayaran`, `menunggu_validasi`, dan
   `diproses`, serta hanya ketika tanggal pengiriman lama termasuk tanggal libur
   operasional. Untuk `menunggu_validasi`, pilihan tawaran berarti Admin menyatakan
   pembayaran valid; penerimaan mengubah status menjadi `diproses`.
4. Tanggal usulan harus merupakan tanggal masa depan yang tidak ditutup. Kuota
   diperiksa saat Admin membuat tawaran untuk mencegah usulan yang sudah penuh,
   tetapi tidak dipesan. Pemeriksaan dan penguncian diulang ketika pelanggan menerima.
5. Tawaran untuk status `menunggu_validasi`/`diproses` berlaku 24 jam. Pesanan
   `menunggu_pembayaran` tetap memakai tenggat pembayaran satu jam sejak
   `created_at`; tawaran tidak menjeda atau mengulang tenggat tersebut.
6. Selama tawaran aktif, mutasi yang bentrok ditolak server: validasi/penolakan
   pembayaran, checklist, upload kiriman, penyelesaian, upload bukti, dan
   pembatalan umum. Pelanggan tetap dapat membatalkan melalui tombol **Tolak
   jadwal**, sedangkan job kedaluwarsa tetap dapat membatalkan unpaid.
7. Tanggal lama dan kuotanya tetap tercatat sampai tawaran diterima. UI Admin
   harus menampilkan penanda **Menunggu konfirmasi jadwal** dan instruksi jeda
   produksi; tidak ada reservasi kuota tanggal usulan.
8. Penerimaan hanya mengganti bagian tanggal dari `pesanan.tgl_kirim`; jam
   pengiriman yang sudah tersimpan tetap dipakai.

## Di luar tahap ini

- Pelanggan tidak mengusulkan tanggal tandingan dan tidak memilih dari beberapa tanggal.
- Tidak ada notifikasi WhatsApp/email, refund otomatis, atau pembayaran ulang.
- Owner tidak mendapat aksi baru; aksesnya tetap read-only. Tampilan khusus
  tawaran pada halaman Owner dapat ditambahkan kemudian jika dibutuhkan.
- Tidak ada perubahan nama kolom lama `pesanan.pax` atau keputusan terbuka lain
  dalam `AGENTS.md`.

## Phase 0 — Penemuan dokumentasi dan kontrak yang tersedia

**How this serves the primary goal:** memastikan tawaran jadwal memakai alur
status, penguncian, kuota, dan hak akses yang sudah terbukti di proyek.

### Sumber dan temuan

- `docs/peta-kode.md`, `docs/aturan-bisnis.md`, `docs/api.md`,
  `docs/database.md`, dan `HANDOFF.md` sudah dibaca. `.opencode/revisi.md` tidak
  ditemukan pada branch saat rencana ditulis.
- `public/admin/api/orders.php:137-207` memuat transisi `validate`, `reject`, dan
  `complete`. Penolakan pembayaran hanya menerima `menunggu_validasi`, memakai
  `SELECT ... FOR UPDATE`, dan mewajibkan `alasan_penolakan` 1–500 karakter.
- `public/pelanggan/api/orders.php:10-36` memuat pembatalan milik pelanggan dengan
  row lock. Baris 136–160 mengunci upload bukti dan tenggat satu jam. Baris
  211–257 menunjukkan urutan lock checkout dan pemakaian helper kuota.
- `app/daily-quota.php:3-108` menyediakan `daily_quota_snapshot($date, $report)`
  dan `daily_quota_validate($snapshot, $quantities)`. Pesanan aktif dihitung dari
  `detail_pesanan`; hanya `ditolak` dan `dibatalkan` yang tidak memakai kuota.
- `app/order-expiration.php:2-11` membatalkan unpaid berdasarkan `created_at + 1
  hour`. Endpoint GET tidak menjalankan job ini.
- `database/schema.sql:76-104` menunjukkan `tgl_kirim` bertipe `TIMESTAMPTZ` dan
  status yang tersedia. Tidak ada status menunggu jadwal atau tabel tawaran.
- Migrasi `020_order_rejection_reason.sql` hanya untuk alasan pembayaran tidak
  valid. Migrasi `021_cancellation_admin_review.sql` menyimpan metadata
  pembatalan pelanggan/otomatis; keduanya tidak boleh dipakai untuk menyimpan
  tawaran jadwal.
- `public/admin/order-detail.php:19-57` dan `orderDetail()` di
  `public/admin/assets/admin.js:870-1110` menjadi pola dialog serta aksi Admin.
  `openHistoryDetail()` di `public/pelanggan/assets/customer.js` menjadi pola
  kartu detail dan aksi pelanggan.
- Pola uji database/race tersedia di `scripts/test-order-cancellation.php`, job
  di `scripts/test-payment-deadline.php`, penolakan pembayaran di
  `scripts/test-order-rejection-reason.php`, dan UI pelanggan di
  `scripts/test-order-cancellation-ui.cjs`.

### Allowed APIs dan pola

| Kebutuhan | API/pola yang dipakai |
|---|---|
| Hak akses | `require_role('admin')` dan `require_role('pelanggan')`; identitas pelanggan selalu dari sesi. |
| Database | `db(): PDO`, `q($sql, $params)`, prepared statement, transaksi, dan `SELECT ... FOR UPDATE`. |
| Respons | `body()` untuk JSON dan `json($data, $status)` untuk respons endpoint. |
| Kuota | `daily_quota_snapshot()` dan `daily_quota_validate()` setelah urutan lock checkout yang sudah ada. |
| Tanggal libur | Tabel `tanggal_libur_operasional` dan overlay tanggal pada helper kuota. |
| Pembatalan | Metadata `sumber_pembatalan='pelanggan'` serta `status_sebelum_batal` ditentukan server. |
| UI | Dialog native yang sudah dipakai, `esc()`, `dateLabel()`, `api()`, dan komponen tombol/panel proyek. |

**Verification checklist:** sebelum implementasi, periksa lagi HEAD branch,
nomor migrasi tertinggi, signature helper, dan kontrak endpoint karena branch lain
dapat berubah setelah rencana ini ditulis.

**Anti-pattern guards:** jangan menambah status pesanan baru, jangan memakai GET
untuk mutasi, jangan menerima user ID/status asal dari payload, jangan memakai
`alasan_penolakan` untuk catatan jadwal, dan jangan menjalankan job dari halaman web.

## Phase 1 — Migrasi dan kontrak tawaran jadwal

**How this serves the primary goal:** menyediakan satu sumber data yang mencatat
alasan, tanggal usulan, keputusan pelanggan, dan batas waktu tanpa mengubah status
pesanan sebelum pelanggan menjawab.

### What to implement

1. Minta persetujuan tim untuk perubahan skema sesuai `AGENTS.md`, lalu periksa
   ulang nomor migrasi. Jika 021 masih terakhir, buat
   `database/migrations/022_order_reschedule_offer.sql`.
2. Tambahkan tabel `penawaran_jadwal_ulang` minimal dengan:
   `id`, `pesanan_id` (UNIQUE, FK cascade), `status_asal`, `alasan`,
   `tanggal_usulan` (DATE), `status_tawaran` (`menunggu`, `diterima`, `ditolak`,
   `kedaluwarsa`), `dibuat_oleh` (FK Admin, SET NULL), `dibuat_at`,
   `berlaku_sampai`, dan `ditanggapi_at`.
3. CHECK membatasi `status_asal` ke tiga status yang disetujui, alasan 1–500
   karakter, serta status tawaran ke empat nilai di atas. Satu baris per pesanan
   cukup untuk versi awal; perubahan tawaran memperbarui baris yang sama.
4. Tambahkan struktur setara ke `database/schema.sql`. Jangan mengubah constraint
   `pesanan.status`, VIEW kuota, atau VIEW rekap produksi.
5. Tetapkan kontrak respons bersama `reschedule_offer` pada detail Admin dan
   pelanggan: tanggal lama/usulan, alasan, status asal/tawaran, batas waktu,
   waktu tanggapan, dan boolean aksi yang dihitung server.

### Documentation references

- Ikuti gaya FK/CHECK/timestamp `database/schema.sql:76-123`.
- Ikuti migrasi nullable/FK/index `database/migrations/021_cancellation_admin_review.sql`.
- Catat kontrak aktif hanya saat implementasi di `docs/api.md` dan skema di
  `docs/database.md`; jangan menyajikan rancangan sebagai fitur aktif sebelum kode ada.

### Verification checklist

- Migrasi fresh dan upgrade berjalan satu kali melalui `scripts/migrate.php`.
- Constraint menolak status/asalan/tanggal yang tidak valid.
- Migrasi tidak mengubah baris pesanan, status, bukti, atau kuota yang sudah ada.

### Anti-pattern guards

- Jangan menambah kolom tawaran ke `pesanan` satu per satu atau memakai satu
  string bebas untuk seluruh state.
- Jangan membuat tabel histori/negosiasi multiputaran pada versi awal.
- Jangan mengubah file migrasi lama yang mungkin sudah tercatat di ledger.

## Phase 2 — Admin membuat atau mengganti tawaran

**How this serves the primary goal:** memberi Admin satu aksi yang aman untuk
menjelaskan penutupan tanggal dan menawarkan tanggal pengganti yang saat itu masih tersedia.

### What to implement

1. Tambahkan POST `action=offer_reschedule` pada
   `public/admin/api/orders.php` dengan `id`, `alasan`, dan `tanggal_usulan`.
2. Validasi ID positif, alasan 1–500 karakter, format/tanggal nyata, tanggal
   usulan setelah hari ini WIB, serta tanggal lama benar-benar tercakup
   `tanggal_libur_operasional`.
3. Dalam transaksi, ikuti urutan lock checkout untuk produk, kuota, tanggal
   libur, dan baris kuota tanggal tujuan; lock pesanan dan tawaran sebelum
   keputusan akhir. Pastikan status masih salah satu dari tiga status yang sah.
4. Untuk `menunggu_validasi`, wajib ada bukti pembayaran. Aksi ini menjadi
   pernyataan Admin bahwa pembayaran valid; jangan sekaligus menjalankan action
   `validate` karena status baru berubah setelah pelanggan menerima.
5. Ambil kuantitas dari `detail_pesanan`, lalu panggil `daily_quota_snapshot()`
   dan `daily_quota_validate()`. Pemeriksaan ini hanya memvalidasi usulan, tidak
   mengubah `tgl_kirim` dan tidak memesan kuota tujuan.
6. Hitung `berlaku_sampai`: `created_at + 1 hour` untuk
   `menunggu_pembayaran`, atau waktu database + 24 jam untuk dua status lain.
   Tolak tawaran unpaid yang tenggat pembayarannya sudah lewat.
7. INSERT tawaran baru atau UPDATE tawaran yang belum diterima/ditolak dengan
   row lock. Kembalikan object `reschedule_offer` terbaru.
8. Tambahkan tombol **Tawarkan jadwal pengganti** dan dialog pada detail Admin.
   Tampilkan tanggal lama, alasan, input satu tanggal, masa berlaku, dan peringatan
   bahwa kuota baru diperiksa ulang saat pelanggan menerima.
9. Detail dan daftar Admin menampilkan badge **Menunggu konfirmasi jadwal**.
   Detail memberi instruksi untuk menjeda produksi bila status asal `diproses`.

### Documentation references

- Salin validasi alasan dan transaksi row lock dari
  `public/admin/api/orders.php:142-173`.
- Salin urutan lock dan validasi kuota dari
  `public/pelanggan/api/orders.php:211-257`, bukan membuat perhitungan baru.
- Salin pola dialog dari `public/admin/order-detail.php:37-57` dan handler
  `public/admin/assets/admin.js:1028-1110`.

### Verification checklist

- Role selain Admin ditolak server.
- Status terminal, tanggal lama yang tidak ditutup, tanggal tujuan libur/penuh,
  bukti kosong pada `menunggu_validasi`, dan unpaid lewat tenggat menghasilkan
  respons 4xx tanpa perubahan data.
- Dua perubahan tawaran bersamaan menghasilkan satu versi akhir yang utuh.

### Anti-pattern guards

- Jangan percaya status, pembayaran valid, tanggal lama, atau jumlah menu dari payload.
- Jangan mengubah `pesanan.status`, `status_updated_at`, atau `tgl_kirim` saat menawarkan.
- Jangan menyalin logika hitung kuota ke endpoint baru.

## Phase 3 — Pelanggan menerima atau menolak tawaran

**How this serves the primary goal:** pelanggan dapat memberi keputusan yang
tercatat, sementara perpindahan tanggal atau pembatalan terjadi sekali secara atomik.

### What to implement

1. Tambahkan POST `action=respond_reschedule` pada
   `public/pelanggan/api/orders.php` dengan `id` dan `decision` bernilai
   `accept` atau `reject`. Pemilik selalu diambil dari sesi.
2. Lock master kuota/tanggal libur dengan urutan checkout, lalu lock pesanan dan
   tawaran. Periksa pemilik, status tawaran masih `menunggu`, status pesanan sama
   dengan `status_asal`, serta waktu database belum melewati `berlaku_sampai`.
3. Untuk `accept`, baca kuantitas detail dari database, periksa ulang tanggal
   tujuan dan kuota memakai helper bersama, lalu ubah tanggal pada `tgl_kirim`
   dengan mempertahankan jam Asia/Jakarta.
4. Setelah accept, pertahankan `menunggu_pembayaran` atau `diproses` sesuai
   status asal; khusus asal `menunggu_validasi`, ubah menjadi `diproses` karena
   validitas pembayaran sudah diputuskan Admin. Perbarui `status_updated_at`
   hanya bila status memang berubah.
5. Tandai tawaran `diterima` dan isi `ditanggapi_at` dalam transaksi yang sama.
6. Untuk `reject`, ubah pesanan menjadi `dibatalkan`, isi
   `sumber_pembatalan='pelanggan'`, `status_sebelum_batal` dari baris yang
   dikunci, dan tandai tawaran `ditolak` dalam transaksi yang sama.
7. Tambahkan kartu tawaran pada detail riwayat pelanggan: alasan, tanggal lama,
   tanggal baru, batas waktu, serta tombol **Terima jadwal** dan **Tolak jadwal**.
   Setelah keputusan, tampilkan hasil read-only dan tanggal pengiriman aktual.
8. Bila kuota/tanggal berubah sebelum accept, kembalikan 409/422 yang jelas,
   pertahankan tawaran menunggu, dan minta pelanggan menunggu tawaran baru Admin.

### Documentation references

- Salin otorisasi kepemilikan dan row lock dari
  `public/pelanggan/api/orders.php:10-36`.
- Salin lock serta `daily_quota_validate()` dari baris 211–257 file yang sama.
- Salin metadata pembatalan dari action `cancel_order`, bukan dari payload.
- Salin pola render/konfirmasi dari `openHistoryDetail()` dan handler cancel di
  `public/pelanggan/assets/customer.js:461-560`.

### Verification checklist

- Pelanggan tidak dapat menjawab tawaran milik akun lain.
- Accept memindahkan tanggal satu kali, mempertahankan jam, dan tidak melewati
  kuota dalam dua request bersamaan.
- Reject menghasilkan pembatalan yang muncul pada antrean tinjauan Admin dan
  melepaskan kuota melalui agregasi yang sudah ada.
- Tawaran kedaluwarsa/terminal/versi lama tidak dapat diterima kembali.

### Anti-pattern guards

- Jangan menerima `user_id`, `status_asal`, total, item, atau jam kirim dari payload.
- Jangan memindahkan tanggal sebelum validasi kuota selesai.
- Jangan menghidupkan kembali pesanan `ditolak`, `dibatalkan`, atau `selesai`.

## Phase 4 — Penjagaan mutasi, tenggat pembayaran, dan pembacaan UI

**How this serves the primary goal:** mencegah aksi lain menimpa tawaran atau
membuat pelanggan membayar/produksi berjalan ketika jadwal belum disepakati.

### What to implement

1. Buat pemeriksaan query bersama yang kecil untuk mengetahui tawaran aktif;
   gunakan langsung pada endpoint yang benar-benar menulis, tanpa membuat layer
   service/MVC baru.
2. Blokir dengan 409 ketika tawaran masih aktif: action Admin `validate`,
   `reject`, `check_item`, `upload_dispatch`, `complete`; action pelanggan
   `upload_proof` dan `cancel_order`. UI menyembunyikan/menonaktifkan tombol yang
   sama, tetapi server tetap menjadi sumber izin.
3. Pertahankan `expire_unpaid_orders()` sebagai otoritas tenggat satu jam. Ubah
   query secara atomik agar tawaran unpaid terkait ditandai `kedaluwarsa` saat
   pesanan dibatalkan otomatis; jangan memperpanjang `created_at`.
4. Detail GET Admin dan pelanggan menghitung `can_offer_reschedule`,
   `can_respond_reschedule`, serta status efektif tawaran dari database dan waktu
   database. Tawaran lewat waktu ditampilkan kedaluwarsa walau belum disentuh UI.
5. Daftar Admin/pelanggan cukup mendapat indikator tawaran aktif; tidak perlu
   filter/tab baru pada versi awal. Dashboard dan VIEW produksi tetap memakai
   status/tanggal pesanan asli sampai tawaran diterima.

### Documentation references

- Pertahankan UPDATE bersyarat pada `public/admin/api/orders.php:203-207`.
- Pertahankan waktu database dan cleanup upload pada
  `public/pelanggan/api/orders.php:136-160`.
- Pertahankan job CLI-only dari `app/order-expiration.php` dan
  `scripts/expire-unpaid-orders.php`.

### Verification checklist

- Semua mutasi yang bentrok ditolak dari UI dan endpoint langsung.
- Job membatalkan unpaid tepat pada tenggat walau ada tawaran, menutup tawaran,
  dan tidak memengaruhi pesanan yang sudah mengunggah bukti.
- GET tetap read-only dan tidak mengubah status tawaran/pesanan.
- Owner tetap tidak mendapat endpoint tulis.

### Anti-pattern guards

- Jangan mengandalkan tombol tersembunyi sebagai otorisasi.
- Jangan memperbarui tawaran lewat GET atau timer JavaScript.
- Jangan menjeda tenggat pembayaran dengan mengubah `created_at`.

## Phase 5 — Dokumentasi, pengujian, dan review

**How this serves the primary goal:** membuktikan alur tetap benar pada status,
pembayaran, kuota, hak akses, dan permintaan bersamaan sebelum fitur dianggap selesai.

### What to implement

1. Perbarui `docs/api.md`, `docs/database.md`, `docs/peta-kode.md`,
   `docs/aturan-bisnis.md`, README bila job berubah, dan `HANDOFF.md` dalam
   perubahan yang sama. Centang `.opencode/revisi.md` hanya jika file tersedia
   dan item telah diuji.
2. Tambahkan satu uji integrasi terisolasi
   `scripts/test-order-reschedule.php` yang mencakup migrasi, role, status,
   tanggal libur, bukti, accept/reject, kuota, tenggat, dan race accept versus
   perubahan Admin/checkout/job.
3. Perluas uji job di `scripts/test-payment-deadline.php` serta buat/perluas uji
   UI kecil untuk tombol dan kartu Admin/pelanggan. Hindari duplikasi fixture
   yang sudah tersedia di tes pembatalan.
4. Jalankan lint PHP/JavaScript, suite terfokus pembatalan/penolakan/kuota/job/UI,
   migrasi fresh dan upgrade pada PostgreSQL terisolasi, serta `git diff --check`.
5. Karena fitur mengubah pembayaran, checkout/kuota, pembatalan, dan hak akses,
   delegasikan review akhir ke `reviewer` sesuai `AGENTS.md`. Tindak lanjuti semua
   temuan sebelum menandai pekerjaan selesai.

### Documentation references

- Ikuti harness transaksi rollback `scripts/test-order-rejection-reason.php`.
- Ikuti pengujian migrasi/race `scripts/test-order-cancellation.php` dan tenggat
  `scripts/test-payment-deadline.php`.
- Ikuti uji DOM/VM yang sudah ada daripada menambah dependency UI baru.

### Verification checklist

- Seluruh implementasi cocok dengan kontrak dokumentasi dan Allowed APIs Phase 0.
- Grep memastikan tidak ada SQL dengan input pengguna yang digabungkan, GET
  mutatif, status pesanan baru, atau endpoint Owner tulis.
- Pengujian membuktikan kuota tujuan tidak dipesan saat offer dan tidak dapat
  oversold saat accept.
- Reviewer tidak menyisakan temuan yang dapat menyebabkan data/status salah.

### Anti-pattern guards

- Jangan menguji terhadap database aplikasi untuk mutasi.
- Jangan menerapkan migrasi target atau mengaktifkan job tanpa langkah aktivasi
  terpisah dan persetujuan pengguna.
- Jangan menambah dependency, framework, folder MVC, atau notifikasi eksternal.

## Urutan eksekusi setelah plan disetujui

1. Pastikan branch `feature/penolakan-reschedule` masih berbasis `dev` terbaru.
2. Dapatkan persetujuan perubahan skema, lalu kerjakan Phase 1 sampai Phase 4
   berurutan karena kontrak dan lock saling bergantung.
3. Jalankan Phase 5, review, dan perbarui handoff.
4. Commit dengan Conventional Commit; merge kembali melalui alur `dev`. Jangan
   push langsung ke `main` dan jangan membaca/mengubah `.env`.

## Hasil implementasi

- Phase 1: migrasi 022 dan schema membuat satu baris tawaran per pesanan,
  termasuk snapshot tanggal asal.
- Phase 2: Admin dapat membuat/mengganti tawaran hanya untuk pesanan online
  terdampak; detail dan daftar menampilkan status, alasan, batas waktu, serta
  peringatan jeda produksi.
- Phase 3: pelanggan dapat menerima/menolak menggunakan token versi. Accept
  memeriksa kuota ulang dan mempertahankan jam; reject membatalkan dengan metadata.
- Phase 4: aksi Admin/pelanggan yang berbenturan ditolak selama tawaran aktif;
  job unpaid menutup tawaran bersamaan dengan pembatalan.
- Phase 5: dokumentasi API/database/bisnis/peta dan uji UI/job diperbarui.
- Lulus: lint PHP/JS, `git diff --check`, tes UI pelanggan, Admin detail/list,
  dan Dashboard.
- Review sumber akhir: tiga temuan mengenai lock Admin, tanggal tujuan saat
  accept, dan aksi UI ketika tawaran menunggu sudah diperbaiki; reviewer tidak
  menemukan isu tambahan.
- Belum: tes pembayaran PostgreSQL tidak dijalankan karena koneksi uji terisolasi
  belum tersedia di sesi ini. Tidak membaca `.env`, menjalankan migrasi, membuka
  browser login, commit, atau push.
