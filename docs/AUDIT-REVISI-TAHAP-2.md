# AUDIT REVISI TAHAP 2 — Se.Noesa-Web

Tanggal audit: 17 November 2026
Auditor: AI coding agent (Kilo)
Basis: `REVISI-LANJUTAN-SE.NOESA.md`, `revisi-senoesa.md`, kondisi aktual repository.

---

## 1. Ringkasan Kondisi Proyek

### 1.1 Stack dan arsitektur

- **Backend:** PHP 8.1 native (tanpa framework), PostgreSQL (target Supabase).
- **Frontend:** HTML/CSS/JS vanilla, tanpa build tool.
- **Role:** Pelanggan, Admin, Owner.
- **Entry:** `php -S localhost:8000 -t public`.
- **Database:** PostgreSQL via PDO. Migration runner `scripts/migrate.php`. Schema awal `database/schema.sql`.

### 1.2 Kondisi database dan migration

- 24+ migration files tersedia di `database/migrations/` (001–024).
- Tabel utama: `users`, `produk`, `jadwal_po`, `pesanan`, `detail_pesanan`, `kuota_harian`, `metode_pembayaran`, `pesanan_foto_kiriman`, `alamat_pelanggan`, `tanggal_libur_operasional`, `pemberitahuan_perubahan_menu*`.
- Kuota harian: override per produk per tanggal (`kuota_harian`), fallback ke `produk.kuota_harian` (default 50).
- Foto pesanan: tabel `pesanan_foto_kiriman` + kolom legacy `pesanan.foto_kiriman`.
- Bukti pembayaran: kolom `pesanan.bukti_bayar` + `nama_pembayar`.
- Alasan penolakan: kolom `pesanan.alasan_penolakan`.

### 1.3 Perubahan tahap 1 (commit a25fe4d)

15 file diubah, 716 insertions, 79 deletions. Mencakup:

- QRIS imagePath fix, sisa porsi dinamis, kuota filter sisa>0
- Checklist sync, bukti pesanan CSS, owner data sync (menu_notifications)
- UI/teks: logout popup, validasi→verifikasi, hapus "Menu Utama", dll.

### 1.4 Hasil pengujian sintaks

- PHP lint: semua file lulus.
- JS syntax check (node --check): semua file lulus.
- **Catatan:** PHP lint dan JS syntax check tidak cukup untuk menyatakan fitur Done. Pengujian fungsional (browser, database, role) belum dilakukan pada tahap ini.

---

## 2. Audit Implementasi Sebelumnya (Tahap 1)

