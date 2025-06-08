<?php
session_start();
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$user_id = $_SESSION['user']['id'];

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$where = "WHERE l.user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($start) && !empty($end)) {
    $where .= " AND DATE(l.created_at) BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;
    $types .= "ss";
}

$sql = "SELECT l.*, j.contract_number, j.location_info, j.province FROM job_logs l 
        LEFT JOIN jobs j ON j.id = l.job_id 
        $where ORDER BY l.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>📜 ประวัติงานภาคสนาม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4 sm:p-6">
  <div class="max-w-6xl mx-auto bg-white shadow-md rounded-xl p-6 space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <h2 class="text-2xl font-bold text-gray-800">📜 ประวัติงานภาคสนามของคุณ</h2>
      <a href="field.php" class="text-blue-600 hover:underline">🔙 กลับหน้าหลัก</a>
    </div>

    <form method="get" class="flex flex-col sm:flex-row items-start sm:items-center gap-3 text-sm">
      <div>
        <label>เริ่มวันที่</label>
        <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="border px-3 py-1 rounded">
      </div>
      <div>
        <label>ถึงวันที่</label>
        <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="border px-3 py-1 rounded">
      </div>
      <div class="pt-4 sm:pt-6">
        <button type="submit" class="bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700">🔍 กรอง</button>
      </div>
    </form>

    <?php if ($result->num_rows === 0): ?>
      <p class="text-gray-600">ยังไม่มีประวัติงาน</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full table-auto border border-gray-300 text-sm">
          <thead class="bg-gray-200 text-gray-700">
            <tr>
              <th class="px-4 py-2 border">วันที่</th>
              <th class="px-4 py-2 border">เลขสัญญา</th>
              <th class="px-4 py-2 border">พื้นที่</th>
              <th class="px-4 py-2 border">จังหวัด</th>
              <th class="px-4 py-2 border">ผลการลงพื้นที่</th>
              <th class="px-4 py-2 border">รูปถ่าย</th>
              <th class="px-4 py-2 border">ดูแผนที่</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 border"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                <td class="px-4 py-2 border"><?= htmlspecialchars($row['contract_number']) ?></td>
                <td class="px-4 py-2 border"><?= htmlspecialchars($row['location_info']) ?></td>
                <td class="px-4 py-2 border"><?= htmlspecialchars($row['province']) ?></td>
                <td class="px-4 py-2 border"><?= htmlspecialchars($row['result']) ?></td>
                <td class="px-4 py-2 border">
                  <?php 
                    $imgs = json_decode($row['images'], true);
                    if (!empty($imgs)):
                      foreach ($imgs as $img):
                        $imgUrl = "../uploads/job_photos/" . htmlspecialchars($img);
                  ?>
                    <a href="<?= $imgUrl ?>" target="_blank">
                      <img src="<?= $imgUrl ?>" alt="img" class="inline-block w-12 h-12 object-cover rounded border">
                    </a>
                  <?php endforeach; else: ?>
                    -
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2 border text-center">
                  <?php if (!empty($row['gps'])): ?>
                    <?php 
                      $gps = htmlspecialchars($row['gps']);
                      $gpsUrl = 'https://www.google.com/maps?q=' . urlencode($gps);
                    ?>
                    <a href="<?= $gpsUrl ?>" target="_blank" class="text-blue-600 hover:underline">📍</a>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
