<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$id = (int)($in['id'] ?? 0);
if (!$id) respond(false, 'Material not specified.', 422);

$db = getDB();
$stmt = $db->prepare("SELECT id FROM instructional_materials WHERE id = ?");
$stmt->execute([$id]);
if (!$stmt->fetch()) respond(false, 'Material not found.', 404);

$fields = [];
$params = [];
foreach (['title','description','price','year_level'] as $f) {
    if (isset($in[$f])) { $fields[] = "$f = ?"; $params[] = $in[$f]; }
}
if (isset($in['department_id'])) { $fields[] = "department_id = ?"; $params[] = (int)$in['department_id'] ?: null; }
if (isset($in['program_id'])) { $fields[] = "program_id = ?"; $params[] = (int)$in['program_id'] ?: null; }
if (isset($in['non_body_pages'])) { $fields[] = "non_body_pages = ?"; $params[] = json_encode($in['non_body_pages']); }
if (isset($in['preview_excluded_pages'])) { $fields[] = "preview_excluded_pages = ?"; $params[] = json_encode($in['preview_excluded_pages']); }
if (isset($in['status']) && in_array($in['status'], ['draft','published'], true)) { $fields[] = "status = ?"; $params[] = $in['status']; }

if (!$fields) respond(false, 'Nothing to update.', 422);

$params[] = $id;
$stmt = $db->prepare("UPDATE instructional_materials SET " . implode(', ', $fields) . " WHERE id = ?");
$stmt->execute($params);

respond(true, ['message' => 'Material updated successfully.']);
