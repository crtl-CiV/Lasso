<?php
require_once __DIR__ . '/config/bootstrap.php';
$db = getDB();
$departments = $db->query('SELECT id, name FROM college_departments ORDER BY name')->fetchAll();
$programs = $db->query('SELECT id, department_id, name FROM college_programs ORDER BY name')->fetchAll();
respond(true, ['departments' => $departments, 'programs' => $programs]);
