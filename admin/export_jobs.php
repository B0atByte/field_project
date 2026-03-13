<?php
// เริ่ม Output Buffering ทันทีเพื่อดักจับ Error/Warning
ob_start();

// เพิ่ม Memory Limit และ Time Limit สำหรับการ export ข้อมูลจำนวนมาก
ini_set('memory_limit', '512M');
set_time_limit(0);

require_once __DIR__ . '/../includes/session_config.php';

if (!isset($_SESSION['user'])) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

require '../config/db.php';
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// รับค่ากรอง (เปลี่ยนชื่อให้ตรงกับ jobs.php)
$start             = $_GET['start_date'] ?? '';
$end               = $_GET['end_date'] ?? '';
$keyword           = $_GET['keyword'] ?? '';
$assigned_to       = $_GET['assigned_to'] ?? '';
$month             = $_GET['month'] ?? '';
$status_filter     = $_GET['status'] ?? '';
$submitted_status  = $_GET['submitted_status'] ?? '';
$submitted_start   = $_GET['submitted_start'] ?? '';
$submitted_end     = $_GET['submitted_end'] ?? '';
$export_all        = isset($_GET['all']);

$current_user_id   = $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role'];

// จำกัดแผนกที่ดูได้
$visible_dept_ids = [];
if ($current_user_role !== 'admin') {
    $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $stmt->bind_result($current_department_id);
    $stmt->fetch();
    $stmt->close();
    if ($current_department_id) {
        $visible_dept_ids[] = $current_department_id;
    }

    $stmt = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_user_id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $visible_dept_ids[] = $row['to_department_id'];
    }

    if (count($visible_dept_ids) === 0) {
        die("คุณไม่มีสิทธิ์ดูข้อมูลในแผนกใดเลย");
    }
}

// เงื่อนไข WHERE
$where = '1=1';
$params = [];
$types  = '';

if (!$export_all) {
    if ($start && $end) {
        $where .= " AND DATE(j.created_at) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
        $types .= 'ss';
    } elseif ($start) {
        $where .= " AND DATE(j.created_at) >= ?";
        $params[] = $start;
        $types .= 's';
    } elseif ($end) {
        $where .= " AND DATE(j.created_at) <= ?";
        $params[] = $end;
        $types .= 's';
    }

    if ($keyword !== '') {
        $where .= " AND (
            j.contract_number LIKE ?
            OR j.product LIKE ?
            OR j.location_info LIKE ?
            OR j.model LIKE ?
            OR j.plate LIKE ?
        )";
        for ($i = 0; $i < 5; $i++) {
            $params[] = "%$keyword%";
            $types .= 's';
        }
    }

    if ($assigned_to !== '') {
        $where .= " AND j.assigned_to = ?";
        $params[] = $assigned_to;
        $types .= 's';
    }

    if ($month !== '') {
        $where .= " AND DATE_FORMAT(j.due_date, '%Y-%m') = ?";
        $params[] = $month;
        $types .= 's';
    }

    if ($status_filter === 'completed') {
        $where .= " AND j.status = 'completed'";
    } elseif ($status_filter === 'pending') {
        $where .= " AND (j.status IS NULL OR j.status != 'completed')";
    }

    if ($submitted_status === 'sent') {
        $where .= " AND EXISTS (SELECT 1 FROM job_logs jl WHERE jl.job_id = j.id)";
    } elseif ($submitted_status === 'unsent') {
        $where .= " AND NOT EXISTS (SELECT 1 FROM job_logs jl WHERE jl.job_id = j.id)";
    }

    if ($submitted_start) {
        $where .= " AND EXISTS (
            SELECT 1 FROM job_logs jl 
            WHERE jl.job_id = j.id AND DATE(jl.created_at) >= ?
        )";
        $params[] = $submitted_start;
        $types .= 's';
    }

    if ($submitted_end) {
        $where .= " AND EXISTS (
            SELECT 1 FROM job_logs jl 
            WHERE jl.job_id = j.id AND DATE(jl.created_at) <= ?
        )";
        $params[] = $submitted_end;
        $types .= 's';
    }
}

