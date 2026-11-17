# Se.Noesa — Dokumen Revisi & Pengembangan Lanjutan

> Dokumen kerja untuk melanjutkan pengembangan website Se.Noesa berdasarkan **Kumpulan REVISI - Sheet1.pdf** (3 halaman) dan catatan revisi pada gambar yang diberikan.
>
> **Tujuan:** mengubah daftar temuan mitra, hasil uji coba, dan catatan tambahan menjadi spesifikasi implementasi yang dapat dikerjakan bertahap oleh developer/AI coding agent.
>
> **Catatan penting:** status `Done`, `passed`, `under review`, `ada yg kurang`, `angkat tangan`, dan `X` di bawah dipertahankan sebagai informasi dari dokumen sumber. Status tersebut bukan klaim bahwa kondisi aplikasi saat ini sudah diverifikasi ulang.

---

## 1. Konteks dan aturan pengerjaan

### 1.1 Area aplikasi

- **Pelanggan:** melihat menu dan kuota, membuat/mengelola pesanan, membayar, mengirim bukti pembayaran, melihat status dan bukti pesanan selesai.
- **Admin:** mengelola produk, kuota, jadwal operasional, pesanan, pembayaran, status pesanan, dan kebutuhan pesanan manual.
- **Owner:** memantau data, tren, produk, kuota, dan bukti pesanan dengan hak akses baca saja.
- **Umum:** autentikasi, pemulihan kata sandi, database, dan konsistensi identitas/branding.

### 1.2 Konteks teknis proyek

Dokumen ini ditujukan untuk kelanjutan repository **Se.Noesa-Web** yang telah menggunakan PHP dan PostgreSQL serta memiliki role Admin, Owner, dan Pelanggan. Dokumen revisi menyebut perpindahan database ke Supabase dan statusnya `passed`; implementasi harus mempertahankan Supabase sebagai target database apabila konfigurasi repository saat ini memang sudah menggunakannya.

### 1.3 Aturan wajib untuk developer / AI agent

1. **Audit sebelum mengubah:** baca `AGENTS.md`, `PRD.md`, `DESIGN.md` (jika tersedia), struktur folder, konfigurasi, migration, schema, API, dan alur autentikasi yang sudah ada.
2. Jangan membuat ulang aplikasi atau mengganti stack hanya untuk menyelesaikan revisi.
3. Jangan menghapus atau merombak fitur yang sudah berjalan tanpa alasan teknis dan persetujuan.
4. Ikuti arsitektur, design system, pola komponen, naming, dan standar keamanan repository.
5. Perubahan database harus melalui migration yang aman, dapat dilacak, dan kompatibel dengan data produksi. Jangan mengandalkan perubahan manual yang tidak terdokumentasi.
6. Validasi hak akses di backend, bukan hanya menyembunyikan tombol di frontend.
7. Perhitungan kuota dan pembuatan pesanan harus aman dari race condition/transaksi bersamaan.
8. Setiap aksi penting harus mempunyai validasi, pesan sukses/gagal, dan konfirmasi bila berisiko.
9. Jangan menandai pekerjaan `Done` sebelum diuji. Catat bukti pengujian dan hasilnya.
10. Jangan mengisi informasi yang belum jelas dengan asumsi. Masukkan ke bagian **Pertanyaan / keputusan yang perlu dikonfirmasi**.

---

## 2. Revisi dari gambar tambahan

### 2.1 Pelanggan

| ID | Revisi | Kriteria penerimaan |
|---|---|---|
| IMG-CUS-01 | Beri tahu pelanggan ketika kuota produk sudah penuh. Informasi perlu menyebut tanggal stok/kuota yang penuh. | Pada produk/tanggal yang kuotanya habis, pelanggan melihat pesan yang jelas dan tanggal terkait. Produk tersebut tidak dapat ditambahkan ke keranjang untuk tanggal itu. |
| IMG-CUS-02 | Ingatkan pelanggan yang sudah membayar tetapi belum mengirim bukti pembayaran. | Jika pesanan sudah dibayar menurut alur yang berlaku tetapi bukti belum dikirim, tampilkan pengingat yang relevan pada halaman/detail pesanan. Jangan tampilkan pengingat kepada pesanan yang sudah memiliki bukti atau sudah ditangani admin. |

### 2.2 Admin

