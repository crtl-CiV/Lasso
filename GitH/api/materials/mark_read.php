<?php
// =========================================================
// LASSO — Record that a subscribed student read a page.
// The scrolling reader calls this once a page has been on
// screen for about 2 seconds. Only body pages count toward
// the percentage; last_page_read lets the reader reopen there.
// POST JSON: { material_id, page }
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$in = jsonInput();
$materialId = (int)($in['material_id'] ?? 0);
$page = (int)($in['page'] ?? 0);

$db = getDB();
$st = $db->prepare("SELECT m.total_pages, m.non_body_pages FROM instructional_materials m
                     JOIN subscriptions s ON s.material_id = m.id AND s.student_id = ?
                     WHERE m.id = ? AND s.status = 'active' AND s.expiry_date >= CURRENT_DATE");
$st->execute([$user['id'], $materialId]);
$m = $st->fetch();
if (!$m) respond(false, 'You do not have an active subscription to this material.', 403);
if ($page < 1 || $page > (int)$m['total_pages']) respond(false, 'Page out of range.', 422);

$nonBody = array_map('intval', json_decode($m['non_body_pages'] ?? '[]', true) ?: []);
$bodyTotal = 0;
for ($p = 1; $p <= (int)$m['total_pages']; $p++) if (!in_array($p, $nonBody, true)) $bodyTotal++;

$st = $db->prepare("SELECT pages_read FROM progress_tracker WHERE student_id = ? AND material_id = ?");
$st->execute([$user['id'], $materialId]);
$row = $st->fetch();
$pagesRead = $row ? array_map('intval', json_decode($row['pages_read'] ?? '[]', true) ?: []) : [];
if (!in_array($page, $nonBody, true) && !in_array($page, $pagesRead, true)) $pagesRead[] = $page;
sort($pagesRead);
$percent = $bodyTotal ? round(count($pagesRead) / $bodyTotal * 100, 2) : 0;

$db->prepare("INSERT INTO progress_tracker (student_id, material_id, pages_read, last_page_read, percent_complete)
              VALUES (?, ?, ?, ?, ?)
              ON CONFLICT ON CONSTRAINT uq_progress
              DO UPDATE SET pages_read = EXCLUDED.pages_read, last_page_read = EXCLUDED.last_page_read,
                            percent_complete = EXCLUDED.percent_complete")
   ->execute([$user['id'], $materialId, json_encode($pagesRead), $page, $percent]);

respond(true, ['percent_complete' => $percent, 'pages_read' => count($pagesRead), 'body_pages' => $bodyTotal]);
