<?php
// =========================================================
// LASSO — Daily expiry-reminder run.
// Called once a day by cron-job.org:
//   https://lasso-3tyo.onrender.com/api/cron/expiry_reminders.php?key=YOUR_CRON_SECRET
// CRON_SECRET is a Render env var; without the right key nothing runs.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/reminders.php';

$secret = getenv('CRON_SECRET') ?: '';
$key = (string)($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');
if ($secret === '' || !hash_equals($secret, $key)) respond(false, 'Forbidden.', 403);

$result = sendExpiryReminders(getDB());
respond(true, ['message' => "Reminders sent to {$result['students']} student(s)." . ($result['failed'] ? " {$result['failed']} failed and will be retried." : '')] + $result);
