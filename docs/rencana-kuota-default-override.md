# Rencana Kuota Default Produk dan Override Tanggal

## Primary goal

Admin dapat menetapkan kuota default ketika membuat atau mengedit produk, lalu
memberi kuota sementara untuk rentang tanggal pengiriman tertentu. Pelanggan,
Admin, dan Owner selalu melihat kuota efektif yang sama; pesanan lama tetap
diproses ketika kuota diturunkan.

## Keputusan yang sudah dikunci

- Kuota default disimpan pada `produk.kuota_harian` dan berlaku per hari
  pengiriman ketika tanggal itu tidak memiliki override.
- Override memakai rentang tanggal inklusif: tanggal mulai dan tanggal selesai
  sama-sama termasuk.
- Override yang disimpan paling baru menimpa tanggal yang bertumpuk.
- Override disimpan sebagai satu baris efektif per produk per tanggal di tabel
  `kuota_harian`. Input rentang dimaterialisasi menjadi baris tanggal dengan
  UPSERT; riwayat override lama tidak dipertahankan.
- Kuota default `0` membuat produk tidak tersedia kecuali tanggal tersebut
  memiliki override positif.
- Override `0` membuat produk tidak tersedia pada tanggal tersebut.
- Penghapusan override mengembalikan tanggal ke kuota default.
- Penurunan kuota tidak membatalkan pesanan lama. Pemakaian lama tetap dihitung,
  sisa minimal `0`, dan checkout baru ditolak bila melampaui kuota efektif atau
  batas dapur 80%.
- Untuk produk lama, kuota default diisi dari baris `kuota_harian` dengan tanggal
  paling besar; jika produk belum memiliki override, nilai
  `produk.kuota_harian` yang sekarang dipertahankan. Jika tanggal sama, `id`
  paling besar menjadi pemutus urutan yang deterministik.
- Penutupan tanggal pengiriman secara global tidak termasuk rencana ini dan akan
  dirancang terpisah.
- Owner tetap read-only.

## Arsitektur minimum

- Tidak membuat tabel rentang baru dan tidak menambah library.
- `produk.kuota_harian` menjadi sumber default; kolom lama `produk.kuota` dan
  `produk.terpakai` tidak dipakai kembali dan tidak dihapus.
- `kuota_harian(produk_id, tanggal, kuota)` tetap menjadi sumber override tanggal.
- Kuota efektif adalah `COALESCE(override_tanggal, kuota_default_produk)`.
- Respons produk membedakan `kuota_default` dari `kuota` efektif. Alias respons
  lama `kuota_harian` dipertahankan sebagai kuota efektif sampai seluruh caller
  internal tidak lagi bergantung padanya.
- Rentang disimpan dengan satu perintah SQL set-based memakai
  `generate_series(..., interval '1 day')` dan `ON CONFLICT`, bukan loop query PHP.
- Demi membatasi input Admin yang salah, satu permintaan rentang dibatasi maksimal
  366 tanggal inklusif; rentang lebih panjang dapat disimpan dalam beberapa
  permintaan tanpa membatasi tanggal pengiriman aplikasi.

## Phase 0 — Kunci dokumentasi dan persetujuan rancangan

**How this serves the primary goal:** fase ini memastikan implementasi memakai
aturan kuota yang sudah disepakati dan tidak mempertahankan kontrak lama yang
bertentangan.

### What to implement

1. Baca berurutan `docs/peta-kode.md`, `docs/aturan-bisnis.md`, `docs/api.md`,
   `docs/database.md`, lalu `.opencode/revisi.md` bila tersedia.
2. Gunakan bagian “Lanjutan Plan 2: Tanggal pengiriman dan kuota produk” hanya
   sebagai sumber keputusan bisnis; jangan mengubah atau memasukkan
   `.opencode/docs/` ke commit.
3. Minta persetujuan tim atas rancangan minimum di atas sebelum membuat migrasi.
   Setelah disetujui, gunakan nomor migrasi berikutnya; pada saat rencana ini
   ditulis kandidatnya `015`.
4. Catat kontrak istilah berikut sebelum coding:
   - `kuota_default`: kapasitas produk pada tanggal tanpa override;
   - `kuota`: kapasitas efektif untuk tanggal yang diminta;
   - `terpakai`: jumlah porsi pesanan non-`ditolak` pada tanggal itu;
   - `sisa`: `max(0, kuota - terpakai)`.

### Documentation references and allowed APIs

