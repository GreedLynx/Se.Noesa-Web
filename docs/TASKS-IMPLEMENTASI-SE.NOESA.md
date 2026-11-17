# Se.Noesa — Task Board Implementasi Bertahap

> Dokumen eksekusi untuk coding agent. Kerjakan **satu task dalam satu sesi**, sesuai urutan dan dependency. Jangan mengerjakan seluruh task sekaligus.
>
> Sumber utama:
> - `AUDIT-REVISI-TAHAP-2.md`
> - `REVISI-LANJUTAN-SE.NOESA.md`
> - Kondisi aktual repository Se.Noesa-Web
>
> Stack yang tercatat pada audit: PHP 8.1 native, PostgreSQL/Supabase via PDO, HTML/CSS/JavaScript vanilla. Jangan mengganti stack.

---

## A. Aturan kerja wajib untuk agent

1. Baca `AGENTS.md`, `AUDIT-REVISI-TAHAP-2.md`, `REVISI-LANJUTAN-SE.NOESA.md`, dan task yang sedang dikerjakan sebelum menyentuh kode.
2. Kerjakan hanya **satu Task ID** yang diminta user. Jangan lanjut ke task berikutnya secara otomatis.
3. Jangan mengerjakan task berstatus `BLOCKED` sebelum keputusan bisnis terkait disetujui user/mitra.
4. Jangan mengubah fitur berstatus DONE kecuali task secara eksplisit meminta regression test atau ditemukan bug nyata.
5. Audit implementasi dan dependency terlebih dahulu. Jangan menebak struktur file, tabel, endpoint, atau status.
6. Jangan membuat ulang aplikasi, mengganti stack, atau melakukan refactor luas yang tidak diperlukan.
7. Perubahan database wajib memakai migration yang aman, dapat diulang sesuai pola repository, dan mempertahankan data lama.
8. Validasi role di backend. Menyembunyikan tombol di frontend bukan kontrol akses yang cukup.
9. Operasi pesanan/kuota/pembayaran harus menjaga integritas transaksi, concurrency, dan audit trail.
10. Jangan menampilkan secret, credential, token, atau data pelanggan di log/laporan.
11. Jangan push, merge, reset, rebase, atau mengubah remote Git tanpa instruksi eksplisit.
12. Setelah satu task selesai, jalankan test yang relevan, periksa `git diff`, perbarui status task, lalu **berhenti**.
13. Jika task membutuhkan keputusan, data produksi, akses layanan, atau spesifikasi yang belum tersedia, jangan membuat asumsi. Tandai `BLOCKED` dan tulis pertanyaan yang spesifik.
14. Jangan klaim DONE hanya karena PHP lint atau JS syntax check lolos. DONE memerlukan pengujian fungsional sesuai task.

### Format laporan setiap task

Pada akhir pengerjaan, laporkan:
- Task ID dan status: DONE / PARTIAL / BLOCKED / FAILED.
- Ringkasan perubahan.
- File yang dibuat/diubah.
- Migration yang dibuat (jika ada).
- Test yang dijalankan beserta hasil aktual.
- Skenario yang belum dapat diuji dan alasannya.
- Risiko/regresi yang perlu diperhatikan.
- ID revisi yang tercakup.
- Konfirmasi bahwa tidak ada task lain yang ikut dikerjakan.

---

## B. Status task

- `READY`: boleh dikerjakan jika dependency terpenuhi.
- `BLOCKED`: menunggu keputusan bisnis atau prasyarat.
- `TODO`: belum dikerjakan.
- `DONE`: sudah diimplementasikan dan diuji.
- `PARTIAL`: baru sebagian selesai atau pengujian belum lengkap.

Jangan mengubah status menjadi DONE tanpa bukti uji.

---

## C. Urutan task

### TASK-00 — Rekonsiliasi laporan audit

**Status:** TODO  
**Prioritas:** P0 administratif  
**Dependency:** Tidak ada  
**Tipe:** Audit dokumentasi saja; tidak mengubah kode.

Tujuan:
Memastikan daftar ID dan angka ringkasan pada `AUDIT-REVISI-TAHAP-2.md` konsisten sebelum task implementasi dimulai.

