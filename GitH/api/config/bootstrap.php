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

// ---------- One login per account ----------
// Every login saves a new random token on the account. A browser whose token no longer
// matches (because the account logged in somewhere else) is logged out here.
const SESSION_REPLACED_MESSAGE = 'Your account was logged in on another device, so you were logged out here.';
$GLOBALS['SESSION_REPLACED'] = false;
if (!empty($_SESSION['user'])) {
    $u = $_SESSION['user'];
    $table = ($u['role'] ?? '') === 'student' ? 'students' : 'administrators';
    $st = getDB()->prepare("SELECT session_token FROM $table WHERE id = ?");
    $st->execute([(int)$u['id']]);
    $current = (string)$st->fetchColumn();
    if ($current === '' || empty($u['session_token']) || !hash_equals($current, (string)$u['session_token'])) {
        $_SESSION = [];
        $GLOBALS['SESSION_REPLACED'] = true;
    }
}

/** Starts a new login for this account and ends any login on another device. */
function startSession(string $table, int $id, array $user): array {
    $token = bin2hex(random_bytes(32));
    getDB()->prepare("UPDATE $table SET session_token = ? WHERE id = ?")->execute([$token, $id]);
    session_regenerate_id(true);
    $_SESSION['user'] = $user + ['session_token' => $token];
    $out = $_SESSION['user']; unset($out['session_token']);
    return $out;
}

/** 401 for a missing login; says so plainly when another device took over. */
function respondNotLoggedIn(string $message): void {
    if ($GLOBALS['SESSION_REPLACED']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => SESSION_REPLACED_MESSAGE, 'reason' => 'replaced']);
        exit;
    }
    respond(false, $message, 401);
}

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
        respondNotLoggedIn('You must be logged in as a student.');
    }
    return $_SESSION['user'];
}

function requireAdmin(): array {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        respondNotLoggedIn('You must be logged in as an administrator.');
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
    $st = $db->prepare('SELECT program_id FROM students WHERE id = ?');
    $st->execute([$studentId]);
    return ['ybit' => $y ? (1 << ($y - 1)) : 0, 'sbit' => $s ? (1 << ($s - 1)) : 0, 'pid' => (int)$st->fetchColumn()];
}

/** True when the material is tagged for this student's year, the current semester AND their course. */
function materialOpenTo(array $m, array $bits): bool {
    if ((((int)$m['year_mask'] & $bits['ybit']) === 0) || (((int)$m['semester_mask'] & $bits['sbit']) === 0)) return false;
    $st = getDB()->prepare('SELECT COUNT(*) AS n, COUNT(*) FILTER (WHERE program_id = ?) AS mine FROM material_programs WHERE material_id = ?');
    $st->execute([(int)($bits['pid'] ?? 0), (int)$m['id']]);
    $c = $st->fetch();
    return (int)$c['n'] === 0 || (int)$c['mine'] > 0;   // no courses saved = open to every course
}

/** SQL test for "material m is open to course :<param>" (no courses saved = every course). */
function courseOpenSql(string $param): string {
    return "(NOT EXISTS (SELECT 1 FROM material_programs mp WHERE mp.material_id = m.id)
             OR EXISTS (SELECT 1 FROM material_programs mp WHERE mp.material_id = m.id AND mp.program_id = :$param))";
}

/** SQL column: JSON list of the course names a material is open to ([] = every course). */
const COURSES_JSON_SQL = "(SELECT COALESCE(json_agg(json_build_object('id', cp.id, 'name', cp.name) ORDER BY cp.name), '[]'::json)
                           FROM material_programs mp JOIN college_programs cp ON cp.id = mp.program_id WHERE mp.material_id = m.id)";

/** Reads the course choice sent by the admin form: 'all' (or nothing) = every course, else a list of course ids. */
function parseProgramIds(PDO $db, $raw): array {
    if ($raw === null || $raw === 'all') return [];
    $ids = array_values(array_unique(array_filter(array_map('intval', is_array($raw) ? $raw : explode(',', (string)$raw)))));
    if (!$ids) respond(false, 'Tick at least one course, or tick "All departments and courses".', 422);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT COUNT(*) FROM college_programs WHERE id IN ($in)");
    $st->execute($ids);
    if ((int)$st->fetchColumn() !== count($ids)) respond(false, 'One of the ticked courses no longer exists. Reload the page and try again.', 422);
    return $ids;
}

/** Replaces the material's course list ([] = every course). */
function saveMaterialPrograms(PDO $db, int $materialId, array $programIds): void {
    $db->prepare('DELETE FROM material_programs WHERE material_id = ?')->execute([$materialId]);
    $ins = $db->prepare('INSERT INTO material_programs (material_id, program_id) VALUES (?, ?)');
    foreach ($programIds as $pid) $ins->execute([$materialId, $pid]);
}

// ---------- Password rules (registration, reset, change, new admins) ----------
/** Returns what is missing from the password, or null when it meets every rule. */
function passwordProblem(string $pw): ?string {
    $missing = [];
    if (strlen($pw) < 8) $missing[] = 'at least 8 characters';
    if (!preg_match('/[0-9]/', $pw)) $missing[] = '1 number';
    if (!preg_match('/[A-Z]/', $pw)) $missing[] = '1 capital letter';
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) $missing[] = '1 special character';
    return $missing ? 'Password needs ' . implode(', ', $missing) . '.' : null;
}

const ADMIN_POSITIONS = ['Head Administrator', 'Administrator', 'Cashier', 'Accounting Staff', 'IM Coordinator', 'Faculty', 'Staff'];

const SCHOOL_EMAIL_DOMAIN = '@students.isatu.edu.ph';
