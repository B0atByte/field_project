<?php
// Disable error display to prevent HTML output breaking JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

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
 * Process Excel file and match with database records
 * Returns list of jobs that can be exported to Word
 */
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/permissions.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}
if (!hasPermission('action_export_word')) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ Export Word']);
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/csrf.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Validate CSRF
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check file upload
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบไฟล์ที่อัปโหลด']);
    exit;
}

$file = $_FILES['excel_file'];
$allowed_types = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel'
];

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'ไฟล์ไม่ใช่ Excel']);
    exit;
}

try {
    // Load Excel file
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    $items = [];
    $matched_job_ids = [];
    $found_count = 0;
    $not_found_count = 0;

    // Read data from row 2 (skip header)
    for ($row = 2; $row <= $highestRow; $row++) {
        // Column B = contract_number (เลขที่สัญญา)
        $contract_number = trim($sheet->getCell("B{$row}")->getValue() ?? '');

        // Column D = customer name (ชื่อลูกค้า)
        $customer_name = trim($sheet->getCell("D{$row}")->getValue() ?? '');

        // Column S = submit date (เวลาที่ลงรายงานล่าสุด)
        $submit_date_raw = $sheet->getCell("S{$row}")->getValue();
        $submit_date = '';

        // Handle different date formats
        if ($submit_date_raw) {
            if (is_numeric($submit_date_raw)) {
                // Excel serial date
                $submit_date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($submit_date_raw)->format('Y-m-d H:i');
            } else {
                // String date
                $submit_date = date('Y-m-d H:i', strtotime($submit_date_raw));
            }
        }

        if (empty($contract_number)) {
            continue; // Skip empty rows
        }

        // Find matching job in database
        $job_id = null;
        $found = false;

        if (!empty($submit_date)) {
            // Match by contract_number + submit date
            $sql = "SELECT j.id
                    FROM jobs j
                    INNER JOIN job_logs jl ON j.id = jl.job_id
                    WHERE j.contract_number = ?
                    AND j.status = 'completed'
                    AND DATE(jl.created_at) = ?
                    ORDER BY jl.id DESC
                    LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $contract_number, $submit_date);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row_data = $result->fetch_assoc()) {
                $job_id = $row_data['id'];
                $found = true;
            }
            $stmt->close();
        }

        // If not found by date, try just contract_number with completed status
        if (!$found) {
            $sql = "SELECT j.id
                    FROM jobs j
                    INNER JOIN job_logs jl ON j.id = jl.job_id
                    WHERE j.contract_number = ?
                    AND j.status = 'completed'
                    ORDER BY jl.created_at DESC
                    LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $contract_number);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row_data = $result->fetch_assoc()) {
                $job_id = $row_data['id'];
                $found = true;
            }
            $stmt->close();
        }

        $items[] = [
            'contract_number' => $contract_number,
            'customer_name' => $customer_name,
            'submit_date' => $submit_date ?: '-',
            'found' => $found,
            'job_id' => $job_id
        ];

        if ($found && $job_id) {
            $matched_job_ids[] = $job_id;
            $found_count++;
        } else {
            $not_found_count++;
        }
    }

    // Remove duplicates
    $matched_job_ids = array_unique($matched_job_ids);

    echo json_encode([
        'success' => true,
        'total' => count($items),
        'found' => $found_count,
        'not_found' => $not_found_count,
        'items' => $items,
        'matched' => array_values($matched_job_ids)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการอ่านไฟล์: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
