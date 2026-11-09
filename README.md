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

## Alur Fitur Utama

Admin mengelola katalog produk, jadwal PO, dan validasi pembayaran. Pelanggan dapat melihat katalog, memasukkan produk ke keranjang, melakukan checkout, mengunggah bukti pembayaran, dan melihat riwayat pesanan. Owner hanya dapat melihat data dan laporan.

Pesanan menyimpan snapshot harga setiap produk. Bukti pembayaran yang didukung adalah JPG, PNG, atau WebP dengan ukuran maksimal 2 MB.

## Kuota Pengiriman

Admin mengatur kuota produk untuk setiap tanggal pengiriman. Pelanggan hanya dapat memesan produk yang masih memiliki sisa kuota pada tanggal yang dipilih. Sistem juga membatasi total pesanan hingga 80% dari akumulasi kuota produk pada tanggal tersebut.

Rincian teknis database dan endpoint tersedia di `docs/database.md` dan `docs/api.md`.
Panduan konsistensi penulisan tersedia di `docs/gaya-kode.md`.
