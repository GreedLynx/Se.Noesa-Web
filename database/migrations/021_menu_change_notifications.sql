CREATE TABLE IF NOT EXISTS pemberitahuan_perubahan_menu (
    id SERIAL PRIMARY KEY,
    produk_id INT REFERENCES produk(id) ON DELETE SET NULL,
    kode_produk_snapshot VARCHAR(10) NOT NULL,
    nama_produk_snapshot VARCHAR(100) NOT NULL,
    catatan_admin TEXT NOT NULL CHECK (length(btrim(catatan_admin)) > 0),
    created_by INT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS pemberitahuan_perubahan_menu_opsi (
    id SERIAL PRIMARY KEY,
    pemberitahuan_id INT NOT NULL REFERENCES pemberitahuan_perubahan_menu(id) ON DELETE RESTRICT,
    kode_opsi VARCHAR(30) NOT NULL CHECK (kode_opsi IN ('menu_pengganti', 'hubungi_admin', 'tunggu_informasi')),
    label VARCHAR(100) NOT NULL CHECK (length(btrim(label)) > 0),
    urutan SMALLINT NOT NULL DEFAULT 0 CHECK (urutan >= 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (pemberitahuan_id, kode_opsi),
    UNIQUE (pemberitahuan_id, id)
);

CREATE TABLE IF NOT EXISTS pemberitahuan_perubahan_menu_pesanan (
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
    CONSTRAINT pemberitahuan_menu_pesanan_unique_detail UNIQUE (pemberitahuan_id, detail_pesanan_id),
    CONSTRAINT pemberitahuan_menu_pesanan_option_fk
        FOREIGN KEY (pemberitahuan_id, opsi_id)
        REFERENCES pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, id)
        ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS pemberitahuan_perubahan_menu_created_idx
    ON pemberitahuan_perubahan_menu (created_at DESC);
CREATE INDEX IF NOT EXISTS pemberitahuan_perubahan_menu_produk_created_idx
    ON pemberitahuan_perubahan_menu (produk_id, created_at DESC);
CREATE INDEX IF NOT EXISTS pemberitahuan_perubahan_menu_opsi_order_idx
    ON pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, urutan, id);
CREATE INDEX IF NOT EXISTS pemberitahuan_perubahan_menu_pesanan_detail_idx
    ON pemberitahuan_perubahan_menu_pesanan (detail_pesanan_id);
CREATE INDEX IF NOT EXISTS pemberitahuan_perubahan_menu_pesanan_status_idx
    ON pemberitahuan_perubahan_menu_pesanan (status_respons, created_at DESC);
