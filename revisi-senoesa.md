# Revisi Website Senoesa

Sumber: PDF "Kumpulan REVISI - Sheet1" + catatan tambahan (screenshot).
Role: **Pelanggan**, **Admin**, **Owner**, **Umum**.

## Legenda Status

**Developer:** `Done` = selesai dibuat/diperbaiki · `Angkat tangan` = developer menyerah, tanya "Teman" nanti · kosong = belum dikerjakan

**QA/QI:** `Passed` = lolos · `Ada yg kurang` = perlu diperbaiki/ditambah · `Under review` = sedang diperiksa · `X` = developer bilang Done tapi ternyata belum

Prioritas: 🔴 bug/fungsi · 🟠 fitur/logika · 🟡 UI/teks

---

## 0. Catatan Tambahan (dari screenshot)

### Pelanggan
- [ ] 🟠 Cara tahu kuota penuh (tampilkan "tanggal sekian, stok sekian")
- [ ] 🟠 Kalau sudah bayar tapi lupa kirim bukti, perlu penanganan/pengingat

### Admin
- [ ] 🟠 Tanggal libur: bisa tambah tanggal libur, tapi bagaimana kalau di tanggal itu sudah ada pesanan?
- [ ] 🟠 Tambah informasi **No WA**
- [ ] 🟠 Notifikasi: tampilkan warning pelanggan yang terdampak saat kuota diubah jadi 0. Tambah catatan (tidak harus usulan dari Pak Unggul)
- [ ] 🟡 Dashboard otomatis kembali (setelah aksi)
- [ ] 🟠 Pembayaran ditolak: di detail pesanan pelanggan masih kurang keterangan (alasan penolakan)

### Owner
- [ ] 🔴 Fitur pesanan: data tidak sinkron (catatan tidak keluar)

---

## 1. PELANGGAN

### Perlu dikerjakan / diperbaiki

| # | Revisi | Status |
|---|--------|--------|
| 4 | 🟠 Popup bisa ditutup dengan klik background (FE). **Crosscheck:** Menu > Pesan Sekarang > Lihat Pesanan (popup masih harus klik "X") > Lanjutkan (masih harus klik "X") | Ada yg kurang |
| 6 | 🟠 Pelanggan bisa membatalkan pesanan maks H-2. **Crosscheck:** setelah admin validasi pembayaran, customer tidak bisa batal (contoh: pesan H-7 → validasi H-7 → batal H-5 tidak bisa). Jika sudah divalidasi dan customer ingin batal, bagaimana mengembalikan uang? | Under review |
| 8 | 🟡 Halaman riwayat pesanan: ada 3 logo profil (kiri atas, kiri bawah, kanan atas) → cukup 1 | Belum |
| 9 | 🟡 Ada 2 tombol keluar (kiri bawah, kanan bawah) → cukup 1 | Belum |
| 10 | 🟡 Box "Info pemesanan: ..." dipindah ke halaman pembayaran, di atas box pemilihan tanggal | Belum |
| 12 | 🟡 Teks "Lihat perkembangan pesanan..." tidak perlu (?) | Belum |
| 13 | 🟡 Halaman riwayat pesanan: tombol plus (tambah pesanan) pindah ke kanan bawah | Belum |
| 14 | 🟡 Popup detail menu: ada 2 "jumlah porsi" dengan fungsi sama → 1 saja | Belum |
| 15 | 🟠 Sisa porsi hanya ditampilkan jika di bawah 50%, dan "/100" tidak perlu ditampilkan | Belum |
| 17 | 🟡 Box informasi di atas tombol kirim pesanan dibuat list, letaknya di atas/bawah box pemilihan tanggal | Belum |
| 18 | 🟡 Tanda panah di 4 menu dashboard kurang simetris | Belum |
| 19 | 🟡 Saat logout tidak ada popup konfirmasi (seperti di admin dan owner) | Belum |

### Hasil uji coba, pembahasan, dan temuan tambahan

