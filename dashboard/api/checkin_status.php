<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'field') {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$userId = (int)$_SESSION['user']['id'];

// ดึง record ล่าสุดของวันนี้
$stmt = $conn->prepare("
    SELECT type, latitude, longitude, address, checked_at
    FROM work_checkins
    WHERE user_id = ? AND DATE(checked_at) = CURDATE()
    ORDER BY checked_at DESC LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$latest = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ดึง checkin แรกของวันนี้
$stmt = $conn->prepare("
    SELECT checked_at FROM work_checkins
    WHERE user_id = ? AND type = 'checkin' AND DATE(checked_at) = CURDATE()
    ORDER BY checked_at ASC LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$firstCheckin = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success'       => true,
    'latest'        => $latest,
    'first_checkin' => $firstCheckin,
]);
