<?php
session_start();
if (!in_array($_SESSION['user']['role'], ['admin', 'field'])) {
    die("Unauthorized");
}

require_once '../vendor/autoload.php';
include '../config/db.php';

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

$job_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

if (!$job_id) {
    die("Missing job ID");
}

if ($role === 'admin') {
    $stmt = $conn->prepare("SELECT j.*, l.note, l.result, l.gps, l.images, l.created_at AS log_time,
        u1.name AS assigned_name, u2.name AS imported_name
        FROM jobs j 
        LEFT JOIN job_logs l ON j.id = l.job_id 
        LEFT JOIN users u1 ON j.assigned_to = u1.id
        LEFT JOIN users u2 ON j.imported_by = u2.id
        WHERE j.id = ?");
    $stmt->bind_param("i", $job_id);
} else {
    $stmt = $conn->prepare("SELECT j.*, l.note, l.result, l.gps, l.images, l.created_at AS log_time,
        u1.name AS assigned_name, u2.name AS imported_name
        FROM jobs j 
        LEFT JOIN job_logs l ON j.id = l.job_id 
        LEFT JOIN users u1 ON j.assigned_to = u1.id
        LEFT JOIN users u2 ON j.imported_by = u2.id
        WHERE j.id = ? AND j.assigned_to = ?");
    $stmt->bind_param("ii", $job_id, $user_id);
}

$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    die("ไม่พบข้อมูล");
}

// Font config
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
        ]
    ]),
    'default_font' => 'sarabun',
    'format' => 'A4'
]);

// HTML START
$html = '<style>
body { font-family: sarabun; font-size: 14px; }
h2 { color: #003366; text-align:center; }
h3 { color: #003366; margin-top: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 10px; }
th, td { border: 1px solid #333; padding: 6px; text-align: left; }
th { background-color: #f0f0f0; }
img { margin: 10px 5px; border: 1px solid #ccc; }
</style>';

$html .= '<h2>รายงานผลการลงพื้นที่</h2>';

$html .= '<h3> รายละเอียดสัญญา</h3>
<table>
<tr><th>เลขที่สัญญา</th><td>' . $job['contract_number'] . '</td></tr>
<tr><th>Product</th><td>' . $job['product'] . '</td></tr>
<tr><th>วันครบกำหนด</th><td>' . $job['due_date'] . '</td></tr>
<tr><th>จำนวนวันครบกำหนด</th><td>' . $job['overdue_period'] . '</td></tr>
<tr><th>สถานะ</th><td>' . $job['status'] . '</td></tr>
<tr><th>OS</th><td>' . number_format($job['os'], 2) . ' บาท</td></tr>
</table>';

$html .= '<h3> ข้อมูลลูกค้า / พื้นที่ / รถ</h3>
<table>
<tr><th>ชื่อลูกค้า</th><td>' . $job['location_info'] . '</td></tr>
<tr><th>ข้อมูลพื้นที่</th><td>' . $job['location_area'] . '</td></tr>
<tr><th>โซน</th><td>' . $job['zone'] . '</td></tr>
<tr><th>ยี่ห้อ</th><td>' . $job['model'] . '</td></tr>
<tr><th>รุ่น</th><td>' . $job['model_detail'] . '</td></tr>
<tr><th>สี</th><td>' . $job['color'] . '</td></tr>
<tr><th>ทะเบียน</th><td>' . $job['plate'] . '</td></tr>
<tr><th>จังหวัด</th><td>' . $job['province'] . '</td></tr>
</table>';

$html .= '<h3> ผลการดำเนินงาน</h3>
<table>
<tr><th>ผลการลงพื้นที่</th><td>' . ($job['result'] ?? '-') . '</td></tr>
<tr><th>หมายเหตุ</th><td>' . nl2br($job['note'] ?? '-') . '</td></tr>
<tr><th>พิกัด GPS</th><td>' . ($job['gps'] ?? '-') . '</td></tr>
<tr><th>เวลาที่บันทึก</th><td>' . ($job['log_time'] ? date('d/m/Y H:i', strtotime($job['log_time'])) : '-') . '</td></tr>
</table>';

// Images
if (!empty($job['images'])) {
    $images = json_decode($job['images'], true);
    $html .= '<h3> รูปภาพประกอบ</h3><div>';
    foreach ($images as $img) {
        $imgPath = '../uploads/job_photos/' . $img;
        if (file_exists($imgPath)) {
            $base64 = base64_encode(file_get_contents($imgPath));
            $type = pathinfo($imgPath, PATHINFO_EXTENSION);
            $html .= '<img src="data:image/' . $type . ';base64,' . $base64 . '" width="180">';
        }
    }
    $html .= '</div>';
}

$mpdf->WriteHTML($html);
$mpdf->Output('job_detail_' . $job['contract_number'] . '.pdf', 'D');
exit;
