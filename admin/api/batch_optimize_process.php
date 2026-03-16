<?php
/**
 * Batch Image Optimization API Endpoint
 *
 * Actions:
 * - start: เริ่มต้นและนับจำนวนรูปทั้งหมด
 * - process: ประมวลผลรูปแบบ batch
 */

require_once __DIR__ . '/../../includes/session_config.php';

// Check permission
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Check CSRF token
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? null;
if (!$token || !validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    switch ($action) {
        case 'start':
            handleStart($conn, $input);
            break;

        case 'process':
            handleProcess($conn, $input);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle start action - นับจำนวนรูปทั้งหมด
 */
function handleStart($conn, $input) {
    // Query รูปภาพเก่าทั้งหมด (รูปที่ยังอยู่ใน root folder)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_count
        FROM job_logs
        WHERE images IS NOT NULL
          AND images != '[]'
          AND images != ''
    ");

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    // นับจำนวนไฟล์จริงๆ
    $total = 0;
    $stmt = $conn->prepare("SELECT id, images FROM job_logs WHERE images IS NOT NULL AND images != '[]' AND images != ''");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $images = json_decode($row['images'], true);
        if (is_array($images)) {
            foreach ($images as $img) {
                // เช็คว่าเป็นรูปแบบเก่า (ไม่มี / ในชื่อไฟล์)
                if (strpos($img, '/') === false) {
                    $total++;
                }
            }
        }
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'total' => $total,
        'message' => "พบรูปภาพที่ต้อง optimize ทั้งหมด {$total} รูป"
    ]);
}

/**
 * Handle process action - ประมวลผลแบบ batch
 */
function handleProcess($conn, $input) {
    $dryRun = $input['dryRun'] ?? true;
    $offset = $input['offset'] ?? 0;
    $limit = $input['limit'] ?? 50;

    $results = [];
    $uploadDir = __DIR__ . '/../../uploads/job_photos/';

    // Query job_logs ที่มีรูปภาพ
    $stmt = $conn->prepare("
        SELECT id, job_id, images
        FROM job_logs
        WHERE images IS NOT NULL
          AND images != '[]'
          AND images != ''
        ORDER BY id
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    $processed = 0;
    $skipped_offset = 0;

    while ($row = $result->fetch_assoc()) {
        $log_id = $row['id'];
        $job_id = $row['job_id'];
        $images = json_decode($row['images'], true);

        if (!is_array($images)) {
            continue;
        }

        $newImages = [];
        $hasChanges = false;

        foreach ($images as $img) {
            // ข้ามรูปที่อยู่ในโครงสร้างใหม่แล้ว
            if (strpos($img, '/') !== false) {
                $newImages[] = $img;
                continue;
            }

            // ข้าม offset
            if ($skipped_offset < $offset) {
                $skipped_offset++;
                $newImages[] = $img;
                continue;
            }

            // ถึง limit แล้ว
            if ($processed >= $limit) {
                $newImages[] = $img;
                continue;
            }

            // ประมวลผลรูป
            $oldPath = $uploadDir . $img;

            if (!file_exists($oldPath)) {
                $results[] = [
                    'filename' => $img,
                    'success' => false,
                    'skipped' => false,
                    'message' => 'ไม่พบไฟล์'
                ];
                $processed++;
                continue;
            }

            try {
                if ($dryRun) {
                    // Dry run - ไม่แก้ไขจริง
                    $results[] = [
                        'filename' => $img,
                        'success' => true,
                        'skipped' => false,
                        'message' => '[DRY RUN] จะถูก optimize'
                    ];
                    $newImages[] = $img;
                } else {
                    // Live mode - ทำจริง
                    $optimizer = new ImageOptimizer();

                    // ใช้ file creation time เป็นเกณฑ์วันที่
                    $fileTime = filectime($oldPath);
                    $originalName = $img;

                    // Optimize และบันทึก
                    $relativePath = $optimizer->optimizeAndSave($oldPath, $originalName, $job_id);

                    if ($relativePath) {
                        // ลบไฟล์เก่า
                        @unlink($oldPath);

                        $newImages[] = $relativePath;
                        $hasChanges = true;

                        $results[] = [
                            'filename' => $img,
                            'success' => true,
                            'skipped' => false,
                            'message' => 'Optimized → ' . $relativePath
                        ];
                    } else {
                        $results[] = [
                            'filename' => $img,
                            'success' => false,
                            'skipped' => false,
                            'message' => 'Optimization failed'
                        ];
                        $newImages[] = $img; // Keep old path
                    }
                }

                $processed++;

            } catch (Exception $e) {
                $results[] = [
                    'filename' => $img,
                    'success' => false,
                    'skipped' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ];
                $newImages[] = $img; // Keep old path
                $processed++;
            }

            // ถึง limit แล้วให้หยุด
            if ($processed >= $limit) {
                // เอารูปที่เหลือใส่กลับ
                $remainingKeys = array_slice(array_keys($images), array_search($img, $images) + 1);
                foreach ($remainingKeys as $key) {
                    $newImages[] = $images[$key];
                }
                break;
            }
        }

        // Update database ถ้าไม่ใช่ dry run และมีการเปลี่ยนแปลง
        if (!$dryRun && $hasChanges) {
            $newImagesJson = json_encode($newImages, JSON_UNESCAPED_UNICODE);
            $updateStmt = $conn->prepare("UPDATE job_logs SET images = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newImagesJson, $log_id);
            $updateStmt->execute();
            $updateStmt->close();
        }

        // ถึง limit แล้วให้หยุด loop
        if ($processed >= $limit) {
            break;
        }
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'results' => $results,
        'processed' => $processed
    ]);
}
