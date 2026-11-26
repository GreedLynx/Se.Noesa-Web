# Rencana revisi: tahap pembayaran pada detail riwayat pelanggan

## Primary goal

Setelah pelanggan mengunggah bukti bayar, bar perkembangan di pop up detail riwayat tetap menunjukkan **Pembayaran** sebagai tahap terakhir yang aktif. **Verifikasi** baru ditandai selesai setelah Admin menyetujui pembayaran.

## Kondisi saat ini dan kriteria penerimaan

- `statusProgress()` di `public/pelanggan/assets/customer.js` menandai tiga langkah pertama `.done` ketika status pesanan `menunggu_validasi`. Akibatnya, Verifikasi terlihat selesai segera setelah unggah bukti.
- Upload memang mengubah status pesanan dari `menunggu_pembayaran` menjadi `menunggu_validasi` di `public/pelanggan/api/orders.php`. Status ini berarti **menunggu** pemeriksaan Admin; `validate` di `public/admin/api/orders.php` baru mengubahnya menjadi `diproses`.
- Saat `menunggu_pembayaran`: hanya Pesanan yang sudah ditandai, sesuai perilaku sekarang. Saat `menunggu_validasi`: Pesanan dan Pembayaran ditandai; Verifikasi, Diproses, dan Selesai belum ditandai. Saat `diproses`: Verifikasi dan Diproses sudah ditandai, sesuai perilaku sekarang. Saat `selesai`: seluruh langkah ditandai. Penolakan/pembatalan tetap memakai tampilan terminal yang sudah ada.
- Badge teks `Menunggu verifikasi` tetap menunjukkan status operasional yang benar; revisi ini hanya mengoreksi bar perkembangan agar tidak mengklaim verifikasi sudah selesai. Perilaku yang sama harus terlihat setelah detail dimuat ulang, termasuk refresh otomatis sesudah unggah bukti.

## Phase 0 — Konfirmasi kontrak dan pola yang tersedia

**How this serves the primary goal:** Menetapkan arti setiap status sebelum mengubah representasi visualnya.

- Baca `docs/00-panduan/peta-kode.md` bagian riwayat/pembayaran, `docs/01-spesifikasi/aturan-bisnis.md` bagian batas pembayaran, `docs/01-spesifikasi/api.md` bagian upload bukti, dan `docs/01-spesifikasi/database.md` bagian status pesanan. Periksa pula `.opencode/revisi.md` bila tersedia; file itu tidak ada pada pemeriksaan rencana ini.
- Alur yang sudah tersedia: `statusProgress(status)` dipanggil dari `openHistoryDetail(id)` dalam `public/pelanggan/assets/customer.js`; GET detail `public/pelanggan/api/orders.php?id=...` mengirim `status`; POST `action=upload_proof` mengganti `menunggu_pembayaran` menjadi `menunggu_validasi`; Admin `action=validate` mengganti `menunggu_validasi` menjadi `diproses`.
- API yang dipakai untuk revisi hanya status yang sudah ada. Tidak ada status khusus `terverifikasi` pada pesanan; jangan menciptakan status, endpoint, atau kolom baru. Pertahankan validasi upload, tenggat, dan keputusan Admin di server.

## Phase 1 — Koreksi pemetaan bar perkembangan

**How this serves the primary goal:** Menjaga Pembayaran sebagai tahap yang ditandai selama bukti masih menunggu verifikasi Admin.

- Di `public/pelanggan/assets/customer.js`, ubah pemetaan `menunggu_validasi` pada `statusProgress()` dari tiga langkah `.done` menjadi dua langkah `.done`. Gunakan struktur `steps` dan kelas `.done` yang sudah ada; `public/pelanggan/assets/customer.css` sudah menata kelas itu.
- Pertahankan pemetaan status lain, badge `statusLabels`, tab riwayat, dan proses refresh detail setelah upload. Tidak perlu perubahan API, skema PostgreSQL, atau alur pembayaran.
- Verifikasi fase: inspeksi output fungsi untuk enam status pesanan yang dikenal; pastikan `menunggu_validasi` tidak menandai Verifikasi dan `diproses` tetap menandainya. Hindari mengubah arti status pesanan di database sekadar untuk memperbaiki tampilan.

## Phase 2 — Uji regresi dan dokumentasi

**How this serves the primary goal:** Membuktikan bar tidak melompat ke Verifikasi setelah upload dan baru berubah setelah tindakan Admin.

- Tambah satu uji terarah untuk `statusProgress()` atau perluas fixture `scripts/test-customer-revisions-browser.cjs`. Uji urutan kelas `.done` pada `menunggu_pembayaran`, `menunggu_validasi`, `diproses`, `selesai`, serta tampilan terminal `ditolak`/`dibatalkan`. `scripts/test-customer-payment-errors.cjs` dan `scripts/test-order-cancellation-ui.cjs` saat ini men-stub fungsi ini, sehingga belum menguji bar asli.
- Uji alur browser dengan data uji: buka detail riwayat sebelum upload, unggah bukti, periksa badge `Menunggu verifikasi` dan bar berhenti di Pembayaran, muat ulang halaman, lalu validasi melalui Admin dan periksa Verifikasi baru ditandai. Uji desktop dan mobile bila sesi browser tersedia; jangan menggunakan data pesanan produksi untuk pengujian.
- Jalankan `node --check` dan uji relevan. Perbarui `docs/00-panduan/peta-kode.md`, `docs/01-spesifikasi/api.md`, `docs/01-spesifikasi/database.md`, serta `docs/00-panduan/HANDOFF.md` dalam perubahan implementasi sesuai AGENTS.md. Catat bahwa API/skema tidak berubah. Tandai revisi selesai hanya setelah hasil uji sesuai kriteria di atas.

## Final verification

**How this serves the primary goal:** Menutup revisi dengan bukti bahwa tampilan mengikuti verifikasi Admin yang sebenarnya.

- Bandingkan tampilan bar untuk seluruh status dengan aturan bisnis dan respons API; pastikan tidak ada tahap Verifikasi `.done` pada `menunggu_validasi`.
- Jalankan uji terarah, pemeriksaan sintaks, dan `git diff --check`; catat hasil dan batas uji browser di handoff. Tidak membaca atau mengubah `.env`.

Status: **diimplementasikan.** Pemetaan bar dan uji regresi Node lulus. Fixture browser progres diuji terpisah dengan `--payment --progress`; alur nyata dengan API/database dan login Admin masih perlu verifikasi.
