# Rencana perbaikan bug pesanan: tanggal libur dan pembayaran terlambat

Status implementasi: perubahan kode selesai pada branch `fix/fitur-admin`.
Uji fixture PHP/JS, lint, dan review sumber lulus; transaksi PostgreSQL terisolasi,
render browser, status migrasi 022/026, dan scheduler aplikasi belum diverifikasi.
Bagian "Kondisi saat rencana dibuat" di bawah adalah catatan sebelum implementasi.
Pencarian tab Pembayaran terlambat juga menampilkan pesanan batal yang belum
dicatat saat kata kunci diisi, lalu menautkannya ke form pencatatan.

## Primary goal

Ketika Admin menutup tanggal pengiriman yang sudah memiliki pesanan, setiap pesanan aktif yang terdampak terlihat dan dapat ditindaklanjuti. Admin juga dapat menemukan pesanan yang kedaluwarsa karena bukti bayar tidak diunggah, lalu mencatat dan mencari rekonsiliasi pembayaran yang dilaporkan pelanggan.

## Kondisi saat rencana dibuat

- Checkout pelanggan sudah memakai `daily_quota_lock_tables()`, `daily_quota_snapshot()`, dan `daily_quota_validate()`; tanggal libur ditolak server. Pesanan yang dibuat sebelum libur dicatat sengaja tidak diubah otomatis.
- `POST admin/api/holidays.php` menyimpan rentang libur tanpa mencari pesanan terdampak. Halaman `schedules.php` hanya menampilkan rentang dan tombol edit/hapus.
- `POST admin/api/orders.php` sudah memiliki `offer_reschedule`; `POST pelanggan/api/orders.php` sudah memiliki `respond_reschedule`. Detail Admin mempunyai tombol dan dialog tawaran, tetapi `public/admin/assets/admin.js` pada branch aktif belum mempunyai handler tawaran atau penanda statusnya. Detail pelanggan sudah mempunyai respons tawaran.
- Migrasi `022_order_reschedule_offer.sql` ada di sumber, tetapi penerapannya pada database aktif dan uji PostgreSQL terisolasi belum terbukti. Ada file migrasi lain bernomor 022; periksa ledger/nama file sebelum menjalankan migrasi.
- Branch saat ini `feature/perbaikan-ui-pelanggan`. Kerjakan implementasi pada branch fitur dari `dev` setelah mencocokkan perubahan lintas branch; jangan menganggap kode branch reschedule sudah ada di `dev`.
- `.opencode/revisi.md` tidak ada di checkout saat rencana dibuat. Checklist yang tidak tersedia tidak bisa dicentang.
- Tab **Pembayaran terlambat** di `public/admin/orders.php` menyembunyikan pencarian. Cabang `loadOrders()` pada `public/admin/assets/admin.js` memanggil `api/late-payments.php` tanpa `q`; daftar API hanya berisi rekonsiliasi yang sudah dicatat. Form pencatatan di `public/admin/late-payments.php` memakai pilihan `eligible=1` yang hanya mengambil 100 pesanan dibatalkan terbaru tanpa pencarian.
- Pesanan tanpa bukti menjadi `dibatalkan` melalui job `scripts/expire-unpaid-orders.php --apply`, bukan melalui GET Admin. Jadi tidak adanya pesanan pada pilihan pencatatan juga dapat berarti job belum berjalan, migrasi rekonsiliasi 026 belum tersedia, atau pesanan berada di luar batas 100; penyebab pada database aktif belum dibuktikan.

## Phase 0 — Cocokkan kontrak dan reproduksi

**How this serves the primary goal:** memastikan perbaikan diarahkan pada pesanan lama yang terdampak, bukan mengubah checkout yang sudah memblokir tanggal libur.

### Pekerjaan

