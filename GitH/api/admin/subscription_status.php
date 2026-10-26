<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$studentId = (int)($_GET['student_id'] ?? 0);
if ($studentId) {
    $stmt = $db->prepare("SELECT m.title, m.material_code, m.year_mask, m.semester_mask, au.name AS author_name,
                                  s.semester_count, s.start_date, s.expiry_date, s.status,
                                  (SELECT COALESCE(SUM(bi.price), 0) FROM billing_statement_items bi
                                     JOIN billing_statements pb ON pb.id = bi.billing_id
                                    WHERE bi.material_id = s.material_id AND pb.student_id = s.student_id AND pb.status = 'paid') AS total_paid,
                                  lb.billing_code, lb.receipt_number, lb.confirmed_at
                           FROM subscriptions s
                           JOIN instructional_materials m ON m.id = s.material_id
                           LEFT JOIN authors au ON au.id = m.author_id
                           LEFT JOIN billing_statements lb ON lb.id = s.billing_id
                           WHERE s.student_id = ? ORDER BY s.expiry_date DESC");
    $stmt->execute([$studentId]);
    respond(true, ['subscriptions' => $stmt->fetchAll()]);
}

// Per-material subscriber counts, institution-wide overview
$rows = $db->query("SELECT m.id, m.material_code, m.title,
                            SUM(CASE WHEN s.status='active' AND s.expiry_date >= CURRENT_DATE THEN 1 ELSE 0 END) AS active_subscribers,
                            COUNT(s.id) AS total_ever_subscribed
                     FROM instructional_materials m
                     LEFT JOIN subscriptions s ON s.material_id = m.id
                     WHERE m.status='published'
                     GROUP BY m.id ORDER BY active_subscribers DESC")->fetchAll();
respond(true, ['materials' => $rows]);
