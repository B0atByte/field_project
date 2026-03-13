<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการ login และสิทธิ์
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

include('../../config/db.php');

$log_id = $_GET['id'] ?? null;

if (!$log_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรหัส log']);
    exit;
}

try {
    // ดึงข้อมูล deletion log
    $sql = "SELECT jdl.*, u.name as deleted_by_name, u2.name as assigned_to_name
            FROM job_deletion_logs jdl
            LEFT JOIN users u ON jdl.deleted_by = u.id
            LEFT JOIN users u2 ON jdl.assigned_to = u2.id
            WHERE jdl.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $log_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $log = $result->fetch_assoc();
    $stmt->close();

    if (!$log) {
        throw new Exception('ไม่พบข้อมูล log');
    }

    echo json_encode([
        'success' => true,
        'log' => $log
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
