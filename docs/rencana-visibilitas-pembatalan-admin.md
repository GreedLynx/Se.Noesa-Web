# Rencana visibilitas pembatalan pesanan di Admin

Status: disetujui pengguna untuk implementasi, termasuk tombol **Sudah ditinjau**.

## Tujuan utama

Admin dapat menemukan pesanan yang dibatalkan, mengetahui kapan dan bagaimana
pembatalan terjadi, serta segera memeriksa pembayaran atau menghentikan persiapan
pesanan yang memerlukannya. Pelanggan tetap dapat membatalkan langsung sesuai
aturan yang sudah berlaku.

## Hasil yang diusulkan

| Area | Perilaku yang diinginkan |
|---|---|
| Pesanan Admin | Tab **Dibatalkan** beserta jumlah pesanan, pencarian kode/nama, dan tautan detail. |
| Label status | Teks **Dibatalkan** yang konsisten di desktop, mobile, dan detail. |
| Informasi pembatalan | Waktu WIB, sumber pelanggan/otomatis, dan status sebelum batal bila tercatat. |
| Bukti dan rincian | Bukti transfer, nominal, menu, dan foto yang sudah tersimpan dapat dilihat kembali. |
| Pemberitahuan | Panel pembatalan belum ditinjau di Dashboard; bertahan sampai Admin menandai Sudah ditinjau. |
| Refund | Admin memeriksa pembayaran dan menangani refund penuh di luar aplikasi. |
| Produksi dan kuota | Pesanan batal tetap tidak dihitung dalam rekap produksi atau pemakaian kuota. |

Contoh: pelanggan membatalkan pesanan yang sebelumnya `diproses`. Status langsung
menjadi `dibatalkan`, kuota terlepas melalui perhitungan yang sudah ada, dan menu
pesanan hilang dari rekap produksi. Admin melihat pemberitahuan untuk menghentikan
persiapan serta memeriksa tindak lanjut refund, kemudian membuka detail yang sama.

## Keputusan implementasi yang disetujui

1. **Pencatatan minimal:** sumber dan status sebelum pembatalan, waktu tinjauan,
   serta ID Admin peninjau disimpan pada empat kolom nullable. Waktu pembatalan
   tetap memakai `status_updated_at` yang sudah ada.
2. **Pemberitahuan persisten:** maksimal 2 tautan prioritas di Dashboard, jumlah
   total yang belum ditinjau, dan tautan ke daftar lengkap. Tidak ada batas umur
   tujuh hari: pembatalan bertahan sampai ditinjau, termasuk data lama.
3. **Sudah ditinjau:** berlaku bersama untuk seluruh Admin. Server mencatat waktu
   dan peninjau pertama; permintaan berulang tidak menimpa keduanya. Penanda ini
   berarti pembatalan sudah diperiksa, bukan konfirmasi bahwa refund selesai.
4. Informasi diperbarui saat halaman dibuka/dimuat ulang; belum berupa push realtime.

## Phase 0 — Penemuan dokumentasi dan alur yang sudah tersedia

**Kontribusi pada tujuan utama:** memastikan rencana menggunakan filter, detail,
dan penghitungan yang sudah ada agar informasi pembatalan konsisten.

### Temuan saat perencanaan

- `docs/peta-kode.md`, `docs/aturan-bisnis.md`, `docs/api.md`, dan
  `docs/database.md` menjadi sumber aturan. `.opencode/revisi.md` tidak ditemukan.
- `public/admin/api/orders.php`: filter `tab=dibatalkan` dan `tab=semua` sudah
  mencakup status batal; respons daftar memiliki `counts.cancelled`.
- `public/admin/orders.php`: tab yang terlihat berhenti pada Ditolak; belum ada
  tombol Dibatalkan. `loadOrders()` dalam `public/admin/assets/admin.js` belum
  mengisi jumlah pembatalan, dan `badge()` belum memetakan label status batal.
- `orderDetail()` sudah merender waktu status dan daftar menu, tetapi panel bukti
  transfer hanya dipasang pada `menunggu_validasi`. Pesanan batal memakai tampilan
  lain sehingga buktinya tidak terlihat meskipun datanya masih tersimpan.
