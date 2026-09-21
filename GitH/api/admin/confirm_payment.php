<?php
// Simulates the cashier's office confirming payment against a printed
// billing statement. Once confirmed, the validation code is released
// (status -> 'ready') and handed back to the cashier/admin right here so
// they can relay it to the student in person, per the actual process:
// student pays -> cashier confirms -> cashier gives the student the code
// -> student enters it themselves to activate the subscription.
require_once __DIR__ . '/../config/bootstrap.php';
$staff = requireStaff(); // admin or cashier can confirm payments
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

// Notify the admin that a cashier (or admin) just processed this payment.
$stmt = $db->prepare("SELECT m.title FROM billing_statement_items bi
                       JOIN instructional_materials m ON m.id = bi.material_id
                       WHERE bi.billing_id = ?");
$stmt->execute([$billing['id']]);
$titles = $stmt->fetchAll(PDO::FETCH_COLUMN);
$titleList = $titles ? implode(', ', $titles) : 'their billing statement';

$message = "{$billing['full_name']} ({$billing['student_id_number']}) paid for {$titleList}. "
         . "Validation code {$code} was released by " . ($staff['role'] === 'cashier' ? 'cashier' : 'admin')
         . " {$staff['full_name']}.";

$db->prepare("INSERT INTO notifications (type, message, student_id, billing_id, cashier_id)
              VALUES ('payment_confirmed', ?, ?, ?, ?)")
   ->execute([$message, $billing['student_id'], $billing['id'], $staff['id']]);

respond(true, [
    'message' => 'Payment confirmed.',
    'validation_code' => $code,
    'student_name' => $billing['full_name'],
    'student_id_number' => $billing['student_id_number'],
]);
