-- Allow verified late payments to reactivate the original cancelled/rejected order
-- after explicit WhatsApp confirmation and a fresh capacity/menu validation.
ALTER TABLE pembayaran_terlambat
    DROP CONSTRAINT IF EXISTS pembayaran_terlambat_tindak_lanjut_check,
    DROP CONSTRAINT IF EXISTS pembayaran_terlambat_follow_up_check;

ALTER TABLE pembayaran_terlambat
    ADD CONSTRAINT pembayaran_terlambat_tindak_lanjut_check
        CHECK (tindak_lanjut IS NULL OR tindak_lanjut IN ('lanjutkan_pesanan', 'pesanan_baru', 'refund')),
    ADD CONSTRAINT pembayaran_terlambat_follow_up_check CHECK (
        (tindak_lanjut IS NULL) = (tindak_lanjut_at IS NULL)
        AND (tindak_lanjut IS NULL OR status_rekonsiliasi = 'terverifikasi')
        AND (
            tindak_lanjut IS NULL
            OR (tindak_lanjut = 'lanjutkan_pesanan' AND tindak_lanjut_order_id = pesanan_id)
            OR (tindak_lanjut = 'pesanan_baru' AND tindak_lanjut_order_id IS NOT NULL)
            OR (tindak_lanjut = 'refund'
                AND refund_nominal IS NOT NULL
                AND refund_tanggal IS NOT NULL)
        )
    );