- `GET public/admin/api/orders.php?id=...` mengambil `p.*`, rincian menu, dan galeri.
  Halaman `public/admin/order-detail.php` tetap menjadi tujuan semua tautan detail.
- Pembatalan pelanggan di `public/pelanggan/api/orders.php` mengunci baris dan
  memperbarui status/timestamp dengan syarat kepemilikan, status, serta H-2.
- `expire_unpaid_orders(): int` di `app/order-expiration.php` juga menulis status
  batal dan timestamp; hanya status belum dibayar tanpa bukti yang kedaluwarsa.
- Kedua jalur belum menyimpan sumber pembatalan atau status asal. Data lama tidak
  dapat ditentukan sumbernya secara pasti dari status akhirnya.
- `public/admin/api/dashboard.php` menampilkan prioritas validasi, sedangkan
  aktivitas terbaru diurutkan dari waktu pembuatan. Pesanan lama yang baru
  dibatalkan dapat tidak muncul pada daftar aktivitas tersebut.
- `app/daily-quota.php`, VIEW `v_kuota_harian`, dan VIEW
  `v_rekap_produksi_harian` sudah mengecualikan `dibatalkan`.

### API dan pola yang boleh dipakai

| Keperluan | Pola yang tersedia |
|---|---|
| Identitas dan akses | `me()`, `require_role('admin')`, `require_owner()` di `app/bootstrap.php`. |
| Database | `db(): PDO`, `q(string $sql, array $params = []): PDOStatement`; semua input diikat sebagai parameter. |
| Respons | `json($data, int $code = 200): void`; tambahan field harus menjaga respons lama. |
| Daftar dan detail | `GET admin/api/orders.php?tab=dibatalkan&q=...`, `GET ...?id=...`. |
| Pembatalan pelanggan | POST `{action:"cancel_order",id}`; sumber/status asal ditentukan server. |
| Job kedaluwarsa | `expire_unpaid_orders()` melalui CLI terjadwal, bukan GET Dashboard. |
| Tampilan | `badge()`, `dateLabel()`, `esc()`, `loadOrders()`, `orderDetail()`, `dashboard()` di JS Admin. |
| Laporan | VIEW PostgreSQL dan migrasi baru seperti `020_daily_production_report.sql`. |

**Verifikasi:** cocokkan kembali file tersebut sebelum implementasi; pastikan
nomor migrasi terbaru dan kondisi branch karena pekerjaan lain dapat berjalan.

**Batas pola:** jangan membuat endpoint persetujuan pembatalan baru, memanggil job
dari GET, atau menduga sumber pembatalan lama dari adanya bukti pembayaran.

## Phase 1 — Catat sumber dan status sebelum pembatalan

**Kontribusi pada tujuan utama:** memberi dasar yang dapat dipercaya untuk label
dan prioritas pemberitahuan, terutama pesanan yang sudah diproses.

### Pekerjaan yang direncanakan

1. Setelah persetujuan skema, buat migrasi bernomor berikutnya. Repo saat rencana
   dibuat berakhir di 020; verifikasi nomor dan nama lengkap file sebelum membuatnya.
2. Tambahkan empat kolom nullable pada `pesanan`:
   - `sumber_pembatalan`: `pelanggan` atau `otomatis`, dengan CHECK nilai yang sah.
   - `status_sebelum_batal`: salah satu dari tiga status asal yang boleh batal.
   - `pembatalan_ditinjau_at`: waktu tinjauan pertama.
   - `pembatalan_ditinjau_oleh`: ID Admin peninjau, FK users dengan ON DELETE SET NULL.
3. Gunakan `status_updated_at` sebagai waktu pembatalan baru karena `dibatalkan`
   adalah status terminal. Tidak perlu kolom waktu kedua untuk informasi yang sama.
4. Ikuti pola transaksi dan UPDATE bersyarat pada cancel pelanggan: simpan sumber,
   status asal, status baru, dan waktu dalam satu UPDATE yang menang. Status asal
   harus berasal dari baris sebelum UPDATE; jangan mengambilnya dari payload.
