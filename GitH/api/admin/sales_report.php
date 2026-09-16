<?php
// =========================================================
// LASSO — Sales Report generator.
// Supports grouping by material/department/program/year level/semester,
// plus optional filters (material, department, program, semester,
// academic year, date range), and returns grand totals for the
// filtered set so the report is self-contained and printable.
// =========================================================
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$groupBy = $_GET['group_by'] ?? 'material'; // material | department | program | year_level | semester
$materialId = (int)($_GET['material_id'] ?? 0);
$departmentId = (int)($_GET['department_id'] ?? 0);
$programId = (int)($_GET['program_id'] ?? 0);
$semester = trim($_GET['semester'] ?? '');
$academicYear = trim($_GET['academic_year'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$groupCols = [
    'material'   => ['m.title AS label', 'm.id'],
    'department' => ['d.name AS label', 'd.id'],
    'program'    => ['p.name AS label', 'p.id'],
    'year_level' => ['s.year_level AS label', 's.year_level'],
    'semester'   => ['b.semester AS label', 'b.semester'],
];
if (!isset($groupCols[$groupBy])) $groupBy = 'material';
[$selectLabel, $groupField] = $groupCols[$groupBy];

$sql = "SELECT $selectLabel, COUNT(*) AS total_subscriptions, COALESCE(SUM(bi.price),0) AS total_revenue
        FROM subscriptions sub
        JOIN instructional_materials m ON m.id = sub.material_id
        JOIN students s ON s.id = sub.student_id
        LEFT JOIN college_departments d ON d.id = s.department_id
        LEFT JOIN college_programs p ON p.id = s.program_id
        LEFT JOIN billing_statements b ON b.id = sub.billing_id
        LEFT JOIN billing_statement_items bi ON bi.billing_id = sub.billing_id AND bi.material_id = sub.material_id
        WHERE 1=1";
$params = [];
if ($materialId > 0)   { $sql .= " AND m.id = ?"; $params[] = $materialId; }
if ($departmentId > 0) { $sql .= " AND s.department_id = ?"; $params[] = $departmentId; }
if ($programId > 0)    { $sql .= " AND s.program_id = ?"; $params[] = $programId; }
if ($semester !== '')  { $sql .= " AND b.semester = ?"; $params[] = $semester; }
if ($academicYear !== '') { $sql .= " AND b.academic_year = ?"; $params[] = $academicYear; }
if ($dateFrom !== '')  { $sql .= " AND DATE(sub.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo !== '')    { $sql .= " AND DATE(sub.created_at) <= ?"; $params[] = $dateTo; }
$sql .= " GROUP BY $groupField ORDER BY total_subscriptions DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalSubs = (int)array_sum(array_column($rows, 'total_subscriptions'));
$totalRevenue = array_sum(array_map('floatval', array_column($rows, 'total_revenue')));

respond(true, [
    'group_by' => $groupBy,
    'report' => $rows,
    'summary' => ['total_subscriptions' => $totalSubs, 'total_revenue' => $totalRevenue],
    'filters_applied' => [
        'material_id' => $materialId ?: null,
        'department_id' => $departmentId ?: null,
        'program_id' => $programId ?: null,
        'semester' => $semester ?: null,
        'academic_year' => $academicYear ?: null,
        'date_from' => $dateFrom ?: null,
        'date_to' => $dateTo ?: null,
    ],
    'generated_at' => date('Y-m-d H:i:s'),
]);