| Item | Status Audit | Bukti | Catatan |
|------|-------------|-------|---------|
| QRIS + imagePath | **DONE** | `customer.js:62-68` imagePath handle `uploads/payment-methods/`; `openHistoryDetail()` pakai `imagePath()` + onerror | Path storage `uploads/payment-methods/method-*.jpg` tersedia. |
| Sisa porsi dinamis | **DONE** | `customer.js:210-242` `#detail-sisa` update di `setQuantity()` | Tampil hanya jika sisa < 50% kuota. |
| Kuota filter (catalog) | **DONE** | `catalog.php:18` filter `sisa > 0` | Produk habis tidak muncul. |
| Checklist sync | **DONE** | `admin.js:1155-1161` `syncCompleteButton()` | Tombol Selesai disabled jika belum semua centang. |
| Bukti pesanan CSS | **DONE** | `app.css:733-748` `.proof` max-height 400px, object-fit contain, overflow auto | Scrollable, tidak terpotong. |
| Owner data sync | **PARTIAL** | `admin/api/orders.php` include menu-notifications, tambah `menu_notifications` ke detail; `app.js` render section | Perlu verifikasi data pesanan Owner = Admin secara menyeluruh (query, filter, pagination). |
| Logout popup (Pelanggan) | **DONE** | `customer.js:49-72` custom dialog | Konfirmasi sebelum logout. |
| "Validasi" → "Verifikasi" | **DONE** | `admin.js`, `admin/orders.php` | Label & dialog diperbarui. |
| "Menu Utama" hapus | **DONE** | `admin/partials/layout.php:30` empty div | Teks dihapus. |
| "Buka" → "Lihat" | **DONE** | `admin.js:323` | Label diperbarui. |
| "Antrean Prioritas" → "Menunggu Verifikasi" | **DONE** | `admin/dashboard.php:120` | Label diperbarui. |
| "Status sejak" → "Terakhir diubah" | **DONE** | `admin.js:433,468` | Label diperbarui. |
| Owner topbar "Se.Noesa" → "Owner" | **DONE** | `owner/partials/layout.php:42` | Teks diperbarui. |
| "Hari ini, Minggu, Bulan" → "Hari, Minggu, Bulan" | **DONE** | `owner/dashboard.php:27-29` | Label diperbarui. |
| "Tiket Rata-Rata" → "Rata-rata Pesanan" | **DONE** | `owner/dashboard.php:18` | Label diperbarui. |
| "Botol" → "Botol minuman" | **DONE** | `app.js:415` | Label diperbarui. |
| Checklist owner disabled | **DONE** | `app.js:617` `disabled` attribute | Read-only enforced di frontend. |
| Batas pembayaran teks | **DONE** | `pelanggan/partials/layout.php:205` | "dan kuota dikembalikan" dihapus. |
| Cart kurangi/tambah sebagian | **DONE** | `customer.js:300-335` | Tombol −/+ per item. |
| Sisa porsi < 50% | **DONE** | `customer.js:210-242` | Tampil hanya jika < 50%. |
| Riwayat hapus teks | **DONE** | `pelanggan/riwayat.php:9` | Subtitle dihapus. |
| Duplikasi logo profil & tombol keluar | **DONE** | `pelanggan/partials/layout.php` | `customer-bottom-user` hidden, `user-icon` removed. |
| Info pemesanan → checkout dialog | **DONE** | `pelanggan/partials/layout.php:205` | catalog-policy di checkout. |

---

## 3. Status Semua ID Revisi

### 3.1 Gambar tambahan (IMG-*)

| ID | Status | Keterangan |
|----|--------|------------|
| IMG-CUS-01 | **NOT IMPLEMENTED** | Tidak ada pesan khusus saat kuota penuh. Produk habis difilter dari katalog, pelanggan tidak melihat pesan "stok habis untuk tanggal X". |
| IMG-CUS-02 | **NOT IMPLEMENTED** | Tidak ada pengingat untuk pesanan yang sudah dibayar (menunggu_validasi) tapi bukti belum dikirim. |
| IMG-ADM-01 | **DONE** | `holidays.php` API + `schedules.php` UI. Admin bisa tambah/edit/hapus tanggal libur. |
| IMG-ADM-02 | **NOT IMPLEMENTED** | Tidak ada nomor WhatsApp di manapun. |
| IMG-ADM-03 | **PARTIAL** | Admin bisa set kuota 0, tapi tidak ada warning/konfirmasi dampak ke pelanggan terdampak. Tidak ada catatan alasan. |
| IMG-ADM-04 | **PARTIAL** | Beberapa aksi pakai `location.reload()` (admin.js), tapi tidak konsisten. Beberapa dialog tidak auto-refresh. |
| IMG-ADM-05 | **DONE** | `alasan_penolakan` disimpan dan ditampilkan ke pelanggan. |
| IMG-OWN-01 | **PARTIAL** | `menu_notifications` ditambahkan ke owner detail. Perlu verifikasi penuh query/filter/data. |

### 3.2 Pelanggan (CUS-*)

