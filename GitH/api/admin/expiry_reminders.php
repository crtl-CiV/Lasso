<?php
// Admin backup for the daily reminder run.
//   GET  -> how many students have a reminder waiting
//   POST -> send them now
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/reminders.php';
requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(true, ['due' => remindersDue($db), 'email_ready' => (bool)mailConfigured()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);
if (!mailConfigured()) respond(false, 'Email is not set up yet (Mailjet keys are missing in Render).', 503);

$result = sendExpiryReminders($db);
if ($result['students'] === 0 && $result['failed'] > 0) respond(false, 'Could not send the reminders. Please try again later.', 502);
respond(true, ['message' => "Reminders sent to {$result['students']} student(s)." . ($result['failed'] ? " {$result['failed']} could not be sent." : '')] + $result);