- Pakai `q(string $sql, array $params = []): PDOStatement` dari
  `app/bootstrap.php:38` untuk seluruh nilai dinamis.
- Pakai `body()`, `json()`, dan `require_role('admin')` dari
  `app/bootstrap.php:47-77`; jangan membuat wrapper API baru.
- Ikuti transaksi, advisory lock, dan pencatatan nama file dari
  `scripts/migrate.php:18-68`.
- Ikuti pola UPSERT dan table lock yang sudah ada di
  `public/admin/api/kuota-harian.php:24-80`.
- Ikuti helper frontend `api(url, options)` di
  `public/admin/assets/admin.js:12-29`.
- PostgreSQL yang diizinkan untuk rancangan ini: `COALESCE`, `DISTINCT ON`,
  `generate_series`, `ON CONFLICT`, VIEW, prepared statement, dan transaksi.

### Verification checklist

- [ ] Tim menyetujui penggunaan kolom default yang sudah ada dan materialisasi
      rentang menjadi baris tanggal.
- [ ] Tidak ada keputusan aktif di `.opencode/revisi.md` yang bertentangan.
- [ ] Semua field respons memiliki arti tunggal yang tertulis di `docs/api.md`.
- [ ] Nomor migrasi ditentukan kembali dari daftar file saat implementasi dimulai.

### Anti-pattern guards

- Jangan membaca atau mengubah `.env`.
- Jangan menghidupkan kembali counter `produk.terpakai`.
- Jangan membuat tabel fisik untuk laporan.
- Jangan memasukkan penutupan tanggal global ke perubahan ini.
- Jangan mengubah UI Admin/Owner di luar kebutuhan kuota; desain kedua role belum
  final.

## Phase 1 — Migrasikan default dan selaraskan VIEW

**How this serves the primary goal:** fase ini memberi setiap produk lama sumber
kuota default yang deterministik dan membuat laporan memakai aturan efektif yang
sama dengan checkout.

### What to implement

1. Buat satu migrasi baru setelah persetujuan tim. Gunakan pola migrasi biasa,
   tanpa `BEGIN`/`COMMIT` internal karena runner sudah membungkus setiap file dalam
   transaksi.
2. Isi `produk.kuota_harian` dari override bertanggal paling besar per produk:
   gunakan `DISTINCT ON (produk_id)` dengan urutan
   `produk_id, tanggal DESC, id DESC`. Produk tanpa override tidak disentuh.
3. Tambahkan CHECK `produk.kuota_harian >= 0` bila belum tersedia.
4. Perbarui `v_kuota_harian` agar:
   - override tanggal dipilih bila ada, termasuk nilai `0`;
   - bila hanya ada pemakaian tanpa override, kuota berasal dari default produk;
   - transaksi selain `ditolak` tetap dihitung dalam zona `Asia/Jakarta`;
   - `sisa` tetap minimal `0`.
5. Selaraskan baseline `database/schema.sql` dengan kolom
   `produk.kuota_harian INT NOT NULL DEFAULT 0 CHECK (...)`. Pertahankan migrasi
   009 dengan `ADD COLUMN IF NOT EXISTS` agar instalasi lama dan baru tetap aman.
6. Jangan menghapus override lama: baris itu masih berlaku pada tanggalnya setelah
   default produk diisi.

### Documentation references

- Salin pola pemilihan/penggabungan pemakaian dari
  `database/migrations/011_daily_quota_report.sql:4-18`.
- Salin nama kolom default dari
  `database/migrations/009_produk_kuota_harian.sql:1-2`.
- Salin pola CHECK bernama dari
  `database/migrations/014_numeric_check_constraints.sql`.
- Salin aturan FULL JOIN yang mempertahankan transaksi tanpa konfigurasi dari
  `docs/database.md` bagian “Plan 1 — sumber kuota aktif”.

### Verification checklist

- [ ] Preflight read-only menghitung produk dengan default negatif dan memastikan
      tidak ada nilai yang akan melanggar CHECK.
- [ ] Dry-run migrasi dalam transaksi lalu `ROLLBACK` berhasil.
- [ ] Produk dengan override memiliki default dari tanggal terbesar; tie memakai
      `id` terbesar.
- [ ] Produk tanpa override mempertahankan default sebelumnya.
- [ ] Override lama tidak berubah atau terhapus.
- [ ] Definisi VIEW valid dan `convalidated` untuk CHECK bernilai benar.

### Anti-pattern guards

