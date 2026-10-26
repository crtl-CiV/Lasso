<?php
// =========================================================
// LASSO — Sales Report.
// One row per student+material subscription, counting the paid purchases
// that fall inside the chosen filters (so a renewal inside the period shows
// as 2 terms). Every filter is optional.
//   Semester / Academic Year / dates -> when the purchase was made
//   Year level                       -> the year the MATERIAL is tagged for
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

$sql = "SELECT sub.id AS subscription_id,
               s.student_id_number, s.full_name,
               d.name AS department_name, p.name AS course_name,
               m.title, m.material_code, m.year_mask, m.semester_mask, au.name AS author_name,
               sub.start_date, sub.expiry_date, sub.semester_count,
               (sub.status = 'active' AND sub.expiry_date >= CURRENT_DATE) AS is_active,
               COUNT(bi.id) AS purchases,
               COALESCE(SUM(bi.price), 0) AS revenue,
               MIN($purchaseDate) AS first_purchased,
               string_agg(DISTINCT b.semester, ', ') AS semesters_purchased
        FROM billing_statement_items bi
        JOIN billing_statements b ON b.id = bi.billing_id AND b.status = 'paid'
        JOIN subscriptions sub ON sub.student_id = b.student_id AND sub.material_id = bi.material_id
        JOIN students s ON s.id = b.student_id
        JOIN instructional_materials m ON m.id = bi.material_id
        LEFT JOIN authors au ON au.id = m.author_id
        LEFT JOIN college_departments d ON d.id = s.department_id
        LEFT JOIN college_programs p ON p.id = s.program_id
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
$sql .= " GROUP BY sub.id, s.id, d.name, p.name, m.id, au.name
          ORDER BY MIN($purchaseDate) DESC, s.full_name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$sum = ['purchases' => 0, 'revenue' => 0.0];
$sumActive = ['purchases' => 0, 'revenue' => 0.0];
foreach ($rows as &$r) {
    $r['is_active'] = (bool)$r['is_active'];
    $r['purchases'] = (int)$r['purchases'];
    $r['revenue'] = (float)$r['revenue'];
    $r['year_label'] = yearMaskLabel((int)$r['year_mask']);
    $r['semester_label'] = semesterMaskLabel((int)$r['semester_mask']);
    $sum['purchases'] += $r['purchases'];       $sum['revenue'] += $r['revenue'];
    if ($r['is_active']) { $sumActive['purchases'] += $r['purchases']; $sumActive['revenue'] += $r['revenue']; }
}
unset($r);

// Author revenue/ranking — tied to CURRENTLY ACTIVE subscriptions only,
// so both the count and revenue drop as subscriptions expire.
$authorRows = $db->query("SELECT au.name AS author_name,
                                  COUNT(*) AS active_subscriptions,
                                  COALESCE(SUM(bi.price),0) AS active_revenue
                           FROM subscriptions sub
                           JOIN instructional_materials m ON m.id = sub.material_id
                           JOIN authors au ON au.id = m.author_id
                           LEFT JOIN billing_statement_items bi ON bi.billing_id = sub.billing_id AND bi.material_id = sub.material_id
                           WHERE sub.status = 'active' AND sub.expiry_date >= CURRENT_DATE
                           GROUP BY au.id ORDER BY active_subscriptions DESC")->fetchAll();

respond(true, [
    'report' => $rows,
    'summary' => ['total_subscriptions' => $sum['purchases'], 'total_revenue' => $sum['revenue']],
    'summary_active' => ['total_subscriptions' => $sumActive['purchases'], 'total_revenue' => $sumActive['revenue']],
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
