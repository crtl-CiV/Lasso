<?php
// =========================================================
// LASSO — Registration step 1: email a 6-digit code to the
// student's school email. Registration only continues once
// that code is typed back (register_verify_code.php).
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/emailer.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$email = strtolower(trim(jsonInput()['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, SCHOOL_EMAIL_DOMAIN)) {
    respond(false, 'Please use your official ' . SCHOOL_EMAIL_DOMAIN . ' email.', 422);
}

$db = getDB();
$st = $db->prepare("SELECT 1 FROM students WHERE LOWER(university_email) = ?");
$st->execute([$email]);
if ($st->fetch()) respond(false, 'This email already has an account. Log in, or use Forgot password if you can\'t remember it.', 409);

$st = $db->prepare("SELECT EXTRACT(EPOCH FROM (NOW() - created_at))::int FROM email_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1");
$st->execute([$email]);
$ago = $st->fetchColumn();
if ($ago !== false && (int)$ago < 60) respond(false, 'Please wait ' . (60 - (int)$ago) . ' seconds before asking for another code.', 429);

$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$db->prepare("UPDATE email_verifications SET used_at = NOW() WHERE email = ? AND used_at IS NULL")->execute([$email]);
$db->prepare("INSERT INTO email_verifications (email, code_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL '10 minutes')")
   ->execute([$email, password_hash($code, PASSWORD_DEFAULT)]);

if (!emailVerifyCode($email, $code)) respond(false, 'We could not send the email right now. Please try again in a few minutes.', 502);
respond(true, ['message' => 'We sent a 6-digit code to ' . $email . '. Check your inbox (and Spam folder).']);
