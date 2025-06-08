<?php
session_start();
if (!isset($_SESSION['user'])) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

require '../config/db.php';
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 🧠 ดึงค่าจาก GET
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$keyword = $_GET['keyword'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$month = $_GET['month'] ?? '';
$export_all = isset($_GET['all']);

$current_user_id = $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role'];

// 🔐 จำกัดสิทธิ์การเข้าถึงแผนก
$stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$stmt->bind_result($current_department_id);
$stmt->fetch();
$stmt->close();

$visible_dept_ids = [$current_department_id];
if ($current_user_role !== 'admin') {
    $stmt = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_department_id = ?");
    $stmt->bind_param("i", $current_department_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $visible_dept_ids[] = $row['to_department_id'];
    }
}

// 🔍 WHERE
$where = '1=1';
$params = [];

if (!$export_all) {
    if ($start && $end) {
        $where .= " AND DATE(j.due_date) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    }
    if ($keyword !== '') {
        $where .= " AND (
            j.contract_number LIKE ?
            OR j.product LIKE ?
            OR j.location_info LIKE ?
            OR j.model LIKE ?
            OR j.plate LIKE ?
        )";
        for ($i = 0; $i < 5; $i++) $params[] = "%$keyword%";
    }
    if ($assigned_to !== '') {
        $where .= " AND j.assigned_to = ?";
        $params[] = $assigned_to;
    }
    if ($month) {
        $where .= " AND DATE_FORMAT(j.due_date, '%Y-%m') = ?";
        $params[] = $month;
    }
}
if ($current_user_role !== 'admin') {
    $placeholders = implode(',', array_fill(0, count($visible_dept_ids), '?'));
    $where .= " AND j.department_id IN ($placeholders)";
    $params = array_merge($params, $visible_dept_ids);
}

// 📄 Main export query
$sql = "SELECT j.*, 
        u1.name AS officer_name, 
        u2.name AS imported_by_name,
        d.name AS department_name,
        (SELECT result FROM job_logs WHERE job_id = j.id ORDER BY id DESC LIMIT 1) AS latest_result
        FROM jobs j
        LEFT JOIN users u1 ON j.assigned_to = u1.id
        LEFT JOIN users u2 ON j.imported_by = u2.id
        LEFT JOIN departments d ON j.department_id = d.id
        WHERE $where
        ORDER BY j.due_date DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// 📤 สร้าง Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray([
    'เลขที่สัญญา', 'ลูกค้า', 'จังหวัด', 'วันที่กำหนด',
    'สถานะ', 'ผลลงงานล่าสุด', 'ผู้รับงาน', 'ผู้นำเข้า', 'แผนก'
], null, 'A1');

$rowIndex = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A$rowIndex", $row['contract_number']);
    $sheet->setCellValue("B$rowIndex", $row['location_info']);
    $sheet->setCellValue("C$rowIndex", $row['province']);
    $sheet->setCellValue("D$rowIndex", $row['due_date']);
    $sheet->setCellValue("E$rowIndex", $row['status'] === 'completed' ? 'สำเร็จ' : 'รอดำเนินการ');
    $sheet->setCellValue("F$rowIndex", $row['latest_result'] ?? '-');
    $sheet->setCellValue("G$rowIndex", $row['officer_name'] ?? '-');
    $sheet->setCellValue("H$rowIndex", $row['imported_by_name'] ?? '-');
    $sheet->setCellValue("I$rowIndex", $row['department_name'] ?? '-');
    $rowIndex++;
}

// 📎 ส่ง Excel กลับให้ผู้ใช้ดาวน์โหลด
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="export_jobs_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