Langkah:
1. Hitung ulang seluruh ID revisi dari tabel per kategori.
2. Bandingkan jumlah aktual dengan ringkasan 38 DONE, 18 PARTIAL, 22 NOT IMPLEMENTED, 14 NEEDS CONFIRMATION.
3. Periksa duplikasi ID lintas bagian, item yang belum masuk rekap, dan status yang saling bertentangan.
4. Perhatikan bahwa bagian 9 laporan audit tampak tidak konsisten dengan daftar status pada bagian 3. Jangan mengubah status berdasarkan perkiraan.
5. Perbarui bagian ringkasan atau tambahkan catatan koreksi dengan jejak perubahan.
6. Jangan mengubah kode aplikasi.

Acceptance criteria:
- Setiap ID memiliki satu status utama yang jelas.
- Total status sama dengan jumlah ID unik.
- Konflik status dijelaskan.
- Tidak ada kode aplikasi yang berubah.

Output:
- Perbaiki `AUDIT-REVISI-TAHAP-2.md` atau buat `AUDIT-REVISI-TAHAP-2-CORRECTION.md`.

---

### TASK-01 — Siapkan keputusan bisnis

**Status:** BLOCKED  
**Prioritas:** P0  
**Dependency:** TASK-00

Tujuan:
Membuat catatan keputusan bisnis yang harus disetujui sebelum perilaku transaksi/operasional diubah.

Buat `KEPUTUSAN-BISNIS-SE.NOESA.md` berisi 14 keputusan berikut. Pertahankan pertanyaan; jangan menjawab atas nama pemilik/mitra.

1. Refund: jika pembayaran sudah diverifikasi lalu pesanan dibatalkan dalam kondisi khusus, apakah refund manual, sebagian, atau tidak ada refund?
2. Definisi H-2: tepat 48 jam sebelum jam pengiriman atau berdasarkan tanggal kalender?
3. Kuota menjadi 0: pesanan yang sudah masuk tetap diproses, perlu konfirmasi pelanggan, atau dibatalkan Admin?
4. Catatan perubahan kuota: hanya audit internal atau juga ditampilkan kepada pelanggan terdampak?
5. Pengingat bukti pembayaran: kapan ditampilkan dan kapan dihentikan?
6. Nomor WhatsApp: nomor siapa yang dipakai dan pada halaman mana ditampilkan?
7. Tanggal libur: menutup semua produk atau ada produk/acara yang dikecualikan?
8. Dine-in: apakah minimum porsi berlaku, apakah perlu jam kedatangan, dan apakah alamat dihilangkan?
9. Pesanan manual Admin: wajib terhubung ke akun pelanggan atau boleh memakai nama/nomor tanpa akun?
10. Jam pesanan H-7: apakah pelanggan boleh memilih jam seperti Admin?
11. Kuota dapur: sama dengan kuota penjualan atau batas produksi terpisah?
12. Status pembayaran: istilah final yang dipakai agar status menunggu pembayaran, menunggu verifikasi, diterima, dan ditolak tidak tumpang tindih?
13. Metrik Owner: definisi “Rata-rata Pesanan” dan “Botol minuman”; jika tidak memiliki makna bisnis, apakah dihapus?
14. Branding: apakah logo dan warna baru sudah final, dan aset resminya tersedia?

Untuk setiap keputusan, sediakan:
- Pertanyaan.
- Konteks temuan audit.
- Opsi yang perlu dipilih (hanya jika memang didukung audit).
- Keputusan final (kosong sampai disetujui).
- Dampak ke frontend/backend/database/test.
- Task yang diblokir oleh keputusan tersebut.

Acceptance criteria:
- Semua 14 keputusan tercatat.
- Tidak ada keputusan yang diisi dengan asumsi agent.
- Task terkait ditandai BLOCKED sampai keputusan tersedia.

---

## D. P0 — Integritas data dan transaksi

### TASK-02 — Verifikasi sinkronisasi data Owner dan Admin

**Status:** TODO  
**Prioritas:** P0  
**Dependency:** TASK-00  
**Revisi:** IMG-OWN-01, OWN-21

Audit:
- `public/owner/api/orders.php`
- `public/admin/api/orders.php`
- Query, filter tanggal/status, pagination, detail pesanan, dan relasi data.

Implementasi hanya jika ditemukan perbedaan nyata:
1. Pastikan Owner membaca sumber data yang sama dengan Admin.
2. Pastikan filter dan pagination tidak menghilangkan pesanan yang seharusnya terlihat.
3. Pastikan detail pesanan dan notifikasi terkait konsisten.
4. Pertahankan Owner read-only pada backend dan frontend.
5. Jangan memberi Owner akses ke endpoint mutasi Admin.

