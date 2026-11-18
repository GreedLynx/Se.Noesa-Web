-- createdb senoesa && psql senoesa -f database/schema.sql
-- PERHATIAN: skrip inisialisasi ini menghapus tabel lama sebelum membuat ulang skema.
-- Jalankan hanya untuk database baru atau database yang memang siap di-reset.
-- DDL: hapus tabel berurutan dengan CASCADE agar relasi dependennya ikut dibersihkan.
DROP TABLE IF EXISTS 
  pemberitahuan_perubahan_menu_pesanan,
  pemberitahuan_perubahan_menu_opsi,
  pemberitahuan_perubahan_menu,
  pesanan_foto_kiriman,
  detail_pesanan,
  penawaran_jadwal_ulang,
  pesanan,
  jadwal_po,
  produk,
  password_reset_otps,
  password_resets,
  users 
CASCADE;

-- DDL: tabel akun dan role pengguna aplikasi.
CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  phone VARCHAR(20),
  role VARCHAR(10) NOT NULL DEFAULT 'pelanggan' CHECK(role IN('pelanggan', 'admin', 'owner')),
  created_at TIMESTAMPTZ DEFAULT now()
);

-- DDL: tabel token dan OTP untuk proses pemulihan password.
CREATE TABLE password_resets (
  id BIGSERIAL PRIMARY KEY,
  user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  used_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX password_resets_expiry_idx ON password_resets(expires_at);

CREATE TABLE password_reset_otps (
  id BIGSERIAL PRIMARY KEY,
  user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  attempts SMALLINT NOT NULL DEFAULT 0 CHECK (attempts BETWEEN 0 AND 5),
  requested_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  used_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX password_reset_otps_expiry_idx ON password_reset_otps(expires_at);

-- DDL: tabel master produk, harga, kategori, dan informasi kuota.
CREATE TABLE produk (
  id SERIAL PRIMARY KEY,
  kode VARCHAR(10) UNIQUE NOT NULL,
  nama VARCHAR(100) NOT NULL,
  kategori VARCHAR(10) NOT NULL CHECK(kategori IN('makanan', 'minuman', 'snack')),
  harga INT NOT NULL CHECK(harga >= 0),
  kuota INT NOT NULL DEFAULT 0,
  kuota_harian INT NOT NULL DEFAULT 50 CONSTRAINT produk_kuota_harian_nonnegative_check CHECK(kuota_harian >= 0),
  min_pesan INT NOT NULL DEFAULT 10 CHECK(min_pesan >= 10),
  terpakai INT NOT NULL DEFAULT 0,
  gambar VARCHAR(120)
);

-- DDL: tabel jadwal pre-order dan kapasitasnya.
CREATE TABLE jadwal_po (
  id SERIAL PRIMARY KEY,
  nama VARCHAR(80),
  tgl_buka DATE,
  tgl_tutup DATE,
  kapasitas INT NOT NULL CHECK(kapasitas >= 0),
  CHECK(tgl_tutup >= tgl_buka),
  aktif BOOLEAN DEFAULT true
);

-- DDL: tabel header pesanan, pemilik, status, total, dan informasi pengiriman.
CREATE TABLE pesanan (
  id SERIAL PRIMARY KEY,
  kode VARCHAR(20) UNIQUE NOT NULL,
  user_id INT REFERENCES users(id),
  jadwal_id INT REFERENCES jadwal_po(id),
  nama_pemesan VARCHAR(100) NOT NULL,
  keterangan VARCHAR(120),
  pax INT NOT NULL DEFAULT 0,
  total INT NOT NULL DEFAULT 0 CHECK(total >= 0),
  sumber VARCHAR(10) NOT NULL DEFAULT 'online' CHECK (sumber IN ('online', 'offline')),
  status VARCHAR(24) NOT NULL DEFAULT 'menunggu_pembayaran'
    CHECK (
      status IN (
        'menunggu_pembayaran',
        'menunggu_validasi',
        'diproses',
        'selesai',
        'ditolak',
        'dibatalkan'
      )
    ),
  bukti_bayar VARCHAR(200),
  nama_pembayar VARCHAR(100),
  alasan_penolakan VARCHAR(500),
  foto_kiriman VARCHAR(200),
  tgl_kirim TIMESTAMPTZ,
  status_updated_at TIMESTAMPTZ DEFAULT now(),
  created_at TIMESTAMPTZ DEFAULT now()
);

-- DDL: satu tawaran jadwal pengganti per pesanan; status pesanan tetap sampai pelanggan menjawab.
CREATE TABLE penawaran_jadwal_ulang (
  id SERIAL PRIMARY KEY,
  pesanan_id INT NOT NULL UNIQUE REFERENCES pesanan(id) ON DELETE CASCADE,
  status_asal VARCHAR(24) NOT NULL
    CHECK (status_asal IN ('menunggu_pembayaran', 'menunggu_validasi', 'diproses')),
  tanggal_asal DATE NOT NULL,
  alasan VARCHAR(500) NOT NULL
    CHECK (CHAR_LENGTH(BTRIM(alasan)) BETWEEN 1 AND 500),
  tanggal_usulan DATE NOT NULL,
  status_tawaran VARCHAR(12) NOT NULL DEFAULT 'menunggu'
    CHECK (status_tawaran IN ('menunggu', 'diterima', 'ditolak', 'kedaluwarsa')),
  dibuat_oleh INT REFERENCES users(id) ON DELETE SET NULL,
  dibuat_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  berlaku_sampai TIMESTAMPTZ NOT NULL,
  ditanggapi_at TIMESTAMPTZ,
  CHECK (berlaku_sampai > dibuat_at)
);

-- DDL: tabel baris item yang menghubungkan pesanan dengan produk.
CREATE TABLE detail_pesanan (
  id SERIAL PRIMARY KEY,
  pesanan_id INT REFERENCES pesanan(id) ON DELETE CASCADE,
  produk_id INT REFERENCES produk(id),
  qty INT NOT NULL CHECK(qty > 0),
  harga INT NOT NULL CHECK(harga >= 0),
  sudah_dicek BOOLEAN NOT NULL DEFAULT FALSE
);

-- DDL: simpan pemberitahuan, opsi respons, dan keputusan perubahan menu per item pesanan.
CREATE TABLE pemberitahuan_perubahan_menu (
  id SERIAL PRIMARY KEY,
  produk_id INT REFERENCES produk(id) ON DELETE SET NULL,
  kode_produk_snapshot VARCHAR(10) NOT NULL,
  nama_produk_snapshot VARCHAR(100) NOT NULL,
  catatan_admin TEXT NOT NULL CHECK (length(btrim(catatan_admin)) > 0),
  created_by INT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE pemberitahuan_perubahan_menu_opsi (
  id SERIAL PRIMARY KEY,
  pemberitahuan_id INT NOT NULL REFERENCES pemberitahuan_perubahan_menu(id) ON DELETE RESTRICT,
  kode_opsi VARCHAR(30) NOT NULL CHECK (kode_opsi IN ('menu_pengganti', 'hubungi_admin', 'tunggu_informasi')),
  label VARCHAR(100) NOT NULL CHECK (length(btrim(label)) > 0),
  urutan SMALLINT NOT NULL DEFAULT 0 CHECK (urutan >= 0),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (pemberitahuan_id, kode_opsi),
  UNIQUE (pemberitahuan_id, id)
);

CREATE TABLE pemberitahuan_perubahan_menu_pesanan (
  id SERIAL PRIMARY KEY,
  pemberitahuan_id INT NOT NULL REFERENCES pemberitahuan_perubahan_menu(id) ON DELETE RESTRICT,
  detail_pesanan_id INT NOT NULL REFERENCES detail_pesanan(id) ON DELETE RESTRICT,
  status_respons VARCHAR(30) NOT NULL DEFAULT 'menunggu_respons'
    CHECK (status_respons IN (
      'menunggu_respons',
      'menunggu_konfirmasi',
      'menunggu_tindak_lanjut',
      'dikonfirmasi',
      'selesai'
    )),
  opsi_id INT,
  produk_lama_id INT REFERENCES produk(id) ON DELETE SET NULL,
  kode_produk_lama_snapshot VARCHAR(10) NOT NULL,
  nama_produk_lama_snapshot VARCHAR(100) NOT NULL,
  qty_lama INT NOT NULL CHECK (qty_lama > 0),
  harga_lama INT NOT NULL CHECK (harga_lama >= 0),
  produk_pengganti_id INT REFERENCES produk(id) ON DELETE SET NULL,
  kode_produk_pengganti_snapshot VARCHAR(10),
  nama_produk_pengganti_snapshot VARCHAR(100),
  qty_pengganti INT CHECK (qty_pengganti IS NULL OR qty_pengganti > 0),
  harga_pengganti INT CHECK (harga_pengganti IS NULL OR harga_pengganti >= 0),
  selisih_total BIGINT,
  status_penyelesaian_selisih VARCHAR(30) NOT NULL DEFAULT 'belum_dihitung'
    CHECK (status_penyelesaian_selisih IN (
      'belum_dihitung',
      'tidak_ada',
      'menunggu_tagihan',
      'menunggu_pengembalian',
      'selesai'
    )),
  catatan_respons TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  dibaca_at TIMESTAMPTZ,
  responded_at TIMESTAMPTZ,
  confirmed_by INT REFERENCES users(id) ON DELETE RESTRICT,
  confirmed_at TIMESTAMPTZ,
  selisih_diselesaikan_oleh INT REFERENCES users(id) ON DELETE RESTRICT,
  selisih_diselesaikan_at TIMESTAMPTZ,
  total_pesanan_lama INT CHECK (total_pesanan_lama IS NULL OR total_pesanan_lama >= 0),
  total_pesanan_baru INT CHECK (total_pesanan_baru IS NULL OR total_pesanan_baru >= 0),
  harga_katalog_saat_konfirmasi INT,
  perbedaan_harga_katalog BIGINT,
  tindak_lanjut_oleh INT REFERENCES users(id) ON DELETE RESTRICT,
  tindak_lanjut_at TIMESTAMPTZ,
  catatan_tindak_lanjut TEXT,
  jenis_penutupan VARCHAR(30),
  alasan_penutupan TEXT,
  ditutup_oleh INT REFERENCES users(id) ON DELETE RESTRICT,
  ditutup_at TIMESTAMPTZ,
  CONSTRAINT pemberitahuan_menu_pesanan_perubahan_snapshot_check CHECK (
    (kode_produk_pengganti_snapshot IS NULL
      AND nama_produk_pengganti_snapshot IS NULL
      AND qty_pengganti IS NULL
      AND harga_pengganti IS NULL)
    OR
    (kode_produk_pengganti_snapshot IS NOT NULL
      AND nama_produk_pengganti_snapshot IS NOT NULL
      AND qty_pengganti IS NOT NULL
      AND harga_pengganti IS NOT NULL)
  ),
  CONSTRAINT pemberitahuan_menu_pesanan_confirmation_check CHECK (
    (confirmed_by IS NULL) = (confirmed_at IS NULL)
  ),
  CONSTRAINT pemberitahuan_menu_pesanan_resolution_check CHECK (
    (selisih_diselesaikan_oleh IS NULL) = (selisih_diselesaikan_at IS NULL)
  ),
  CONSTRAINT pemberitahuan_menu_pesanan_order_total_snapshot_check CHECK (
    (total_pesanan_lama IS NULL) = (total_pesanan_baru IS NULL)
  ),
  CONSTRAINT pemberitahuan_menu_pesanan_catalog_price_check CHECK (
    (harga_katalog_saat_konfirmasi IS NULL) = (perbedaan_harga_katalog IS NULL)
    AND (harga_katalog_saat_konfirmasi IS NULL OR harga_katalog_saat_konfirmasi >= 0)
  ),
  CONSTRAINT pemberitahuan_menu_pesanan_follow_up_check CHECK (
    (tindak_lanjut_oleh IS NULL) = (tindak_lanjut_at IS NULL)
  ),
  CONSTRAINT pemberitahuan_menu_pesanan_closure_type_check CHECK (
    jenis_penutupan IS NULL
    OR jenis_penutupan IN (
      'penolakan_pengganti',
      'pembatalan_pesanan',
      'kedaluwarsa_pesanan'
    )
  ),
  CONSTRAINT pemberitahuan_menu_pesanan_closure_audit_check CHECK (
    (jenis_penutupan IS NULL) = (alasan_penutupan IS NULL)
    AND (jenis_penutupan IS NULL) = (ditutup_at IS NULL)
    AND (alasan_penutupan IS NULL OR length(btrim(alasan_penutupan)) > 0)
  ),
  CONSTRAINT pemberitahuan_menu_pesanan_closure_actor_check CHECK (
    (ditutup_oleh IS NULL OR jenis_penutupan IS NOT NULL)
    AND (jenis_penutupan IS DISTINCT FROM 'penolakan_pengganti' OR ditutup_oleh IS NOT NULL)
  ),
  CONSTRAINT pemberitahuan_menu_pesanan_unique_detail UNIQUE (pemberitahuan_id, detail_pesanan_id),
  CONSTRAINT pemberitahuan_menu_pesanan_option_fk
    FOREIGN KEY (pemberitahuan_id, opsi_id)
    REFERENCES pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, id)
    ON DELETE RESTRICT
);

CREATE INDEX pemberitahuan_perubahan_menu_created_idx
  ON pemberitahuan_perubahan_menu (created_at DESC);
CREATE INDEX pemberitahuan_perubahan_menu_produk_created_idx
  ON pemberitahuan_perubahan_menu (produk_id, created_at DESC);
CREATE INDEX pemberitahuan_perubahan_menu_opsi_order_idx
  ON pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, urutan, id);
CREATE INDEX pemberitahuan_perubahan_menu_pesanan_detail_idx
  ON pemberitahuan_perubahan_menu_pesanan (detail_pesanan_id);
CREATE INDEX pemberitahuan_perubahan_menu_pesanan_status_idx
  ON pemberitahuan_perubahan_menu_pesanan (status_respons, created_at DESC);

-- DDL: rekonsiliasi pembayaran QRIS terlambat (tidak mengubah pesanan lama/kuota).
CREATE TABLE pembayaran_terlambat (
  id SERIAL PRIMARY KEY,
  pesanan_id INT NOT NULL UNIQUE REFERENCES pesanan(id) ON DELETE RESTRICT,
  bukti_path VARCHAR(200) NOT NULL
    CHECK (bukti_path ~ '^(storage/payments|uploads/payments)/[A-Za-z0-9._-]+$'),
  nominal_dilaporkan BIGINT NOT NULL CHECK (nominal_dilaporkan >= 0),
  waktu_transaksi TIMESTAMPTZ NOT NULL,
  referensi_transaksi VARCHAR(100),
  status_rekonsiliasi VARCHAR(30) NOT NULL DEFAULT 'menunggu_verifikasi'
    CHECK (status_rekonsiliasi IN ('menunggu_verifikasi', 'terverifikasi', 'tidak_valid')),
  catatan_admin TEXT,
  dicatat_oleh INT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  dicatat_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  diverifikasi_oleh INT REFERENCES users(id) ON DELETE RESTRICT,
  diverifikasi_at TIMESTAMPTZ,
  hasil_catatan TEXT,
  tindak_lanjut VARCHAR(30)
    CHECK (tindak_lanjut IS NULL OR tindak_lanjut IN ('pesanan_baru', 'refund')),
  tindak_lanjut_order_id INT REFERENCES pesanan(id) ON DELETE SET NULL,
  tindak_lanjut_catatan TEXT,
  refund_nominal BIGINT CHECK (refund_nominal IS NULL OR refund_nominal >= 0),
  refund_tanggal TIMESTAMPTZ,
  refund_referensi VARCHAR(100),
  tindak_lanjut_oleh INT REFERENCES users(id) ON DELETE RESTRICT,
  tindak_lanjut_at TIMESTAMPTZ,
  CONSTRAINT pembayaran_terlambat_verification_check CHECK (
    (diverifikasi_oleh IS NULL) = (diverifikasi_at IS NULL)
    AND (
      (status_rekonsiliasi = 'menunggu_verifikasi' AND diverifikasi_oleh IS NULL)
      OR (status_rekonsiliasi IN ('terverifikasi', 'tidak_valid') AND diverifikasi_oleh IS NOT NULL)
    )
  ),
  CONSTRAINT pembayaran_terlambat_follow_up_check CHECK (
    (tindak_lanjut IS NULL) = (tindak_lanjut_at IS NULL)
    AND (tindak_lanjut IS NULL OR status_rekonsiliasi = 'terverifikasi')
    AND (
      tindak_lanjut IS NULL
      OR (tindak_lanjut = 'pesanan_baru' AND tindak_lanjut_order_id IS NOT NULL)
      OR (tindak_lanjut = 'refund' AND refund_nominal IS NOT NULL AND refund_tanggal IS NOT NULL)
    )
  )
);

CREATE TABLE pembayaran_terlambat_log (
  id SERIAL PRIMARY KEY,
  rekonsiliasi_id INT NOT NULL REFERENCES pembayaran_terlambat(id) ON DELETE RESTRICT,
  aksi VARCHAR(30) NOT NULL
    CHECK (aksi IN ('dicatat', 'diverifikasi', 'ditolak', 'tindak_lanjut')),
  status_sebelum VARCHAR(30),
  status_sesudah VARCHAR(30),
  alasan TEXT,
  actor_id INT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE FUNCTION pembayaran_terlambat_log_immutable()
RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'Audit trail rekonsiliasi bersifat append-only';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER pembayaran_terlambat_log_no_update_delete
  BEFORE UPDATE OR DELETE ON pembayaran_terlambat_log
  FOR EACH ROW EXECUTE FUNCTION pembayaran_terlambat_log_immutable();

CREATE INDEX pembayaran_terlambat_status_created_idx
  ON pembayaran_terlambat (status_rekonsiliasi, dicatat_at DESC);
CREATE INDEX pembayaran_terlambat_log_rekonsiliasi_idx
  ON pembayaran_terlambat_log (rekonsiliasi_id, created_at);

-- DDL: simpan seluruh foto kiriman per pesanan; kolom pesanan.foto_kiriman tetap menjadi kompatibilitas foto lama.
CREATE TABLE pesanan_foto_kiriman (
  id SERIAL PRIMARY KEY,
  pesanan_id INT NOT NULL REFERENCES pesanan(id) ON DELETE CASCADE,
  path VARCHAR(200) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX pesanan_foto_kiriman_order_idx ON pesanan_foto_kiriman(pesanan_id, id);

-- DDL: tabel alamat tersimpan milik pelanggan.
CREATE TABLE alamat_pelanggan (
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
-- Data contoh awal untuk akun owner dan pelanggan.
INSERT INTO users (username, email, password_hash, phone, role)
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
-- SEED: contoh data produk untuk katalog.
INSERT INTO produk (kode, nama, kategori, harga, kuota, terpakai, gambar)
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

-- SEED: satu jadwal PO untuk contoh.
INSERT INTO jadwal_po (nama, tgl_buka, tgl_tutup, kapasitas)
VALUES ('PO Oktober 2026', '2026-10-01', '2026-10-31', 2200);
-- SEED: contoh header pesanan dalam beberapa status.
INSERT INTO pesanan (
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

-- SEED: detail produk untuk pesanan contoh.
INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga)
VALUES (1, 1, 100, 30000),
(1, 7, 100, 5000),
(2, 1, 220, 32000),
(3, 7, 75, 5000);
-- SELECT verifikasi: tampilkan akun yang sudah dimasukkan, urut berdasarkan ID.
SELECT id,
  username,
  email,
  role
FROM users
ORDER BY id;
-- SELECT verifikasi yang sama untuk memastikan urutan data akun.
SELECT id,
  username,
  email,
  role
FROM users
ORDER BY id;
