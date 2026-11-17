# Progress Implementasi Se.Noesa

- Last updated: 2026-11-17 21:45 WIB
- Current branch: feature/full-stack
- Baseline commit: f7ff4d37a0b99a58c929733ae9964445d56f5183
- Current HEAD: (checkpoint TASK-05)
- Overall status: IN_PROGRESS

## Ringkasan
| Status | Jumlah |
|---|---:|
| DONE | 5 |
| PARTIAL | 0 |
| BLOCKED | 7 |
| TODO | 12 |
| FAILED | 0 |

## Riwayat Task
| Task ID | Status | Commit | Test | Catatan |
|---|---|---|---|---|
| TASK-00 | DONE | 9b7f8f5 | lolos | 89 ID unik; ringkasan dikoreksi 38/18/22 → 47/22/20; tanpa perubahan kode |
| TASK-01 | DONE | 6be9681 | lolos | 14 keputusan PENDING, tidak ada asumsi agent |
| TASK-02 | DONE | 2ba8f69 | lolos | Output Owner=Admin identik 11 skenario; kontrol mutasi Owner dihapus |
| TASK-03 | BLOCKED | - | - | Menunggu keputusan #3, #4 |
| TASK-04 | DONE | 2bf0153 | lolos (14 skenario terpetakan) | Audit + test matrix; tanpa perubahan status/timeout; BUG-001 dicatat |
| TASK-05 | DONE | (checkpoint ini) | lolos (5/5 unit skenario) | Cart clamp ke minimum; pesan error Admin diperjelas |
| TASK-06 | TODO | - | - | Upload foto produk |
| TASK-07 | TODO | - | - | Concurrency kuota (dep TASK-02, 05) |
| TASK-08 | BLOCKED | - | - | Menunggu keputusan #6 |
| TASK-09 | BLOCKED | - | - | Menunggu keputusan #8, #9, #10 |
| TASK-10 | BLOCKED | - | - | Menunggu keputusan #11 |
| TASK-11 | TODO | - | - | Status/filter Admin |
| TASK-12 | TODO | - | - | Auto-refresh Admin |
| TASK-13 | TODO | - | - | Produk nonaktif di histori |
| TASK-14 | TODO | - | - | Kuota penuh Pelanggan (dep TASK-07) |
| TASK-15 | BLOCKED | - | - | Menunggu keputusan #5, #12 |
| TASK-16 | TODO | - | - | Dialog Pelanggan |
| TASK-17 | TODO | - | - | Label keranjang (dep TASK-05) |
| TASK-18 | TODO | - | - | Navigasi/dashboard Pelanggan |
| TASK-19 | BLOCKED | - | - | Menunggu keputusan #14 |
| TASK-20 | TODO | - | - | Dashboard Owner (sebagian dep #13) |
| TASK-21 | TODO | - | - | UI Admin |
| TASK-22 | TODO | - | - | Regression lintas role (dep semua task) |
| TASK-23 | TODO | - | - | Release readiness (dep TASK-22) |

## Task aktif
- ID: TASK-06
- Tujuan: Uji upload foto produk Admin (valid/invalid, ukuran, nama file, storage, tampil, rollback)
- File yang akan disentuh: public/admin/api/products.php, public/uploads/products (audit); file progres
- Risiko: Sedang — test mutasi/upload dilarang pada DB remote; gunakan analisis + test lokal yang tidak menyentuh storage produksi
- Dependency: TASK-00 (selesai)

## Keputusan bisnis tertunda
| Decision ID | Pertanyaan | Task terdampak | Status |
|---|---|---|---|
| #1 | Refund pembayaran terverifikasi | (kebijakan, TASK-04/06 area) | PENDING |
| #2 | Definisi H-2 48 jam vs kalender | (kebijakan pembatalan) | PENDING |
| #3 | Tindakan terhadap pesanan saat kuota 0 | TASK-03 | PENDING |
| #4 | Catatan perubahan kuota: internal atau pelanggan | TASK-03 | PENDING |
| #5 | Kapan pengingat bukti muncul/berhenti | TASK-15 | PENDING |
| #6 | Nomor WhatsApp & halaman tampil | TASK-08 | PENDING |
| #7 | Tanggal libur: semua produk atau ada pengecualian | (kebijakan libur) | PENDING |
| #8 | Aturan dine-in (min porsi, jam, alamat) | TASK-09 | PENDING |
| #9 | Pesanan manual wajib akun atau tidak | TASK-09 | PENDING |
| #10 | Jam pesanan H-7 untuk Pelanggan | TASK-09 | PENDING |
| #11 | Definisi kuota dapur | TASK-10 | PENDING |
| #12 | Istilah/status final pembayaran | TASK-04, 11, 15, 20 | PENDING |
| #13 | Definisi metrik Owner | TASK-20 | PENDING |
| #14 | Aset branding final | TASK-19 | PENDING |

## Bug/regresi yang ditemukan
| ID | Gejala | Commit terkait | Status | Tindakan |
|---|---|---|---|---|
| BUG-001 | `v_kuota_harian` hanya mengecualikan status `ditolak`, bukan `dibatalkan` ⇒ berpotensi menghitung pesanan batal sebagai pemakaian kuota. Belum ter-reproduksi (0 baris selisih pada data aktual). | 011_daily_quota_report.sql:9 vs app/daily-quota.php:44 | OPEN | Perlu migration `CREATE OR REPLACE VIEW` + uji pada data dengan pesanan batal; tidak dijalankan agent (dilarang mengubah DB). Perlu task baru. |
| OBS-001 | 6 pesanan `menunggu_pembayaran` tanpa bukti sudah lewat tenggat 1 jam. | - | OPEN | Operasional: penjadwal `scripts/expire-unpaid-orders.php` perlu dipastikan aktif. |
| OBS-002 | 4 pesanan `ditolak` tanpa `alasan_penolakan` (data historis). | - | OPEN | Tidak di-backfill tanpa keputusan. |
| OBS-003 | Teks “refund penuh ditangani Admin” di `customer.js:563` menyatakan kebijakan yang masih PENDING (#1). | - | OPEN | Tidak diubah; tunggu keputusan #1. |

## Riwayat checkpoint
| Waktu | Commit | Task | Hasil test | Catatan |
|---|---|---|---|---|
| 2026-11-17 21:25 | f7ff4d3 | baseline | git status clean | Organisasi docs ke folder docs/ (diizinkan user) |
| 2026-11-17 21:45 | 9b7f8f5 | TASK-00 | lolos (hitung ulang 89 ID) | Koreksi ringkasan audit; tanpa perubahan kode aplikasi |
| 2026-11-17 21:55 | 6be9681 | TASK-01 | lolos (14/14 keputusan) | Dokumentasi keputusan bisnis; semua PENDING |
| 2026-11-17 22:15 | 2ba8f69 | TASK-02 | lolos (11 skenario identik) | Owner read-only frontend diperketat; tanpa perubahan API |
| 2026-11-17 22:40 | 2bf0153 | TASK-04 | lolos (14 skenario, agregat DB) | Audit pembayaran; test matrix; BUG-001/OBS-001..003 dicatat |
| 2026-11-17 23:00 | (checkpoint ini) | TASK-05 | lolos (5/5 skenario cart) | Clamp minimum di keranjang; pesan error Admin spesifik |

## Catatan lingkungan / keterbatasan
- `AGENTS.md`, `PRD.md`, `DESIGN.md` **tidak ditemukan** di repository (dicatat sesuai instruksi §1).
- PHP 8.5.10 CLI, Node v24.21.0, PDO pgsql tersedia.
- `.env` mengarah ke **Supabase remote** (`aws-0-ap-southeast-1.pooler.supabase.com`) dan dapat dijangkau.
- Sesuai larangan "jangan menyentuh production", interaksi DB dibatasi **read-only (`SELECT`)**. Tidak ada migration, INSERT/UPDATE/DELETE, atau test yang mengubah data. Test yang memerlukan mutasi ditandai **belum dijalankan** dengan alasan ini.
- Tidak ada browser automation (Playwright/Cypress) yang terkonfigurasi; test UI dilakukan lewat analisis kode + `node --check` + PHP lint, dan dicatat sebagai terbatas.