| ID | Revisi | Kriteria penerimaan |
|---|---|---|
| IMG-ADM-01 | Tambahkan pengelolaan tanggal libur. | Admin dapat menambahkan, melihat, mengubah, dan menghapus tanggal libur sesuai hak akses. Tanggal libur memengaruhi pemilihan tanggal pengiriman/pemesanan sesuai aturan operasional yang disepakati. |
| IMG-ADM-02 | Tambahkan informasi nomor WhatsApp (WA). | Nomor WA yang relevan ditampilkan pada lokasi yang disepakati dan dapat digunakan pelanggan untuk menghubungi pihak Se.Noesa. Format nomor divalidasi dan tautan WhatsApp menggunakan format internasional yang benar. |
| IMG-ADM-03 | Saat kuota diubah menjadi 0, tampilkan peringatan pelanggan yang terdampak. Tambahkan catatan/alasan perubahan; catatan tidak wajib berasal dari Pak Unggul. | Sebelum menyimpan kuota 0, admin mendapat konfirmasi yang menjelaskan dampaknya dan jumlah/daftar pesanan pelanggan yang terdampak jika tersedia. Admin dapat mengisi catatan alasan. Perubahan tidak dilakukan diam-diam. |
| IMG-ADM-04 | Dashboard otomatis kembali/terbarui. | Setelah aksi admin berhasil, tampilan kembali ke dashboard atau memperbarui data sesuai alur yang paling masuk akal. Tidak terjadi halaman kosong, state lama, atau kebutuhan refresh manual yang tidak perlu. |
| IMG-ADM-05 | Ketika pembayaran ditolak, pelanggan harus melihat keterangan penolakan pada detail pesanan. | Admin wajib atau dapat mengisi alasan penolakan sesuai keputusan bisnis. Alasan tersimpan dan ditampilkan pada detail pesanan pelanggan dengan bahasa yang jelas. |

### 2.3 Owner

| ID | Revisi | Kriteria penerimaan |
|---|---|---|
| IMG-OWN-01 | Data pesanan pada Owner tidak sinkron (catatan menyebut “tidak keluar”). | Audit sumber data, query, filter tanggal/status, relasi, dan kebijakan akses. Daftar serta detail pesanan Owner harus menampilkan data yang sama dengan data valid di Admin, tetapi tetap read-only. |

---

## 3. Revisi role Pelanggan dari PDF

