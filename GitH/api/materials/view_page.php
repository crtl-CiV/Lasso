<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$db = getDB();

$materialId = (int)($_GET['material_id'] ?? 0);
$page = (int)($_GET['page'] ?? 0);
if (!$materialId || !$page) respond(false, 'Invalid request.', 422);

$stmt = $db->prepare("SELECT * FROM instructional_materials WHERE id = ? AND status='published'");
$stmt->execute([$materialId]);
$m = $stmt->fetch();
if (!$m) respond(false, 'Material not found.', 404);

$stmt = $db->prepare("SELECT status FROM subscriptions WHERE student_id = ? AND material_id = ? AND status='active'");
$stmt->execute([$user['id'], $materialId]);
$subscribed = (bool)$stmt->fetch();

if (!$subscribed) {
    $nonBody = json_decode($m['non_body_pages'] ?? '[]', true) ?: [];
    $excluded = json_decode($m['preview_excluded_pages'] ?? '[]', true) ?: [];
    $bodyPages = [];
    for ($p = 1; $p <= (int)$m['total_pages']; $p++) if (!in_array($p, $nonBody)) $bodyPages[] = $p;
    $previewAllowed = array_slice(array_values(array_diff($bodyPages, $excluded)), 0, 5);
    if (!in_array($page, $previewAllowed, true)) {
        respond(false, 'Subscribe to this material to view this page.', 403);
    }
} elseif ($page < 1 || $page > (int)$m['total_pages']) {
    respond(false, 'Page out of range.', 404);
}

// File naming convention: uploads/materials/{material_id}/page_{n}.jpg
$dir = __DIR__ . '/../uploads/materials/' . $materialId . '/';
$candidates = [$dir . "page_$page.jpg", $dir . "page_$page.png", $dir . "page_$page.webp"];
$file = null;
foreach ($candidates as $c) if (is_file($c)) { $file = $c; break; }

if (!$file) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Page image missing.';
    exit;
}

// Log progress for subscribed students (body pages only)
if ($subscribed) {
    $nonBody = json_decode($m['non_body_pages'] ?? '[]', true) ?: [];
    if (!in_array($page, $nonBody)) {
        $stmt = $db->prepare("SELECT pages_read FROM progress_tracker WHERE student_id=? AND material_id=?");
        $stmt->execute([$user['id'], $materialId]);
        $row = $stmt->fetch();
        $pagesRead = $row ? (json_decode($row['pages_read'], true) ?: []) : [];
        if (!in_array($page, $pagesRead)) $pagesRead[] = $page;
        $bodyTotal = 0;
        for ($p = 1; $p <= (int)$m['total_pages']; $p++) if (!in_array($p, $nonBody)) $bodyTotal++;
        $percent = $bodyTotal ? round(count($pagesRead) / $bodyTotal * 100, 2) : 0;
        $stmt = $db->prepare("INSERT INTO progress_tracker (student_id, material_id, pages_read, last_page_read, percent_complete)
                               VALUES (?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE pages_read=VALUES(pages_read), last_page_read=VALUES(last_page_read), percent_complete=VALUES(percent_complete)");
        $stmt->execute([$user['id'], $materialId, json_encode($pagesRead), $page, $percent]);
    }
}

// Anti-piracy headers: no caching, inline only, no content-disposition download
header('Content-Type: ' . mime_content_type($file));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: SAMEORIGIN');
header('Content-Disposition: inline');
readfile($file);
exit;