| ID | Status | Keterangan |
|----|--------|------------|
| CUS-01 | **DONE** (passed) | Tombol ganti bukti. |
| CUS-02 | **DONE** (passed) | Pilih data pelanggan/orang lain. |
| CUS-03 | **DONE** (passed) | Info pemesanan H-berapa. |
| CUS-04 | **PARTIAL** | Backdrop close: detail-dialog ✓, history-dialog ✓. Cart dialog ✗, checkout dialog ✗. |
| CUS-05 | **DONE** (passed) | Kuota ikuti tanggal kirim. |
| CUS-06 | **DONE** | Batal maks H-2, backend enforce. |
| CUS-07 | **DONE** (passed) | Foto siap kirim tampil. |
| CUS-08 | **DONE** | Sisa porsi update dinamis. |
| CUS-09 | **DONE** | QRIS muncul. |
| CUS-10 | **PARTIAL** | Backend validasi min_pesan per produk. Frontend pakai product.min_pesan. Admin form min ≥ 4. Perlu audit konsistensi. |
| CUS-11 | **PARTIAL** | Alur pembayaran ada tapi perlu testing end-to-end (upload, status, timeout). |
| CUS-12 | **DONE** | Cart punya tombol −/+ per item. |
| CUS-13 | **DONE** | Batas pembayaran ditampilkan, teks kuota dikembalikan dihapus. |
| CUS-14 | **DONE** | 3 logo profil → 1. |
| CUS-15 | **DONE** | Info pemesanan pindah ke checkout. |
| CUS-16 | **DONE** (passed) | "Riwayat Pesanan" double fixed. |
| CUS-17 | **NOT IMPLEMENTED** | Tombol plus masih di header, belum pindah ke kanan bawah. |
| CUS-18 | **PARTIAL** | Masih ada label "Jumlah" dan "Sisa Porsi". Keduanya konsep berbeda, tapi revisi minta satu saja. |
| CUS-19 | **DONE** | Sisa porsi hanya tampil jika < 50%, tanpa "/100". |
| CUS-20 | **NOT IMPLEMENTED** | Masih "RINGKASAN KERANJANG" (index.php:55), belum "ISI KERANJANG". |
| CUS-21 | **PARTIAL** | catalog-policy adalah paragraf tunggal, belum list. |
| CUS-22 | **NOT IMPLEMENTED** | Panah di 4 menu dashboard belum disimetris. |
| CUS-23 | **DONE** | Logout popup konfirmasi. |
| CUS-24 | **NOT IMPLEMENTED** | Brand masih "Se.Noesa", belum "se.noesa" lowercase. |
| CUS-25 | **NOT IMPLEMENTED** | Audit warna/logo/background belum dilakukan (design task). |
| CUS-26 | **DONE** | Batas pembatalan H-2 enforced. |
| CUS-27 | **DONE** | Foto pesanan siap kirim tampil di customer. |
| CUS-28 | **DONE** | Kuota default 50 (migration 017). |
| CUS-29 | **PARTIAL** | Upload produk punya error handling, tapi perlu testing upload gagal/berhasil. |

### 3.3 Admin (ADM-*)

