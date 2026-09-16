<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$db = getDB();

$stmt = $db->prepare("SELECT s.id AS subscription_id, m.id AS material_id, m.material_code, m.title, m.cover_image,
                              m.total_pages, s.semester_count, s.start_date, s.expiry_date, s.status,
                              COALESCE(pt.percent_complete, 0) AS percent_complete,
                              COALESCE(pt.last_page_read, 0) AS last_page_read
                       FROM subscriptions s
                       JOIN instructional_materials m ON m.id = s.material_id
                       LEFT JOIN progress_tracker pt ON pt.student_id = s.student_id AND pt.material_id = s.material_id
                       WHERE s.student_id = ?
                       ORDER BY s.created_at DESC");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

// auto-expire
$today = date('Y-m-d');
foreach ($rows as &$r) {
    if ($r['status'] === 'active' && $r['expiry_date'] < $today) {
        $r['status'] = 'expired';
        $db->prepare("UPDATE subscriptions SET status='expired' WHERE id=?")->execute([$r['subscription_id']]);
    }
}

respond(true, ['library' => $rows]);
