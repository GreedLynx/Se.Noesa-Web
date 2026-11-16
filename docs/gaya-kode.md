# Gaya Kode

Panduan ini melengkapi pola yang sudah ada di proyek. Ikuti pola file di sekitar
perubahan bila berbeda, kecuali aturan keamanan dan bisnis di bawah.

## Umum

- Gunakan indentasi 4 spasi; jangan mencampur tab dan spasi.
- Gunakan nama yang menjelaskan peran data, bukan singkatan yang tidak jelas.
- Istilah domain memakai bahasa proyek: `porsi`, `min_pesan`, dan `kapasitas`.
  Jangan gunakan pax, box, paket, botol, atau pcs.
- Hindari kode, opsi konfigurasi, dan abstraksi yang belum dipakai.

## PHP dan database

- Ikuti gaya PHP file terdekat; gunakan `snake_case` untuk fungsi bila itu pola
  yang berlaku, dan `UPPER_SNAKE_CASE` untuk konstanta.
- Letakkan blok dan kondisi pada baris yang mudah dibaca; gunakan 4 spasi untuk
  isi blok.
- Selalu gunakan PDO prepared statement. Input pengguna tidak boleh digabungkan
  ke string SQL.
- Tulis kata kunci SQL dengan huruf kapital dan gunakan nama tabel/kolom sesuai
  skema (`snake_case`).
- Validasi input dan otorisasi di server. Endpoint role harus memanggil
  `require_role()` atau `require_owner()` sesuai aksesnya.

## API JSON

- Gunakan status HTTP yang sesuai: `200` untuk baca/berhasil, `201` untuk data
  baru, `422` untuk input tidak valid, dan `403` untuk akses ditolak.
- Pertahankan bentuk respons endpoint yang telah ada. Endpoint baru memakai
  `{ "ok": true, "data": ... }` saat berhasil atau `{ "error": "..." }`
  saat gagal, kecuali kontrak fitur menetapkan bentuk lain.
- Pesan kesalahan harus aman ditampilkan pengguna dan tidak membocorkan detail
  database atau server.

## HTML dan Bootstrap

- Gunakan elemen semantik seperti `main`, `nav`, `section`, dan `button` sesuai
  fungsinya.
- Setiap input memiliki `label` yang terhubung melalui atribut `for` dan `id`.
- Gunakan grid dan komponen Bootstrap untuk layout, formulir, tombol, navbar,
  dan modal. CSS Se.Noesa ditempatkan di `public/assets/css/` sebagai override.
- Jangan memakai `!important` kecuali diperlukan untuk mengatasi batasan yang
  jelas dari komponen Bootstrap; beri komentar singkat alasannya.

## CSS

- Gunakan variabel CSS untuk warna, ukuran, dan jarak yang dipakai ulang.
- Nama class memakai `kebab-case`, misalnya `.order-card` dan `.status-badge`.
- Hindari selector yang terlalu dalam; buat selector spesifik secukupnya.
- Warna dan tampilan tetap mengikuti identitas sage dan coklat kayu Se.Noesa.

## JavaScript

- Gunakan `const` secara default dan `let` hanya untuk nilai yang berubah.
- Gunakan `camelCase` untuk variabel dan fungsi.
- Pisahkan handler event dan logika pengolahan data dari atribut event inline di
  HTML.
- Validasi frontend membantu pengalaman pengguna, tetapi tidak menggantikan
  validasi server.

## Dokumentasi dan commit

- Perubahan endpoint, basis data, atau lokasi kode harus memperbarui
  `docs/api.md`, `docs/database.md`, atau `docs/peta-kode.md` yang relevan.
- Gunakan Conventional Commits: `feat:`, `fix:`, `refactor:`, `docs:`, atau
  `chore:`.
- Jangan memasukkan `.env`, kredensial, atau unggahan pengguna ke commit.
