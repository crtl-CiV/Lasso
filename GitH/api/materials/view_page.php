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
        respond(false, 'This material is not available for your course, year level or the current semester.', 403);
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

// File naming convention: {material_id}/page_{n}.jpg (in the private bucket).
// ?thumb=1 serves a small copy for the reader's page sidebar: {material_id}/thumb_{n}.jpg,
// made the first time it's asked for and kept in storage after that.
$wantThumb = !empty($_GET['thumb']);
$result = null;
if ($wantThumb) $result = downloadFromStorage(STORAGE_MATERIALS_BUCKET, $materialId . '/thumb_' . $page . '.jpg');

if (!$result) {
    foreach (['jpg', 'png', 'webp'] as $ext) {
        $result = downloadFromStorage(STORAGE_MATERIALS_BUCKET, $materialId . '/page_' . $page . '.' . $ext);
        if ($result) break;
    }
    if ($result && $wantThumb && ($small = makeThumbJpeg($result[0]))) {
        $tmp = tempnam(sys_get_temp_dir(), 'th');
        file_put_contents($tmp, $small);
        uploadToStorage(STORAGE_MATERIALS_BUCKET, $materialId . '/thumb_' . $page . '.jpg', $tmp, 'image/jpeg');   // best effort
        @unlink($tmp);
        $result = [$small, 'image/jpeg'];
    }
}

if (!$result) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Page image missing.';
    exit;
}
[$fileBytes, $fileMime] = $result;

// Reading progress is recorded separately (mark_read.php), after a page has been on screen for ~2 seconds.

// Kept only in this student's own browser for up to an hour ("private": never in shared caches),
// so scrolling back or reopening a material doesn't download every page again.
// Still shown inline only, never offered as a download.
header('Content-Type: ' . $fileMime);
header('Cache-Control: private, max-age=3600');
header('Vary: Cookie');
header('X-Frame-Options: SAMEORIGIN');
header('Content-Disposition: inline');
echo $fileBytes;
exit;
