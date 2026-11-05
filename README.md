# SIMPO-Se.Noesa
Sistem Informasi Manajemen Pre-Order &amp; Antrean Produksi UMKM Se.Noesa.

Aplikasi web manajemen operasional dan pemesanan *Pre-Order* (PO) makanan untuk UMKM Se.Noesa. Proyek ini dikembangkan sebagai luaran Project-Based Learning (PBL) Semester 3 D-IV Teknik Informatika, Politeknik Negeri Malang.

---

## 👥 Tim Pengembang (Kelompok 5 - TI-2E)

| NIM | Nama | Peran |
| :--- | :--- | :--- |
| 254107020208 | **Naufal Shofil Fuadi** | Ketua Tim / Project Manager & Backend |
| 254107020151 | **Deswita Khansa Rafifah** | Sekretaris / Dokumentator & UI Assitant |
| 254107020064 | **Carlos Fadellilah Rama** | Pengembang Utama & Database Architect |
| 254107020234 | **Syahroni Nur'an Syafi'i** | Analis / Desainer & Frontend Developer |
| 254107020088 | **Ken Dhiyaa Prawira** | QA / Penguji & Functional Tester |

---

## 📚 Mata Kuliah Terintegrasi

* **Manajemen Proyek** — Ahmadi Yuli Ananta, S.T., M.M.
* **Desain & Pemrograman Web** — Muhammad Unggul Pamenang, S.ST., M.T.
* **Basis Data Lanjut** — Dinny Wahyu Widarti, S.Kom., MMSI
* **Sistem Informasi Manajemen** — Budi Harijanto, S.T., M.MKom.

---

## ⚙️ Arsitektur Basis Data (5 Entitas Utama)

Sistem ini bertumpu pada 5 tabel utama berbasis PostgreSQL:
1. `users` — Menyimpan data akun pelanggan, admin, dan owner beserta otorisasi peran (*role*).
2. `produk` — Katalog menu beserta harga, kuota harian master yang diatur Admin, dan minimal pesan 4 porsi.
3. `jadwal_po` — Menyimpan jadwal pembukaan pre-order, status aktif, dan kapasitas jadwal lama. Checkout memakai batas dapur 80% dari kuota master.
4. `pesanan` — Menyimpan data transaksi pemesanan yang dilakukan oleh pelanggan.
5. `detail_pesanan` — Menyimpan rincian produk yang ada di dalam satu pesanan.

---

## 🌲 Aturan Git & Branching Strategy

Untuk menjaga stabilitas kodingan, ikuti konvensi *branching* berikut:

- `main` : Kode produksi stabil (hanya diisi via Merge Request saat *checkpoint*).
- `dev` : Branch integrasi fitur harian tim.
- `feature/<nama-fitur>` : Branch pembuatan fitur baru (contoh: `feature/login`, `feature/verifikasi-bayar`).

### Panduan Workflow Singkat:
```bash
# 1. Ambil pembaruan terbaru dari dev
git checkout dev
git pull origin dev

# 2. Buat branch fitur baru
git checkout -b feature/nama-fitur

# 3. Lakukan commit perubahan
git add .
git commit -m "feat: menambahkan halaman login pelanggan"

# 4. Push branch ke GitHub
git push origin feature/nama-fitur
```

## Struktur Aplikasi

Aplikasi menggunakan PHP native dan PostgreSQL, dengan area terpisah untuk owner, admin, dan pelanggan. Antarmuka menggunakan HTML, CSS, dan JavaScript untuk tata letak responsif, metrik, grafik, dan navigasi.

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

1. Salin `.env.example` menjadi `.env`, isi konfigurasi PostgreSQL, dan aktifkan ekstensi PHP `pdo_pgsql`.
2. Pada database baru yang kosong, jalankan `psql senoesa -f database/schema.sql`. File skema menghapus dan membuat ulang tabel, jadi jangan jalankan ulang pada database yang berisi data. Untuk database yang sudah ada, jalankan `php scripts/migrate.php`; migrasi menggunakan `.env`, mencatat file yang sudah diterapkan di `schema_migrations`, dan menjalankan file yang belum diterapkan sesuai urutan.
3. Buat akun admin melalui CLI:

   ```powershell
   $env:SENOESA_ADMIN_PASSWORD = 'gunakan-password-pribadi'
   php scripts/create-admin.php admin admin@senoesa.id
   Remove-Item Env:SENOESA_ADMIN_PASSWORD
   ```

4. Jalankan server pengembangan dari direktori proyek: `php -S localhost:8000 -t public`.
5. Buka `http://localhost:8000/`. Login mengarahkan akun berdasarkan role database. Registrasi publik selalu membuat akun `pelanggan`; akun pelanggan contoh dari seed adalah `andi@mail.com` dengan password `pelanggan123`.

## Alur Fitur Utama

Menu admin dan pelanggan menggunakan tabel PostgreSQL `produk` yang sama. Produk yang disimpan admin otomatis muncul di katalog pelanggan selama sisa stoknya lebih dari nol. Gambar produk diunggah admin dalam format JPG, PNG, atau WebP dengan ukuran maksimal 2 MB.

Pesanan pelanggan menyimpan snapshot harga setiap item dan memeriksa kuota produk serta kapasitas dapur pada tanggal kirim. Status awal adalah menunggu pembayaran; setelah bukti diunggah, pesanan masuk ke antrean validasi admin. Pelanggan dapat melihat status pesanan dan mengunggah bukti pembayaran JPG, PNG, atau WebP dengan ukuran maksimal 2 MB. Pesanan ditautkan ke jadwal aktif; sistem membuat jadwal default jika belum tersedia.

Akun owner tetap menggunakan area owner, akun admin hanya dibuat melalui script CLI, dan registrasi publik tidak dapat membuat role admin atau owner.

## Revisi Kuota Harian

Plan 1 memakai konfigurasi `kuota_harian(produk_id,tanggal,kuota)`. Jalankan migrasi
011 untuk VIEW laporan, lalu atur kapasitas total per tanggal melalui editor kuota
Admin. Tidak ada fallback master produk. Batas dapur adalah 80% dari total konfigurasi
tanggal itu. Filter tanggal tersedia pada laporan Admin/Owner; checkout tetap memakai
prepared query dalam transaksi. Uji UI: `node scripts/test-quota-ui.cjs`.

Catatan implementasi sebelumnya:

Jalankan `php scripts/migrate.php` untuk memasang migrasi 009 (master kuota harian)
dan 010 (indeks agregasi). Admin kemudian perlu mengisi kuota harian setiap produk;
nilai awal 0 berarti produk tidak tersedia. Kuota lama tidak disalin otomatis.

Checkout memakai tanggal pengiriman dalam Asia/Jakarta, menghitung jumlah porsi
dari detail pesanan selain yang ditolak, dan membatasi dapur pada 80% dari total
kuota master produk aktif. Ketersediaan pulih otomatis ketika pesanan ditolak.
Katalog menyediakan pilihan tanggal pengiriman untuk melihat sisa kuota.

Jalankan `php scripts/test-daily-quota.php` untuk memeriksa indeks, validasi kuota,
penolakan, batas hari, dan kunci checkout. Pengujian menggunakan tabel sementara
dan membatalkan transaksi, tanpa mengubah data pesanan maupun produk aplikasi.
Rincian kontrak dan migrasi ada di `docs/api.md` dan `docs/database.md`.
