ALTER TABLE produk
  ADD COLUMN IF NOT EXISTS min_pesan INT NOT NULL DEFAULT 1 CHECK (min_pesan >= 1);

-- Set a password only for the untouched sample customer placeholder.
UPDATE users
SET password_hash = '$2y$12$2tI1xZ7Et3mnGlF7CfqK5uvdpRWDcSbzBh/XOZBTauaBuxZyuqNce'
WHERE email = 'andi@mail.com' AND role = 'pelanggan' AND password_hash = '-';
