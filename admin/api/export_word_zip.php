<?php
// Disable error display to prevent HTML output breaking JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Increase execution time and memory limit for bulk operations
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

// Register shutdown function to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    // Check if it's a fatal error or parse error
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        // Output JSON error for fatal errors if headers haven't been sent
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Critical Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
            ]);
        }
    }
});

/**
 * Export multiple jobs to Word files and create ZIP
 */
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['user']['role'], ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/csrf.php';
require_once '../../vendor/autoload.php';
require_once '../../includes/image_optimizer.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Validate CSRF
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get job IDs from request
$input = json_decode(file_get_contents('php://input'), true);
$job_ids = $input['job_ids'] ?? [];

if (empty($job_ids) || !is_array($job_ids)) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีข้อมูลที่จะ Export']);
    exit;
}

// Sanitize job IDs
$job_ids = array_map('intval', $job_ids);
$job_ids = array_filter($job_ids, fn($id) => $id > 0);

if (empty($job_ids)) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูล Job ID ไม่ถูกต้อง']);
    exit;
}

try {
    // Create temp directory for Word files
    $temp_dir = sys_get_temp_dir() . '/word_export_' . uniqid();
    if (!mkdir($temp_dir, 0755, true)) {
        throw new Exception('ไม่สามารถสร้างโฟลเดอร์ชั่วคราวได้');
    }

    $created_files = [];
    $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
    $types = str_repeat('i', count($job_ids));

    // Query all jobs
    $sql = "SELECT j.*, l.note, l.result, l.gps, l.images, l.created_at AS log_time,
            u1.name AS assigned_name
            FROM jobs j
            LEFT JOIN (
                SELECT jl1.*
                FROM job_logs jl1
                INNER JOIN (
                    SELECT job_id, MAX(id) as max_id
                    FROM job_logs
                    GROUP BY job_id
                ) jl2 ON jl1.job_id = jl2.job_id AND jl1.id = jl2.max_id
            ) l ON j.id = l.job_id
            LEFT JOIN users u1 ON j.assigned_to = u1.id
            WHERE j.id IN ($placeholders)
            AND j.status = 'completed'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$job_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($job = $result->fetch_assoc()) {
        $word_file = createWordDocument($job, $temp_dir);
        if ($word_file) {
            $created_files[] = $word_file;
        }
    }
    $stmt->close();

    if (empty($created_files)) {
        throw new Exception('ไม่สามารถสร้างไฟล์ Word ได้');
    }

    // Create ZIP file
    $zip_filename = 'export_word_' . date('Ymd_His') . '.zip';
    $zip_path = __DIR__ . '/../../uploads/temp/' . $zip_filename;

    // Ensure temp upload directory exists
    $temp_upload_dir = __DIR__ . '/../../uploads/temp';
    if (!is_dir($temp_upload_dir)) {
        mkdir($temp_upload_dir, 0755, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('ไม่สามารถสร้างไฟล์ ZIP ได้');
    }

    foreach ($created_files as $file) {
        $zip->addFile($file, basename($file));
    }

    if (!$zip->close()) {
        throw new Exception('ไม่สามารถบันทึกไฟล์ ZIP ได้');
    }

    if (!file_exists($zip_path)) {
        throw new Exception('ไฟล์ ZIP ไม่ถูกสร้าง');
    }

    // Ensure file is readable
    chmod($zip_path, 0644);

    // Clean up temp Word files
    foreach ($created_files as $file) {
        @unlink($file);
    }
    @rmdir($temp_dir);

    // Use absolute path for download URL to avoid relative path issues
    $download_url = '/uploads/temp/' . $zip_filename;

    echo json_encode([
        'success' => true,
        'count' => count($created_files),
        'download_url' => $download_url,
        'message' => 'สร้างไฟล์เรียบร้อย'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Clean up on error
    if (isset($temp_dir) && is_dir($temp_dir)) {
        array_map('unlink', glob("$temp_dir/*.*"));
        @rmdir($temp_dir);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Create Word document for a single job
 */
function createWordDocument($job, $temp_dir)
{
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();

    // Styles
    $h1 = ['name' => 'TH Sarabun New', 'size' => 16, 'bold' => true];
    $label14 = ['name' => 'TH Sarabun New', 'size' => 14, 'bold' => true];
    $value14 = ['name' => 'TH Sarabun New', 'size' => 14];
    $centered = ['alignment' => 'center'];

    // Header
    $section->addText('รายงานผลการลงพื้นที่', $h1, $centered);
    $section->addTextBreak(1);

    // Helper function for styled lines
    $addLineStyled = function ($section, $items) {
        $textrun = $section->addTextRun();
        foreach ($items as $item) {
            $textrun->addText($item['label'], ['bold' => true, 'name' => 'TH Sarabun New', 'size' => 14]);
            $textrun->addText(' ' . $item['value'] . '   ', ['name' => 'TH Sarabun New', 'size' => 14]);
        }
    };

    // Row 1: Contract info
    $addLineStyled($section, [
        ['label' => 'เลขที่สัญญา:', 'value' => $job['contract_number']],
        ['label' => 'เช่าซื้อชื่อ:', 'value' => $job['location_info']],
        ['label' => 'กลุ่มงาน:', 'value' => $job['product'] ?? '-'],
    ]);

    // Row 2: OS
    $os_clean = str_replace(',', '', $job['os'] ?? '0');
    $os_text = number_format(floatval($os_clean), 2) . ' บาท';
    $addLineStyled($section, [
        ['label' => 'ค้างชำระปัจจุบัน:', 'value' => 'OutStanding : ' . $os_text],
    ]);

    // Row 3: Team & Date
    $log_date = $job['log_time'] ? date('j/n/Y', strtotime($job['log_time'])) : '-';
    $addLineStyled($section, [
        ['label' => 'ทีม:', 'value' => 'บ.บาร์เกน พ้อยท์ จำกัด'],
        ['label' => 'วันที่ลงพื้นที่:', 'value' => $log_date],
    ]);

    // Row 4: Location
    $addLineStyled($section, [
        ['label' => 'สถานที่ลง:', 'value' => $job['location_area'] ?? '-'],
    ]);

    $section->addTextBreak(1);

    // Result
    $addLineStyled($section, [
        ['label' => 'ผลการลงพื้นที่:', 'value' => $job['result'] ?? '-'],
    ]);

    // Note
    $addLineStyled($section, [
        ['label' => 'หมายเหตุ:', 'value' => $job['note'] ?? '-'],
    ]);

    // GPS
    $addLineStyled($section, [
        ['label' => 'พิกัด:', 'value' => $job['gps'] ?? '-'],
    ]);

    $section->addTextBreak(1);

    // Images
    if (!empty($job['images'])) {
        $images = json_decode($job['images'], true);
        if (is_array($images) && count($images) > 0) {
            $section->addText('รูปภาพประกอบ', $label14, $centered);
            $section->addTextBreak(1);

            $table = $section->addTable(['alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
            $count = 0;

            foreach ($images as $img) {
                $paths = ImageOptimizer::getImagePaths($img);
                $imgPath = __DIR__ . '/../../uploads/job_photos/' . $paths['original'];

                if (file_exists($imgPath)) {
                    if ($count % 2 === 0) {
                        $table->addRow();
                    }
                    $table->addCell(3000)->addImage($imgPath, [
                        'width' => 180,
                        'height' => 135,
                        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
                    ]);
                    $count++;
                }
            }
        }
    }

    // Save file
    $safe_contract = preg_replace('/[^a-zA-Z0-9ก-๙\-_]/', '_', $job['contract_number']);
    $filename = $temp_dir . '/job_' . $safe_contract . '_' . $job['id'] . '.docx';

    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($filename);

    return file_exists($filename) ? $filename : null;
}
