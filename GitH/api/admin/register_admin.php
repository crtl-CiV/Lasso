<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin(); // only existing admins can create new admin accounts
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$adminId  = trim($in['admin_id'] ?? '');
$email    = trim($in['email'] ?? '');
$fullName = trim($in['full_name'] ?? '');
$position = trim($in['position'] ?? '');
$password = (string)($in['password'] ?? '');

if (!$adminId || !$email || !$fullName || !$position || $password === '') {
    respond(false, 'All fields are required.', 422);
}
if (!in_array($position, ADMIN_POSITIONS, true)) respond(false, 'Please choose a position from the list.', 422);
if ($problem = passwordProblem($password)) respond(false, $problem, 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', 422);
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM administrators WHERE admin_id = ? OR email = ?");
$stmt->execute([$adminId, $email]);
if ($stmt->fetch()) respond(false, 'Admin ID or email already in use.', 409);

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO administrators (admin_id, email, password_hash, full_name, position, role) VALUES (?, ?, ?, ?, ?, 'admin')");
$stmt->execute([$adminId, $email, $hash, $fullName, $position]);

respond(true, ['message' => 'Admin account created successfully.']);
