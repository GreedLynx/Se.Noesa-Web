# Keputusan Bisnis Se.Noesa — Menunggu Persetujuan Pemilik/Mitra

> Dokumen ini berisi 14 keputusan bisnis dari `docs/02-revisi-tugas/TASKS-IMPLEMENTASI-SE.NOESA.md` (TASK-01) dan `docs/02-revisi-tugas/REVISI-LANJUTAN-SE.NOESA.md` bagian 10.
> **Aturan:** pertanyaan dipertahankan apa adanya. Agent tidak memilih jawaban. Kolom "Keputusan final" dikosongkan sampai ada persetujuan tertulis.
> Status: `PENDING` / `APPROVED` / `REJECTED`.

- Dibuat: 2026-11-17
- Status keseluruhan: 14 PENDING, 0 APPROVED, 0 REJECTED

---

## #1 — Refund pembayaran terverifikasi
- **Pertanyaan:** Refund: jika pembayaran sudah diverifikasi lalu pesanan dibatalkan dalam kondisi khusus, apakah refund manual, sebagian, atau tidak ada refund?
- **Konteks audit:** CUS-06 / CUS-26 menyatakan pembatalan maksimal H-2 dan dilarang setelah verifikasi Admin. Tidak ada mekanisme refund otomatis di kode. `app/order-cancellation.php` hanya menyimpan metadata pembatalan. Catatan TASK-04: `public/pelanggan/assets/customer.js:563` saat ini menampilkan teks “refund penuh ditangani Admin di luar aplikasi”, yang menyatakan kebijakan refund penuh sebelum keputusan ini disetujui; teks tidak diubah oleh agent.
- **Opsi (jika didukung audit):** (a) refund manual penuh, (b) refund manual sebagian, (c) tanpa refund.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Backend alur pembatalan, UI konfirmasi pelanggan, teks kebijakan, test transaksi.
- **Task terdampak:** TASK-04 (sebagai kebijakan), pembatalan terkait.

## #2 — Definisi H-2
- **Pertanyaan:** Definisi H-2: tepat 48 jam sebelum jam pengiriman atau berdasarkan tanggal kalender?
- **Konteks audit:** CUS-06/CUS-26 memakai H-2; implementasi ada di backend, tetapi definisi zona waktu/batas jam belum tertulis eksplisit.
- **Opsi:** (a) tepat 48 jam sebelum jam pengiriman, (b) dua hari kalender sebelum tanggal pengiriman.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Validasi backend dan teks frontend, konsistensi timezone.
- **Task terdampak:** kebijakan pembatalan (TASK-04 area).

## #3 — Kuota menjadi 0
- **Pertanyaan:** Kuota menjadi 0: pesanan yang sudah masuk tetap diproses, perlu konfirmasi pelanggan, atau dibatalkan Admin?
- **Konteks audit:** IMG-ADM-03 / ADM-29 — Admin bisa set kuota 0 tanpa peringatan dampak; tidak ada perlakuan khusus pesanan yang sudah masuk.
- **Opsi:** (a) tetap diproses, (b) perlu konfirmasi pelanggan, (c) dibatalkan Admin.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** UI konfirmasi Admin, transaksi kuota, status pesanan lama, migration catatan (jika disetujui).
- **Task terdampak:** TASK-03 (BLOCKED).

## #4 — Catatan perubahan kuota
- **Pertanyaan:** Catatan perubahan kuota: hanya audit internal atau juga ditampilkan kepada pelanggan terdampak?
- **Konteks audit:** IMG-ADM-03 / ADM-29 — belum ada catatan alasan perubahan kuota.
- **Opsi:** (a) audit internal saja, (b) internal + ditampilkan ke pelanggan terdampak.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Migration tabel catatan, API, UI Admin, possibly UI Pelanggan.
- **Task terdampak:** TASK-03 (BLOCKED).

## #5 — Pengingat bukti pembayaran
- **Pertanyaan:** Pengingat bukti pembayaran: kapan ditampilkan dan kapan dihentikan?
- **Konteks audit:** IMG-CUS-02 — belum ada pengingat untuk pesanan yang statusnya menunggu validasi tetapi bukti belum dikirim.
- **Opsi:** (a) setelah upload bukti, (b) setelah tenggat, (c) setelah verifikasi — kombinasi sesuai keputusan.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Logika status frontend/backend, antisipasi notifikasi berulang.
- **Task terdampak:** TASK-15 (BLOCKED), TASK-04.

## #6 — Nomor WhatsApp
- **Pertanyaan:** Nomor WhatsApp: nomor siapa yang dipakai dan pada halaman mana ditampilkan?
- **Konteks audit:** IMG-ADM-02 / ADM-27 — tidak ada nomor WA di aplikasi.
- **Opsi:** nomor Admin / CS / Owner; halaman yang disetujui.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Sumber konfigurasi, validasi format, tautan wa.me, lokasi tampil.
- **Task terdampak:** TASK-08 (BLOCKED).

## #7 — Tanggal libur
- **Pertanyaan:** Tanggal libur: menutup semua produk atau ada produk/acara yang dikecualikan?
- **Konteks audit:** IMG-ADM-01 sudah diimplementasikan, tetapi kebijakan pengecualian belum ditetapkan.
- **Opsi:** (a) semua produk tutup, (b) ada pengecualian produk/acara.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Logika pemilihan tanggal Pelanggan/Admin.
- **Task terdampak:** kebijakan libur.

