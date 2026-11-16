# Rencana backend pembatalan pesanan

## Primary goal

Pesanan tanpa bukti pembayaran kedaluwarsa setelah satu jam agar kuota tersedia
kembali. Pelanggan dapat membatalkan pesanan yang memenuhi syarat sampai batas
H-2 sebelum pengiriman, tanpa mengubah pesanan milik orang lain atau menimpa
pembayaran yang sedang diproses.

Status 12 November 2026: implementasi kode dan pengujian terisolasi selesai pada
branch `feature/pembatalan-pesanan`. Branch dibuat dari HEAD yang sudah memuat
`dev` dan prasyarat kuota, karena `dev` lokal tertinggal. Perubahan foto Admin yang
sudah ada dipertahankan. Migrasi/job belum diaktifkan pada database aplikasi.

## Hasil pelaksanaan

| Fase | Hasil |
|---|---|
| 0–1 | Discovery dan keputusan bisnis selesai; aturan final ada di bawah |
| 2 | Migrasi 016 + schema + helper kuota/VIEW mengecualikan batal |
| 3 | Cancel tiga status sampai akhir H-2; lock/UPDATE bersyarat dan cleanup upload |
| 4 | CLI `--inventory`, `--dry-run`, `--apply`; panduan cron/Task Scheduler di README |
| 5 | Kontrak izin/waktu detail, filter/count Admin/Owner dan pesan refund pelanggan |
| 6 | Uji PostgreSQL batas waktu/role/kuota/migrasi/balapan, kuota/API lama, UI VM, lint dan review |

Aktivasi yang masih harus dilakukan di lingkungan target: inventaris dan dry-run
pesanan lama, penerapan migrasi, menjalankan/mengecek scheduler setiap menit,
serta uji upload browser. Tidak membaca/mengubah `.env`. WhatsApp Admin menyusul
setelah nomor/tautan diberikan; tidak ada integrasi refund otomatis.

## Keputusan final pengguna

| Pokok | Usulan | Status |
|---|---|---|
| Awal satu jam | `pesanan.created_at`, bukan waktu halaman dibuka | Disetujui |
| Kapan dianggap sudah membayar | Upload bukti berhasil mengubah status menjadi `menunggu_validasi`; waktu validasi Admin tidak membebani pelanggan | Disetujui |
| Status yang boleh dibatalkan manual | `menunggu_pembayaran`, `menunggu_validasi`, `diproses` | Disetujui |
| Arti H-2 | Sampai akhir hari H-2 Asia/Jakarta, bukan tepat 48 jam | Disetujui |
| Pesanan lama saat aktivasi | Ikut tenggat satu jam sejak dibuat setelah inventaris dan dry-run | Disetujui |
| Constraint database | Tambahkan `dibatalkan` lewat migrasi 016 | Disetujui |
| Uang yang sudah ditransfer | Refund penuh ditangani Admin di luar aplikasi; kontak WhatsApp menyusul | Disetujui |

Pembatalan setelah bukti diunggah mengikuti refund penuh oleh Admin di luar
aplikasi, termasuk status `menunggu_validasi`/`diproses`, tetap sampai akhir H-2.
Upload/validasi yang selesai dahulu dapat diikuti pembatalan yang sah; pembatalan
yang menang lebih dahulu mencegah upload/validasi/penyelesaian menghidupkan pesanan.
Backend tidak dapat memastikan transfer bank terjadi; upload hanya menjadi
penanda operasional untuk menghentikan tenggat.

Bagian fase berikut dipertahankan sebagai rencana acuan; temuan Phase 0 dan
keadaan saat rencana ditulis adalah catatan sebelum implementasi.

## Phase 0 — Discovery dokumentasi dan sumber

**How this serves the primary goal:** memastikan aturan baru memakai alur nyata
dan tidak menduplikasi endpoint yang sudah ada.

Sudah dibaca: `docs/peta-kode.md`, `docs/aturan-bisnis.md`, `docs/api.md`,
`docs/database.md`, endpoint pelanggan/Admin, helper kuota, skema dan migrasi.
`.opencode/revisi.md` tidak tersedia pada pemeriksaan sesi ini.

Temuan:

- `public/pelanggan/api/orders.php`: POST `action=cancel_order` sudah ada;
  status yang diterima `menunggu_pembayaran` dan `menunggu_validasi`.
  SELECT awal membatasi `user_id`, tetapi UPDATE akhirnya hanya memakai `id`.
