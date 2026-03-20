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

$input   = json_decode(file_get_contents('php://input'), true);
$type    = $input['type']    ?? '';
$lat     = isset($input['lat'])     ? (float)$input['lat']     : null;
$lng     = isset($input['lng'])     ? (float)$input['lng']     : null;
$address = isset($input['address']) ? trim(substr($input['address'], 0, 500)) : null;
$note    = isset($input['note'])    ? trim(substr($input['note'], 0, 500))    : null;

if (!in_array($type, ['checkin', 'checkout'], true)) {
    echo json_encode(['success' => false, 'message' => 'ประเภทไม่ถูกต้อง']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];

// --- ค้นหา session ที่ยังเปิดอยู่วันนี้ (checkin แต่ยังไม่ checkout) ---
$stmt = $conn->prepare("
    SELECT id FROM work_checkins
    WHERE user_id = ? AND DATE(checkin_at) = CURDATE() AND checkout_at IS NULL
    ORDER BY checkin_at DESC LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$openSession = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($type === 'checkin') {
    // ห้าม checkin ซ้ำถ้ายังมี session ที่ยังไม่ได้ checkout
    if ($openSession) {
        echo json_encode(['success' => false, 'message' => 'ลงเวลาเข้างานไปแล้ว กรุณาลงเวลาออกงานก่อน']);
        exit;
    }
    // สร้าง session ใหม่
    $stmt = $conn->prepare("
        INSERT INTO work_checkins (user_id, checkin_at, checkin_lat, checkin_lng, checkin_address, note)
        VALUES (?, NOW(), ?, ?, ?, ?)
    ");
    $stmt->bind_param('iddss', $userId, $lat, $lng, $address, $note);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ']);
        exit;
    }
    $stmt->close();

} else {
    // checkout: ต้องมี session ที่เปิดอยู่
    if (!$openSession) {
        echo json_encode(['success' => false, 'message' => 'ยังไม่ได้ลงเวลาเข้างานวันนี้']);
        exit;
    }
    $sessionId = (int)$openSession['id'];
    $stmt = $conn->prepare("
        UPDATE work_checkins
           SET checkout_at = NOW(), checkout_lat = ?, checkout_lng = ?, checkout_address = ?
         WHERE id = ?
    ");
    $stmt->bind_param('ddsi', $lat, $lng, $address, $sessionId);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ']);
        exit;
    }
    $stmt->close();
}

// --- ดึง session ล่าสุดของวันนี้กลับมา ---
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
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isCheckedIn = ($record && $record['checkout_at'] === null);

echo json_encode([
    'success'    => true,
    'type'       => $type,
    'message'    => $type === 'checkin' ? 'ลงเวลาเข้างานสำเร็จ' : 'ลงเวลาออกงานสำเร็จ',
    'checked_at' => $isCheckedIn ? $record['checkin_at'] : ($record['checkout_at'] ?? null),
    'lat'        => $isCheckedIn ? $record['checkin_lat'] : ($record['checkout_lat'] ?? null),
    'lng'        => $isCheckedIn ? $record['checkin_lng'] : ($record['checkout_lng'] ?? null),
]);
