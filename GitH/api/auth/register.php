<?php
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$studentId   = trim($_POST['student_id_number'] ?? '');
$email       = trim($_POST['university_email'] ?? '');
$password    = (string)($_POST['password'] ?? '');
$fullName    = trim($_POST['full_name'] ?? '');
$departmentId= (int)($_POST['department_id'] ?? 0);
$programId   = (int)($_POST['program_id'] ?? 0);
$yearLevel   = trim($_POST['year_level'] ?? '');
$section     = trim($_POST['section'] ?? '');

if (!$studentId || !$email || !$password || !$fullName || !$departmentId || !$programId || !$yearLevel || !$section) {
    respond(false, 'Please fill out all required fields.', 422);
}
if (strlen($password) < 8) {
    respond(false, 'Password must be at least 8 characters.', 422);
}
if (!str_ends_with(strtolower($email), '.edu.ph') && !str_ends_with(strtolower($email), '.edu')) {
    // Soft check only — adjust or remove to match your actual university domain.
}

$db = getDB();

// studentID already registered? (Alt flow from the Register sequence diagram)
$stmt = $db->prepare('SELECT id FROM students WHERE student_id_number = ? OR university_email = ?');
$stmt->execute([$studentId, $email]);
if ($stmt->fetch()) {
    respond(false, 'This Student ID or university email is already registered.', 409);
}

// Handle required ID photo uploads (front & back)
function saveIdPhoto(string $field, string $studentId): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) return null;
    $dir = __DIR__ . '/../uploads/ids/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $studentId) . '_' . $field . '_' . time() . '.' . $allowed[$mime];
    move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $filename);
    return 'uploads/ids/' . $filename;
}

$idFront = saveIdPhoto('id_photo_front', $studentId);
$idBack  = saveIdPhoto('id_photo_back', $studentId);

if (!$idFront || !$idBack) {
    respond(false, 'Front and back photos of your Student ID are required for verification.', 422);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare('INSERT INTO students
    (student_id_number, university_email, password_hash, full_name, department_id, program_id, year_level, section, id_photo_front, id_photo_back)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$studentId, $email, $hash, $fullName, $departmentId, $programId, $yearLevel, $section, $idFront, $idBack]);

respond(true, ['message' => 'Account created successfully. You may now log in.']);
