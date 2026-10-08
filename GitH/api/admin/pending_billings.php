<?php
// Billings still waiting for access. Ones with a submitted receipt come first.
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$rows = $db->query("SELECT b.id, b.billing_code, b.total_amount, b.semester, b.academic_year, b.created_at,
                            b.receipt_status, b.receipt_number, b.receipt_submitted_at,
                            s.full_name, s.student_id_number
                     FROM billing_statements b
                     JOIN students s ON s.id = b.student_id
                     WHERE b.status = 'pending'
                     ORDER BY (b.receipt_status = 'submitted') DESC, b.receipt_submitted_at ASC NULLS LAST, b.created_at ASC")->fetchAll();

respond(true, ['pending_billings' => $rows]);
