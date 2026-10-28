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
-- SEED (login owner: owner@senoesa.id / owner123)
INSERT INTO users(username, email, password_hash, phone, role)
VALUES (
    'hendra',
    'owner@senoesa.id',
    '$2y$12$qWPNCZo4VgwDnzEbpRO9GeWfbA6EYRqktvsjl9mAJIrKhqpuzVXdW',
    '08123456789',
    'owner'
  ),
  (
    'andi',
    'andi@mail.com',
    '$2y$12$2tI1xZ7Et3mnGlF7CfqK5uvdpRWDcSbzBh/XOZBTauaBuxZyuqNce',
    '0811111111',
    'pelanggan'
  );
INSERT INTO produk(kode, nama, kategori, harga, kuota, terpakai, gambar)
VALUES (
    'NK-01',
    'Paket Nasi Kotak Ayam',
    'makanan',
    42000,
    150,
    105,
    'NK-01.png'
  ),
  (
    'BF-04',
    'Nasi Bulat Pak Kris',
    'makanan',
    85000,
    200,
    182,
    'NK-02.png'
  ),
  (
    'TP-02',
    'Tumpeng Coklat',
    'makanan',
    550000,
    10,
    7,
    NULL
  ),
  (
    'BT-08',
    'Bento Beef Teriyaki',
    'makanan',
    48000,
    120,
    48,
    'BT-01.png'
  ),
  (
    'SB-03',
    'Coffe aja sih',
    'minuman',
    22000,
    100,
    100,
    'ES-01.png'
  ),
  (
    'MC-01',
    'Matcha Cold Brew',
    'minuman',
    10000,
    200,
    60,
    'MC-01.png'
  ),
  (
    'SN-01',
    'Snack Box Coffee Break',
    'snack',
    5000,
    300,
    120,
    NULL
  );
INSERT INTO jadwal_po(nama, tgl_buka, tgl_tutup, kapasitas)
VALUES('PO Oktober 2026', '2026-10-01', '2026-10-31', 2200);
INSERT INTO pesanan(
    kode,
    user_id,
    jadwal_id,
    nama_pemesan,
    keterangan,
    pax,
    total,
    status,
    tgl_kirim,
    created_at
  )
VALUES (
    '#CAT-8840',
    2,
    1,
    'Andi Pratama',
    '150 Pax Gathering Kantor',
    150,
    3500000,
    'diproses',
    '2026-10-20 11:30+07',
    now()
  ),
  (
    '#CAT-8839',
    2,
    1,
    'PT Surya Kencana',
    '220 Box Nasi Liwet',
    220,
    7200000,
    'menunggu_validasi',
    '2026-10-21 11:00+07',
    now()
  ),
  (
    '#CAT-8835',
    2,
    1,
    'Bpk. Hendra Gunawan',
    '75 Pax Coffee Break',
    75,
    1850000,
    'selesai',
    '2026-10-19 09:00+07',
    now() - interval '1 day'
  );
INSERT INTO detail_pesanan(pesanan_id, produk_id, qty, harga)
VALUES(1, 1, 100, 30000),
(1, 7, 100, 5000),
(2, 1, 220, 32000),
(3, 7, 75, 5000);
SELECT id,
  username,
  email,
  role
FROM users
ORDER BY id;
SELECT id,
  username,
  email,
  role
FROM users
ORDER BY id;
