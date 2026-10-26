<?php
// =========================================================
// LASSO — shared bootstrap included by every api/*.php endpoint
// =========================================================
error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak PHP errors as HTML into JSON responses

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

function jsonInput(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return $_POST ?: [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ($_POST ?: []);
}

function respond(bool $success, $payload = null, int $code = 200): void {
    http_response_code($code);
    $out = ['success' => $success];
    if ($success) {
        if (is_array($payload)) { $out = array_merge($out, $payload); }
        else { $out['data'] = $payload; }
    } else {
        $out['message'] = $payload ?? 'Something went wrong.';
    }
    echo json_encode($out);
    exit;
}

function requireStudent(): array {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
        respond(false, 'You must be logged in as a student.', 401);
    }
    return $_SESSION['user'];
}

function requireAdmin(): array {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        respond(false, 'You must be logged in as an administrator.', 401);
    }
    return $_SESSION['user'];
}

function genCode(string $prefix, int $len = 8): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $chars[random_int(0, strlen($chars) - 1)];
    return $prefix . '-' . $s;
}

/** Current semester + academic year, purely for labeling billing statements. */
function currentTerm(): array {
    $month = (int)date('n');
    $year = (int)date('Y');
    if ($month >= 8 || $month <= 0) { // Aug–Dec = 1st sem
        $sem = '1st Semester';
        $ay = $year . '-' . ($year + 1);
    } elseif ($month <= 5) { // Jan–May = 2nd sem
        $sem = '2nd Semester';
        $ay = ($year - 1) . '-' . $year;
    } else { // Jun–Jul = summer
        $sem = 'Summer Term';
        $ay = ($year - 1) . '-' . $year;
    }
    return [$sem, $ay];
}


// ---------------- Year level helpers ----------------
const YEAR_LABELS = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year'];

/** '3rd Year' -> 3 (0 if unrecognised). */
function yearToInt(?string $label): int {
    $n = (int)substr(trim((string)$label), 0, 1);
    return ($n >= 1 && $n <= 5) ? $n : 0;
}

/** Checkbox values like ["1","3"] (or "1,3") -> bitmask where bit n-1 means year/semester n.
 *  Returns 0 when empty or when any value is out of range 1..$max. */
function tagMask($values, int $max): int {
    if (is_string($values)) $values = $values === '' ? [] : explode(',', $values);
    if (!is_array($values)) return 0;
    $mask = 0;
    foreach ($values as $v) {
        $n = (int)trim((string)$v);
        if ($n < 1 || $n > $max) return 0;
        $mask |= (1 << ($n - 1));
    }
    return $mask;
}

/** "1st, 3rd Year" / "All Years" */
function yearMaskLabel(int $mask): string {
    if ($mask === 31) return 'All Years';
    $short = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th'];
    $parts = [];
    for ($n = 1; $n <= 5; $n++) if ($mask & (1 << ($n - 1))) $parts[] = $short[$n];
    return $parts ? implode(', ', $parts) . ' Year' : '—';
}

/** "1st Semester" / "2nd Semester" / "1st & 2nd Semester" */
function semesterMaskLabel(int $mask): string {
    return $mask === 3 ? '1st & 2nd Semester' : ($mask === 1 ? '1st Semester' : ($mask === 2 ? '2nd Semester' : '—'));
}

/** Fresh year level for a student, read from the DB (the session copy can be stale after an approved change). */
function studentYearInt(PDO $db, int $studentId): int {
    $st = $db->prepare('SELECT year_level FROM students WHERE id = ?');
    $st->execute([$studentId]);
    return yearToInt($st->fetchColumn());
}

/** A subscription only counts as active while its status is active AND it has not passed its expiry date. */
const SUB_ACTIVE_SQL = "status = 'active' AND expiry_date >= CURRENT_DATE";

/** 1 = 1st Semester, 2 = 2nd Semester, 0 = between semesters (Summer Term). */
function currentSemesterInt(): int {
    [$sem] = currentTerm();
    return $sem === '1st Semester' ? 1 : ($sem === '2nd Semester' ? 2 : 0);
}

/** Who is asking: this student's year level and the semester happening right now, as single bits. */
function eligibilityBits(PDO $db, int $studentId): array {
    $y = studentYearInt($db, $studentId);
    $s = currentSemesterInt();
    return ['ybit' => $y ? (1 << ($y - 1)) : 0, 'sbit' => $s ? (1 << ($s - 1)) : 0];
}

/** Year + semester exclusivity: true only if the material is tagged for this student's year AND the current semester. */
function materialOpenTo(array $m, array $bits): bool {
    return (((int)$m['year_mask'] & $bits['ybit']) !== 0) && (((int)$m['semester_mask'] & $bits['sbit']) !== 0);
}
