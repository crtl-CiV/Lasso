<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/emailer.php';
$admin = requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$id = (int)($in['id'] ?? 0);
$action = $in['action'] ?? '';
$note = trim($in['note'] ?? '');
if (!$id || !in_array($action, ['approve', 'reject'], true)) respond(false, 'Invalid request.', 422);

$db = getDB();
$stmt = $db->prepare("SELECT * FROM year_level_requests WHERE id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) respond(false, 'Request not found.', 404);
if ($req['status'] !== 'pending') respond(false, 'This request was already reviewed.', 409);

try {
    $db->beginTransaction();
    if ($action === 'approve') {
        $db->prepare("UPDATE students SET year_level = ? WHERE id = ?")->execute([$req['requested_year_level'], $req['student_id']]);
    }
    $db->prepare("UPDATE year_level_requests SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
       ->execute([$action === 'approve' ? 'approved' : 'rejected', $note ?: null, $admin['id'], $id]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    respond(false, 'Could not save the decision: ' . $e->getMessage(), 500);
}

$st = $db->prepare("SELECT full_name, university_email FROM students WHERE id = ?");
$st->execute([$req['student_id']]);
$mailed = false;
if ($stu = $st->fetch()) {
    $mailed = emailYearRequestResult($stu['university_email'], $stu['full_name'], $action === 'approve', $req['requested_year_level'], $note);
}
respond(true, ['message' => ($action === 'approve' ? 'Year level updated.' : 'Request rejected.') . ($mailed ? ' The student was notified by email.' : ' (The email notification could not be sent.)'), 'email_sent' => $mailed]);
