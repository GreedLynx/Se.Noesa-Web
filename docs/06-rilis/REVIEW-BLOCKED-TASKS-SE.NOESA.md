# Review Task BLOCKED & Audit Lanjutan TASK-20 — Se.Noesa

- Tanggal: 2026-11-18
- Branch / HEAD: `feature/full-stack` / `43da86d`
- Sifat dokumen: **REVIEW ONLY**. Status task tidak diubah; tidak ada kode/migration/database yang disentuh.
- Sumber: `docs/02-revisi-tugas/TASKS-IMPLEMENTASI-SE.NOESA.md`, `docs/04-implementasi/PROGRESS-IMPLEMENTASI-SE.NOESA.md`, `docs/05-keputusan-bisnis/KEPUTUSAN-BISNIS-SE.NOESA.md`, dan inspeksi kode statis.

---

## 1. Ringkasan klasifikasi

| Task | Status saat ini | Dependency sebenarnya | Jenis hambatan | Dapat dikerjakan sebagian tanpa keputusan? |
|---|---|---|---|---|
| TASK-03 | BLOCKED | #3 (tindakan kuota 0) + #4 (catatan) + DB test untuk migration | **Keputusan bisnis**, plus teknis (migration) | Ya: audit + dialog konfirmasi + hitung dampak |
| TASK-08 | BLOCKED | #6 (nomor & halaman) | **Keputusan bisnis** | Ya: mekanisme konfigurasi + validasi + generator `wa.me` |
| TASK-09 | BLOCKED | #8, #9, #10 (dine-in, identitas, jam) | **Keputusan bisnis + teknis** (tipe pesanan/schema) | Sebagian besar tidak: implementasi menunggu keputusan |
| TASK-10 | BLOCKED | #11 (definisi kuota dapur) | **Keputusan bisnis** | Sebagian: dokumentasi/pemetaan; rumus tidak diubah |
| TASK-13 | BLOCKED | DECISION-NEW-01 + DB test untuk migration | **Keputusan bisnis + teknis** | Ya: audit (sudah selesai); implementasi tidak |
| TASK-15 | BLOCKED | #5 (waktu pengingat) + #12 (istilah status) | **Keputusan bisnis** | Tidak: seluruh perilaku bergantung keputusan |
| TASK-19 | BLOCKED | #14 (aset branding final) | **Keputusan bisnis / aset** | Tidak: lokasi & aset harus disetujui |
| TASK-22 | BLOCKED | Semua task implementasi relevan selesai (7 BLOCKED, 1 PARTIAL) + test environment | **Dependency + teknis** (tanpa DB terpisah & browser automation) | Ya: regresi terbatas pada fitur DONE |
| TASK-23 | BLOCKED | TASK-22 | **Dependency** | Dokumen readiness sudah ada sebagai draft NO-GO |

**Semua status di atas tetap BLOCKED/PARTIAL.** Tidak ada yang diubah menjadi DONE tanpa implementasi dan bukti uji.

---

## 2. Analisis per task

### TASK-03 — Warning & audit trail kuota 0