| ID | Revisi / temuan | Status pada PDF | Kriteria penerimaan / tindak lanjut |
|---|---|---|---|
| CUS-01 | Tombol kirim bukti pembayaran otomatis terkirim; tombol dihapus atau diganti menjadi **Ganti Bukti**. | passed | Pertahankan alur yang sudah dinyatakan lolos. Jika bukti sudah dikirim, tombol tidak boleh membuat duplikasi; gunakan aksi ganti bukti bila memang diizinkan. |
| CUS-02 | Pelanggan dapat memilih data pelanggan/pesanan sendiri atau orang lain. | passed | Pertahankan dan regression-test validasi data pemesan/penerima. |
| CUS-03 | Tambahkan informasi pemesanan H-berapa sebelum acara. | passed | Tampilkan selisih hari secara akurat berdasarkan tanggal acara/pengiriman dan zona waktu yang digunakan aplikasi. |
| CUS-04 | Popup dapat ditutup dengan menekan area background; alur Lihat Pesanan → Lanjutkan tidak mengharuskan klik tombol X berulang. | ada yg kurang | Klik backdrop menutup popup jika aman; klik di dalam popup tidak menutupnya. Pastikan state scroll halaman kembali normal. |
| CUS-05 | Kuota produk berubah mengikuti kapan produk dikirim. | passed | Kuota dihitung berdasarkan tanggal pengiriman yang dipilih, bukan hanya kuota global. |
| CUS-06 | Pelanggan dapat membatalkan pesanan maksimal H-2. Setelah pembayaran divalidasi admin, pelanggan tidak dapat membatalkan. Perlu kejelasan pengembalian uang jika pembayaran sudah divalidasi. | under review | Terapkan batas H-2 dan larangan pembatalan setelah validasi pembayaran. Kebijakan refund harus diputuskan sebelum otomatisasi pembatalan/refund dibuat. |
| CUS-07 | Foto hasil pesanan siap pengiriman ditampilkan kepada pelanggan. | passed | Foto harus tetap dapat dilihat setelah status pesanan berubah menjadi selesai. |
| CUS-08 | Sisa porsi pada popup produk tidak bertambah/berkurang saat produk ditambah/dikurangi. | X | Perbaiki sinkronisasi hitungan sisa porsi secara langsung dan setelah refresh. Pastikan angka berasal dari kuota tanggal yang dipilih dikurangi total porsi pesanan yang relevan. |
| CUS-09 | Gambar QRIS Se.Noesa tidak muncul. | angkat tangan | Periksa path/storage, URL publik, permission, format gambar, dan fallback. QRIS harus terlihat di halaman pembayaran. |
| CUS-10 | Minimum pemesanan tiap produk belum seragam. | belum ditandai selesai | Samakan validasi minimum per produk pada frontend dan backend. Sumber minimum harus konsisten dan tidak hanya divalidasi di UI. |
| CUS-11 | Alur pembayaran terindikasi tidak berjalan. | belum ditandai selesai | Audit alur dari ringkasan pesanan, pembuatan pesanan, instruksi pembayaran, unggah bukti, sampai status menunggu verifikasi. Catat titik gagal dan perbaiki tanpa melewati validasi. |
| CUS-12 | Pada detail pesanan, pelanggan hanya dapat menghapus seluruh jumlah menu dengan tombol X; belum bisa mengurangi sebagian jumlah. | belum ditandai selesai | Tambahkan kontrol tambah/kurang jumlah per item. Tombol hapus seluruh item tetap tersedia sebagai aksi terpisah. Validasi minimum pemesanan dan kuota setelah perubahan. |
| CUS-13 | Informasi batas pembayaran harus jelas, termasuk batas maksimal pembayaran. Teks “...dan kuota dikembalikan” diminta untuk dicek dan tidak perlu bila tidak sesuai. | belum ditandai selesai | Tampilkan tenggat pembayaran yang benar. Hapus janji pengembalian kuota dari teks jika perilaku sistem tidak benar-benar menjaminnya. |
| CUS-14 | Tampilan riwayat pesanan memiliki beberapa ikon profil; cukup satu. Ada dua tombol keluar; cukup satu. | belum ditandai selesai | Rapikan elemen duplikat pada halaman riwayat. |
| CUS-15 | Kotak “Info pemesanan” dipindahkan ke halaman pembayaran, di atas kotak pemilihan tanggal. | belum ditandai selesai | Pindahkan informasi ke posisi yang diminta tanpa menghilangkan isi pentingnya. |
| CUS-16 | Teks “Riwayat Pesanan” tampil ganda; teks “Lihat perkembangan pesanan...” dinilai tidak perlu. | Done | Pertahankan hasil perbaikan dan regression-test. |
| CUS-17 | Tombol plus untuk menambah pesanan pada halaman riwayat sebaiknya berada di kanan bawah. | belum ditandai selesai | Posisikan tombol dengan aksesibilitas baik dan tidak menutupi konten pada mobile. |
| CUS-18 | Popup detail menu menampilkan dua label “Jumlah Porsi” dengan fungsi sama. | belum ditandai selesai | Sisakan satu kontrol/label yang jelas. |
| CUS-19 | Sisa porsi hanya ditampilkan jika kurang dari 50%; format “/100” tidak perlu. | belum ditandai selesai | Terapkan aturan tampilan tersebut dan pastikan angka sisa porsi tetap akurat. |
| CUS-20 | “Ringkasan Keranjang” diganti menjadi “Isi Keranjang”. | Done | Pertahankan label baru. |
| CUS-21 | Informasi di atas tombol kirim pesanan lebih baik ditampilkan sebagai list dan diletakkan dekat kotak pemilihan tanggal. | belum ditandai selesai | Susun informasi menjadi list ringkas di sekitar pemilihan tanggal. |
| CUS-22 | Panah pada empat menu dashboard kurang simetris. | belum ditandai selesai | Samakan ukuran, alignment, dan posisi ikon panah. |
| CUS-23 | Saat logout tidak muncul popup seperti pada Admin dan Owner. | belum ditandai selesai | Samakan feedback/konfirmasi logout antar-role sesuai pola UI yang dipilih. |
| CUS-24 | Penulisan nama brand harus huruf kecil semua: **se.noesa**. | catatan meeting | Gunakan `se.noesa` secara konsisten pada teks brand yang tampil. |
| CUS-25 | Warna tampilan, logo, dan area background yang kosong perlu diperbaiki agar lebih menarik. | catatan meeting | Audit konsistensi design system, logo, kontras, dan komposisi area kosong. Jangan mengganti identitas visual tanpa menyelaraskan dengan aset/referensi yang disetujui. |
| CUS-26 | Batas pembatalan pesanan ditetapkan maksimal H-2 sebelum jadwal pesanan. | catatan meeting | Selaraskan dengan CUS-06; jangan membuat dua aturan pembatalan yang berbeda. |
| CUS-27 | Belum ada tampilan untuk melihat foto pesanan yang telah selesai dan siap dikirim, yang diunggah Admin. | catatan meeting | Selaraskan dengan CUS-07; foto tersedia pada detail pesanan pelanggan dan dapat dibuka dengan jelas. |
| CUS-28 | Kuota produk harian menggunakan default. | catatan meeting | Pastikan kuota harian memiliki nilai default dari pengaturan produk, tetapi dapat dioverride untuk tanggal tertentu sesuai fitur kuota per tanggal. |
| CUS-29 | Error ketika Admin menambahkan foto produk. | catatan meeting | Audit upload foto produk: validasi MIME/ukuran, nama file, storage, URL, permission, dan error backend. Tampilkan pesan gagal yang informatif. |

---

## 4. Revisi role Admin dari PDF

