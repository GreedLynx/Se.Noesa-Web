-- Tandai sumber pesanan agar pesanan yang dicatat Admin dapat dibedakan dari checkout pelanggan.
ALTER TABLE pesanan
  ADD COLUMN IF NOT EXISTS sumber VARCHAR(10) NOT NULL DEFAULT 'online';

ALTER TABLE pesanan
  DROP CONSTRAINT IF EXISTS pesanan_sumber_check,
  ADD CONSTRAINT pesanan_sumber_check CHECK (sumber IN ('online', 'offline'));
