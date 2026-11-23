<?php
// =========================================================
// LASSO — New admin account, step 2: create the account.
// Requires the 6-digit code that admin_send_code.php emailed
// to the new admin; without the right code nothing is created.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/email_codes.php';
require_once __DIR__ . '/../config/admin_form.php';
requireAdmin(); // only existing admins can create new admin accounts
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$db = getDB();
$f = validateNewAdmin($db, $in);

[$ok, $msg] = checkEmailCode($db, $f['email'], 'admin', (string)($in['code'] ?? ''));
if (!$ok) respond(false, $msg, 422);

$db->prepare("INSERT INTO administrators (admin_id, email, password_hash, full_name, position, role) VALUES (?, ?, ?, ?, ?, 'admin')")
   ->execute([$f['admin_id'], $f['email'], password_hash($f['password'], PASSWORD_DEFAULT), $f['full_name'], $f['position']]);

respond(true, ['message' => 'Admin account created for ' . $f['full_name'] . '.']);
