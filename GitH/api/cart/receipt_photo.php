<?php
// Lets a student see the receipt photo they submitted for one of their own billings. ?code=BILLING_CODE
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
$user = requireStudent();
$st = getDB()->prepare("SELECT receipt_photo FROM billing_statements WHERE billing_code = ? AND student_id = ?");
$st->execute([(string)($_GET['code'] ?? ''), $user['id']]);
$path = $st->fetchColumn();
if (!$path) respond(false, 'No receipt photo.', 404);
$result = downloadFromStorage(STORAGE_MATERIALS_BUCKET, $path);
if (!$result) respond(false, 'Receipt photo not found.', 404);
header('Content-Type: ' . $result[1]);
header('Cache-Control: private, no-store');
echo $result[0];
exit;
