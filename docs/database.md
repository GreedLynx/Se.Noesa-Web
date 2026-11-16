# Database

Foto bukti pesanan tersimpan melalui path `pesanan.foto_kiriman`. Penyelesaian
pesanan mempertahankan path dan file foto; perbaikan tampilannya tidak mengubah skema.

## Sumber kuota aktif

Skema awal `database/schema.sql` dilanjutkan seluruh migrasi melalui
`php scripts/migrate.php`. Runner memakai advisory lock prepared dan transaksi
per file; nama file tercatat di schema_migrations.

- `produk.kuota_harian`: default per hari, integer tidak negatif, default 50
  porsi setelah migrasi 017.
- `kuota_harian(produk_id,tanggal,kuota)`: override kapasitas total per tanggal;
  UNIQUE produk/tanggal dan indeks tanggal/produk berasal dari migrasi 008.
- Efektif `COALESCE(override.kuota, produk.kuota_harian)`; override 0 valid.
- Rentang inklusif dimaterialisasi menggunakan generate_series dan satu UPSERT
  ON CONFLICT per request. Save terbaru menimpa overlap. Reset menghapus override
  terpilih dan kembali ke default, bukan histori override lama.
- Checkout tidak mengurangi kuota. Pemakaian dijumlahkan dari detail pesanan
  selain ditolak/dibatalkan pada tanggal kirim Asia/Jakarta. Sisa minimal 0.

## Migrasi 015 dan VIEW

`015_default_product_quota.sql` mengisi default produk lama dari override bertanggal
terbesar (DISTINCT ON, tanggal DESC, id DESC). Produk tanpa override mempertahankan
defaultnya. Override lama dipertahankan. Backfill hanya sekali melalui ledger;
jangan menjalankan file manual lagi setelah Admin mengubah default.

Migrasi menambahkan CHECK produk_kuota_harian_nonnegative_check serta mengganti
VIEW v_kuota_harian dengan urutan/tipe kolom tetap:
produk_id,tanggal,kuota,terpakai,sisa. FULL JOIN mempertahankan pemakaian tanpa
override dan mengambil default produk. Produk/tanggal tanpa override maupun
transaksi tidak membentuk baris VIEW; helper laporan memakai LEFT JOIN dan
fallback default. Tidak menghasilkan deret tanggal tanpa batas.

Preflight read-only memeriksa nilai negatif dan dry-run selalu rollback sebelum
penerapan aktif. Data negatif tidak diperbaiki otomatis.

## Kuota default 50 porsi

Migrasi 017 mengatur `produk.kuota_harian` ke 50 untuk produk yang sudah ada
dan default kolom untuk produk baru. Nilai ini berlaku setiap tanggal tanpa
override di `kuota_harian`; override tanggal yang sudah tersimpan tetap berlaku.

## Batas pembayaran

Migrasi 018 mengizinkan status `dibatalkan`, membuat indeks pesanan yang belum
dibayar, dan mengecualikan pesanan `ditolak`/`dibatalkan` dari VIEW kuota.
`app/order-expiration.php` membatalkan pesanan `menunggu_pembayaran` setelah
satu jam berdasarkan `created_at`, hanya bila tidak ada bukti; jadwalkan
`scripts/expire-unpaid-orders.php --apply` setiap menit setelah inventaris/dry-run.
Katalog dan riwayat tidak memicu perubahan status. Helper memakai UPDATE
bersyarat atomik; CLI mempertahankan guard kegagalan dan mode read-only.

Dua migrasi bernomor 016 memiliki nama file berbeda dan tetap dipertahankan
untuk kompatibilitas ledger instalasi kedua branch. Runner mengurutkan nama
file lengkap; migrasi 018 mengulang constraint/VIEW yang setara dengan
016_order_cancellation tanpa menghapus data atau mengubah tipe kolom.

## Pesanan offline Admin

