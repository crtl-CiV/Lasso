<?php
// Data for the printed IIRGP billing statement. Each call is one print:
// Rev. No. = how many times it was printed before (first print = 0), then the counter goes up by 1.
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.', 405);

$code = trim(jsonInput()['billing_code'] ?? '');
$db = getDB();
$st = $db->prepare("UPDATE billing_statements SET print_count = print_count + 1
                    WHERE billing_code = ? AND student_id = ?
                    RETURNING id, billing_code, total_amount, semester, academic_year, created_at, print_count - 1 AS rev_no");
$st->execute([$code, $user['id']]);
$b = $st->fetch();
if (!$b) respond(false, 'Billing statement not found.', 404);

$st = $db->prepare("SELECT s.full_name, s.student_id_number, s.year_level, s.section, d.name AS department, p.name AS program
                    FROM students s
                    JOIN college_departments d ON d.id = s.department_id
                    JOIN college_programs p ON p.id = s.program_id
                    WHERE s.id = ?");
$st->execute([$user['id']]);
$s = $st->fetch();

$st = $db->prepare("SELECT m.title, bi.price FROM billing_statement_items bi
                    JOIN instructional_materials m ON m.id = bi.material_id
                    WHERE bi.billing_id = ? ORDER BY bi.id");
$st->execute([$b['id']]);

// Printed dates use Philippine time (the server clock is UTC).
$created = (new DateTimeImmutable($b['created_at']))->setTimezone(new DateTimeZone('Asia/Manila'));
respond(true, ['statement' => [
    'billing_code'   => $b['billing_code'],
    'document_code'  => 'QF-IIRGP-' . $b['billing_code'],
    'rev_no'         => (int)$b['rev_no'],
    'effective_date' => $created->format('F j, Y'),
    'date'           => $created->format('m/d/Y'),
    'semester'       => $b['semester'],
    'academic_year'  => $b['academic_year'],
    'total'          => (float)$b['total_amount'],
    'student_name'   => $s['full_name'],
    'student_id_number' => $s['student_id_number'],
    'department'     => $s['department'],
    'program'        => $s['program'],
    'year_level'     => $s['year_level'],
    'section'        => $s['section'],
    'items'          => array_map(fn($i) => ['title' => $i['title'], 'price' => (float)$i['price']], $st->fetchAll()),
]]);
