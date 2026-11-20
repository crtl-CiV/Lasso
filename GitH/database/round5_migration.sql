-- =========================================================
-- LASSO — Round 5 migration (emailer)
--   * password_resets: one-time 6-digit codes for "Forgot password"
--     (only a hash of the code is stored, never the code itself)
--   * subscriptions.reminder_sent_for: remembers which expiry date
--     a 7-day reminder was already emailed for, so nobody gets the
--     same reminder twice (a renewal changes the date and resets it)
-- Run ONCE in Supabase's SQL Editor (new query tab), whole file at once.
-- Requires round4_migration.sql.
-- =========================================================
CREATE TABLE password_resets (
  id SERIAL PRIMARY KEY,
  user_type VARCHAR(10) NOT NULL CHECK (user_type IN ('student','admin')),
  user_id INT NOT NULL,
  email VARCHAR(150) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,          -- wrong guesses; the code dies at 5
  expires_at TIMESTAMPTZ NOT NULL,          -- 10 minutes after it was sent
  used_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_password_resets_email ON password_resets (email, created_at DESC);

ALTER TABLE subscriptions ADD COLUMN reminder_sent_for DATE;
