-- Konfigurasi kapasitas tetap di kuota_harian; pemakaian berasal dari transaksi.
CREATE OR REPLACE VIEW v_kuota_harian AS
WITH pemakaian AS (
    SELECT d.produk_id,(p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date tanggal,
           SUM(d.qty)::bigint terpakai
    FROM pesanan p JOIN detail_pesanan d ON d.pesanan_id=p.id
    WHERE p.status<>'ditolak' AND p.tgl_kirim IS NOT NULL
    GROUP BY d.produk_id,(p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date
)
SELECT COALESCE(k.produk_id,u.produk_id) produk_id,
       COALESCE(k.tanggal,u.tanggal) tanggal,
       COALESCE(k.kuota,0) kuota,
       COALESCE(u.terpakai,0::bigint) terpakai,
       GREATEST(0::bigint,COALESCE(k.kuota,0)-COALESCE(u.terpakai,0)) sisa
FROM kuota_harian k FULL JOIN pemakaian u
ON u.produk_id=k.produk_id AND u.tanggal=k.tanggal;
