-- DDL: tambahkan status checklist untuk setiap item pesanan.
ALTER TABLE detail_pesanan
  ADD COLUMN IF NOT EXISTS sudah_dicek BOOLEAN NOT NULL DEFAULT FALSE;

-- DDL: simpan lokasi foto kiriman pada data pesanan.
ALTER TABLE pesanan
  ADD COLUMN IF NOT EXISTS foto_kiriman VARCHAR(200);
