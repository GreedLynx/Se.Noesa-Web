ALTER TABLE pesanan
    ADD COLUMN sumber_pembatalan VARCHAR(12)
        CHECK (sumber_pembatalan IN ('pelanggan', 'otomatis')),
    ADD COLUMN status_sebelum_batal VARCHAR(24)
        CHECK (status_sebelum_batal IN ('menunggu_pembayaran', 'menunggu_validasi', 'diproses')),
    ADD COLUMN pembatalan_ditinjau_at TIMESTAMPTZ,
    ADD COLUMN pembatalan_ditinjau_oleh INT REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX idx_pesanan_pembatalan_belum_ditinjau
    ON pesanan(status_updated_at DESC, id DESC)
    WHERE status = 'dibatalkan' AND pembatalan_ditinjau_at IS NULL;

CREATE VIEW v_pembatalan_pesanan AS
SELECT p.id, p.kode, p.nama_pemesan, p.total, p.tgl_kirim,
       p.sumber_pembatalan AS cancellation_source,
       p.status_sebelum_batal AS status_before_cancel,
       p.status_updated_at AS cancelled_at,
       p.pembatalan_ditinjau_at AS reviewed_at,
       p.pembatalan_ditinjau_oleh AS reviewed_by,
       reviewer.username AS reviewed_by_name,
       (NULLIF(BTRIM(p.bukti_bayar), '') IS NOT NULL
           OR COALESCE(p.status_sebelum_batal IN ('menunggu_validasi', 'diproses'), FALSE)) AS needs_payment_review,
       COALESCE(p.status_sebelum_batal = 'diproses', FALSE) AS production_stop_required
FROM pesanan p
LEFT JOIN users reviewer ON reviewer.id = p.pembatalan_ditinjau_oleh
WHERE p.status = 'dibatalkan';
