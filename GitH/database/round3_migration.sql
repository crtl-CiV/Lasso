-- =========================================================
-- LASSO — Round 3 migration
--   1. Departments become CCI, CAS, COE, CIT, CEA
--   2. Materials are tagged with year level(s) and semester(s) (multi-select)
-- Run ONCE in Supabase's SQL Editor (new query tab), whole file at once.
-- Requires client_feedback_migration.sql and receipt_flow_migration.sql.
-- =========================================================

-- ---------------------------------------------------------
-- 1. Departments (matched by name, so it is safe to re-run).
--    Existing departments keep their id, students and courses:
--      College of Information Technology -> CCI
--      College of Engineering            -> CEA
--      College of Business Administration -> CAS   (placeholder: its course
--        "BS Accountancy" stays attached; reassign it if that is wrong)
-- ---------------------------------------------------------
UPDATE college_departments SET name = 'CCI' WHERE name = 'College of Information Technology';
UPDATE college_departments SET name = 'CEA' WHERE name = 'College of Engineering';
UPDATE college_departments SET name = 'CAS' WHERE name = 'College of Business Administration';
INSERT INTO college_departments (name) VALUES ('CCI'), ('CAS'), ('COE'), ('CIT'), ('CEA')
ON CONFLICT (name) DO NOTHING;

-- ---------------------------------------------------------
-- 2. Year + semester tags.
--    year_mask:     bit 1 = 1st year ... bit 5 = 5th year   (31 = every year)
--    semester_mask: bit 1 = 1st semester, bit 2 = 2nd       (3  = both)
--    Existing materials keep the year range they had; they are tagged for
--    BOTH semesters so nothing disappears. Re-tag them from Materials -> Edit.
-- ---------------------------------------------------------
ALTER TABLE instructional_materials ADD COLUMN year_mask SMALLINT;
ALTER TABLE instructional_materials ADD COLUMN semester_mask SMALLINT;

UPDATE instructional_materials
SET year_mask = (((1 << year_max::int) - 1) & ~((1 << (year_min::int - 1)) - 1)),
    semester_mask = 3;

ALTER TABLE instructional_materials ALTER COLUMN year_mask SET NOT NULL;
ALTER TABLE instructional_materials ALTER COLUMN semester_mask SET NOT NULL;
ALTER TABLE instructional_materials ADD CONSTRAINT chk_year_mask CHECK (year_mask BETWEEN 1 AND 31);
ALTER TABLE instructional_materials ADD CONSTRAINT chk_semester_mask CHECK (semester_mask BETWEEN 1 AND 3);

-- readable label kept in year_level, e.g. "1st, 3rd Year" or "All Years"
UPDATE instructional_materials SET year_level = CASE WHEN year_mask = 31 THEN 'All Years' ELSE
  (SELECT string_agg(CASE y WHEN 1 THEN '1st' WHEN 2 THEN '2nd' WHEN 3 THEN '3rd' WHEN 4 THEN '4th' ELSE '5th' END, ', ' ORDER BY y)
     FROM generate_series(1, 5) AS y WHERE (year_mask & (1 << (y - 1))) <> 0) || ' Year' END;

ALTER TABLE instructional_materials DROP CONSTRAINT chk_material_years;
ALTER TABLE instructional_materials DROP COLUMN year_min;
ALTER TABLE instructional_materials DROP COLUMN year_max;
