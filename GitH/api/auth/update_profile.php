<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$db = getDB();
$fullName = trim($_POST['full_name'] ?? $user['full_name']);
$section  = trim($_POST['section'] ?? $user['section']);

$photoPath = null;
if (!empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES['profile_photo']['tmp_name']);
    if (isset($allowed[$mime])) {
        $dir = __DIR__ . '/../uploads/ids/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $filename = 'profile_' . $user['id'] . '_' . time() . '.' . $allowed[$mime];
        move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $filename);
        $photoPath = 'uploads/ids/' . $filename;
    }
}

if ($photoPath) {
    $stmt = $db->prepare('UPDATE students SET full_name = ?, section = ?, profile_photo = ? WHERE id = ?');
    $stmt->execute([$fullName, $section, $photoPath, $user['id']]);
    $_SESSION['user']['profile_photo'] = $photoPath;
} else {
    $stmt = $db->prepare('UPDATE students SET full_name = ?, section = ? WHERE id = ?');
    $stmt->execute([$fullName, $section, $user['id']]);
}
$_SESSION['user']['full_name'] = $fullName;
$_SESSION['user']['section'] = $section;

respond(true, ['user' => $_SESSION['user']]);
