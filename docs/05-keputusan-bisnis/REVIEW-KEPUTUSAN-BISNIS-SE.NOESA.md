# Review Keputusan Bisnis se.noesa — Post-Implementation

- Tanggal: 2026-11-18
- Sumber: `docs/05-keputusan-bisnis/KEPUTUSAN-BISNIS-se.noesa.md` (14 keputusan + DECISION-NEW-01)
- Branch / HEAD: `feature/full-stack` / `43da86d`
- Sifat dokumen: **REVIEW ONLY**. Tidak ada kode, database, atau status keputusan yang diubah.

> **Aturan penting:** Seluruh 15 keputusan masih **PENDING**. Rekomendasi di bawah adalah bahan pertimbangan untuk pemilik proyek, **bukan pengganti keputusan**. Kolom "Keputusan final" di `docs/05-keputusan-bisnis/KEPUTUSAN-BISNIS-se.noesa.md` tetap kosong sampai ada persetujuan tertulis.

## Ringkasan prioritas jawaban

| Prioritas | Keputusan | Alasan mendesak |
|---|---|---|
| WAJIB sebelum GO | #3, #4, #5, #6, #8, #9, #10, #11, #12, #13, #14, NEW-01 | Mengunci task yang tersisa dan/atau menyatakan kebijakan yang belum disetujui di UI |
| WAJIB sebelum fitur pembatalan/refund final | #1, #2 | Menentukan kebijakan uang dan batas waktu; saat ini teks UI sudah menyatakan "refund penuh" lebih dulu |
| Dapat menyusul, tapi tetap perlu jawaban | #7 | Fitur libur sudah jalan; hanya pengecualian yang belum didefinisikan |

