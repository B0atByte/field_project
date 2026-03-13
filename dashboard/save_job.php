<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!in_array($_SESSION['user']['role'], ['field', 'admin'])) {
    die("Unauthorized");
}

include '../config/db.php';
require_once '../includes/csrf.php';
requireCsrfToken();

// ---- Input & basic sanitize ----
$user_id = (int)($_SESSION['user']['id'] ?? 0);
$job_id  = (int)($_POST['job_id'] ?? 0);
if ($user_id <= 0 || $job_id <= 0) {
    die("Invalid data");
}

$result   = trim($_POST['result'] ?? 'ไม่ระบุ');
$note     = trim($_POST['note'] ?? '');
$gps      = trim($_POST['gps'] ?? '');
$log_time = $_POST['log_time'] ?? date("Y-m-d H:i:s");
$images   = [];

// ---- Load Image Optimizer ----
require_once __DIR__ . '/../includes/image_optimizer.php';
$optimizer = new ImageOptimizer();

// ---- Handle images (ใช้ Image Optimizer) ----
if (!empty($_FILES['images']['name'][0])) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        if (!is_uploaded_file($tmp_name)) continue;

        // ตรวจสอบขนาดไฟล์
        $fileSize = $_FILES['images']['size'][$key];
        if ($fileSize > $maxFileSize) {
            continue; // ข้ามไฟล์ที่ใหญ่เกินไป
        }

        // ตรวจสอบ MIME type จริงๆ (ไม่เชื่อ client)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            continue; // ข้ามไฟล์ที่ไม่ใช่รูปภาพ
        }

        $orig = basename($_FILES['images']['name'][$key]);
        $ext  = pathinfo($orig, PATHINFO_EXTENSION);

        // อนุญาตเฉพาะ extension ที่ปลอดภัย
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
        $ext = strtolower($ext);
        if (!in_array($ext, $allowedExt)) {
            continue;
        }

        // Optimize และบันทึกรูป (สร้าง original + thumbnail)
        $relativePath = $optimizer->optimizeAndSave($tmp_name, $orig, $job_id);

        if ($relativePath) {
            $images[] = $relativePath;
        }
    }
}
$img_json = json_encode($images, JSON_UNESCAPED_UNICODE);

// ---- Insert job_logs (fix types: i i s s s s s) ----
$stmt = $conn->prepare(
    "INSERT INTO job_logs (job_id, user_id, result, note, gps, images, log_time)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("iisssss", $job_id, $user_id, $result, $note, $gps, $img_json, $log_time);
$stmt->execute();
$stmt->close();

// ---- Update job: mark completed (ตามเดิม) + ปิดการลบอัตโนมัติ ----
// ถ้าอยากเปลี่ยนเป็น in_progress ให้แก้ 'completed' เป็น 'in_progress'
$stmt = $conn->prepare(
    "UPDATE jobs
        SET status = 'completed',
            auto_delete_at = NULL,
            updated_at = NOW()
      WHERE id = ?"
);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$stmt->close();

// ---- Fetch job for notification ----
$job = ['contract_number' => '-', 'location_info' => '-', 'province' => '-'];
$stmt = $conn->prepare("SELECT contract_number, location_info, province FROM jobs WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $row = $res->fetch_assoc()) $job = $row;
$stmt->close();

// ---- Send Discord notification ----
function sendToDiscord($job, $note, $gps, $images, $result) {
    // โหลด webhook URL จาก environment variable
    require_once __DIR__ . '/../config/env.php';
    $webhookUrl = env('DISCORD_WEBHOOK_URL', '');

    // ถ้าไม่มี webhook URL ให้ข้ามการส่ง
    if (empty($webhookUrl)) {
        return;
    }

    $gpsLink = "https://www.google.com/maps?q=" . urlencode($gps);
    $content = "\u2728 **ลงพื้นที่ภาคสนามสำเร็จแล้ว** \u2728\n"
        . "\n**เลขที่สัญญา:** `{$job['contract_number']}`"
        . "\n**พื้นที่:** {$job['location_info']} ({$job['province']})"
        . "\n**ผลการลงพื้นที่:** {$result}"
        . "\n**สรุป:** {$note}"
        . "\n**พิกัด GPS:** {$gps}\n🌍 [$gpsLink]($gpsLink)";

    if (!empty($images)) {
        foreach ($images as $img) {
            $img_url = "http://" . $_SERVER['HTTP_HOST'] . "/uploads/job_photos/" . $img;
            $content .= "\n🖼️ รูปภาพ: $img_url";
        }
    }

    $data = json_encode(["content" => $content], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
sendToDiscord($job, $note, $gps, $images, $result);

// ---- Redirect ----
echo "<script>alert('✅ บันทึกผลงานสำเร็จ'); window.location.href='field.php';</script>";