| ID | Status | Keterangan |
|----|--------|------------|
| ADM-01 | **DONE** | confirmDialog untuk validate/reject/complete. |
| ADM-02 | **DONE** (passed) | Tanggal tidak digrup. |
| ADM-03 | **PARTIAL** | Status "belum bayar" (menunggu_pembayaran) dan "ditolak" ada. Filter pembatalan ada. Perlu verifikasi konsistensi. |
| ADM-04 | **DONE** | Kuota per tanggal. |
| ADM-05 | **DONE** | Kuota default saat tambah produk. |
| ADM-06 | **PARTIAL** | daily_quota_snapshot punya maxKitchen (80%). Tapi "kuota dapur" vs "kuota penjualan" belum jelas apakah konsep terpisah. Perlu konfirmasi. |
| ADM-07 | **DONE** | Foto tetap terhubung, filter pembatalan ada. |
| ADM-08 | **DONE** | Empty state "Belum ada bukti pembayaran". |
| ADM-09 | **DONE** | Alasan penolakan disimpan & ditampilkan. |
| ADM-10 | **NOT IMPLEMENTED** | Tidak ada keterangan jika produk dinonaktifkan tapi ada di pesanan lama. |
| ADM-11 | **DONE** | Pesanan manual (offline) via create_offline_order. |
| ADM-12 | **PARTIAL** | Offline order masih wajib alamat. Tidak ada tipe dine-in. Perlu keputusan bisnis. |
| ADM-13 | **PARTIAL** | Admin bisa pesan H-7 (offline min today). Customer min +7 hari. Tidak konsisten. |
| ADM-14 | **DONE** | Rekap produksi per tanggal + tautan detail. |
| ADM-15 | **DONE** | "Buka" → "Lihat". |
| ADM-16 | **DONE** | "Antrean Prioritas" → "Menunggu Verifikasi". |
| ADM-17 | **PARTIAL** | Kode dan nama_pemesan sama-sama bold. Username belum lebih menonjol dari ID. |
| ADM-18 | **NOT IMPLEMENTED** | Strip di menu "Pesanan" belum diperbaiki. |
| ADM-19 | **DONE** | "Validasi" → "Verifikasi". |
| ADM-20 | **NOT IMPLEMENTED** | Ikon belum diganti ke vector konsisten. |
| ADM-21 | **NOT IMPLEMENTED** | Mobile nav aktif state belum jelas. |
| ADM-22 | **DONE** | "Status sejak" → "Terakhir diubah". |
| ADM-23 | **NOT IMPLEMENTED** | Ikon search box belum diganti. |
| ADM-24 | **DONE** | "Senoesa/Admin" double dikurangi. |
| ADM-25 | **NOT IMPLEMENTED** | Foto profil admin masih double (sidebar + topbar). |
| ADM-26 | **DONE** | "Menu Utama" dihapus. |
| ADM-27 | **NOT IMPLEMENTED** | No WA belum ditambahkan. |
| ADM-28 | **PARTIAL** | Auto-refresh tidak konsisten. |
| ADM-29 | **PARTIAL** | Warning kuota 0 belum ada. |

### 3.4 Owner (OWN-*)

| ID | Status | Keterangan |
|----|--------|------------|
| OWN-01 | **DONE** (passed) | Read-only enforced di backend & frontend. |
| OWN-02 | **PARTIAL** | CSS .proof fixed, tapi white space besar di detail belum sepenuhnya diatasi. |
| OWN-03 | **PARTIAL** | Desktop/mobile layout perlu audit lebih lanjut. |
| OWN-04 | **PARTIAL** | Filter periode ada (today/week/month) tapi pemilihan tanggal spesifik belum. |
| OWN-05 | **DONE** (passed) | Status ditolak tampil. |
| OWN-06 | **DONE** | Gambar produk tampil. |
| OWN-07 | **DONE** | "Senoesa" → "Owner". |
| OWN-08 | **DONE** | "Hari ini, Minggu, Bulan" → "Hari, Minggu, Bulan". |
| OWN-09 | **DONE** | "Tiket Rata-Rata" → "Rata-rata Pesanan". |
| OWN-10 | **NOT IMPLEMENTED** | Ikon dekoratif belum dihapus. |
| OWN-11 | **DONE** | "Botol" → "Botol minuman". |
| OWN-12 | **NOT IMPLEMENTED** | Ikon pemesan belum dihapus. |
| OWN-13 | **NOT IMPLEMENTED** | Space kosong bawah Pemesan belum diperbaiki. |
| OWN-14 | **DONE** | Bukti pesanan CSS fixed (scrollable). |
| OWN-15 | **NOT IMPLEMENTED** | Space bawah Jadwal belum diperbaiki. |
| OWN-16 | **DONE** | Checklist disabled di owner. |
| OWN-17 | **NOT IMPLEMENTED** | Tombol return panah belum disimetris. |
| OWN-18 | **PARTIAL** | confirm-dialog centered (position:fixed, inset:0, margin:auto). Perlu verifikasi owner logout pakai dialog ini. |
| OWN-19 | **DONE** (passed) | Status ditolak + alasan. |
| OWN-20 | **PARTIAL** | quota-date filter ada tapi label tanggal belum ditampilkan bersama kuota. |
| OWN-21 | **PARTIAL** | menu_notifications ditambahkan. Perlu verifikasi data pesanan Owner = Admin. |

