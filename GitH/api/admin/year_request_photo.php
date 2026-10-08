<?php
// Streams the ID proof for a year level request (admin only, private bucket).
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
requireAdmin();
$db = getDB();

$stmt = $db->prepare("SELECT id_photo FROM year_level_requests WHERE id = ?");
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$path = $stmt->fetchColumn();
if (!$path) respond(false, 'Photo not found.', 404);

$result = downloadFromStorage(STORAGE_MATERIALS_BUCKET, $path);
if (!$result) respond(false, 'Photo not found.', 404);

header('Content-Type: ' . $result[1]);
header('Cache-Control: private, no-store');
echo $result[0];
exit;
