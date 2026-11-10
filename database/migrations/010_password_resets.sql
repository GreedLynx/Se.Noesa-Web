CREATE TABLE IF NOT EXISTS password_resets (
  id BIGSERIAL PRIMARY KEY,
  user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  used_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS password_resets_expiry_idx
  ON password_resets(expires_at);
