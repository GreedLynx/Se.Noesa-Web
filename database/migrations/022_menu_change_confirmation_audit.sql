ALTER TABLE pemberitahuan_perubahan_menu_pesanan
    ADD COLUMN IF NOT EXISTS total_pesanan_lama INT CHECK (total_pesanan_lama IS NULL OR total_pesanan_lama >= 0),
    ADD COLUMN IF NOT EXISTS total_pesanan_baru INT CHECK (total_pesanan_baru IS NULL OR total_pesanan_baru >= 0),
    ADD COLUMN IF NOT EXISTS tindak_lanjut_oleh INT REFERENCES users(id) ON DELETE RESTRICT,
    ADD COLUMN IF NOT EXISTS tindak_lanjut_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS catatan_tindak_lanjut TEXT;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'pemberitahuan_menu_pesanan_order_total_snapshot_check'
          AND conrelid = 'pemberitahuan_perubahan_menu_pesanan'::regclass
    ) THEN
        ALTER TABLE pemberitahuan_perubahan_menu_pesanan
            ADD CONSTRAINT pemberitahuan_menu_pesanan_order_total_snapshot_check
            CHECK ((total_pesanan_lama IS NULL) = (total_pesanan_baru IS NULL));
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'pemberitahuan_menu_pesanan_follow_up_check'
          AND conrelid = 'pemberitahuan_perubahan_menu_pesanan'::regclass
    ) THEN
        ALTER TABLE pemberitahuan_perubahan_menu_pesanan
            ADD CONSTRAINT pemberitahuan_menu_pesanan_follow_up_check
            CHECK ((tindak_lanjut_oleh IS NULL) = (tindak_lanjut_at IS NULL));
    END IF;
END;
$$;
