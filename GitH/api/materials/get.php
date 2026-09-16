<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) respond(false, 'Material not specified.', 422);

$stmt = $db->prepare("SELECT m.*, d.name AS department_name, p.name AS program_name
                       FROM instructional_materials m
                       LEFT JOIN college_departments d ON d.id = m.department_id
                       LEFT JOIN college_programs p ON p.id = m.program_id
                       WHERE m.id = ? AND m.status = 'published'");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) respond(false, 'Material not found.', 404);

$stmt = $db->prepare("SELECT status FROM subscriptions WHERE student_id = ? AND material_id = ? AND status='active'");
$stmt->execute([$user['id'], $id]);
$subscribed = (bool)$stmt->fetch();

$nonBody = json_decode($m['non_body_pages'] ?? '[]', true) ?: [];
$excludedFromPreview = json_decode($m['preview_excluded_pages'] ?? '[]', true) ?: [];

// Build the ordered list of body pages (i.e. pages that count toward progress/preview)
$bodyPages = [];
for ($p = 1; $p <= (int)$m['total_pages']; $p++) {
    if (!in_array($p, $nonBody)) $bodyPages[] = $p;
}

if ($subscribed) {
    $accessiblePages = range(1, (int)$m['total_pages']);
} else {
    // Limited preview: first 5 body pages, minus any admin-excluded pages
    $previewBody = array_values(array_diff($bodyPages, $excludedFromPreview));
    $accessiblePages = array_slice($previewBody, 0, 5);
}

unset($m['file_path'], $m['non_body_pages'], $m['preview_excluded_pages']);
$m['is_subscribed'] = $subscribed;
$m['body_page_count'] = count($bodyPages);
$m['accessible_pages'] = $accessiblePages;

respond(true, ['material' => $m]);
