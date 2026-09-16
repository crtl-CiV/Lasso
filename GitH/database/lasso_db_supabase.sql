-- =========================================================
-- LASSO — Learning Asset Subscription and Sales Operation
-- Database Schema (PostgreSQL / Supabase)
-- Converted from the original MySQL/MariaDB (XAMPP) version
-- =========================================================

-- ---------------------------------------------------------
-- Enum types
-- (Postgres has no inline ENUM like MySQL — each distinct
-- set of values gets its own named type)
-- ---------------------------------------------------------
CREATE TYPE account_status AS ENUM ('active', 'trashed');
CREATE TYPE material_status AS ENUM ('draft', 'published', 'trashed');
CREATE TYPE billing_status AS ENUM ('pending', 'paid');
CREATE TYPE validation_status AS ENUM ('awaiting_payment', 'ready', 'validated');
CREATE TYPE subscription_status AS ENUM ('active', 'expired');

-- ---------------------------------------------------------
-- Reference tables
-- ---------------------------------------------------------
CREATE TABLE college_departments (
  id SERIAL PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE college_programs (
  id SERIAL PRIMARY KEY,
  department_id INT NOT NULL REFERENCES college_departments(id) ON DELETE CASCADE,
  name VARCHAR(150) NOT NULL
);

-- ---------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------
CREATE TABLE students (
  id SERIAL PRIMARY KEY,
  student_id_number VARCHAR(50) NOT NULL UNIQUE,
  university_email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  department_id INT NOT NULL REFERENCES college_departments(id),
  program_id INT NOT NULL REFERENCES college_programs(id),
  year_level VARCHAR(20) NOT NULL,
  section VARCHAR(20) NOT NULL,
  id_photo_front VARCHAR(255) DEFAULT NULL,
  id_photo_back VARCHAR(255) DEFAULT NULL,
  profile_photo VARCHAR(255) DEFAULT NULL,
  status account_status NOT NULL DEFAULT 'active',
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE administrators (
  id SERIAL PRIMARY KEY,
  admin_id VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  position VARCHAR(100) NOT NULL,
  status account_status NOT NULL DEFAULT 'active',
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ---------------------------------------------------------
-- Instructional materials (the "Store")
-- ---------------------------------------------------------
CREATE TABLE instructional_materials (
  id SERIAL PRIMARY KEY,
  material_code VARCHAR(30) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  department_id INT DEFAULT NULL REFERENCES college_departments(id),
  program_id INT DEFAULT NULL REFERENCES college_programs(id),
  year_level VARCHAR(20) DEFAULT NULL,
  price NUMERIC(10,2) NOT NULL DEFAULT 0,
  cover_image VARCHAR(255) DEFAULT NULL,
  file_path VARCHAR(255) DEFAULT NULL,             -- protected file, served only through viewer
  total_pages INT NOT NULL DEFAULT 0,
  non_body_pages JSONB DEFAULT NULL,               -- pages excluded from body count (TOC, cover, etc.)
  preview_excluded_pages JSONB DEFAULT NULL,        -- admin-designated pages excluded from the 5-page preview
  status material_status NOT NULL DEFAULT 'draft',
  is_promoted BOOLEAN NOT NULL DEFAULT FALSE,
  uploaded_by INT DEFAULT NULL REFERENCES administrators(id),
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- ---------------------------------------------------------
-- Cart
-- ---------------------------------------------------------
CREATE TABLE cart_items (
  id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  material_id INT NOT NULL REFERENCES instructional_materials(id) ON DELETE CASCADE,
  added_at TIMESTAMPTZ DEFAULT NOW(),
  CONSTRAINT uq_cart UNIQUE (student_id, material_id)
);

-- ---------------------------------------------------------
-- Checkout: billing statement + validation code
-- ---------------------------------------------------------
CREATE TABLE billing_statements (
  id SERIAL PRIMARY KEY,
  billing_code VARCHAR(30) NOT NULL UNIQUE,
  student_id INT NOT NULL REFERENCES students(id),
  total_amount NUMERIC(10,2) NOT NULL,
  semester VARCHAR(20) NOT NULL,
  academic_year VARCHAR(20) NOT NULL,
  status billing_status NOT NULL DEFAULT 'pending',
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE billing_statement_items (
  id SERIAL PRIMARY KEY,
  billing_id INT NOT NULL REFERENCES billing_statements(id) ON DELETE CASCADE,
  material_id INT NOT NULL REFERENCES instructional_materials(id),
  price NUMERIC(10,2) NOT NULL
);

CREATE TABLE validation_codes (
  id SERIAL PRIMARY KEY,
  billing_id INT NOT NULL REFERENCES billing_statements(id) ON DELETE CASCADE,
  student_id INT NOT NULL REFERENCES students(id),
  code VARCHAR(20) NOT NULL UNIQUE,
  status validation_status NOT NULL DEFAULT 'awaiting_payment',
  -- awaiting_payment: not yet released by cashier | ready: cashier confirmed payment, released to student
  created_at TIMESTAMPTZ DEFAULT NOW(),
  validated_at TIMESTAMPTZ NULL DEFAULT NULL
);

-- ---------------------------------------------------------
-- Subscriptions (the "Library")
-- ---------------------------------------------------------
CREATE TABLE subscriptions (
  id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  material_id INT NOT NULL REFERENCES instructional_materials(id),
  billing_id INT DEFAULT NULL REFERENCES billing_statements(id),
  semester_count INT NOT NULL DEFAULT 1,      -- +1 each time the same material is re-subscribed
  start_date DATE NOT NULL,
  expiry_date DATE NOT NULL,                  -- extends by 1 semester per additional subscription
  status subscription_status NOT NULL DEFAULT 'active',
  created_at TIMESTAMPTZ DEFAULT NOW(),
  CONSTRAINT uq_sub UNIQUE (student_id, material_id)
);

-- ---------------------------------------------------------
-- Progress tracker (body pages only)
-- ---------------------------------------------------------
CREATE TABLE progress_tracker (
  id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  material_id INT NOT NULL REFERENCES instructional_materials(id),
  pages_read JSONB DEFAULT NULL,      -- array of body page numbers read
  last_page_read INT DEFAULT 0,
  percent_complete NUMERIC(5,2) NOT NULL DEFAULT 0,
  updated_at TIMESTAMPTZ DEFAULT NOW(),
  CONSTRAINT uq_progress UNIQUE (student_id, material_id)
);

-- ---------------------------------------------------------
-- Auto-update "updated_at" columns
-- (MySQL's ON UPDATE CURRENT_TIMESTAMP has no direct Postgres
-- equivalent — this trigger function replicates it)
-- ---------------------------------------------------------
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_materials_updated_at
BEFORE UPDATE ON instructional_materials
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_progress_updated_at
BEFORE UPDATE ON progress_tracker
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- =========================================================
-- Seed data
-- =========================================================
INSERT INTO college_departments (name) VALUES
 ('College of Information Technology'),
 ('College of Engineering'),
 ('College of Business Administration');

INSERT INTO college_programs (department_id, name) VALUES
 (1, 'BS Information Technology'),
 (1, 'BS Computer Science'),
 (2, 'BS Civil Engineering'),
 (3, 'BS Accountancy');

-- NOTE: The first administrator account is intentionally not seeded
-- here with a placeholder password hash. After importing this schema
-- into Supabase, create the default head administrator account
-- (Admin ID: admin001) through your app's own password-hashing logic
-- (e.g. PHP's password_hash(), or whatever now calls the Supabase
-- API) rather than via raw SQL, so the stored hash matches what your
-- app can actually verify.