- Perhitungan `$today->diff($deliveryDate)->days` mengambil besar selisih tanpa
  arah; tanggal lama bisa lolos. Tidak ada penguncian antara SELECT dan UPDATE.
- Upload memakai multipart `action=upload_proof`; mengubah unpaid menjadi
  `menunggu_validasi`. UPDATE sudah menyaring pemilik/status, tetapi tidak
  memeriksa jumlah baris berubah; perlu menangani kalah balapan sebagai gagal.
- `database/schema.sql` dan `006_order_payment_status.sql`: CHECK hanya menerima
  lima status, belum `dibatalkan`. Kolom waktu sudah TIMESTAMPTZ.
- `app/daily-quota.php`, fungsi `daily_quota_snapshot()`, dan VIEW pada migrasi
  015 menghitung semua status selain `ditolak`; batal masih memakai kuota.
- UI pelanggan sudah mengenal `dibatalkan` dan `cancel_order`; tetap perlu
  kontrak backend agar UI tidak menebak batas sendiri.
- Belum ditemukan scheduler kedaluwarsa pada `scripts/` atau README.

Allowed APIs/pola yang tersedia:

- `require_role('pelanggan')`, `q($sql, $params)`, `db()`, `body()`, `json()`:
  `app/bootstrap.php` dan penggunaannya pada endpoint pesanan.
- Transaksi `beginTransaction()/commit()/rollBack()` dan SELECT FOR UPDATE:
  pola penolakan `public/admin/api/orders.php`.
- UPDATE status bersyarat dan `rowCount()`: akhir transisi status endpoint Admin.
- Guard `PHP_SAPI !== 'cli'`: awal `scripts/create-admin.php`.
- Uji PostgreSQL TEMP dan respons endpoint: `scripts/test-daily-quota.php`,
  `scripts/test-quota-api.php`.

Verifikasi: cocokkan daftar ini lagi pada branch implementasi. Hindari framework,
folder MVC baru, dependency scheduler, akses `.env`, dan asumsi tabel status baru.

## Phase 1 — Tetapkan kontrak waktu, status, dan aktivasi

**How this serves the primary goal:** pelanggan memperoleh batas yang konsisten
dan pembayaran yang sah tidak dibatalkan hanya karena antrean validasi.

Implementasi fase ini berupa keputusan/dokumentasi sebelum kode:

1. Jawab keputusan terbuka di atas, termasuk pesanan lama.
2. Usulan transisi otomatis: `menunggu_pembayaran -> dibatalkan` ketika waktu
   database mencapai `created_at + interval '1 hour'` dan belum ada bukti bayar.
   Tepat tenggat dianggap kedaluwarsa; jangan memperpanjang dari status_updated_at.
3. Transisi manual menggunakan `cancel_order` yang sudah ada. Status terminal
   tidak dapat hidup kembali; `selesai` dan `ditolak` tidak dapat dibatalkan.
4. Jika dipilih kalender H-2, kirim 20 November: boleh sampai 18 November
   23:59:59… WIB; tolak mulai 19 November 00:00 WIB. Gunakan perbandingan
   langsung tanggal lokal, bukan nilai absolut selisih hari. Tanggal NULL/lama
   ditolak untuk pembatalan manual.
5. Pembatalan otomatis satu jam terpisah dari batas H-2 pembatalan manual.

Rujukan: status dan created_at di schema; upload_proof/cancel_order endpoint
pelanggan. Verifikasi tabel contoh sebelum/saat/sesudah kedua tenggat disepakati.
Guard: jangan menganggap jawaban yang belum masuk sebagai persetujuan refund.

## Phase 2 — Siapkan database dan lepaskan kuota

**How this serves the primary goal:** pesanan batal dapat disimpan dan langsung
berhenti menahan ketersediaan produk/dapur.

- Setelah persetujuan tim untuk perubahan constraint, buat migrasi baru dengan
  nomor berikutnya (kandidat 016; periksa ulang saat implementasi).
- Tambahkan `dibatalkan` pada CHECK status, pertahankan kelima status lama.
  Sinkronkan schema awal; jangan mengedit migrasi 006/015 yang sudah diterapkan.
- Ganti definisi VIEW lewat migrasi baru dan samakan agregasi helper:
  kecualikan `ditolak` serta `dibatalkan`, termasuk query checkout langsung
  dan jalur laporan yang memakai VIEW. Telusuri semua pemakaian filter status.
- Pertahankan pesanan/detail/foto/bukti sebagai riwayat; jangan hapus baris atau
  menaikkan counter kuota. Pemulihan berasal dari agregasi status.