if ($current_user_role !== 'admin') {
    $placeholders = implode(',', array_fill(0, count($visible_dept_ids), '?'));
    $where .= " AND j.department_id IN ($placeholders)";
    foreach ($visible_dept_ids as $id) {
        $params[] = $id;
        $types .= 'i';
    }
}

// Query
$sql = "SELECT j.*,
        u1.name AS officer_name,
        u2.name AS imported_by_name,
        d.name AS department_name,
        (SELECT result FROM job_logs WHERE job_id = j.id ORDER BY id DESC LIMIT 1) AS latest_result,
        (SELECT note FROM job_logs WHERE job_id = j.id ORDER BY id DESC LIMIT 1) AS latest_note,
        (SELECT MAX(created_at) FROM job_logs WHERE job_id = j.id) AS latest_report_time,
        (SELECT COUNT(*) FROM job_logs WHERE job_id = j.id) AS submission_count
    FROM jobs j
    LEFT JOIN users u1 ON j.assigned_to = u1.id
    LEFT JOIN users u2 ON j.imported_by = u2.id
    LEFT JOIN departments d ON j.department_id = d.id
    WHERE $where
    ORDER BY j.due_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->fromArray([
    'Product', 'เลขที่สัญญา', 'บัตร ปชช.', 'ชื่อลูกค้า', 'พื้นที่',
    'โซน', 'DUE DATE', 'OVERDUE_PERIOD',
    'ยี่ห้อ', 'รุ่น', 'สี', 'ทะเบียน', 'จังหวัด', 'OS ยอดค้าง',
    'สถานะ', 'ผลลงงานล่าสุด', 'หมายเหตุล่าสุด', 'ลงเมื่อ', 'เวลาที่ลงรายงานล่าสุด', 'ผู้รับงาน', 'ผู้นำเข้า', 'แผนก'
], null, 'A1');

$rowIndex = 2;
while ($row = $result->fetch_assoc()) {
    // กำหนดสถานะตาม job_logs (เช่นเดียวกับ jobs_data.php)
    $submission_count = (int)$row['submission_count'];
    $statusText = $submission_count > 0 ? 'เสร็จสิ้น' : 'รอดำเนินการ';

    $sheet->setCellValue("A$rowIndex", $row['product']);
    $sheet->setCellValue("B$rowIndex", $row['contract_number']);
    $sheet->setCellValue("C$rowIndex", $row['customer_id_card'] ?? '-');
    $sheet->setCellValue("D$rowIndex", strip_tags($row['location_info']));
    $sheet->setCellValue("E$rowIndex", $row['location_area']);
    $sheet->setCellValue("F$rowIndex", $row['zone']);
    $sheet->setCellValue("G$rowIndex", $row['due_date']);
    $sheet->setCellValue("H$rowIndex", $row['overdue_period']);
    $sheet->setCellValue("I$rowIndex", $row['model']);
    $sheet->setCellValue("J$rowIndex", $row['model_detail']);
    $sheet->setCellValue("K$rowIndex", $row['color'] ?? '-');
    $sheet->setCellValue("L$rowIndex", $row['plate']);
    $sheet->setCellValue("M$rowIndex", $row['province']);
    $sheet->setCellValue("N$rowIndex", $row['os']);
    $sheet->setCellValue("O$rowIndex", $statusText);
    $sheet->setCellValue("P$rowIndex", $row['latest_result'] ?? '-');
    $sheet->setCellValue("Q$rowIndex", $row['latest_note'] ?? '-');
    $sheet->setCellValue("R$rowIndex", $row['created_at'] ? date('Y-m-d H:i', strtotime($row['created_at'])) : '-');
    $sheet->setCellValue("S$rowIndex", $row['latest_report_time'] ? date('Y-m-d H:i', strtotime($row['latest_report_time'])) : '-');
    $sheet->setCellValue("T$rowIndex", $row['officer_name'] ?? '-');
    $sheet->setCellValue("U$rowIndex", $row['imported_by_name'] ?? '-');
    $sheet->setCellValue("V$rowIndex", $row['department_name'] ?? '-');
    $rowIndex++;
}

// ส่งออก
// ล้าง Buffer เดิมที่มีอยู่ทั้งหมด (ป้องกัน Warning/Notice หลุดไปในไฟล์ Excel)
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="export_jobs_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