Acceptance criteria:
- Dataset Owner dan Admin konsisten untuk filter/periode yang sama, dengan pengecualian field yang memang dibatasi role.
- Owner tidak dapat melakukan mutasi melalui request langsung.
- Uji data kosong, banyak halaman, filter tanggal, dan status.
- Catat bukti pengujian.

### TASK-03 — Warning dan audit trail ketika kuota diubah menjadi 0

**Status:** BLOCKED  
**Prioritas:** P0  
**Dependency:** TASK-01 keputusan #3 dan #4  
**Revisi:** IMG-ADM-03, ADM-29

Tujuan:
Mencegah Admin mengubah kuota menjadi 0 tanpa memahami dampak terhadap pesanan yang sudah masuk.

Langkah setelah keputusan tersedia:
1. Audit alur update kuota di API dan UI Admin.
2. Ambil daftar/jumlah pesanan terdampak berdasarkan produk dan tanggal.
3. Tampilkan dialog konfirmasi sebelum perubahan disimpan.
4. Tampilkan dampak perubahan secara spesifik.
5. Simpan catatan alasan perubahan sesuai keputusan.
6. Jika catatan disimpan di database, buat migration aman dan dokumentasikan.
7. Terapkan transaksi/locking bila dibutuhkan agar data dampak dan perubahan kuota konsisten.
8. Jangan membatalkan atau mengubah pesanan lama kecuali keputusan bisnis secara eksplisit memerintahkannya.

Acceptance criteria:
- Admin melihat dampak sebelum menyimpan kuota 0.
- Perubahan dapat dibatalkan dari dialog.
- Catatan tersimpan sesuai kebijakan.
- Perilaku terhadap pesanan yang sudah masuk sesuai keputusan.
- Uji kuota 0, kuota nonzero, tidak ada pesanan, dan ada beberapa pesanan terdampak.

### TASK-04 — Audit dan uji alur pembayaran end-to-end

**Status:** TODO  
**Prioritas:** P0  
**Dependency:** TASK-00; TASK-01 keputusan #12 jika mengubah definisi status  
**Revisi:** CUS-11, CUS-13

Fokus audit dan pengujian:
1. Pembuatan pesanan dan status awal.
2. Tampilan instruksi pembayaran/QRIS.
3. Batas waktu pembayaran dan perilaku ketika tenggat terlewati.
4. Upload bukti pembayaran.
5. Penggantian bukti jika diizinkan.
6. Verifikasi Admin.
7. Penolakan dan alasan penolakan.
8. Tampilan status dan alasan pada sisi Pelanggan.
9. Dampak setiap transisi terhadap kuota.

Aturan:
- Jangan mengubah nama/status atau kebijakan timeout tanpa bukti dan keputusan.
- Jangan mengimplementasikan refund otomatis.
- Jangan menganggap upload sukses hanya karena file ada di storage; pastikan database dan UI juga konsisten.

Acceptance criteria:
- Ada test matrix untuk seluruh jalur sukses/gagal.
- Status tidak melompat atau saling bertentangan.
- Bukti terkait dengan pesanan yang benar.
- Kuota konsisten pada setiap transisi.
- Hasil browser/API/database dicatat.

### TASK-05 — Konsistensi minimum pemesanan per produk

**Status:** TODO  
**Prioritas:** P0  
**Dependency:** TASK-00  
**Revisi:** CUS-10

Audit:
- `produk.min_pesan`
- Form tambah/edit produk Admin.
- `public/pelanggan/assets/customer.js`
- `public/pelanggan/api/orders.php` atau endpoint checkout terkait.
- Validasi keranjang dan pesanan manual.

Langkah:
1. Temukan sumber nilai minimum yang sebenarnya.
2. Periksa apakah batas minimum Admin form (audit menyebut min ≥ 4) memang aturan bisnis atau hanya batas UI.
3. Samakan validasi frontend dan backend.
4. Pastikan perubahan jumlah dengan tombol minus tidak bisa melewati minimum.
5. Pastikan request API yang dimanipulasi tetap ditolak.
6. Jangan mengubah aturan minimum tanpa keputusan pemilik jika nilainya belum ditetapkan.

Acceptance criteria:
- Nilai minimum yang sama digunakan pada UI dan backend.
- Validasi berlaku untuk setiap produk secara terpisah.
- Pesan kesalahan menjelaskan minimum yang benar.
- Uji tambah, kurang, hapus item, checkout, dan request API langsung.

### TASK-06 — Uji upload foto produk Admin