- Gunakan `created_at` untuk deadline; belum perlu kolom deadline, tabel job,
  maupun kolom alasan pembatalan. Indeks `(status, created_at)` sudah ada pada
  schema; periksa keadaan instalasi dan query plan sebelum menambah indeks.

Rujukan: schema, migrations/006, 010, 015, `app/daily-quota.php`.
Verifikasi: fresh migration dan upgrade TEMP, status lama tetap valid,
status asing ditolak, kuota produk/dapur kembali, VIEW/helper/checkout setara.
Guard: jangan menerapkan migrasi/data produksi saat hanya mengerjakan plan.

## Phase 3 — Pembatalan manual dan upload yang aman saat bersamaan

**How this serves the primary goal:** pembatalan hanya berlaku untuk pemilik dan
tidak menimpa upload atau validasi Admin yang lebih dahulu berhasil.

- Perbaiki cabang `cancel_order`, jangan membuat endpoint pembatalan kedua.
- Gunakan transaksi dan kunci baris pesanan atau UPDATE bersyarat atomik yang
  menyertakan pemilik, status asal, dan batas waktu. Semua validasi status/waktu
  harus diperiksa pada keadaan yang dikunci, bukan SELECT lama.
- Upload bukti harus memeriksa deadline menggunakan waktu database pada langkah
  mutasi yang menentukan hasil; scheduler yang terlambat tidak membuat upload
  setelah tenggat diterima. Upload selesai sebelum tenggat harus selamat dari job.
- Periksa `rowCount()` upload: nol baris bukan sukses; bersihkan file baru yang
  gagal dikaitkan, pertahankan file lama, kembalikan konflik HTTP 409.
- Pertahankan UPDATE status bersyarat Admin; uji balapan validasi vs pembatalan
  bila status menunggu_validasi akhirnya diizinkan untuk dibatalkan.
- Semua jalur memakai urutan lock konsisten; jangan menambah penguncian tabel
  produk setelah mengunci pesanan jika jalur checkout melakukan urutan sebaliknya.
- Pertahankan JSON lama; sukses cancel dapat menambah `status: dibatalkan`.
  Role salah 403, pesanan bukan milik sendiri 404, konflik status/tenggat 409.
  Dokumentasikan perubahan cancel lama dari 422 ke409 bila dipakai.
- Untuk handoff frontend, usulkan field tambahan deadline ISO 8601 ber-offset dan
  `can_cancel` dari aturan server pada detail; implementasikan hanya field yang
  dipakai frontend, jangan membuat countdown/UI pada pekerjaan backend ini.

Rujukan: cancel_order/upload_proof pelanggan, reject/complete Admin.
Verifikasi: pemilik lain, payload invalid, status akhir, upload valid/tidak valid,
uji dua koneksi yang berlomba dan pengecekan file gagal tersimpan.
Guard: jangan menerima role/user_id/status tujuan atau waktu sekarang dari klien.

## Phase 4 — Job kedaluwarsa tanpa kunjungan pengguna

**How this serves the primary goal:** pesanan yang ditinggalkan tetap dibatalkan
meskipun tidak ada pengguna membuka aplikasi.

- Tambah helper kecil di `app/` hanya bila dibagi CLI dan endpoint; ikuti fungsi
  native yang ada. Gunakan prepared UPDATE bersyarat, aman diulang dan aman
  jika dua job berjalan bersamaan. Syarat minimal status unpaid, tanpa bukti,
  created_at sudah melewati deadline; data NULL/inkonsisten dilaporkan.
- Tambah `scripts/expire-unpaid-orders.php`, guard CLI-only, keluaran jumlah
  perubahan dan exit nonzero saat gagal. Tidak ada route HTTP publik untuk job.
- Jalankan setiap menit lewat cron hosting atau Windows Task Scheduler lokal.
  README harus memuat perintah, path PHP/proyek, log, dan cara cek job bekerja.
- Jelaskan batas operasional: mutasi upload ditolak tepat mulai tenggat,
  status pembatalan dan kuota bisa terlambat sampai siklus job berikutnya
  (sekitar satu menit saat scheduler sehat), lebih lama jika job mati.
  Jangan menjanjikan perubahan background tepat per detik.
- Sebelum aktivasi, inventaris jumlah unpaid lama dan lakukan dry-run read-only.
  Terapkan kebijakan transisi yang disetujui; jangan mass-cancel tanpa inventaris.

