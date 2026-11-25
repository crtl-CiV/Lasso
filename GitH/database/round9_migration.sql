-- Round 9: (1) which courses may see each material, (2) one active login per account.
-- Safe to run more than once. Deletes no data.

-- 1) Courses a material is visible to. A material with NO rows here is visible to every course,
--    so every existing material stays visible to everyone after this update.
CREATE TABLE IF NOT EXISTS material_programs (
  material_id INT NOT NULL REFERENCES instructional_materials(id) ON DELETE CASCADE,
  program_id  INT NOT NULL REFERENCES college_programs(id) ON DELETE CASCADE,
  PRIMARY KEY (material_id, program_id)
);
CREATE INDEX IF NOT EXISTS idx_material_programs_program ON material_programs (program_id);
ALTER TABLE material_programs ENABLE ROW LEVEL SECURITY;  -- blocks the public Supabase API; LASSO's server login is unaffected

-- 2) The token of the newest login. A browser whose token no longer matches is logged out.
ALTER TABLE students       ADD COLUMN IF NOT EXISTS session_token VARCHAR(64);
ALTER TABLE administrators ADD COLUMN IF NOT EXISTS session_token VARCHAR(64);
