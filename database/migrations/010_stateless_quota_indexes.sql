-- Indeks agregasi kuota. Gunakan nama kolom pada skema aplikasi saat ini.
-- Kuota mengikuti tanggal pengiriman (Asia/Jakarta).
CREATE INDEX IF NOT EXISTS idx_pesanan_created_status
    ON pesanan (created_at, status);

CREATE INDEX IF NOT EXISTS idx_detail_pesanan_produk
    ON detail_pesanan (produk_id, pesanan_id);

CREATE INDEX IF NOT EXISTS idx_pesanan_delivery_status
    ON pesanan (tgl_kirim, status);
