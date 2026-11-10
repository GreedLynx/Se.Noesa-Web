-- Selaraskan data lama dengan batas minimum aktif sebelum constraint diperketat.
UPDATE produk
SET min_pesan = 4
WHERE min_pesan < 4;

ALTER TABLE produk
  ALTER COLUMN min_pesan SET DEFAULT 4,
  DROP CONSTRAINT IF EXISTS produk_min_pesan_check,
  ADD CONSTRAINT produk_min_pesan_check CHECK (min_pesan >= 4),
  ADD CONSTRAINT produk_harga_nonnegative_check CHECK (harga >= 0);

ALTER TABLE kuota_harian
  ADD CONSTRAINT kuota_harian_kuota_nonnegative_check CHECK (kuota >= 0);

ALTER TABLE detail_pesanan
  ADD CONSTRAINT detail_pesanan_qty_positive_check CHECK (qty > 0),
  ADD CONSTRAINT detail_pesanan_harga_nonnegative_check CHECK (harga >= 0);

ALTER TABLE pesanan
  ADD CONSTRAINT pesanan_total_nonnegative_check CHECK (total >= 0);

ALTER TABLE jadwal_po
  ADD CONSTRAINT jadwal_po_kapasitas_nonnegative_check CHECK (kapasitas >= 0),
  ADD CONSTRAINT jadwal_po_tanggal_check CHECK (tgl_tutup >= tgl_buka);
