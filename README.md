# SIMPO-Se.Noesa

Sistem Informasi Manajemen Pre-Order dan Antrean Produksi UMKM Se.Noesa.

Aplikasi web manajemen operasional dan pemesanan *pre-order* (PO) makanan untuk UMKM Se.Noesa. Proyek ini dikembangkan sebagai luaran Project-Based Learning (PBL) Semester 3 D-IV Teknik Informatika, Politeknik Negeri Malang.

---

## Tim Pengembang (Kelompok 5 - TI-2E)

| NIM | Nama | Peran |
| :--- | :--- | :--- |
| 254107020208 | **Naufal Shofil Fuadi** | Ketua Tim / Project Manager & Backend |
| 254107020151 | **Deswita Khansa Rafifah** | Sekretaris / Dokumentator |
| 254107020064 | **Carlos Fadellilah Rama** | Pengembang Utama & Database Architect |
| 254107020234 | **Syahroni Nur'an Syafi'i** | Analis / Desainer & Frontend Developer |
| 254107020088 | **Ken Dhiyaa Prawira** | QA / Penguji & Functional Tester |

---

## Mata Kuliah Terintegrasi

- **Manajemen Proyek** — Ahmadi Yuli Ananta, S.T., M.M.
- **Desain & Pemrograman Web** — Muhammad Unggul Pamenang, S.ST., M.T.
- **Basis Data Lanjut** — Dinny Wahyu Widarti, S.Kom., MMSI.
- **Sistem Informasi Manajemen** — Budi Harijanto, S.T., M.MKom.

---

## Arsitektur Basis Data

Sistem menggunakan tabel utama PostgreSQL berikut:

1. `users` — akun pelanggan, admin, dan owner beserta otorisasi peran.
2. `produk` — katalog menu, harga, dan minimal pesan 4 porsi.
3. `jadwal_po` — jadwal pembukaan pre-order, status aktif, dan kapasitas jadwal.
4. `pesanan` — transaksi pemesanan pelanggan.
5. `detail_pesanan` — rincian produk dalam pesanan.

## Aturan Git dan Branching Strategy

- `main`: kode produksi stabil; hanya melalui Merge Request saat *checkpoint*.
- `dev`: branch integrasi fitur harian tim.
- `feature/<nama-fitur>`: branch pembuatan fitur, misalnya `feature/login`.

### Workflow Singkat

```bash
git checkout dev
git pull origin dev
git checkout -b feature/nama-fitur
git add .
git commit -m "feat: menambahkan halaman login pelanggan"
git push origin feature/nama-fitur
```

## Struktur Aplikasi

Pemilih tanggal katalog pelanggan dan filter kuota Admin/Owner memakai JavaScript
lokal `public/assets/js/date-picker.js` dan CSS `public/assets/css/date-picker.css`,
tanpa library baru. Kalender mendukung keyboard, tanggal berbahasa Indonesia,
dan batas input yang sudah ada. Bootstrap belum dimuat pada branch ini; pemuatan
Bootstrap tetap menunggu keputusan tim.

Uji kalender: `node scripts/test-date-picker.cjs`. Untuk uji DOM dengan CSS ketiga
role, tambahkan path executable Chrome/Edge sebagai argumen, misalnya
`node scripts/test-date-picker.cjs "C:/Program Files/Google/Chrome/Application/chrome.exe"`.

Aplikasi menggunakan PHP native dan PostgreSQL, dengan area terpisah untuk owner, admin, dan pelanggan. Antarmuka menggunakan HTML, CSS, dan JavaScript.

```text
app/                         Helper database, session, dan hak akses bersama
config/config.php            Konfigurasi koneksi PostgreSQL
database/schema.sql          Skema awal dan data contoh
database/migrations/         Perubahan aman untuk database yang sudah berisi data
scripts/create-admin.php     Membuat akun admin melalui CLI
public/
  api/auth.php               API login, registrasi, dan logout bersama
  assets/                    Aset bersama owner dan pelanggan
  owner/                     Dashboard, pesanan, produk, dan API owner
  admin/                     Dashboard, validasi pembayaran, produk, dan API admin
  pelanggan/                 Menu, keranjang, checkout, riwayat, dan API pelanggan
  dashboard.php              Redirect kompatibilitas ke owner/dashboard.php
```

