<?php
session_start();
if (!in_array($_SESSION['user']['role'], ['admin', 'field'])) {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$q = $_GET['q'] ?? '';
$job_id = $_GET['job_id'] ?? null;
$export = $_GET['export'] ?? null;

$where = "WHERE l.gps IS NOT NULL AND l.gps != ''";
$params = [];

if ($job_id) {
    $start = $end = $q = '';
    $params = [];

    $stmt_job = $conn->prepare("SELECT location_info FROM jobs WHERE id = ?");
    $stmt_job->bind_param("i", $job_id);
    $stmt_job->execute();
    $res_job = $stmt_job->get_result();
    $job_data = $res_job->fetch_assoc();
    $stmt_job->close();

    if (!$job_data) {
        die("ไม่พบข้อมูลงาน");
    }

    $location_info = $job_data['location_info'];

    $stmt_related = $conn->prepare("SELECT id FROM jobs WHERE location_info = ?");
    $stmt_related->bind_param("s", $location_info);
    $stmt_related->execute();
    $res_related = $stmt_related->get_result();

    $job_ids = [];
    while ($row = $res_related->fetch_assoc()) {
        $job_ids[] = $row['id'];
    }
    $stmt_related->close();

    if (empty($job_ids)) {
        die("ไม่พบงานอื่นที่ชื่อเหมือนกัน");
    }

    $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
    $where = "WHERE l.gps IS NOT NULL AND l.gps != '' AND l.job_id IN ($placeholders)";
    $params = $job_ids;
} else {
    if ($start && $end) {
        $where .= " AND DATE(l.created_at) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    }

    if (!empty($q)) {
        $where .= " AND (
            j.contract_number LIKE ?
            OR j.location_info LIKE ?
        )";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
}

$sql = "SELECT l.*, j.contract_number, j.product, j.location_info
        FROM job_logs l
        JOIN jobs j ON j.id = l.job_id
        $where";

$stmt = $conn->prepare($sql);

// ✅ แก้ bind_param: รองรับทั้ง i/s
if ($params) {
    $types = '';
    foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : 's';
    }
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// ✅ Export Excel
if ($export === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['ผลิตภัณฑ์', 'สัญญา', 'พื้นที่', 'พิกัด', 'วันที่ลงงาน'], NULL, 'A1');
    $rowIndex = 2;
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue("A$rowIndex", $row['product']);
        $sheet->setCellValue("B$rowIndex", $row['contract_number']);
        $sheet->setCellValue("C$rowIndex", $row['location_info']);
        $sheet->setCellValue("D$rowIndex", $row['gps']);
        $sheet->setCellValue("E$rowIndex", $row['created_at']);
        $rowIndex++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="job_export.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ✅ เตรียม marker
$markers = [];
$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    $gps = explode(",", $row['gps']);
    if (count($gps) === 2) {
        $markers[] = [
            'id' => $row['job_id'],
            'lat' => (float)$gps[0],
            'lng' => (float)$gps[1],
            'product' => $row['product'],
            'contract' => $row['contract_number'],
            'location' => $row['location_info']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>🗺️ แผนที่ตำแหน่งงาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    #map { width: 100%; height: 500px; border-radius: 8px; border: 1px solid #ccc; }
    .fallback { display: none; color: red; font-weight: bold; padding: 10px; }
  </style>
</head>
<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-xl shadow p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h2 class="text-2xl font-bold text-gray-700">🗺️ แผนที่ตำแหน่งงาน</h2>
      <?php if ($_SESSION['user']['role'] === 'admin'): ?>
        <a href="../dashboard/admin.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">🔙 กลับแดชบอร์ด</a>
      <?php elseif ($_SESSION['user']['role'] === 'field'): ?>
        <a href="../dashboard/field.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">🔙 กลับหน้าภาคสนาม</a>
      <?php endif; ?>
    </div>

    <form method="get" class="flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-sm text-gray-600">📅 วันที่เริ่ม</label>
        <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="border px-3 py-2 rounded w-full" <?= $job_id ? 'disabled' : '' ?>>
      </div>
      <div>
        <label class="block text-sm text-gray-600">📅 สิ้นสุด</label>
        <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="border px-3 py-2 rounded w-full" <?= $job_id ? 'disabled' : '' ?>>
      </div>
      <div class="min-w-[250px]">
        <label class="block text-sm text-gray-600">🔎 ค้นหา</label>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="เลขสัญญา หรือ ชื่อลูกค้า" class="border px-3 py-2 rounded w-full" <?= $job_id ? 'disabled' : '' ?>>
      </div>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 mt-6">🔍 ค้นหา</button>
    </form>

    <div id="map"></div>
    <div id="map-fallback" class="fallback">❌ ไม่สามารถโหลดแผนที่ได้ กรุณาตรวจสอบ API Key</div>
  </div>

<script>
  let map;
  let mapLoaded = false;

  function initMap() {
    mapLoaded = true;
    const centerPos = { lat: 13.736717, lng: 100.523186 };
    map = new google.maps.Map(document.getElementById("map"), {
      center: centerPos,
      zoom: 6
    });

    const markers = <?= json_encode($markers) ?>;
    markers.forEach(m => {
      const marker = new google.maps.Marker({
        position: { lat: m.lat, lng: m.lng },
        map,
        title: m.product,
        animation: google.maps.Animation.DROP
      });

      const info = new google.maps.InfoWindow({
        content: `<strong>${m.product}</strong><br>
                  📄 เลขสัญญา: ${m.contract}<br>
                  📍 ลูกค้า: ${m.location}<br>
                  <a href="/field_project/dashboard/job_result.php?id=${m.id}" target="_blank" class="text-blue-600 underline">🔍 ดูผลงาน</a>`
      });

      marker.addListener('click', () => {
        info.open(map, marker);
        marker.setAnimation(google.maps.Animation.BOUNCE);
        setTimeout(() => marker.setAnimation(null), 2000);
      });
    });
  }

  setTimeout(() => {
    if (!mapLoaded) {
      document.getElementById("map").style.display = "none";
      document.getElementById("map-fallback").style.display = "block";
      Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: 'ไม่สามารถโหลดแผนที่ได้ กรุณาตรวจสอบ API Key',
      });
    }
  }, 10000);
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB3NfHFEyJb3yltga-dX0C23jsLEAQpORc&callback=initMap" async defer></script>
</body>
</html>
