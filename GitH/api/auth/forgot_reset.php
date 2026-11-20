<?php
// =========================================================
// LASSO — Forgot password, step 2: check the code, set the
// new password. Codes last 10 minutes, work once, and stop
// working after 5 wrong tries.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/emailer.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$email   = strtolower(trim($in['email'] ?? ''));
$code    = preg_replace('/\D/', '', (string)($in['code'] ?? ''));
$new     = (string)($in['new_password'] ?? '');
$confirm = (string)($in['confirm_password'] ?? $new);

if (!$email || strlen($code) !== 6) respond(false, 'Enter the 6-digit code from the email.', 422);
if (strlen($new) < 8) respond(false, 'New password must be at least 8 characters.', 422);
if ($new !== $confirm) respond(false, 'The two passwords do not match.', 422);

$db = getDB();
$st = $db->prepare("SELECT *, (expires_at < NOW()) AS expired FROM password_resets WHERE email = ? AND used_at IS NULL ORDER BY created_at DESC LIMIT 1");
$st->execute([$email]);
$r = $st->fetch();

$dead = 'This code is no longer valid. Please request a new one.';
if (!$r) respond(false, $dead, 422);
if ($r['expired']) respond(false, 'This code has expired. Please request a new one.', 422);
if ((int)$r['attempts'] >= 5) respond(false, $dead, 422);

if (!password_verify($code, $r['code_hash'])) {
    $db->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?")->execute([$r['id']]);
    $left = 5 - ((int)$r['attempts'] + 1);
    if ($left <= 0) {
        $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$r['id']]);
        respond(false, 'Too many wrong tries. Please request a new code.', 422);
    }
    respond(false, 'Incorrect code. ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.', 422);
}

$table = $r['user_type'] === 'admin' ? 'administrators' : 'students';
$st = $db->prepare("SELECT full_name FROM $table WHERE id = ? AND status = 'active'");
$st->execute([$r['user_id']]);
$name = $st->fetchColumn();
if ($name === false) respond(false, $dead, 422);

$db->beginTransaction();
$db->prepare("UPDATE $table SET password_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $r['user_id']]);
$db->prepare("UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL")->execute([$email]);
$db->commit();

emailPasswordChanged($email, $name); // heads-up only; the reset already succeeded
respond(true, ['message' => 'Password updated. You can now log in with your new password.']);
