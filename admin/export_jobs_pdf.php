<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
requirePermission('action_export_pdf');

require_once '../vendor/autoload.php';
include '../config/db.php';

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

$job_id = $_GET['id'] ?? null;
if (!$job_id) die("Missing job ID");

// ดึงข้อมูลล่าสุดจาก job_logs เท่านั้น
$stmt = $conn->prepare("SELECT j.*, 
    l.note, l.result, l.gps, l.images, l.created_at AS log_time,
    u1.name AS officer_name, d.name AS department_name
    FROM jobs j 
    LEFT JOIN job_logs l ON j.id = l.job_id AND l.created_at = (
        SELECT MAX(created_at) FROM job_logs WHERE job_id = j.id
    )
    LEFT JOIN users u1 ON j.assigned_to = u1.id
    LEFT JOIN departments d ON j.department_id = d.id
    WHERE j.id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$job) die("ไม่พบข้อมูล");

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

$html = '<style>
body { font-family: sarabun; font-size: 14px; }
.label { font-weight: bold; font-size: 14px; }
.value { font-weight: normal; font-size: 14px; }
.section-title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin-top: 20px;
    margin-bottom: 20px;
}
.img-table td { padding: 5px; }
img.photo { width: 250px; height: auto; }
</style>';

$html .= '<div class="section-title">รายงานผลการลงพื้นที่</div>';

$html .= '<div><span class="label">เลขที่สัญญา:</span> <span class="value">' . htmlspecialchars($job['contract_number']) . '</span>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <span class="label">เช่าซื้อชื่อ:</span> <span class="value">' . htmlspecialchars($job['location_info']) . '</span>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <span class="label">กลุ่มงาน:</span> <span class="value">' . htmlspecialchars($job['product']) . '</span>
</div><br>';

$html .= '<div><span class="label">ค้างชำระปัจจุบัน:</span> <span class="value">' . htmlspecialchars($job['overdue_period']) . ' งวด, ' . number_format((float)($job['os'] ?? 0), 2) . ' บาท</span></div>';

$html .= '<div><span class="label">ทีม:</span> <span class="value">บ.บาร์เกน พ้อยท์ จำกัด</span>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <span class="label">วันที่ลงพื้นที่:</span> <span class="value">' . date('j/n/Y', strtotime($job['log_time'])) . '</span>
</div><br>';

$html .= '<div><span class="label">สถานที่ลง:</span> <span class="value">' . htmlspecialchars($job['location_area'] ?? '-') . '</span></div><br>';

$html .= '<div><span class="label">ผลการลงพื้นที่:</span><br><span class="value">' . nl2br(htmlspecialchars($job['result'] ?? '-')) . '</span></div><br>';

$html .= '<div><span class="label">หมายเหตุ:</span><br><span class="value">' . nl2br(htmlspecialchars($job['note'] ?? '-')) . '</span></div><br>';

$html .= '<div><span class="label">พิกัด:</span><br><span class="value">' . htmlspecialchars($job['gps'] ?? '-') . '</span></div><br>';

if (!empty($job['images'])) {
    $html .= '<div class="label" style="text-align:center; margin-top:20px;">รูปภาพประกอบ</div><br>';
    $images = json_decode($job['images'], true);

    require_once __DIR__ . '/../includes/image_optimizer.php';

    $html .= '<table class="img-table" align="center">';
    $col = 0;
    foreach ($images as $img) {
        $paths = ImageOptimizer::getImagePaths($img);
        $imgPath = '../uploads/job_photos/' . $paths['original'];
        if (file_exists($imgPath)) {
            if ($col % 2 == 0) {
                $html .= '<tr>';
            }
            $base64 = base64_encode(file_get_contents($imgPath));
            $src = 'data:image/jpeg;base64,' . $base64;
            $html .= '<td><img src="' . $src . '" class="photo"></td>';
            $col++;
            if ($col % 2 == 0) {
                $html .= '</tr>';
            }
        }
    }
    if ($col % 2 != 0) {
        $html .= '<td></td></tr>';
    }
    $html .= '</table>';
}

$mpdf->WriteHTML($html);
$mpdf->Output('job_detail_' . $job['contract_number'] . '.pdf', 'D');
exit;