Migrasi 019 menambah `pesanan.sumber`, bernilai `online` (default untuk transaksi
lama dan checkout pelanggan) atau `offline`. Pesanan yang dicatat Admin langsung
berstatus `diproses`, menyimpan snapshot harga/detail, dan memakai perhitungan,
lock, kuota produk, serta batas dapur tanggal kirim yang sama dengan checkout.
Dengan demikian pesanan offline segera mengurangi sisa kuota pesanan online.

## Alasan penolakan pembayaran

Migrasi 020 menambahkan `pesanan.alasan_penolakan` (maksimal 500 karakter).
Penolakan pembayaran oleh Admin mewajibkan alasan; detail API pelanggan dan
riwayat menampilkan alasan tersebut untuk pesanan berstatus `ditolak`.

## Pemberitahuan perubahan menu

Migrasi 021 menambahkan tiga tabel tanpa mengubah `pesanan`, `detail_pesanan`,
status pesanan, status pembayaran, atau mekanisme kuota:

- `pemberitahuan_perubahan_menu` menyimpan produk sumber, snapshot kode/nama
  produk, catatan Admin, pembuat, dan waktu pembuatan.
- `pemberitahuan_perubahan_menu_opsi` menyimpan opsi yang tersedia untuk satu
  pemberitahuan (`menu_pengganti`, `hubungi_admin`, atau `tunggu_informasi`).
- `pemberitahuan_perubahan_menu_pesanan` menghubungkan pemberitahuan dengan satu
  `detail_pesanan`, menyimpan status respons, snapshot menu/kuantitas/harga lama
  dan pengganti, serta audit konfirmasi dan penyelesaian selisih harga.

Satu detail pesanan hanya dapat ditautkan satu kali pada pemberitahuan yang sama.
Relasi pemberitahuan, opsi, dan detail memakai `ON DELETE RESTRICT`; menghapus
pesanan/detail yang menjadi target tidak menghapus riwayat pemberitahuan.
Referensi produk lama/pengganti dapat menjadi NULL bila produk dihapus, tetapi
snapshot kode, nama, dan harga tetap tersimpan. Perubahan kuota atau penonaktifan
produk tidak menghapus tabel-tabel ini.

`status_respons` dan `status_penyelesaian_selisih` hanya mencatat alur pemberitahuan
dan penyelesaian selisih; keduanya bukan status pesanan maupun pembayaran.
`selisih_total` menyimpan selisih bertanda: positif untuk tagihan pelanggan dan
negatif untuk pengembalian. Status penyelesaian dapat menandai tindak lanjut
manual tanpa memindahkan dana atau mengubah status pembayaran.

Target pemberitahuan mengacu ke detail pesanan, sehingga kepemilikan pelanggan
harus selalu ditentukan melalui `detail_pesanan.pesanan_id` dan
`pesanan.user_id` pada query/API pelanggan. Pesanan offline tetap dapat ditautkan
untuk tindak lanjut manual karena target menggunakan detail pesanan dan tidak
mewajibkan `pesanan.user_id`.

Status pesanan aktif untuk pemilihan target adalah `menunggu_pembayaran`,
`menunggu_validasi`, dan `diproses`, mengikuti status yang masih dapat
ditindaklanjuti dalam alur pesanan saat ini. Status terminal tetap tersimpan
sebagai riwayat, tetapi tidak menjadi target pemberitahuan baru. Migrasi tidak
membuat pemberitahuan historis dan tidak menjalankan penggantian, pembatalan,
atau perubahan status otomatis.

Migrasi 022 menambahkan snapshot total pesanan sebelum/sesudah persetujuan serta
audit Admin dan catatan untuk tindak lanjut manual. Persetujuan memperbarui detail
menu dan total pesanan secara transaksional; status pesanan/pembayaran tidak
diubah. Selisih bertanda dicatat sebagai menunggu tagihan atau pengembalian.
Penyelesaian tersebut tetap manual dan tidak memindahkan uang.

