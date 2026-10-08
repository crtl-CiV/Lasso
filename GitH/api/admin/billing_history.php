<?php
// Approved billings: which OR was used, who approved it, and when.
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$rows = $db->query("SELECT b.id, b.billing_code, b.total_amount, b.confirmed_at, b.receipt_number,
                            s.full_name AS student_name, s.student_id_number,
                            a.full_name AS confirmed_by_name
                     FROM billing_statements b
                     JOIN students s ON s.id = b.student_id
                     LEFT JOIN administrators a ON a.id = b.confirmed_by
                     WHERE b.status = 'paid'
                     ORDER BY b.confirmed_at DESC NULLS LAST
                     LIMIT 100")->fetchAll();

respond(true, ['billing_history' => $rows]);
