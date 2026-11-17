# Se.Noesa — Master Prompt Eksekusi Semua Task dengan Checkpoint Git

> Prompt orkestrasi untuk coding agent. Tujuan: mengerjakan seluruh task yang memungkinkan secara berurutan, mencatat progres secara persisten di Markdown, dan membuat checkpoint Git agar perubahan dapat dipulihkan jika muncul bug/regresi.

## 1. Dokumen yang wajib dibaca

Sebelum melakukan apa pun, baca:
- `AGENTS.md`
- `PRD.md` dan `DESIGN.md` jika tersedia
- `docs/02-revisi-tugas/REVISI-LANJUTAN-SE.NOESA.md`
- `docs/03-audit/AUDIT-REVISI-TAHAP-2.md` (termasuk koreksi audit jika sudah dibuat)
- `docs/02-revisi-tugas/TASKS-IMPLEMENTASI-SE.NOESA.md`
- Dokumen keputusan bisnis yang sudah disetujui, jika tersedia

Jangan mengarang isi dokumen yang tidak ditemukan. Catat dokumen yang hilang dalam laporan.

## 2. Tujuan eksekusi

Kerjakan semua task dalam `docs/02-revisi-tugas/TASKS-IMPLEMENTASI-SE.NOESA.md` secara bertahap dan berurutan berdasarkan dependency serta prioritasnya.

**Tidak perlu menunggu instruksi user untuk berpindah ke task berikutnya** jika:
- task sebelumnya sudah selesai dan diverifikasi;
- dependency terpenuhi;
- task berikutnya tidak membutuhkan keputusan bisnis yang belum disetujui.

Jika task berstatus BLOCKED karena keputusan bisnis, jangan menebak. Catat pertanyaan/opsi yang diperlukan, tandai BLOCKED, lalu lanjutkan ke task independen berikutnya. Kembali ke task BLOCKED hanya setelah keputusan tertulis tersedia.

Jangan berhenti setelah satu task selesai kecuali ada blocker yang benar-benar menghentikan seluruh pekerjaan, risiko kehilangan data, konflik Git, kegagalan test kritis, atau tindakan yang memerlukan persetujuan manusia.

## 3. File progres wajib

Buat dan perbarui file berikut di root repository:

### `docs/04-implementasi/PROGRESS-IMPLEMENTASI-SE.NOESA.md`

File ini adalah sumber kebenaran progres lintas sesi. Update file:
- sebelum mulai task;
- setelah audit awal task;
- setelah implementasi;
- setelah test;
- setelah commit;
- saat task menjadi BLOCKED/FAILED.

Format minimum:

```md
# Progress Implementasi Se.Noesa

- Last updated: YYYY-MM-DD HH:mm WIB
- Current branch:
- Baseline commit:
- Current HEAD:
- Overall status: IN_PROGRESS / BLOCKED / COMPLETED

## Ringkasan
| Status | Jumlah |
|---|---:|
| DONE | 0 |
| PARTIAL | 0 |
| BLOCKED | 0 |
| TODO | 0 |
| FAILED | 0 |

## Riwayat Task
| Task ID | Status | Commit | Test | Catatan |
|---|---|---|---|---|

## Task aktif
- ID:
- Tujuan:
- File yang akan disentuh:
- Risiko:
- Dependency:

## Keputusan bisnis tertunda
| Decision ID | Pertanyaan | Task terdampak | Status |
|---|---|---|---|

## Bug/regresi yang ditemukan
| ID | Gejala | Commit terkait | Status | Tindakan |
|---|---|---|---|---|

## Riwayat checkpoint
| Waktu | Commit | Task | Hasil test | Catatan |
|---|---|---|---|---|
```

Jangan menghapus riwayat lama. Tambahkan entri baru dan perbarui status secara konsisten.

Buat juga:
- `docs/05-keputusan-bisnis/KEPUTUSAN-BISNIS-SE.NOESA.md` untuk 14 keputusan yang perlu persetujuan pemilik/mitra.
- `docs/04-implementasi/CHANGELOG-IMPLEMENTASI-SE.NOESA.md` untuk ringkasan perubahan per task.
- `docs/04-implementasi/TEST-REPORT-SE.NOESA.md` untuk hasil test terperinci.

