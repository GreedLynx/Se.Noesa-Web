# Revisi Halaman Owner

Daftar revisi yang terbaca dari sheet **OWNER**. Status hanya dicantumkan jika terlihat pada tabel.

| No. | Status | Revisi |
|---:|---|---|
| 1 | Passed | Hak owner hanya melihat; tidak bisa mengubah data (BE). |
| 2 | Diperbaiki | Navbar detail pesanan tampil di kiri pada desktop dan disembunyikan di mobile. Status duplikat di bawah header dihapus; tombol kembali memakai ikon panah SVG. |
| 3 | Done | Menambahkan gambar produk di list produk (BE/FE). |
| 4 | — | Tren pesanan: bulan bisa memilih tanggal. |
| 5 | Passed | Status ditolak. |
| 6 | Dalam pengerjaan | Bukti pesanan pelanggan tidak muncul di beberapa detail pesanan. Implementasi: endpoint streaming bukti yang sebelumnya khusus Admin kini mengizinkan Owner terautentikasi; detail owner memakai endpoint tersebut alih-alih path file langsung. |
| 7 | Selesai | Teks "Senoesa" tidak muncul di atas item Dashboard, Pesanan, atau Produk pada navbar Owner; label header yang tampil adalah "Owner". Tidak perlu penghapusan tambahan. |
| 8 | — | Label "Hari ini, Minggu, Bulan" diganti menjadi "Hari, Minggu, Bulan". |
| 9 | — | Tinjau label "Tiket Rata-Rata"; maksud revisinya belum jelas. |
| 10 | — | Ikon tidak jelas dan kurang relevan; sebaiknya dihapus dan tidak perlu menggunakan ikon. |
| 11 | — | Tinjau label "botol"; maksud revisinya belum jelas. |
| 12 | — | Ikon pemesan datang dari mana? Sebaiknya dihapus. |
| 13 | — | Masih banyak ruang kosong di bawah "Pemesanan". |
| 14 | Dalam pengerjaan | Bukti pesanan pelanggan terpotong dan tidak bisa di-scroll. Implementasi: gambar bukti kini mempertahankan proporsi penuh, dengan wadah yang dapat di-scroll dan tinggi maksimum 70vh (maksimal 620px). Jika berkas gagal dimuat, tampil pesan kegagalan alih-alih gambar produk pengganti. |
| 15 | — | Ruang kosong di bawah jadwal masih banyak. Beri jarak antara tanggal pemesanan dan tanggal pengiriman. |
| 16 | — | Fitur checklist masih ada di dashboard owner; dianggap tidak berguna. |
| 17 | — | Tombol return: panahnya tidak simetris. |
| 18 | — | Popup saat logout berada di pojok kiri atas, bukan di tengah. |
| 19 | Diperbaiki | Kuota setiap produk kini mencantumkan tanggal aktif dan jumlah sisa dari total kuota. |

## Laporan pengerjaan

- **Diperbaiki:** akses owner ke file bukti pesanan lewat endpoint terautentikasi dan tampilan bukti agar dapat dilihat utuh serta di-scroll.
- **Revisi layout:** navbar owner dirender pada detail pesanan desktop untuk mengisi sidebar 224px; navbar disembunyikan pada mobile. Berkas: `public/owner/order-detail.php`, `public/assets/css/app.css`.
- **Berkas kode:** `public/admin/api/proof.php`, `public/assets/js/app.js`, dan `public/assets/css/app.css`.
- **Verifikasi:** belum menjalankan aplikasi di browser atau tes otomatis; perubahan perlu dikonfirmasi pada detail pesanan Owner dengan bukti yang tersedia dan bukti yang hilang.

## Catatan sumber

Nomor mengikuti urutan catatan yang tampak pada gambar. Beberapa catatan di kolom bersebelahan saya gabungkan berdasarkan topiknya; ejaan dan maksud yang kurang jelas dipertahankan sebagai catatan untuk dikonfirmasi.

- **Perapian detail:** status di bawah judul dihapus dan ikon kembali header diganti dengan panah SVG yang konsisten.
- **Dashboard:** sapaan tidak lagi memakai username akun; sekarang menampilkan “Selamat Pagi, Owner”. Berkas: `public/owner/dashboard.php`.
- **Halaman pelanggan — timer pembayaran:** detail pesanan menampilkan hitung mundur satu jam sebelum bagian “Pesanan”. Timer tetap berjalan setelah bukti pertama dikirim ke verifikasi; pelanggan dapat mengganti bukti sampai batas waktu. API kini juga menolak unggah/penggantian setelah batas satu jam. Berkas: `public/pelanggan/assets/customer.js`, `public/pelanggan/assets/customer.css`, `public/pelanggan/api/orders.php`.
- **Perbaikan error halaman pelanggan:** dihapus referensi ke variabel `paymentError` yang tidak pernah didefinisikan saat detail dibuka. Error itu menghentikan pemasangan handler Batalkan Pesanan dan form bukti. Berkas: `public/pelanggan/assets/customer.js`.
- **Konfirmasi pembatalan pelanggan:** dialog bawaan browser diganti dialog kustom terpusat dengan ikon peringatan, judul, keterangan refund, tombol batal, dan aksi merah “Ya, Batalkan”.
- **Kontak pada syarat pembatalan:** panel kini menampilkan tautan WhatsApp Admin beserta nomor, serta tautan Instagram/TikTok yang tersedia di konfigurasi kontak aplikasi.
- **Halaman Owner Produk:** tiap baris produk kini memberi label “Kuota [tanggal pilihan]” dan menunjukkan sisa dari total porsi; tanggal ditampilkan lengkap dengan nama bulan.