-- =========================================================
-- LASSO — Round 7 migration (speed + admin email codes)
--   * Indexes for the lookups LASSO runs most often, so pages stay
--     fast as students, billings and subscriptions pile up
--   * email_verifications.purpose: the same 6-digit code table now
--     also verifies the email of a new administrator account
-- Run ONCE in Supabase's SQL Editor (new query tab), whole file at once.
-- Requires round6_migration.sql. Safe to run on a live database.
-- =========================================================

-- Case-insensitive lookups (login, forgot password, registration, OR-number duplicate check)
CREATE INDEX IF NOT EXISTS idx_students_email_lower ON students (LOWER(university_email));
CREATE INDEX IF NOT EXISTS idx_admins_email_lower   ON administrators (LOWER(email));
CREATE INDEX IF NOT EXISTS idx_billing_or_lower     ON billing_statements (LOWER(receipt_number));

-- Billing and subscription joins (cart, Sales page, reports, approvals)
CREATE INDEX IF NOT EXISTS idx_billing_student_status ON billing_statements (student_id, status);
CREATE INDEX IF NOT EXISTS idx_billing_items_billing  ON billing_statement_items (billing_id);
CREATE INDEX IF NOT EXISTS idx_billing_items_material ON billing_statement_items (material_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_material ON subscriptions (material_id, status, expiry_date);
CREATE INDEX IF NOT EXISTS idx_subscriptions_expiry   ON subscriptions (status, expiry_date);
CREATE INDEX IF NOT EXISTS idx_materials_status       ON instructional_materials (status);

-- Email codes: 'register' = student sign-up, 'admin' = new administrator account
ALTER TABLE email_verifications ADD COLUMN IF NOT EXISTS purpose VARCHAR(20) NOT NULL DEFAULT 'register';
DROP INDEX IF EXISTS idx_email_verifications_email;
CREATE INDEX IF NOT EXISTS idx_email_verifications_lookup ON email_verifications (email, purpose, created_at DESC);
