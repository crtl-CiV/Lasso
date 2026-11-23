<?php
// Checks the "Register admin" form. Used before the email code is sent
// (admin_send_code.php) and again when the account is created (register_admin.php).
// Stops the request with a message if anything is wrong; otherwise returns the cleaned values.
function validateNewAdmin(PDO $db, array $in): array {
    $f = [
        'admin_id'  => trim($in['admin_id'] ?? ''),
        'email'     => strtolower(trim($in['email'] ?? '')),
        'full_name' => trim($in['full_name'] ?? ''),
        'position'  => trim($in['position'] ?? ''),
        'password'  => (string)($in['password'] ?? ''),
    ];
    $confirm = (string)($in['password_confirm'] ?? '');
    if ($f['admin_id'] === '' || $f['email'] === '' || $f['full_name'] === '' || $f['position'] === '' || $f['password'] === '') {
        respond(false, 'All fields are required.', 422);
    }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) respond(false, 'Please enter a valid email address.', 422);
    if (!in_array($f['position'], ADMIN_POSITIONS, true)) respond(false, 'Please choose a position from the list.', 422);
    if ($problem = passwordProblem($f['password'])) respond(false, $problem, 422);
    if ($f['password'] !== $confirm) respond(false, 'The two passwords do not match.', 422);

    $st = $db->prepare("SELECT admin_id, LOWER(email) AS email FROM administrators WHERE admin_id = ? OR LOWER(email) = ?");
    $st->execute([$f['admin_id'], $f['email']]);
    if ($row = $st->fetch()) {
        respond(false, $row['admin_id'] === $f['admin_id'] ? 'That Admin ID is already in use.' : 'That email already belongs to an admin account.', 409);
    }
    return $f;
}