| ID | Revisi / temuan | Status pada PDF | Kriteria penerimaan / tindak lanjut |
|---|---|---|---|
| ADM-01 | Aksi keputusan penting memerlukan peringatan/konfirmasi. Popup pembatalan pesanan perlu diperbaiki. | ada yg kurang | Tambahkan dialog konfirmasi untuk aksi berisiko, tampilkan objek dan dampak aksi, serta sediakan batal/lanjutkan. |
| ADM-02 | Tanggal data pemesanan harus sama dan tidak digabung (jangan di-group). | Done | Pertahankan daftar data per tanggal sesuai kebutuhan operasional. Regression-test filter dan tampilan. |
| ADM-03 | Tambahkan status “belum bayar” dan “ditolak”; pesanan yang dibatalkan pelanggan tidak tampil di Admin. | ada yg kurang | Definisikan status secara konsisten. Audit apakah pesanan batal perlu disembunyikan atau tersedia di filter pembatalan; PDF juga meminta filter pembatalan pada ADM-07, sehingga keputusan tampilan harus konsisten. |
| ADM-04 | Pengaturan kuota berdasarkan tanggal; setiap tanggal dapat memiliki kuota berbeda. | Done | Pertahankan kuota per tanggal. |
| ADM-05 | Produk memiliki kuota default saat dibuat dan kuota dapat diubah pada tanggal tertentu. | Done | Nilai default produk menjadi fallback; override per tanggal harus menang ketika tersedia. |
| ADM-06 | Kuota dapur berbeda tiap tanggal dan dapat difilter berdasarkan tanggal. | under review | Selesaikan kuota dapur per tanggal dan filter tanggal. Bedakan kuota dapur dari kuota penjualan jika memang dua konsep berbeda. |
| ADM-07 | Foto pesanan tidak boleh hilang ketika status pesanan berubah menjadi selesai; tambahkan filter pembatalan oleh pelanggan. | passed / Done | Pastikan foto tetap terhubung dengan pesanan. Filter pembatalan menampilkan pesanan yang dibatalkan pelanggan sesuai kebijakan akses. |
| ADM-08 | Bagian bukti pembayaran pada detail pesanan dengan status “menunggu pembayaran” perlu catatan bahwa bukti belum ada. | ada yg kurang | Tampilkan empty state “Belum ada bukti pembayaran” dan jangan menampilkan area kosong seolah-olah gambar gagal dimuat. |
| ADM-09 | Saat pembayaran ditolak, berikan keterangan/alasan penolakan. | belum ditandai selesai | Selaraskan dengan IMG-ADM-05. Simpan alasan penolakan dan tampilkan kepada pelanggan. |
| ADM-10 | Jika produk/menu dinonaktifkan padahal sudah dipesan, berikan keterangan. | belum ditandai selesai | Pesanan lama tetap dapat ditelusuri. Tampilkan keterangan bahwa produk dinonaktifkan; jangan menghapus detail historis pesanan. |
| ADM-11 | Admin dapat menambah pesanan (misalnya pesanan mendadak atau dine-in). | belum ditandai selesai | Buat alur pesanan manual dengan sumber pesanan tercatat, validasi kuota/minimum, tanggal, jam bila diperlukan, dan data pelanggan yang sesuai. |
| ADM-12 | Untuk dine-in masih muncul isian alamat; dine-in perlu ditinjau untuk aturan minimum pemesanan. | ada yg kurang | Alamat tidak wajib untuk dine-in. Putuskan apakah minimum pemesanan tetap berlaku untuk dine-in sebelum mengubah validasi. |
| ADM-13 | Pesanan H-7 dan pilihan jam perlu konsisten antara Admin dan Pelanggan. | ada yg kurang | Tentukan rentang pemesanan (termasuk H-7) dan pilihan jam yang berlaku untuk kedua role. Jangan memberi kemampuan berbeda tanpa aturan bisnis. |
| ADM-14 | Rekap produksi per produk pada tanggal tertentu dan tautan menuju detail produk untuk checklist. | Done | Pertahankan rekap produksi berbasis tanggal dan tautan detail produk. |
| ADM-15 | Teks tombol “Buka ...” diganti menjadi “Lihat...”. | belum ditandai selesai | Ganti label sesuai konteks dan pastikan konsisten. |
| ADM-16 | Istilah “Antrean Prioritas” membingungkan; maksudnya antrean pesanan yang menunggu validasi admin/belum dikonfirmasi. | belum ditandai selesai | Ganti dengan label yang eksplisit, misalnya “Menunggu Verifikasi”, jika sesuai alur status yang disepakati. |
| ADM-17 | Pada tampilan daftar, Username harus lebih menonjol daripada ID Pesanan. | Done | Tampilkan username lebih besar/utama dan ID pesanan sebagai informasi sekunder. |
| ADM-18 | Ada strip pada menu “Pesanan”. | belum ditandai selesai | Periksa artefak garis/strip dan rapikan sesuai design system. |
| ADM-19 | Label “Validasi” diganti menjadi “Verifikasi”. | belum ditandai selesai | Gunakan istilah “Verifikasi” secara konsisten di UI. |
| ADM-20 | Ikon kurang setema; gunakan ikon vector yang konsisten. | belum ditandai selesai | Gunakan satu keluarga ikon vector dengan ukuran dan stroke konsisten. |
| ADM-21 | Di mobile, menu paling bawah tidak memiliki penanda halaman aktif. | belum ditandai selesai | Tambahkan state aktif yang jelas, termasuk pada navigasi mobile. |
| ADM-22 | Arti teks “Status sejak...” dan “Dibuat ...” tidak jelas. | belum ditandai selesai | Hapus atau ubah menjadi label yang menjelaskan makna tanggal/waktu secara spesifik. |
| ADM-23 | Ikon pada search box perlu diganti. | belum ditandai selesai | Gunakan ikon pencarian yang sesuai dan konsisten. |
| ADM-24 | Teks “Senoesa/Admin” tampil ganda. | Done | Pertahankan hasil perbaikan. |
| ADM-25 | Foto profil Admin tampil ganda. | belum ditandai selesai | Sisakan satu elemen profil yang diperlukan. |
| ADM-26 | Teks “Menu Utama” dinilai tidak perlu. | belum ditandai selesai | Hapus jika tidak dibutuhkan dalam hierarki navigasi. |
| ADM-27 | Tambahkan nomor WhatsApp. | catatan gambar | Selaraskan dengan IMG-ADM-02; tentukan sumber nomor (konfigurasi) dan lokasi tampilnya. |
| ADM-28 | Dashboard otomatis kembali/terbarui setelah aksi. | catatan gambar | Selaraskan dengan IMG-ADM-04. |
| ADM-29 | Saat kuota diubah menjadi 0, beri warning untuk pelanggan terdampak dan sediakan catatan alasan. | catatan gambar | Selaraskan dengan IMG-ADM-03. |

