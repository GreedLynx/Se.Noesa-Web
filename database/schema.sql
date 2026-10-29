-- createdb senoesa && psql senoesa -f database/schema.sql
DROP TABLE IF EXISTS detail_pesanan,
pesanan,
jadwal_po,
produk,
users CASCADE;
CREATE TABLE users(
  id SERIAL PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  phone VARCHAR(20),
  role VARCHAR(10) NOT NULL DEFAULT 'pelanggan' CHECK(role IN('pelanggan', 'admin', 'owner')),
  created_at TIMESTAMPTZ DEFAULT now()
);
CREATE TABLE produk(
  id SERIAL PRIMARY KEY,
  kode VARCHAR(10) UNIQUE NOT NULL,
  nama VARCHAR(100) NOT NULL,
  kategori VARCHAR(10) NOT NULL CHECK(kategori IN('makanan', 'minuman', 'snack')),
  harga INT NOT NULL,
  kuota INT NOT NULL DEFAULT 0,
  min_pesan INT NOT NULL DEFAULT 1 CHECK(min_pesan >= 1),
  terpakai INT NOT NULL DEFAULT 0,
  gambar VARCHAR(120)
);
CREATE TABLE jadwal_po(
  id SERIAL PRIMARY KEY,
  nama VARCHAR(80),
  tgl_buka DATE,
  tgl_tutup DATE,
  kapasitas INT NOT NULL,
  aktif BOOLEAN DEFAULT true
);
CREATE TABLE pesanan(
  id SERIAL PRIMARY KEY,
  kode VARCHAR(20) UNIQUE NOT NULL,
  user_id INT REFERENCES users(id),
  jadwal_id INT REFERENCES jadwal_po(id),
  nama_pemesan VARCHAR(100) NOT NULL,
  keterangan VARCHAR(120),
  pax INT NOT NULL DEFAULT 0,
  total INT NOT NULL DEFAULT 0,
  status VARCHAR(24) NOT NULL DEFAULT 'menunggu_pembayaran' CHECK(
    status IN(
      'menunggu_pembayaran',
      'menunggu_validasi',
      'diproses',
      'selesai',
      'ditolak'
    )
  ),
  bukti_bayar VARCHAR(200),
  nama_pembayar VARCHAR(100),
  foto_kiriman VARCHAR(200),
  tgl_kirim TIMESTAMPTZ,
  status_updated_at TIMESTAMPTZ DEFAULT now(),
  created_at TIMESTAMPTZ DEFAULT now()
);
CREATE TABLE detail_pesanan(
  id SERIAL PRIMARY KEY,
  pesanan_id INT REFERENCES pesanan(id) ON DELETE CASCADE,
  produk_id INT REFERENCES produk(id),
  qty INT NOT NULL,
  harga INT NOT NULL,
  sudah_dicek BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE TABLE alamat_pelanggan(
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  label VARCHAR(50) NOT NULL DEFAULT 'Rumah',
  nama_penerima VARCHAR(100) NOT NULL,
  telepon VARCHAR(20) NOT NULL,
  alamat TEXT NOT NULL,
  is_default BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE pesanan ADD COLUMN alamat_pengiriman TEXT;
ALTER TABLE pesanan ADD COLUMN telepon_penerima VARCHAR(20);
CREATE INDEX ON pesanan(status, created_at);
CREATE INDEX ON detail_pesanan(pesanan_id);

