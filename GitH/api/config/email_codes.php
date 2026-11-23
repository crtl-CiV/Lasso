<?php
// =========================================================
// LASSO — 6-digit email codes (shared rules)
// Used by student registration ('register') and new admin
// accounts ('admin'). Only a hash of each code is stored.
// Rules: valid 10 minutes, single use, 5 wrong tries, one per minute.
// =========================================================

/** Seconds left before another code may be sent to this email (0 = may send now). */
function emailCodeWait(PDO $db, string $email, string $purpose): int {
    $st = $db->prepare("SELECT EXTRACT(EPOCH FROM (NOW() - created_at))::int FROM email_verifications
                        WHERE email = ? AND purpose = ? ORDER BY created_at DESC LIMIT 1");
    $st->execute([$email, $purpose]);
    $ago = $st->fetchColumn();
    return ($ago !== false && (int)$ago < 60) ? 60 - (int)$ago : 0;
}

/** Creates a new code (older unused ones stop working) and returns it. */
function issueEmailCode(PDO $db, string $email, string $purpose): string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $db->prepare("UPDATE email_verifications SET used_at = NOW() WHERE email = ? AND purpose = ? AND used_at IS NULL")->execute([$email, $purpose]);
    $db->prepare("INSERT INTO email_verifications (email, purpose, code_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL '10 minutes')")
       ->execute([$email, $purpose, password_hash($code, PASSWORD_DEFAULT)]);
    return $code;
}

/**
 * Checks a typed code. On success the code is used up.
 * Returns [true, ''] or [false, message for the person].
 */
function checkEmailCode(PDO $db, string $email, string $purpose, string $code): array {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return [false, 'Enter the 6-digit code from the email.'];
    $st = $db->prepare("SELECT *, (expires_at < NOW()) AS expired FROM email_verifications
                        WHERE email = ? AND purpose = ? AND used_at IS NULL ORDER BY created_at DESC LIMIT 1");
    $st->execute([$email, $purpose]);
    $r = $st->fetch();
    $dead = 'This code is no longer valid. Please request a new one.';
    if (!$r || (int)$r['attempts'] >= 5) return [false, $dead];
    if ($r['expired']) return [false, 'This code has expired. Please request a new one.'];
    if (!password_verify($code, $r['code_hash'])) {
        $db->prepare("UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?")->execute([$r['id']]);
        $left = 5 - ((int)$r['attempts'] + 1);
        if ($left <= 0) {
            $db->prepare("UPDATE email_verifications SET used_at = NOW() WHERE id = ?")->execute([$r['id']]);
            return [false, 'Too many wrong tries. Please request a new code.'];
        }
        return [false, 'Incorrect code. ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.'];
    }
    $db->prepare("UPDATE email_verifications SET used_at = NOW(), verified_at = NOW() WHERE id = ?")->execute([$r['id']]);
    return [true, ''];
}
