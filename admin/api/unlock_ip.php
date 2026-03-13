<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบว่าเป็น Admin เท่านั้น
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

include __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/csrf.php';

// ตรวจสอบ CSRF Token (รับจาก header X-CSRF-Token หรือ POST)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

// รับข้อมูล JSON
$input = json_decode(file_get_contents('php://input'), true);
$ipAddress = $input['ip_address'] ?? null;

if (!$ipAddress) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ไม่พบ IP Address']);
    exit;
}

// ลบประวัติการพยายาม login ของ IP นี้
$stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
$stmt->bind_param("s", $ipAddress);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();

    // บันทึก log การปลดล็อค (ข้ามถ้าไม่มีตาราง)
    try {
        $admin_id = $_SESSION['user']['id'];
        $log_stmt = $conn->prepare("
            INSERT INTO admin_action_logs (admin_id, action_type, details, created_at)
            VALUES (?, 'unlock_ip', ?, NOW())
        ");
        if ($log_stmt) {
            $details = "ปลดล็อค IP: $ipAddress";
            $log_stmt->bind_param("is", $admin_id, $details);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        // ข้ามถ้าตาราง admin_action_logs ยังไม่มี
    }

    echo json_encode([
        'success' => true,
        'message' => 'ปลดล็อคสำเร็จ',
        'affected_rows' => $affected
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ไม่สามารถปลดล็อคได้']);
}
