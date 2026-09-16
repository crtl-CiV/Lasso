<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$db = getDB();

$stmt = $db->prepare("SELECT b.id, b.billing_code, b.total_amount, b.status AS billing_status,
                              v.status AS code_status, v.code, b.created_at
                       FROM billing_statements b
                       JOIN validation_codes v ON v.billing_id = b.id
                       WHERE b.student_id = ? AND v.status != 'validated'
                       ORDER BY b.created_at DESC");
$stmt->execute([$user['id']]);
$pending = $stmt->fetchAll();
// Only reveal the code once the cashier has actually confirmed payment.
foreach ($pending as &$p) {
    if ($p['code_status'] !== 'ready') $p['code'] = null;
}
unset($p);

$stmt = $db->prepare("SELECT s.id, m.title, m.material_code, s.semester_count, s.start_date, s.expiry_date, s.status
                       FROM subscriptions s JOIN instructional_materials m ON m.id = s.material_id
                       WHERE s.student_id = ? ORDER BY s.created_at DESC");
$stmt->execute([$user['id']]);
$subs = $stmt->fetchAll();

respond(true, ['pending_billings' => $pending, 'subscriptions' => $subs]);