- [ ] 🟠 **Batas pembayaran:** beri keterangan maksimal waktu pembayaran. **Crosscheck:** teks "...dan kuota dikembalikan." tidak perlu. *(Ada yg kurang)*
- [ ] 🔴 "Sisa porsi" di popup produk tidak berkurang/bertambah saat jumlah produk yang dipesan ditambah/dikurangi. *(X: dianggap Done tapi belum)*
- [ ] 🔴 Gambar QRIS Senoesa tidak muncul. *(Angkat tangan: perlu bantuan)*
- [ ] 🟠 Minimal pemesanan setiap produk belum seragam
- [ ] 🟠 Indikasi alur pemesanan saat pembayaran tidak berjalan (?)
- [ ] 🟠 Popup detail pesanan: jika menu dipesan kebanyakan dan ingin mengurangi sebagian, belum ada fiturnya. Saat ini hanya tombol "x" yang menghapus seluruh jumlah menu tersebut → tambah tombol kurang (−)
- [ ] 🟡 Tampilan: delay saat memuat list menu ketika ganti-ganti kategori
- [ ] 🟡 Logo profil kiri atas fungsinya ke riwayat pemesanan (?) → perjelas
- [ ] 🟡 Penulisan nama: **se.noesa** (huruf kecil semua)
- [ ] 🟡 Warna tampilan
- [ ] 🟡 Logo
- [ ] 🟡 Bagian background yang masih kosong diperbaiki agar tampilan lebih menarik
- [ ] 🟠 Batas pembatalan pesanan ditetapkan maksimal H-2 sebelum jadwal pesanan *(tercatat Done, cek ulang bersama poin #6)*
- [ ] 🟠 Belum ada tampilan untuk melihat foto pesanan yang sudah selesai dan siap dikirim (diunggah Admin)
- [ ] 🟠 Kuota produk harian menggunakan default

### Sudah selesai (arsip)

- ✅ #1 Tombol kirim bukti pembayaran otomatis terkirim / berganti jadi "Ganti Bukti" (FE) — Passed
- ✅ #2 Pilih data pelanggan/pesanan (data sendiri atau orang lain) — Passed
- ✅ #3 Informasi pemesanan H-berapa sebelum acara — Passed
- ✅ #5 Kuota produk berubah mengikuti tanggal pengiriman (BE) — Passed
- ✅ #7 Foto hasil siap pengiriman — Passed
- ✅ Tampilan tanggal — Passed
- ✅ #11 Teks "Riwayat Pesanan" double — Done
- ✅ #16 "Ringkasan Keranjang" diganti "Isi Keranjang" — Done

---

## 2. ADMIN

### Perlu dikerjakan / diperbaiki

| # | Revisi | Status |
|---|--------|--------|
| 1 | 🟠 Setiap keputusan harus ada peringatan verifikasi (FE). **Crosscheck:** popup saat customer ingin membatalkan pesanan perlu dibenerin | Ada yg kurang |
| 3 | 🟠 Tambah status (user belum bayar, ditolak). **Crosscheck:** pesanan yang dibatalkan customer tidak tampil di admin | Ada yg kurang |
| 5 | 🟠 Fitur tambah pesanan di role admin (pesan mendadak, dine-in). **Crosscheck:** (a) jika dine-in, masih ada box alamat; (b) bisa pesan H-7 dan menentukan jam, apakah customer tidak punya fitur itu?; (c) jika dine-in, masih ada aturan minimal pemesanan | Ada yg kurang |
| 6 | 🟠 Kuota dapur berbeda tiap tanggal → bisa difilter berdasarkan tanggal (BE) | Under review |
| 9 | 🟡 Ada strip di menu "Pesanan" | Belum |
| 10 | 🟡 "Validasi" diganti "Verifikasi" | Belum |
| 11 | 🟡 Icon kurang setema → pakai vector semua | Belum |
| 12 | 🟡 Mobile: menu paling bawah tidak ter-block warna (tidak ada tanda halaman aktif) | Belum |
| 13 | 🟡 "Status sejak..." dan "Dibuat ..." apa maksudnya → perjelas | Belum |
| 14 | 🟡 Icon di search box diganti | Belum |

### Hasil uji coba, pembahasan, dan temuan tambahan

- [ ] 🔴 Setelah mengedit kuota harian, customer masih bisa pesan melebihi kuota yang telah diatur
- [ ] 🔴 "Tandai selesai": checklist tetap terbaca tercentang meski sudah di-uncheck (klik → centang muncul → klik lagi → hilang), sehingga tombol "Selesai" dan "Kirim" tetap bisa diklik walau checklist kosong
- [ ] 🟠 Jika sudah terlanjur ada pesanan lalu menu dinonaktifkan, beri keterangan
- [ ] 🟡 Profil admin tampil double → kurangi
- [ ] 🟡 Teks "Menu Utama" tidak perlu (?)
- [ ] 🟡 "Buka ..." diganti "Lihat ..."
- [ ] 🟡 "Antrean Prioritas" membingungkan. Penjelasan: antrean yang menunggu validasi, belum dikonfirmasi admin → perjelas label/tooltip
- [ ] 🟡 ID Pesanan dan Username pemesan ditukar (Username besar, ID kecil) *(tercatat Done, cek ulang)*

### Sudah selesai (arsip)

- ✅ Jadwal libur operasional — Done
- ✅ #2 Tanggal data pemesanan harus sama (jangan digrup) — Done
- ✅ Detail pesanan: catatan "tidak ada bukti pembayaran" jika status "menunggu pembayaran" — Passed
- ✅ Keterangan saat pembayaran pelanggan ditolak — Done
- ✅ Teks "Senoesa/Admin" double dikurangi — Done
- ✅ #4 Menu baru setting kuota berdasarkan tanggal (FE & BE) — Done
- ✅ #5 Kuota produk default saat tambah produk, dan bisa berubah di hari tertentu (BE) — Done
- ✅ Rekap data produksi per produk pada tanggal tertentu + tautan ke detail produk untuk checklist — Done
- ✅ #7 Bukti foto pesanan tidak hilang saat status selesai (FE) — Passed
- ✅ Filter status pembatalan oleh pelanggan — Done

---

## 3. OWNER

### Perlu dikerjakan / diperbaiki

| # | Revisi | Status |
|---|--------|--------|
| 2 | 🟡 Detail pesanan tidak boleh ada white space besar (FE). **Crosscheck:** Desktop: ada space kosong di kiri (navbar dihilangkan atau error?) dan ada elemen (?) di paling bawah halaman. Mobile: ada elemen (?) di paling bawah halaman | Ada yg kurang |
| 4 | 🟡 "Hari ini, Minggu, Bulan" → "Hari, Minggu, Bulan" | Belum |
| 5 | 🟡 "Tiket Rata-Rata" ??? → perjelas atau ganti label | Belum |
| 6 | 🟡 Icon tidak jelas dan kurang relevan → hapus | Belum |
| 7 | 🟡 "Botol" ?? → perjelas atau ganti label | Belum |
| 8 | 🟡 Icon "pemesan datang dari mana" → hapus | Belum |
| 9 | 🟡 Space kosong di bawah "Pemesan" masih banyak | Belum |
| 10 | 🔴 Bukti pesanan pelanggan terpotong dan tidak bisa di-scroll | Belum |
| 11 | 🟡 Space kosong di bawah "Jadwal" masih banyak; beri jarak antara tgl pemesanan dan pengiriman | Belum |
| 12 | 🟡 Fitur checklist masih ada di dashboard owner (tidak berguna) → hapus | Belum |
| 13 | 🟡 Tombol return: panah tidak simetris | Belum |
| 14 | 🟡 Popup logout muncul di pojok kiri atas, bukan di tengah | Belum |

### Hasil uji coba, pembahasan, dan temuan tambahan

- [ ] 🟠 Tren pesanan: bulan bisa memilih tanggal
- [ ] 🔴 Bukti pesanan pelanggan di beberapa detail pesanan tidak muncul
- [ ] 🟠 Halaman produk: kuota yang ditampilkan untuk tanggal berapa? → beri keterangan tanggal
- [ ] 🟡 Teks "Senoesa" di atas "Dashboard", "Pesanan", "Produk" → ganti "Owner" atau hapus
- [ ] 🔴 (dari catatan tambahan) Fitur pesanan: data tidak sinkron, catatan tidak keluar

### Sudah selesai (arsip)

- ✅ #1 Owner hanya bisa melihat, tidak bisa mengubah data (BE) — Passed
- ✅ Status "ditolak" — Passed
- ✅ #3 Gambar produk di list produk (BE/FE) — Done

---

## 4. UMUM

- ✅ #1 Fitur forgot password via email ke pelanggan — Done *(catatan: "Siapa ini?" → pastikan email tujuan jelas)*
- ✅ #2 Migrasi database ke Supabase (BE) — Passed

---

## 5. Ringkasan Prioritas Pengerjaan

**🔴 Kerjakan dulu (bug/fungsi)**
1. QRIS tidak muncul (Pelanggan)
2. "Sisa porsi" tidak update saat jumlah dipesan berubah (Pelanggan)
3. Kuota bisa terlewati setelah diedit admin (Admin)
4. Checklist "Tandai selesai" tidak sinkron dengan tombol Selesai/Kirim (Admin)
5. Bukti pesanan terpotong / tidak muncul / tidak bisa di-scroll (Owner)
6. Data pesanan tidak sinkron, catatan tidak keluar (Owner)

**🟠 Fitur/logika (butuh keputusan dengan mitra)**
- Alur pembatalan & refund setelah pembayaran divalidasi
- Tambah pesanan oleh admin (dine-in, jam, minimal pesanan)
- Kuota dapur per tanggal; tampilan kuota penuh untuk pelanggan
- Tanggal libur vs pesanan yang sudah ada; notifikasi pelanggan terdampak saat kuota jadi 0
- Status pesanan (belum bayar, ditolak, dibatalkan pelanggan) di admin
- Pengurangan jumlah menu di popup detail pesanan
- Foto pesanan siap kirim untuk pelanggan
- Tren pesanan per tanggal (Owner); No WA (Admin)

**🟡 UI/teks (batch terakhir)**
- Rapikan duplikasi (logo profil, tombol keluar, teks judul)
- Perbaikan label, icon vector, dan simetri panah
- Konsistensi penulisan **se.noesa**, warna, logo, background kosong
- Popup logout (posisi & konfirmasi)

---

## 6. Catatan

- Status di PDF dibaca dari tabel yang kolomnya tergabung, jadi beberapa status (terutama kolom "Meeting dgn Mitra" dan "Hasil uji coba") dibaca berdasarkan urutan baris. Cek ulang ke PDF asli untuk item bertanda *"cek ulang"*.
- Item yang tidak punya status di PDF dianggap **belum dikerjakan**.