## Setup Teknis

1. Salin `.env.example` menjadi `.env`, isi konfigurasi PostgreSQL, lalu aktifkan ekstensi PHP `pdo_pgsql`.
2. Untuk database baru yang kosong, jalankan `psql senoesa -f database/schema.sql`. File ini menghapus dan membuat ulang tabel, jadi jangan digunakan pada database yang telah berisi data. Untuk database yang sudah ada, jalankan `php scripts/migrate.php`.
3. Buat akun admin melalui CLI:

   ```powershell
   $env:SENOESA_ADMIN_PASSWORD = 'gunakan-password-pribadi'
   php scripts/create-admin.php admin admin@senoesa.id
   Remove-Item Env:SENOESA_ADMIN_PASSWORD
   ```

4. Jalankan server pengembangan dari direktori proyek: `php -S localhost:8000 -t public`.
5. Buka `http://localhost:8000/`. Registrasi publik selalu membuat akun dengan peran `pelanggan`.
6. Untuk membatalkan pesanan yang belum dibayar tepat waktu, jadwalkan
   `php scripts/expire-unpaid-orders.php --apply` setiap menit setelah inventaris
   dan dry-run. Rincian aktivasi, argumen Task Scheduler, dan cron ada di bawah.

## Alur Fitur Utama

Admin mengelola katalog produk, jadwal PO, dan validasi pembayaran. Pelanggan dapat melihat katalog, memasukkan produk ke keranjang, melakukan checkout, mengunggah bukti pembayaran, dan melihat riwayat pesanan. Owner hanya dapat melihat data dan laporan.
Admin juga dapat mencatat pesanan offline dari halaman daftar pesanan. Pesanan
tersebut langsung masuk status diproses dan mengurangi kuota pada tanggal kirim.
Saat menolak pembayaran, Admin wajib mengisi alasan yang terlihat oleh pelanggan
di riwayat dan detail pesanan.

Pesanan menyimpan snapshot harga setiap produk. Bukti pembayaran yang didukung adalah JPG, PNG, atau WebP dengan ukuran maksimal 2 MB.
Pesanan yang belum mengirim bukti pembayaran dibatalkan satu jam setelah dibuat.
Pembatalan otomatis berjalan melalui scheduler; membuka katalog atau riwayat
tidak mengubah pesanan. Pesanan yang dibatalkan tidak lagi memakai kuota.

## Kuota Pengiriman

Admin mengatur kuota produk untuk setiap tanggal pengiriman. Pelanggan hanya dapat memesan produk yang masih memiliki sisa kuota pada tanggal yang dipilih. Sistem juga membatasi total pesanan hingga 80% dari akumulasi kuota produk pada tanggal tersebut.

Rincian teknis database dan endpoint tersedia di `docs/database.md` dan `docs/api.md`.

## Lupa Password

Halaman login menyediakan alur reset password menggunakan OTP enam digit yang dikirim ke email terdaftar. OTP berlaku 10 menit, maksimal lima kali percobaan, dan permintaan ulang dibatasi satu kali per akun setiap 60 detik. Untuk mengaktifkannya, isi `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USERNAME`, `SMTP_PASSWORD`, dan `SMTP_FROM_EMAIL` pada `.env`. Gmail memerlukan Verifikasi 2 Langkah dan App Password; jangan gunakan atau bagikan password login Gmail. Jalankan `composer install` dan `php scripts/migrate.php` pada database yang sudah ada. Migrasi reset password berada di `012_password_resets.sql` dan `013_password_reset_otps.sql` setelah migrasi kuota.
Panduan konsistensi penulisan tersedia di `docs/gaya-kode.md`.

## Pembatalan dan aktivasi job pembayaran

Pelanggan boleh membatalkan pesanan sendiri saat menunggu pembayaran, menunggu
validasi, atau diproses sampai akhir hari H-2 WIB. Refund penuh ditangani Admin
di luar aplikasi; bukti/riwayat tetap tersimpan. Kontak WhatsApp Admin menyusul
setelah nomor/tautan tersedia.

