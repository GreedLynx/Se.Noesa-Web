-- DDL: tambah batas minimum pesanan produk dengan nilai awal 1.
ALTER TABLE produk
  ADD COLUMN IF NOT EXISTS min_pesan INT NOT NULL DEFAULT 1 CHECK (min_pesan >= 1);

-- Set a password only for the untouched sample customer placeholder.
-- UPDATE khusus akun contoh yang password-nya masih berupa placeholder.
UPDATE users
SET password_hash = '$2y$12$2tI1xZ7Et3mnGlF7CfqK5uvdpRWDcSbzBh/XOZBTauaBuxZyuqNce'
WHERE email = 'andi@mail.com' AND role = 'pelanggan' AND password_hash = '-';
