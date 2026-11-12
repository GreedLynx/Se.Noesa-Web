CREATE TABLE tanggal_libur_operasional (
    id SERIAL PRIMARY KEY,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    CHECK (tanggal_selesai >= tanggal_mulai)
);
