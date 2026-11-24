-- Aturan yang disetujui: minimal 10 porsi per menu, maksimal 50 porsi per
-- pesanan, dan maksimal 50 paket per tanggal kirim.
-- Tidak ada tabel baru: kuota_harian tetap menjadi stok per menu per tanggal.

-- Samakan data lama dengan minimum baru sebelum constraint diperketat.
UPDATE produk
SET min_pesan = 10
WHERE min_pesan < 10;

ALTER TABLE produk
  ALTER COLUMN min_pesan SET DEFAULT 10,
  DROP CONSTRAINT IF EXISTS produk_min_pesan_check,
  ADD CONSTRAINT produk_min_pesan_check CHECK (min_pesan >= 10);

-- View laporan memakai aturan reservasi yang sama dengan checkout:
-- unpaid yang sudah lewat tenggat 1 jam tidak lagi menahan kuota.
CREATE OR REPLACE VIEW v_kuota_harian AS
WITH pemakaian AS (
    SELECT d.produk_id, (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date tanggal,
           SUM(d.qty)::bigint terpakai
    FROM pesanan p JOIN detail_pesanan d ON d.pesanan_id = p.id
    WHERE p.tgl_kirim IS NOT NULL
      AND (
          p.status IN ('menunggu_validasi', 'diproses', 'selesai')
          OR (
              p.status = 'menunggu_pembayaran'
              AND NULLIF(BTRIM(p.bukti_bayar), '') IS NULL
              AND p.created_at + interval '1 hour' > now()
          )
      )
    GROUP BY d.produk_id, (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date
)
SELECT COALESCE(k.produk_id, u.produk_id) produk_id,
       COALESCE(k.tanggal, u.tanggal) tanggal,
       COALESCE(k.kuota, pr.kuota_harian) kuota,
       COALESCE(u.terpakai, 0::bigint) terpakai,
       GREATEST(0::bigint, COALESCE(k.kuota, pr.kuota_harian) - COALESCE(u.terpakai, 0)) sisa
FROM kuota_harian k FULL JOIN pemakaian u
    ON u.produk_id = k.produk_id AND u.tanggal = k.tanggal
JOIN produk pr ON pr.id = COALESCE(k.produk_id, u.produk_id);
