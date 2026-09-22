<?php
// Admin-only: paid billing statements with who confirmed each one.
// (Cashiers see the live pending queue via pending_billings.php, but this
// "who confirmed what" history view is admin-only, per the requirement.)
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$rows = $db->query("SELECT b.billing_code, b.total_amount, b.semester, b.academic_year, b.confirmed_at,
                            s.full_name AS student_name, s.student_id_number,
                            a.full_name AS confirmed_by_name, a.role AS confirmed_by_role
                     FROM billing_statements b
                     JOIN students s ON s.id = b.student_id
                     LEFT JOIN administrators a ON a.id = b.confirmed_by
                     WHERE b.status = 'paid'
                     ORDER BY b.confirmed_at DESC
                     LIMIT 100")->fetchAll();

respond(true, ['billing_history' => $rows]);
