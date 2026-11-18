# Test Plan Release — Se.Noesa

- Tanggal: 2026-11-18
- Branch / HEAD: `feature/full-stack` / `43da86d`
- Sifat dokumen: **RENCANA**. Belum ada test yang dijalankan pada tahap ini.
- Tujuan: menutup celah pengujian yang masih terbuka pada `docs/04-implementasi/TEST-REPORT-SE.NOESA.md` dan `docs/06-rilis/RELEASE-READINESS-SE.NOESA.md`.

## Aturan lingkungan (wajib)

1. **Dilarang** menjalankan test mutasi, migration, DDL, atau `INSERT/UPDATE/DELETE` pada Supabase remote.
2. Semua test mutasi dilakukan pada **PostgreSQL lokal/staging terisolasi** (lihat prosedur di bawah).
3. Browser test memakai akun fixture, **bukan data pelanggan nyata**.
4. **Lint / `node --check` tidak dihitung sebagai lulus fungsional.** Setiap skenario harus memiliki bukti (response API, screenshot, log, atau query read-only pada DB lokal).
5. Jika prasyarat tidak terpenuhi, tandai **BELUM DIJALANKAN** — jangan dinyatakan lulus.

### Prosedur menyiapkan DB isolat

1. Install/jalankan PostgreSQL lokal (container atau native) dengan database `se_noesa_test`.
2. Salin `.env.example` menjadi `.env.test`, isi kredensial lokal. **Jangan commit `.env.test`** (perbarui `.gitignore` jika diperlukan, oleh pemilik).
3. Jalankan `database/schema.sql`, lalu seluruh `database/migrations/*.sql` berurutan sesuai `scripts/migrate.php`. Verifikasi dengan `scripts/check-db-target.php` (read-only).
4. Seed fixture sintetis: 3 produk (kuota default 50, satu override 0, satu override 10), 2 pelanggan, 1 admin, 1 owner, 1 tanggal libur, 1 metode pembayaran aktif dengan QRIS fixture. Ganti semua identitas dengan data palsu.
5. Pastikan aplikasi test memakai `.env.test`, bukan `.env` produksi.
6. Catat hash migration/schema yang dipakai agar hasil dapat direproduksi.

---

## 1. Checkout end-to-end

- **Prasyarat:** DB isolat siap; pelanggan fixture login; produk dengan kuota cukup; tanggal kirim ≥ H+7; alamat tersimpan; CSRF token tersedia.
- **Langkah:**
  1. Buka katalog, pilih tanggal kirim, tambah produk minimum jumlah (≥ `min_pesan`), periksa jumlah kuota menurun.
  2. Buka keranjang, ubah jumlah dengan tombol +/−, pastikan tidak di bawah minimum, hapus satu item, tambah lagi.
  3. Klan checkout, isi alamat, kirim pesanan.
  4. Periksa response: `ok=true`, `id`, `kode`, `total`; status awal `menunggu_pembayaran`.
  5. Buka detail pesanan; verifikasi QRIS tampil, `payment_expires_at` ≈ `created_at + 1 jam`, tombol unggah bukti aktif.
  6. Unggah bukti (JPG valid, ≤2 MB) + nama pembayar → status `menunggu_validasi`.
  7. Ganti bukti → path DB berubah, status tetap `menunggu_validasi`.
  8. Admin verifikasi → `diproses`; checklist Centang semua; unggah foto kiriman → `selesai`; foto tetap tampil di Pelanggan.
  9. Reload & login ulang; data tetap konsisten.
- **Diharapkan:** status berurutan tanpa lompat; bukti & foto terikat `pesanan_id`; kuota terhitung sekali per porsi; tidak ada duplikat bukti.
- **Risiko:** race update status, file orphan, kuota tidak kembali saat batal, QRIS path salah.
- **Bukti:**_response JSON_ tiap langkah, screenshot detail, query `SELECT id,status,bukti_bayar,nama_pembayar FROM pesanan WHERE id=?` pada DB lokal, daftar `detail_pesanan`, dan perbandingan kuota sebelum/sesudah.

## 2. Mutasi data (Admin & Pelanggan)