---

## 5. Revisi role Owner dari PDF

| ID | Revisi / temuan | Status pada PDF | Kriteria penerimaan / tindak lanjut |
|---|---|---|---|
| OWN-01 | Owner hanya boleh melihat data, tidak boleh mengubah (termasuk checklist). | passed | Terapkan read-only di backend dan frontend. Pastikan endpoint mutasi menolak role Owner. |
| OWN-02 | Detail pesanan tidak boleh memiliki white space terlalu besar. | belum ditandai selesai | Rapikan layout dan responsivitas tanpa mengorbankan keterbacaan. |
| OWN-03 | Mode desktop memiliki ruang kosong di kiri/menu navbar tidak muncul atau error; ada elemen di bagian bawah. Mode mobile juga memiliki elemen bawah yang tidak jelas. | ada yg kurang | Audit layout responsif, sidebar/navbar, overflow, tinggi halaman, dan elemen yang tidak sengaja muncul. |
| OWN-04 | Tren pesanan harus dapat dipilih berdasarkan bulan/tanggal. | belum ditandai selesai | Sediakan filter periode yang jelas dan pastikan grafik/angka mengikuti periode terpilih. |
| OWN-05 | Tampilkan status ditolak. | passed | Pertahankan status ditolak pada tampilan Owner. |
| OWN-06 | Tambahkan gambar produk pada daftar produk. | Done | Pertahankan gambar produk pada list dan sediakan fallback jika gambar kosong. |
| OWN-07 | Teks “Senoesa” di atas Dashboard/Pesanan/Produk diganti menjadi “Owner” atau dihapus. | belum ditandai selesai | Gunakan satu identitas role yang konsisten dan hindari teks ganda. |
| OWN-08 | Filter “Hari ini, Minggu, Bulan” diubah menjadi “Hari, Minggu, Bulan”. | belum ditandai selesai | Perbaiki label filter sesuai permintaan. |
| OWN-09 | Istilah “Tiket Rata-Rata” tidak jelas. | belum ditandai selesai | Hapus atau ganti setelah definisi metrik dikonfirmasi. Jangan menampilkan metrik tanpa arti yang jelas. |
| OWN-10 | Ikon tidak jelas/kurang relevan; lebih baik dihapus. | belum ditandai selesai | Hapus ikon dekoratif yang tidak menambah informasi. |
| OWN-11 | Label “botol” tidak jelas. | belum ditandai selesai | Hapus atau ganti setelah satuan/metrik yang dimaksud dikonfirmasi. |
| OWN-12 | Ikon pemesan tidak jelas asalnya; lebih baik tidak digunakan. | belum ditandai selesai | Hapus ikon yang tidak memiliki fungsi informatif. |
| OWN-13 | Ruang kosong di bawah “Pemesan” terlalu banyak. | belum ditandai selesai | Perbaiki spacing dan susunan informasi. |
| OWN-14 | Bukti pesanan pelanggan terpotong dan tidak bisa di-scroll. | belum ditandai selesai | Gambar/bukti dapat dibuka penuh, di-scroll, dan tidak terpotong pada desktop maupun mobile. |
| OWN-15 | Area di bawah Jadwal terlalu kosong; beri jarak yang tepat antara tanggal pemesanan dan pengiriman. | belum ditandai selesai | Rapikan spacing berdasarkan hierarki konten. |
| OWN-16 | Checklist masih muncul di dashboard Owner padahal tidak berguna. | belum ditandai selesai | Hilangkan checklist dari Owner; Owner tetap read-only. |
| OWN-17 | Tombol return memiliki panah tidak simetris. | belum ditandai selesai | Samakan alignment ikon panah dengan tombol lain. |
| OWN-18 | Popup logout muncul di pojok kiri atas, bukan di tengah. | belum ditandai selesai | Posisikan dialog logout di tengah viewport dan pastikan responsif. |
| OWN-19 | Status ditolak harus tersedia dan dapat dilihat. | passed | Selaraskan dengan OWN-05 dan pastikan detail alasan penolakan dapat ditampilkan bila tersedia. |
| OWN-20 | Kuota pada halaman produk harus menjelaskan kuota untuk tanggal berapa. | belum ditandai selesai | Tampilkan tanggal yang sedang dilihat/difilter bersama angka kuota. |
| OWN-21 | Data pesanan Owner tidak sinkron/tidak muncul. | catatan gambar | Selaraskan dengan IMG-OWN-01; audit query, filter, relasi, dan akses data. |