5. Ikuti pola UPDATE atomik pada job untuk mencatat sumber `otomatis` dan status
   asal `menunggu_pembayaran`. Jalur dan batas waktu pembatalan tidak berubah.
6. Buat VIEW laporan pembatalan dengan satu baris per pesanan batal untuk Dashboard.
   Sertakan waktu tercatat, metadata, dan keberadaan bukti tanpa menyalin filenya.
7. Pertahankan NULL pada metadata pesanan batal lama; tampilkan “Sumber belum
   tercatat”/“Status sebelumnya belum tercatat”. Jangan mengarang riwayat atau
   mengganti timestamp yang tidak diketahui dengan `created_at`.

**Referensi pola:** `public/pelanggan/api/orders.php`, blok `cancel_order`;
`app/order-expiration.php`, `expire_unpaid_orders()`;
`scripts/migrate.php`; migrasi 016/018 untuk CHECK dan VIEW.

**Verifikasi:** migrasi fresh/upgrade di database uji; cancel dari ketiga status;
job hanya menandai unpaid yang sah; metadata lama tetap NULL; request gagal atau
berulang tidak menimpa metadata. Uji balapan cancel dengan upload/validasi/job.

**Batas pola:** tidak menambahkan tabel notifikasi, kolom saldo/refund, atau jejak
peristiwa umum. Jangan mengubah transaksi pembatalan menjadi persetujuan Admin.

## Phase 2 — Lengkapi tab dan detail pembatalan Admin

**Kontribusi pada tujuan utama:** Admin dapat menemukan pembatalan dan memeriksa
informasinya melalui halaman pesanan yang sudah digunakan setiap hari.

### Pekerjaan yang direncanakan

1. Tambahkan tab Dibatalkan di `public/admin/orders.php`; sambungkan jumlah ke
   `counts.cancelled` dan gunakan `tab=dibatalkan` yang sudah didukung server.
2. Tambahkan label dan warna badge yang terbaca untuk `dibatalkan`, termasuk mobile.
   Semua teks baru memakai satuan porsi; nama kolom lama `pax` tetap mengikuti skema.
3. Urutkan tab batal berdasarkan waktu perubahan status terbaru, lalu ID sebagai
   penentu urutan jika waktunya sama. Pertahankan pencarian kode/nama dan tab lain.
4. Tambahkan field respons yang bersifat tambahan: `cancellation_source`,
   `status_before_cancel`, dan `cancelled_at` ber-offset WIB bila waktu tercatat.
   Untuk data lama tanpa sumber, beri label waktu status tercatat dan jangan
   menjanjikan ketepatan riwayat yang tidak tersedia.
5. Gunakan panel detail yang sama untuk menunjukkan informasi pembatalan, bukti
   transfer/nama pembayar, nominal, menu, serta foto yang pernah tersimpan.
6. Pesanan batal menampilkan checklist read-only. Tombol validasi, penolakan,
   upload, dan penyelesaian tidak tersedia untuk status terminal ini; pemeriksaan
   endpoint yang sudah ada tetap menjadi pengaman utama.
7. Tampilkan petunjuk “Periksa pembayaran yang diterima; refund penuh ditangani
   Admin di luar aplikasi” sesuai aturan. Bukti unggahan tidak diberi label sudah
   dibayar atau sudah direfund tanpa pemeriksaan.
8. Tab Dibatalkan menghitung semua sumber pembatalan. Label sumber membedakan
   pembatalan pelanggan dan kedaluwarsa satu jam; jumlah tab adalah total data,
   bukan hitungan refund yang belum selesai.
9. Tambahkan tombol **Sudah ditinjau** untuk pembatalan yang belum ditinjau dan
   filter tinjauan `review=unreviewed|reviewed` pada tab Dibatalkan. POST
   `{action:"review_cancellation",id}` memeriksa role Admin, ID positif, dan status
   batal di server. Pembacaan GET tidak pernah menandai tinjauan otomatis.
