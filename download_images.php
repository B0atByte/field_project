<?php
require_once __DIR__ . '/includes/session_config.php';
if (!in_array($_SESSION['user']['role'], ['admin', 'field', 'manager'])) {
    http_response_code(403);
    exit("Access denied");
}

$job_id = $_GET['job_id'] ?? null;
if (!$job_id || !is_numeric($job_id)) {
    http_response_code(400);
    exit("Invalid job ID");
}

require_once __DIR__ . '/config/db.php';

// Increase execution time and memory limit
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

// ตั้งค่า temp directory สำหรับ ZipArchive
// ใช้ /tmp ที่มี permission เหมาะสมสำหรับ Docker container
$systemTempDir = '/tmp';
if (!is_dir($systemTempDir)) {
    $systemTempDir = sys_get_temp_dir();
}

// ตั้งค่า environment variable สำหรับ ZipArchive
putenv("TMPDIR=$systemTempDir");
putenv("TEMP=$systemTempDir");
putenv("TMP=$systemTempDir");

$stmt = $conn->prepare("SELECT images FROM job_logs WHERE job_id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data || empty($data['images'])) {
    http_response_code(404);
    exit("ไม่พบรูปภาพของงานนี้");
}

$images = json_decode($data['images'], true);
if (!is_array($images)) {
    http_response_code(500);
    exit("ข้อมูลรูปผิดพลาด");
}

require_once __DIR__ . '/includes/image_optimizer.php';

$uploadDir = __DIR__ . '/uploads/job_photos/';
$zipFilename = "job_images_$job_id.zip";

// ใช้ system temp directory แทน /var/www/html/tmp
$zipPath = $systemTempDir . '/' . $zipFilename;

// ตรวจสอบว่า temp directory สามารถเขียนได้หรือไม่
if (!is_writable($systemTempDir)) {
    error_log("ZipArchive Error: Temp directory $systemTempDir is not writable");
    http_response_code(500);
    exit("ไม่สามารถเขียนไฟล์ชั่วคราวได้ กรุณาติดต่อผู้ดูแลระบบ");
}

// ลบไฟล์ zip เก่าถ้ามี
if (file_exists($zipPath)) {
    @unlink($zipPath);
}

$zip = new ZipArchive();
$openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

if ($openResult !== true) {
    $errorMsg = "ไม่สามารถสร้างไฟล์ zip ได้";
    switch ($openResult) {
        case ZipArchive::ER_EXISTS:
            $errorMsg .= " (ไฟล์มีอยู่แล้ว)";
            break;
        case ZipArchive::ER_INCONS:
            $errorMsg .= " (ไฟล์ไม่สมบูรณ์)";
            break;
        case ZipArchive::ER_MEMORY:
            $errorMsg .= " (หน่วยความจำไม่เพียงพอ)";
            break;
        case ZipArchive::ER_NOENT:
            $errorMsg .= " (ไม่พบไฟล์)";
            break;
        case ZipArchive::ER_NOZIP:
            $errorMsg .= " (ไม่ใช่ไฟล์ zip)";
            break;
        case ZipArchive::ER_OPEN:
            $errorMsg .= " (ไม่สามารถเปิดไฟล์ได้)";
            break;
        case ZipArchive::ER_READ:
            $errorMsg .= " (ไม่สามารถอ่านไฟล์ได้)";
            break;
        case ZipArchive::ER_SEEK:
            $errorMsg .= " (ข้อผิดพลาดในการค้นหาไฟล์)";
            break;
    }
    error_log("ZipArchive Error: $errorMsg (Code: $openResult) - Path: $zipPath");
    http_response_code(500);
    exit($errorMsg);
}

$added = 0;
$errors = [];

foreach ($images as $img) {
    try {
        $paths = ImageOptimizer::getImagePaths($img);
        $imgPath = $uploadDir . $paths['original'];

        if (!file_exists($imgPath)) {
            $errors[] = "ไม่พบไฟล์: " . basename($paths['original']);
            continue;
        }

        if (!is_readable($imgPath)) {
            $errors[] = "ไม่สามารถอ่านไฟล์: " . basename($paths['original']);
            continue;
        }

        $addResult = $zip->addFile($imgPath, basename($paths['original']));
        if ($addResult) {
            $added++;
        } else {
            $errors[] = "ไม่สามารถเพิ่มไฟล์: " . basename($paths['original']);
        }
    } catch (Exception $e) {
        $errors[] = "ข้อผิดพลาด: " . $e->getMessage();
        error_log("Error adding file to zip: " . $e->getMessage());
    }
}

// ปิด zip file
$closeResult = $zip->close();

if (!$closeResult) {
    $lastError = error_get_last();
    $errorDetail = $lastError ? $lastError['message'] : 'Unknown error';
    error_log("ZipArchive::close() failed: $errorDetail - Path: $zipPath");

    // ลบไฟล์ที่อาจสร้างไว้ไม่สมบูรณ์
    if (file_exists($zipPath)) {
        @unlink($zipPath);
    }

    http_response_code(500);
    exit("ไม่สามารถปิดไฟล์ zip ได้: $errorDetail");
}

// ตรวจสอบว่ามีไฟล์ที่เพิ่มเข้า zip หรือไม่
if ($added === 0) {
    @unlink($zipPath);
    http_response_code(404);
    $errorMsg = "ไม่พบรูปที่สามารถบีบอัดได้";
    if (!empty($errors)) {
        $errorMsg .= "\n\nรายละเอียด:\n" . implode("\n", $errors);
    }
    exit($errorMsg);
}

// ตรวจสอบว่าไฟล์ zip ถูกสร้างและมีขนาดมากกว่า 0
if (!file_exists($zipPath) || filesize($zipPath) === 0) {
    error_log("ZipArchive Error: Created zip file is empty or doesn't exist - Path: $zipPath");
    @unlink($zipPath);
    http_response_code(500);
    exit("ไฟล์ zip ที่สร้างไม่สมบูรณ์");
}

// ส่งไฟล์ให้ผู้ใช้ download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// อ่านและส่งไฟล์
if (!readfile($zipPath)) {
    error_log("ZipArchive Error: Failed to read zip file - Path: $zipPath");
    http_response_code(500);
    exit("ไม่สามารถอ่านไฟล์ zip ได้");
}

// ลบไฟล์ชั่วคราว
@unlink($zipPath);

exit;
