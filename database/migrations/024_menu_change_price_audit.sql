-- DDL: catat harga katalog saat konfirmasi penggantian menu dan selisihnya
-- terhadap snapshot pelanggan. Total pesanan tetap memakai harga snapshot;
-- nilai ini hanya audit dan tidak mengubah alur pembayaran.
ALTER TABLE pemberitahuan_perubahan_menu_pesanan
    ADD COLUMN IF NOT EXISTS harga_katalog_saat_konfirmasi INT;

ALTER TABLE pemberitahuan_perubahan_menu_pesanan
    ADD COLUMN IF NOT EXISTS perbedaan_harga_katalog BIGINT;

ALTER TABLE pemberitahuan_perubahan_menu_pesanan
    DROP CONSTRAINT IF EXISTS pemberitahuan_menu_pesanan_catalog_price_check;

ALTER TABLE pemberitahuan_perubahan_menu_pesanan
    ADD CONSTRAINT pemberitahuan_menu_pesanan_catalog_price_check CHECK (
        (harga_katalog_saat_konfirmasi IS NULL) = (perbedaan_harga_katalog IS NULL)
        AND (harga_katalog_saat_konfirmasi IS NULL OR harga_katalog_saat_konfirmasi >= 0)
    );
