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
require_once __DIR__ . '/../../includes/rate_limiter.php';

$maxAttempts = (int)env('MAX_LOGIN_ATTEMPTS', 5);
$lockoutTime = (int)env('LOGIN_LOCKOUT_TIME', 900);

// ดึง IP ที่พยายาม login ผิดพลาดเกิน maxAttempts ภายใน lockoutTime
$sql = "
    SELECT 
        ip_address,
        GROUP_CONCAT(DISTINCT username ORDER BY username SEPARATOR ', ') as username,
        COUNT(*) as attempt_count,
        MAX(attempted_at) as last_attempt
    FROM login_attempts
    WHERE attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    GROUP BY ip_address
    HAVING attempt_count >= ?
    ORDER BY last_attempt DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $lockoutTime, $maxAttempts);
$stmt->execute();
$result = $stmt->get_result();

$locked_ips = [];
while ($row = $result->fetch_assoc()) {
    $locked_ips[] = [
        'ip_address' => $row['ip_address'],
        'username' => $row['username'],
        'attempt_count' => (int)$row['attempt_count'],
        'last_attempt' => $row['last_attempt']
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'locked_ips' => $locked_ips,
    'max_attempts' => $maxAttempts,
    'lockout_seconds' => $lockoutTime
], JSON_UNESCAPED_UNICODE);
