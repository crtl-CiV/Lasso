<?php
// =========================================================
// LASSO — Registration step 1b: check the emailed code.
// On success the verified email is remembered in this browser
// session for 1 hour; register.php only accepts that email.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$email = strtolower(trim($in['email'] ?? ''));
$code  = preg_replace('/\D/', '', (string)($in['code'] ?? ''));
if (!$email || strlen($code) !== 6) respond(false, 'Enter the 6-digit code from the email.', 422);

$db = getDB();
$st = $db->prepare("SELECT *, (expires_at < NOW()) AS expired FROM email_verifications WHERE email = ? AND used_at IS NULL ORDER BY created_at DESC LIMIT 1");
$st->execute([$email]);
$r = $st->fetch();

$dead = 'This code is no longer valid. Please request a new one.';
if (!$r || (int)$r['attempts'] >= 5) respond(false, $dead, 422);
if ($r['expired']) respond(false, 'This code has expired. Please request a new one.', 422);

if (!password_verify($code, $r['code_hash'])) {
    $db->prepare("UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?")->execute([$r['id']]);
    $left = 5 - ((int)$r['attempts'] + 1);
    if ($left <= 0) {
        $db->prepare("UPDATE email_verifications SET used_at = NOW() WHERE id = ?")->execute([$r['id']]);
        respond(false, 'Too many wrong tries. Please request a new code.', 422);
    }
    respond(false, 'Incorrect code. ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.', 422);
}

$db->prepare("UPDATE email_verifications SET used_at = NOW(), verified_at = NOW() WHERE id = ?")->execute([$r['id']]);
$_SESSION['reg_verified'] = ['email' => $email, 'at' => time()];
respond(true, ['message' => 'Email verified.', 'email' => $email]);