Runner `scripts/migrate.php` mengurutkan nama file migrasi lengkap, merekam nama
file di `schema_migrations`, dan menjalankan setiap file dalam transaksi.
Migrasi 021 menggunakan `CREATE TABLE IF NOT EXISTS` dan `CREATE INDEX IF NOT
EXISTS`; migrasi 022 menambahkan kolom secara idempoten. Pemanggilan ulang
melalui runner melewati file yang sudah tercatat. Definisi akhir tabel dan
indeks juga ada pada `database/schema.sql` untuk instalasi baru. File skema
tersebut menghapus tabel saat inisialisasi; jangan jalankan pada database yang
sudah berisi data.

## Penguncian

Checkout mengunci tabel produk lalu kuota_harian, dan baris kuota/jadwal hingga
commit. Save/reset/delete override memakai transaksi dan lock tabel kuota_harian.
Update produk memperoleh ROW EXCLUSIVE implisit, menunggu lock checkout SHARE ROW
EXCLUSIVE. Pertahankan urutan lock dan validasi dalam transaksi checkout.

Penurunan kuota tidak mengubah pesanan lama: pemakaian tetap dihitung dan sisa
minimal 0. Batas dapur 80% berasal dari jumlah kuota efektif tanggal itu.

## Migrasi dan kompatibilitas lain

- Migrasi 010: idx_pesanan_created_status, idx_detail_pesanan_produk,
  idx_pesanan_delivery_status.
- Migrasi 012/013: token/OTP reset password memakai IF NOT EXISTS.
- Migrasi 014: CHECK harga/kuota/total/kapasitas tidak negatif, qty positif,
  minimum produk 4 porsi, urutan tanggal jadwal.
- Kolom lama produk.kuota dan produk.terpakai tetap ada tetapi tidak dipakai sebagai
  sumber kuota/counter. pesanan.pax mengikuti skema sampai rename disetujui.
- Dispatch memakai detail_pesanan.sudah_dicek dan tabel pesanan_foto_kiriman
  untuk menyimpan banyak foto. Kolom pesanan.foto_kiriman tetap dipertahankan
  sebagai kompatibilitas foto lama dan di-backfill oleh migrasi 016.
- Status penolakan ditolak; produk.status_aktif belum ada.
- jadwal_po, kalender dan konfigurasi rahasia tidak diubah.

## Migrasi 016: pembatalan

Pemulihan tampilan kartu pembatalan pelanggan tidak mengubah skema atau kontrak waktu.

`016_order_cancellation.sql` menambahkan `dibatalkan` ke `pesanan_status_check`
tanpa mengubah lima status lama. VIEW `v_kuota_harian` diganti dengan kolom/tipe
tetap, mengecualikan `ditolak` dan `dibatalkan`, sama dengan helper checkout.
Migrasi 006/015 tidak diubah. Tidak ada kolom, tabel, indeks, atau counter baru.
Skema awal juga menerima status batal; tetap jalankan seluruh migrasi sesudahnya.

`created_at` TIMESTAMPTZ menjadi dasar tenggat satu jam. Batas H-2 memakai tanggal
pengiriman/hari sekarang pada Asia/Jakarta, bebas dari zona sesi database.
Cancel/upload pembayaran dan upload galeri mengunci baris pesanan sebelum menulis.
Cancel/upload pembayaran memakai UPDATE bersyarat dan memeriksa
waktu aktual database setelah lock. Job memakai UPDATE bersyarat atomik yang
mengecek ulang status/bukti setelah menunggu lock. Urutan lock checkout tidak diubah.

Tidak ada penghapusan pesanan, detail, bukti, maupun foto. Refund manual di luar
aplikasi; tidak ada tabel status refund baru. Inventaris/dry-run job read-only;
lihat urutan aktivasi di README. Migrasi dan job pada database aplikasi belum
dijalankan oleh implementasi ini; pengujian memakai database khusus terisolasi.
