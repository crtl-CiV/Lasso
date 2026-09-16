<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();
$type = $_GET['type'] ?? 'students'; // 'students' | 'admins'

if ($type === 'admins') {
    $rows = $db->query("SELECT id, admin_id, full_name, position, status, created_at FROM administrators ORDER BY created_at DESC")->fetchAll();
    respond(true, ['admins' => $rows]);
}

$rows = $db->query("SELECT s.id, s.student_id_number, s.university_email, s.full_name, s.year_level, s.section, s.status, s.created_at,
                            d.name AS department_name, p.name AS program_name,
                            (SELECT COUNT(*) FROM subscriptions sub WHERE sub.student_id = s.id AND sub.status='active') AS active_subscriptions
                     FROM students s
                     LEFT JOIN college_departments d ON d.id = s.department_id
                     LEFT JOIN college_programs p ON p.id = s.program_id
                     ORDER BY s.created_at DESC")->fetchAll();
respond(true, ['students' => $rows]);
