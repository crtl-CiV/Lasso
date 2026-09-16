<?php
// =========================================================
// LASSO — Upload an instructional material.
//
// Three modes, all through this one endpoint:
//
// 1) CREATE          (no material_id, no pages[])
//    -> creates a draft material record (fields + optional cover_image),
//       returns material_id. Used as step 1 of the PDF upload flow.
//
// 2) CREATE + PAGES   (no material_id, pages[] present)
//    -> original single-shot behaviour: creates the material AND saves
//       the given page images immediately. Used by "Upload Page Images".
//
// 3) APPEND PAGES     (material_id present)
//    -> appends pages[] to an existing material, continuing the page
//       numbering, and bumps total_pages. Used by the PDF upload flow
//       to add pages in small batches (avoids PHP's default
//       max_file_uploads / post_max_size limits on big documents).
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
$admin = requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$db = getDB();
$materialId = (int)($_POST['material_id'] ?? 0);

$allowedPage = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function saveCover(int $materialId): ?string {
    global $allowedPage;
    if (empty($_FILES['cover_image']) || $_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) return null;
    $mime = mime_content_type($_FILES['cover_image']['tmp_name']);
    if (!isset($allowedPage[$mime])) return null;
    $dir = __DIR__ . '/../uploads/covers/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $fn = 'cover_' . $materialId . '_' . time() . '.' . $allowedPage[$mime];
    move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . $fn);
    return 'uploads/covers/' . $fn;
}

function savePagesBatch(int $materialId, int $startingAt): int {
    global $allowedPage;
    if (empty($_FILES['pages']) || empty($_FILES['pages']['name'][0])) return 0;
    $dir = __DIR__ . '/../uploads/materials/' . $materialId . '/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $count = count($_FILES['pages']['name']);
    $saved = 0;
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['pages']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $mime = mime_content_type($_FILES['pages']['tmp_name'][$i]);
        if (!isset($allowedPage[$mime])) continue;
        $saved++;
        $fn = 'page_' . ($startingAt + $saved) . '.' . $allowedPage[$mime];
        move_uploaded_file($_FILES['pages']['tmp_name'][$i], $dir . $fn);
    }
    return $saved;
}

// ---------------------------------------------------------
// MODE 3: append a batch of pages to an existing material
// ---------------------------------------------------------
if ($materialId > 0) {
    $stmt = $db->prepare("SELECT id, total_pages, cover_image FROM instructional_materials WHERE id = ?");
    $stmt->execute([$materialId]);
    $m = $stmt->fetch();
    if (!$m) respond(false, 'Material not found.', 404);

    $savedCount = savePagesBatch($materialId, (int)$m['total_pages']);
    $newTotal = (int)$m['total_pages'] + $savedCount;

    // If this material still has no cover and a cover was sent with this batch, set it.
    $coverPath = $m['cover_image'];
    if (!$coverPath) {
        $newCover = saveCover($materialId);
        if ($newCover) $coverPath = $newCover;
    }

    $stmt = $db->prepare("UPDATE instructional_materials SET total_pages = ?, cover_image = ? WHERE id = ?");
    $stmt->execute([$newTotal, $coverPath, $materialId]);

    respond(true, ['message' => "Saved $savedCount page(s).", 'material_id' => $materialId, 'total_pages' => $newTotal, 'pages_saved_this_batch' => $savedCount]);
}

// ---------------------------------------------------------
// MODE 1 / 2: create a new material (optionally with pages)
// ---------------------------------------------------------
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$departmentId = (int)($_POST['department_id'] ?? 0) ?: null;
$programId = (int)($_POST['program_id'] ?? 0) ?: null;
$yearLevel = trim($_POST['year_level'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$nonBodyPages = json_decode($_POST['non_body_pages'] ?? '[]', true) ?: [];
$previewExcluded = json_decode($_POST['preview_excluded_pages'] ?? '[]', true) ?: [];

if (!$title || $price < 0) respond(false, 'Title and a valid price are required.', 422);

$hasPagesNow = !empty($_FILES['pages']) && !empty($_FILES['pages']['name'][0]);
// If the caller explicitly says more pages are coming in later batches
// (the PDF flow), we allow creating with zero pages for now.
$expectMoreBatches = !empty($_POST['expect_more_batches']);

if (!$hasPagesNow && !$expectMoreBatches) {
    respond(false, 'Please upload at least one page image for the material.', 422);
}

$code = genCode('LM', 6);
$stmt = $db->prepare("INSERT INTO instructional_materials
    (material_code, title, description, department_id, program_id, year_level, price, total_pages, non_body_pages, preview_excluded_pages, status, uploaded_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'draft', ?)");
$stmt->execute([$code, $title, $description, $departmentId, $programId, $yearLevel, $price, json_encode($nonBodyPages), json_encode($previewExcluded), $admin['id']]);
$materialId = (int)$db->lastInsertId();

$coverPath = saveCover($materialId);
$savedCount = $hasPagesNow ? savePagesBatch($materialId, 0) : 0;

$stmt = $db->prepare("UPDATE instructional_materials SET total_pages = ?, cover_image = ? WHERE id = ?");
$stmt->execute([$savedCount, $coverPath, $materialId]);

respond(true, [
    'message' => $expectMoreBatches ? 'Material created. Ready to receive pages.' : 'Material uploaded as draft.',
    'material_id' => $materialId,
    'material_code' => $code,
    'total_pages' => $savedCount,
]);
