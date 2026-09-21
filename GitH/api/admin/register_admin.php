<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin(); // only existing admins can create new admin/cashier accounts
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$adminId  = trim($in['admin_id'] ?? '');
$email    = trim($in['email'] ?? '');
$fullName = trim($in['full_name'] ?? '');
$position = trim($in['position'] ?? '');
$role     = $in['role'] ?? 'admin'; // 'admin' | 'cashier'
$password = (string)($in['password'] ?? '');

if (!$adminId || !$email || !$fullName || !$position || strlen($password) < 8) {
    respond(false, 'All fields are required and password must be at least 8 characters.', 422);
}
if (!in_array($role, ['admin', 'cashier'], true)) {
    respond(false, 'Invalid role.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', 422);
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM administrators WHERE admin_id = ? OR email = ?");
$stmt->execute([$adminId, $email]);
if ($stmt->fetch()) respond(false, 'Admin ID or email already in use.', 409);

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO administrators (admin_id, email, password_hash, full_name, position, role) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$adminId, $email, $hash, $fullName, $position, $role]);

respond(true, ['message' => ucfirst($role) . ' account created successfully.']);