**Status:** TODO  
**Prioritas:** P0  
**Dependency:** TASK-00  
**Revisi:** CUS-29

Audit dan test:
1. Upload file gambar valid dengan ukuran yang diizinkan.
2. Upload format yang tidak didukung.
3. Upload file terlalu besar.
4. Upload dengan nama file khusus/duplikat.
5. Kegagalan storage/permission.
6. Simpan dan baca path gambar dari database.
7. Tampilkan gambar pada katalog Pelanggan dan daftar produk Owner/Admin.
8. Pastikan file gagal tidak meninggalkan record/path rusak.

Acceptance criteria:
- File valid tersimpan dan tampil di lokasi yang benar.
- File invalid ditolak dengan pesan yang jelas.
- Tidak ada path traversal atau akses file yang tidak semestinya.
- Batas MIME dan ukuran diperiksa di backend.
- Test berhasil dan gagal dicatat.

### TASK-07 — Uji concurrency kuota saat checkout

**Status:** TODO  
**Prioritas:** P0  
**Dependency:** TASK-02, TASK-05  
**Revisi:** CUS-05, CUS-08, CUS-10; risiko race condition audit

Audit `app/daily-quota.php`, khususnya `daily_quota_lock_tables()` dan transaksi checkout.

Langkah:
1. Telusuri lock, isolation, urutan query, commit, dan rollback.
2. Simulasikan dua atau lebih checkout bersamaan pada kuota yang hampir habis.
3. Pastikan total pesanan tidak melebihi kuota.
4. Pastikan kegagalan checkout tidak meninggalkan pengurangan kuota atau detail pesanan parsial.
5. Perbaiki hanya jika test membuktikan race condition atau inkonsistensi.

Acceptance criteria:
- Kuota tidak dapat oversold pada request bersamaan.
- Transaksi rollback bersih ketika salah satu validasi gagal.
- Hasil uji concurrency terdokumentasi.

---

## E. P1 — Operasional Admin

### TASK-08 — Tambahkan nomor WhatsApp yang dapat dikonfigurasi

**Status:** BLOCKED  
**Prioritas:** P1  
**Dependency:** TASK-01 keputusan #6  
**Revisi:** IMG-ADM-02, ADM-27

Langkah setelah keputusan:
1. Tentukan sumber nomor (konfigurasi environment atau pengaturan aplikasi) sesuai keputusan.
2. Jangan hardcode nomor pribadi di banyak file.
3. Validasi format nomor dan buat tautan WhatsApp yang benar.
4. Tampilkan hanya pada lokasi yang disetujui.
5. Jangan mengekspos nomor yang tidak disetujui.

Acceptance criteria:
- Nomor dapat diubah tanpa mengedit banyak komponen.
- Link WhatsApp valid di mobile dan desktop.
- Tidak ada nomor dummy di production.

### TASK-09 — Selesaikan alur pesanan manual dan dine-in

**Status:** BLOCKED  
**Prioritas:** P1  
**Dependency:** TASK-01 keputusan #8, #9, #10  
**Revisi:** ADM-11, ADM-12, ADM-13

Catatan audit:
- Fitur pesanan manual sudah ada melalui `create_offline_order`.
- Offline order masih mewajibkan alamat.
- Belum ada tipe pesanan dine-in.
- Batas tanggal Admin dan Pelanggan tidak konsisten.

Langkah:
1. Pertahankan fitur pesanan manual yang sudah ada.
2. Terapkan tipe dine-in/delivery hanya sesuai keputusan.
3. Atur kewajiban alamat, jam kedatangan, minimum porsi, dan identitas pelanggan sesuai keputusan.
4. Samakan aturan rentang tanggal dan pilihan jam Admin/Pelanggan sesuai keputusan.
5. Buat migration hanya jika struktur data memang memerlukannya.
6. Uji pesanan manual delivery dan dine-in secara terpisah.

Acceptance criteria:
- Pesanan manual lama tetap kompatibel.
- Field yang wajib sesuai tipe pesanan.
- Aturan tanggal/jam konsisten dengan kebijakan yang disetujui.
- Data pesanan tetap masuk ke rekap produksi dan laporan.

### TASK-10 — Definisikan dan implementasikan kuota dapur

**Status:** BLOCKED  
**Prioritas:** P1  
**Dependency:** TASK-01 keputusan #11  
**Revisi:** ADM-06

