<?php
// =========================================================
// LASSO — Sales report by instructional material.
// One row per material, counting the paid purchases that fall inside the
// chosen filters. Every filter is optional.
//   Semester / Academic Year / dates -> when the purchase was made
//   Year level                       -> the year the MATERIAL is tagged for
//   Department / Course              -> the BUYER's department / course
// "Active" means the student's subscription is still running today.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$materialId   = (int)($_GET['material_id'] ?? 0);
$departmentId = (int)($_GET['department_id'] ?? 0);
$programId    = (int)($_GET['program_id'] ?? 0);   // "Course" in the UI
$yearLevel    = (int)($_GET['year_level'] ?? 0);    // 1..5
$semester     = trim($_GET['semester'] ?? '');
$academicYear = trim($_GET['academic_year'] ?? '');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');

$purchaseDate = "COALESCE(b.confirmed_at, b.created_at)";   // older records may lack confirmed_at
$isActive = "(sub.status = 'active' AND sub.expiry_date >= CURRENT_DATE)";

$sql = "SELECT m.id AS material_id, m.title, m.material_code, m.subject_code, m.price, m.year_mask, m.semester_mask,
               au.name AS author_name,
               COUNT(DISTINCT sub.id) AS subscribers,
               COUNT(DISTINCT sub.id) FILTER (WHERE $isActive) AS active,
               COUNT(bi.id) AS purchases,
               COALESCE(SUM(bi.price), 0) AS revenue,
               COALESCE(SUM(bi.price) FILTER (WHERE $isActive), 0) AS active_revenue
        FROM billing_statement_items bi
        JOIN billing_statements b ON b.id = bi.billing_id AND b.status = 'paid'
        JOIN subscriptions sub ON sub.student_id = b.student_id AND sub.material_id = bi.material_id
        JOIN students s ON s.id = b.student_id
        JOIN instructional_materials m ON m.id = bi.material_id
        LEFT JOIN authors au ON au.id = m.author_id
        WHERE 1=1";
$params = [];
if ($materialId > 0)   { $sql .= " AND m.id = ?";            $params[] = $materialId; }
if ($departmentId > 0) { $sql .= " AND s.department_id = ?"; $params[] = $departmentId; }
if ($programId > 0)    { $sql .= " AND s.program_id = ?";    $params[] = $programId; }
if ($yearLevel >= 1 && $yearLevel <= 5) { $sql .= " AND (m.year_mask & ?) <> 0"; $params[] = 1 << ($yearLevel - 1); }
if ($semester !== '')     { $sql .= " AND b.semester = ?";      $params[] = $semester; }
if ($academicYear !== '') { $sql .= " AND b.academic_year = ?"; $params[] = $academicYear; }
if ($dateFrom !== '')     { $sql .= " AND DATE($purchaseDate) >= ?"; $params[] = $dateFrom; }
if ($dateTo !== '')       { $sql .= " AND DATE($purchaseDate) <= ?"; $params[] = $dateTo; }
$sql .= " GROUP BY m.id, au.name ORDER BY SUM(bi.price) DESC, m.title";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$tot = ['subscribers' => 0, 'active' => 0, 'purchases' => 0, 'revenue' => 0.0, 'active_revenue' => 0.0];
foreach ($rows as &$r) {
    foreach (['subscribers', 'active', 'purchases'] as $k) $r[$k] = (int)$r[$k];
    foreach (['revenue', 'active_revenue', 'price'] as $k) $r[$k] = (float)$r[$k];
    $r['expired'] = $r['subscribers'] - $r['active'];
    $r['year_label'] = yearMaskLabel((int)$r['year_mask']);
    $r['semester_label'] = semesterMaskLabel((int)$r['semester_mask']);
    foreach (array_keys($tot) as $k) $tot[$k] += $r[$k];
}
unset($r);
foreach ($rows as &$r) {
    $r['share'] = $tot['revenue'] > 0 ? round($r['revenue'] / $tot['revenue'] * 100, 1) : 0;
    $r['active_share'] = $tot['active_revenue'] > 0 ? round($r['active_revenue'] / $tot['active_revenue'] * 100, 1) : 0;
}
unset($r);

// Author revenue/ranking — tied to CURRENTLY ACTIVE subscriptions only, so it drops as subscriptions expire.
$authorRows = $db->query("SELECT au.name AS author_name,
                                  COUNT(*) AS active_subscriptions,
                                  COALESCE(SUM(bi.price),0) AS active_revenue
                           FROM subscriptions sub
                           JOIN instructional_materials m ON m.id = sub.material_id
                           JOIN authors au ON au.id = m.author_id
                           LEFT JOIN billing_statement_items bi ON bi.billing_id = sub.billing_id AND bi.material_id = sub.material_id
                           WHERE sub.status = 'active' AND sub.expiry_date >= CURRENT_DATE
                           GROUP BY au.id ORDER BY active_subscriptions DESC, au.name")->fetchAll();

respond(true, [
    'report' => $rows,
    'summary' => [
        'materials' => count($rows),
        'total_subscriptions' => $tot['purchases'],   // paid purchases (a renewal counts again)
        'subscribers' => $tot['subscribers'], 'active' => $tot['active'], 'expired' => $tot['subscribers'] - $tot['active'],
        'total_revenue' => $tot['revenue'], 'active_revenue' => $tot['active_revenue'],
    ],
    'author_ranking' => $authorRows,
    'filters_applied' => [
        'material_id' => $materialId ?: null,
        'department_id' => $departmentId ?: null,
        'program_id' => $programId ?: null,
        'year_level' => ($yearLevel >= 1 && $yearLevel <= 5) ? yearMaskLabel(1 << ($yearLevel - 1)) : null,
        'semester' => $semester ?: null,
        'academic_year' => $academicYear ?: null,
        'date_from' => $dateFrom ?: null,
        'date_to' => $dateTo ?: null,
    ],
    'generated_at' => date('Y-m-d H:i:s'),
]);
