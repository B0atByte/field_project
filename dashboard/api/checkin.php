<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'field') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/csrf.php';

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$type    = $input['type']      ?? '';
$lat     = isset($input['lat'])  ? (float)$input['lat']  : null;
$lng     = isset($input['lng'])  ? (float)$input['lng']  : null;
$address = isset($input['address']) ? trim(substr($input['address'], 0, 500)) : null;
$note    = isset($input['note'])    ? trim(substr($input['note'], 0, 500))    : null;

if (!in_array($type, ['checkin', 'checkout'], true)) {
    echo json_encode(['success' => false, 'message' => 'ประเภทไม่ถูกต้อง']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];

// ถ้า checkout ต้องมี checkin ก่อน (วันนี้)
if ($type === 'checkout') {
    $stmt = $conn->prepare("
        SELECT id FROM work_checkins
        WHERE user_id = ? AND type = 'checkin'
          AND DATE(checked_at) = CURDATE()
        ORDER BY checked_at DESC LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'ยังไม่ได้ลงเวลาเข้างานวันนี้']);
        exit;
    }
    $stmt->close();
}

// ป้องกัน checkin ซ้ำภายใน 1 ชม.
if ($type === 'checkin') {
    $stmt = $conn->prepare("
        SELECT id FROM work_checkins
        WHERE user_id = ? AND type = 'checkin'
          AND checked_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY checked_at DESC LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'ลงเวลาเข้างานไปแล้วในช่วง 1 ชั่วโมงที่ผ่านมา']);
        exit;
    }
    $stmt->close();
}

$stmt = $conn->prepare("
    INSERT INTO work_checkins (user_id, type, latitude, longitude, address, note)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param('isddss', $userId, $type, $lat, $lng, $address, $note);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ']);
    exit;
}
$stmt->close();

// ดึง record ล่าสุดกลับมา
$stmt = $conn->prepare("
    SELECT id, type, latitude, longitude, address, checked_at
    FROM work_checkins
    WHERE user_id = ? ORDER BY checked_at DESC LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success'    => true,
    'type'       => $type,
    'message'    => $type === 'checkin' ? 'ลงเวลาเข้างานสำเร็จ' : 'ลงเวลาออกงานสำเร็จ',
    'checked_at' => $record['checked_at'],
    'lat'        => $record['latitude'],
    'lng'        => $record['longitude'],
]);