1. Baca ulang `docs/00-panduan/peta-kode.md`, `docs/01-spesifikasi/aturan-bisnis.md` bagian Tanggal libur, `docs/01-spesifikasi/api.md` bagian Tanggal libur/checkout/tawaran, `docs/01-spesifikasi/database.md` bagian migrasi 019/022, dan handoff sebelum implementasi.
2. Dengan fixture atau PostgreSQL terisolasi, buat pesanan aktif pada tanggal buka, lalu pratinjau dan simpan libur yang mencakup tanggal tersebut. Pastikan konfirmasi merinci pesanan itu dan penyimpanan tidak mengubah statusnya. Ulangi urutan serentak checkout–penyimpanan libur untuk memastikan lock menolak checkout yang kalah balapan.
3. Periksa status migrasi lewat ledger migrasi tanpa membaca `.env` atau mengubah database aplikasi. Identifikasi apakah branch implementasi memiliki skema, API, dan UI tawaran yang sama.

### Verifikasi

- Bukti respons checkout sebelum dan sesudah libur, status pesanan lama, serta daftar file dan migrasi yang benar-benar tersedia.

### Guard

- Jangan membatalkan pesanan lama otomatis atau mengubah kuota tersimpan ketika libur dibuat.

## Phase 1 — Peringatkan pesanan terdampak sebelum menyimpan libur

**How this serves the primary goal:** Admin langsung mengetahui pesanan mana yang perlu dihubungi setelah menyimpan atau mengedit tanggal libur.

### Pekerjaan

1. Sebelum konfirmasi simpan, minta pratinjau pesanan aktif dalam rentang baru dan tampilkan kode, nama, tanggal kirim, serta statusnya di dialog konfirmasi.
2. Nyatakan dengan jelas bahwa pesanan terdampak tidak berubah otomatis dan Admin perlu menindaklanjuti memakai alur pada `docs/02-revisi-tugas/rencana-penolakan-reschedule.md`.
3. Jika pratinjau gagal atau rentang tidak valid, tahan penyimpanan dan tampilkan error di form.

### Verifikasi

- Konfirmasi memuat semua pesanan aktif di dalam rentang; status `ditolak`/`dibatalkan` dikecualikan. Jika tidak ada hasil, dialog menyatakan kondisi kosong.

### Guard

- Jangan mengubah status pesanan atau membuat tawaran otomatis ketika menyimpan libur. Ikuti detail tawaran dalam plan reschedule yang menjadi sumber utama.

## Phase 2 — Sambungkan tindakan tawaran pada detail Admin

**How this serves the primary goal:** Admin dapat menawarkan tanggal pengganti kepada setiap pelanggan yang terdampak tanpa meninggalkan alur aplikasi.

### Pekerjaan

1. Gunakan dialog `public/admin/order-detail.php` dan kontrak `offer_reschedule` di `public/admin/api/orders.php`; tambahkan handler di `orderDetail()` pada `public/admin/assets/admin.js` untuk membuka, mengisi, memvalidasi, mengirim, dan memuat ulang detail. Tampilkan alasan, tanggal asal/usulan, batas berlaku, dan status tawaran yang dikembalikan API.
2. Tampilkan tombol hanya saat `can_offer_reschedule` dari server benar. Selama tawaran aktif, jelaskan bahwa produksi dijeda dan aksi yang bentrok mengikuti guard server. Tampilkan kegagalan API di dialog tanpa menyatakan tawaran berhasil.
3. Pertahankan respons pelanggan di `public/pelanggan/assets/customer.js`. Periksa integrasi UI/API saat merge dari `dev`, karena riwayat handoff mencatat handler Admin pernah hilang pada resolusi konflik.

### Verifikasi

- Status eligible dan tidak eligible, alasan kosong/panjang, tanggal tujuan libur/penuh, tawaran berhasil/diganti, dan kegagalan jaringan. Pelanggan dapat melihat versi tawaran terbaru.

### Guard

- Jangan percaya status, kepemilikan, kuota, atau tanggal dari browser; API tetap sumber validasi. Jangan meminta pelanggan membayar ulang jika pembayaran sudah valid.

