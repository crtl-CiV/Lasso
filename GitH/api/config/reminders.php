<?php
// =========================================================
// LASSO — Expiry reminders (7 days before a subscription ends)
// Used by api/cron/expiry_reminders.php (daily, from cron-job.org)
// and by the admin Dashboard's "Send now" button as a backup.
// One email per student, listing every material that expires
// within the next 7 days. Each expiry date is reminded once.
// =========================================================
require_once __DIR__ . '/emailer.php';

const REMINDER_DAYS = 7;

/** How many students have a reminder waiting. */
function remindersDue(PDO $db): int {
    return (int)$db->query("SELECT COUNT(DISTINCT sub.student_id) FROM subscriptions sub
        JOIN students s ON s.id = sub.student_id AND s.status = 'active'
        WHERE sub.status = 'active' AND sub.expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + " . REMINDER_DAYS . "
          AND (sub.reminder_sent_for IS NULL OR sub.reminder_sent_for <> sub.expiry_date)")->fetchColumn();
}

/** Sends the waiting reminders. Returns ['students' => sent, 'failed' => n]. */
function sendExpiryReminders(PDO $db): array {
    $rows = $db->query("SELECT sub.id, sub.student_id, sub.expiry_date, m.title, s.full_name, s.university_email
        FROM subscriptions sub
        JOIN students s ON s.id = sub.student_id AND s.status = 'active'
        JOIN instructional_materials m ON m.id = sub.material_id
        WHERE sub.status = 'active' AND sub.expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + " . REMINDER_DAYS . "
          AND (sub.reminder_sent_for IS NULL OR sub.reminder_sent_for <> sub.expiry_date)
        ORDER BY sub.student_id, sub.expiry_date, m.title")->fetchAll();

    $byStudent = [];
    foreach ($rows as $r) $byStudent[$r['student_id']][] = $r;

    $sent = 0; $failed = 0;
    $mark = $db->prepare("UPDATE subscriptions SET reminder_sent_for = expiry_date WHERE id = ?");
    foreach ($byStudent as $items) {
        $first = $items[0];
        if (emailExpiryReminder($first['university_email'], $first['full_name'], $items)) {
            foreach ($items as $it) $mark->execute([$it['id']]);
            $sent++;
        } else {
            $failed++; // left unmarked, so the next run tries again
        }
    }
    return ['students' => $sent, 'failed' => $failed];
}
