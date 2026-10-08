<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$where = ($_GET['status'] ?? 'pending') === 'all' ? '' : "WHERE r.status = 'pending'";
$rows = $db->query("SELECT r.id, r.current_year_level, r.requested_year_level, r.status, r.review_note, r.created_at, r.reviewed_at,
                            s.full_name, s.student_id_number, s.university_email,
                            a.full_name AS reviewed_by_name
                     FROM year_level_requests r
                     JOIN students s ON s.id = r.student_id
                     LEFT JOIN administrators a ON a.id = r.reviewed_by
                     $where
                     ORDER BY (r.status = 'pending') DESC, r.created_at DESC
                     LIMIT 200")->fetchAll();

respond(true, ['requests' => $rows]);
