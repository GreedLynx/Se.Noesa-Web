# Rencana perbaikan auth mobile dan batas kuota terpakai

Status: rencana saja, belum diterapkan.

## Tujuan dan keputusan

1. Pada lebar mobile (di bawah 760 px), login dan register memenuhi lebar serta tinggi layar tanpa bingkai luar atau sudut putih yang menimpa area hero. Form tetap dapat digulir pada layar pendek dan keyboard mobile.
2. Admin tidak dapat menyimpan kuota efektif produk pada suatu tanggal lebih kecil dari jumlah porsi pesanan yang masih memakai kuota pada tanggal tersebut. Jika sudah terpakai 40 porsi, nilai 39 ditolak dan nilai 40 diterima.
3. Pesanan lama tidak diubah atau dibatalkan. Aturan kuota baru diterapkan pada setiap perubahan kuota Admin berikutnya.

## Temuan kode dan cakupan

- `public/login.php` dan `public/register.php` memakai layout `.app.auth`, `.hero`, dan `.sheet` dari `public/assets/css/app.css`. `.app` dibatasi `max-width: 430px` sampai breakpoint 760 px. `.sheet` putih memakai `margin-top: -30px` dan `border-radius: 40px 40px 0 0`; pada mobile keduanya tampak sebagai bingkai/tepi putih. Aturan desktop dua kolom mulai 760 px dan perlu dipertahankan.
- Admin menyimpan kuota default di `public/admin/api/products.php`. Kuota khusus, reset rentang, dan hapus override tunggal ada di `public/admin/api/kuota-harian.php`; perubahan nilainya lewat `public/admin/assets/admin.js` dan `public/admin/products.php`.
- Pemakaian aktif sudah didefinisikan di `daily_reserved_clause()` pada `app/daily-quota.php` dan dipakai snapshot checkout. Query pengaman perlu memakai definisi yang sama, tanggal `tgl_kirim` dalam zona Asia/Jakarta, serta hanya tanggal tanpa override saat memeriksa perubahan default.
- `docs/01-spesifikasi/aturan-bisnis.md` dan `docs/01-spesifikasi/database.md` saat ini secara eksplisit membolehkan penurunan kuota di bawah pemakaian. Keduanya perlu diselaraskan dengan keputusan baru.

## Tahap 1 — login dan register mobile

1. Tambah aturan CSS khusus `body[data-page="login"]` dan `body[data-page="register"]` untuk viewport di bawah 760 px: `.app.auth` selebar viewport, tanpa batas 430 px, margin/border/radius/bayangan luar, dan tinggi minimal `100dvh` dengan fallback `100vh`.
2. Buat pertemuan hero dan form rata tanpa lengkungan putih: hilangkan overlap negatif dan radius atas `.sheet` pada mobile. Pertahankan warna dasar form, logo, serta layout desktop mulai 760 px.
3. Pastikan padding form cukup pada 320 px, safe area, dan layar pendek; konten register serta tombol submit tetap dapat digulir dan disentuh saat keyboard muncul.
4. Perluas pemeriksaan auth di `scripts/test-customer-revisions-browser.cjs`: lebar 320, 390, 430, 431, 759, 760, dan 1280 px; tepi kiri/kanan `.app.auth` sama dengan viewport pada mobile, tidak ada overflow horizontal, panel tidak menimpa hero, dan desktop tetap dua kolom.

## Tahap 2 — batas kuota yang sudah terpakai

1. Buat satu pemeriksaan pemakaian produk per tanggal di `app/daily-quota.php` yang memakai `daily_reserved_clause()` dan porsi dari `detail_pesanan`. Jalankan di dalam transaksi bersama lock kuota yang urutannya sama dengan checkout, sehingga pesanan baru tidak bisa masuk antara pemeriksaan dan penyimpanan.
2. Saat edit kuota default di `public/admin/api/products.php`, periksa tanggal pesanan aktif produk yang **tidak** memiliki override. Tolak HTTP 409 bila kuota default baru lebih kecil dari porsi terpakai pada salah satu tanggal tersebut. Jangan mengubah metadata produk atau meninggalkan file gambar baru bila validasi gagal.
3. Saat `save` override di `public/admin/api/kuota-harian.php`, periksa setiap tanggal dalam rentang terhadap kuota baru. Saat `reset` atau `delete`, bandingkan pemakaian tanggal terdampak terhadap kuota default yang akan berlaku. Seluruh rentang harus atomik: satu tanggal gagal berarti tidak ada tanggal yang berubah.
4. Respons konflik menyebut tanggal, porsi terpakai, dan kuota minimum yang boleh disimpan. `admin.js` sudah menampilkan pesan API pada `#product-error` untuk form/rentang; pastikan aksi hapus tunggal juga menampilkan pesan yang terbaca, bukan hanya toast singkat.
5. Pertahankan nilai 0 untuk tanggal tanpa pemakaian, override di atas/bawah default selama kuota efektif tetap cukup, serta pesanan `ditolak`/`dibatalkan` dan unpaid yang tenggatnya habis sesuai definisi pemakaian bersama.

## Tahap 3 — verifikasi dan dokumentasi

- Perluas `scripts/test-quota-api.php` dengan tabel TEMP dan fixture 40 porsi: default/override 39 ditolak, 40 diterima, 41 diterima, reset/delete yang kembali ke default 39 ditolak, tanggal dengan override tidak membatasi edit default, serta konflik pada satu hari membatalkan seluruh rentang. Tambahkan uji role Admin dan dua transaksi bersamaan bila PostgreSQL uji terisolasi tersedia.
- Jalankan PHP lint, uji API kuota, fixture browser auth, dan `git diff --check`. Uji browser tidak memerlukan login/database nyata; uji transaksi harus memakai PostgreSQL uji tanpa membaca atau mengubah `.env` dan database aplikasi.
- Perbarui `docs/01-spesifikasi/aturan-bisnis.md`, `api.md`, `database.md`, `docs/00-panduan/peta-kode.md`, dan `HANDOFF.md` bersama implementasi. Tidak perlu migrasi karena aturan dapat diperiksa dari tabel yang ada. Setelah perubahan kuota, minta review `reviewer` dengan diff, halaman Produk Admin, serta kriteria batas 39/40 dan atomisitas rentang.
- Kerjakan implementasi pada branch fitur dari `dev` saat diminta, sambil menjaga perubahan lokal yang sudah ada. `.opencode/revisi.md` tidak ada pada checkout ini; tidak ada item yang dapat dicentang.

## Batas rencana

Perubahan ini hanya menjaga kuota **produk** pada tanggal pesanan. Konsistensi kapasitas dapur gabungan 80% ketika kuota produk lain diturunkan memerlukan aturan tersendiri dan tidak diubah dalam rencana ini.