## Phase 3 — Uji alur penuh dan rapikan kontrak

**How this serves the primary goal:** perubahan tanggal, kuota, pembayaran, dan hak akses tetap benar dalam kondisi normal maupun balapan.

### Pekerjaan

1. Jalankan uji PHP/Node yang relevan: `scripts/test-holidays.cjs`, `scripts/test-admin-order-detail.cjs`, `scripts/test-order-cancellation-ui.cjs`, dan `scripts/test-daily-quota.php`; tambahkan uji kecil untuk pratinjau pesanan dan handler tawaran. Uji PostgreSQL terisolasi untuk migrasi 022, role, dua Admin/tawaran, accept ketika tanggal tujuan berubah libur atau penuh, reject, dan expiry unpaid. Terapkan migrasi ke database aplikasi hanya setelah prasyarat dan persetujuan skema tim terpenuhi.
2. Periksa halaman Jadwal, Detail Admin, dan Detail Riwayat Pelanggan pada desktop dan mobile; verifikasi status sesudah simpan libur, tawaran, terima/tolak, dan refresh.
3. Setelah kode berubah, sinkronkan `docs/00-panduan/peta-kode.md`, `docs/01-spesifikasi/api.md`, `docs/01-spesifikasi/database.md`, aturan bisnis bila perilaku berubah, dan `docs/00-panduan/HANDOFF.md`. Minta review `reviewer` untuk checkout, pembayaran, kuota, dan hak akses. Centang `.opencode/revisi.md` hanya bila file tersedia dan fitur lulus uji.

### Verifikasi

- Lint PHP/JS, uji transaksi dan UI terarah, `git diff --check`, serta bukti review dan status migrasi dicatat. Tanpa hasil PostgreSQL, fitur belum dianggap selesai.

### Guard

- Jangan mengaktifkan refund otomatis, notifikasi eksternal, atau mengubah Owner yang read-only.

## Phase 4 — Reproduksi dua jalur pembayaran terlambat

**How this serves the primary goal:** membedakan pesanan batal yang belum dicatat dari rekonsiliasi yang sudah dicatat, sehingga pencarian tidak menyembunyikan kasus pelanggan yang sudah transfer.

### Pekerjaan

1. Baca `docs/01-spesifikasi/aturan-bisnis.md` bagian Pembatalan, `docs/01-spesifikasi/api.md` bagian tenggat bukti, `docs/01-spesifikasi/database.md` bagian pembatalan, `README.md` bagian scheduler, dan implementasi `app/order-expiration.php`, `public/admin/api/late-payments.php`, serta `app/late-payment.php`.
2. Pada PostgreSQL terisolasi, buat pesanan `menunggu_pembayaran` tanpa bukti; lewatkan satu jam dan jalankan job `--apply`. Pastikan status menjadi `dibatalkan` dengan sumber otomatis. Periksa pilihan `eligible=1`, tab Pembayaran terlambat sebelum pencatatan, dan tab setelah Admin mencatat bukti. Uji juga pesanan lama di luar 100 baris terbaru.
3. Periksa secara baca saja apakah scheduler dan migrasi `026_late_payment_reconciliation.sql` aktif pada lingkungan yang sedang dipakai. Jangan menyimpulkan uang sudah diterima hanya dari status pesanan atau klaim pelanggan; verifikasi transaksi tetap tugas Admin.

### Verifikasi

- Catat kondisi dan respons API pada tiap tahap: sebelum kedaluwarsa, sesudah job, sesudah pencatatan, serta hasil pencarian nama/kode. Bedakan penyebab data belum ada dari pencarian yang tidak diterapkan.

### Guard

- Jangan menjalankan job `--apply` pada database aplikasi sebagai bagian diagnosis; gunakan dry-run/inventaris lebih dulu sesuai README.

## Phase 5 — Buat pesanan dan rekonsiliasi terlambat dapat dicari

