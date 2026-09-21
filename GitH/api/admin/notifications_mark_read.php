<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$id = (int)($in['id'] ?? 0);
$all = !empty($in['all']);

$db = getDB();
if ($all) {
    $db->exec("UPDATE notifications SET is_read = TRUE WHERE is_read = FALSE");
} elseif ($id) {
    $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?")->execute([$id]);
} else {
    respond(false, 'Nothing specified to mark as read.', 422);
}

respond(true, ['message' => 'Updated.']);
