<?php
session_start();
if (!in_array($_SESSION['user']['role'], ['field', 'admin'])) {
    die("Unauthorized");
}
include '../config/db.php';

$user_id = $_SESSION['user']['id'];
$job_id = $_POST['job_id'];
$note = $_POST['note'];
$gps = $_POST['gps'];
$images = [];

if (!is_dir('../uploads/job_photos')) {
    mkdir('../uploads/job_photos', 0777, true);
}

// จัดการรูปภาพ
if (!empty($_FILES['images']['name'][0])) {
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        $filename = time() . '_' . basename($_FILES['images']['name'][$key]);
        $target_path = '../uploads/job_photos/' . $filename;

        if (move_uploaded_file($tmp_name, $target_path)) {
            $images[] = $filename;
        }
    }
}

// บันทึกลงตาราง job_logs
$stmt = $conn->prepare("INSERT INTO job_logs (job_id, user_id, note, gps, images) VALUES (?, ?, ?, ?, ?)");
$img_json = json_encode($images, JSON_UNESCAPED_UNICODE);
$stmt->bind_param("iisss", $job_id, $user_id, $note, $gps, $img_json);
$stmt->execute();

// อัปเดต job ว่าเสร็จแล้ว
$conn->query("UPDATE jobs SET status = 'completed' WHERE id = $job_id");

// ดึงข้อมูล job สำหรับแจ้งเตือน
$job_result = $conn->query("SELECT contract_number, customer_name, customer_address FROM jobs WHERE id = $job_id");
$job = $job_result->fetch_assoc();

// ส่งแจ้งเตือน Discord
function sendToDiscord($job, $note, $gps, $images) {
    $webhookUrl = "";

    $gpsLink = "https://www.google.com/maps?q=" . urlencode($gps);

    $content = "\u2728 **ลงพื้นที่ภาคสนามสำเร็จแล้ว** \u2728\n"
        . "\n**เลขที่สัญญา:** `{$job['contract_number']}`"
        . "\n**ลูกค้า:** {$job['customer_name']}"
        . "\n**ที่อยู่:** {$job['customer_address']}"
        . "\n**ผลการลงพื้นที่:** {$note}"
        . "\n**พิกัด GPS:** {$gps}\n🌍 [$gpsLink]($gpsLink)";

    // เพิ่มลิงก์รูปถ้ามี
    if (!empty($images)) {
        foreach ($images as $img) {
            $img_url = "http://" . $_SERVER['HTTP_HOST'] . "/field_project/uploads/job_photos/" . $img;
            $content .= "\n🖼️ รูปภาพ: $img_url";
        }
    }

    $data = json_encode(["content" => $content]);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

sendToDiscord($job, $note, $gps, $images);

echo "<script>alert('บันทึกผลงานสำเร็จ'); window.location.href='field.php';</script>";
?>
