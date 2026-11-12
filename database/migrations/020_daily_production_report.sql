CREATE OR REPLACE VIEW v_rekap_produksi_harian AS
SELECT (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date AS tanggal,
       d.produk_id,
       pr.nama AS menu,
       p.id AS pesanan_id,
       p.kode,
       p.status,
       SUM(d.qty)::bigint AS porsi
FROM pesanan p
JOIN detail_pesanan d ON d.pesanan_id = p.id
JOIN produk pr ON pr.id = d.produk_id
WHERE p.status NOT IN ('ditolak', 'dibatalkan')
  AND p.tgl_kirim IS NOT NULL
GROUP BY (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date,
         d.produk_id, pr.nama, p.id, p.kode, p.status;