10. Tinjauan tidak mengubah `status_updated_at`, status pesanan, maupun data
    pembayaran. Tampilkan waktu/nama peninjau setelah ditandai; ulang/concurrent
    request mempertahankan peninjau dan waktu pertama. ID hilang menghasilkan
    404, status bukan batal 409, dan ID invalid 422.

**Referensi pola:** `public/admin/api/orders.php`, GET daftar/detail;
`public/admin/assets/admin.js`, `loadOrders()`, `badge()`, `orderDetail()` dan
panel `proof`/`receipt`; `public/admin/assets/admin.css`, `.status-badge`.

**Verifikasi:** tab/count dan pencarian; desktop/mobile; batal dengan/tanpa bukti;
data lama; label WIB; bukti/foto dapat dilihat; semua aksi terlarang tetap ditolak
server. Status `ditolak` tetap dibedakan dari `dibatalkan`.

**Batas pola:** tidak membuat halaman detail kedua, tidak menghapus bukti/order,
dan tidak menyatukan status penolakan Admin dengan pembatalan pelanggan.

## Phase 3 — Tampilkan pemberitahuan pembatalan belum ditinjau

**Kontribusi pada tujuan utama:** Admin dapat melihat pembatalan pesanan yang
baru terjadi, meskipun pesanan dibuat jauh sebelumnya.

### Aturan prioritas yang diusulkan

| Kondisi | Pesan/tindak lanjut |
|---|---|
| Pelanggan membatalkan dari `diproses` | Hentikan persiapan/pengiriman dan periksa tindak lanjut refund. |
| Pelanggan membatalkan dari `menunggu_validasi` atau bukti tersimpan | Periksa pembayaran masuk dan kebutuhan refund. |
| Pelanggan membatalkan dari `menunggu_pembayaran`, tanpa bukti | Informasi pembatalan biasa. |
| Sistem membatalkan unpaid tanpa bukti | Tampilkan “Batas pembayaran habis”; bukan prioritas pemeriksaan pembayaran. |
| Metadata lama tidak lengkap | Tampilkan keterbatasan; bila bukti ada, sarankan pemeriksaan manual. |

### Pekerjaan yang direncanakan

1. Tambahkan objek `cancellations` pada GET Dashboard menggunakan VIEW Phase 1:
   `unreviewed_count`, `urgent_count`, dan maksimal 2 item dengan tautan detail.
   Semua pembatalan yang belum ditinjau dihitung tanpa batas usia.
2. Dahulukan kasus pembayaran/produksi, kemudian waktu
   pembatalan terbaru. Daftar tetap menyediakan tautan ke tab semua pembatalan
   sehingga batas 2 item tidak menyembunyikan akses ke sisanya.
3. Tampilkan panel “Pembatalan belum ditinjau” di Dashboard dengan waktu, sumber,
   petunjuk tindak lanjut, dan tautan detail. Tombol Sudah ditinjau ada di detail
   pesanan agar Admin dapat memeriksa bukti dan informasi terlebih dahulu. Setelah
   POST berhasil, perbarui daftar dan jumlah; data tetap ada dalam riwayat pembatalan.
4. Ringkasan ini diperbarui saat Dashboard dibuka atau dimuat ulang. Tab Dibatalkan
   tetap dapat diakses dari Pesanan. Bila tautan membawa `tab=dibatalkan`, dukung
   parameter URL tersebut pada inisialisasi `ordersPage()` dengan whitelist tab.
5. Jelaskan bahwa panel memuat seluruh pembatalan belum ditinjau, bukan hanya
   tanggal kirim pilihan. Filter tanggal kuota/produksi tidak membatasi panel ini.
6. Penanda tinjauan bukan status refund atau bukti uang dikembalikan. Tidak ada
   uang yang dipindahkan ketika Admin meninjau atau membuka pemberitahuan.

**Referensi pola:** `public/admin/api/dashboard.php`, prioritas/aktivitas;
`public/admin/dashboard.php`; `dashboard()` dan tautan `order-detail.php?id=...`;
pola VIEW pada `database/migrations/020_daily_production_report.sql`.

