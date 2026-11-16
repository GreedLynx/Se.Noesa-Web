-- DDL: tambahkan nilai kuota harian pada data produk.
ALTER TABLE produk ADD COLUMN IF NOT EXISTS kuota_harian INT NOT NULL DEFAULT 0;