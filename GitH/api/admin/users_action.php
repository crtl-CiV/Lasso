<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$id = (int)($in['id'] ?? 0);
$type = $in['type'] ?? '';       // 'student' | 'admin'
$action = $in['action'] ?? '';   // 'trash' | 'restore' | 'delete'
if (!$id || !in_array($type, ['student','admin'], true) || !in_array($action, ['trash','restore','delete'], true)) {
    respond(false, 'Invalid request.', 422);
}

$table = $type === 'student' ? 'students' : 'administrators';
$db = getDB();

if ($action === 'delete') {
    // Safety guardrail: only permanently delete accounts already trashed.
    $stmt = $db->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) respond(false, 'Account not found.', 404);
    if ($u['status'] !== 'trashed') {
        respond(false, 'Move this account to trash first before deleting it permanently.', 422);
    }

    try {
        $db->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
    } catch (PDOException $e) {
        // Foreign key violation — this account has billing/subscription
        // history (students) or uploaded materials (admins) and can't be
        // hard-deleted without breaking those records.
        $noun = $type === 'student' ? 'orders or subscriptions' : 'uploaded materials';
        respond(false, "This account has existing $noun and cannot be permanently deleted.", 409);
    }

    // Best-effort cleanup of their files in Supabase Storage (students only)
    if ($type === 'student') {
        $paths = array_filter([$u['id_photo_front'] ?? null, $u['id_photo_back'] ?? null, $u['profile_photo'] ?? null]);
        if ($paths) deleteStorageObjects(STORAGE_PUBLIC_BUCKET, array_values($paths));
    }

    respond(true, ['message' => 'Account permanently deleted.']);
}

$newStatus = $action === 'trash' ? 'trashed' : 'active';
$stmt = $db->prepare("UPDATE $table SET status = ? WHERE id = ?");
$stmt->execute([$newStatus, $id]);

respond(true, ['message' => ($action === 'trash' ? 'Account moved to trash/archive.' : 'Account restored successfully.')]);
