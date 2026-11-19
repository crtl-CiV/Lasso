<?php
// "Approved history": every approved billing with its materials, the OR used,
// who approved it and when, and how long the access runs.
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$rows = $db->query("SELECT b.id, b.billing_code, b.total_amount, b.semester, b.academic_year, b.created_at,
                            b.confirmed_at, b.receipt_number, b.receipt_submitted_at,
                            s.id AS student_pk, s.full_name AS student_name, s.student_id_number,
                            a.full_name AS confirmed_by_name
                     FROM billing_statements b
                     JOIN students s ON s.id = b.student_id
                     LEFT JOIN administrators a ON a.id = b.confirmed_by
                     WHERE b.status = 'paid'
                     ORDER BY b.confirmed_at DESC NULLS LAST, b.id DESC
                     LIMIT 200")->fetchAll();

if ($rows) {
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    // expiry_date is only known for the billing that last (re)activated the subscription
    $stmt = $db->prepare("SELECT bi.billing_id, m.title, m.material_code, m.subject_code, bi.price,
                                 sub.expiry_date, sub.start_date
                          FROM billing_statement_items bi
                          JOIN billing_statements b ON b.id = bi.billing_id
                          JOIN instructional_materials m ON m.id = bi.material_id
                          LEFT JOIN subscriptions sub ON sub.billing_id = bi.billing_id AND sub.material_id = bi.material_id AND sub.student_id = b.student_id
                          WHERE bi.billing_id IN ($in) ORDER BY m.title");
    $stmt->execute($ids);
    $items = [];
    foreach ($stmt->fetchAll() as $it) $items[$it['billing_id']][] = $it;
    foreach ($rows as &$r) $r['items'] = $items[$r['id']] ?? [];
    unset($r);
}

respond(true, ['billing_history' => $rows]);