**How this serves the primary goal:** Admin dapat menemukan pesanan yang perlu dicatat dan catatan yang sudah dibuat dengan nama pelanggan atau kode pesanan.

### Pekerjaan

1. Pada tab Pembayaran terlambat di `public/admin/orders.php`, tampilkan kembali input pencarian. Kirim `q` dari `loadOrders()` ke GET `public/admin/api/late-payments.php`; terapkan filter nama/kode dengan parameter SQL terikat pada daftar `late_payment_list()` di `app/late-payment.php`. Pertahankan daftar lengkap saat `q` kosong.
2. Pada form `public/admin/late-payments.php`, sediakan pencarian nama/kode untuk calon pesanan batal dan terapkan `q` pada GET `eligible=1`. Cari di database sebelum batas hasil, bukan pada 100 opsi yang sudah dipotong. Pertahankan syarat belum punya rekonsiliasi dan status `dibatalkan`; perjelas label bahwa transfer belum terverifikasi.
3. Gunakan hasil pencarian dan keadaan kosong yang berbeda untuk “tidak ditemukan” dan “belum ada catatan”. Setelah pencatatan berhasil, muat ulang pilihan dan daftar; pesanan tidak boleh dicatat dua kali. Ikuti pola `loadEligible()`/`loadList()` yang ada, tanpa skema atau dependency baru.

### Verifikasi

- Nama parsial/kode tepat, hasil kosong, karakter khusus pada query, lebih dari 100 pesanan batal, status lain, rekonsiliasi sudah tercatat, serta pencarian tab setelah refresh. Uji role Admin dan query parameterized.

### Guard

- Pencarian tidak mengubah status pesanan, bukti, kuota, atau audit rekonsiliasi. Jangan menghidupkan kembali pesanan lama atau menandai pembayaran terverifikasi otomatis.

## Phase 6 — Verifikasi akhir kedua bug

**How this serves the primary goal:** kedua alur dapat digunakan bersama tanpa merusak checkout, kedaluwarsa pembayaran, atau tindak lanjut Admin.

### Pekerjaan

1. Jalankan `scripts/test-late-payment-reconciliation.php` di PostgreSQL terisolasi, `scripts/test-payment-deadline.php` melalui harness yang aman, serta uji UI tab dan form yang mencakup pencarian. Uji ulang suite pada Phase 3 untuk tanggal libur dan tawaran jadwal.
2. Periksa halaman Admin pada desktop/mobile; rekam hasil nyata, status scheduler, dan status migrasi 022/026. Perbarui peta kode, API, database, aturan bisnis bila perlu, dan handoff setelah implementasi. Mintakan review `reviewer` karena pekerjaan menyentuh pembayaran, kuota, dan hak akses.

### Verifikasi

- Uji dan review terarah lulus, `git diff --check` bersih, dan kasus transfer tanpa upload dapat ditemukan untuk tindak lanjut tanpa mengklaim verifikasi pembayaran otomatis.

### Guard

- Jangan menganggap hasil uji fixture sebagai bukti scheduler produksi berjalan atau transfer sungguhan sudah diterima.

## Batas dan keputusan yang masih perlu dipastikan

- Jika tidak ada tanggal pengganti yang layak atau pelanggan tidak merespons hingga tawaran habis, tindakan akhir Admin perlu keputusan bisnis tersendiri. Rencana ini memakai alur yang sudah disepakati: satu tanggal usulan, pelanggan menerima atau menolak, refund pembayaran valid dilakukan manual di luar aplikasi.
- Bug berikutnya dapat ditambahkan sebagai bagian terpisah setelah akar masalah, file terdampak, dan kriteria uji diketahui; jangan menggabungkannya ke perubahan tanggal libur tanpa hubungan langsung.
- Rekonsiliasi hanya mencatat pembayaran yang dilaporkan dan bukti yang dikumpulkan Admin; pesanan lama tetap dibatalkan, sementara verifikasi transaksi serta refund/pesanan baru mengikuti alur manual yang sudah ada.
