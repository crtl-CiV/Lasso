<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$db = getDB();

$stmt = $db->prepare('SELECT year_level FROM students WHERE id = ?');
$stmt->execute([$user['id']]);
$current = (string)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT requested_year_level, status, review_note, created_at, reviewed_at
                       FROM year_level_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user['id']]);

respond(true, ['current_year_level' => $current, 'latest_request' => $stmt->fetch() ?: null]);