- **Dependency deklaratif:** TASK-01 keputusan #3 dan #4.
- **Dependency aktual:** `public/admin/api/kuota-harian.php:61-75` menerima `kuota = 0` dan langsung `daily_quota_write_range` dalam transaksi + lock. Tidak ada query pesanan terdampak, tidak ada field alasan, tidak ada dialog. Jika #4 memilih catatan di DB, diperlukan migration baru.
- **Jenis hambatan:** keputusan bisnis (#3, #4) + hambatan teknis (migration tidak boleh dijalankan ke DB remote; tidak ada DB test).
- **Yang bisa dikerjakan tanpa keputusan:** audit alur (selesai), dialog konfirmasi dengan tombol batal, query read-only jumlah/daftar pesanan terdampak per produk+tanggal. **Namun** acceptance criteria “catatan tersimpan sesuai kebijakan” dan “perilaku pesanan lama sesuai keputusan” tidak bisa dipenuhi ⇒ tetap BLOCKED.
- **Rekomendasi urutan:** setelah #3 & #4 dijawab, siapkan migration di DB lokal, lalu implementasi UI+API dalam satu checkpoint.

### TASK-08 — Nomor WhatsApp

- **Dependency:** #6 (nomor siapa + halaman mana).
- **Dependency aktual:** tidak ada konfigurasi WA; `config/config.php` hanya DB + mail. Perlu sumber konfigurasi (env atau tabel setting) dan lokasi tampil.
- **Jenis hambatan:** murni keputusan bisnis. Validasi E.164 dan format `wa.me` adalah teknis dan dapat disiapkan.
- **Yang bisa dikerjakan tanpa keputusan:** helper konfigurasi + validasi nomor + generator tautan + test unit. **Tidak boleh** menampilkan nomor dummy di UI produksi ⇒ tetap BLOCKED.
- **Risiko:** nomor pribadi hardcode; tautan salah format di mobile.

### TASK-09 — Pesanan manual & dine-in

- **Dependency:** #8, #9, #10.
- **Dependency aktual:** `app/offline-orders.php` sudah ada (status `diproses`, sumber `offline`), tetapi: alamat wajib (`:15-19`), tidak ada `tipe_pesanan`, `user_id` tidak diisi (`:83-100`), dan Admin bisa hari ini sementara Pelanggan min +7 hari. Dine-in memerlukan keputusan + kemungkinan migration kolom tipe.
- **Jenis hambatan:** keputusan bisnis + teknis (schema).
- **Yang bisa dikerjakan tanpa keputusan:** audit (selesai) dan test pesanan manual delivery yang sudah ada di DB terpisah. Implementasi dine-in tidak bisa ditebak.
- **Catatan:** jangan mengubah `create_offline_order` sebelum #8/#9/#10; kompatibilitas pesanan offline lama wajib dijaga.

### TASK-10 — Kuota dapur

- **Dependency:** #11.
- **Dependency aktual:** `daily_quota_snapshot` (`app/daily-quota.php:69-78`) memakai `maxKitchen = intdiv(capacity*4,5)` dan `kitchenUsed = SUM(terpakai)`. Tidak ada tabel kapasitas dapur tersendiri. TASK-10 secara eksplisit melarang mengubah rumus 80% sebelum definisi disetujui.
- **Jenis hambatan:** murni keputusan bisnis.
- **Yang bisa dikerjakan tanpa keputusan:** dokumentasi pemetaan kuota produk → kuota harian → kapasitas dapur (sudah ada di `docs/03-audit/AUDIT-CONCURRENCY-KUOTA.md`), tidak lebih.
- **Risiko:** mengubah 80% tanpa keputusan akan merusak validasi checkout (`daily_quota_validate:104`).

### TASK-13 — Keterangan produk dinonaktifkan

- **Dependency deklaratif:** TASK-00 (selesai).
- **Dependency aktual:** DECISION-NEW-01 + migration + DB test. Verifikasi `information_schema`: `produk.aktif` tidak ada, `detail_pesanan` tidak punya snapshot. FK `produk_id` mencegah delete, tetapi rename mengubah tampilan histori.
- **Jenis hambatan:** keputusan bisnis + teknis (migration & backfill).
- **Yang bisa dikerjakan tanpa keputusan:** audit (sudah selesai dan ter-checkpoint `055d350`).
- **Risiko:** backfill snapshot ke production dilarang; migration harus diuji di lokal/staging.

### TASK-15 — Pengingat bukti

- **Dependency:** #5 dan #12, plus TASK-04 (selesai).
- **Dependency aktual:** status `menunggu_validasi` mensyaratkan bukti di `admin/api/orders.php:197-208`, sehingga pengingat untuk kasus normal harus dimulai lebih awal (saat `menunggu_pembayaran`). Tanpa #5, tidak ada titik mulai/berhenti yang disepakati; tanpa #12, istilah status final belum pasti.
- **Jenis hambatan:** murni keputusan bisnis.
- **Yang bisa dikerjakan tanpa keputusan:** tidak ada implementasi yang aman; evaluasi data read-only sudah tercatat di TASK-04.

### TASK-19 — Branding

- **Dependency:** #14.
- **Dependency aktual:** aset logo/warna belum ada di repo; revisi meminta `se.noesa` lowercase dan audit warna. Lokasi perubahan harus disetujui.
- **Jenis hambatan:** keputusan bisnis + ketersediaan aset.
- **Yang bisa dikerjakan tanpa keputusan:** tidak ada perubahan identitas visual yang aman; agent tidak boleh menggambar logo baru.
- **Risiko:** perubahan spekulatif merusak konsistensi brand.

### TASK-22 — Regression lintas role

- **Dependency deklaratif:** semua task implementasi relevan selesai.
- **Dependency aktual:** 7 task masih BLOCKED, TASK-20 PARTIAL; tambahan hambatan teknis: tidak ada DB terpisah untuk mutasi dan tidak ada browser automation.
- **Jenis hambatan:** dependency + teknis.
- **Yang bisa dikerjakan tanpa keputusan:** regression terbatas pada 14 task DONE — lint (78 PHP + 17 JS/CJS) sudah lolos, tetapi ini **bukan** pengganti test matrix role. Perlu prosedur DB lokal (bagian 4 dokumen ini) dan browser test.
- **Syarat agar eligible:** semua task BLOCKED terselesaikan, TASK-20 DONE, DB isolat tersedia, browser automation siap.

### TASK-23 — Release readiness

- **Dependency:** TASK-22.
- **Dependency aktual:** TASK-22 belum bisa dijalankan penuh. Dokumen `docs/06-rilis/RELEASE-READINESS-SE.NOESA.md` sudah dibuat sebagai **draft NO-GO** sesuai master prompt §10, tetapi keputusan final perlu diulang setelah TASK-22.
- **Jenis hambatan:** dependency.
- **Yang bisa dikerjakan tanpa keputusan:** pemutakhiran draft readiness dengan hasil regression terbatas (sudah ada).

---

## 3. Lampiran Tahap 4 — Audit lanjutan TASK-20 (PARTIAL)

> Sesuai batasan, **tidak ada akses ke database produksi** pada tahap review ini. Temuan berikut berasal dari inspeksi kode statis `public/owner/dashboard.php`, `public/owner/api/dashboard.php`, `public/assets/js/app.js`, dan `database/migrations/010_stateless_quota_indexes.sql`.

### 3.1 Metrik Owner & sumber datanya

| Tampilan | Sumber | Penilaian |
|---|---|---|
| `Rata-rata Pesanan` (`#tiket`) | `owner/api/dashboard.php:101` → `omzet / COUNT(pesanan status='selesai')` | Angka **rata-rata nilai pesanan dalam Rupiah**, bukan rata-rata jumlah pesanan. Label menyesatkan; definisi #13 belum ada. |
| `KUANTITAS PAX` / `TOTAL TERJUAL` (`#items`) | `owner/api/dashboard.php:53-57` → `SUM(detail_pesanan.qty)` untuk pesanan `selesai` | Ini **jumlah porsi**, bukan “PAX” (orang). Label tidak sesuai satuan data. |
| `Botol minuman` (`app.js:415`) | `kategori.n` → `SUM(detail_pesanan.qty)` kategori minuman | Satuan sebenarnya porsi, bukan botol. Label OWN-11 masih bermasalah meski sudah diganti dari “botol”. |
| `STATUS LUNAS — 100% Lunas` | `public/owner/dashboard.php:19` — **teks statis** | Tidak bersumber data. Berpotensi menyesatkan bila ada pesanan menunggu pembayaran. |
| `Omzet`, `growth`, `chart`, `kategori`, `top` | Filter `status='selesai'` + `created_at::date BETWEEN` | Konsisten satu sama lain (periode & status sama). `growth` dihitung dari periode sebelumnya dengan rumus yang sama. |

### 3.2 Konsistensi angka dengan sumber data

- Semua agregasi memakai `status = 'selesai'` dan rentang `created_at::date` yang sama, sehingga **konsisten internal**.
- Yang **tidak konsisten** adalah pelabelan satuan (“PAX”, “Botol minuman”) dan klaim statis “100% Lunas”. Ini bukan bug query, melainkan definisi metrik (#13).
- `selected` (tanggal/hari/bulan) sudah membatasi `startDate`/`endDate`, dan query chart mengikuti variabel yang sama ⇒ filter sudah benar secara kode (belum diverifikasi di browser).

### 3.3 Potensi query mahal

- `created_at::date BETWEEN ? AND ?` (`owner/api/dashboard.php:24-26, 53-57, 75-82, 84-93`) **mengubah kolom**, sehingga indeks `idx_pesanan_created_status (created_at, status)` dari migrasi 010 tidak optimal untuk predikat ini.
- Query `top` melakukan `GROUP BY pr.id` dengan `SUM(d.qty * d.harga)` dan `ORDER BY n DESC LIMIT 5`; `kategori` melakukan `GROUP BY pr.kategori` — keduanya memindai detail pesanan pada rentang tanggal.
- Chart bulanan memakai `generate_series(...) LEFT JOIN ... ON date_trunc('month', s.created_at) = m` — `date_trunc` pada kolom juga bisa menghalangi indeks.
- **Rekomendasi review (tidak dieksekusi):** ukur dengan `EXPLAIN (ANALYZE, BUFFERS)` di DB lokal/staging pada dataset realistis. Perbaikan yang mungkin: predikat rentang timestamp (`created_at >= start AND created_at < end + interval '1 day'`) atau expression index sesuai zona waktu Asia/Jakarta. Jangan diubah sebelum ada pengukuran.

### 3.4 QA visual & design system

- Belum dilakukan: tidak ada browser automation; perubahan TASK-20 (hint tanggal kuota, ikon dihapus, panah back) hanya diverifikasi statis + unit test string tanggal.
- Perlu dicek: desktop ≥1280px, tablet, mobile ≤430px; overflow pada kartu detail; kontras teks hint; konsistensi ikon setelah `.ic` dihapus; posisi tombol kembali.
- Design system: warna memakai token CSS existing (`--green-dark`, `--line`); tidak ada token baru yang ditambahkan. Aset branding masih menunggu #14.

### 3.5 Prosedur DB isolat untuk menyelesaikan TASK-20 & test lanjutan

1. Siapkan PostgreSQL lokal (container atau install) **terpisah** dari Supabase remote; jangan arahkan `.env` aplikasi ke remote.
2. Buat file konfigurasi test terpisah (mis. `.env.test`, tidak di-commit) dengan kredensial lokal.
3. Jalankan `database/schema.sql` + seluruh `database/migrations/*.sql` pada DB lokal mengikuti `scripts/migrate.php`.
4. Seed data sintetis (tidak memakai data pelanggan nyata); gunakan `database/data-dummy.sql` sebagai referensi tetapi ganti identitas dengan fixture.
5. Jalankan query dashboard dengan `EXPLAIN (ANALYZE, BUFFERS)` dan bandingkan angka agregasi terhadap fixture yang diketahui.
6. Semua test mutasi (checkout, verifikasi, kuota, upload) hanya dilakukan pada DB lokal ini.
7. Jangan pernah menjalankan migration atau DML pada Supabase remote tanpa persetujuan eksplisit tertulis.

### 3.6 Kesimpulan TASK-20

- Status tetap **PARTIAL**. Bagian yang bisa diselesaikan tanpa keputusan: QA visual & pengukuran query (dengan DB lokal/browser).
- Bagian yang menunggu #13: definisi/penamaan metrik dan penghapusan teks statis “100% Lunas”.
- Tidak ada perubahan kode atau database yang dilakukan pada tahap review ini.