Rujukan: guard CLI create-admin, prepared q(), pola skrip migrasi.
Verifikasi: tanpa traffic web, dua eksekusi berulang, overlap job/upload,
job gagal dan pulih, scheduler benar-benar dijalankan di lingkungan target.
Guard: jangan mengandalkan JavaScript, GET riwayat, atau kunjungan katalog sebagai
satu-satunya pemicu. Jangan daftarkan scheduled task di komputer saat tahap plan.

## Phase 5 — Konsistensi laporan dan kontrak frontend

**How this serves the primary goal:** riwayat menjelaskan pembatalan dengan benar
dan laporan tidak memperlakukan pesanan batal sebagai transaksi berhasil.

- Telusuri `public/admin/api/orders.php` filter semua/detail/counts dan endpoint
  Owner: status batal harus bisa ditemukan Admin dan tetap read-only pada Owner.
- Pertahankan pendapatan hanya dari `selesai`; jangan mengurangi pendapatan
  dua kali atau menghapus histori. Jumlah semua pesanan boleh tetap mencakup
  batal jika definisi total adalah semua pesanan; dokumentasikan definisinya.
- UI pelanggan sudah punya label batal. Catat handoff penyelarasan tombol H-2,
  status/filter Admin/Owner dan deadline; backend menjadi sumber kebenaran.
- Perbarui `docs/api.md`, `docs/database.md`, `docs/peta-kode.md`,
  `docs/aturan-bisnis.md` dan README pada commit implementasi yang sama.

Rujukan: API dashboard/orders Admin/Owner, customer.js cancel_order/status.
Verifikasi: pendapatan tetap, pesanan batal terakses pemilik/Admin,
Owner dilarang menulis, frontend lama menampilkan pesan konflik dari server.
Guard: jangan memfinalisasi desain Admin/Owner atau menambah halaman pada scope BE.

## Phase 6 — Verifikasi dan review

**How this serves the primary goal:** batas waktu, kuota dan hak akses terbukti
sebelum pembatalan otomatis dijalankan terhadap pesanan nyata.

Tambahkan uji backend mengikuti TEMP PostgreSQL dan harness endpoint yang sudah
ada; gunakan waktu uji yang tetap dan dua koneksi untuk balapan transaksi.

| Kasus | Hasil yang diharapkan setelah keputusan disepakati |
|---|---|
| Unpaid usia 59:59 | Tetap unpaid |
| Tepat 60:00 / lebih | Tidak menerima bukti terlambat; job membatalkan |
| Upload valid menang sebelum deadline | Menunggu validasi; tidak dibatalkan job |
| Validasi Admin tertunda berjam-jam | Tidak auto-cancel jika bukti sudah diterima |
| Menunggu validasi/diproses/selesai/ditolak/batal | Tidak diubah job unpaid |
| Job dijalankan ulang/bersamaan | Aman; tidak menggandakan efek kuota |
| Batal manual tepat H-2 akhir hari vs H-1 awal hari | Terima lalu tolak untuk opsi kalender |
| Tanggal pengiriman kemarin atau NULL | Tolak pembatalan manual |
| Cancel/upload/validasi bersamaan | Hanya transisi sah berhasil; tidak ada sukses palsu |
| Milik pelanggan lain / role salah | 404 / 403, tanpa perubahan data |
| Pesanan dibatalkan | Tidak dihitung kuota; riwayat tetap ada |
| Scheduler tidak berjalan | Upload terlambat tetap ditolak; log/gap operasional jelas |
| Zona DB bukan Jakarta | Tenggat satu jam dan kalender H-2 tetap benar |

Jalankan lint PHP, uji kuota/API terkait, uji migrasi fresh/upgrade dan diff check.
Delegasikan review checkout/pembayaran/kuota/hak akses ke `reviewer` sesuai
AGENTS; tindak lanjuti temuannya sebelum menandai tugas selesai. Laporkan tes
yang benar-benar dijalankan, bukan sekadar daftar rencana. Implementasi dibuat
pada feature branch dari dev yang sudah memuat prasyarat; jangan mencampur
perubahan foto Admin yang masih berada di working tree sesi ini.

## Batas scope dan keadaan saat rencana ditulis

Belum ada implementasi pembatalan, migrasi, akses database, cron, atau perubahan
`.env` pada tugas plan ini. Refund otomatis, integrasi bank/payment gateway,
notifikasi email/WhatsApp, desain UI, dan histori alasan pembatalan ditunda.
Risiko yang harus ditutup sebelum aktivasi: kebijakan pesanan yang sudah dibayar,
pesanan lama, persetujuan constraint database, dan ketersediaan scheduler hosting.
