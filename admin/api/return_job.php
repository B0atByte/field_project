<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการ login และสิทธิ์ (เฉพาะ admin และ manager)
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ในการตีงานกลับ']);
    exit;
}

include('../../config/db.php');
require_once('../../includes/csrf.php');

// ตรวจสอบ CSRF token (รับจาก header X-CSRF-Token)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
if (!$token || !validateCsrfToken($token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];

// รับข้อมูลจาก POST
$data = json_decode(file_get_contents('php://input'), true);

$job_id = $data['job_id'] ?? null;
$return_reason = $data['return_reason'] ?? null;

if (!$job_id || !$return_reason) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    // เริ่ม transaction
    $conn->begin_transaction();

    // ดึงข้อมูลงานเดิม
    $sql_get = "SELECT * FROM jobs WHERE id = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->bind_param("i", $job_id);
    $stmt_get->execute();
    $job = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$job) {
        throw new Exception('ไม่พบงานนี้');
    }

    // ตรวจสอบว่างานนี้ส่งแล้วหรือยัง
    if ($job['status'] !== 'completed') {
        throw new Exception('งานนี้ยังไม่ได้ส่ง ไม่สามารถตีกลับได้');
    }

    $old_status = $job['status'];
    $new_status = 'returned';
    $assigned_to = $job['assigned_to'];

    // อัปเดตสถานะงาน
    $sql_update = "UPDATE jobs
                   SET status = ?,
                       return_reason = ?,
                       returned_by = ?,
                       returned_at = NOW(),
                       revision_count = revision_count + 1
                   WHERE id = ?";

    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssii", $new_status, $return_reason, $user_id, $job_id);
    $stmt_update->execute();
    $stmt_update->close();

    // บันทึก log ใน job_status_history
    $sql_history = "INSERT INTO job_status_history
                    (job_id, old_status, new_status, changed_by, reason, action_type)
                    VALUES (?, ?, ?, ?, ?, 'return')";

    $stmt_history = $conn->prepare($sql_history);
    $stmt_history->bind_param("issis", $job_id, $old_status, $new_status, $user_id, $return_reason);
    $stmt_history->execute();
    $stmt_history->close();

    // เพิ่มความคิดเห็นลงระบบแชท
    $comment_message = "งานถูกตีกลับเพื่อแก้ไข\nเหตุผล: " . $return_reason;
    $sql_comment = "INSERT INTO job_comments
                    (job_id, user_id, comment, comment_type)
                    VALUES (?, ?, ?, 'return_request')";

    $stmt_comment = $conn->prepare($sql_comment);
    $stmt_comment->bind_param("iis", $job_id, $user_id, $comment_message);
    $stmt_comment->execute();
    $stmt_comment->close();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'ตีงานกลับสำเร็จ',
        'job_id' => $job_id,
        'new_status' => $new_status,
        'revision_count' => $job['revision_count'] + 1
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
