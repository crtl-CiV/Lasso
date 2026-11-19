<?php
// Admin dashboard: KPI tiles (with their change), top materials / authors and the receipts waiting for review.
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$one = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();
$PURCHASE = "COALESCE(b.confirmed_at, b.created_at)";
$PAID = "FROM billing_statement_items bi JOIN billing_statements b ON b.id = bi.billing_id AND b.status = 'paid'";

// ---- Active subscribers (+ new/renewed this week that are still running) ----
$active = (int)$one("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND expiry_date >= CURRENT_DATE");
$activeWeek = (int)$one("SELECT COUNT(DISTINCT sub.id) $PAID
                         JOIN subscriptions sub ON sub.student_id = b.student_id AND sub.material_id = bi.material_id
                         WHERE $PURCHASE >= NOW() - INTERVAL '7 days' AND sub.status = 'active' AND sub.expiry_date >= CURRENT_DATE");

// ---- Subscriptions sold (+ this month) ----
$sold = (int)$one("SELECT COUNT(*) $PAID");
$soldMonth = (int)$one("SELECT COUNT(*) $PAID WHERE date_trunc('month', $PURCHASE) = date_trunc('month', NOW())");

// ---- Revenue (+ this semester vs the previous one) ----
$revenue = (float)$one("SELECT COALESCE(SUM(bi.price), 0) $PAID");
[$sem, $ay] = currentTerm();
[$y1, $y2] = array_map('intval', explode('-', $ay));
if ($sem === '1st Semester')      { $prevSem = '2nd Semester'; $prevAy = ($y1 - 1) . '-' . ($y2 - 1); }
elseif ($sem === '2nd Semester')  { $prevSem = '1st Semester'; $prevAy = $ay; }
else                              { $prevSem = '2nd Semester'; $prevAy = $ay; }   // Summer follows the 2nd semester
$revCur  = (float)$one("SELECT COALESCE(SUM(bi.price), 0) $PAID WHERE b.semester = ? AND b.academic_year = ?", [$sem, $ay]);
$revPrev = (float)$one("SELECT COALESCE(SUM(bi.price), 0) $PAID WHERE b.semester = ? AND b.academic_year = ?", [$prevSem, $prevAy]);
$revChange = $revPrev > 0 ? round((($revCur - $revPrev) / $revPrev) * 100, 1) : null;

// ---- Pending receipts ----
$pending = (int)$one("SELECT COUNT(*) FROM billing_statements WHERE status = 'pending' AND receipt_status = 'submitted'");

$topMaterials = $db->query("SELECT m.title, m.subject_code, au.name AS author_name, COUNT(*) AS subscribers
                             FROM subscriptions s
                             JOIN instructional_materials m ON m.id = s.material_id
                             LEFT JOIN authors au ON au.id = m.author_id
                             WHERE s.status = 'active' AND s.expiry_date >= CURRENT_DATE
                             GROUP BY m.id, au.name ORDER BY subscribers DESC, m.title LIMIT 5")->fetchAll();

// Top authors by CURRENTLY active subscriptions (drops as subscriptions expire)
$topAuthors = $db->query("SELECT au.name, COUNT(*) AS active_subscriptions
                           FROM subscriptions s
                           JOIN instructional_materials m ON m.id = s.material_id
                           JOIN authors au ON au.id = m.author_id
                           WHERE s.status = 'active' AND s.expiry_date >= CURRENT_DATE
                           GROUP BY au.id ORDER BY active_subscriptions DESC, au.name LIMIT 5")->fetchAll();

$awaiting = $db->query("SELECT b.billing_code, b.total_amount, b.receipt_number, b.receipt_submitted_at,
                                s.full_name, s.student_id_number
                         FROM billing_statements b JOIN students s ON s.id = b.student_id
                         WHERE b.status = 'pending' AND b.receipt_status = 'submitted'
                         ORDER BY b.receipt_submitted_at ASC LIMIT 5")->fetchAll();

respond(true, [
    'kpis' => [
        'active_subscribers' => $active, 'active_week' => $activeWeek,
        'subscriptions_sold' => $sold, 'sold_month' => $soldMonth,
        'total_revenue' => $revenue, 'revenue_change_pct' => $revChange, 'revenue_semester' => $revCur, 'previous_semester_label' => $prevSem,
        'pending_receipts' => $pending,
    ],
    'term' => ['semester' => $sem, 'academic_year' => $ay],
    'top_materials' => $topMaterials,
    'top_authors' => $topAuthors,
    'awaiting_review' => $awaiting,
]);
