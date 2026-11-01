CREATE TABLE IF NOT EXISTS alamat_pelanggan (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  label VARCHAR(50) NOT NULL DEFAULT 'Rumah',
  nama_penerima VARCHAR(100) NOT NULL,
  telepon VARCHAR(20) NOT NULL,
  alamat TEXT NOT NULL,
  is_default BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS alamat_pelanggan_user_idx ON alamat_pelanggan(user_id, is_default DESC, id);
ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS alamat_pengiriman TEXT;
ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS telepon_penerima VARCHAR(20);
