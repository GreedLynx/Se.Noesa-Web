# Rencana pemilihan jam pengiriman pada Data pemesanan Pelanggan

## Primary goal

Pelanggan dapat melihat dan memilih jam pengiriman saat mengisi Data pemesanan. Jam yang dipilih tersimpan pada pesanan dan terlihat kembali pada riwayat sehingga pelanggan dan Admin membaca jadwal yang sama.

## Kondisi kode dan keputusan rencana

- Form `#checkout-form` di `public/pelanggan/partials/layout.php` memiliki `tgl_kirim`, tetapi belum menampilkan input jam.
- `submitOrder()` di `public/pelanggan/assets/customer.js` membuat payload dari `FormData(form)`. Input bernama `waktu_kirim` akan ikut terkirim tanpa serialisasi baru.
- `public/pelanggan/api/orders.php` menerima `waktu_kirim`, memakai `12:00` bila kosong, lalu menyimpan tanggal dan jam sebagai `pesanan.tgl_kirim` (`TIMESTAMPTZ`) dalam zona Asia/Jakarta. Admin offline sudah memakai input `type="time"` dengan nilai awal `12:00`.
- Gunakan pemilih waktu native dengan nilai awal `12:00` dan label **Jam pengiriman (WIB)**. Nilai awal terlihat dan dapat diubah pelanggan. Rencana ini tidak menetapkan jam operasional baru karena belum ada rentang jam yang disetujui dalam aturan bisnis.
- Tampilkan jam dari `tgl_kirim` di ringkasan dan detail riwayat Pelanggan menggunakan formatter `dateLabel(value, true)` yang sudah mengunci zona Asia/Jakarta. Pertahankan penanganan data lama dengan `tgl_kirim` kosong.

## Phase 0 — Pastikan kontrak dan pemanggil

**How this serves the primary goal:** Memastikan jam pilihan memakai alur dan kolom yang sudah tersedia.

- Baca `docs/00-panduan/peta-kode.md` untuk checkout dan riwayat, `docs/01-spesifikasi/aturan-bisnis.md` untuk batas tanggal H-7, `docs/01-spesifikasi/api.md` untuk payload checkout, dan `docs/01-spesifikasi/database.md` untuk `pesanan.tgl_kirim`.
- Periksa form Pelanggan, `openCheckout()` dan `submitOrder()` di `customer.js`, validasi `DateTimeImmutable::createFromFormat('!Y-m-d H:i', ...)` dan INSERT di API, serta tampilan jam pada detail Admin. Periksa fixture checkout yang mengirim payload tanpa `waktu_kirim` sebelum mengubah kontrak.
- Gunakan nama field `waktu_kirim` yang sudah diterima API. Tidak perlu endpoint, tabel, kolom, atau migrasi baru.

## Phase 1 — Tampilkan pemilih jam pada Data pemesanan

**How this serves the primary goal:** Pelanggan dapat menentukan jam sebelum menekan Kirim pesanan.

- Tambahkan `<input type="time" name="waktu_kirim" id="checkout-time" value="12:00" required>` sesudah tanggal kirim dalam `public/pelanggan/partials/layout.php`, dengan label yang terhubung dan keterangan WIB. Ikuti pola label dan jarak form yang sudah ada.
- Pastikan membuka ulang dialog tidak menimpa jam yang sudah dipilih selama form masih aktif. `FormData(form)` harus mengirim nilai `HH:mm` ke API. Pertahankan fallback `12:00` pada API untuk pemanggil lama yang belum mengirim jam.
- Verifikasi fase dengan uji browser bahwa input tampil dan dapat diubah pada desktop/mobile, dan payload checkout memuat jam pilihan.

## Phase 2 — Tampilkan hasil pilihan pada riwayat

**How this serves the primary goal:** Pelanggan dapat memeriksa jam pengiriman setelah pesanan dibuat.

- Pada kartu riwayat dan bagian Tanggal kirim di pop up detail `public/pelanggan/assets/customer.js`, tampilkan tanggal beserta jam WIB dari `tgl_kirim`. Pakai `dateLabel(..., true)` yang sudah tersedia. Untuk `tgl_kirim` kosong, pertahankan penanda `-` tanpa jam tambahan.
- Periksa tampilan detail Admin yang sudah memformat `tgl_kirim` dengan jam. Saat tawaran jadwal pengganti diterima, API yang ada mempertahankan bagian jam pada `tgl_kirim`; pastikan jam pilihan tetap terlihat setelah tanggal berubah.
- Verifikasi fase dengan fixture respons `tgl_kirim` ber-offset +07:00 dan tanggal lama/baru. Pastikan angka jam yang tampil tetap WIB pada browser dengan zona sistem berbeda.

## Phase 3 — Uji dan dokumentasikan

**How this serves the primary goal:** Membuktikan jam yang dipilih melewati form, API, penyimpanan, dan riwayat tanpa perubahan tanggal.

- Uji waktu awal `12:00`, jam lain yang dipilih pelanggan, format waktu tidak valid, batas tanggal H-7, dan pemanggil lama tanpa `waktu_kirim`. Uji data lama dengan `tgl_kirim` NULL. Gunakan PostgreSQL uji terisolasi untuk membuktikan nilai tersimpan sebagai waktu Asia/Jakarta; hindari database aplikasi.
- Jalankan pemeriksaan sintaks PHP/JavaScript, uji browser responsif yang relevan, dan `git diff --check`. Perbarui `docs/00-panduan/peta-kode.md`, `docs/01-spesifikasi/api.md`, `docs/01-spesifikasi/database.md`, dan `docs/00-panduan/HANDOFF.md` bersama implementasi. Catat batas pengujian jika PostgreSQL uji belum tersedia.

Status: diimplementasikan. Fixture browser form dan riwayat lulus pada lebar 320, 390, dan 1280 piksel. Batas uji PostgreSQL dicatat di `docs/00-panduan/HANDOFF.md`.
