-- DDL: buat tabel OTP reset password dengan masa berlaku dan batas percobaan.
CREATE TABLE IF NOT EXISTS password_reset_otps (
  id BIGSERIAL PRIMARY KEY,
  user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  attempts SMALLINT NOT NULL DEFAULT 0 CHECK (attempts BETWEEN 0 AND 5),
  requested_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  used_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- INDEX: percepat pencarian OTP yang mendekati atau melewati masa kedaluwarsa.
CREATE INDEX IF NOT EXISTS password_reset_otps_expiry_idx
  ON password_reset_otps(expires_at);
