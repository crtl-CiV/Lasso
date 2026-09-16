<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$db = getDB();
$stmt = $db->prepare("SELECT m.id, m.title, m.price FROM cart_items c
                       JOIN instructional_materials m ON m.id = c.material_id
                       WHERE c.student_id = ?");
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();
if (!$items) respond(false, 'Your cart is empty.', 422);

[$semester, $ay] = currentTerm();
$total = array_sum(array_column($items, 'price'));
$billingCode = genCode('BS', 8);

try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO billing_statements (billing_code, student_id, total_amount, semester, academic_year, status)
                           VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$billingCode, $user['id'], $total, $semester, $ay]);
    $billingId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare("INSERT INTO billing_statement_items (billing_id, material_id, price) VALUES (?, ?, ?)");
    foreach ($items as $it) $itemStmt->execute([$billingId, $it['id'], $it['price']]);

    // Validation code: generated now, transmitted to "the cashier's office" (simulated —
    // an administrator confirms payment via /api/admin/confirm_payment.php, which flips it to 'ready').
    $code = genCode('VAL', 10);
    $stmt = $db->prepare("INSERT INTO validation_codes (billing_id, student_id, code, status) VALUES (?, ?, ?, 'awaiting_payment')");
    $stmt->execute([$billingId, $user['id'], $code]);

    $db->prepare("DELETE FROM cart_items WHERE student_id = ?")->execute([$user['id']]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    respond(false, 'Checkout failed: ' . $e->getMessage(), 500);
}

respond(true, [
    'billing' => [
        'billing_code' => $billingCode,
        'student_name' => $user['full_name'],
        'student_id_number' => $user['student_id_number'],
        'items' => $items,
        'total' => $total,
        'semester' => $semester,
        'academic_year' => $ay,
        'date' => date('Y-m-d H:i'),
    ],
    'message' => 'Billing statement generated. Print it and present it at the cashier. Your validation code has been transmitted to the cashier\'s office and will be released to you once payment is confirmed.'
]);