Jika file tersebut sudah ada, baca dan lanjutkan; jangan menimpa riwayat yang sudah ada.

## 4. Kebijakan Git dan checkpoint

### 4.1 Persiapan aman

1. Jalankan `git status --short --branch`, `git branch --show-current`, dan `git log -5 --oneline`.
2. Periksa remote dan branch aktif, tetapi jangan mengubah remote.
3. Pastikan working tree bersih sebelum membuat baseline.
4. Jika ada perubahan user yang belum di-commit, **jangan stash, reset, checkout paksa, atau commit perubahan itu atas nama agent**. Catat dan minta user menyelesaikan/menentukan tindakan.
5. Jangan pernah menghapus file atau data user untuk membuat working tree bersih.
6. Pastikan branch kerja bukan branch production/protected seperti `main` atau `master`. Jika sedang di branch tersebut, buat branch kerja baru dari HEAD, misalnya `work/implementation-tasks-YYYYMMDD`. Jangan mengubah branch remote.
7. Buat baseline commit hanya jika working tree bersih dan perubahan baseline memang milik repository yang sudah disetujui untuk di-commit. Jika tidak, berhenti dan minta arahan.
8. Jangan push ke remote kecuali user secara eksplisit meminta.

### 4.2 Satu task = satu checkpoint commit

Untuk setiap task yang menghasilkan perubahan kode/dokumen:
1. Catat HEAD awal dan status Git.
2. Implementasikan hanya scope task tersebut.
3. Jalankan test yang relevan.
4. Periksa `git diff --check`, `git diff`, dan `git status`.
5. Pastikan tidak ada secret, `.env`, credential, file upload pelanggan, dump database, atau data pribadi masuk ke commit.
6. Stage file secara eksplisit satu per satu; hindari `git add .` dan `git add -A`.
7. Commit task dengan pesan jelas, misalnya:
   - `task(TASK-02): verify owner order synchronization`
   - `task(TASK-06): harden product image upload`
   - `docs: update Se.Noesa implementation progress`
8. Catat hash commit, hasil test, dan file terkait di `docs/04-implementasi/PROGRESS-IMPLEMENTASI-SE.NOESA.md` serta `docs/04-implementasi/CHANGELOG-IMPLEMENTASI-SE.NOESA.md`.
9. Buat commit dokumentasi progres setelah commit task, atau sertakan update progress dalam commit task yang sama. Pastikan setiap checkpoint tetap memiliki catatan status dan hash commit.
10. Lanjutkan ke task berikutnya hanya setelah checkpoint berhasil dibuat.

Task audit tanpa perubahan kode boleh menghasilkan commit dokumentasi tersendiri bila repository bersih dan aturan di atas terpenuhi.

### 4.3 Jika test gagal atau bug muncul

Jangan langsung menjalankan `git reset --hard`, `git clean`, atau menghapus perubahan.

Lakukan:
1. Hentikan task aktif.
2. Simpan log test dan gejala ke `docs/04-implementasi/TEST-REPORT-SE.NOESA.md` dan `docs/04-implementasi/PROGRESS-IMPLEMENTASI-SE.NOESA.md`.
3. Identifikasi apakah bug berasal dari perubahan task aktif atau sudah ada sebelumnya.
4. Jika perubahan task aktif belum di-commit: perbaiki dalam scope task yang sama. Jika tidak dapat diperbaiki dengan aman, batalkan hanya perubahan task aktif secara selektif dan verifikasi diff; jangan sentuh perubahan user atau task sebelumnya.
5. Jika bug ditemukan setelah commit: jangan rewrite history. Gunakan `git revert <commit-hash>` untuk membatalkan checkpoint yang terbukti menyebabkan regresi, setelah memastikan commit tersebut hanya berisi perubahan task terkait. Jalankan test lagi dan buat commit revert.
6. Jika commit mencakup lebih dari satu task atau revert berisiko konflik/data, berhenti dan minta persetujuan.
7. Catat hash commit penyebab dan hash revert di progress.
8. Jangan menganggap rollback Git mengembalikan perubahan database atau file upload. Untuk database, gunakan migration rollback yang sudah dirancang dan aman; jika tidak tersedia, jangan rollback database otomatis. Ambil backup dan minta persetujuan sebelum tindakan destruktif.

