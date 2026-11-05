# Ringkasan Sesi Perapian Gaya Kode

Tanggal: 6 November 2026

## Tujuan

Menyusun pedoman gaya kode, merapikan area pelanggan secara bertahap, lalu
memastikan perubahan tidak mengubah perilaku aplikasi.

## Dokumentasi yang ditambahkan

- `docs/gaya-kode.md` sebagai pedoman PHP, SQL, HTML, CSS, JavaScript, API,
  dokumentasi, dan commit.
- README menautkan pedoman tersebut.

## Perubahan kode

### Fondasi dan kuota

- `app/bootstrap.php`: indentasi, nama variabel lokal, blok kondisi, dan tipe
  pengembalian `json()` dirapikan.
- `app/daily-quota.php`: nama parameter, format array, dan format query SQL
  dirapikan tanpa mengubah aturan kuota.

### Pelanggan

- `public/pelanggan/api/catalog.php`: format query, filter katalog, dan respons
  JSON dirapikan.
- `public/pelanggan/api/orders.php`: perapian seluruh alur alamat, unggah bukti
  pembayaran, checkout, detail pesanan, dan riwayat.
- `public/pelanggan/api/payment-methods.php` dan `public/pelanggan/api/auth.php`:
  perapian endpoint kecil tanpa perubahan kontrak.
- `public/pelanggan/index.php`: blok PHP, struktur markup katalog, toolbar,
  keranjang, dan data awal JavaScript dirapikan.
- `public/pelanggan/riwayat.php`: perapian markup halaman riwayat. Perubahan ini
  masih belum di-commit saat dokumen dibuat.

## Perbaikan bug

Penyimpanan alamat baru gagal saat `is_default` bernilai `false`. Penyebabnya
adalah parameter boolean yang diteruskan melalui `PDOStatement::execute()` tanpa
tipe eksplisit. Perbaikan dilakukan di `public/pelanggan/api/orders.php` dengan
`bindValue(6, $isDefault, PDO::PARAM_BOOL)`.

Hasil verifikasi manual: alamat default maupun non-default dapat disimpan.

## Konflik merge

Konflik diselesaikan pada:

- `public/login.php`
- `public/owner/dashboard.php`
- `public/pelanggan/riwayat.php`

Tautan forgot password dari cabang lain tidak dipertahankan karena fitur tersebut
masih menjadi keputusan terbuka tim. Konflik lain hanya perbedaan format markup.

## Pengujian

- `php -l` dijalankan untuk file PHP yang diubah pada setiap batch.
- `git diff --check` dijalankan pada setiap batch.
- Self-check `daily_quota_validate()` lulus.
- Pengujian manual pelanggan berhasil untuk katalog, pembuatan pesanan, unggah
  bukti pembayaran, serta penyimpanan alamat default dan non-default.

## Commit terkait

- `91cfe92` - gaya kode awal.
- `2e24edf` - merge `dev` ke branch kerja dan resolusi konflik.
- `b266803` - perapian gaya kode.
- `a3d3105` - perapian endpoint API pelanggan.
- `34bcfe9` - perapian halaman katalog pelanggan. Prefix commit tertulis
  `rafacter:`; gunakan `refactor:` pada commit berikutnya.

## Langkah berikutnya

1. Uji tampilan tab dan dialog pada halaman riwayat pelanggan.
2. Commit perubahan `public/pelanggan/riwayat.php` dengan pesan:
   `refactor: rapikan gaya halaman riwayat pelanggan`.
3. Lanjutkan perapian ke JavaScript pelanggan atau area Admin/Owner secara
   bertahap, dengan lint dan uji manual setelah setiap batch.
