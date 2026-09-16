<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$code = trim($in['code'] ?? '');
if (!$code) respond(false, 'Please enter your validation code.', 422);

$db = getDB();
$stmt = $db->prepare("SELECT * FROM validation_codes WHERE student_id = ? AND code = ?");
$stmt->execute([$user['id'], $code]);
$vc = $stmt->fetch();

if (!$vc) respond(false, 'Invalid code. Please check and resubmit.', 400);
if ($vc['status'] === 'validated') respond(false, 'This code has already been used.', 409);
if ($vc['status'] === 'awaiting_payment') {
    respond(false, 'This code has not yet been released. Please complete payment at the cashier first.', 403);
}

// status === 'ready' -> codes match and payment confirmed. Activate subscriptions.
$stmt = $db->prepare("SELECT bi.material_id, m.title FROM billing_statement_items bi
                       JOIN instructional_materials m ON m.id = bi.material_id
                       WHERE bi.billing_id = ?");
$stmt->execute([$vc['billing_id']]);
$materials = $stmt->fetchAll();

try {
    $db->beginTransaction();
    $today = date('Y-m-d');
    foreach ($materials as $mat) {
        $stmt = $db->prepare("SELECT * FROM subscriptions WHERE student_id=? AND material_id=?");
        $stmt->execute([$user['id'], $mat['material_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Re-subscription: extend access by one additional semester from current expiry (or today if expired)
            $base = max($existing['expiry_date'], $today);
            $newExpiry = date('Y-m-d', strtotime($base . ' +6 months'));
            $stmt = $db->prepare("UPDATE subscriptions SET semester_count = semester_count + 1, expiry_date = ?, status='active', billing_id=? WHERE id=?");
            $stmt->execute([$newExpiry, $vc['billing_id'], $existing['id']]);
        } else {
            $expiry = date('Y-m-d', strtotime($today . ' +6 months'));
            $stmt = $db->prepare("INSERT INTO subscriptions (student_id, material_id, billing_id, semester_count, start_date, expiry_date, status)
                                   VALUES (?, ?, ?, 1, ?, ?, 'active')");
            $stmt->execute([$user['id'], $mat['material_id'], $vc['billing_id'], $today, $expiry]);
        }
    }

    $db->prepare("UPDATE validation_codes SET status='validated', validated_at=NOW() WHERE id=?")->execute([$vc['id']]);
    $db->prepare("UPDATE billing_statements SET status='paid' WHERE id=?")->execute([$vc['billing_id']]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    respond(false, 'Validation failed: ' . $e->getMessage(), 500);
}

respond(true, ['message' => 'Validation successful! Full access has been unlocked in your Library.', 'materials' => $materials]);
