-- =========================================================
-- LASSO — Migration: unified login, authors, notifications
-- Run this in Supabase's SQL Editor AFTER your initial schema
-- (lasso_db_supabase.sql) has already been applied.
-- =========================================================

-- ---------------------------------------------------------
-- 1. Administrators: add role + email (unified login uses email
--    for everyone; role distinguishes admin vs cashier)
-- ---------------------------------------------------------
ALTER TABLE administrators ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin';
ALTER TABLE administrators ADD CONSTRAINT chk_admin_role CHECK (role IN ('admin','cashier'));
ALTER TABLE administrators ADD COLUMN email VARCHAR(150);
ALTER TABLE administrators ADD CONSTRAINT uq_admin_email UNIQUE (email);

-- IMPORTANT: your existing head admin (admin001) has no email yet.
-- Replace the placeholder below with a real email BEFORE running this
-- line, so the head admin can still log in once login switches to email.
UPDATE administrators SET email = 'headadmin001@admin.com', role = 'admin' WHERE admin_id = 'admin001';

-- ---------------------------------------------------------
-- 2. Authors (normalized — reused across materials for accurate
--    "top author" ranking)
-- ---------------------------------------------------------
CREATE TABLE authors (
  id SERIAL PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
);

ALTER TABLE instructional_materials ADD COLUMN author_id INT REFERENCES authors(id);

-- ---------------------------------------------------------
-- 3. Notifications (cashier payment confirmations -> admin)
-- ---------------------------------------------------------
CREATE TABLE notifications (
  id SERIAL PRIMARY KEY,
  type VARCHAR(30) NOT NULL DEFAULT 'payment_confirmed',
  message TEXT NOT NULL,
  student_id INT REFERENCES students(id) ON DELETE SET NULL,
  billing_id INT REFERENCES billing_statements(id) ON DELETE SET NULL,
  cashier_id INT REFERENCES administrators(id) ON DELETE SET NULL,
  is_read BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_notifications_unread ON notifications (is_read, created_at DESC);