Unpaid tanpa bukti kedaluwarsa satu jam sejak dibuat. Upload ditolak tepat mulai
tenggat meskipun job belum berjalan. Upload yang sudah berhasil dan antrean
validasi tidak terkena job. Status dan kuota berubah ketika job dijalankan;
dengan scheduler setiap menit keterlambatan sekitar satu menit, lebih lama jika
scheduler mati. Tidak ada proses background JavaScript atau route job publik.

Urutan aktivasi pada lingkungan target (belum dijalankan oleh implementasi):

1. Jalankan `php scripts/expire-unpaid-orders.php --inventory` lalu
   `php scripts/expire-unpaid-orders.php --dry-run`. Keduanya read-only,
   menampilkan total unpaid, kandidat (`eligible`), dan inkonsistensi.
   Pesanan lama mengikuti tenggat asli; periksa kandidat sebelum menerapkan.
2. Terapkan migrasi dengan `php scripts/migrate.php` agar 016 menambah status
   batal dan mengganti VIEW. Hentikan penulisan aplikasi saat migrasi/deploy
   backend agar kode baru dan constraint tersedia bersamaan.
3. Jalankan `php scripts/expire-unpaid-orders.php --apply`, periksa jumlah
   `Dibatalkan`, lalu jalankan lagi: perubahan kedua harus 0 bila tidak ada
   pesanan baru jatuh tempo. Unpaid dengan waktu NULL/bukti tersimpan dilewati
   dan dilaporkan, exit 1; jangan memperbaiki datanya secara otomatis.
4. Aktifkan salah satu scheduler berikut setiap menit. Gunakan path absolut
   PHP/proyek target dan folder log di luar `public/`; simpan riwayat eksekusi.

Cron hosting (ganti path sesuai instalasi):

```cron
* * * * * /usr/bin/php /srv/senoesa/scripts/expire-unpaid-orders.php --apply >> /srv/senoesa-logs/expire-unpaid.log 2>&1
```

Windows Task Scheduler: buat task yang boleh berjalan saat pengguna tidak login;
trigger setiap hari, Repeat task every **1 minute**, duration **Indefinitely**.
Action **Start a program**:

- Program: `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe`
- Arguments: `"E:\Tugas Kuliah\Semester 3\Coding_PBL\Se.Noesa-Web\scripts\expire-unpaid-orders.php" --apply`
- Start in: `E:\Tugas Kuliah\Semester 3\Coding_PBL\Se.Noesa-Web`

Aktifkan task history dan pilih tidak memulai instance kedua bila task masih
berjalan. Untuk menyimpan stdout/stderr, gunakan action PowerShell tersembunyi:
`-NoProfile -WindowStyle Hidden -Command "& 'C:\path\php.exe' 'E:\path\Se.Noesa-Web\scripts\expire-unpaid-orders.php' --apply >> 'E:\senoesa-logs\expire-unpaid.log' 2>&1; exit $LASTEXITCODE"`.
Buat folder log terlebih dahulu, sesuaikan path, dan pantau exit code/history.

Setelah aktivasi, periksa task/cron benar-benar berjalan tanpa kunjungan web,
log mencatat jumlah pembatalan, `--inventory` tidak menyisakan kandidat yang terus
tertunda, serta riwayat/kuota sesuai. Exit nonzero perlu diperiksa dan job diuji
ulang setelah pemulihan. Jangan aktifkan hanya berdasarkan sukses uji lokal.

Pengujian backend terisolasi tanpa `.env`: siapkan PostgreSQL uji lokal dan
database kosong bernama `senoesa_cancellation_test`, lalu PowerShell:

```powershell
$env:SENOESA_TEST_PGPORT = '55432' # port PostgreSQL uji
php scripts/test-order-cancellation.php
node scripts/test-order-cancellation-ui.cjs
```

User uji default `postgres`; bila perlu isi `SENOESA_TEST_PGUSER` dan
`SENOESA_TEST_PGPASSWORD` melalui environment. Harness membuat schema acak,
menjalankan migrasi/endpoint/dua koneksi/job dan tes kuota yang ada, lalu
menghapus fixture uji. Perpindahan file HTTP disimulasikan hanya pada harness CLI;
upload browser dan scheduler lingkungan target perlu diverifikasi saat aktivasi.
