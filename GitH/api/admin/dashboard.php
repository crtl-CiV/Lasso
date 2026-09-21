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

// Top author by number of CURRENTLY active subscriptions to their materials.
// "Currently active" = status='active' AND not yet past its expiry date, so
// this count (and the matching figure in the sales report) drops on its own
// as subscriptions expire, rather than being a permanent all-time tally.
$topAuthors = $db->query("SELECT au.name, COUNT(*) AS active_subscriptions
                           FROM subscriptions s
                           JOIN instructional_materials m ON m.id = s.material_id
                           JOIN authors au ON au.id = m.author_id
                           WHERE s.status = 'active' AND s.expiry_date >= CURRENT_DATE
                           GROUP BY au.id ORDER BY active_subscriptions DESC LIMIT 5")->fetchAll();

respond(true, ['stats' => $stats, 'top_materials' => $topMaterials, 'top_authors' => $topAuthors]);
