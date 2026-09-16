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
