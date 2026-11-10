-- DDL: tambahkan kolom nama pembayar pada data pesanan.
ALTER TABLE pesanan
  ADD COLUMN IF NOT EXISTS nama_pembayar VARCHAR(100);