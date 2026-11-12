-- Gunakan 50 porsi sebagai kuota default untuk setiap produk dan tanggal tanpa override.
UPDATE produk
SET kuota_harian = 50;

ALTER TABLE produk
  ALTER COLUMN kuota_harian SET DEFAULT 50;
