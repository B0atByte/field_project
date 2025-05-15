<?php
session_start();
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$job_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user']['id'];

$stmt = $conn->prepare("SELECT j.*, l.note, l.gps, l.images, l.created_at AS log_time 
                        FROM jobs j 
                        LEFT JOIN job_logs l ON j.id = l.job_id 
                        WHERE j.id = ? AND j.assigned_to = ?");
$stmt->bind_param("ii", $job_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if (!$job) {
    die("ไม่พบงาน หรือคุณไม่มีสิทธิ์เข้าถึง");
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>📋 รายละเอียดงาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

  <div class="max-w-4xl mx-auto bg-white shadow-lg rounded-xl p-6 space-y-6">

    <h2 class="text-2xl font-bold text-gray-700">📋 รายละเอียดผลการลงพื้นที่</h2>

    <div class="space-y-2 text-sm text-gray-700">
      <p><strong>เลขที่สัญญา:</strong> <?= htmlspecialchars($job['contract_number']) ?></p>
      <p><strong>ลูกค้า:</strong> <?= htmlspecialchars($job['customer_name']) ?></p>
      <p><strong>ที่อยู่:</strong> <?= htmlspecialchars($job['customer_address']) ?></p>
      <p><strong>เบอร์โทร:</strong> <?= htmlspecialchars($job['customer_phone']) ?></p>
      <p><strong>ข้อมูลรถ:</strong> <?= htmlspecialchars($job['car_info']) ?></p>
      <p><strong>ยอดหนี้:</strong> <?= number_format($job['debt_amount'], 2) ?> บาท</p>
    </div>

    <hr class="my-4">

    <h3 class="text-xl font-semibold text-gray-800">📌 ผลการดำเนินงาน</h3>

    <div class="text-sm text-gray-700 space-y-2">
      <p><strong>หมายเหตุ:</strong><br><?= nl2br(htmlspecialchars($job['note'])) ?></p>
      <p><strong>เวลาที่บันทึก:</strong> <?= date('d/m/Y H:i', strtotime($job['log_time'])) ?></p>

      <?php if (!empty($job['gps'])): ?>
        <?php
          $gps = explode(",", $job['gps']);
          $lat = trim($gps[0]);
          $lng = trim($gps[1]);
          $gmap_url = "https://www.google.com/maps/search/?api=1&query=$lat,$lng";
        ?>
        <p><strong>พิกัด GPS:</strong> <?= htmlspecialchars($job['gps']) ?>
          <a href="<?= $gmap_url ?>" target="_blank" class="text-blue-600 hover:underline">🗺️ เปิดใน Google Maps</a>
        </p>
      <?php endif; ?>
    </div>

    <?php if (!empty($job['images'])): ?>
      <?php $images = json_decode($job['images'], true); ?>
      <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-2 mt-6">📷 รูปภาพประกอบ</h3>
        <div class="flex flex-wrap gap-4">
          <?php foreach ($images as $img): ?>
            <img src="../uploads/job_photos/<?= htmlspecialchars($img) ?>" width="200" class="rounded border">
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="pt-6">
      <a href="field.php" class="text-blue-600 hover:underline">🔙 กลับไปยังรายการของคุณ</a>
    </div>

  </div>

</body>
</html>
