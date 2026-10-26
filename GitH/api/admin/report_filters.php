<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

// Always offer both semesters, plus any other term that already exists in billing history (e.g. an old Summer Term)
$fromData = $db->query("SELECT DISTINCT semester FROM billing_statements ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);
$semesters = array_values(array_unique(array_merge(['1st Semester', '2nd Semester'], $fromData)));
$academicYears = $db->query("SELECT DISTINCT academic_year FROM billing_statements ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);

respond(true, ['semesters' => $semesters, 'academic_years' => $academicYears]);
