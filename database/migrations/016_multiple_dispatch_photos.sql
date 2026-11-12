-- DDL: simpan banyak foto kiriman untuk setiap pesanan.
CREATE TABLE IF NOT EXISTS pesanan_foto_kiriman (
  id SERIAL PRIMARY KEY,
  pesanan_id INT NOT NULL REFERENCES pesanan(id) ON DELETE CASCADE,
  path VARCHAR(200) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS pesanan_foto_kiriman_order_idx
  ON pesanan_foto_kiriman(pesanan_id, id);

-- Backfill foto lama supaya tetap muncul di galeri foto baru.
INSERT INTO pesanan_foto_kiriman (pesanan_id, path)
SELECT p.id, p.foto_kiriman
FROM pesanan p
WHERE p.foto_kiriman IS NOT NULL
  AND p.foto_kiriman <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM pesanan_foto_kiriman f
    WHERE f.pesanan_id = p.id AND f.path = p.foto_kiriman
  );
