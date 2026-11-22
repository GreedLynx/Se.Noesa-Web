-- Rekonsiliasi pembayaran QRIS terlambat (keputusan bisnis: Admin Reconciliation).
-- Catatan ini TIDAK mengubah pesanan lama, status pesanan, kuota, stok, atau checkout normal.

CREATE TABLE IF NOT EXISTS pembayaran_terlambat (
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
            OR (tindak_lanjut = 'refund'
                AND refund_nominal IS NOT NULL
                AND refund_tanggal IS NOT NULL)
        )
    )
);

-- Audit trail append-only: agent aplikasi hanya boleh INSERT ke tabel ini.
CREATE TABLE IF NOT EXISTS pembayaran_terlambat_log (
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

CREATE OR REPLACE FUNCTION pembayaran_terlambat_log_immutable()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Audit trail rekonsiliasi bersifat append-only';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS pembayaran_terlambat_log_no_update_delete
    ON pembayaran_terlambat_log;
CREATE TRIGGER pembayaran_terlambat_log_no_update_delete
    BEFORE UPDATE OR DELETE ON pembayaran_terlambat_log
    FOR EACH ROW EXECUTE FUNCTION pembayaran_terlambat_log_immutable();

CREATE INDEX IF NOT EXISTS pembayaran_terlambat_status_created_idx
    ON pembayaran_terlambat (status_rekonsiliasi, dicatat_at DESC);
CREATE INDEX IF NOT EXISTS pembayaran_terlambat_dicatat_oleh_idx
    ON pembayaran_terlambat (dicatat_oleh, dicatat_at DESC);
CREATE INDEX IF NOT EXISTS pembayaran_terlambat_log_rekonsiliasi_idx
    ON pembayaran_terlambat_log (rekonsiliasi_id, created_at);
