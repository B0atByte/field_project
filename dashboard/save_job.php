<?php
session_start();
if (!in_array($_SESSION['user']['role'], ['field', 'admin'])) {
    die("Unauthorized");
}

include '../config/db.php';

$user_id = $_SESSION['user']['id'];
$job_id = $_POST['job_id'];
$result = $_POST['result'] ?? 'ไม่ระบุ';
$note = $_POST['note'];
$gps = $_POST['gps'];
$log_time = $_POST['log_time'] ?? date("Y-m-d H:i:s");
$images = [];

if (!is_dir('../uploads/job_photos')) {
    mkdir('../uploads/job_photos', 0777, true);
}

// 📸 จัดการรูปภาพ
if (!empty($_FILES['images']['name'][0])) {
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        $filename = time() . '_' . basename($_FILES['images']['name'][$key]);
        $target_path = '../uploads/job_photos/' . $filename;
        if (move_uploaded_file($tmp_name, $target_path)) {
            $images[] = $filename;
        }
    }
}

$img_json = json_encode($images, JSON_UNESCAPED_UNICODE);

// ✅ บันทึกลง job_logs
$stmt = $conn->prepare("INSERT INTO job_logs (job_id, user_id, result, note, gps, images, log_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssss", $job_id, $user_id, $result, $note, $gps, $img_json, $log_time);
$stmt->execute();
$stmt->close();

// ✅ อัปเดตสถานะงาน
$conn->query("UPDATE jobs SET status = 'completed' WHERE id = $job_id");

// ✅ ดึงข้อมูลสำหรับแจ้งเตือน
$job_result = $conn->query("SELECT contract_number, location_info, province FROM jobs WHERE id = $job_id");
$job = $job_result && $job_result->num_rows > 0 ? $job_result->fetch_assoc() : ['contract_number' => '-', 'location_info' => '-', 'province' => '-'];

// ✅ ส่งแจ้งเตือน Discord
function sendToDiscord($job, $note, $gps, $images, $result) {
    $webhookUrl = "https://discord.com/api/webhooks/1372323356715778119/Ew1itSE--T_bYsggb3nl8kQ7MG2cNFaShBkxu_XZkir_smpOBWWnBUnRygvu5dmkogO4";

    $gpsLink = "https://www.google.com/maps?q=" . urlencode($gps);
    $content = "\u2728 **ลงพื้นที่ภาคสนามสำเร็จแล้ว** \u2728\n"
        . "\n**เลขที่สัญญา:** `{$job['contract_number']}`"
        . "\n**พื้นที่:** {$job['location_info']} ({$job['province']})"
        . "\n**ผลการลงพื้นที่:** {$result}"
        . "\n**สรุป:** {$note}"
        . "\n**พิกัด GPS:** {$gps}\n🌍 [$gpsLink]($gpsLink)";

    if (!empty($images)) {
        foreach ($images as $img) {
            $img_url = "http://" . $_SERVER['HTTP_HOST'] . "/field_project/uploads/job_photos/" . $img;
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

// ✅ กลับไปหน้า field.php
echo "<script>alert('✅ บันทึกผลงานสำเร็จ'); window.location.href='field.php';</script>";
?>
