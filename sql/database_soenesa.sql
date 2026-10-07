-- ============================================
-- SISTEM PRE-ORDER CATERING - SE.NOESA
-- ============================================

-- 1. TABEL USERS
CREATE TABLE users (
    user_id     BIGSERIAL PRIMARY KEY,
    nama        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    no_telp     VARCHAR(20),
    alamat      TEXT,
    role        VARCHAR(20) NOT NULL DEFAULT 'pelanggan'
                CHECK (role IN ('pelanggan', 'admin', 'owner')),
    created_at  TIMESTAMPTZ DEFAULT now()
);

-- 2. TABEL JADWAL_PO
CREATE TABLE jadwal_po (
    jadwal_id           BIGSERIAL PRIMARY KEY,
    nama_jadwal         VARCHAR(100) NOT NULL,
    tanggal_buka        TIMESTAMPTZ,
    tanggal_tutup       TIMESTAMPTZ,
    tanggal_pengiriman  DATE,
    kapasitas_porsi     INT,
    status_jadwal       VARCHAR(20) DEFAULT 'draft'
                        CHECK (status_jadwal IN ('draft','dibuka','ditutup','selesai','dibatalkan')),
    catatan             TEXT,
    created_at          TIMESTAMPTZ DEFAULT now()
);

-- 3. TABEL PRODUK
CREATE TABLE produk (
    produk_id     BIGSERIAL PRIMARY KEY,
    nama_produk   VARCHAR(100) NOT NULL,
    harga         NUMERIC(12,2) NOT NULL CHECK (harga >= 0),
    minimal_porsi INT CHECK (minimal_porsi > 0),
    stok_harian   INT DEFAULT 0,
    status_produk VARCHAR(20) DEFAULT 'tersedia'
                  CHECK (status_produk IN ('tersedia','tidak tersedia')),
    foto          VARCHAR(255)
);

-- 4. TABEL PESANAN
CREATE TABLE pesanan (
    pesanan_id       BIGSERIAL PRIMARY KEY,
    user_id          BIGINT NOT NULL REFERENCES users(user_id),
    jadwal_id        BIGINT NOT NULL REFERENCES jadwal_po(jadwal_id),
    tanggal_pesan    TIMESTAMPTZ DEFAULT now(),
    metode_pembayaran VARCHAR(20),
    nama_pembayar    VARCHAR(100),
    bank_ewallet     VARCHAR(50),
    bukti_pembayaran VARCHAR(255),
    status_pesanan   VARCHAR(20) DEFAULT 'menunggu'
                     CHECK (status_pesanan IN ('menunggu','diproses','siap','dikirim','selesai','batal')),
    catatan          TEXT,
    total_harga      NUMERIC(12,2) DEFAULT 0
);

-- 5. TABEL DETAIL_PESANAN
CREATE TABLE detail_pesanan (
    detail_id    BIGSERIAL PRIMARY KEY,
    pesanan_id   BIGINT NOT NULL REFERENCES pesanan(pesanan_id) ON DELETE CASCADE,
    produk_id    BIGINT NOT NULL REFERENCES produk(produk_id),
    jumlah_porsi INT NOT NULL CHECK (jumlah_porsi > 0),
    harga_satuan NUMERIC(12,2) NOT NULL,
    subtotal     NUMERIC(12,2) NOT NULL,
    catatan_menu TEXT
);

-- INDEX untuk performa
CREATE INDEX idx_pesanan_user    ON pesanan(user_id);
CREATE INDEX idx_pesanan_jadwal  ON pesanan(jadwal_id);
CREATE INDEX idx_detail_pesanan  ON detail_pesanan(pesanan_id);
CREATE INDEX idx_detail_produk   ON detail_pesanan(produk_id);