<?php
/**
 * API สำหรับดึงข้อมูลสรุปก่อน Export
 */

require_once __DIR__ . '/../../includes/session_config.php';
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'manager'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// รับค่า filter ทั้งหมด
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$assigned_to = $_GET['assigned_to'] ?? null;
$q = $_GET['q'] ?? null;
$export_type = $_GET['type'] ?? 'word'; // word หรือ excel

// สำหรับ compatibility กับ code เดิม
$date_from = $start_date;
$date_to = $end_date;

// สร้าง WHERE clause ตาม filter
$where_parts = ["j.status = 'completed'"];
$params = [];
$types = "";

if ($start_date) {
    $where_parts[] = "DATE(j.created_at) >= ?";
    $params[] = $start_date;
    $types .= "s";
}
if ($end_date) {
    $where_parts[] = "DATE(j.created_at) <= ?";
    $params[] = $end_date;
    $types .= "s";
}
if ($assigned_to) {
    $where_parts[] = "j.assigned_to = ?";
    $params[] = $assigned_to;
    $types .= "i";
}
if ($q) {
    $where_parts[] = "(j.contract_number LIKE ? OR j.location_info LIKE ? OR j.product LIKE ?)";
    $search = "%$q%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

$where_clause = implode(" AND ", $where_parts);

// นับจำนวนงานที่ส่งแล้ว (completed) ตาม filter
$sql = "SELECT COUNT(*) as cnt FROM jobs j WHERE $where_clause";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $export_count = $result->fetch_assoc()['cnt'];
    $stmt->close();
} else {
    $result = $conn->query($sql);
    $export_count = $result->fetch_assoc()['cnt'];
}

echo json_encode([
    'success' => true,
    'export_count' => (int)$export_count
], JSON_UNESCAPED_UNICODE);
