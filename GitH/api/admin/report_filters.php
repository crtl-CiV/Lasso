<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$semesters = $db->query("SELECT DISTINCT semester FROM billing_statements ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);
$academicYears = $db->query("SELECT DISTINCT academic_year FROM billing_statements ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);

respond(true, ['semesters' => $semesters, 'academic_years' => $academicYears]);
