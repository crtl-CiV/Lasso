<?php
// Everything an admin needs to validate one billing's receipt, looked up by billing code.
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$stmt = $db->prepare("SELECT b.id, b.billing_code, b.total_amount, b.semester, b.academic_year, b.status,
                              b.receipt_status, b.receipt_number, b.receipt_note, b.receipt_submitted_at,
                              s.full_name AS student_name, s.student_id_number
                       FROM billing_statements b
                       JOIN students s ON s.id = b.student_id
                       WHERE b.billing_code = ?");
$stmt->execute([trim($_GET['code'] ?? '')]);
$b = $stmt->fetch();
if (!$b) respond(false, 'No billing statement found with that code.', 404);

$stmt = $db->prepare("SELECT m.title, m.material_code, bi.price
                       FROM billing_statement_items bi
                       JOIN instructional_materials m ON m.id = bi.material_id
                       WHERE bi.billing_id = ?");
$stmt->execute([$b['id']]);
$b['items'] = $stmt->fetchAll();

respond(true, ['billing' => $b]);
