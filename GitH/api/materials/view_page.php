<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage.php';
$user = requireStudent();
$db = getDB();

$materialId = (int)($_GET['material_id'] ?? 0);
$page = (int)($_GET['page'] ?? 0);
if (!$materialId || !$page) respond(false, 'Invalid request.', 422);

$stmt = $db->prepare("SELECT * FROM instructional_materials WHERE id = ? AND status='published'");
$stmt->execute([$materialId]);
$m = $stmt->fetch();
if (!$m) respond(false, 'Material not found.', 404);

$stmt = $db->prepare("SELECT status FROM subscriptions WHERE student_id = ? AND material_id = ? AND status='active' AND expiry_date >= CURRENT_DATE");
$stmt->execute([$user['id'], $materialId]);
$subscribed = (bool)$stmt->fetch();

if (!$subscribed) {
    if (!materialOpenTo($m, eligibilityBits($db, (int)$user['id']))) {
        respond(false, 'This material is not available for your year level or the current semester.', 403);
    }
    $nonBody = json_decode($m['non_body_pages'] ?? '[]', true) ?: [];
    $bodyPages = [];
    for ($p = 1; $p <= (int)$m['total_pages']; $p++) if (!in_array($p, $nonBody)) $bodyPages[] = $p;
    $previewAllowed = array_slice($bodyPages, 0, 5);
    if (!in_array($page, $previewAllowed, true)) {
        respond(false, 'Subscribe to this material to view this page.', 403);
    }
} elseif ($page < 1 || $page > (int)$m['total_pages']) {
    respond(false, 'Page out of range.', 404);
}

// File naming convention: {material_id}/page_{n}.jpg (in the private bucket)
$extensions = ['jpg', 'png', 'webp'];
$result = null;
foreach ($extensions as $ext) {
    $objectPath = $materialId . '/page_' . $page . '.' . $ext;
    $result = downloadFromStorage(STORAGE_MATERIALS_BUCKET, $objectPath);
    if ($result) break;
}

if (!$result) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Page image missing.';
    exit;
}
[$fileBytes, $fileMime] = $result;

// Reading progress is recorded separately (mark_read.php), after a page has been on screen for ~2 seconds.

// Anti-piracy headers: no caching, inline only, no content-disposition download
header('Content-Type: ' . $fileMime);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: SAMEORIGIN');
header('Content-Disposition: inline');
echo $fileBytes;
exit;
