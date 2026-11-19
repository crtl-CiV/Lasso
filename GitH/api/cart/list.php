<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$db = getDB();

$stmt = $db->prepare("SELECT m.id, m.material_code, m.title, m.price, m.cover_image, m.subject_code, au.name AS author_name
                       FROM cart_items c
                       JOIN instructional_materials m ON m.id = c.material_id
                       LEFT JOIN authors au ON au.id = m.author_id
                       WHERE c.student_id = ?
                       ORDER BY c.added_at DESC");
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();
$total = array_sum(array_column($items, 'price'));
[$sem, $ay] = currentTerm();
respond(true, ['items' => $items, 'total' => $total, 'term' => ['semester' => $sem, 'academic_year' => $ay]]);
