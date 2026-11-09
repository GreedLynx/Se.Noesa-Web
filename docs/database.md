# Database

Revisi tampilan pemilih tanggal pelanggan/Admin/Owner tidak mengubah skema,
VIEW, migrasi, atau data kuota. Tanggal pilihan tetap diteruskan melalui
parameter `tgl_kirim` yang sudah ada.
Perbaikan grid desktop Owner juga tidak memerlukan migrasi atau perubahan data.

Pelaksana migrasi `scripts/migrate.php` mengikat kunci advisory lock melalui PDO
prepared statement untuk `pg_advisory_lock` dan `pg_advisory_unlock`. Pelepasan
lock tetap berada pada blok `finally`. Perapian SQL tidak mengubah skema;
kolom lama `pesanan.pax` dipertahankan sampai keputusan rename dan migrasinya disetujui.

## Plan 1 — sumber kuota aktif

Konfigurasi adalah `kuota_harian(produk_id,tanggal,kuota)` dari migrasi 008,
dengan UNIQUE produk/tanggal dan indeks tanggal yang sudah ada. Kuota menyimpan
kapasitas total dan tidak dikurangi checkout. Kolom master produk dipertahankan
tetapi tidak menjadi fallback.

Migrasi `011_daily_quota_report.sql` membuat VIEW `v_kuota_harian`
(`produk_id,tanggal,kuota,terpakai,sisa`). FULL JOIN menjaga pemakaian pesanan yang
konfigurasinya telah dihapus. Hanya transaksi selain `ditolak` dihitung.
Laporan memakai VIEW, checkout memakai rentang timestamp Asia/Jakarta dengan PDO.

Checkout mengunci produk, tabel kuota, dan baris kuota tanggal yang dipilih hingga
commit. Penulisan konfigurasi juga mengunci tabel kuota. Kunci tabel menserialisasi
checkout antar tanggal sekalipun untuk melindungi insert/delete, termasuk tanggal
yang belum memiliki baris. Tidak ada counter pemakaian.

Tidak ada pengisian data otomatis. Admin harus memeriksa konfigurasi lama:
implementasi lama pernah mengurangi kuota, sehingga nilainya mungkin bukan kapasitas total.

Bagian berikut mencatat implementasi sebelum Plan 1.

## Revisi kuota stateless

Skema yang digunakan adalah `database/schema.sql` beserta file di `database/migrations/`.
Migrasi baru `010_stateless_quota_indexes.sql` menambahkan indeks:

- `idx_pesanan_created_status` pada `pesanan(created_at, status)`.
- `idx_detail_pesanan_produk` pada `detail_pesanan(produk_id, pesanan_id)`.
- `idx_pesanan_delivery_status` pada `pesanan(tgl_kirim, status)`.

Nama kolom pada contoh dokumen revisi disesuaikan dengan skema sebenarnya:

| Nama dalam contoh revisi | Nama pada aplikasi |
|---|---|
| `pesanan.status_pesanan` | `pesanan.status` |
| `detail_pesanan.kuantitas` | `detail_pesanan.qty` |
| `produk.min_order` | `produk.min_pesan` |
| `produk.nama_produk` | `produk.nama` |
| `pesanan.pelanggan_id` | `pesanan.user_id` |

Checklist dispatch menggunakan `detail_pesanan.sudah_dicek` dan `pesanan.foto_kiriman`.
Status pembatalan/penolakan yang tersedia adalah `ditolak`.
Kolom `produk.status_aktif` belum ada.

Migrasi 009 menambahkan `produk.kuota_harian` dengan default 0. Admin mengatur
nilai master melalui API produk. Nilai 0 berarti produk tidak tersedia.
Data kuota lama tidak disalin otomatis. Setelah migrasi, Admin perlu menetapkan
kuota harian agar produk tersedia untuk pelanggan.

Migrasi 008 dan tabel `kuota_harian` dipertahankan sebagai riwayat, tetapi tidak
menentukan checkout. Kolom lama `produk.kuota` dan `produk.terpakai` juga dipertahankan;
checkout dan penolakan tidak memperbarui counter tersebut.

Keputusan pengguna: kuota dihitung per tanggal pengiriman dalam Asia/Jakarta;
produk aktif adalah produk dengan `kuota_harian > 0`. Agregasi menggunakan rentang
`tgl_kirim >= awal_hari AND tgl_kirim < awal_hari_berikutnya` agar indeks bisa dipakai.
Semua status kecuali `ditolak` dihitung, termasuk pesanan belum dibayar dan selesai.
Batas dapur adalah pembulatan ke bawah 80% dari jumlah kuota master produk aktif.

Checkout mengambil `LOCK TABLE produk IN SHARE ROW EXCLUSIVE MODE` sebelum membaca
master dan agregasi, hingga header dan detail pesanan selesai disimpan. Kunci ini
menserialisasi checkout untuk produk berbeda sekalipun serta menunggu perubahan
master oleh admin. Pemakaian tetap dihitung dari detail transaksi, bukan counter.

Migrasi 009 dan 010 sudah diterapkan melalui `scripts/migrate.php` pada lingkungan
pengembangan sesi ini. Ketiga indeks diverifikasi lewat `pg_indexes`.
Untuk lingkungan lain, jalankan migrasi sebelum menyajikan aplikasi.
