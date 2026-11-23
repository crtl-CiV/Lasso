<?php
// =========================================================
// LASSO — Registration step 1b: check the emailed code.
// On success the verified email is remembered in this browser
// session for 1 hour; register.php only accepts that email.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/email_codes.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$email = strtolower(trim($in['email'] ?? ''));
$code  = (string)($in['code'] ?? '');
if (!$email) respond(false, 'Enter the 6-digit code from the email.', 422);

[$ok, $msg] = checkEmailCode(getDB(), $email, 'register', $code);
if (!$ok) respond(false, $msg, 422);

$_SESSION['reg_verified'] = ['email' => $email, 'at' => time()];
respond(true, ['message' => 'Email verified.', 'email' => $email]);
