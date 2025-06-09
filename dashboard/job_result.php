<?php 
session_start();
if (!in_array($_SESSION['user']['role'], ['field', 'admin'])) {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$job_id = $_GET['id'] ?? null;

$stmt = $conn->prepare("SELECT j.*, 
    l.note, l.result, l.gps, l.images, l.created_at AS log_time,
    u1.name AS assigned_name, u2.name AS imported_name
    FROM jobs j 
    LEFT JOIN job_logs l ON j.id = l.job_id 
    LEFT JOIN users u1 ON j.assigned_to = u1.id
    LEFT JOIN users u2 ON j.imported_by = u2.id
    WHERE j.id = ?");
$stmt->bind_param("i", $job_id);
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
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    img:hover { transform: scale(1.05); transition: 0.3s ease; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen p-4 sm:p-6">
<div class="max-w-5xl mx-auto bg-white shadow rounded-xl p-6 space-y-6">

  <!-- Header -->
  <div class="flex justify-between items-center flex-wrap gap-4">
    <h2 class="text-2xl font-bold text-gray-700">📋 รายละเอียดผลการลงพื้นที่</h2>
    <div class="flex gap-2">
      <?php if ($job['status'] === 'completed'): ?>
        <a href="../admin/export_job_detail_pdf.php?id=<?= $job_id ?>" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded shadow">
          🧾 Export PDF
        </a>
      <?php else: ?>
        <button onclick="alertExportDenied()" class="bg-gray-400 text-white px-4 py-2 rounded shadow cursor-not-allowed">
          🧾 Export PDF
        </button>
      <?php endif; ?>
      <a href="<?= ($_SESSION['user']['role'] === 'admin') ? '../admin/jobs.php' : '../dashboard/field.php' ?>"
         class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow">
        🔙 กลับหน้าภาคสนาม
      </a>
    </div>
  </div>

  <!-- Contract Info -->
  <div class="bg-gray-50 p-4 rounded-lg shadow-inner">
    <h3 class="font-semibold text-lg text-gray-800 mb-2">🧾 รายละเอียดสัญญา</h3>
    <div class="grid sm:grid-cols-2 gap-4 text-sm text-gray-700">
      <p><strong>เลขที่สัญญา:</strong> <?= htmlspecialchars($job['contract_number']) ?></p>
      <p><strong>ชื่อสินค้า:</strong> <?= htmlspecialchars($job['product']) ?></p>
      <p><strong>วันครบกำหนด:</strong> <?= htmlspecialchars($job['due_date']) ?></p>
      <p><strong>จำนวนวันครบกำหนด:</strong> <?= htmlspecialchars($job['overdue_period']) ?></p>
      <p><strong>สถานะ:</strong> <?= htmlspecialchars($job['status']) ?></p>
      <p><strong>ยอดคงเหลือ OS:</strong> <?= number_format($job['os'], 2) ?> บาท</p>
    </div>
  </div>

  <!-- Customer Info -->
  <div class="bg-gray-50 p-4 rounded-lg shadow-inner">
    <h3 class="font-semibold text-lg text-gray-800 mb-2">🚘 ลูกค้า / พื้นที่ / รถ</h3>
    <div class="grid sm:grid-cols-2 gap-4 text-sm text-gray-700">
      <p><strong>ชื่อ-สกุล(ลูกค้า):</strong> <?= htmlspecialchars($job['location_info']) ?></p>
      <p><strong>พื้นที่:</strong> <?= htmlspecialchars($job['location_area']) ?></p>
      <p><strong>โซน:</strong> <?= htmlspecialchars($job['zone']) ?></p>
      <p><strong>ยี่ห้อ:</strong> <?= htmlspecialchars($job['model']) ?></p>
      <p><strong>รุ่น:</strong> <?= htmlspecialchars($job['model_detail']) ?></p>
      <p><strong>สี:</strong> <?= htmlspecialchars($job['color']) ?></p>
      <p><strong>ทะเบียน:</strong> <?= htmlspecialchars($job['plate']) ?></p>
      <p><strong>จังหวัด:</strong> <?= htmlspecialchars($job['province']) ?></p>
      <p><strong>ผู้นำเข้า:</strong> <?= htmlspecialchars($job['imported_name'] ?? '-') ?></p>
      <p><strong>ผู้รับผิดชอบ:</strong> <?= htmlspecialchars($job['assigned_name'] ?? '-') ?></p>
    </div>
  </div>

  <!-- Log Result -->
  <div class="bg-gray-50 p-4 rounded-lg shadow-inner">
    <h3 class="font-semibold text-lg text-red-600 mb-2">📌 ผลการดำเนินงาน</h3>
    <div class="text-sm text-gray-700 space-y-2">
      <p><strong>ผล:</strong> <?= htmlspecialchars($job['result'] ?? '-') ?></p>
      <p><strong>หมายเหตุ:</strong><br><?= nl2br(htmlspecialchars($job['note'] ?? '-')) ?></p>
      <p><strong>บันทึกเมื่อ:</strong> <?= $job['log_time'] ? date('d/m/Y H:i', strtotime($job['log_time'])) : '-' ?></p>

      <?php if (!empty($job['gps'])): ?>
        <?php
          $gps = explode(",", $job['gps']);
          $lat = trim($gps[0]);
          $lng = trim($gps[1]);
        ?>
        <p><strong>GPS:</strong> <?= htmlspecialchars($job['gps']) ?>
          <a href="https://www.google.com/maps/search/?api=1&query=<?= $lat ?>,<?= $lng ?>" target="_blank" class="text-blue-600 hover:underline">
            🗺️ เปิดใน Google Maps
          </a>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Images -->
  <?php if (!empty($job['images'])): ?>
    <?php $images = json_decode($job['images'], true); ?>
    <div>
      <h3 class="font-semibold text-lg text-gray-800 mb-2">📷 รูปภาพประกอบ</h3>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <?php foreach ($images as $img): ?>
          <a href="../uploads/job_photos/<?= htmlspecialchars($img) ?>" target="_blank">
            <img src="../uploads/job_photos/<?= htmlspecialchars($img) ?>" class="w-full h-auto rounded border hover:shadow-xl">
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
function alertExportDenied() {
  Swal.fire({
    icon: 'warning',
    title: 'ยังไม่สามารถ Export ได้',
    text: 'อนุญาตเฉพาะงานที่ "สำเร็จ" เท่านั้น',
    confirmButtonText: 'ตกลง'
  });
}
</script>
</body>
</html>
