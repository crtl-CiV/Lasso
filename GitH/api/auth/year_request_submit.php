<?php
// Student asks to change their year level. Requires a photo of their ID
// validated for the new year as proof; an admin approves or rejects it.
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
$user = requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$db = getDB();
$requested = trim($_POST['requested_year_level'] ?? '');
if (!in_array($requested, YEAR_LABELS, true)) respond(false, 'Please choose a valid year level.', 422);

$stmt = $db->prepare('SELECT year_level FROM students WHERE id = ?');
$stmt->execute([$user['id']]);
$current = (string)$stmt->fetchColumn();
if ($requested === $current) respond(false, 'That is already your current year level.', 422);

$stmt = $db->prepare("SELECT 1 FROM year_level_requests WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
if ($stmt->fetch()) respond(false, 'You already have a pending year level request. Please wait for it to be reviewed.', 409);

foreach (['id_photo' => 'front', 'id_photo_back' => 'back'] as $field => $side) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        respond(false, "Please attach a photo of the $side of your ID, validated for the new year level.", 422);
    }
}
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mimes = [];
foreach (['id_photo', 'id_photo_back'] as $field) {
    $mimes[$field] = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mimes[$field]])) respond(false, 'The ID photos must be JPG, PNG or WEBP images.', 422);
}

// Stored in the PRIVATE bucket; only admins can view them through year_request_photo.php
$paths = [];
foreach (['id_photo' => 'front', 'id_photo_back' => 'back'] as $field => $side) {
    $paths[$field] = 'year-proofs/' . $user['id'] . '_' . $side . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$mimes[$field]];
    if (!uploadToStorage(STORAGE_MATERIALS_BUCKET, $paths[$field], $_FILES[$field]['tmp_name'], $mimes[$field])) {
        deleteStorageObjects(STORAGE_MATERIALS_BUCKET, array_values($paths));
        respond(false, 'Could not upload your ID photos. Please try again.', 500);
    }
}

$db->prepare("INSERT INTO year_level_requests (student_id, current_year_level, requested_year_level, id_photo, id_photo_back)
              VALUES (?, ?, ?, ?, ?)")->execute([$user['id'], $current, $requested, $paths['id_photo'], $paths['id_photo_back']]);

respond(true, ['message' => 'Request submitted. An administrator will review it shortly.']);