- Jangan memilih “baris terakhir diedit”; tabel lama tidak memiliki timestamp
  perubahan. Gunakan tanggal terbesar dan `id` sebagai tie-breaker.
- Jangan menyalin nilai dari kolom legacy `produk.kuota`.
- Jangan menebak default positif untuk produk tanpa override.
- Jangan menjalankan migrasi produksi sebelum preflight dan persetujuan tim.

## Phase 2 — Satukan perhitungan kuota efektif

**How this serves the primary goal:** fase ini memastikan katalog, checkout,
Admin, dan Owner menghitung default, override, pemakaian, dan sisa dengan rumus
yang identik.

### What to implement

1. Ubah hanya titik bersama `daily_quota_snapshot()` di
   `app/daily-quota.php`:
   - tambahkan `pr.kuota_harian AS kuota_default` pada field produk;
   - query checkout langsung memakai
     `COALESCE(k.kuota, pr.kuota_harian)`;
   - query laporan memakai nilai VIEW bila ada dan fallback default bila VIEW
     tidak mempunyai baris untuk produk/tanggal tersebut;
   - hitung `sisa` dari kuota efektif dan pemakaian, minimal `0`;
   - hitung batas dapur 80% dari jumlah kuota efektif seluruh produk.
2. Pertahankan `kuota` dan `kuota_harian` sebagai alias kuota efektif agar caller
   yang ada tidak putus. Tambahkan `kuota_default` secara eksplisit.
3. Pertahankan `daily_quota_validate()` sebagai satu gerbang checkout:
   kuota efektif `<= 0` tidak tersedia, pesanan melebihi sisa ditolak, minimum
   produk tetap sedikitnya 4 porsi, dan batas dapur tetap diperiksa terakhir.
4. Pastikan pemakaian produk yang kemudian dinonaktifkan tetap masuk
   `kitchenUsed`; hanya penerimaan pesanan baru yang berubah.

### Documentation references

- Salin struktur dua jalur query dan bentuk snapshot dari
  `app/daily-quota.php:3-63`.
- Salin urutan validasi checkout dari `app/daily-quota.php:66-89`.
- Salin rentang hari Asia/Jakarta dari parameter query langsung pada
  `app/daily-quota.php:35-43`.

### Verification checklist

- [ ] Tanpa override: kuota efektif sama dengan `kuota_default`.
- [ ] Override positif: kuota efektif sama dengan override.
- [ ] Override `0`: produk ditolak sebagai tidak tersedia.
- [ ] Hapus override: kuota efektif kembali ke default.
- [ ] Pemakaian di atas kuota baru menghasilkan `sisa = 0`, bukan negatif.
- [ ] Snapshot laporan dan snapshot checkout identik untuk fixture yang sama.
- [ ] Batas dapur memakai 80% dari kuota efektif, bukan default mentah.

### Anti-pattern guards

- Jangan menduplikasi rumus fallback di setiap endpoint.
- Jangan memakai operator truthy untuk override karena nilai `0` adalah override
  yang valid; gunakan keberadaan baris/`COALESCE`.
- Jangan mengurangi kuota tersimpan saat checkout atau penolakan.
- Jangan mengubah aturan status transaksi selain pengecualian `ditolak`.

## Phase 3 — Simpan kuota default bersama produk

**How this serves the primary goal:** fase ini membuat produk baru langsung punya
aturan kapasitas yang jelas dan memungkinkan Admin memperbarui default tanpa
membuat override setiap hari.

### What to implement

1. Tambahkan field wajib “Kuota default per hari (porsi)” pada form produk di
   `public/admin/products.php`. Nilai minimal `0`; nilai awal produk baru `0` agar
   ketersediaan selalu merupakan keputusan Admin.
2. Di `public/admin/assets/admin.js`:
   - isi field dari `p.kuota_default` ketika edit;
   - biarkan FormData mengirim field bersama produk;
   - ubah bantuan teks agar membedakan kuota default dan kuota tanggal khusus.
3. Di `public/admin/api/products.php`:
   - terima field canonical `kuota_default` dan alias lama `kuota_harian` bila
     masih dikirim caller;
   - validasi integer `0..2147483647`;
   - simpan ke `produk.kuota_harian` pada INSERT dan UPDATE;
   - respons GET menyertakan `kuota_default` serta kuota efektif tanggal filter.
4. Pertahankan upload gambar, validasi kategori/harga/minimum, dan error handling
   yang ada. Jangan membuat endpoint produk baru.
