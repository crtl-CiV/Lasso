-- =========================================================
-- LASSO — Learning Asset Subscription and Sales Operation
-- Database Schema (MySQL / MariaDB, for XAMPP phpMyAdmin)
-- =========================================================

CREATE DATABASE IF NOT EXISTS lasso_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lasso_db;

-- ---------------------------------------------------------
-- Reference tables
-- ---------------------------------------------------------
CREATE TABLE college_departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE college_programs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  FOREIGN KEY (department_id) REFERENCES college_departments(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id_number VARCHAR(50) NOT NULL UNIQUE,
  university_email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  department_id INT NOT NULL,
  program_id INT NOT NULL,
  year_level VARCHAR(20) NOT NULL,
  section VARCHAR(20) NOT NULL,
  id_photo_front VARCHAR(255) DEFAULT NULL,
  id_photo_back VARCHAR(255) DEFAULT NULL,
  profile_photo VARCHAR(255) DEFAULT NULL,
  status ENUM('active','trashed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES college_departments(id),
  FOREIGN KEY (program_id) REFERENCES college_programs(id)
);

CREATE TABLE administrators (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  position VARCHAR(100) NOT NULL,
  status ENUM('active','trashed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- Instructional materials (the "Store")
-- ---------------------------------------------------------
CREATE TABLE instructional_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  material_code VARCHAR(30) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  department_id INT DEFAULT NULL,
  program_id INT DEFAULT NULL,
  year_level VARCHAR(20) DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  cover_image VARCHAR(255) DEFAULT NULL,
  file_path VARCHAR(255) DEFAULT NULL,          -- protected file, served only through viewer
  total_pages INT NOT NULL DEFAULT 0,
  non_body_pages JSON DEFAULT NULL,              -- pages excluded from body count (TOC, cover, etc.)
  preview_excluded_pages JSON DEFAULT NULL,       -- admin-designated pages excluded from the 5-page preview
  status ENUM('draft','published','trashed') NOT NULL DEFAULT 'draft',
  is_promoted TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES college_departments(id),
  FOREIGN KEY (program_id) REFERENCES college_programs(id),
  FOREIGN KEY (uploaded_by) REFERENCES administrators(id)
);

-- ---------------------------------------------------------
-- Cart
-- ---------------------------------------------------------
CREATE TABLE cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  material_id INT NOT NULL,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart (student_id, material_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES instructional_materials(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- Checkout: billing statement + validation code
-- ---------------------------------------------------------
CREATE TABLE billing_statements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  billing_code VARCHAR(30) NOT NULL UNIQUE,
  student_id INT NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  semester VARCHAR(20) NOT NULL,
  academic_year VARCHAR(20) NOT NULL,
  status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE billing_statement_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  billing_id INT NOT NULL,
  material_id INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (billing_id) REFERENCES billing_statements(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES instructional_materials(id)
);

CREATE TABLE validation_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  billing_id INT NOT NULL,
  student_id INT NOT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  status ENUM('awaiting_payment','ready','validated') NOT NULL DEFAULT 'awaiting_payment',
  -- awaiting_payment: not yet released by cashier | ready: cashier confirmed payment, released to student
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  validated_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (billing_id) REFERENCES billing_statements(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ---------------------------------------------------------
-- Subscriptions (the "Library")
-- ---------------------------------------------------------
CREATE TABLE subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  material_id INT NOT NULL,
  billing_id INT DEFAULT NULL,
  semester_count INT NOT NULL DEFAULT 1,     -- +1 each time the same material is re-subscribed
  start_date DATE NOT NULL,
  expiry_date DATE NOT NULL,                 -- extends by 1 semester per additional subscription
  status ENUM('active','expired') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sub (student_id, material_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES instructional_materials(id),
  FOREIGN KEY (billing_id) REFERENCES billing_statements(id)
);

-- ---------------------------------------------------------
-- Progress tracker (body pages only)
-- ---------------------------------------------------------
CREATE TABLE progress_tracker (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  material_id INT NOT NULL,
  pages_read JSON DEFAULT NULL,     -- array of body page numbers read
  last_page_read INT DEFAULT 0,
  percent_complete DECIMAL(5,2) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_progress (student_id, material_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES instructional_materials(id)
);

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

-- NOTE: The very first administrator account is NOT seeded here with a
-- fake password hash (that would be unreliable across PHP builds).
-- After importing this file, open http://localhost/lasso/setup_admin.php
-- once in your browser — it creates the default head administrator
-- (Admin ID: admin001 / Password: Admin@123) using PHP's own
-- password_hash(), then locks itself so it can't be run twice.