Catatan audit:
- `daily_quota_snapshot` memiliki `maxKitchen` sebesar 80%.
- Belum jelas apakah ini kuota dapur terpisah atau batas produksi.

Langkah:
1. Jangan mengubah rumus 80% sebelum definisi disetujui.
2. Petakan relasi kuota produk, kuota harian, dan kapasitas dapur.
3. Implementasikan filter tanggal jika memang diperlukan oleh keputusan.
4. Tampilkan perbedaan kuota dengan label yang tidak membingungkan.

Acceptance criteria:
- Definisi kuota dapur terdokumentasi.
- Perhitungan dan filter tanggal konsisten.
- Tidak terjadi double-count dengan kuota penjualan.

### TASK-11 — Konsistensi status dan filter pesanan Admin

**Status:** TODO  
**Prioritas:** P1  
**Dependency:** TASK-01 keputusan #12 bila mengubah istilah status  
**Revisi:** ADM-03, ADM-07, ADM-16

Langkah:
1. Audit status `menunggu_pembayaran`, `menunggu_validasi`, `diproses`, `selesai`, `ditolak`, `dibatalkan`.
2. Pastikan label UI, filter, badge, dan API konsisten.
3. Pastikan pesanan yang dibatalkan tidak tercampur dengan daftar aktif.
4. Pastikan filter pembatalan tetap dapat menampilkan pesanan yang dibatalkan.
5. Jangan mengganti nilai status database tanpa migration dan rencana kompatibilitas.

Acceptance criteria:
- Setiap status memiliki label dan makna jelas.
- Filter status mengembalikan hasil benar.
- Pesanan batal tetap dapat ditelusuri tanpa mengotori antrean aktif.

### TASK-12 — Konsistensi auto-refresh setelah aksi Admin

**Status:** TODO  
**Prioritas:** P1  
**Dependency:** TASK-00  
**Revisi:** IMG-ADM-04, ADM-28

Langkah:
1. Audit aksi Admin yang mengubah pesanan, kuota, produk, dan checklist.
2. Tentukan apakah setelah aksi perlu memperbarui komponen, memuat ulang data, atau kembali ke dashboard.
3. Hilangkan reload yang tidak perlu, tetapi jangan mempertahankan data UI yang stale.
4. Pertahankan filter/periode jika memungkinkan.
5. Tampilkan feedback sukses/gagal.

Acceptance criteria:
- Setelah aksi sukses, data yang relevan terbarui.
- Tidak ada dialog/halaman yang menampilkan state lama.
- Kegagalan tidak menghapus perubahan form tanpa penjelasan.

### TASK-13 — Keterangan produk yang dinonaktifkan pada pesanan lama

**Status:** TODO  
**Prioritas:** P1  
**Dependency:** TASK-00  
**Revisi:** ADM-10

Langkah:
1. Audit penyimpanan snapshot nama/detail produk pada `detail_pesanan`.
2. Pastikan histori pesanan tidak bergantung pada produk aktif.
3. Tampilkan keterangan produk dinonaktifkan bila relevan.
4. Jangan menghapus detail historis ketika produk dinonaktifkan.

Acceptance criteria:
- Pesanan lama tetap menampilkan nama/detail produk yang benar.
- Status nonaktif tidak mengubah histori atau jumlah pesanan.

---

## F. P2 — Pengalaman Pelanggan dan Owner

### TASK-14 — Informasi kuota penuh untuk Pelanggan

**Status:** TODO  
**Prioritas:** P2  
**Dependency:** TASK-07  
**Revisi:** IMG-CUS-01

Langkah:
1. Periksa bagaimana katalog memfilter produk dengan sisa kuota 0.
2. Tampilkan informasi produk/tanggal yang kuotanya penuh sesuai alur pemilihan tanggal.
3. Jangan menampilkan produk seolah bisa dipesan jika kuota habis.
4. Pastikan pesan tidak menyesatkan ketika tanggal berubah.

Acceptance criteria:
- Pelanggan memahami produk mana yang penuh dan untuk tanggal berapa.
- Produk penuh tidak dapat di-checkout.
- Informasi diperbarui ketika tanggal pengiriman diganti.

### TASK-15 — Pengingat pembayaran/bukti untuk Pelanggan

**Status:** BLOCKED  
**Prioritas:** P2  
**Dependency:** TASK-01 keputusan #5 dan #12; TASK-04  
**Revisi:** IMG-CUS-02