**Wajib dijawab pemilik proyek: semua (#1–#14 dan NEW-01).** Tidak ada keputusan yang dapat ditentukan oleh agent.

---

## #1 — Refund pembayaran terverifikasi

- **Masalah:** Jika pembayaran sudah diverifikasi Admin lalu pesanan dibatalkan karena kondisi khusus, tidak ada aturan apakah uang dikembalikan penuh, sebagian, atau tidak. Saat ini `public/pelanggan/assets/customer.js:563` sudah menampilkan teks “refund penuh ditangani Admin di luar aplikasi” — ini adalah pernyataan kebijakan yang belum disetujui.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum GO.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Refund manual penuh | Tambah field/status `refund` (mis. `refund_status`, `refund_amount`) bila ingin dicatat di aplikasi | Endpoint pembatalan mencatat maksud refund; tetap **tanpa** transfer otomatis | Teks konfirmasi menyebut refund penuh; perlu sinkron dengan kenyataan | Admin mencatat transfers manual di luar aplikasi; perlu SOP bukti transfer |
| (b) Refund manual sebagian | Perlu field jumlah refund + alasan | Perlu validasi jumlah ≤ total pesanan | Form/teks harus menyebut nominal bisa sebagian | Preset aturan potongan (biaya dapur?) harus ditetapkan |
| (c) Tanpa refund | Tidak perlu field baru | Tidak ada perubahan alur; hapus/ubah teks refund | Teks “refund penuh” **harus dihapus/dikoreksi** | CS perlu skrip penjelasan ke pelanggan |

- **Rekomendasi (bukan keputusan):** (a) dengan pencatatan manual, karena teks UI saat ini sudah mengarah ke refund penuh dan transparansi lebih mudah dioperasionalkan; namun ini **harus** dikonfirmasi pemilik karena menyangkut kewajiban finansial.
- **Task terdampak:** TASK-04 (kebijakan), OBS-003.

## #2 — Definisi H-2

- **Masalah:** Batas pembatalan H-2 saat ini dihitung dengan perbandingan tanggal kalender Asia/Jakarta (`public/pelanggan/api/orders.php:27-28`), bukan 48 jam tepat sebelum jam kirim. Teks UI menyebut “akhir hari WIB”.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum GO.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Tepat 48 jam sebelum jam kirim | Tidak perlu skema baru | Ubah predikat ke `tgl_kirim - interval '48 hours' >= now()`; perlu uji timezone | Teks batas harus menampilkan tanggal **dan jam** | CS perlu memahami batas jam, bukan hanya tanggal |
| (b) Dua hari kalender (status quo) | Tidak ada | Pertahankan query; dokumentasikan aturan agar tidak diubah keliru | Teks “akhir hari WIB” sudah sesuai | Paling sederhana; batas lebih longgar |

- **Rekomendasi (bukan keputusan):** (b) bila tidak ada keluhan pelanggan, karena sesuai implementasi dan teks yang sudah tampil; (a) jika bisnis ingin presisi jam. Pemilik yang menentukan.
- **Task terdampak:** kebijakan pembatalan (TASK-04 area).

## #3 — Tindakan saat kuota diubah menjadi 0

- **Masalah:** `public/admin/api/kuota-harian.php:61-75` menerima `kuota = 0` tanpa konfirmasi, tanpa daftar pesanan terdampak, dan tanpa perlakuan khusus pesanan yang sudah masuk. `daily_quota_snapshot` tetap menghitung pesanan lama sebagai pemakaian.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum GO / sebelum TASK-03.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Pesanan tetap diproses | Tidak ada perubahan | Cukup dialog konfirmasi; tidak ada mutasi pesanan | Warning menampilkan jumlah pesanan terdampak | Risiko dapur kelebihan kapasitas; perlu koordinasi manual |
| (b) Perlu konfirmasi pelanggan | Perlu status/flag respons | Perlu alur notifikasi + state baru | UI notifikasi pelanggan | Alur lebih panjang; SLA perlu ditetapkan |
| (c) Dibatalkan Admin | Perlu catatan alasan & audit | Mutasi pesanan dalam transaksi + lock kuota | Dialog konfirmasi dengan daftar pesanan | Berdampak finansial ⇒ terkait keputusan #1 |

- **Rekomendasi (bukan keputusan):** (a) untuk meminimalkan risiko hukum/finansial, dengan dialog informatif (dampak terlihat tetapi pesanan lama tidak disentuh). Ini juga selaras dengan prinsip TASK-03: jangan membatalkan pesanan tanpa perintah eksplisit.
- **Task terdampak:** TASK-03 (BLOCKED).

## #4 — Catatan perubahan kuota

- **Masalah:** Tidak ada kolom/tabel untuk alasan perubahan kuota. Admin bisa mengubah kuota menjadi 0 tanpa jejak alasan.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum GO / sebelum TASK-03.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Audit internal saja | Migration tabel `kuota_perubahan_log` (produk, tanggal, kuota lama/baru, alasan, admin, waktu) | Tulis log dalam transaksi yang sama dengan update kuota | Field alasan di dialog Admin | Jejak internal untuk investigasi |
| (b) Internal + tampil ke pelanggan | Sama + relasi ke pesanan terdampak | Endpoint/detail pesanan mengembalikan catatan | Notifikasi/teks pada detail pelanggan | Potensi kekhawatiran pelanggan; perlu template bahasa |

- **Rekomendasi (bukan keputusan):** (a) lebih aman sebagai langkah pertama; (b) dapat menyusul setelah template notifikasi disepakati. Catatan: migration baru **belum dijalankan** dan memerlukan DB test terpisah.
- **Task terdampak:** TASK-03 (BLOCKED).

## #5 — Kapan pengingat bukti pembayaran muncul/berhenti

- **Masalah:** `IMG-CUS-02` belum ada. Status `menunggu_validasi` dengan `bukti_bayar` kosong sebenarnya tidak mungkin lewat jalur normal (validasi Admin mensyaratkan bukti), tetapi bisa terjadi pada data lama/edge case. Pengingat tanpa batas berisiko mengganggu.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum TASK-15.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Saat pesanan menunggu pembayaran & belum lewat tenggat | Tidak ada | Turunkan flag `can_upload_proof` + countdown dari `created_at + 1 jam` | Banner pada detail/riwayat | Otomatis hilang saat status berubah |
| (b) Saat status menunggu verifikasi tetapi bukti kosong | Tidak ada | Query khusus data anomaly | Banner khusus | Perlu audit data lama (4 pesanan ditolak tanpa alasan, bukan kasus ini) |
| (c) Sampai verifikasi Admin | Tidak ada | Perlu state “sudah diingatkan” agar tidak berulang | Perlu tombol tunda/tutup | Risiko notifikasi berulang tanpa batas |

- **Rekomendasi (bukan keputusan):** kombinasi (a) + berhenti otomatis pada `menunggu_validasi`/`selesai`/`dibatalkan`, tanpa state baru. Perlu konfirmasi pemilik.
- **Task terdampak:** TASK-15 (BLOCKED), TASK-04.

## #6 — Nomor WhatsApp

- **Masalah:** Tidak ada nomor WA di aplikasi. Perlu sumber nomor (env/app setting) dan lokasi tampil yang disetujui.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum TASK-08.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| Sumber: env `WHATSAPP_NUMBER` | Tidak ada | Validasi format E.164 di server; variabel tidak boleh masuk Git | Tautan `wa.me/<nomor>` | Ganti nomor tanpa deploy kode (restart/env) |
| Sumber: tabel `pengaturan` | Migration tabel setting | API setting + validasi role | Ambil dari API | Bisa diubah Admin tanpa redeploy, tapi perlu hak akses |
| Nomor per role (Admin/CS/Owner) | Migration/setting multi-baris | Endpoint memilih nomor aktif | Tampil sesuai konteks halaman | Lebih fleksibel, lebih kompleks |

- **Lokasi tampil (pilih):** katalog Pelanggan / detail pesanan / halaman pembayaran / footer semua halaman. **Nomor pribadi tidak boleh di-hardcode.**
- **Rekomendasi (bukan keputusan):** env `WHATSAPP_NUMBER` + tampil di halaman pembayaran/detail pesanan. Alasan: perubahan nomor murah, tidak membuka mutasi data oleh Admin.
- **Task terdampak:** TASK-08 (BLOCKED).

## #7 — Pengecualian tanggal libur

- **Masalah:** Fitur libur sudah berjalan (`tanggal_libur_operasional`), dan `daily_quota_snapshot` menetapkan **semua** produk kuota 0 pada tanggal libur. Belum ada aturan pengecualian produk/acara.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: dapat menyusul.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Semua produk tutup | Tidak ada | Pertahankan logika saat ini | Pesan libur sudah ada | Paling sederhana & konsisten |
| (b) Ada pengecualian | Perlu tabel/field relasi libur–produk | `daily_quota_snapshot` perlu logika pengecualian + lock yang sama | UI Admin untuk memilih produk yang dikecualikan | Perlu aturan siapa yang boleh mengecualikan |

- **Rekomendasi (bukan keputusan):** (a) sampai kebutuhan acara khusus muncul; hindari kompleksitas lock/kuota tambahan.
- **Task terdampak:** kebijakan libur (bukan task board terpisah).

## #8 — Aturan dine-in

- **Masalah:** `create_offline_order` (`app/offline-orders.php:15-19`) mewajibkan alamat, belum ada tipe pesanan, dan belum ada jam kedatangan. Tidak ada kolom `tipe_pesanan`.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum TASK-09.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| Minimum porsi tetap berlaku | Tidak ada | `daily_quota_validate` dipakai apa adanya | Form dine-in menampilkan min porsi | Konsisten dengan delivery |
| Minimum porsi dikecualikan | Tidak ada | Perlu cabang validasi (risiko menyalahi aturan kuota) | Label berbeda | Perlu batas lain agar dapur tidak kelebihan |
| Jam kedatangan wajib | Perlu kolom `waktu_kirim`/`waktu_datang` bila belum cukup | Validasi jam | Input waktu di form |Perlu SLA dapur |
| Alamat dihilangkan | Tidak ada | `create_offline_order` jangan menolak alamat kosong untuk dine-in | Form menyembunyikan alamat | Rekap produksi tetap jalan |

- **Rekomendasi (bukan keputusan):** tipe dine-in dengan alamat opsional, minimum tetap berlaku, jam kedatangan opsional—namun ini **harus** diputuskan pemilik karena memengaruhi kuota dan operasi dapur.
- **Task terdampak:** TASK-09 (BLOCKED).

## #9 — Identitas pesanan manual Admin

- **Masalah:** `create_offline_order` menyimpan `nama_pemesan`, `telepon_penerima`, `alamat_pengiriman`, tetapi **tidak** mengisi `user_id`. Pesanan offline tidak terhubung ke akun.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum TASK-09.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Wajib akun pelanggan | Tidak ada kolom baru | Validasi `user_id` ada; relasi `users` | Form perlu pencarian pelanggan | Pelanggan tanpa akun tidak bisa dilayani manual |
| (b) Nama/nomor tanpa akun (status quo) | Tidak ada | Pertahankan; dokumentasikan | Form tetap bebas | Cocok untuk pesanan mendadak/telepon |

- **Rekomendasi (bukan keputusan):** (b) dengan pencatatan nama/nomor yang jelas, karena fitur ini memang untuk pesanan mendadak. Perlu konfirmasi tertulis.
- **Task terdampak:** TASK-09 (BLOCKED).

## #10 — Jam pesanan H-7 untuk Pelanggan

- **Masalah:** Pelanggan minimal 7 hari (`public/pelanggan/api/orders.php:202-205`), Admin bisa hari ini (`app/offline-orders.php:29-34`). Keduanya punya field `waktu_kirim`, tetapi UI Pelanggan tidak menawarkan pilihan jam yang sama dengan Admin.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum TASK-09.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Pelanggan boleh pilih jam | Tidak ada | Pertahankan validasi jam; pastikan batas kapasitas dapur | Date/time picker Pelanggan | Perlu aturan kapasitas per jam (belum ada) |
| (b) Pelanggan tidak memilih jam | Tidak ada | Pakai default `12:00` seperti sekarang | Sembunyikan input jam | Paling aman; Admin tetap bisa atur jam manual |
| (c) Pilih jam dengan kuota per jam | Perlu tabel kapasitas per jam | Perlu agregasi & lock baru | UI dengan slot jam | Kompleks; migration + test |

- **Rekomendasi (bukan keputusan):** (b) untuk saat ini, karena kapasitas per jam belum ada di data model; (c) baru jika pemilik memang ingin slot jam.
- **Task terdampak:** TASK-09 (BLOCKED).

## #11 — Definisi kuota dapur

- **Masalah:** `daily_quota_snapshot` menghitung `maxKitchen = 80% × total kuota` dan `kitchenUsed = total pemakaian produk`. Tidak jelas apakah ini batas produksi terpisah atau sekadar turunan kuota penjualan.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum TASK-10.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Turunan kuota penjualan | Tidak ada | Pertahankan rumus 80% & validasi `daily_quota_validate` | Label “Kapasitas dapur” sudah ada | Sederhana, tapi angka 80% perlu justifikasi |
| (b) Batas produksi terpisah | Migration tabel kapasitas dapur per tanggal | Rumus & filter tanggal baru; lock sama | Input kapasitas di Admin | Perluowner dapur menetapkan angka harian |

- **Rekomendasi (bukan keputusan):** (a) dengan dokumentasi bahwa 80% turunan kuota penjualan, sampai dapur meminta angka mandiri. Hindari mengubah rumus tanpa keputusan (TASK-10 juga melarang).
- **Task terdampak:** TASK-10 (BLOCKED).

## #12 — Istilah/status final pembayaran

- **Masalah:** 6 nilai status di DB (`menunggu_pembayaran`, `menunggu_validasi`, `diproses`, `selesai`, `ditolak`, `dibatalkan`). Label UI sudah diseragamkan menjadi “Menunggu verifikasi” (TASK-11), tetapi pemilik belum menetapkan istilah final.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum GO (karena menyentuh TASK-15/20).

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Pertahankan nilai DB, rapikan label UI | Tidak ada | Tidak ada perubahan API | Lanjutkan label “Menunggu verifikasi” | Risiko rendah |
| (b) Ganti nilai status DB | Migration + backfill + kompatibilitas API/laporan | Ubah semua query/transisi | Ubah label & filter | **Berisiko**: riwayat & laporan bisa tumpang tindih |

- **Rekomendasi (bukan keputusan):** (a) dengan daftar istilah final tertulis dari pemilik. Mengubah enum DB tanpa migration & uji kompatibilitas dilarang oleh task board.
- **Task terdampak:** TASK-04, 11, 15, 20.

## #13 — Metrik Owner

- **Masalah:** Dashboard Owner menampilkan `Rata-rata Pesanan` (dari `d.tiket = omzet / jumlah pesanan selesai`), `KUANTITAS PAX` / `TOTAL TERJUAL` (dari `SUM(detail_pesanan.qty)`), dan label “Botol minuman” untuk kategori minuman. `STATUS LUNAS 100% Lunas` di `public/owner/dashboard.php:19` adalah **teks statis**, bukan agregasi.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum TASK-20 selesai.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Pertahankan + beri definisi | Tidak ada | Dokumentasikan `tiket` = nilai rata-rata pesanan (Rp), `items` = porsi terjual | Ubah label jadi “Rata-rata nilai pesanan” & “Porsi terjual”; hilangkan “Botol minuman” bila satuan bukan botol | Metrik bisa dipakai |
| (b) Hapus metrik yang tidak bermakna | Tidak ada | Hentikan query yang tidak dipakai (jika ada) | Hapus kartu/teks “100% Lunas” yang statis | Dashboard lebih jujur |

- **Rekomendasi (bukan keputusan):** (a) untuk `tiket` (memang rata-rata nilai pesanan) dan hapus/koreksi label “Botol minuman” serta teks “100% Lunas” yang tidak bersumber data. Perlu konfirmasi pemilik.
- **Task terdampak:** TASK-20 (PARTIAL — bagian metrik).

## #14 — Branding final

- **Masalah:** `CUS-24` meminta `se.noesa` lowercase; `CUS-25` meminta audit warna/logo/background. Aset resmi belum tersedia di repo.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum TASK-19.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Aset final disediakan pemilik | Tidak ada | Tidak ada | Terapkan token warna/logo resmi, teks `se.noesa` | Perlu file aset (SVG/PNG) & panduan penggunaan |
| (b) Tetap pakai aset existing | Tidak ada | Tidak ada | Koreksi teks lowercase saja; jangan ganti logo/warna | Risiko visual rendah, tapi revisi CUS-25 belum terpenuhi |

- **Rekomendasi (bukan keputusan):** (b) sementara + minta aset resmi untuk (a). Agent tidak boleh menggambar/mengganti logo tanpa aset.
- **Task terdampak:** TASK-19 (BLOCKED).

## DECISION-NEW-01 — Nonaktif produk & snapshot histori

- **Masalah:** Tabel `produk` **tidak punya kolom `aktif`**; `detail_pesanan` **tidak punya snapshot nama** (verifikasi `information_schema` saat implementasi). FK mencegah penghapusan produk yang dipesan, tetapi detail pesanan menampilkan `produk.nama` **saat ini**, sehingga rename produk mengubah histori.
- **Status:** PENDING · **Wajib dijawab pemilik: YA** · Prioritas: sebelum TASK-13.

| Opsi | Database | Backend | Frontend | Operasional |
|---|---|---|---|---|
| (a) Tambah `aktif` + snapshot nama | Migration `ALTER TABLE produk ADD aktif`, `ALTER TABLE detail_pesanan ADD nama_snapshot`, backfill dari `produk.nama` | Semua INSERT detail menulis snapshot; query detail memakai `COALESCE(snapshot, nama)`; toggle nonaktif Admin | Keterangan “produk dinonaktifkan” pada detail; Admin bisa menonaktifkan | Migration **harus** diuji di DB lokal/staging dulu; dilarang ke produksi |
| (b) Keterangan dari data yang ada | Tidak ada | Tetap `JOIN produk`; tidak ada informasi nonaktif | Tidak bisa menampilkan keterangan andal | Tidak menyelesaikan ADM-10 |
| (c) Tidak diimplementasikan | Tidak ada | Tidak ada | Tidak ada | ADM-10 tetap NOT IMPLEMENTED |

- **Rekomendasi (bukan keputusan):** (a) hanya jika pemilik memang membutuhkan fitur nonaktif; migration harus dibuat terpisah dan diuji di DB lokal. Jika tidak, (c) dengan mencatat ADM-10 sebagai tidak dikerjakan.
- **Task terdampak:** TASK-13 (BLOCKED).

---

## Prosedur penetapan (tidak dijalankan agent)

1. Pemilik menulis jawaban langsung di kolom **Keputusan final** pada `docs/05-keputusan-bisnis/KEPUTUSAN-BISNIS-se.noesa.md` dan mengubah status menjadi `APPROVED`/`REJECTED`.
2. Agent tidak mengisi, tidak menebak, dan tidak mengubah status tanpa tulisan pemilik.
3. Setiap jawaban memicu penyesuaian task board: task yang terkunci dievaluasi ulang di `docs/06-rilis/REVIEW-BLOCKED-TASKS-se.noesa.md`.