### 3.5 Umum (GEN-*)

| ID | Status | Keterangan |
|----|--------|------------|
| GEN-01 | **DONE** (passed) | Forgot password via email. |
| GEN-02 | **DONE** (passed) | Supabase/PostgreSQL. |

---

## 4. File Frontend/Backend Terkait

### Backend (PHP API)
- `public/pelanggan/api/catalog.php` — filter sisa>0 (DONE)
- `public/pelanggan/api/orders.php` — checkout, cancel, upload proof, detail
- `public/pelanggan/api/payment-methods.php` — metode pembayaran aktif
- `public/admin/api/orders.php` — admin orders, check_item, transitions, detail + menu_notifications
- `public/admin/api/products.php` — CRUD produk, upload gambar
- `public/admin/api/kuota-harian.php` — kuota per tanggal
- `public/admin/api/holidays.php` — tanggal libur
- `public/owner/api/orders.php` — include admin orders (read-only)
- `app/daily-quota.php` — kuota snapshot, validasi, lock
- `app/order-cancellation.php` — cancellation metadata
- `app/menu-notifications.php` — notifikasi perubahan menu
- `app/offline-orders.php` — pesanan manual admin

### Frontend (JS)
- `public/pelanggan/assets/customer.js` — katalog, cart, checkout, riwayat, detail
- `public/admin/assets/admin.js` — dashboard, orders, products, order detail
- `public/assets/js/app.js` — owner dashboard, orders, products, order detail

### Frontend (PHP views)
- `public/pelanggan/index.php`, `riwayat.php`, `partials/layout.php`
- `public/admin/dashboard.php`, `orders.php`, `order-detail.php`, `partials/layout.php`
- `public/owner/dashboard.php`, `orders.php`, `order-detail.php`, `products.php`, `partials/layout.php`

### CSS
- `public/assets/css/app.css` — shared owner/pelanggan styles
- `public/admin/assets/admin.css` — admin styles
- `public/pelanggan/assets/customer.css` — customer styles

---

## 5. Kondisi Database dan Migration

### Tabel relevan

| Tabel | Status | Keterangan |
|-------|--------|------------|
| `kuota_harian` | ✅ Ada | Override kuota per produk per tanggal |
| `produk.kuota_harian` | ✅ Ada | Default kuota produk |
| `pesanan.foto_kiriman` | ✅ Ada | Legacy foto kiriman |
| `pesanan_foto_kiriman` | ✅ Ada | Multi-foto kiriman |
| `pesanan.bukti_bayar` | ✅ Ada | Path bukti pembayaran |
| `pesanan.alasan_penolakan` | ✅ Ada | Alasan penolakan |
| `pesanan.status` | ✅ Ada | 6 status (menunggu_pembayaran, menunggu_validasi, diproses, selesai, ditolak, dibatalkan) |
| `metode_pembayaran` | ✅ Ada | Tabel metode pembayaran (QRIS) |
| `tanggal_libur_operasional` | ✅ Ada | Tanggal libur |
| `pemberitahuan_perubahan_menu*` | ✅ Ada | Notifikasi perubahan menu |

### Migration yang diperlukan tapi belum ada

- Tidak ada migration untuk: nomor WhatsApp (konfigurasi), catatan alasan perubahan kuota 0, tipe pesanan (dine-in vs delivery).

---

## 6. Bug yang Ditemukan

### 6.1 Bug fungsional

