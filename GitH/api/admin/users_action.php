<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$id = (int)($in['id'] ?? 0);
$type = $in['type'] ?? '';       // 'student' | 'admin'
$action = $in['action'] ?? '';   // 'trash' | 'restore'
if (!$id || !in_array($type, ['student','admin'], true) || !in_array($action, ['trash','restore'], true)) {
    respond(false, 'Invalid request.', 422);
}

$table = $type === 'student' ? 'students' : 'administrators';
$newStatus = $action === 'trash' ? 'trashed' : 'active';

$db = getDB();
$stmt = $db->prepare("UPDATE $table SET status = ? WHERE id = ?");
$stmt->execute([$newStatus, $id]);

respond(true, ['message' => ($action === 'trash' ? 'Account moved to trash/archive.' : 'Account restored successfully.')]);
