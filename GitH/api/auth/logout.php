<?php
require_once __DIR__ . '/../config/bootstrap.php';
// Clear the saved token too, but only if it is still this browser's (never log out a newer device).
if (!empty($_SESSION['user']['session_token'])) {
    $u = $_SESSION['user'];
    $table = $u['role'] === 'student' ? 'students' : 'administrators';
    getDB()->prepare("UPDATE $table SET session_token = NULL WHERE id = ? AND session_token = ?")->execute([(int)$u['id'], $u['session_token']]);
}
$_SESSION = [];
session_destroy();
respond(true, ['message' => 'Logged out.']);
