<?php
// Student cancels a billing they haven't paid for yet (e.g. a mistaken checkout).
// Allowed only while no receipt is waiting for review. Its materials go back to the cart.
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
$user = requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$code = trim(jsonInput()['billing_code'] ?? '');
$db = getDB();
$st = $db->prepare("SELECT * FROM billing_statements WHERE billing_code = ? AND student_id = ?");
$st->execute([$code, $user['id']]);
$b = $st->fetch();
if (!$b) respond(false, 'Billing statement not found.', 404);
if ($b['status'] === 'paid') respond(false, 'This billing was already approved and can no longer be cancelled.', 409);
if ($b['receipt_status'] === 'submitted') respond(false, 'Your receipt for this billing is waiting for review, so it can\'t be cancelled now.', 409);

$db->beginTransaction();
$db->prepare("INSERT INTO cart_items (student_id, material_id)
              SELECT ?, material_id FROM billing_statement_items WHERE billing_id = ?
              ON CONFLICT DO NOTHING")->execute([$user['id'], $b['id']]);
$db->prepare("DELETE FROM billing_statements WHERE id = ?")->execute([$b['id']]);
$db->commit();
if (!empty($b['receipt_photo'])) deleteStorageObjects(STORAGE_MATERIALS_BUCKET, [$b['receipt_photo']]);

respond(true, ['message' => "Billing {$b['billing_code']} cancelled. Its materials are back in your cart."]);
