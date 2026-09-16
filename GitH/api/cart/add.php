<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$in = jsonInput();
$materialId = (int)($in['material_id'] ?? 0);
if (!$materialId) respond(false, 'Material not specified.', 422);

$db = getDB();

$stmt = $db->prepare("SELECT id FROM instructional_materials WHERE id=? AND status='published'");
$stmt->execute([$materialId]);
if (!$stmt->fetch()) respond(false, 'Material not available.', 404);

$stmt = $db->prepare("SELECT id FROM subscriptions WHERE student_id=? AND material_id=? AND status='active'");
$stmt->execute([$user['id'], $materialId]);
if ($stmt->fetch()) respond(false, 'You already have an active subscription to this material.', 409);

$stmt = $db->prepare("INSERT IGNORE INTO cart_items (student_id, material_id) VALUES (?, ?)");
$stmt->execute([$user['id'], $materialId]);

respond(true, ['message' => 'Added to cart.']);
