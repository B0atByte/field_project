<?php
session_start();
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$job_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user']['id'];

// ดึงข้อมูล job + user
$stmt = $conn->prepare("SELECT j.*, u.name AS imported_name FROM jobs j LEFT JOIN users u ON j.imported_by = u.id WHERE j.id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    die("ไม่พบงาน");
}

$can_submit = $job['assigned_to'] == $user_id;
$date_now = date("Y-m-d\TH:i");

$has_log = 0;
$stmt_log = $conn->prepare("SELECT COUNT(*) FROM job_logs l INNER JOIN jobs j ON j.id = l.job_id WHERE j.contract_number = ? OR j.location_info = ?");
$stmt_log->bind_param("ss", $job['contract_number'], $job['location_info']);
$stmt_log->execute();
$stmt_log->store_result();
$stmt_log->bind_result($has_log);
$stmt_log->fetch();
$stmt_log->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>📝 รายละเอียดงานภาคสนาม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    img:hover { transform: scale(1.05); }
  </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 sm:p-6">
<div class="max-w-5xl mx-auto bg-white shadow-md rounded-xl p-6 space-y-6">

  <!-- ปุ่มกลับ -->
  <div class="mb-2">
    <a href="field.php" class="text-blue-600 font-semibold hover:underline flex items-center">
      <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0L2.586 11l3.707-3.707a1 1 0 011.414 1.414L5.414 10H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
      </svg> กลับ
    </a>
  </div>

  <h2 class="text-xl font-bold text-gray-800 mb-4">📋 รายละเอียดงานภาคสนาม</h2>

  <!-- 🗂️ ข้อมูลงาน -->
  <div class="bg-gray-50 p-4 rounded-lg shadow-inner space-y-2 text-sm text-gray-700 mb-4">
    <h3 class="font-semibold text-blue-700 text-md mb-2">🗂️ ข้อมูลงาน</h3>
    <div class="grid sm:grid-cols-2 gap-4">
      <p><strong>เลขที่สัญญา:</strong> <?= htmlspecialchars($job['contract_number']) ?></p>
      <p><strong>ชื่อสินค้า:</strong> <?= htmlspecialchars($job['product']) ?></p>
      <p><strong>วันครบกำหนด:</strong> <?= htmlspecialchars($job['due_date']) ?></p>
      <p><strong>จำนวนวันครบกำหนด:</strong> <?= htmlspecialchars($job['overdue_period']) ?></p>
      <p><strong>ผู้บันทึกข้อมูล:</strong> <?= htmlspecialchars($job['imported_name'] ?? '-') ?></p>
    </div>
  </div>

  <!-- 🚘 ข้อมูลรถ / ลูกค้า -->
  <div class="bg-gray-50 p-4 rounded-lg shadow-inner space-y-2 text-sm text-gray-700 mb-4">
    <h3 class="font-semibold text-blue-700 text-md mb-2">🚘 ข้อมูลรถ / ลูกค้า</h3>
    <div class="grid sm:grid-cols-2 gap-4">
      <p><strong>ชื่อ-สกุล(ลูกค้า):</strong> <?= htmlspecialchars($job['location_info']) ?></p>
      <p><strong>ข้อมูลพื้นที่:</strong> <?= htmlspecialchars($job['location_area']) ?></p>
      <p><strong>โซน:</strong> <?= htmlspecialchars($job['zone']) ?></p>
      <p><strong>ยี่ห้อ:</strong> <?= htmlspecialchars($job['model']) ?></p>
      <p><strong>รุ่น:</strong> <?= htmlspecialchars($job['model_detail']) ?></p>
      <p><strong>สี:</strong> <?= htmlspecialchars($job['color']) ?></p>
      <p><strong>ทะเบียน:</strong> <?= htmlspecialchars($job['plate']) ?></p>
      <p><strong>จังหวัด:</strong> <?= htmlspecialchars($job['province']) ?></p>
    </div>
  </div>

  <!-- 🔍 ตรวจสอบ log -->
  <?php if ($has_log > 0): ?>
    <div class="text-center">
      <a href="../admin/map.php?job_id=<?= $job_id ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded shadow">
        🕵️‍♂️ เคยมีคนวิ่งงานนี้แล้ว ดูแผนที่
      </a>
    </div>
  <?php endif; ?>

  <!-- 📥 รับงาน / บันทึกผล -->
  <?php if (!$can_submit): ?>
    <div class="text-center mt-6">
      <button onclick="confirmAcceptJob(<?= $job_id ?>)" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded">
        📥 รับงานนี้
      </button>
    </div>
  <?php else: ?>
    <form method="post" action="save_job.php" enctype="multipart/form-data" class="space-y-6 mt-6">
      <input type="hidden" name="job_id" value="<?= $job['id'] ?>">

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">📌 ผลการลงพื้นที่</label>
        <div class="flex gap-4">
          <label><input type="radio" name="result" value="พบ" required> ✅ พบ</label>
          <label><input type="radio" name="result" value="ไม่พบ" required> ❌ ไม่พบ</label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">🕒 วันที่/เวลา</label>
        <input type="datetime-local" name="log_time" class="w-full border px-3 py-2 rounded" value="<?= $date_now ?>" readonly>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">📝 หมายเหตุ</label>
        <textarea name="note" rows="4" required class="w-full border px-3 py-2 rounded"></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">📷 รูปถ่าย</label>
        <input type="file" name="images[]" multiple accept="image/*" class="w-full" onchange="validateFileCount(this)">
        <small class="text-gray-500">อัปโหลดได้ไม่เกิน 5 รูป</small>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">📍 พิกัด GPS</label>
        <div class="flex gap-3">
          <input type="text" name="gps" id="gps" class="flex-1 border px-3 py-2 rounded" readonly>
          <button type="button" onclick="getLocation()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">📡 ดึงพิกัด</button>
        </div>
      </div>

      <div id="map" class="w-full h-96 rounded border mt-4"></div>

      <div class="pt-4 text-center">
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded font-semibold">
          💾 บันทึกผลภาคสนาม
        </button>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
