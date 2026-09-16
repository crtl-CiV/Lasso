<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$in = jsonInput();
$materialId = (int)($in['material_id'] ?? 0);
if (!$materialId) respond(false, 'Material not specified.', 422);

$db = getDB();
$stmt = $db->prepare("DELETE FROM cart_items WHERE student_id=? AND material_id=?");
$stmt->execute([$user['id'], $materialId]);
respond(true, ['message' => 'Removed from cart.']);