**Penting:** Git commit adalah checkpoint source code, bukan backup database atau storage. Jangan menjalankan migration destruktif, menghapus tabel, menghapus data produksi, atau menghapus file upload sebagai mekanisme rollback.

## 5. Aturan implementasi dan keamanan

- Pertahankan stack dan arsitektur yang digunakan repository.
- Jangan rewrite aplikasi atau melakukan refactor luas di luar task.
- Jangan mengubah kontrak API tanpa memeriksa semua pemanggil.
- Validasi authorization di backend; jangan hanya menyembunyikan tombol.
- Jaga transaksi database, konsistensi kuota, status pembayaran, dan histori pesanan.
- Migration harus mengikuti migration runner proyek, aman untuk data lama, dan memiliki strategi rollback/forward-fix yang jelas.
- Jangan menjalankan migration ke production atau remote database tanpa persetujuan eksplisit.
- Jangan mengubah `.env`, secret, kredensial, atau konfigurasi production.
- Jangan menambahkan dependency baru kecuali benar-benar dibutuhkan dan alasannya dicatat.
- Jangan menyatakan task DONE hanya karena PHP lint atau JavaScript syntax check lolos.
- Gunakan test fungsional, API/database test, dan browser/manual test yang sesuai.
- Jika layanan eksternal atau database test tidak tersedia, tandai test yang belum dijalankan. Jangan mengklaim lulus.
- Jangan menggunakan data pelanggan nyata untuk test; gunakan fixture/test data.

## 6. Keputusan bisnis

Ada 14 keputusan bisnis yang disebutkan pada task board. Agent wajib:
1. Menyalin pertanyaan keputusan ke `docs/05-keputusan-bisnis/KEPUTUSAN-BISNIS-SE.NOESA.md`.
2. Menandai setiap keputusan `PENDING`, `APPROVED`, atau `REJECTED`.
3. Tidak memilih jawaban untuk pemilik/mitra.
4. Menandai task terkait `BLOCKED` jika keputusan tersebut memengaruhi perilaku bisnis.
5. Tetap mengerjakan task independen yang tidak membutuhkan keputusan itu.

Task tidak boleh dianggap selesai jika implementasinya bergantung pada keputusan yang belum disetujui.

## 7. Alur kerja otomatis

Lakukan loop berikut sampai seluruh task yang dapat dikerjakan selesai atau semua task tersisa terblokir:

### Untuk setiap task
1. Baca task dan dependency.
2. Periksa status progres dan Git.
3. Jika dependency belum terpenuhi: tandai BLOCKED, jelaskan dependency, lanjutkan ke task independen.
4. Jika butuh keputusan bisnis yang belum disetujui: catat keputusan, tandai BLOCKED, lanjutkan.
5. Audit kode terkait dan tulis rencana singkat ke progress.
6. Implementasikan scope task.
7. Jalankan test acceptance criteria.
8. Review diff, keamanan, migration, dan regresi.
9. Jika lolos: commit checkpoint task.
10. Update progress, changelog, dan test report dengan hash commit.
11. Lanjutkan ke task eligible berikutnya.

### Prioritas
Ikuti urutan dependency dalam `docs/02-revisi-tugas/TASKS-IMPLEMENTASI-SE.NOESA.md`, dengan prioritas:
1. Rekonsiliasi audit.
2. Keputusan bisnis (dokumentasikan, tunggu persetujuan).
3. P0 integritas data/transaksi.
4. P1 operasional Admin.
5. P2 UX Pelanggan/Owner.
6. P3 UI Admin, regression, release readiness.

Jangan mengerjakan task P2/P3 yang bergantung pada P0 yang belum stabil.

