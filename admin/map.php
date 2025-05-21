<?php
session_start();
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$province = $_GET['province'] ?? '';
$export = $_GET['export'] ?? null;

$where = "WHERE l.gps IS NOT NULL AND l.gps != ''";
$params = [];

if ($start && $end) {
    $where .= " AND DATE(l.created_at) BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;
}

if ($province) {
    $where .= " AND j.customer_address LIKE ?";
    $params[] = "%$province%";
}

$sql = "SELECT j.id, j.customer_name, j.contract_number, j.customer_phone,
        j.customer_address, l.gps, l.created_at
        FROM jobs j
        JOIN job_logs l ON j.id = l.job_id
        $where";

$stmt = $conn->prepare($sql);
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($export === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['ชื่อลูกค้า', 'เบอร์โทร', 'สัญญา', 'ที่อยู่', 'พิกัด', 'วันที่ลงงาน'], NULL, 'A1');
    $rowIndex = 2;
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue("A$rowIndex", $row['customer_name']);
        $sheet->setCellValue("B$rowIndex", $row['customer_phone']);
        $sheet->setCellValue("C$rowIndex", $row['contract_number']);
        $sheet->setCellValue("D$rowIndex", $row['customer_address']);
        $sheet->setCellValue("E$rowIndex", $row['gps']);
        $sheet->setCellValue("F$rowIndex", $row['created_at']);
        $rowIndex++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="job_export.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$markers = [];
$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    $gps = explode(",", $row['gps']);
    if (count($gps) === 2) {
        $markers[] = [
            'id' => $row['id'],
            'lat' => (float)$gps[0],
            'lng' => (float)$gps[1],
            'name' => $row['customer_name'],
            'contract' => $row['contract_number'],
            'phone' => $row['customer_phone'],
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>🗺️ แผนที่กรองงาน</title>
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

    <!-- Header + ปุ่มกลับแดชบอร์ด -->
    <div class="flex justify-between items-center">
      <h2 class="text-2xl font-bold text-gray-700">🗺️ แผนที่ตำแหน่งงาน (กรอง)</h2>
      <a href="../dashboard/admin.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
        🔙 กลับแดชบอร์ด
      </a>
    </div>

    <!-- ฟอร์มกรอง -->
    <form method="get" class="flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-sm text-gray-600">📅 วันที่เริ่ม</label>
        <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="border px-3 py-2 rounded w-full">
      </div>
      <div>
        <label class="block text-sm text-gray-600">📅 สิ้นสุด</label>
        <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="border px-3 py-2 rounded w-full">
      </div>
      <div class="min-w-[200px]">
        <label class="block text-sm text-gray-600">📍 จังหวัด/ที่อยู่</label>
        <input type="text" name="province" value="<?= htmlspecialchars($province) ?>" class="border px-3 py-2 rounded w-full">
      </div>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition mt-6">🔍 ค้นหา</button>
      <?php if ($start && $end): ?>
        <a href="?start=<?= $start ?>&end=<?= $end ?>&province=<?= $province ?>&export=excel"
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition mt-6">📥 Export Excel</a>
      <?php endif; ?>
    </form>

    <!-- แผนที่ -->
    <div id="map"></div>
    <div id="map-fallback" class="fallback">❌ ไม่สามารถโหลดแผนที่ได้ กรุณาตรวจสอบการเชื่อมต่อหรือ API Key</div>
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
          title: m.name
        });

        const info = new google.maps.InfoWindow({
          content: `<strong>${m.name}</strong><br>
                    📄 สัญญา: ${m.contract}<br>
                    ☎️ โทร: ${m.phone}<br>
                    <a href="/field_project/dashboard/job_result.php?id=${m.id}" 
                       target="_blank" 
                       class="text-blue-600 underline">🔍 ดูผลงาน</a>`
        });

        marker.addListener('click', () => {
          info.open(map, marker);
        });
      });
    }

    // Fallback ถ้าโหลดไม่สำเร็จ
    setTimeout(() => {
      if (!mapLoaded) {
        document.getElementById("map").style.display = "none";
        document.getElementById("map-fallback").style.display = "block";
        Swal.fire({
          icon: 'error',
          title: 'เกิดข้อผิดพลาด',
          text: 'ไม่สามารถโหลดแผนที่ได้ กรุณาตรวจสอบ API Key หรือการเชื่อมต่ออินเทอร์เน็ต',
        });
      }
    }, 10000);
  </script>

  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAhahcvRts5Ln9pcIan4CQ-JQXLRsrL9as&callback=initMap" async defer></script>
</body>
</html>
