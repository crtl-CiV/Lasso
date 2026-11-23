<?php
// =========================================================
// LASSO — New admin account, step 1: check the form, then
// email a 6-digit code to the NEW admin's email address.
// The account is created only when that code is typed back
// (register_admin.php). Any email works; it need not be a school email.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/emailer.php';
require_once __DIR__ . '/../config/email_codes.php';
require_once __DIR__ . '/../config/admin_form.php';
$creator = requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$db = getDB();
$f = validateNewAdmin($db, jsonInput());

if ($wait = emailCodeWait($db, $f['email'], 'admin')) respond(false, 'Please wait ' . $wait . ' seconds before sending another code.', 429);
$code = issueEmailCode($db, $f['email'], 'admin');

$st = $db->prepare("SELECT full_name FROM administrators WHERE id = ?");
$st->execute([$creator['id']]);
if (!emailAdminCode($f['email'], $f['full_name'], $code, (string)$st->fetchColumn())) {
    respond(false, 'We could not send the email right now. Please try again in a few minutes.', 502);
}
respond(true, ['message' => 'A 6-digit code was sent to ' . $f['email'] . '.', 'email' => $f['email']]);
