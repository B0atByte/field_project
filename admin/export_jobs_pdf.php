<?php
session_start();
if (!isset($_SESSION['user'])) {
    die("Unauthorized");
}

require_once '../vendor/autoload.php';
include '../config/db.php';

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

// รับค่ากรอง
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$keyword = $_GET['keyword'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$month = $_GET['month'] ?? '';

$current_user = $_SESSION['user'];
$user_id = $current_user['id'];
$role = $current_user['role'];

// เข้าถึงแผนกที่มองเห็น
$dept_ids = [];
$stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($current_dept);
$stmt->fetch();
$stmt->close();
$dept_ids[] = $current_dept;

if ($role !== 'admin') {
    $stmt = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_department_id = ?");
    $stmt->bind_param("i", $current_dept);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dept_ids[] = $row['to_department_id'];
    }
}

// WHERE เงื่อนไข
$where = '1=1';
$params = [];
$types = '';

if ($start && $end) {
    $where .= " AND DATE(j.due_date) BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;
    $types .= 'ss';
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
if ($month) {
    $where .= " AND DATE_FORMAT(j.due_date, '%Y-%m') = ?";
    $params[] = $month;
    $types .= 's';
}
if ($role !== 'admin') {
    $where .= " AND j.department_id IN (" . implode(',', array_fill(0, count($dept_ids), '?')) . ")";
    foreach ($dept_ids as $id) {
        $params[] = $id;
        $types .= 's';
    }
}

// ดึงข้อมูล
$sql = "SELECT j.*, u1.name AS officer_name, u2.name AS imported_by_name, d.name AS department_name
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

// เรียกใช้ฟอนต์ไทย
$defaultConfig = (new ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new Mpdf([
    'fontDir' => array_merge($fontDirs, [__DIR__ . '/../vendor/mpdf/mpdf/ttfonts']),
    'fontdata' => array_merge($fontData, [
        'sarabun' => [
            'R' => 'Sarabun-Regular.ttf',
            'B' => 'Sarabun-Bold.ttf',
            'I' => 'Sarabun-Italic.ttf',
            'BI' => 'Sarabun-BoldItalic.ttf',
        ]
    ]),
    'default_font' => 'sarabun',
    'format' => 'A4'
]);

// HTML Template
$html = '<style>
body { font-family: sarabun; font-size: 14px; }
table { border-collapse: collapse; width: 100%; margin-top: 10px; }
th, td { border: 1px solid #333; padding: 6px; text-align: left; }
th { background-color: #f2f2f2; }
h2 { text-align: center; margin-bottom: 20px; }
</style>';

$html .= '<h2>📋 รายงานรายการงานภาคสนาม</h2>';
$html .= '<table>
<thead>
<tr>
  <th>สัญญา</th>
  <th>ชื่อผู้เช่าซื้อ</th>
  <th>จังหวัด</th>
  <th>Product</th>
  <th>รุ่น</th>
  <th>OS คงเหลือ</th>
  <th>ครบกำหนด</th>
  <th>ผู้รับงาน</th>
</tr>
</thead>
<tbody>';

while ($row = $result->fetch_assoc()) {
    $html .= '<tr>
        <td>' . htmlspecialchars($row['contract_number']) . '</td>
        <td>' . htmlspecialchars($row['location_info']) . '</td>
        <td>' . htmlspecialchars($row['province']) . '</td>
        <td>' . htmlspecialchars($row['product']) . '</td>
        <td>' . htmlspecialchars($row['model']) . '</td>
        <td>' . number_format($row['os'], 2) . '</td>
        <td>' . htmlspecialchars($row['due_date']) . '</td>
        <td>' . htmlspecialchars($row['officer_name'] ?? '-') . '</td>
    </tr>';
}
$html .= '</tbody></table>';

$mpdf->WriteHTML($html);
$mpdf->Output('job_report_' . date('Ymd_His') . '.pdf', 'D');
exit;
