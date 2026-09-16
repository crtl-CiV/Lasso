<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$rows = $db->query("SELECT b.billing_code, b.total_amount, b.semester, b.academic_year, b.created_at,
                            s.full_name, s.student_id_number, v.status AS code_status, v.code
                     FROM billing_statements b
                     JOIN students s ON s.id = b.student_id
                     JOIN validation_codes v ON v.billing_id = b.id
                     WHERE b.status = 'pending'
                     ORDER BY b.created_at ASC")->fetchAll();

// Only reveal the actual code once it has been released (status = 'ready').
// Before that, the cashier shouldn't be able to see it (payment not yet confirmed).
foreach ($rows as &$r) {
    if ($r['code_status'] !== 'ready') $r['code'] = null;
}

respond(true, ['pending_billings' => $rows]);
