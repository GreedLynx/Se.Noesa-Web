-- Indeks agregasi kuota. Gunakan nama kolom pada skema aplikasi saat ini.
-- Kuota mengikuti tanggal pengiriman (Asia/Jakarta).
-- INDEX: optimalkan ringkasan pesanan menurut waktu pembuatan dan status.
CREATE INDEX IF NOT EXISTS idx_pesanan_created_status
    ON pesanan (created_at, status);

-- INDEX: percepat agregasi jumlah produk dari detail pesanan.
CREATE INDEX IF NOT EXISTS idx_detail_pesanan_produk
    ON detail_pesanan (produk_id, pesanan_id);

-- INDEX: optimalkan pencarian pemakaian kuota menurut waktu pengiriman dan status.
CREATE INDEX IF NOT EXISTS idx_pesanan_delivery_status
    ON pesanan (tgl_kirim, status);
