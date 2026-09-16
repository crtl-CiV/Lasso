<?php
require_once __DIR__ . '/../config/bootstrap.php';
if (empty($_SESSION['user'])) respond(false, 'Not logged in.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$current = (string)($in['current_password'] ?? '');
$new = (string)($in['new_password'] ?? '');
if (strlen($new) < 8) respond(false, 'New password must be at least 8 characters.', 422);

$db = getDB();
$role = $_SESSION['user']['role'];
$table = $role === 'admin' ? 'administrators' : 'students';
$id = $_SESSION['user']['id'];

$stmt = $db->prepare("SELECT password_hash FROM $table WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || !password_verify($current, $row['password_hash'])) {
    respond(false, 'Current password is incorrect.', 401);
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$db->prepare("UPDATE $table SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);

respond(true, ['message' => 'Password changed successfully.']);
