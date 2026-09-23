# Se.Noesa-Web
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

## ⚙️ Arsitektur Basis Data (4 Entitas Utama)

Sistem ini bertumpu pada 4 tabel utama berbasis MySQL:
1. `Users` — Menyimpan data akun pelanggan, admin, dan dapur beserta otorisasi peran (*role*).
2. `Produk` — Katalog menu katering beserta harga, stok harian (kuota max 50 porsi/hari), dan minimal order (10 porsi).
3. `Pesanan` — Header transaksi pemesanan, jadwal pengiriman, total harga, dan status pesanan.
4. `Detail_Pesanan` — Rincian item menu dan kuantitas porsi pada setiap transaksi.

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