---

## 6. Revisi umum dari PDF

| ID | Revisi | Status pada PDF | Kriteria penerimaan / tindak lanjut |
|---|---|---|---|
| GEN-01 | Fitur forgot password melalui email pelanggan. | Done | Pertahankan dan regression-test proses permintaan reset, token kedaluwarsa, serta penggantian password. |
| GEN-02 | Database menggunakan Supabase. | passed | Pertahankan koneksi Supabase/PostgreSQL yang aktif. Pastikan secret tidak disimpan di frontend atau commit repository. |

---

## 7. Aturan bisnis yang perlu dijadikan satu sumber kebenaran

Bagian ini penting karena beberapa revisi saling berkaitan. Implementasi frontend dan backend harus memakai aturan yang sama.

### 7.1 Kuota produk per tanggal

- Setiap produk memiliki **kuota default**.
- Setiap tanggal dapat memiliki **override kuota** yang berbeda.
- Jika override untuk tanggal tertentu tersedia, gunakan override tersebut; jika tidak, gunakan kuota default.
- Kuota yang ditampilkan kepada pelanggan adalah kuota untuk tanggal pengiriman yang dipilih.
- Sisa kuota dihitung dari kuota tanggal tersebut dikurangi jumlah porsi yang sudah mengunci/menggunakan kuota sesuai status pesanan yang disepakati.
- Tambah, kurangi, atau hapus item keranjang harus langsung memperbarui estimasi sisa kuota.
- Backend wajib menghitung ulang saat checkout; angka dari frontend tidak boleh dipercaya sebagai sumber final.
- Perubahan kuota menjadi 0 harus memunculkan konfirmasi dan catatan dampak kepada pelanggan yang sudah memesan.
- Perubahan kuota tidak boleh diam-diam menghapus atau mengubah detail pesanan lama.

### 7.2 Minimum pemesanan

- Minimum ditetapkan per produk.
- Nilai minimum harus konsisten di halaman produk, popup detail, keranjang, checkout, pesanan Admin, dan API.
- Pengurangan jumlah item harus dicegah jika hasilnya di bawah minimum; berikan pesan yang menjelaskan minimum produk.
- Pengecualian untuk pesanan dine-in belum diputuskan dan harus dikonfirmasi.

### 7.3 Pembayaran

- QRIS Se.Noesa harus tampil pada instruksi pembayaran.
- Batas waktu pembayaran harus ditampilkan secara eksplisit.
- Status pembayaran minimal harus dapat membedakan menunggu pembayaran/bukti, menunggu verifikasi, diterima/terverifikasi, dan ditolak sesuai state machine aplikasi.
- Jika bukti belum ada, Admin melihat empty state yang jelas.
- Jika pembayaran ditolak, alasan penolakan disimpan dan ditampilkan kepada pelanggan.
- Tombol kirim bukti tidak boleh menghasilkan duplikasi. Jika bukti sudah ada dan penggantian diizinkan, gunakan aksi **Ganti Bukti**.
- Jangan menjanjikan kuota otomatis dikembalikan kecuali mekanisme tersebut benar-benar diterapkan dan diuji.

### 7.4 Pembatalan

- Batas pembatalan pelanggan: maksimal sampai H-2 sebelum jadwal pesanan.
- Setelah pembayaran diverifikasi Admin, pelanggan tidak dapat membatalkan melalui alur mandiri.
- Kebijakan refund untuk pembayaran yang sudah diverifikasi masih perlu keputusan pemilik/mitra.
- Pesanan yang dibatalkan perlu dapat ditelusuri melalui filter/status pembatalan, meskipun tidak muncul pada daftar pesanan aktif.
- Zona waktu dan definisi H-2 harus konsisten di frontend dan backend.

### 7.5 Foto produk dan foto pesanan

- Bedakan **foto produk** (katalog) dan **foto hasil pesanan** (bukti pesanan siap dikirim/selesai).
- Foto produk harus dapat diunggah Admin dan tampil pada list/detail produk.
- Foto hasil pesanan harus tetap terhubung dengan pesanan meskipun status berubah menjadi selesai.
- Pelanggan dan Owner dapat melihat foto sesuai hak akses.
- Owner tidak boleh mengunggah, mengubah, atau menghapus foto.
- Validasi tipe file, ukuran, nama file, penyimpanan, dan akses URL dilakukan di backend.

### 7.6 Role dan akses

