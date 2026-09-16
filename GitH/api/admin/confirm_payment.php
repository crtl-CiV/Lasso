<?php
// Simulates the cashier's office confirming payment against a printed
// billing statement. Once confirmed, the validation code is released
// (status -> 'ready') and handed back to the cashier/admin right here so
// they can relay it to the student in person, per the actual process:
// student pays -> cashier confirms -> cashier gives the student the code
// -> student enters it themselves to activate the subscription.
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$billingCode = trim($in['billing_code'] ?? '');
if (!$billingCode) respond(false, 'Billing code is required.', 422);

$db = getDB();
$stmt = $db->prepare("SELECT b.*, s.full_name, s.student_id_number
                       FROM billing_statements b
                       JOIN students s ON s.id = b.student_id
                       WHERE b.billing_code = ?");
$stmt->execute([$billingCode]);
$billing = $stmt->fetch();
if (!$billing) respond(false, 'Billing statement not found.', 404);
if ($billing['status'] === 'paid') respond(false, 'This billing statement was already settled.', 409);

$db->prepare("UPDATE validation_codes SET status='ready' WHERE billing_id=?")->execute([$billing['id']]);

$stmt = $db->prepare("SELECT code FROM validation_codes WHERE billing_id = ?");
$stmt->execute([$billing['id']]);
$code = $stmt->fetchColumn();

respond(true, [
    'message' => 'Payment confirmed.',
    'validation_code' => $code,
    'student_name' => $billing['full_name'],
    'student_id_number' => $billing['student_id_number'],
]);
