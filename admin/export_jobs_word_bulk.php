<?php
/**
 * Bulk Export Jobs to Word (Multiple Jobs → ZIP file)
 * รองรับ 2 โหมด:
 * 1. Export by selected job_ids (checkbox)
 * 2. Export by date range (วันที่เสร็จ)
 */

require_once __DIR__ . '/../includes/session_config.php';
if (!in_array($_SESSION['user']['role'], ['admin', 'manager'])) {
    die("Unauthorized");
}

require_once '../vendor/autoload.php';
include '../config/db.php';
require_once '../includes/csrf.php';

// Increase execution time and memory limit for bulk operations
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// ตรวจสอบ CSRF
requireCsrfToken();

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

// รับค่า filter ทั้งหมด
$job_ids = $_POST['job_ids'] ?? null;
$date_from = $_POST['date_from'] ?? null;
$date_to = $_POST['date_to'] ?? null;
$assigned_to = $_POST['assigned_to'] ?? null;
$q = $_POST['q'] ?? null;
$export_mode = $_POST['export_mode'] ?? 'selected'; // 'selected', 'date_range', 'filter'

// =========================
// ดึงรายการ jobs ที่จะ export
// =========================
$jobs = [];

if ($export_mode === 'selected' && $job_ids) {
    // โหมด 1: Export jobs ที่เลือก
    $ids_array = explode(',', $job_ids);
    $ids_array = array_map('intval', $ids_array);
    $placeholders = implode(',', array_fill(0, count($ids_array), '?'));

    $sql = "SELECT j.id, j.contract_number, j.location_info, j.product, j.os, j.created_at,
                   u1.name AS assigned_name
            FROM jobs j
            LEFT JOIN users u1 ON j.assigned_to = u1.id
            WHERE j.id IN ($placeholders)
            ORDER BY j.contract_number";

    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($ids_array));
    $stmt->bind_param($types, ...$ids_array);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    $stmt->close();

} elseif ($export_mode === 'filter' || $export_mode === 'date_range') {
    // โหมด 2: Export ตาม filter (รวม date_range ด้วย)

    // สร้าง WHERE clause ตาม filter
    $where_parts = ["j.status = 'completed'"];
    $params = [];
    $types = "";

    if ($date_from) {
        $where_parts[] = "DATE(j.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    if ($date_to) {
        $where_parts[] = "DATE(j.created_at) <= ?";
        $params[] = $date_to;
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

    $sql = "SELECT j.id, j.contract_number, j.location_info, j.product, j.os, j.created_at,
                   u1.name AS assigned_name
            FROM jobs j
            LEFT JOIN users u1 ON j.assigned_to = u1.id
            WHERE $where_clause
            ORDER BY j.contract_number";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }

    if (!empty($params)) {
        $stmt->close();
    }
} else {
    die("กรุณาเลือกงานหรือระบุตัวกรอง");
}

// ดึง log ล่าสุดของแต่ละ job
foreach ($jobs as &$job) {
    $job_id = $job['id'];
    $log_sql = "SELECT note, result, gps, images, created_at AS log_time
                FROM job_logs
                WHERE job_id = ?
                ORDER BY id DESC LIMIT 1";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("i", $job_id);
    $log_stmt->execute();
    $log_result = $log_stmt->get_result();
    $log_data = $log_result->fetch_assoc();
    $log_stmt->close();

    $job['note'] = $log_data['note'] ?? '';
    $job['result'] = $log_data['result'] ?? '';
    $job['gps'] = $log_data['gps'] ?? '';
    $job['images'] = $log_data['images'] ?? '';
    $job['log_time'] = $log_data['log_time'] ?? '';
}
unset($job);

if (empty($jobs)) {
    die("ไม่พบข้อมูลที่ต้องการ export ตามเงื่อนไขที่กำหนด");
}

// =========================
// สร้าง Word files และรวมเป็น ZIP
// =========================

$temp_dir = sys_get_temp_dir() . '/field_project_export_' . uniqid();
if (!mkdir($temp_dir, 0755, true)) {
    die("ไม่สามารถสร้าง temporary directory");
}

$created_files = [];

// === Helper Function (ประกาศนอก loop) ===
function addLineStyled($section, $items = [])
{
    $textrun = $section->addTextRun();
    foreach ($items as $item) {
        $textrun->addText($item['label'], ['bold' => true, 'name' => 'TH Sarabun New', 'size' => 14]);
        $textrun->addText(' ' . $item['value'] . '   ', ['name' => 'TH Sarabun New', 'size' => 14]);
    }
}

foreach ($jobs as $job) {
    try {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // === STYLES ===
        $h1 = ['name' => 'TH Sarabun New', 'size' => 16, 'bold' => true];
        $label14 = ['name' => 'TH Sarabun New', 'size' => 14, 'bold' => true];
        $value14 = ['name' => 'TH Sarabun New', 'size' => 14];
        $centered = ['alignment' => 'center'];

        // === HEADER ===
        $section->addText('รายงานผลการลงพื้นที่', $h1, $centered);
        $section->addTextBreak(1);

        // แถว 1: เลขที่สัญญา | เช่าซื้อชื่อ | กลุ่มงาน
        addLineStyled($section, [
            ['label' => 'เลขที่สัญญา:', 'value' => $job['contract_number'] ?? '-'],
            ['label' => 'เช่าซื้อชื่อ:', 'value' => $job['location_info'] ?? '-'],
            ['label' => 'กลุ่มงาน:', 'value' => $job['product'] ?? '-'],
        ]);

        // แถว 2: ค้างชำระ
        $os_clean = str_replace(',', '', $job['os'] ?? '0');
        $os_text = number_format(floatval($os_clean), 2) . ' บาท';
        addLineStyled($section, [
            ['label' => 'ค้างชำระปัจจุบัน:', 'value' => 'OutStanding : ' . $os_text],
        ]);

        // แถว 3: ทีม | วันที่ลงพื้นที่
        $log_date = $job['log_time'] ? date('j/n/Y', strtotime($job['log_time'])) : '-';
        addLineStyled($section, [
            ['label' => 'ทีม:', 'value' => 'บ.บาร์เกน พ้อยท์ จำกัด'],
            ['label' => 'วันที่ลงพื้นที่:', 'value' => $log_date],
        ]);

        // แถว 4: สถานที่ลง
        addLineStyled($section, [
            ['label' => 'สถานที่ลง:', 'value' => $job['location_info'] ?? '-'],
        ]);

        // แถว 5: ผลการลง
        addLineStyled($section, [
            ['label' => 'ผลการลง:', 'value' => $job['result'] ?? '-'],
        ]);

        // แถว 6: หมายเหตุ
        $section->addText('หมายเหตุ:', $label14);
        $section->addText($job['note'] ?? '-', $value14);
        $section->addTextBreak(1);

        // === รูปภาพ ===
        if (!empty($job['images'])) {
            $section->addText('รูปภาพประกอบ:', $label14);
            $section->addTextBreak(1);

            $images_array = json_decode($job['images'], true);
            if (is_array($images_array)) {
                $img_count = 0;
                foreach ($images_array as $img_path) {
                    // แปลง relative path เป็น absolute path
                    $full_path = __DIR__ . '/../uploads/job_photos/' . $img_path;

                    if (file_exists($full_path)) {
                        try {
                            $section->addImage($full_path, [
                                'width' => 400,
                                'height' => 300,
                                'wrappingStyle' => 'inline'
                            ]);
                            $section->addTextBreak(1);
                            $img_count++;

                            // จำกัดไม่เกิน 4 รูปต่อเอกสาร
                            if ($img_count >= 4)
                                break;
                        } catch (Exception $e) {
                            error_log("Failed to add image: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        // === GPS ===
        if (!empty($job['gps'])) {
            $section->addTextBreak(1);
            addLineStyled($section, [
                ['label' => 'พิกัด GPS:', 'value' => $job['gps']],
            ]);
        }

        // บันทึก Word file
        $contract_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $job['contract_number']);
        $filename = "รายงาน_{$contract_safe}.docx";
        $filepath = $temp_dir . '/' . $filename;

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filepath);

        $created_files[] = $filepath;

    } catch (Exception $e) {
        error_log("Error creating Word file for job {$job['id']}: " . $e->getMessage());
    }
}

// =========================
// สร้าง ZIP file
// =========================

if (empty($created_files)) {
    // ลบ temp directory
    array_map('unlink', glob("$temp_dir/*.*"));
    rmdir($temp_dir);
    die("ไม่สามารถสร้างไฟล์ Word ได้");
}

$zip_filename = "รายงานผลงาน_" . date('Y-m-d_His') . ".zip";
$zip_filepath = $temp_dir . '/' . $zip_filename;

$zip = new ZipArchive();
if ($zip->open($zip_filepath, ZipArchive::CREATE) !== true) {
    die("ไม่สามารถสร้างไฟล์ ZIP");
}

foreach ($created_files as $file) {
    $zip->addFile($file, basename($file));
}

$zip->close();

// =========================
// ดาวน์โหลด ZIP
// =========================

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($zip_filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

readfile($zip_filepath);

// =========================
// ลบไฟล์ชั่วคราว
// =========================

foreach ($created_files as $file) {
    @unlink($file);
}
@unlink($zip_filepath);
@rmdir($temp_dir);

exit;
?>