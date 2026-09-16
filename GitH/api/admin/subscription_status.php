<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$studentId = (int)($_GET['student_id'] ?? 0);
if ($studentId) {
    $stmt = $db->prepare("SELECT m.title, m.material_code, s.semester_count, s.start_date, s.expiry_date, s.status
                           FROM subscriptions s JOIN instructional_materials m ON m.id = s.material_id
                           WHERE s.student_id = ? ORDER BY s.created_at DESC");
    $stmt->execute([$studentId]);
    respond(true, ['subscriptions' => $stmt->fetchAll()]);
}

// Per-material subscriber counts, institution-wide overview
$rows = $db->query("SELECT m.id, m.material_code, m.title,
                            SUM(CASE WHEN s.status='active' THEN 1 ELSE 0 END) AS active_subscribers,
                            COUNT(s.id) AS total_ever_subscribed
                     FROM instructional_materials m
                     LEFT JOIN subscriptions s ON s.material_id = m.id
                     WHERE m.status='published'
                     GROUP BY m.id ORDER BY active_subscribers DESC")->fetchAll();
respond(true, ['materials' => $rows]);