- **Prasyarat:** DB isolat; akun admin, owner, pelanggan; pesanan fixture di tiap status.
- **Langkah:**
  1. Admin: ubah kuota produk untuk tanggal tertentu (termasuk ke 0), reset ke default, hapus override.
  2. Admin: tambah/edit produk dengan gambar valid; hapus produk yang belum dipakai; coba hapus produk yang sudah dipakai (harus ditolak 409).
  3. Admin: tambah/edit/hapus tanggal libur; verifikasi tanggal libur menutup kuota di katalog.
  4. Admin: validasi, tolak (dengan alasan), selesaikan pesanan; tinjau pembatalan.
  5. Pelanggan: simpan alamat, jadikan default, batalkan pesanan pada H-3/H-2/H-1 dan setelah verifikasi.
  6. Owner: coba POST ke endpoint mutasi Admin → harus 401.
  7. Coba aksi tanpa CSRF token → harus 419.
- **Diharapkan:** setiap mutasi mengubah DB sesuai aksi; role ditolak di backend; CSRF berfungsi; histori tidak hilang.
- **Risiko:** Owner mendapat akses mutasi; operasi kuota tanpa lock; alasan penolakan tidak tersimpan.
- **Bukti:** response API + `SELECT` pada tabel terkait sebelum/sesudah + log error server.

## 3. Konkurensi kuota

- **Prasyarat:** DB isolat; produk dengan sisa kuota kecil (mis. 10 porsi, minimum 4); 2+ sesi pelanggan berbeda; alat request paralel (script Node/curl).
- **Langkah:**
  1. Jalankan 2 checkout bersamaan untuk produk & tanggal sama dengan total permintaan melebihi sisa kuota.
  2. Ulangi dengan 5 checkout paralel.
  3. Ulangi dengan checkout + Admin mengubah kuota/range secara bersamaan.
  4. Ulangi dengan checkout + penutupan tanggal libur secara bersamaan.
  5. Periksa jumlah pesanan berhasil dan total porsi vs kuota.
- **Diharapkan:** tidak ada oversold; satu request gagal dengan pesan kuota tidak mencukupi; tidak ada detail pesanan parsial; transaksi rollback bersih.
- **Risiko:** deadlock urutan lock berbeda; snapshot dibaca sebelum lock; partial insert.
- **Bukti:** log request paralel dengan timestamp, response gagal/sukses, query agregat `SUM(d.qty)` per produk/tanggal, dan `EXPLAIN` bila perlu. Jangan pakai `LOCK TABLE` pada DB remote.

## 4. Upload gambar valid

- **Prasyarat:** DB isolat; folder `public/uploads/products` dan `public/uploads/payments` dapat ditulis; file fixture JPG/PNG/WebP ≤2 MB; file >2 MB; file berekstensi `.jpg` berisi teks.
- **Langkah:**
  1. Admin upload gambar produk valid → response `ok=true`, path `uploads/products/<hex>.<ext>`, gambar tampil di katalog & daftar produk Owner/Admin.
  2. Upload format tidak didukung → 422 dengan pesan jelas.
  3. Upload >2 MB → 422.
  4. Upload nama file khusus/duplikat (`../../x.jpg`) → nama file hasil generate tetap aman.
  5. Simulasikan kegagalan DB setelah file dipindah → pastikan tidak ada record/path rusak (OBS-004 masih terbuka; uji untuk konfirmasi perilaku saat ini).
  6. Pelanggan upload & ganti bukti bayar; Admin unggah foto kiriman; periksa tampil di tiga role.
- **Diharapkan:** path tersimpan di DB dan file dapat dibuka; file invalid ditolak; tidak ada path traversal; orphan file terdeteksi/didokumentasi.
- **Risiko:** file orphan (OBS-004), MIME palsu, permission folder.
- **Bukti:** response API, `SELECT gambar/bukti_bayar`, daftar file di folder upload, screenshot tampilan tiap role.

## 5. Browser desktop

- **Prasyarat:** browser Chromium/Firefox; DB isolat; akun fixture tiga role; viewport 1280×800 & 1920×1080.
- **Langkah:**
  1. Login tiap role; periksa redirect ke dashboard masing-masing.
  2. Jalankan alur katalog → checkout → pembayaran → riwayat (Pelanggan).
  3. Kelola pesanan, kuota, produk, libur, checklist (Admin).
  4. Dashboard, filter periode, detail pesanan, produk & kuota (Owner).
  5. Uji Escape/backdrop dialog, fokus keyboard, toast, auto-refresh setelah aksi.
  6. Periksa console browser: tidak ada error JS/404 aset.
- **Diharapkan:** tidak ada elemen terpotong/overflow; state UI segar setelah aksi; filter/periode dipertahankan; dialog dapat ditutup.
- **Risiko:** data stale, dialog tidak menutup, aset 404, ikon SVG tidak render.
- **Bukti:** screenshot per halaman, log console, catatan viewport, daftar error (bila ada).

