-- Pulihkan minimum dasar 4 porsi setelah migrasi 025 menaikkan semua nilai di bawah 10.
-- Nilai di atas 10 tetap menjadi minimum khusus produk.
-- Lepas constraint lama sebelum menurunkan nilai data dari 10 menjadi 4.
ALTER TABLE produk
  DROP CONSTRAINT IF EXISTS produk_min_pesan_check;

UPDATE produk
SET min_pesan = 4
WHERE min_pesan = 10;

ALTER TABLE produk
  ALTER COLUMN min_pesan SET DEFAULT 4,
  ADD CONSTRAINT produk_min_pesan_check CHECK (min_pesan >= 4);
