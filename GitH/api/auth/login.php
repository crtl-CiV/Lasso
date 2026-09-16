<?php
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$identifier = trim($in['identifier'] ?? '');   // university email OR admin ID
$password   = (string)($in['password'] ?? '');
$role       = $in['role'] ?? 'student';        // 'student' | 'admin'

if (!$identifier || !$password) respond(false, 'Please enter your credentials.', 422);

$db = getDB();

if ($role === 'admin') {
    $stmt = $db->prepare('SELECT * FROM administrators WHERE admin_id = ? LIMIT 1');
    $stmt->execute([$identifier]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] === 'trashed' || !password_verify($password, $user['password_hash'])) {
        respond(false, 'Invalid Admin ID or password.', 401);
    }
    $_SESSION['user'] = [
        'id' => $user['id'], 'role' => 'admin', 'admin_id' => $user['admin_id'],
        'full_name' => $user['full_name'], 'position' => $user['position'],
    ];
    respond(true, ['user' => $_SESSION['user']]);
}

$stmt = $db->prepare('SELECT * FROM students WHERE university_email = ? OR student_id_number = ? LIMIT 1');
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch();
if (!$user || $user['status'] === 'trashed' || !password_verify($password, $user['password_hash'])) {
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