Langkah setelah keputusan:
1. Tentukan status pesanan yang memicu pengingat.
2. Tentukan kapan pengingat dimulai dan berhenti.
3. Hindari pengingat untuk pesanan yang bukti pembayarannya sudah diterima atau sudah ditangani.
4. Gunakan status backend sebagai sumber kebenaran.
5. Jangan membuat notifikasi berulang tanpa batas.

Acceptance criteria:
- Pengingat muncul hanya pada kondisi yang disetujui.
- Pengingat berhenti setelah kondisi selesai/kedaluwarsa.
- Tidak ada pengingat untuk pesanan yang tidak relevan.

### TASK-16 — Perbaiki interaksi dialog Pelanggan

**Status:** TODO  
**Prioritas:** P2  
**Dependency:** TASK-00  
**Revisi:** CUS-04

Temuan audit:
- Detail dialog dan history dialog sudah mendukung backdrop close.
- Cart dialog dan checkout dialog belum.

Langkah:
1. Tambahkan backdrop close untuk cart dialog.
2. Tambahkan backdrop close untuk checkout dialog.
3. Klik di dalam isi dialog tidak boleh menutup dialog.
4. Pastikan tombol Escape, fokus keyboard, dan scroll body tetap berfungsi sesuai pola dialog yang ada.

Acceptance criteria:
- Backdrop menutup dialog.
- Klik konten dialog tidak menutup.
- Tidak ada scroll lock tertinggal setelah dialog ditutup.

### TASK-17 — Rapikan label dan kontrol keranjang Pelanggan

**Status:** TODO  
**Prioritas:** P2  
**Dependency:** TASK-05  
**Revisi:** CUS-18, CUS-20, CUS-21

Langkah:
1. Ubah “RINGKASAN KERANJANG” menjadi “ISI KERANJANG”.
2. Tinjau label “Jumlah” dan “Sisa Porsi”; jangan menghapus informasi yang berbeda makna tanpa memeriksa konteks. Jika revisi meminta satu label saja, pilih istilah yang mewakili fungsi sebenarnya atau minta klarifikasi.
3. Ubah informasi kebijakan checkout menjadi list ringkas.
4. Letakkan dekat pemilihan tanggal sesuai dokumen revisi.

Acceptance criteria:
- Tidak ada label yang salah atau redundan.
- Informasi checkout mudah dipindai.
- Fungsi jumlah dan sisa kuota tidak tertukar.

### TASK-18 — Penyempurnaan navigasi dan dashboard Pelanggan

**Status:** TODO  
**Prioritas:** P2  
**Dependency:** TASK-00  
**Revisi:** CUS-17, CUS-22, CUS-23

Langkah:
1. Pindahkan tombol tambah pesanan ke kanan bawah dengan mempertimbangkan safe area mobile.
2. Samakan alignment panah pada empat menu dashboard.
3. Regression-test popup logout yang sudah dibuat.
4. Pastikan tombol tidak menutupi konten atau kontrol penting.

Acceptance criteria:
- Posisi tombol sesuai permintaan dan responsif.
- Panah konsisten.
- Logout tetap meminta konfirmasi.

### TASK-19 — Konsistensi branding Pelanggan

**Status:** BLOCKED  
**Prioritas:** P2  
**Dependency:** TASK-01 keputusan #14  
**Revisi:** CUS-24, CUS-25

Langkah:
1. Terapkan nama `se.noesa` lowercase pada lokasi yang disetujui.
2. Audit logo, warna, background, dan aset yang tersedia.
3. Jangan menggambar/mengganti logo final tanpa aset atau persetujuan.
4. Periksa kontras, konsistensi token warna, dan tampilan mobile/desktop.

Acceptance criteria:
- Brand konsisten.
- Logo dan warna menggunakan aset yang disetujui.
- Tidak ada perubahan identitas visual spekulatif.

### TASK-20 — Penyempurnaan dashboard dan detail Owner

**Status:** TODO  
**Prioritas:** P2  
**Dependency:** TASK-02; TASK-01 keputusan #13  
**Revisi:** OWN-02, OWN-03, OWN-04, OWN-10, OWN-12, OWN-13, OWN-15, OWN-17, OWN-18, OWN-20

