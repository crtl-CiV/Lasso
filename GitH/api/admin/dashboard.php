<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$stats = [
    'total_students' => (int)$db->query("SELECT COUNT(*) c FROM students WHERE status='active'")->fetch()['c'],
    'total_materials' => (int)$db->query("SELECT COUNT(*) c FROM instructional_materials WHERE status='published'")->fetch()['c'],
    'total_active_subscriptions' => (int)$db->query("SELECT COUNT(*) c FROM subscriptions WHERE status='active'")->fetch()['c'],
    'total_sales' => (float)$db->query("SELECT COALESCE(SUM(total_amount),0) s FROM billing_statements WHERE status='paid'")->fetch()['s'],
    'pending_payments' => (int)$db->query("SELECT COUNT(*) c FROM billing_statements WHERE status='pending'")->fetch()['c'],
];

$topMaterials = $db->query("SELECT m.title, COUNT(*) AS subscribers
                             FROM subscriptions s JOIN instructional_materials m ON m.id = s.material_id
                             WHERE s.status='active'
                             GROUP BY m.id ORDER BY subscribers DESC LIMIT 5")->fetchAll();

respond(true, ['stats' => $stats, 'top_materials' => $topMaterials]);
