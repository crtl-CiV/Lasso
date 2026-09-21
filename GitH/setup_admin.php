<?php
require_once __DIR__ . '/api/config/db.php';
header('Content-Type: text/html; charset=utf-8');

$db = getDB();
$count = $db->query("SELECT COUNT(*) c FROM administrators")->fetch()['c'];

if ($count > 0) {
    echo "<p>An administrator account already exists. This setup script is now locked. Delete <code>setup_admin.php</code> from your server for security.</p>";
    exit;
}

// Since login is now email-based for everyone, the head admin needs a real
// email to actually log in. Pass it as ?email=you@example.com when visiting
// this script, or it falls back to a placeholder you'll need to update
// directly in the database afterward.
$email = trim($_GET['email'] ?? '') ?: 'admin001@example.com';

$hash = password_hash('Admin@123', PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO administrators (admin_id, email, password_hash, full_name, position, role, status) VALUES (?, ?, ?, ?, ?, 'admin', 'active')");
$stmt->execute(['admin001', $email, $hash, 'Head Administrator', 'System Administrator']);

echo "<h2>Default administrator created</h2>";
echo "<p><b>Email:</b> " . htmlspecialchars($email) . "<br><b>Password:</b> Admin@123</p>";
echo "<p style='color:red'>Please log in and change this password (or create a new admin and archive this one), then delete setup_admin.php.</p>";
echo "<p><a href='index.html'>Go to login page</a></p>";