5. Pastikan update default terserialisasi terhadap checkout melalui lock tabel
   `produk` yang sudah diambil checkout; verifikasi perilaku lock sebelum
   menambah lock eksplisit baru.

### Documentation references

- Salin pola field Bootstrap/form yang sudah ada di
  `public/admin/products.php:95-121`.
- Salin validasi integer dan INSERT/UPDATE produk dari
  `public/admin/api/products.php:53-65,120-136`.
- Salin pola pengisian dialog dan submit FormData dari
  `public/admin/assets/admin.js:549-574,630-676`.

### Verification checklist

- [ ] Produk baru dengan default positif langsung muncul untuk tanggal tanpa
      override.
- [ ] Produk baru dengan default `0` tidak muncul sampai ada override positif.
- [ ] Edit default memengaruhi tanggal tanpa override dan tidak mengubah tanggal
      yang memiliki override.
- [ ] Nilai kosong, pecahan, negatif, dan di atas batas INT ditolak HTTP 422.
- [ ] Upload gambar baru/lama tetap bekerja seperti sebelumnya.
- [ ] Owner tidak memperoleh endpoint tulis baru.

### Anti-pattern guards

- Jangan memakai label “stok”; gunakan “kuota default” dan “porsi”.
- Jangan memakai field respons efektif sebagai nilai awal default.
- Jangan menerima nilai hanya dari validasi HTML; validasi ulang di server.
- Jangan menyimpan default ke tabel `kuota_harian`.

## Phase 4 — Tambahkan override rentang inklusif

**How this serves the primary goal:** fase ini memungkinkan Admin mengatur hari
khusus secara efisien, dengan hasil overlap dan penghapusan yang dapat diprediksi.

### What to implement

1. Ubah editor kuota tanggal khusus di `public/admin/products.php` menjadi:
   `tanggal_mulai`, `tanggal_selesai`, dan `kuota`. Default tanggal selesai sama
   dengan tanggal mulai.
2. Di `public/admin/api/kuota-harian.php`:
   - tetap wajib `require_role('admin')`;
   - terima `tanggal_mulai`, `tanggal_selesai`, dan `kuota`;
   - untuk kompatibilitas, `tanggal` tunggal dinormalisasi menjadi mulai=selesai;
   - validasi format dan tanggal nyata, selesai >= mulai, maksimal 366 tanggal
     inklusif, produk ada, serta kuota `0..2147483647`;
   - dalam transaksi yang mengunci `kuota_harian`, lakukan satu prepared
     `INSERT ... SELECT generate_series(...) ... ON CONFLICT ... DO UPDATE`;
   - respons menyebut jumlah tanggal yang tersimpan.
3. Tambahkan aksi “Kembali ke default” untuk satu tanggal atau rentang. Endpoint
   menghapus baris produk dalam rentang inklusif di transaksi/lock yang sama.
4. Pertahankan GET daftar override sebagai baris tanggal efektif, urut terbaru.
   Jangan menambah histori atau pengelompokan rentang pada tahap ini.
5. Setelah simpan/reset, muat ulang daftar override dan daftar produk agar kuota
   efektif tanggal filter langsung berubah.
6. Jelaskan di UI bahwa penyimpanan rentang baru menimpa tanggal yang bertumpuk.

### Documentation references

- Salin validasi tanggal, validasi kuota, transaksi, lock, UPSERT, dan GET list
  dari `public/admin/api/kuota-harian.php:24-110`.
- Salin renderer dan handler editor yang ada dari
  `public/admin/assets/admin.js:512-548,601-620`.
- Salin pola `api()` JSON dari `public/admin/assets/admin.js:12-29`.

### Verification checklist

- [ ] Rentang 20–25 membuat enam override tanggal.
- [ ] Setelah 20–25=30 lalu 23–28=40, tanggal 20–22 bernilai 30 dan 23–28
      bernilai 40.
- [ ] Tanggal 19 dan 29 memakai default.
- [ ] Rentang satu hari bekerja.
- [ ] Override `0` menutup produk hanya pada rentang itu.
- [ ] Reset sebagian rentang menghapus hanya tanggal yang dipilih dan mengembalikan
      tanggal tersebut ke default.
- [ ] Rentang terbalik, tanggal palsu, lebih dari 366 hari, kuota invalid, dan
      produk asing ditolak tanpa perubahan parsial.
- [ ] Dua request bersamaan tidak menghasilkan baris duplikat atau partial range.

### Anti-pattern guards

