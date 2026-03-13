<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการ login
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

include('../../config/db.php');

$job_id = $_GET['job_id'] ?? null;
$user_id = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];

if (!$job_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสงาน']);
    exit;
}

try {
    // ดึงความคิดเห็นทั้งหมดของงานนี้
    $sql = "SELECT jc.*, u.name as user_name, u.role as user_role
            FROM job_comments jc
            LEFT JOIN users u ON jc.user_id = u.id
            WHERE jc.job_id = ?
            ORDER BY jc.created_at ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'id' => $row['id'],
            'job_id' => $row['job_id'],
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'],
            'user_role' => $row['user_role'],
            'comment' => $row['comment'],
            'comment_type' => $row['comment_type'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    $stmt->close();

    // อัปเดตสถานะอ่านแล้วสำหรับความคิดเห็นที่ไม่ใช่ของตัวเอง
    $sql_update = "UPDATE job_comments
                   SET is_read = 1, read_at = NOW()
                   WHERE job_id = ? AND user_id != ? AND is_read = 0";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $job_id, $user_id);
    $stmt_update->execute();
    $stmt_update->close();

    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'count' => count($comments)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