## #8 — Dine-in
- **Pertanyaan:** Dine-in: apakah minimum porsi berlaku, apakah perlu jam kedatangan, dan apakah alamat dihilangkan?
- **Konteks audit:** ADM-12 — offline order masih wajib alamat; belum ada tipe dine-in.
- **Opsi:** sesuai keputusan pemilik untuk cada sub-pertanyaan.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Form Admin, validasi backend, kemungkinan migration tipe pesanan.
- **Task terdampak:** TASK-09 (BLOCKED).

## #9 — Pesanan manual Admin
- **Pertanyaan:** Pesanan manual Admin: wajib terhubung ke akun pelanggan atau boleh memakai nama/nomor tanpa akun?
- **Konteks audit:** ADM-11 sudah ada via `create_offline_order`; identitas pelanggan belum diatur.
- **Opsi:** (a) wajib akun, (b) nama/nomor tanpa akun diperbolehkan.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Validasi backend form manual, relasi user.
- **Task terdampak:** TASK-09 (BLOCKED).

## #10 — Jam pesanan H-7
- **Pertanyaan:** Jam pesanan H-7: apakah pelanggan boleh memilih jam seperti Admin?
- **Konteks audit:** ADM-13 — Admin bisa pesan H-7 (min hari ini), Pelanggan min +7 hari; pilihan jam belum konsisten.
- **Opsi:** (a) Pelanggan boleh memilih jam, (b) tidak, (c) dengan batas kapasitas tertentu.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Date picker/UI Pelanggan & Admin, validasi backend.
- **Task terdampak:** TASK-09 (BLOCKED).

## #11 — Kuota dapur
- **Pertanyaan:** Kuota dapur: sama dengan kuota penjualan atau batas produksi terpisah?
- **Konteks audit:** ADM-06 — `daily_quota_snapshot.maxKitchen` = 80% dari total kuota; makna belum jelas.
- **Opsi:** (a) sama dengan kuota penjualan, (b) batas produksi terpisah.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Rumus perhitungan, filter tanggal, label UI, potensi migration.
- **Task terdampak:** TASK-10 (BLOCKED).

## #12 — Status pembayaran
- **Pertanyaan:** Status pembayaran: istilah final yang dipakai agar status menunggu pembayaran, menunggu verifikasi, diterima, dan ditolak tidak tumpang tindih?
- **Konteks audit:** CUS-11, ADM-03/07/16 — 6 nilai status di DB; label UI belum seragam sepenuhnya.
- **Opsi:** daftar istilah final dari pemilik.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Label UI, filter, badge, API. **Tidak mengubah nilai DB tanpa migration & rencana kompatibilitas.**
- **Task terdampak:** TASK-04, TASK-11, TASK-15, TASK-20.

## #13 — Metrik Owner
- **Pertanyaan:** Metrik Owner: definisi "Rata-rata Pesanan" dan "Botol minuman"; jika tidak memiliki makna bisnis, apakah dihapus?
- **Konteks audit:** OWN-09 / OWN-11 — label sudah diganti, tetapi definisi metrik belum dikonfirmasi.
- **Opsi:** (a) beri definisi & pertahankan, (b) hapus metrik.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Dashboard Owner, query agregasi, test.
- **Task terdampak:** TASK-20 (bagian metrik).

## #14 — Branding
- **Pertanyaan:** Branding: apakah logo dan warna baru sudah final, dan aset resminya tersedia?
- **Konteks audit:** CUS-24/CUS-25 — brand "Se.Noesa" belum jadi "se.noesa"; aset logo/warna resmi belum tersedia.
- **Opsi:** konfirmasi aset final atau tetap memakai aset existing.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Teks brand, token warna, logo, background, test visual.
- **Task terdampak:** TASK-19 (BLOCKED).

---

## Keputusan tambahan yang ditemukan saat implementasi

### DECISION-NEW-01 — Penonaktifkan produk & snapshot histori (TASK-13)
- **Pertanyaan:** Apakah aplikasi perlu kemampuan menonaktifkan produk (bukan menghapus)? Jika ya, bagaimana perilaku pesanan lama: menampilkan nama produk saat pesanan dibuat (snapshot) atau nama produk saat ini, dan apakah perlu keterangan “produk dinonaktifkan” pada detail pesanan?
- **Konteks temuan:** TABEL `produk` **tidak memiliki kolom `aktif`** dan `detail_pesanan` **tidak memiliki kolom snapshot nama/detail** (verifikasi `information_schema` pada 2026-11-18: 0 kolom). FK `detail_pesanan.produk_id → produk.id` mencegah penghapusan produk yang sudah dipesan, sehingga baris histori tidak hilang. Namun detail pesanan Admin/Owner/Pelanggan mengambil `produk.nama` saat ini lewat `JOIN produk`, sehingga penggantian nama produk akan mengubah tampilan pesanan lama.
- **Opsi:** (a) tambah kolom `aktif` + snapshot nama di `detail_pesanan` lewat migration, (b) cukup keterangan berbasis data yang ada, (c) tidak diimplementasikan.
- **Keputusan final:** _(kosong — menunggu pemilik)_
- **Status:** PENDING
- **Dampak:** Migration aman + backfill snapshot, API detail, UI Admin/Owner/Pelanggan, test histori.
- **Task terdampak:** TASK-13 (BLOCKED).
