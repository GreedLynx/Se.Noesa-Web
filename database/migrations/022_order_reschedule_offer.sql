CREATE TABLE IF NOT EXISTS penawaran_jadwal_ulang (
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
