<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$adminId = trim($in['admin_id'] ?? '');
$fullName = trim($in['full_name'] ?? '');
$position = trim($in['position'] ?? '');
$password = (string)($in['password'] ?? '');

if (!$adminId || !$fullName || !$position || strlen($password) < 8) {
    respond(false, 'All fields are required and password must be at least 8 characters.', 422);
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM administrators WHERE admin_id = ?");
$stmt->execute([$adminId]);
if ($stmt->fetch()) respond(false, 'Admin ID already exists.', 409);

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO administrators (admin_id, password_hash, full_name, position) VALUES (?, ?, ?, ?)");
$stmt->execute([$adminId, $hash, $fullName, $position]);

respond(true, ['message' => 'Administrator account created successfully.']);