Langkah:
1. Rapikan white space pada detail pesanan.
2. Audit layout desktop/mobile, sidebar/navbar, overflow, dan elemen bawah.
3. Tambahkan pemilihan tanggal spesifik pada tren jika diperlukan dan sesuai desain.
4. Hapus ikon dekoratif/ikon pemesan yang tidak memiliki fungsi informasi.
5. Rapikan spacing area Pemesan dan Jadwal.
6. Perbaiki simetri panah tombol kembali.
7. Verifikasi dialog logout Owner benar-benar menggunakan dialog yang terpusat.
8. Tampilkan tanggal yang sedang dipakai bersama informasi kuota.
9. Tinjau metrik Owner sesuai keputusan #13.

Acceptance criteria:
- Owner tetap read-only.
- Data dan tanggal filter jelas.
- Tidak ada bukti/gambar terpotong.
- Tampilan desktop/mobile konsisten.
- Metrik tidak menyesatkan.

---

## G. P3 — Penyempurnaan Admin dan regression

### TASK-21 — Penyempurnaan UI Admin

**Status:** TODO  
**Prioritas:** P3  
**Dependency:** TASK-00  
**Revisi:** ADM-17, ADM-18, ADM-20, ADM-21, ADM-23, ADM-25

Langkah:
1. Buat username lebih menonjol daripada ID pesanan.
2. Perbaiki strip/artefak pada menu Pesanan.
3. Samakan ikon vector dan ukuran/stroke.
4. Tambahkan indikator navigasi aktif pada mobile.
5. Ganti ikon search box dengan ikon yang konsisten.
6. Hilangkan duplikasi foto profil Admin.

Acceptance criteria:
- Hierarki informasi jelas.
- Tidak ada ikon atau elemen profil duplikat.
- Navigasi aktif terlihat pada mobile.
- Tidak ada perubahan fungsi Admin.

### TASK-22 — Regression test lintas role

**Status:** TODO  
**Prioritas:** P3  
**Dependency:** Semua task implementasi yang relevan selesai

Cakupan:
- Pelanggan: katalog, kuota, cart, checkout, pembayaran, upload/ganti bukti, riwayat, pembatalan, foto kiriman.
- Admin: verifikasi/penolakan, alasan penolakan, kuota, tanggal libur, produk, foto produk, pesanan manual, checklist, rekap produksi.
- Owner: dashboard, filter, pesanan, produk, foto, status ditolak, read-only.
- Umum: login, logout, forgot password, role access, database/migration.

Uji:
1. Desktop dan mobile.
2. Data kosong dan data terisi.
3. Request valid dan invalid.
4. Role yang benar dan role yang mencoba akses terlarang.
5. Refresh, login ulang, dan konsistensi data.
6. Error jaringan/storage bila dapat disimulasikan.

Acceptance criteria:
- Test matrix terisi dengan hasil aktual.
- Bug yang ditemukan dibuat menjadi task baru, bukan diperbaiki diam-diam di luar scope.
- Tidak ada klaim siap rilis jika masih ada blocker P0.

### TASK-23 — Final release readiness review

**Status:** TODO  
**Prioritas:** P3  
**Dependency:** TASK-22

Langkah:
1. Review semua task dan ID revisi.
2. Pastikan migration production sudah terdokumentasi dan punya prosedur backup/rollback.
3. Pastikan environment variable dan secret aman.
4. Pastikan tidak ada file upload sensitif yang terbuka publik tanpa kontrol yang sesuai.
5. Review Git diff dan status repository.
6. Buat `RELEASE-READINESS-SE.NOESA.md`.

Acceptance criteria:
- Semua blocker dicatat.
- Task dan ID revisi memiliki status final yang dapat ditelusuri.
- Ada keputusan GO / NO-GO dengan alasan.
- Jangan deploy atau push tanpa instruksi eksplisit.

---

## H. Dependency dan urutan eksekusi singkat

```text
TASK-00 Rekonsiliasi audit
   |
   v
TASK-01 Keputusan bisnis
   |
   +--> TASK-02 Owner sync
   +--> TASK-03 Warning kuota 0 [keputusan #3, #4]
   +--> TASK-04 Pembayaran E2E
   +--> TASK-05 Minimum pemesanan
   +--> TASK-06 Upload foto produk
   +--> TASK-07 Concurrency kuota
   |
   +--> TASK-08 WhatsApp [#6]
   +--> TASK-09 Pesanan manual/dine-in [#8, #9, #10]
   +--> TASK-10 Kuota dapur [#11]
   +--> TASK-11 Status/filter [#12]
   +--> TASK-12 Auto-refresh
   +--> TASK-13 Produk nonaktif pada histori
   |
   +--> TASK-14 Kuota penuh
   +--> TASK-15 Pengingat bukti [#5]
   +--> TASK-16 Dialog Pelanggan
   +--> TASK-17 Keranjang
   +--> TASK-18 Dashboard Pelanggan
   +--> TASK-19 Branding [#14]
   +--> TASK-20 Owner [#13]
   +--> TASK-21 UI Admin
   |
   v
TASK-22 Regression test
   |
   v
TASK-23 Release readiness
```

