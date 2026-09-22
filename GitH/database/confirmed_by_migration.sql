-- =========================================================
-- LASSO — track who confirmed each payment, and actually mark
-- billing statements as paid (this was previously missing, so
-- confirmed billings never left the pending list).
-- Run this in Supabase's SQL Editor.
-- =========================================================
ALTER TABLE billing_statements ADD COLUMN confirmed_by INT REFERENCES administrators(id);
ALTER TABLE billing_statements ADD COLUMN confirmed_at TIMESTAMPTZ;