**Verifikasi:** pembatalan lebih dari tujuh hari tetap muncul sampai ditinjau;
semua sumber dan prioritas; lebih dari 2 kejadian; tautan benar-benar membuka
tab/detail; tinjauan dua Admin bersamaan; data kosong dan respons parsial gagal
dengan pesan yang jelas.

**Batas pola:** tidak memakai `created_at` untuk mengurutkan pembatalan; tidak
menjadikan badge validasi yang sudah ada sebagai badge pembatalan; tidak membuat
polling, push WhatsApp/email, atau pengakuan tinjauan melalui GET.

## Phase 4 — Verifikasi keseluruhan, dokumentasi, dan urutan penerapan

**Kontribusi pada tujuan utama:** membuktikan Admin melihat data yang benar tanpa
mengganggu hak pelanggan, perhitungan produksi, atau proses refund manual.

### Bukti penerimaan yang harus dipenuhi

- [ ] Pembatalan H-2 dari tiga status tetap langsung berlaku dan hanya untuk
  pelanggan pemilik; belum login/role salah/kepemilikan salah tetap ditolak.
- [ ] Metadata hanya tersimpan saat UPDATE pembatalan berhasil; job tidak
  menimpa sumber pembatalan pelanggan yang sudah menang.
- [ ] Admin dapat melihat tab, jumlah, rincian, waktu, bukti, dan foto yang sah.
- [ ] Panel Dashboard menampilkan pembatalan baru dari pesanan lama dan prioritas
  yang sesuai; tidak menyimpulkan pembayaran/refund hanya dari file bukti.
- [ ] `ditolak` dan `dibatalkan` tetap dibedakan. Tidak ada aksi memulihkan status
  terminal atau persetujuan tambahan pembatalan pelanggan.
- [ ] Kuota/sisa dapur kembali tersedia melalui agregasi yang sudah ada, tanpa
  menambah counter. VIEW kuota dan rekap produksi tetap mengecualikan status batal.
- [ ] Owner tetap read-only, pelanggan tetap hanya melihat pesanannya sendiri.
- [ ] Semua perubahan UI diuji desktop/mobile, tombol dapat diakses keyboard,
  pesan gagal terlihat tetap, dan input teks di-escape.
- [ ] Uji respons hilang/kosong/tidak valid tidak membuat seluruh Dashboard kosong.

**Pola pengujian:** perluas `scripts/test-order-cancellation.php` dan
`scripts/test-daily-quota.php` untuk metadata/invarian, serta uji UI Admin pada
`scripts/test-admin-order-detail.cjs`, `scripts/test-admin-dashboard.cjs`, dan
uji daftar khusus bila dibutuhkan. Gunakan database PostgreSQL uji terisolasi
untuk migrasi, batas waktu, dan transaksi bersamaan; jangan menguji mutasi pada
database aplikasi yang dipakai pengguna.

**Dokumentasi:** perbarui `docs/api.md`, `docs/database.md`, `docs/peta-kode.md`,
dan `docs/aturan-bisnis.md` bersama perubahan kode. Catat nomor migrasi yang benar
serta urutan penerapan migrasi sebelum kode yang membaca kolom/VIEW baru.

**Review:** setelah perubahan checkout/pembayaran/kuota/hak akses, delegasikan
review sesuai AGENTS.md. Laporkan pengujian yang benar-benar dijalankan dan batasnya.

**Penerapan:** inspeksi skema dan ledger, uji migrasi pada database uji, siapkan
hasil yang dapat ditinjau, lalu terapkan ke target yang telah disepakati.
Jangan menganggap nama migrasi bernomor sama berarti file sudah diterapkan.

**Batas pola:** tidak membuka `.env`, tidak mengubah migrasi lama yang sudah
dipakai, tidak mengklaim refund selesai, dan tidak mencentang revisi yang belum diuji.

## Batas rencana dan tahap lanjutan

- Pengguna sudah menyetujui implementasi dan meminta tombol Sudah ditinjau.
- Refund penuh tetap dikerjakan Admin di luar aplikasi; status pencairan, unggah
  bukti refund, dan integrasi bank tidak masuk rencana awal.
- Penolakan Admin dengan alasan dan tawaran reschedule merupakan fitur berbeda
  yang sudah dicatat sebelumnya; rencana ini menangani status `dibatalkan`.