Catatan:
- TASK-04 boleh dimulai sebagai audit/test tanpa mengubah kebijakan status.
- TASK-05 boleh diaudit terlebih dahulu; perubahan nilai minimum yang belum disepakati tetap BLOCKED.
- TASK-07 adalah pengujian teknis; perubahan mekanisme locking hanya jika terbukti perlu.
- TASK-02, TASK-06, TASK-12, TASK-13, TASK-14, TASK-16, TASK-17, TASK-18, TASK-21 dapat dimulai setelah TASK-00 jika tidak memerlukan keputusan yang belum tersedia.
- Urutan dependency di atas bukan izin mengerjakan banyak task sekaligus. Tetap satu task per sesi.

---

## I. Prompt utama untuk coding agent

Salin prompt ini setiap kali memulai task. Ganti `[TASK-ID]` dengan satu ID task, misalnya `TASK-00`.

```text
Kamu bekerja pada repository Se.Noesa-Web.

Baca terlebih dahulu:
- AGENTS.md
- AUDIT-REVISI-TAHAP-2.md
- REVISI-LANJUTAN-SE.NOESA.md
- TASKS-IMPLEMENTASI-SE.NOESA.md

TASK YANG DIKERJAKAN: [TASK-ID]

ATURAN EKSEKUSI:
1. Kerjakan hanya task dengan ID tersebut. Jangan mengerjakan task lain.
2. Baca deskripsi, dependency, status, langkah, dan acceptance criteria task tersebut.
3. Periksa kondisi repository aktual sebelum mengubah file.
4. Jika task berstatus BLOCKED atau keputusan bisnis belum tersedia, jangan coding. Buat laporan pertanyaan yang dibutuhkan lalu berhenti.
5. Jika task dapat dikerjakan, buat rencana singkat dan implementasikan hanya scope task tersebut.
6. Pertahankan PHP native, PostgreSQL/Supabase via PDO, HTML/CSS/JavaScript vanilla, pola arsitektur, dan design system yang sudah ada.
7. Jangan rewrite aplikasi atau melakukan refactor luas.
8. Untuk perubahan database, gunakan migration aman dan ikuti migration runner yang ada.
9. Validasi role di backend. Jaga transaksi, kuota, histori pesanan, dan file upload.
10. Jangan mengarang aturan bisnis. Jangan menghapus data. Jangan menambahkan dependency baru tanpa alasan kuat.
11. Jangan push, merge, reset, rebase, deploy, atau mengubah remote Git.
12. Jalankan test yang sesuai dengan acceptance criteria. PHP lint dan JS syntax check saja tidak cukup.
13. Periksa git diff untuk memastikan perubahan tidak melebar ke task lain.
14. Perbarui status task dan catatan audit hanya berdasarkan hasil nyata.
15. Setelah task selesai atau terblokir, BERHENTI. Tunggu instruksi user untuk task berikutnya.

OUTPUT WAJIB:
- Task ID
- Status: DONE / PARTIAL / BLOCKED / FAILED
- Temuan awal
- Rencana singkat
- File yang diubah
- Migration (jika ada)
- Test dan hasil aktual
- Acceptance criteria yang terpenuhi/belum
- Risiko dan pertanyaan
- ID revisi terkait
- Pernyataan bahwa task lain tidak dikerjakan

Jangan melanjutkan ke task berikutnya secara otomatis.
```

---

## J. Cara menjalankan

Jalankan satu per satu dengan prompt utama di atas:

1. `TASK-00` — rapikan konsistensi laporan audit.
2. `TASK-01` — siapkan 14 keputusan bisnis dan minta persetujuan.
3. `TASK-02` — verifikasi sinkronisasi Owner.
4. `TASK-03` — warning kuota 0 setelah keputusan tersedia.
5. Lanjutkan sesuai dependency dan prioritas pada bagian H.

**Penting:** jika agent menyelesaikan TASK-02, jangan langsung menyuruhnya mengerjakan TASK-03 dalam prompt yang sama. Review hasil TASK-02 terlebih dahulu, baru berikan Task ID berikutnya.
