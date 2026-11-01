ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS status_updated_at TIMESTAMPTZ;
ALTER TABLE pesanan ALTER COLUMN status_updated_at SET DEFAULT now();
