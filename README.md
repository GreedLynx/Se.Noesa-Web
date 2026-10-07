# SIMPO-Se.Noesa
Sistem Informasi Manajemen Pre-Order &amp; Antrean Produksi UMKM Se.Noesa.

Aplikasi web manajemen operasional dan pemesanan *Pre-Order* (PO) katering untuk UMKM Se.Noesa. Proyek ini dikembangkan sebagai luaran Project-Based Learning (PBL) Semester 3 D-IV Teknik Informatika, Politeknik Negeri Malang.

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
2. `produk` — Katalog menu katering beserta harga, stok harian (kuota max 50 porsi/hari), dan minimal order (4 porsi).
3. `jadwal_PO` — Menyimpan jadwal pembukaan pre-order, tanggal pengiriman, status jadwal, dan kuota batas maksimal porsi harian.
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
