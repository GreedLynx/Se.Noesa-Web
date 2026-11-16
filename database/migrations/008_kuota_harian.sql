-- Migrasi 008: Tabel kuota harian per produk per tanggal kirim.
-- DDL: simpan konfigurasi kuota per produk dan tanggal pengiriman.
CREATE TABLE IF NOT EXISTS kuota_harian (
    id SERIAL PRIMARY KEY,
    produk_id INTEGER NOT NULL REFERENCES produk(id) ON DELETE CASCADE,
    tanggal DATE NOT NULL,
    kuota INTEGER NOT NULL DEFAULT 0,
    UNIQUE(produk_id, tanggal)
);

-- INDEX: dukung pencarian konfigurasi kuota menurut tanggal atau produk.
CREATE INDEX idx_kuota_harian_tanggal ON kuota_harian(tanggal);
CREATE INDEX idx_kuota_harian_produk ON kuota_harian(produk_id);