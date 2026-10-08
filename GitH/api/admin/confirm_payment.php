<?php
// Admin validates the student's submitted official receipt (OR) against a
// billing code, then either grants access to every material on that billing
// (approve) or sends it back with a reason (reject).
require_once __DIR__ . '/../config/bootstrap.php';
$admin = requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$billingCode = trim($in['billing_code'] ?? '');
$action = $in['action'] ?? 'approve';
$note = trim($in['note'] ?? '');
if (!$billingCode) respond(false, 'Billing code is required.', 422);
if (!in_array($action, ['approve', 'reject'], true)) respond(false, 'Invalid action.', 422);

$db = getDB();
$stmt = $db->prepare("SELECT b.*, s.full_name, s.student_id_number
                       FROM billing_statements b
                       JOIN students s ON s.id = b.student_id
                       WHERE b.billing_code = ?");
$stmt->execute([$billingCode]);
$b = $stmt->fetch();
if (!$b) respond(false, 'Billing statement not found.', 404);
if ($b['status'] === 'paid') respond(false, 'This billing was already approved.', 409);
if ($b['receipt_status'] !== 'submitted') {
    respond(false, 'The student has not submitted a receipt for this billing yet.', 422);
}

if ($action === 'reject') {
    if ($note === '') respond(false, 'Please give a reason so the student knows what to fix.', 422);
    $db->prepare("UPDATE billing_statements SET receipt_status = 'rejected', receipt_note = ? WHERE id = ?")
       ->execute([$note, $b['id']]);
    respond(true, ['message' => 'Receipt rejected. The student can submit a corrected one.']);
}

// ---- approve: activate (or extend) a 3-month subscription per material ----
$stmt = $db->prepare("SELECT bi.material_id, m.title FROM billing_statement_items bi
                       JOIN instructional_materials m ON m.id = bi.material_id
                       WHERE bi.billing_id = ?");
$stmt->execute([$b['id']]);
$materials = $stmt->fetchAll();

try {
    $db->beginTransaction();
    $today = date('Y-m-d');
    foreach ($materials as $mat) {
        $stmt = $db->prepare("SELECT * FROM subscriptions WHERE student_id = ? AND material_id = ?");
        $stmt->execute([$b['student_id'], $mat['material_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Re-subscription: add one more semester (3 months) from the current expiry, or from today if it already lapsed
            $base = max($existing['expiry_date'], $today);
            $newExpiry = date('Y-m-d', strtotime($base . ' +3 months'));
            $db->prepare("UPDATE subscriptions SET semester_count = semester_count + 1, expiry_date = ?, status = 'active', billing_id = ? WHERE id = ?")
               ->execute([$newExpiry, $b['id'], $existing['id']]);
        } else {
            $expiry = date('Y-m-d', strtotime($today . ' +3 months'));
            $db->prepare("INSERT INTO subscriptions (student_id, material_id, billing_id, semester_count, start_date, expiry_date, status)
                          VALUES (?, ?, ?, 1, ?, ?, 'active')")
               ->execute([$b['student_id'], $mat['material_id'], $b['id'], $today, $expiry]);
        }
    }
    $db->prepare("UPDATE billing_statements SET status = 'paid', receipt_status = 'approved', receipt_note = NULL,
                         confirmed_by = ?, confirmed_at = NOW() WHERE id = ?")
       ->execute([$admin['id'], $b['id']]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    respond(false, 'Could not grant access: ' . $e->getMessage(), 500);
}

respond(true, [
    'message' => 'Receipt validated. Access granted.',
    'student_name' => $b['full_name'],
    'student_id_number' => $b['student_id_number'],
    'materials' => array_column($materials, 'title'),
]);