- Alasan bebas yang wajib diisi pelanggan belum ditambahkan karena belum diminta;
  evaluasi awal memakai sumber dan status sebelum batal, bukan dugaan alasan pribadi.
- Push realtime dan WhatsApp/email otomatis tetap di luar cakupan tahap ini.

## Hasil implementasi

Discovery, backend, dan UI selesai: migrasi 021, metadata pembatalan, VIEW
pemberitahuan, POST tinjauan idempotent, tab/filter, detail bukti read-only, serta
panel Dashboard. Worker verifikasi melaporkan migrasi 001–021 pada PostgreSQL
terisolasi, uji metadata/role/tinjauan pertama/balapan, dan regresi kuota/job lulus.
Uji JavaScript daftar/detail/Dashboard, pemeriksaan sintaks, dan review kode juga
lulus. Migrasi 021 diterapkan secara terarah pada database aplikasi dan endpoint
Admin diperiksa baca-saja: 47 pesanan tetap, 15 pesanan batal tampil di VIEW dan
Dashboard, tanpa menulis tinjauan atau mengubah status. Tampilan diuji dalam
Chrome memakai markup aktual pada lebar 320, 390, dan 1280 piksel, termasuk kondisi
sudah ditinjau; sesi browser dengan login langsung belum diuji. Atas arahan pengguna,
panel Dashboard diletakkan setelah rekap menu per tanggal kirim dan jumlah item
pemberitahuan dibatasi maksimal dua; jumlah total dan tautan daftar tetap lengkap.

## Prosedur penerapan terarah migrasi 021 (sudah dijalankan)

Prosedur berikut digunakan setelah pengujian PostgreSQL terisolasi dan review
selesai. Hasil deployment dan pemeriksaan endpoint baca-saja dicatat di atas.

1. Gunakan koneksi aplikasi dari bootstrap; jangan membuka atau menampilkan isi
   `.env`. Ambil SQL hanya dari `021_cancellation_admin_review.sql`, bukan seluruh
   direktori migrasi, agar pekerjaan branch lain tidak ikut diterapkan.
2. Mulai transaksi dan ambil `pg_advisory_xact_lock(hashtext(?))` dengan kunci
   `senoesa_schema_migrations`, sama dengan runner migrasi. Beri batas waktu tunggu
   lock dan statement agar penerapan tidak menggantung.
3. Periksa ledger dengan nama file lengkap. Inspeksi empat kolom baru, VIEW
   `v_pembatalan_pesanan`, dan indeks `idx_pesanan_pembatalan_belum_ditinjau`.
   Jika ledger sudah ada dan objek lengkap, periksa jumlah baris VIEW lalu
   laporkan sudah diterapkan tanpa menulis ulang data atau riwayat tinjauan.
4. Bila ledger belum ada, hanya lanjut jika seluruh objek baru belum ada.
   Skema yang sebagian/sudah terpasang tanpa ledger harus menghentikan prosedur
   dan rollback untuk pemeriksaan, bukan ditambal atau dianggap berhasil.
5. Untuk penerapan baru, kunci tabel `pesanan` dalam transaksi agar snapshot data
   lama tidak berubah saat pemeriksaan. Catat jumlah pesanan dan jumlah status
   batal tanpa mencetak data pelanggan, lalu jalankan SQL migrasi 021.
6. Verifikasi empat kolom/VIEW/indeks terbentuk, jumlah pesanan tidak berubah,
   jumlah baris VIEW sama dengan jumlah status batal, dan metadata baru untuk
   seluruh baris lama tetap NULL. Tidak ada backfill sumber/status atau tinjauan.
7. Catat nama file ke `schema_migrations` dalam transaksi yang sama dan commit
   hanya setelah pemeriksaan lulus. Kegagalan apa pun harus rollback. Cetak
   ringkasan nama migrasi, status, dan hitungan tanpa PII/kredensial.
8. Periksa endpoint daftar/detail/Dashboard melalui query baca saja sesudahnya;
   uji mutasi Sudah ditinjau tetap dilakukan di database terisolasi.
