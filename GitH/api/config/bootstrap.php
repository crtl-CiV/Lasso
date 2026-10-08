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

/** Parses a material year-level code like "2" or "1-3" into [min, max], or null if invalid. */
function parseYearRange(string $code): ?array {
    if (!preg_match('/^([1-5])(?:-([1-5]))?$/', trim($code), $m)) return null;
    $min = (int)$m[1];
    $max = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : $min;
    return $min <= $max ? [$min, $max] : null;
}

/** Display label: "1st Year" or "1st - 3rd Year". */
function yearRangeLabel(int $min, int $max): string {
    $short = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th'];
    return $min === $max ? $short[$min] . ' Year' : $short[$min] . ' - ' . $short[$max] . ' Year';
}

/** Fresh year level for a student, read from the DB (the session copy can be stale after an approved change). */
function studentYearInt(PDO $db, int $studentId): int {
    $st = $db->prepare('SELECT year_level FROM students WHERE id = ?');
    $st->execute([$studentId]);
    return yearToInt($st->fetchColumn());
}

/** A subscription only counts as active while its status is active AND it has not passed its expiry date. */
const SUB_ACTIVE_SQL = "status = 'active' AND expiry_date >= CURRENT_DATE";
