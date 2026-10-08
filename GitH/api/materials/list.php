<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$db = getDB();
$myYear = studentYearInt($db, (int)$user['id']);

$search = trim($_GET['search'] ?? '');
$programId = (int)($_GET['program_id'] ?? 0);

// NOTE: PDO with real (non-emulated) prepared statements does not allow the
// same named placeholder to be reused more than once in a single query, so
// each repeated value below gets its own distinct placeholder name.
$sql = "SELECT m.id, m.material_code, m.title, m.description, m.price, m.cover_image,
               m.total_pages, m.is_promoted, m.created_at, m.year_level,
               d.name AS department_name, p.name AS program_name, au.name AS author_name,
               EXISTS(SELECT 1 FROM subscriptions s WHERE s.student_id = :sid1 AND s.material_id = m.id AND s.status='active' AND s.expiry_date >= CURRENT_DATE) AS is_subscribed,
               EXISTS(SELECT 1 FROM cart_items c WHERE c.student_id = :sid2 AND c.material_id = m.id) AS in_cart
        FROM instructional_materials m
        LEFT JOIN college_departments d ON d.id = m.department_id
        LEFT JOIN college_programs p ON p.id = m.program_id
        LEFT JOIN authors au ON au.id = m.author_id
        WHERE m.status = 'published'
          AND (m.year_min <= :myyear
               OR EXISTS(SELECT 1 FROM subscriptions s2 WHERE s2.student_id = :sid3 AND s2.material_id = m.id))";
$params = ['sid1' => $user['id'], 'sid2' => $user['id'], 'sid3' => $user['id'], 'myyear' => $myYear];

if ($search !== '') {
    $sql .= " AND (m.title LIKE :q1 OR m.material_code LIKE :q2)";
    $params['q1'] = "%$search%";
    $params['q2'] = "%$search%";
}
if ($programId > 0) {
    $sql .= " AND m.program_id = :pid";
    $params['pid'] = $programId;
}
$sql .= " ORDER BY m.is_promoted DESC, m.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['is_subscribed'] = (bool)$r['is_subscribed'];
    $r['in_cart'] = (bool)$r['in_cart'];
    $r['is_promoted'] = (bool)$r['is_promoted'];
}
respond(true, ['materials' => $rows]);
