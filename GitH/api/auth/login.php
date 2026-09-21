<?php
// =========================================================
// LASSO — Unified login for students, admins, and cashiers.
// Everyone logs in with email + password; role is looked up
// server-side, not chosen by the client.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$email    = trim($in['identifier'] ?? $in['email'] ?? '');
$password = (string)($in['password'] ?? '');

if (!$email || !$password) respond(false, 'Please enter your credentials.', 422);

$db = getDB();

// Check students first (by university email)
$stmt = $db->prepare('SELECT * FROM students WHERE university_email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
if ($user) {
    if ($user['status'] === 'trashed' || !password_verify($password, $user['password_hash'])) {
        respond(false, 'Invalid credentials.', 401);
    }
    $_SESSION['user'] = [
        'id' => $user['id'], 'role' => 'student',
        'student_id_number' => $user['student_id_number'],
        'full_name' => $user['full_name'], 'university_email' => $user['university_email'],
        'department_id' => $user['department_id'], 'program_id' => $user['program_id'],
        'year_level' => $user['year_level'], 'section' => $user['section'],
        'profile_photo' => $user['profile_photo'],
    ];
    respond(true, ['user' => $_SESSION['user']]);
}

// Not a student — check administrators/cashiers (by email)
$stmt = $db->prepare('SELECT * FROM administrators WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
if ($user) {
    if ($user['status'] === 'trashed' || !password_verify($password, $user['password_hash'])) {
        respond(false, 'Invalid credentials.', 401);
    }
    $_SESSION['user'] = [
        'id' => $user['id'], 'role' => $user['role'], // 'admin' | 'cashier'
        'admin_id' => $user['admin_id'], 'email' => $user['email'],
        'full_name' => $user['full_name'], 'position' => $user['position'],
    ];
    respond(true, ['user' => $_SESSION['user']]);
}

respond(false, 'Invalid credentials.', 401);