1. **Cart dialog tidak bisa ditutup dengan backdrop click** (CUS-04 PARTIAL).
2. **Checkout dialog tidak bisa ditutup dengan backdrop click** (CUS-04 PARTIAL).
3. **"RINGKASAN KERANJANG" belum diganti ke "ISI KERANJANG"** (CUS-20 NOT IMPLEMENTED).
4. **Tombol plus pesanan belum pindah ke kanan bawah** (CUS-17 NOT IMPLEMENTED).
5. **Brand "se.noesa" lowercase belum diterapkan** (CUS-24 NOT IMPLEMENTED).
6. **No WA belum ditambahkan** (IMG-ADM-02, ADM-27 NOT IMPLEMENTED).
7. **Warning kuota 0 belum ada** (IMG-ADM-03, ADM-29 PARTIAL).
8. **Foto profil admin double** (ADM-25 NOT IMPLEMENTED).
9. **Strip di menu Pesanan** (ADM-18 NOT IMPLEMENTED).
10. **Ikon search box** (ADM-23 NOT IMPLEMENTED).
11. **Ikon dekoratif owner** (OWN-10, OWN-12 NOT IMPLEMENTED).
12. **Space kosong bawah Pemesan/Jadwal** (OWN-13, OWN-15 NOT IMPLEMENTED).
13. **Tombol return panah tidak simetris** (OWN-17 NOT IMPLEMENTED).
14. **Menu dinonaktifkan keterangan** (ADM-10 NOT IMPLEMENTED).
15. **Kuota untuk tanggal berapa (label)** (OWN-20 PARTIAL).
16. **Pemilihan tanggal tren pesanan** (OWN-04 PARTIAL).

### 6.2 Bug potensial (perlu testing)

1. **Race condition checkout**: `daily_quota_lock_tables()` menggunakan LOCK TABLE, tapi perlu verifikasi pada concurrent requests.
2. **Owner data sync**: perlu verifikasi bahwa owner API (include admin) mengembalikan data identik.
3. **Foto produk upload**: perlu testing upload gagal/berhasil dengan berbagai format/ukuran.
4. **Minimum pemesanan konsistensi**: perlu verifikasi frontend-backend.

### 6.3 Konflik kebijakan

