<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = requireStudent();
$db = getDB();

// Billings still waiting for access (not yet approved by an admin)
$stmt = $db->prepare("SELECT id, billing_code, total_amount, status AS billing_status, receipt_status,
                              receipt_number, receipt_note, created_at
                       FROM billing_statements
                       WHERE student_id = ? AND status = 'pending'
                       ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$pending = $stmt->fetchAll();

$stmt = $db->prepare("SELECT s.id, m.title, m.material_code, s.semester_count, s.start_date, s.expiry_date, s.status
                       FROM subscriptions s JOIN instructional_materials m ON m.id = s.material_id
                       WHERE s.student_id = ? ORDER BY s.created_at DESC");
$stmt->execute([$user['id']]);

respond(true, ['pending_billings' => $pending, 'subscriptions' => $stmt->fetchAll()]);
