<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'field') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../../config/db.php';

$contract_number = $_GET['contract_number'] ?? '';
$current_job_id = (int)($_GET['current_job_id'] ?? 0);

if (empty($contract_number)) {
    echo json_encode(['success' => false, 'message' => 'Missing contract number']);
    exit;
}

// ดึงประวัติงานที่มีเลขสัญญาเดียวกัน (ยกเว้นงานปัจจุบัน) + เอา log ล่าสุด
$stmt = $conn->prepare("
    SELECT
        j.id,
        j.contract_number,
        j.location_info,
        j.zone,
        j.province,
        j.status,
        j.created_at,
        j.updated_at,
        l.result,
        l.note,
        l.gps,
        l.created_at AS log_time,
        u.name AS assigned_name
    FROM jobs j
    LEFT JOIN (
        SELECT job_id, result, note, gps, created_at,
            ROW_NUMBER() OVER (PARTITION BY job_id ORDER BY id DESC) as rn
        FROM job_logs
    ) l ON j.id = l.job_id AND l.rn = 1
    LEFT JOIN users u ON j.assigned_to = u.id
    WHERE j.contract_number = ?
    AND j.id != ?
    ORDER BY j.created_at DESC
    LIMIT 20
");

$stmt->bind_param("si", $contract_number, $current_job_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = [
        'id' => $row['id'],
        'contract_number' => $row['contract_number'],
        'location_info' => $row['location_info'],
        'zone' => $row['zone'],
        'province' => $row['province'],
        'status' => $row['status'],
        'result' => $row['result'],
        'note' => $row['note'],
        'gps' => $row['gps'],
        'log_time' => $row['log_time'],
        'assigned_name' => $row['assigned_name'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

$stmt->close();

echo json_encode([
    'success' => true,
    'count' => count($history),
    'history' => $history
]);
