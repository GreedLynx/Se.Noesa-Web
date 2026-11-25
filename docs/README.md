# Indeks Dokumentasi se.noesa

Seluruh dokumentasi proyek dikelompokkan ke tujuh kategori berdasarkan perannya. `README.md` di root tetap dipakai sebagai halaman utama repositori; dokumentasi dependency pihak ketiga tetap berada di `vendor/`.

| Kategori | Peran | Isi |
|---|---|---|
| [`00-panduan/`](00-panduan/) | Panduan masuk & konvensi kerja | `HANDOFF.md`, `gaya-kode.md`, `peta-kode.md` |
| [`01-spesifikasi/`](01-spesifikasi/) | Spesifikasi teknis aplikasi | `api.md`, `database.md`, `aturan-bisnis.md` |
| [`02-revisi-tugas/`](02-revisi-tugas/) | Sumber revisi, prompt, dan task board | `REVISI-LANJUTAN-se.noesa.md`, `revisi-senoesa.md`, `TASKS-IMPLEMENTASI-se.noesa.md`, `PROMPT-EKSEKUSI-se.noesa.md`, `rencana-kuota-default-override.md`, `rencana-pembatalan-pesanan.md`, `rencana-visibilitas-pembatalan-admin.md` |
| [`03-audit/`](03-audit/) | Hasil audit & verifikasi | `audit-2026-11-13.md`, `AUDIT-REVISI-TAHAP-2.md`, `AUDIT-REVISI-TAHAP-2-CORRECTION.md`, `AUDIT-CONCURRENCY-KUOTA.md`, `ringkasan-sesi-2026-11-06.md`, `verifikasi-notifikasi-menu-2026-11-16.md` |
| [`04-implementasi/`](04-implementasi/) | Catatan eksekusi & bukti test | `PROGRESS-IMPLEMENTASI-se.noesa.md`, `CHANGELOG-IMPLEMENTASI-se.noesa.md`, `TEST-REPORT-se.noesa.md`, `TEST-MATRIX-PEMBAYARAN.md`, `katalog-ikon.md`, `laporan-uji-ikon-2026-11-24.md` |
| [`05-keputusan-bisnis/`](05-keputusan-bisnis/) | Register & review keputusan pemilik | `KEPUTUSAN-BISNIS-se.noesa.md`, `REVIEW-KEPUTUSAN-BISNIS-se.noesa.md` |
| [`06-rilis/`](06-rilis/) | Review task, rencana test, kesiapan rilis | `REVIEW-BLOCKED-TASKS-se.noesa.md`, `TEST-PLAN-RELEASE-se.noesa.md`, `RELEASE-READINESS-se.noesa.md` |

## Urutan baca yang disarankan

1. `README.md` (root) — gambaran umum & cara menjalankan.
2. `00-panduan/` — konvensi kode dan peta file.
3. `01-spesifikasi/` — kontrak API, skema DB, aturan bisnis.
4. `02-revisi-tugas/` — asal Requirements dan task board.
5. `03-audit/` — temuan dan status revisi.
6. `04-implementasi/` — apa yang sudah dikerjakan dan dibuktikan.
7. `05-keputusan-bisnis/` — keputusan yang masih menunggu pemilik.
8. `06-rilis/` — review blocker, rencana pengujian, dan keputusan GO/NO-GO.

## Catatan

- `AGENTS.md`, `PRD.md`, dan `DESIGN.md` tidak tersedia di repository saat indeks ini dibuat.
- FileMarkdown di `vendor/` adalah dokumentasi dependency dan tidak dipindahkan.