## 8. Kriteria status task

- `DONE`: implementasi lengkap, acceptance criteria diuji dan lulus, checkpoint commit tercatat.
- `PARTIAL`: ada implementasi tetapi sebagian acceptance criteria belum terpenuhi/teruji.
- `BLOCKED`: menunggu keputusan, dependency, akses, atau persetujuan.
- `FAILED`: percobaan implementasi/test gagal dan belum terselesaikan.
- `TODO`: belum dimulai.

Jangan mengubah PARTIAL menjadi DONE tanpa menjalankan test yang belum terpenuhi.

## 9. Kondisi berhenti

Hentikan eksekusi dan laporkan ke user jika:
- Ditemukan perubahan user yang belum di-commit sebelum memulai.
- Branch/repository tidak aman untuk checkpoint.
- Ada indikasi data hilang/korup atau kebocoran secret.
- Test kritis transaksi/pembayaran/kuota gagal dan tidak bisa diperbaiki dengan aman.
- Migration berisiko mengubah/menghapus data yang ada.
- Keputusan bisnis diperlukan untuk semua task tersisa.
- Konflik Git atau kegagalan commit tidak dapat diselesaikan tanpa tindakan destruktif.

Jika hanya sebagian task terblokir, lanjutkan task independen yang aman.

## 10. Laporan akhir

Saat tidak ada lagi task eligible:
1. Perbarui `docs/04-implementasi/PROGRESS-IMPLEMENTASI-SE.NOESA.md`.
2. Buat ringkasan task DONE/PARTIAL/BLOCKED/FAILED/TODO.
3. Cantumkan seluruh checkpoint commit dan cara revert tiap checkpoint.
4. Cantumkan keputusan bisnis yang masih menunggu persetujuan.
5. Cantumkan test yang lulus, gagal, dan belum dijalankan.
6. Buat/isi `docs/06-rilis/RELEASE-READINESS-SE.NOESA.md` dengan keputusan GO atau NO-GO.
7. Jangan push, deploy, atau mengubah production.
8. Berikan laporan akhir dan berhenti.

---

# PROMPT EKSEKUSI

Salin seluruh instruksi dokumen ini kepada coding agent, lalu gunakan instruksi mulai berikut:

```text
Mulai eksekusi seluruh task pada docs/02-revisi-tugas/TASKS-IMPLEMENTASI-SE.NOESA.md sesuai MASTER PROMPT di docs/02-revisi-tugas/PROMPT-EKSEKUSI-SE.NOESA.md.

Jalankan task secara otomatis dan berurutan berdasarkan dependency. Jangan berhenti setelah satu task selesai; lanjutkan ke task berikutnya yang eligible.

Simpan seluruh progres secara persisten di docs/04-implementasi/PROGRESS-IMPLEMENTASI-SE.NOESA.md, catat perubahan di docs/04-implementasi/CHANGELOG-IMPLEMENTASI-SE.NOESA.md, dan catat hasil test di docs/04-implementasi/TEST-REPORT-SE.NOESA.md.

Buat satu Git checkpoint commit untuk setiap task yang berhasil dan sudah diuji. Jangan push ke remote. Jika muncul regresi setelah commit, gunakan git revert pada commit task yang menyebabkan regresi, bukan reset --hard. Jangan pernah menganggap Git rollback membatalkan migration database atau file upload.

Untuk 14 keputusan bisnis yang belum disetujui, dokumentasikan pertanyaannya dan tandai task terkait BLOCKED. Jangan menebak keputusan. Tetap lanjutkan task lain yang independen.

Sebelum mulai, periksa working tree dan branch. Jika ada perubahan user yang belum di-commit atau kondisi Git tidak aman, berhenti dan laporkan. Jangan menghapus atau menimpa pekerjaan user.

Mulai sekarang. Kerjakan hanya dalam repository lokal. Jangan push, deploy, atau menyentuh production. Berhenti hanya ketika semua task yang eligible sudah selesai, task tersisa terblokir, atau terjadi kondisi berhenti yang ditentukan dokumen.
```