## 6. Browser mobile

- **Prasyarat:** emulator/perangkat 390×844 & 360×740; DB isolat; akun fixture.
- **Langkah:**
  1. Uji alur yang sama dengan browser desktop pada kedua viewport.
  2. Periksa FAB “+ Pesan menu” di riwayat (tidak menutup konten, aman dari area safe-area).
  3. Periksa bottom nav aktif Admin/Owner/Pelanggan.
  4. Uji dialog di mobile: backdrop, Escape, scroll body tidak terkunci.
  5. Uji form checkout & unggah gambar dari perangkat.
- **Diharapkan:** tombol dapat dijangkau jari; tidak ada horizontal scroll; teks terbaca; dialog tidak keluar layar.
- **Risiko:** FAB menutup konten, safe-area tidak diterapkan, nav aktif tidak terlihat.
- **Bukti:** screenshot tiap viewport, catatan dimensi, hasil uji sentuh.

## 7. Regresi autentikasi & logout

- **Prasyarat:** DB isolat; akun fixture untuk tiga role; CSRF aktif.
- **Langkah:**
  1. Login berhasil & gagal (password salah) untuk tiap role.
  2. Logout via tombol → dialog konfirmasi muncul; Batal → tetap login; Ya → redirect login; sesi tidak valid setelah logout.
  3. Uji backdrop/klik konten pada dialog logout (Pelanggan & Owner).
  4. Akses halaman Admin tanpa role → 401/redirect; Owner POST ke endpoint Admin → 401.
  5. Forgot password → token/OTP kedaluwarsa; reset password berhasil.
  6. Uji request tanpa CSRF → 419.
- **Diharapkan:** konfirmasi logout konsisten; tidak ada sesi bocor; proteksi role & CSRF bekerja.
- **Risiko:** dialog native vs kustom tidak konsisten; token reset tidak kedaluwarsa.
- **Bukti:** screenshot dialog, response API, cookie/session state, log server.

## 8. Perbedaan tampilan Admin, Owner, Pelanggan

- **Prasyarat:** DB isolat dengan dataset fixture yang sama untuk ketiga role; browser desktop + mobile.
- **Langkah:**
  1. Bandingkan daftar & detail pesanan Admin vs Owner untuk filter `tab` identik: harus identik kecuali kontrol mutasi (Owner read-only).
  2. Verifikasi Owner tidak melihat tombol offline/verifikasi/complete, upload foto kiriman, atau checkbox aktif.
  3. Bandingkan label status di tiga role: “Menunggu verifikasi” konsisten.
  4. Periksa tampilan metrik Owner setelah #13 diputuskan (label & satuan sesuai definisi).
  5. Periksa warna/brand sesuai aset final setelah #14.
- **Diharapkan:** data identik, hak akses berbeda, label konsisten, tidak ada kontrol mutasi di Owner.
- **Risiko:** kontrol mutasi muncul lagi di Owner; label status berbeda antar-role.
- **Bukti:** screenshot berdampingan, response API byte-identik (seperti TASK-02), daftar selector mutasi = 0 di Owner.

---

## 9. Kriteria kelulusan & pelaporan

- Sebuah skenario dinyatakan **LULUS** hanya jika semua langkah berjalan dan bukti dikumpulkan.
- Skenario yang gagal dibuat sebagai **task baru**, tidak diperbaiki diam-diam di luar scope ( sesuai TASK-22).
- Ringkasan akhir test matrix harus memuat: skenario, prasyarat, hasil aktual, bukti, status (LULUS/GAGAL/BELUM DIJALANKAN), dan alasan.
- **GO** hanya jika: 15 keputusan bisnis tertutup, 9 task BLOCKED selesai, TASK-20 DONE, seluruh skenario kritis (checkout, kuota, role, auth) LULUS, dan tidak ada blocker P0.

## 10. Urutan eksekusi test yang disarankan

1. Siapkan DB isolat + fixture (prasyarat semua test).
2. Autentikasi & CSRF & role (cepat, menutup risiko keamanan).
3. Mutasi data Admin/Pelanggan.
4. Checkout end-to-end + upload valid.
5. Konkurensi kuota.
6. Browser desktop → mobile.
7. Perbandingan tiga role.
8. Ulangi TASK-22 & TASK-23, lalu putuskan GO/NO-GO.
