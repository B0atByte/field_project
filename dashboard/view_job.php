<?php
session_start();
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$job_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user']['id'];

$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND assigned_to = ?");
$stmt->bind_param("ii", $job_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if (!$job) {
    die("ไม่พบงาน หรือคุณไม่มีสิทธิ์เข้าถึง");
}

if ($job['status'] === 'completed') {
    header("Location: job_result.php?id=" . $job_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>📝 ส่งผลงานภาคสนาม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

  <div class="max-w-4xl mx-auto bg-white shadow-lg rounded-xl p-6 space-y-6">
    <h2 class="text-2xl font-bold text-gray-800">📝 บันทึกผลงานภาคสนาม</h2>

    <div class="space-y-2 text-sm text-gray-700">
      <p><strong>เลขสัญญา:</strong> <?= htmlspecialchars($job['contract_number']) ?></p>
      <p><strong>ลูกค้า:</strong> <?= htmlspecialchars($job['customer_name']) ?></p>
      <p><strong>ที่อยู่:</strong> <?= htmlspecialchars($job['customer_address']) ?></p>
      <p><strong>เบอร์โทร:</strong> <?= htmlspecialchars($job['customer_phone']) ?></p>
      <p><strong>ข้อมูลรถ:</strong> <?= htmlspecialchars($job['car_info']) ?></p>
      <p><strong>ยอดหนี้:</strong> <?= number_format($job['debt_amount'], 2) ?> บาท</p>
    </div>

    <hr class="my-4">

    <form method="post" action="save_job.php" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="job_id" value="<?= $job['id'] ?>">

      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">📄 สรุปผลการดำเนินงาน</label>
        <textarea name="note" rows="4" required class="w-full border px-3 py-2 rounded"></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">📷 รูปถ่าย (อัปโหลดได้หลายภาพ)</label>
        <input type="file" name="images[]" multiple accept="image/*" class="w-full">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">📍 พิกัด GPS</label>
        <div class="flex items-center gap-3">
          <input type="text" name="gps" id="gps" readonly class="flex-1 border px-3 py-2 rounded">
          <button type="button" onclick="getLocation()" class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700">📡 ดึงพิกัด</button>
        </div>
      </div>

      <div id="map" class="w-full h-96 rounded border mt-4"></div>

      <div class="pt-4 flex justify-between items-center">
        <a href="field.php" class="text-blue-600 hover:underline">🔙 กลับไปยังรายการของคุณ</a>
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded font-semibold">
          💾 บันทึกผลงาน
        </button>
      </div>
    </form>
  </div>

  <!-- Google Maps API -->
  <script src="kEY" async defer></script>

  <script>
    function getLocation() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function(position) {
            const latlng = position.coords.latitude + ',' + position.coords.longitude;
            document.getElementById('gps').value = latlng;
            setMapMarker(position.coords.latitude, position.coords.longitude);
            alert("📍 พิกัดที่ได้: " + latlng);
          },
          function(error) {
            let message = '';
            switch (error.code) {
              case error.PERMISSION_DENIED: message = "คุณปฏิเสธการให้สิทธิ์ตำแหน่ง"; break;
              case error.POSITION_UNAVAILABLE: message = "ตำแหน่งไม่พร้อมใช้งาน"; break;
              case error.TIMEOUT: message = "หมดเวลารอ GPS"; break;
              default: message = "เกิดข้อผิดพลาดไม่ทราบสาเหตุ"; break;
            }
            alert("❌ " + message);
          }
        );
      } else {
        alert("เบราว์เซอร์ไม่รองรับการใช้ GPS");
      }
    }

    let map;
    let marker;

    function initMap() {
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
    }

    function setMapMarker(lat, lng) {
      const pos = new google.maps.LatLng(lat, lng);
      map.setCenter(pos);
      marker.setPosition(pos);
    }
  </script>

</body>
</html>
