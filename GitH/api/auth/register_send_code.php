<?php
// =========================================================
// LASSO — Registration step 1: email a 6-digit code to the
// student's school email. Registration only continues once
// that code is typed back (register_verify_code.php).
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/emailer.php';
require_once __DIR__ . '/../config/email_codes.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$email = strtolower(trim(jsonInput()['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, SCHOOL_EMAIL_DOMAIN)) {
    respond(false, 'Please use your official ' . SCHOOL_EMAIL_DOMAIN . ' email.', 422);
}

$db = getDB();
$st = $db->prepare("SELECT 1 FROM students WHERE LOWER(university_email) = ?");
$st->execute([$email]);
if ($st->fetch()) respond(false, 'This email already has an account. Log in, or use Forgot password if you can\'t remember it.', 409);

if ($wait = emailCodeWait($db, $email, 'register')) respond(false, 'Please wait ' . $wait . ' seconds before asking for another code.', 429);
$code = issueEmailCode($db, $email, 'register');

if (!emailVerifyCode($email, $code)) respond(false, 'We could not send the email right now. Please try again in a few minutes.', 502);
respond(true, ['message' => 'We sent a 6-digit code to ' . $email . '. Check your inbox (and Spam folder).']);
