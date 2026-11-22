<?php
// Student submits the official receipt (OR) they received from the school
// cashier for one of their billing statements. An admin then validates it.
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
$user = requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$code = trim($_POST['billing_code'] ?? '');
if (!$code) respond(false, 'Billing code is missing.', 422);
// The OR number is read off the photo and typed by the admin when approving.

$db = getDB();
$stmt = $db->prepare("SELECT * FROM billing_statements WHERE billing_code = ? AND student_id = ?");
$stmt->execute([$code, $user['id']]);
$b = $stmt->fetch();
if (!$b) respond(false, 'Billing statement not found.', 404);
if ($b['status'] === 'paid') respond(false, 'This billing was already approved.', 409);
if ($b['receipt_status'] === 'submitted') respond(false, 'Your receipt is already waiting for review.', 409);

if (empty($_FILES['receipt_photo']) || $_FILES['receipt_photo']['error'] !== UPLOAD_ERR_OK) {
    respond(false, 'Please attach a clear photo of your official receipt.', 422);
}
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime = mime_content_type($_FILES['receipt_photo']['tmp_name']);
if (!isset($allowed[$mime])) respond(false, 'The receipt photo must be a JPG, PNG or WEBP image.', 422);

$objectPath = 'receipts/' . $b['id'] . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
if (!uploadToStorage(STORAGE_MATERIALS_BUCKET, $objectPath, $_FILES['receipt_photo']['tmp_name'], $mime)) {
    respond(false, 'Could not upload your receipt photo. Please try again.', 500);
}

// Conditional update: if two submissions race (double-click), only the first one wins
$st = $db->prepare("UPDATE billing_statements
              SET receipt_number = NULL, receipt_photo = ?, receipt_status = 'submitted',
                  receipt_note = NULL, receipt_submitted_at = NOW()
              WHERE id = ? AND status = 'pending' AND receipt_status IS DISTINCT FROM 'submitted'");
$st->execute([$objectPath, $b['id']]);
if ($st->rowCount() === 0) {
    deleteStorageObjects(STORAGE_MATERIALS_BUCKET, [$objectPath]);
    respond(false, 'Your receipt is already waiting for review.', 409);
}

// A rejected earlier photo is no longer needed
if (!empty($b['receipt_photo']) && $b['receipt_photo'] !== $objectPath) deleteStorageObjects(STORAGE_MATERIALS_BUCKET, [$b['receipt_photo']]);

respond(true, ['message' => 'Receipt submitted. An administrator will review it and unlock your materials.']);
