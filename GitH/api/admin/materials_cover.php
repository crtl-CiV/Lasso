<?php
// Replace a material's cover photo from the Edit window. POST multipart: id, cover_image
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$id = (int)($_POST['id'] ?? 0);
$db = getDB();
$st = $db->prepare("SELECT cover_image FROM instructional_materials WHERE id = ?");
$st->execute([$id]);
$old = $st->fetch();
if (!$old) respond(false, 'Material not found.', 404);

if (empty($_FILES['cover_image']) || $_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) respond(false, 'Please choose a cover image.', 422);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime = mime_content_type($_FILES['cover_image']['tmp_name']);
if (!isset($allowed[$mime])) respond(false, 'The cover must be a JPG, PNG or WEBP image.', 422);

$path = 'covers/cover_' . $id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
if (!uploadToStorage(STORAGE_PUBLIC_BUCKET, $path, $_FILES['cover_image']['tmp_name'], $mime)) respond(false, 'Could not upload the cover. Please try again.', 500);
$db->prepare("UPDATE instructional_materials SET cover_image = ? WHERE id = ?")->execute([$path, $id]);
if (!empty($old['cover_image'])) deleteStorageObjects(STORAGE_PUBLIC_BUCKET, [$old['cover_image']]);

respond(true, ['message' => 'Cover updated.', 'cover_image' => $path]);
