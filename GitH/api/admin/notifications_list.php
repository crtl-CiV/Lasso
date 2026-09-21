<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAdmin();
$db = getDB();

$rows = $db->query("SELECT id, type, message, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT 50")->fetchAll();
$unread = (int)$db->query("SELECT COUNT(*) c FROM notifications WHERE is_read = FALSE")->fetch()['c'];

respond(true, ['notifications' => $rows, 'unread_count' => $unread]);
