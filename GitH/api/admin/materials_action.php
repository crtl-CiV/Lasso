<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$id = (int)($in['id'] ?? 0);
$action = $in['action'] ?? '';
if (!$id || !in_array($action, ['trash','restore','promote','unpromote','publish'], true)) {
    respond(false, 'Invalid request.', 422);
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM instructional_materials WHERE id = ?");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) respond(false, 'Material not found.', 404);

switch ($action) {
    case 'trash':
        $db->prepare("UPDATE instructional_materials SET status='trashed' WHERE id=?")->execute([$id]);
        $msg = 'Moved to trash.';
        break;
    case 'restore':
        $db->prepare("UPDATE instructional_materials SET status='draft' WHERE id=?")->execute([$id]);
        $msg = 'Restored to drafts.';
        break;
    case 'publish':
        $db->prepare("UPDATE instructional_materials SET status='published' WHERE id=?")->execute([$id]);
        $msg = 'Published to the Store.';
        break;
    case 'promote':
        $db->prepare("UPDATE instructional_materials SET is_promoted=1 WHERE id=?")->execute([$id]);
        $msg = 'Promoted to the top of the Store.';
        break;
    case 'unpromote':
        $db->prepare("UPDATE instructional_materials SET is_promoted=0 WHERE id=?")->execute([$id]);
        $msg = 'Promotion removed.';
        break;
}

respond(true, ['message' => $msg]);
