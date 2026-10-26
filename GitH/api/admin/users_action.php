<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
$self = requireAdmin();
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

// Never let the system lock itself out: no trashing/deleting your own account or the last active admin.
if ($type === 'admin' && in_array($action, ['trash', 'delete'], true)) {
    if ($id === (int)$self['id']) respond(false, 'You cannot ' . $action . ' your own account.', 422);
    $stmt = $db->prepare("SELECT COUNT(*) FROM administrators WHERE status = 'active' AND id <> ?");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() === 0) respond(false, 'This is the last active admin account, so it cannot be removed.', 422);
}

if ($action === 'delete') {
    $stmt = $db->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) respond(false, 'Account not found.', 404);
    // Two-step safety: the account must already be in the trash.
    if ($u['status'] !== 'trashed') {
        respond(false, 'Move this account to trash first before deleting it permanently.', 422);
    }

    $privatePaths = [];
    if ($type === 'student') {
        // Active subscriptions are allowed, but only after the admin confirmed the warning (force).
        $stmt = $db->prepare("SELECT COUNT(*) FROM subscriptions WHERE student_id = ? AND status = 'active' AND expiry_date >= CURRENT_DATE");
        $stmt->execute([$id]);
        $activeSubs = (int)$stmt->fetchColumn();
        if ($activeSubs > 0 && empty($in['force'])) {
            respond(false, "This student still has $activeSubs active subscription(s). Confirm to delete the account anyway.", 409);
        }

        $stmt = $db->prepare("SELECT id_photo FROM year_level_requests WHERE student_id = ?");
        $stmt->execute([$id]);
        $privatePaths = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $db->prepare("SELECT receipt_photo FROM billing_statements WHERE student_id = ? AND receipt_photo IS NOT NULL");
        $stmt->execute([$id]);
        $privatePaths = array_merge($privatePaths, $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    try {
        $db->beginTransaction();
        if ($type === 'student') {
            foreach (['subscriptions', 'progress_tracker', 'cart_items', 'validation_codes', 'billing_statements', 'year_level_requests'] as $t) {
                $db->prepare("DELETE FROM $t WHERE student_id = ?")->execute([$id]);   // billing items go with their billing
            }
        } else {
            // Keep the materials/payments this admin handled; just detach their name
            $db->prepare("UPDATE instructional_materials SET uploaded_by = NULL WHERE uploaded_by = ?")->execute([$id]);
            $db->prepare("UPDATE billing_statements SET confirmed_by = NULL WHERE confirmed_by = ?")->execute([$id]);
        }
        $db->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        respond(false, 'Could not delete this account: ' . $e->getMessage(), 500);
    }

    // Best-effort cleanup of their files in Supabase Storage
    if ($privatePaths) deleteStorageObjects(STORAGE_MATERIALS_BUCKET, array_values($privatePaths));
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
