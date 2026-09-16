<?php
require_once __DIR__ . '/api/config/db.php';
header('Content-Type: text/html; charset=utf-8');

$db = getDB();
$count = $db->query("SELECT COUNT(*) c FROM administrators")->fetch()['c'];

if ($count > 0) {
    echo "<p>An administrator account already exists. This setup script is now locked. Delete <code>setup_admin.php</code> from your server for security.</p>";
    exit;
}

$hash = password_hash('Admin@123', PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO administrators (admin_id, password_hash, full_name, position, status) VALUES (?, ?, ?, ?, 'active')");
$stmt->execute(['admin001', $hash, 'Head Administrator', 'System Administrator']);

echo "<h2>Default administrator created</h2>";
echo "<p><b>Admin ID:</b> admin001<br><b>Password:</b> Admin@123</p>";
echo "<p style='color:red'>Please log in and change this password (or create a new admin and archive this one), then delete setup_admin.php.</p>";
echo "<p><a href='index.html'>Go to login page</a></p>";