- Jangan menjalankan satu query per tanggal dari PHP.
- Jangan menggabungkan tanggal/kuota langsung ke string SQL.
- Jangan memperlakukan `0` sebagai “tidak ada override”.
- Jangan menambahkan aksi tulis pada UI/API Owner.
- Jangan menyimpan baris rentang baru; tabel tetap satu baris per tanggal.

## Phase 5 — Pertahankan seluruh consumer pada kuota efektif

**How this serves the primary goal:** fase ini memastikan pelanggan tidak dapat
melewati aturan baru dan semua role membaca angka yang sama tanpa memperluas hak
akses.

### What to implement

1. `public/pelanggan/api/catalog.php` tetap memfilter produk berdasarkan kuota
   efektif positif dari snapshot; tanggal H+7 dan parameter `tgl_kirim` tidak
   berubah.
2. `public/pelanggan/api/orders.php` tetap mengambil lock `produk` dan
   `kuota_harian`, lalu snapshot dan validasi di transaksi yang sama. Jangan
   memindahkan validasi kuota ke frontend.
3. Admin `products.php`, `dashboard.php`, dan `orders.php` tetap memakai snapshot
   laporan untuk tanggal filter; kategori kritis dihitung dari kuota efektif.
4. Owner `products.php` dan `orders.php` tetap GET-only dan memakai snapshot yang
   sama. Respons boleh menampilkan default dan efektif, tetapi tidak menambah
   kontrol edit.
5. `customer.js` tetap mempertahankan keranjang saat tanggal berubah; item yang
   tidak tersedia harus ditandai/dihapus sesuai perilaku yang sudah ada, bukan
   diselundupkan ke checkout.

### Documentation references

- Caller pelanggan: `public/pelanggan/api/catalog.php:7-31` dan
  `public/pelanggan/api/orders.php:201-241`.
- Caller Admin: `public/admin/api/products.php:139-155` dan
  `public/admin/api/dashboard.php:14-25`.
- Caller Owner read-only: `public/owner/api/products.php:4-37`.
- UI katalog: `public/pelanggan/assets/customer.js` pada `loadCatalog()` dan
  render sisa/minimum produk.

### Verification checklist

- [ ] Katalog dua tanggal berbeda menampilkan kuota default/override yang benar.
- [ ] Checkout mengulang validasi server setelah Admin menurunkan kuota.
- [ ] Pesanan lama tetap ada dan tetap masuk pemakaian setelah kuota diturunkan
      atau menjadi `0`.
- [ ] Checkout baru ditolak ketika sisa produk atau batas dapur efektif habis.
- [ ] Penolakan pesanan memulihkan sisa tanpa mengubah default/override.
- [ ] Admin dan Owner menampilkan kapasitas, pemakaian, sisa, dan produk kritis
      yang sama untuk tanggal yang sama.
- [ ] POST/PUT/DELETE terhadap endpoint Owner tetap tidak tersedia.

### Anti-pattern guards

- Jangan percaya kuota yang dikirim frontend.
- Jangan membatalkan atau mengubah status pesanan lama otomatis.
- Jangan mengubah batas H+7, status pesanan, atau aturan minimum 4 porsi.
- Jangan membuat rumus kapasitas terpisah untuk Owner atau dashboard.

## Phase 6 — Selaraskan dokumentasi dan peta kode

**How this serves the primary goal:** fase ini menjadikan aturan baru dapat
ditemukan dan mencegah implementasi berikutnya kembali memakai aturan no-fallback.

### What to implement

1. `docs/aturan-bisnis.md`: jadikan default+override sebagai aturan aktif;
   jelaskan inklusivitas, overlap terbaru, nilai `0`, penghapusan, pesanan lama,
   dan batas dapur efektif.
2. `docs/api.md`: dokumentasikan field produk default/effective, kontrak POST
   produk, save/reset rentang, response count, error 422, dan GET override.
3. `docs/database.md`: dokumentasikan sumber default, override exact-date,
   migrasi data produk lama, VIEW, constraint, lock, dan rumus efektif.
4. `docs/peta-kode.md`: petakan seluruh file yang berubah dan test terkait.
5. Perbaiki bagian legacy yang bertentangan; jangan membiarkan dua bagian sama-sama
   disebut kontrak aktif.
6. README hanya diubah bila cara menjalankan migrasi/test berubah; jangan menambah
   catatan tanpa perubahan nyata.

### Documentation references

- Ganti pernyataan no-fallback pada `docs/aturan-bisnis.md` bagian Plan 1.
- Ganti kontrak aktif pada `docs/api.md` bagian Plan 1 dan tandai bagian lama
  sebagai riwayat secara konsisten.