- **Pelanggan:** hanya mengakses data dan aksi miliknya sesuai aturan pesanan.
- **Admin:** dapat mengelola operasional sesuai izin.
- **Owner:** read-only; tidak boleh melakukan mutasi termasuk checklist.
- Proteksi harus diterapkan di setiap endpoint/API, bukan hanya pada komponen UI.

---

## 8. Prioritas pengerjaan yang disarankan

Prioritas ini adalah usulan urutan teknis, bukan status dari PDF.

### P0 — Integritas data dan alur transaksi

1. Sinkronisasi data pesanan Owner (IMG-OWN-01).
2. Perhitungan sisa kuota yang benar dan kuota per tanggal (CUS-08, ADM-04, ADM-05, ADM-06, IMG-ADM-03).
3. Alur pembayaran, QRIS, batas pembayaran, bukti pembayaran, dan alasan penolakan (CUS-09, CUS-11, CUS-13, ADM-08, ADM-09, IMG-ADM-05).
4. Aturan pembatalan H-2 dan status setelah pembayaran diverifikasi (CUS-06, CUS-26).
5. Foto produk dan foto hasil pesanan tidak error/hilang (CUS-07, CUS-29, ADM-07).
6. Konsistensi validasi minimum pemesanan (CUS-10, CUS-12).

### P1 — Operasional Admin

1. Tanggal libur dan pengaruhnya pada pemilihan tanggal (IMG-ADM-01).
2. Peringatan perubahan kuota menjadi 0 dan catatan alasan (IMG-ADM-03).
3. Pesanan manual Admin, termasuk kebutuhan mendadak/dine-in (ADM-11).
4. Kuota dapur per tanggal dan rekap produksi (ADM-06, ADM-14).
5. Filter pesanan dibatalkan dan penjelasan status (ADM-03, ADM-07, ADM-16).
6. Nomor WhatsApp (IMG-ADM-02).

### P2 — Pengalaman Pelanggan dan Owner

1. Informasi kuota penuh dan pengingat bukti pembayaran (IMG-CUS-01, IMG-CUS-02).
2. Perbaikan popup, keranjang, riwayat, dan navigasi pelanggan (CUS-04, CUS-12, CUS-14–CUS-23).
3. Penyempurnaan dashboard Owner, filter tren, label metrik, dan tampilan bukti (OWN-02–OWN-20).
4. Konsistensi nama brand `se.noesa`, warna, logo, dan background (CUS-24, CUS-25).

### P3 — Penyempurnaan dan regression test

1. Samakan dialog logout dan konfirmasi aksi.
2. Rapikan ikon, panah, spacing, label, dan state navigasi mobile.
3. Uji ulang fitur yang sebelumnya `Done` atau `passed`.
4. Uji seluruh role di desktop dan mobile.

---

## 9. Rencana pengujian / Definition of Done

Sebuah revisi baru boleh diberi status `Done` setelah:

- [ ] Skenario normal berhasil.
- [ ] Skenario gagal/validasi berhasil menampilkan pesan yang tepat.
- [ ] Hak akses Pelanggan, Admin, dan Owner diuji.
- [ ] Data tetap benar setelah refresh dan login ulang.
- [ ] Tidak ada data pesanan/foto historis yang hilang.
- [ ] Tampilan desktop dan mobile diperiksa.
- [ ] Tidak ada error JavaScript, PHP, atau query database yang terkait.
- [ ] Migration diuji pada database pengembangan dan aman untuk data yang sudah ada.
- [ ] Perubahan penting dicatat pada commit Git.
- [ ] Status revisi diperbarui berdasarkan hasil uji nyata.

### Skenario uji minimum lintas fitur

1. Produk dengan kuota default dan produk dengan override kuota tanggal.
2. Dua pelanggan checkout pada waktu berdekatan ketika kuota hampir habis.
3. Pelanggan menambah, mengurangi sebagian, dan menghapus item keranjang.
4. Kuota diubah Admin menjadi 0 ketika sudah ada pesanan pada tanggal tersebut.
5. Pelanggan membayar, mengirim bukti, mengganti bukti, dan menerima penolakan dengan alasan.
6. Pelanggan membatalkan pada H-3, H-2, dan H-1; ulangi setelah pembayaran diverifikasi.
7. Admin menonaktifkan produk yang sudah tercantum pada pesanan lama.
8. Foto produk gagal upload dan berhasil upload.
9. Foto hasil pesanan tetap terlihat setelah status menjadi selesai.
10. Owner melihat data yang sama dengan Admin tetapi semua aksi mutasi ditolak.
11. Tanggal libur tidak dapat dipilih jika memang ditetapkan sebagai hari operasional tutup.
12. Semua alur diuji pada viewport desktop dan mobile.

---

## 10. Pertanyaan / keputusan yang perlu dikonfirmasi

Jangan menebak jawaban pertanyaan berikut saat implementasi:

