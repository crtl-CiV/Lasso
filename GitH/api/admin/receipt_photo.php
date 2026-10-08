<?php
// Streams a submitted receipt photo (admin only, private bucket). ?id= is the billing id.
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
requireAdmin();
$db = getDB();

$stmt = $db->prepare("SELECT receipt_photo FROM billing_statements WHERE id = ?");
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$path = $stmt->fetchColumn();
if (!$path) respond(false, 'No receipt photo.', 404);

$result = downloadFromStorage(STORAGE_MATERIALS_BUCKET, $path);
if (!$result) respond(false, 'Receipt photo not found.', 404);

header('Content-Type: ' . $result[1]);
header('Cache-Control: private, no-store');
echo $result[0];
exit;
