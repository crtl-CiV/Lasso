-- =========================================================
-- LASSO — Round 4 migration (new UI)
--   * optional Subject code on materials (e.g. "IT 108")
--   * second ID photo (back) on year-level change requests
-- Run ONCE in Supabase's SQL Editor (new query tab), whole file at once.
-- Requires round3_migration.sql.
-- =========================================================
ALTER TABLE instructional_materials ADD COLUMN subject_code VARCHAR(30);
ALTER TABLE year_level_requests ADD COLUMN id_photo_back VARCHAR(255);   -- private bucket path; older requests have only the front photo
