<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$status = $_GET['status'] ?? 'all'; // 'published' | 'draft' | 'trashed' | 'all'
$sql = "SELECT m.*, d.name AS department_name, p.name AS program_name,
               (SELECT COUNT(*) FROM subscriptions s WHERE s.material_id = m.id AND s.status='active') AS subscriber_count
        FROM instructional_materials m
        LEFT JOIN college_departments d ON d.id = m.department_id
        LEFT JOIN college_programs p ON p.id = m.program_id";
$params = [];
if ($status !== 'all') { $sql .= " WHERE m.status = ?"; $params[] = $status; }
$sql .= " ORDER BY m.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
respond(true, ['materials' => $stmt->fetchAll()]);
