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
        // Two-step safety: the material must already be in the trash.
        if ($m['status'] !== 'trashed') {
            respond(false, 'Move this material to trash first before deleting it permanently.', 422);
        }
        // Active subscribers are allowed, but the admin must have confirmed the warning (force).
        $stmt = $db->prepare("SELECT COUNT(*) FROM subscriptions WHERE material_id = ? AND status = 'active' AND expiry_date >= CURRENT_DATE");
        $stmt->execute([$id]);
        $activeSubs = (int)$stmt->fetchColumn();
        if ($activeSubs > 0 && empty($in['force'])) {
            respond(false, "This material still has $activeSubs active subscriber(s). Confirm to delete it anyway.", 409);
        }

        $orphanReceipts = [];
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("SELECT DISTINCT billing_id FROM billing_statement_items WHERE material_id = ?");
            $stmt->execute([$id]);
            $billingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Everything that points at this material goes with it
            $db->prepare("DELETE FROM progress_tracker WHERE material_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM subscriptions WHERE material_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM billing_statement_items WHERE material_id = ?")->execute([$id]);

            // A not-yet-approved billing that only contained this material is now empty: remove it too
            foreach ($billingIds as $bid) {
                $c = $db->prepare("SELECT COUNT(*) FROM billing_statement_items WHERE billing_id = ?");
                $c->execute([$bid]);
                if ((int)$c->fetchColumn() > 0) continue;
                $p = $db->prepare("SELECT status, receipt_photo FROM billing_statements WHERE id = ?");
                $p->execute([$bid]);
                $bs = $p->fetch();
                if ($bs && $bs['status'] === 'pending') {
                    if (!empty($bs['receipt_photo'])) $orphanReceipts[] = $bs['receipt_photo'];
                    $db->prepare("DELETE FROM billing_statements WHERE id = ?")->execute([$bid]);
                }
            }
            $db->prepare("DELETE FROM instructional_materials WHERE id = ?")->execute([$id]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            respond(false, 'Could not delete this material: ' . $e->getMessage(), 500);
        }
        // Best-effort cleanup of its files in Supabase Storage
        if (!empty($m['cover_image'])) deleteStorageObjects(STORAGE_PUBLIC_BUCKET, [$m['cover_image']]);
        deleteStorageObjects(STORAGE_MATERIALS_BUCKET, listStorageObjects(STORAGE_MATERIALS_BUCKET, (string)$id));
        if ($orphanReceipts) deleteStorageObjects(STORAGE_MATERIALS_BUCKET, $orphanReceipts);
        $msg = 'Material permanently deleted' . ($activeSubs ? " (including $activeSubs active subscription(s))." : '.');
        break;
}

respond(true, ['message' => $msg]);