1. **Kuota dapur vs kuota penjualan**: `maxKitchen = 80% × total kuota`. Apakah ini "kuota dapur" terpisah atau batas produksi? Perlu konfirmasi (pertanyaan #11).
2. **Dine-in**: tidak ada tipe pesanan. Offline order wajib alamat. Perlu keputusan (pertanyaan #8).
3. **Pesanan H-7**: Admin bisa pesan hari ini, customer min +7 hari. Tidak konsisten (ADM-13).
4. **Refund**: tidak ada mekanisme refund otomatis. Perlu keputusan (pertanyaan #1).

---

## 7. Keputusan Bisnis yang Masih Dibutuhkan

(Dari bagian 10 REVISI-LANJUTAN-SE.NOESA.md)

1. **Refund**: jika pembayaran sudah diverifikasi lalu pelanggan meminta pembatalan, apakah uang dikembalikan manual/sebagian/tidak?
2. **H-2**: apakah batas pembatalan berarti sampai tepat 48 jam sebelum jam pengiriman, atau berdasarkan pergantian tanggal kalender?
3. **Kuota 0**: apa tindakan terhadap pesanan yang sudah masuk — tetap diproses, perlu konfirmasi pelanggan, atau dibatalkan Admin?
4. **Catatan perubahan kuota**: audit internal saja atau juga ditampilkan ke pelanggan terdampak?
5. **Pengingat bukti**: kapan ditampilkan dan kapan berhenti?
6. **Nomor WhatsApp**: nomor siapa dan di halaman mana?
7. **Tanggal libur**: apakah menutup semua produk atau ada pengecualian?
8. **Dine-in**: apakah minimum porsi berlaku, perlu jam kedatangan, alamat dihilangkan?
9. **Pesanan Admin**: wajib terhubung ke akun pelanggan atau boleh tanpa akun?
10. **Jam pesanan H-7**: apakah pelanggan dapat memilih jam seperti Admin?
11. **Kuota dapur**: sama dengan kuota penjualan atau batas produksi terpisah?
12. **Status pembayaran**: nama status final yang digunakan?
13. **Metrik Owner**: definisi "Tiket Rata-Rata" dan "botol"? Jika tidak ada, hapus.
14. **Branding**: apakah logo/warna baru sudah final?

---

## 8. Rekomendasi Urutan Pengerjaan Berikutnya

### P0 — Integritas data dan alur transaksi
1. Verifikasi owner data sync (query, filter, pagination) — OWN-21, IMG-OWN-01
2. Warning kuota 0 + catatan alasan — IMG-ADM-03, ADM-29
3. Alur pembayaran end-to-end testing — CUS-11, CUS-13
4. Foto produk upload testing — CUS-29
5. Minimum pemesanan konsistensi — CUS-10

### P1 — Operasional Admin
1. No WA — IMG-ADM-02, ADM-27
2. Pesanan manual Admin (dine-in) — ADM-11, ADM-12, ADM-13
3. Kuota dapur per tanggal — ADM-06 (butuh keputusan #11)
4. Filter pesanan dibatalkan + penjelasan status — ADM-03, ADM-07, ADM-16
5. Auto-refresh dashboard — IMG-ADM-04, ADM-28

### P2 — Pengalaman Pelanggan dan Owner
1. Info kuota penuh + pengingat bukti — IMG-CUS-01, IMG-CUS-02
2. Popup, keranjang, riwayat, navigasi pelanggan — CUS-04, CUS-12, CUS-14–CUS-23
3. Dashboard Owner, filter tren, label metrik, bukti — OWN-02–OWN-20
4. Branding se.noesa, warna, logo, background — CUS-24, CUS-25

### P3 — Penyempurnaan dan regression test
1. Samakan dialog logout dan konfirmasi aksi
2. Rapikan ikon, panah, spacing, label, state navigasi mobile
3. Uji ulang fitur Done/passed
4. Uji seluruh role di desktop dan mobile

---

## 9. Ringkasan Status

| Status | Jumlah | ID |
|--------|--------|-----|
| DONE | 38 | CUS-01,02,03,05,06,07,08,09,12,13,14,15,16,19,23,26,27,28; ADM-01,02,04,05,07,08,09,11,14,15,16,19,22,24,26; OWN-01,05,06,07,08,09,11,14,16,19; GEN-01,02; IMG-ADM-01,05 |
| PARTIAL | 18 | IMG-CUS-? (none), IMG-ADM-03,04, IMG-OWN-01; CUS-04,10,11,18,21,29; ADM-03,06,12,13,17,28,29; OWN-02,03,04,18,20,21 |
| NOT IMPLEMENTED | 22 | IMG-CUS-01,02, IMG-ADM-02; CUS-17,20,22,24,25; ADM-10,18,20,21,23,25,27; OWN-10,12,13,15,17; |
| NEEDS CONFIRMATION | 14 | Pertanyaan #1-#14 (bagian 7) |

**Catatan:** Status di atas berdasarkan audit kode aktual, bukan hanya ringkasan tahap sebelumnya. Beberapa item yang sebelumnya diklaim "Done" ternyata "Partial" atau "Not Implemented" setelah verifikasi kode.

---

## 10. Catatan Penting

1. **Jangan mengklaim Done hanya karena PHP lint/JS syntax check lulus.** Pengujian fungsional (browser, database, role) diperlukan.
2. **Tidak ada perubahan kode pada tahap audit ini.** Hanya laporan dan rekomendasi.
3. **Keputusan bisnis (bagian 7) harus dijawab sebelum implementasi P1/P2.**
4. **Migration baru diperlukan** untuk fitur yang membutuhkan kolom/tabel baru (No WA, catatan kuota 0, tipe pesanan dine-in).
5. **Regression test** wajib untuk fitur yang sebelumnya Done/passed sebelum menambah fitur baru.
