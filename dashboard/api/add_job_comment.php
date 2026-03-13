<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการ login
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

include('../../config/db.php');

$user_id = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];

// รับข้อมูลจาก POST
$data = json_decode(file_get_contents('php://input'), true);

$job_id = $data['job_id'] ?? null;
$comment = $data['comment'] ?? null;
$comment_type = $data['comment_type'] ?? 'comment';

if (!$job_id || !$comment) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    // บันทึกความคิดเห็น
    $sql = "INSERT INTO job_comments (job_id, user_id, comment, comment_type)
            VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $job_id, $user_id, $comment, $comment_type);

    if ($stmt->execute()) {
        $comment_id = $stmt->insert_id;
        $stmt->close();

        // ดึงข้อมูลความคิดเห็นที่เพิ่งเพิ่ม
        $sql_get = "SELECT jc.*, u.name as user_name, u.role as user_role
                    FROM job_comments jc
                    LEFT JOIN users u ON jc.user_id = u.id
                    WHERE jc.id = ?";

        $stmt_get = $conn->prepare($sql_get);
        $stmt_get->bind_param("i", $comment_id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();
        $new_comment = $result->fetch_assoc();
        $stmt_get->close();

        echo json_encode([
            'success' => true,
            'message' => 'เพิ่มความคิดเห็นสำเร็จ',
            'comment' => [
                'id' => $new_comment['id'],
                'job_id' => $new_comment['job_id'],
                'user_id' => $new_comment['user_id'],
                'user_name' => $new_comment['user_name'],
                'user_role' => $new_comment['user_role'],
                'comment' => $new_comment['comment'],
                'comment_type' => $new_comment['comment_type'],
                'created_at' => $new_comment['created_at']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('ไม่สามารถเพิ่มความคิดเห็นได้');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
