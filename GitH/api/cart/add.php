<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$in = jsonInput();
$materialId = (int)($in['material_id'] ?? 0);
if (!$materialId) respond(false, 'Material not specified.', 422);

$db = getDB();

$stmt = $db->prepare("SELECT id, year_min FROM instructional_materials WHERE id=? AND status='published'");
$stmt->execute([$materialId]);
$mat = $stmt->fetch();
if (!$mat) respond(false, 'Material not available.', 404);

// Year-level exclusivity (students may buy their own year and anything below it)
if ((int)$mat['year_min'] > studentYearInt($db, (int)$user['id'])) {
    respond(false, 'This material is not available for your year level.', 403);
}

$stmt = $db->prepare("SELECT id FROM subscriptions WHERE student_id=? AND material_id=? AND status='active' AND expiry_date >= CURRENT_DATE");
$stmt->execute([$user['id'], $materialId]);
if ($stmt->fetch()) respond(false, 'You already have an active subscription to this material.', 409);

$stmt = $db->prepare("INSERT INTO cart_items (student_id, material_id) VALUES (?, ?)
                       ON CONFLICT ON CONSTRAINT uq_cart DO NOTHING");
$stmt->execute([$user['id'], $materialId]);

respond(true, ['message' => 'Added to cart.']);