function confirmAcceptJob(jobId) {
  Swal.fire({
    title: 'คุณแน่ใจหรือไม่?',
    text: "คุณต้องการรับงานนี้ใช่หรือไม่",
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: '✅ รับงาน',
    cancelButtonText: 'ยกเลิก'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = 'accept_job.php?id=' + jobId;
    }
  });
}

function getLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      const latlng = position.coords.latitude + ',' + position.coords.longitude;
      document.getElementById('gps').value = latlng;
      setMapMarker(position.coords.latitude, position.coords.longitude);
      Swal.fire("📍 ได้พิกัดแล้ว", latlng, "success");
    }, function(error) {
      let msg = '';
      switch (error.code) {
        case error.PERMISSION_DENIED: msg = "คุณปฏิเสธการให้สิทธิ์ตำแหน่ง"; break;
        case error.POSITION_UNAVAILABLE: msg = "ตำแหน่งไม่พร้อมใช้งาน"; break;
        case error.TIMEOUT: msg = "หมดเวลารอ GPS"; break;
        default: msg = "เกิดข้อผิดพลาด"; break;
      }
      Swal.fire("❌ ไม่สามารถดึงพิกัด", msg, "error");
    });
  } else {
    Swal.fire("เบราว์เซอร์ไม่รองรับ", "ไม่สามารถใช้งาน GPS ได้", "warning");
  }
}

let map;
let marker;
window.initMap = function () {
  const defaultPos = { lat: 13.736717, lng: 100.523186 };
  map = new google.maps.Map(document.getElementById("map"), {
    zoom: 14,
    center: defaultPos
  });
  marker = new google.maps.Marker({
    position: defaultPos,
    map: map,
    draggable: true
  });
  marker.addListener("dragend", function () {
    const pos = marker.getPosition();
    const gps = pos.lat().toFixed(6) + "," + pos.lng().toFixed(6);
    document.getElementById("gps").value = gps;
  });
};
window.setMapMarker = function(lat, lng) {
  const pos = new google.maps.LatLng(lat, lng);
  map.setCenter(pos);
  marker.setPosition(pos);
};

function validateFileCount(input) {
  if (input.files.length > 5) {
    Swal.fire("❌ เกินจำนวน", "อัปโหลดได้ไม่เกิน 5 รูป", "error");
    input.value = "";
  }
}
</script>
Key
</body>
</html>