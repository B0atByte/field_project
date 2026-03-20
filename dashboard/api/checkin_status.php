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

// ดึง session ล่าสุดของวันนี้
$stmt = $conn->prepare("
    SELECT checkin_at, checkout_at,
           checkin_lat, checkin_lng, checkin_address,
           checkout_lat, checkout_lng, checkout_address
    FROM work_checkins
    WHERE user_id = ? AND DATE(checkin_at) = CURDATE()
    ORDER BY checkin_at DESC LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

$latest      = null;
$firstCheckin = null;

if ($session) {
    $isOpen = ($session['checkout_at'] === null);

    // แปลงเป็น format เดิมที่ frontend ใช้
    $latest = [
        'type'       => $isOpen ? 'checkin' : 'checkout',
        'checked_at' => $isOpen ? $session['checkin_at'] : $session['checkout_at'],
        'latitude'   => $isOpen ? $session['checkin_lat']  : $session['checkout_lat'],
        'longitude'  => $isOpen ? $session['checkin_lng']  : $session['checkout_lng'],
        'address'    => $isOpen ? $session['checkin_address'] : $session['checkout_address'],
    ];

    // ดึง checkin แรกของวันนี้ (สำหรับคำนวณเวลาทำงานสะสม)
    $stmt2 = $conn->prepare("
        SELECT checkin_at AS checked_at
        FROM work_checkins
        WHERE user_id = ? AND DATE(checkin_at) = CURDATE()
        ORDER BY checkin_at ASC LIMIT 1
    ");
    $stmt2->bind_param('i', $userId);
    $stmt2->execute();
    $firstCheckin = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
}

echo json_encode([
    'success'       => true,
    'latest'        => $latest,
    'first_checkin' => $firstCheckin,
]);
