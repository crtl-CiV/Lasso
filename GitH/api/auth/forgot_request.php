<?php
// =========================================================
// LASSO — Forgot password, step 1: email a 6-digit code.
// The answer is the same whether or not the email has an
// account, so this page can't be used to find out who is
// registered. One code per minute per email.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/emailer.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$email = strtolower(trim($in['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Please enter a valid email address.', 422);

$db = getDB();
$generic = ['message' => 'If an account uses that email, a 6-digit code has been sent to it. Check your inbox (and Spam folder).'];

// One request per minute per email
$st = $db->prepare("SELECT EXTRACT(EPOCH FROM (NOW() - created_at))::int FROM password_resets WHERE email = ? ORDER BY created_at DESC LIMIT 1");
$st->execute([$email]);
$ago = $st->fetchColumn();
if ($ago !== false && (int)$ago < 60) {
    respond(false, 'Please wait ' . (60 - (int)$ago) . ' seconds before asking for another code.', 429);
}

$user = null; $type = null;
$st = $db->prepare("SELECT id, full_name FROM students WHERE LOWER(university_email) = ? AND status = 'active'");
$st->execute([$email]);
if ($row = $st->fetch()) { $user = $row; $type = 'student'; }
else {
    $st = $db->prepare("SELECT id, full_name FROM administrators WHERE LOWER(email) = ? AND status = 'active'");
    $st->execute([$email]);
    if ($row = $st->fetch()) { $user = $row; $type = 'admin'; }
}
if (!$user) respond(true, $generic);

$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
// Any older unused code stops working the moment a new one is issued
$db->prepare("UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL")->execute([$email]);
$db->prepare("INSERT INTO password_resets (user_type, user_id, email, code_hash, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL '10 minutes')")
   ->execute([$type, $user['id'], $email, password_hash($code, PASSWORD_DEFAULT)]);

if (!emailResetCode($email, $user['full_name'], $code)) {
    respond(false, 'We could not send the email right now. Please try again in a few minutes.', 502);
}
respond(true, $generic);
