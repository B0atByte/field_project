<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
requirePermission('action_export_word');

require_once '../vendor/autoload.php';
include '../config/db.php';

// Increase execution time and memory limit
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;

$job_id = $_POST['job_id'] ?? null;
$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

if (!$job_id)
    die("Missing job ID");

// ดึงข้อมูลจาก jobs + job_logs (เอา log ล่าสุด)
if (in_array($role, ['admin', 'manager'])) {
    $stmt = $conn->prepare("SELECT j.*, l.note, l.result, l.gps, l.images, l.created_at AS log_time, j.os,
        u1.name AS assigned_name
        FROM jobs j
        LEFT JOIN (
            SELECT job_id, note, result, gps, images, created_at
            FROM job_logs
            WHERE job_id = ?
            ORDER BY id DESC
            LIMIT 1
        ) l ON j.id = l.job_id
        LEFT JOIN users u1 ON j.assigned_to = u1.id
        WHERE j.id = ?");
    $stmt->bind_param("ii", $job_id, $job_id);
} else {
    $stmt = $conn->prepare("SELECT j.*, l.note, l.result, l.gps, l.images, l.created_at AS log_time, j.os,
        u1.name AS assigned_name
        FROM jobs j
        LEFT JOIN (
            SELECT job_id, note, result, gps, images, created_at
            FROM job_logs
            WHERE job_id = ?
            ORDER BY id DESC
            LIMIT 1
        ) l ON j.id = l.job_id
        LEFT JOIN users u1 ON j.assigned_to = u1.id
        WHERE j.id = ? AND j.assigned_to = ?");
    $stmt->bind_param("iii", $job_id, $job_id, $user_id);
}

$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$job)
    die("ไม่พบข้อมูล");

$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\ComplexType\ProofErr());
$section = $phpWord->addSection([
    'marginTop'    => 720,   // 0.5 นิ้ว
    'marginBottom' => 720,
    'marginLeft'   => 900,   // 0.625 นิ้ว
    'marginRight'  => 900,
]);

// === STYLES ===
$h1      = ['name' => 'TH Sarabun New', 'size' => 16, 'bold' => true];
$label14 = ['name' => 'TH Sarabun New', 'size' => 14, 'bold' => true];
$value14 = ['name' => 'TH Sarabun New', 'size' => 14];
$centered = ['alignment' => 'center'];
$paraCompact = ['spacing' => ['before' => 0, 'after' => 0, 'line' => 240, 'lineRule' => 'auto']];

// === HEADER ===
$section->addText('รายงานผลการลงพื้นที่', $h1, $centered);

// === FORMAT แบบหัวข้อหนา ข้อมูลบาง ===
function addLineStyled($section, $items = [])
{
    $paraStyle = ['spacing' => ['before' => 0, 'after' => 0, 'line' => 240, 'lineRule' => 'auto']];
    $textrun = $section->addTextRun($paraStyle);
    foreach ($items as $item) {
        $textrun->addText($item['label'], ['bold' => true, 'name' => 'TH Sarabun New', 'size' => 14]);
        $textrun->addText(' ' . $item['value'] . '   ', ['name' => 'TH Sarabun New', 'size' => 14]);
    }
}

// แถว 1: เลขที่สัญญา | เช่าซื้อชื่อ | กลุ่มงาน
addLineStyled($section, [
    ['label' => 'เลขที่สัญญา:', 'value' => $job['contract_number']],
    ['label' => 'เช่าซื้อชื่อ:', 'value' => $job['location_info']],
    ['label' => 'กลุ่มงาน:', 'value' => $job['product'] ?? '-'],
]);

// แถว 2: ค้างชำระ
$os_clean = str_replace(',', '', $job['os']);
$os_text = number_format(floatval($os_clean), 2) . ' บาท';
addLineStyled($section, [
    ['label' => 'ค้างชำระปัจจุบัน:', 'value' => 'OutStanding : ' . $os_text],
]);

// แถว 3: ทีม | วันที่ลงพื้นที่
addLineStyled($section, [
    ['label' => 'ทีม:', 'value' => 'บ.บาร์เกน พ้อยท์ จำกัด'],
    ['label' => 'วันที่ลงพื้นที่:', 'value' => date('j/n/Y', strtotime($job['log_time']))],
]);

// แถว 4: สถานที่ลง
addLineStyled($section, [
    ['label' => 'สถานที่ลง:', 'value' => $job['location_area'] ?? '-'],
]);

// ผลการลงพื้นที่
addLineStyled($section, [
    ['label' => 'ผลการลงพื้นที่:', 'value' => $job['result'] ?? '-'],
]);

// หมายเหตุ
addLineStyled($section, [
    ['label' => 'หมายเหตุ:', 'value' => $job['note'] ?? '-'],
]);

// พิกัด
addLineStyled($section, [
    ['label' => 'พิกัด:', 'value' => $job['gps'] ?? '-'],
]);

// === รูปภาพ ===
if (!empty($job['images'])) {
    $section->addText('รูปภาพประกอบ', $label14, $centered);

    $images = json_decode($job['images'], true);
    require_once __DIR__ . '/../includes/image_optimizer.php';

    $table = $section->addTable([
        'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
        'cellSpacing' => 50,
    ]);
    $count = 0;

    foreach ($images as $img) {
        $paths = ImageOptimizer::getImagePaths($img);
        $imgPath = '../uploads/job_photos/' . $paths['original'];
        if (file_exists($imgPath)) {
            if ($count % 3 === 0) {
                $table->addRow(2400);
            }
            $table->addCell(2800)->addImage($imgPath, [
                'width' => 175,
                'height' => 131,
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            ]);
            $count++;
        }
    }
    // เติม cell ว่างให้ครบแถวสุดท้าย
    $remainder = $count % 3;
    if ($remainder !== 0) {
        for ($i = $remainder; $i < 3; $i++) {
            $table->addCell(2800);
        }
    }
}

// === OUTPUT ===
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header('Content-Disposition: attachment; filename="job_' . $job['contract_number'] . '.docx"');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save("php://output");
exit;