- Perbarui `docs/database.md` bagian sumber kuota aktif, bukan hanya menambah
  paragraf baru yang bertentangan.
- Ikuti format tabel file di `docs/peta-kode.md`.

### Verification checklist

- [ ] Pencarian `no fallback`/`tidak ada fallback` tidak menyisakan pernyataan
      aktif yang salah.
- [ ] Semua nama request/response di dokumentasi sama dengan implementasi.
- [ ] Seluruh dokumentasi memakai istilah porsi.
- [ ] Tidak ada `.env`, upload pengguna, atau `.opencode/docs/` dalam diff.

### Anti-pattern guards

- Jangan menyalin dokumentasi lama ke bagian baru tanpa menghapus kontradiksi.
- Jangan menyatakan penutupan tanggal sudah tersedia.
- Jangan mencentang `.opencode/revisi.md` sebelum pengujian; file saat discovery
  memang tidak tersedia.

## Phase 7 — Verifikasi end-to-end dan review independen

**How this serves the primary goal:** fase ini membuktikan fallback, override,
overlap, transaksi, dan hak akses bekerja sebagai satu alur sebelum perubahan
dianggap selesai.

### What to implement

1. Perluas `scripts/test-daily-quota.php` dengan fixture default, override positif,
   override `0`, reset ke default, pemakaian di atas kuota baru, dan kesetaraan
   query laporan/checkout.
2. Tambahkan pengujian range API/helper yang membuktikan batas inklusif, overlap
   terbaru, satu hari, reset sebagian, input invalid, dan rollback atomik.
3. Perluas `scripts/test-quota-ui.cjs` untuk memastikan field default dikirim,
   rentang memakai nama field yang benar, dan helper filter tanggal tidak
   menyisipkan `tgl_kirim` ke POST.
4. Jalankan lint seluruh PHP yang berubah, `node --check` untuk JavaScript,
   `git diff --check`, test kuota PHP, dan test UI kuota.
5. Jalankan preflight database read-only, dry-run migrasi dengan rollback, lalu
   `scripts/migrate.php` hanya setelah seluruh pemeriksaan lulus dan ada izin
   menerapkan DDL.
6. Verifikasi browser/API untuk:
   - tambah produk default positif dan `0`;
   - edit default;
   - save/reset rentang dan overlap;
   - katalog dan checkout pada tanggal default, override, dan override `0`;
   - Admin serta Owner pada tanggal yang sama;
   - Owner tidak memiliki aksi tulis.
7. Sesuai `AGENTS.md`, delegasikan review read-only ke `.codex` reviewer setelah
   diff selesai. Berikan diff, halaman terdampak, hasil test, dan kriteria di atas;
   agent utama menindaklanjuti semua temuan sebelum commit.

### Documentation references

- Salin pola TEMP table, VIEW pembanding, rollback, dan concurrency dari
  `scripts/test-daily-quota.php:31-100`.
- Salin pola assert request tanggal dari `scripts/test-quota-ui.cjs`.
- Salin kontrak reviewer dari `.codex/agents/reviewer.toml`.

### Verification checklist

- [ ] Semua test otomatis lulus dan outputnya disimpan dalam ringkasan kerja.
- [ ] Tidak ada data uji tertinggal karena test menggunakan TEMP table/rollback.
- [ ] Delapan skenario bisnis utama di atas dibuktikan dengan expected/actual.
- [ ] Reviewer tidak memiliki temuan terbuka P0/P1/P2.
- [ ] Diff hanya berisi file yang masuk cakupan.
- [ ] Commit memakai Conventional Commit, rekomendasi:
      `feat: tambahkan kuota default dan override tanggal produk`.

### Anti-pattern guards

- Jangan mengandalkan test frontend saja untuk aturan kuota.
- Jangan melaporkan skenario sebagai lulus jika tidak benar-benar dijalankan.
- Jangan menjalankan DDL tanpa preflight dan persetujuan eksplisit.
- Jangan commit hasil upload, `.env`, atau `.opencode/docs/`.

## Deferred / not now

- Penutupan tanggal atau rentang tanggal pengiriman secara global.
- Riwayat versi override dan pemulihan override lama setelah override baru dihapus.
- Pengelompokan kembali baris tanggal menjadi rentang pada tabel Admin.
- Rename kolom legacy `pesanan.pax`.
- Perubahan desain final Admin/Owner di luar field kuota yang diperlukan.
