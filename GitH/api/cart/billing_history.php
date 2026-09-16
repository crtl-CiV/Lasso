<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$db = getDB();

$stmt = $db->prepare("SELECT b.id, b.billing_code, b.total_amount, b.semester, b.academic_year, b.status, b.created_at,
                              v.status AS code_status
                       FROM billing_statements b
                       LEFT JOIN validation_codes v ON v.billing_id = b.id
                       WHERE b.student_id = ?
                       ORDER BY b.created_at DESC");
$stmt->execute([$user['id']]);
$billings = $stmt->fetchAll();

$itemStmt = $db->prepare("SELECT m.title, m.material_code, bi.price
                           FROM billing_statement_items bi
                           JOIN instructional_materials m ON m.id = bi.material_id
                           WHERE bi.billing_id = ?");
foreach ($billings as &$b) {
    $itemStmt->execute([$b['id']]);
    $b['items'] = $itemStmt->fetchAll();
}
unset($b);

respond(true, [
    'student_name' => $user['full_name'],
    'student_id_number' => $user['student_id_number'],
    'billings' => $billings,
]);
