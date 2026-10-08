-- =========================================================
-- LASSO — Client feedback migration
--   1. Year-level ranges on materials
--   2. Year-level change requests (student -> admin approval, with ID proof)
--   3. Remove the cashier role and the notifications feature
-- Run ONCE in Supabase's SQL Editor (new query tab), whole file at once.
-- Requires the earlier unify_accounts migration to have run (it already has).
-- The confirmed_by columns are added here too (IF NOT EXISTS), so it is safe
-- whether or not you ran confirmed_by_migration.sql before.
-- =========================================================

ALTER TABLE billing_statements ADD COLUMN IF NOT EXISTS confirmed_by INT REFERENCES administrators(id);
ALTER TABLE billing_statements ADD COLUMN IF NOT EXISTS confirmed_at TIMESTAMPTZ;

-- ---------------------------------------------------------
-- 1. Material year levels: lowest and highest year the material is for.
--    Existing materials are set to 1st-5th Year (visible to everyone) so
--    nothing disappears; edit them from Materials -> Edit afterwards.
-- ---------------------------------------------------------
ALTER TABLE instructional_materials ADD COLUMN year_min SMALLINT;
ALTER TABLE instructional_materials ADD COLUMN year_max SMALLINT;
UPDATE instructional_materials SET year_min = 1, year_max = 5, year_level = '1st - 5th Year';
ALTER TABLE instructional_materials ALTER COLUMN year_min SET NOT NULL;
ALTER TABLE instructional_materials ALTER COLUMN year_max SET NOT NULL;
ALTER TABLE instructional_materials ADD CONSTRAINT chk_material_years CHECK (year_min BETWEEN 1 AND 5 AND year_max BETWEEN year_min AND 5);

-- ---------------------------------------------------------
-- 2. Year-level change requests
-- ---------------------------------------------------------
CREATE TABLE year_level_requests (
  id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  current_year_level VARCHAR(20) NOT NULL,
  requested_year_level VARCHAR(20) NOT NULL,
  id_photo VARCHAR(255) NOT NULL,          -- private bucket path (lasso-materials/year-proofs/...)
  status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
  review_note TEXT,
  reviewed_by INT REFERENCES administrators(id) ON DELETE SET NULL,
  reviewed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_year_requests_status ON year_level_requests (status, created_at DESC);

-- ---------------------------------------------------------
-- 3. Remove the cashier role + notifications
-- ---------------------------------------------------------
DROP TABLE IF EXISTS notifications;

-- Payments a cashier confirmed keep their history, just without the confirmer's name
UPDATE billing_statements SET confirmed_by = NULL
WHERE confirmed_by IN (SELECT id FROM administrators WHERE role = 'cashier');

DELETE FROM administrators WHERE role = 'cashier';

ALTER TABLE administrators DROP CONSTRAINT chk_admin_role;
ALTER TABLE administrators ADD CONSTRAINT chk_admin_role CHECK (role = 'admin');
