-- Pesanan yang belum dibayar dapat kedaluwarsa otomatis.
ALTER TABLE pesanan
  DROP CONSTRAINT IF EXISTS pesanan_status_check,
  ADD CONSTRAINT pesanan_status_check CHECK (
    status IN ('menunggu_pembayaran', 'menunggu_validasi', 'diproses', 'selesai', 'ditolak', 'dibatalkan')
  );

CREATE INDEX IF NOT EXISTS pesanan_unpaid_expiration_idx
  ON pesanan(created_at)
  WHERE status = 'menunggu_pembayaran';

-- Pesanan ditolak atau dibatalkan tidak lagi memakai kuota harian.
CREATE OR REPLACE VIEW v_kuota_harian AS
WITH pemakaian AS (
    SELECT d.produk_id, (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date tanggal,
           SUM(d.qty)::bigint terpakai
    FROM pesanan p JOIN detail_pesanan d ON d.pesanan_id = p.id
    WHERE p.status NOT IN ('ditolak', 'dibatalkan') AND p.tgl_kirim IS NOT NULL
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
