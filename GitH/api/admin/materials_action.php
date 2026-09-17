<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$id = (int)($in['id'] ?? 0);
$action = $in['action'] ?? '';
if (!$id || !in_array($action, ['trash','restore','promote','unpromote','publish','delete'], true)) {
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
        $db->prepare("UPDATE instructional_materials SET is_promoted=TRUE WHERE id=?")->execute([$id]);
        $msg = 'Promoted to the top of the Store.';
        break;
    case 'unpromote':
        $db->prepare("UPDATE instructional_materials SET is_promoted=FALSE WHERE id=?")->execute([$id]);
        $msg = 'Promotion removed.';
        break;
    case 'delete':
        // Safety guardrail: only permanently delete materials that are
        // already trashed, so an accidental click can't nuke a live listing.
        if ($m['status'] !== 'trashed') {
            respond(false, 'Move this material to trash first before deleting it permanently.', 422);
        }
        try {
            $db->prepare("DELETE FROM instructional_materials WHERE id=?")->execute([$id]);
        } catch (PDOException $e) {
            // Most likely a foreign key violation — this material has
            // purchase/subscription history and can't be hard-deleted.
            respond(false, 'This material has existing orders or subscriptions and cannot be permanently deleted.', 409);
        }
        // Best-effort cleanup of its files in Supabase Storage
        if (!empty($m['cover_image'])) {
            deleteStorageObjects(STORAGE_PUBLIC_BUCKET, [$m['cover_image']]);
        }
        $pagePaths = listStorageObjects(STORAGE_MATERIALS_BUCKET, (string)$id);
        deleteStorageObjects(STORAGE_MATERIALS_BUCKET, $pagePaths);
        $msg = 'Material permanently deleted.';
        break;
}

respond(true, ['message' => $msg]);
