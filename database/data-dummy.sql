-- SEED (login owner: owner@senoesa.id / owner123)

INSERT INTO users(username, email, password_hash, phone, role)
VALUES
  (
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
VALUES
  (
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
VALUES ('PO Oktober 2026', '2026-10-01', '2026-10-31', 2200);

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
VALUES
  (
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
VALUES
  (1, 1, 100, 30000),
  (1, 7, 100, 5000),
  (2, 1, 220, 32000),
  (3, 7, 75, 5000);

</content>