ALTER TABLE pesanan DROP CONSTRAINT IF EXISTS pesanan_status_check;
ALTER TABLE pesanan ALTER COLUMN status TYPE VARCHAR(24);
ALTER TABLE pesanan ALTER COLUMN status SET DEFAULT 'menunggu_pembayaran';
ALTER TABLE pesanan ADD CONSTRAINT pesanan_status_check CHECK (
  status IN ('menunggu_pembayaran','menunggu_validasi','diproses','selesai','ditolak')
);