1. **Refund:** jika pembayaran sudah diverifikasi lalu pelanggan meminta pembatalan karena kondisi khusus, apakah uang dikembalikan manual, sebagian, atau tidak dikembalikan?
2. **H-2:** apakah batas pembatalan berarti sampai tepat 48 jam sebelum jam pengiriman, atau berdasarkan pergantian tanggal kalender (dua hari sebelumnya)?
3. **Kuota 0:** apa tindakan terhadap pesanan yang sudah masuk—tetap diproses, perlu konfirmasi pelanggan, atau dibatalkan oleh Admin?
4. **Catatan perubahan kuota:** apakah catatan hanya untuk audit internal atau juga dikirim/ditampilkan kepada pelanggan terdampak?
5. **Pengingat bukti:** kapan pengingat ditampilkan dan kapan berhenti—setelah upload bukti, setelah tenggat, atau setelah verifikasi?
6. **Nomor WhatsApp:** nomor siapa yang ditampilkan (Admin/CS/Owner) dan pada halaman mana?
7. **Tanggal libur:** apakah seluruh tanggal libur menutup semua produk, atau ada produk/acara tertentu yang tetap bisa dipesan?
8. **Dine-in:** apakah minimum porsi tetap berlaku, apakah perlu jam kedatangan, dan apakah alamat benar-benar dihilangkan?
9. **Pesanan Admin:** apakah pesanan manual wajib terhubung ke akun pelanggan atau boleh menggunakan nama/nomor pelanggan tanpa akun?
10. **Jam pesanan H-7:** apakah pelanggan dapat memilih jam yang sama seperti Admin dan apakah jam memiliki batas kapasitas?
11. **Kuota dapur:** apakah kuota dapur sama dengan kuota penjualan atau merupakan batas produksi terpisah?
12. **Status pembayaran:** apa nama status final yang akan digunakan agar istilah `belum bayar`, `menunggu pembayaran`, `menunggu verifikasi`, `diterima`, dan `ditolak` tidak tumpang tindih?
13. **Metrik Owner:** apa definisi “Tiket Rata-Rata” dan “botol”? Jika tidak ada kebutuhan bisnis yang jelas, hapus dari dashboard.
14. **Branding:** apakah logo/warna baru sudah final dan tersedia sebagai aset resmi?

---

## 11. Prompt pelaksanaan untuk AI coding agent

Gunakan prompt berikut ketika dokumen ini diberikan kepada OpenCode, Antigravity, Codex, atau coding agent lain:

> Baca seluruh dokumen `REVISI-LANJUTAN-SE.NOESA.md` dan audit repository Se.Noesa-Web sebelum melakukan perubahan.
>
> Kerjakan sebagai kelanjutan aplikasi yang sudah ada, bukan membuat ulang proyek. Pertahankan stack, arsitektur, database, autentikasi, dan fitur yang telah berjalan. Baca `AGENTS.md`, PRD, design system, migration, schema, API, dan komponen terkait terlebih dahulu.
>
> **Tahap pertama hanya audit dan rencana:** petakan setiap ID revisi ke file frontend, endpoint/backend, tabel/database, serta test yang terdampak. Tandai mana yang sudah ada, belum ada, rusak, atau membutuhkan keputusan bisnis. Jangan mengubah kode pada tahap audit.
>
> Setelah audit, ajukan keputusan yang memang belum tersedia pada bagian “Pertanyaan / keputusan yang perlu dikonfirmasi”. Jangan mengarang kebijakan refund, kuota, atau operasional.
>
> Setelah keputusan tersedia, implementasikan per prioritas P0 → P1 → P2 → P3. Buat migration bila diperlukan, jaga kompatibilitas data, validasi hak akses di backend, dan gunakan transaksi database untuk operasi kuota/pesanan yang rawan bentrok.
>
> Untuk setiap tahap, jalankan test yang relevan, periksa tampilan desktop/mobile, laporkan file yang diubah, migration, hasil test, risiko, dan ID revisi yang benar-benar selesai. Jangan mengklaim `Done` tanpa bukti pengujian. Jangan push atau mengubah remote Git tanpa instruksi eksplisit.
>
> Jika menemukan konflik antara dokumen revisi dan implementasi saat ini, hentikan bagian yang berisiko, jelaskan konflik beserta bukti file/kode, lalu minta keputusan. Jangan menghapus data atau mengubah perilaku transaksi secara spekulatif.

---

## 12. Catatan status

Status dari spreadsheet menggunakan istilah berikut:

- **Done:** menurut dokumen, fitur/desain sudah selesai dibuat atau diperbaiki.
- **passed:** menurut dokumen, QA/QI menyatakan hasil lolos.
- **under review:** sedang diperiksa QA/QI.
- **ada yg kurang:** masih perlu diperbaiki atau ditambahkan.
- **angkat tangan:** developer belum dapat menyelesaikan dan perlu bertanya/eskalasi.
- **X:** hasil uji coba menunjukkan masalah atau belum berhasil.

Status pada dokumen ini perlu diperbarui setelah audit terhadap repository dan pengujian ulang.

---

**Sumber penyusunan:** `Kumpulan REVISI - Sheet1.pdf` dan gambar catatan revisi yang disertakan pada percakapan ini.
