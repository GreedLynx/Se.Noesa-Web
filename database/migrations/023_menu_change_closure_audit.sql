-- Audit penutupan respons: penolakan penggantian oleh Admin, pembatalan, dan kedaluwarsa.
-- Kolom terpisah dari catatan_tindak_lanjut agar jenis catatan tidak tercampur.
ALTER TABLE pemberitahuan_perubahan_menu_pesanan
    ADD COLUMN IF NOT EXISTS jenis_penutupan VARCHAR(30),
    ADD COLUMN IF NOT EXISTS alasan_penutupan TEXT,
    ADD COLUMN IF NOT EXISTS ditutup_oleh INT REFERENCES users(id) ON DELETE RESTRICT,
    ADD COLUMN IF NOT EXISTS ditutup_at TIMESTAMPTZ;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'pemberitahuan_menu_pesanan_closure_type_check'
          AND conrelid = 'pemberitahuan_perubahan_menu_pesanan'::regclass
    ) THEN
        ALTER TABLE pemberitahuan_perubahan_menu_pesanan
            ADD CONSTRAINT pemberitahuan_menu_pesanan_closure_type_check
            CHECK (
                jenis_penutupan IS NULL
                OR jenis_penutupan IN (
                    'penolakan_pengganti',
                    'pembatalan_pesanan',
                    'kedaluwarsa_pesanan'
                )
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'pemberitahuan_menu_pesanan_closure_audit_check'
          AND conrelid = 'pemberitahuan_perubahan_menu_pesanan'::regclass
    ) THEN
        ALTER TABLE pemberitahuan_perubahan_menu_pesanan
            ADD CONSTRAINT pemberitahuan_menu_pesanan_closure_audit_check
            CHECK (
                (jenis_penutupan IS NULL) = (alasan_penutupan IS NULL)
                AND (jenis_penutupan IS NULL) = (ditutup_at IS NULL)
                AND (alasan_penutupan IS NULL OR length(btrim(alasan_penutupan)) > 0)
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'pemberitahuan_menu_pesanan_closure_actor_check'
          AND conrelid = 'pemberitahuan_perubahan_menu_pesanan'::regclass
    ) THEN
        ALTER TABLE pemberitahuan_perubahan_menu_pesanan
            ADD CONSTRAINT pemberitahuan_menu_pesanan_closure_actor_check
            CHECK (
                (ditutup_oleh IS NULL OR jenis_penutupan IS NOT NULL)
                AND (jenis_penutupan IS DISTINCT FROM 'penolakan_pengganti' OR ditutup_oleh IS NOT NULL)
            );
    END IF;
END;
$$;
