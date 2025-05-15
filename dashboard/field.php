<?php
session_start();
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

$userId = $_SESSION['user']['id'];
$status = $_GET['status'] ?? '';

// ดึงงานที่มอบหมายให้ผู้ใช้ โดยกรองสถานะ (optional)
$sql = "SELECT * FROM jobs WHERE assigned_to = ?";
$params = [$userId];
$types = 'i';

if (in_array($status, ['completed', 'pending'])) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>📋 งานของฉัน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

  <div class="max-w-6xl mx-auto bg-white rounded-xl shadow-lg p-6 space-y-6">

    <div class="flex flex-col md:flex-row md:justify-between md:items-center">
      <h2 class="text-2xl font-bold text-gray-800">📋 รายการงานของคุณ</h2>
      <div class="mt-4 md:mt-0">
        <a href="?status=" class="text-sm px-3 py-1 rounded <?= $status === '' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800' ?>">ทั้งหมด</a>
        <a href="?status=pending" class="text-sm px-3 py-1 rounded <?= $status === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-200 text-gray-800' ?>">ยังไม่เสร็จ</a>
        <a href="?status=completed" class="text-sm px-3 py-1 rounded <?= $status === 'completed' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-800' ?>">เสร็จแล้ว</a>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-300 text-sm">
        <thead class="bg-gray-200 text-gray-700">
          <tr>
            <th class="px-3 py-2 border">เลขสัญญา</th>
            <th class="px-3 py-2 border">ชื่อลูกค้า</th>
            <th class="px-3 py-2 border">วันที่ลงงาน</th>
            <th class="px-3 py-2 border text-center">สถานะ</th>
            <th class="px-3 py-2 border text-center">ดำเนินการ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr class="bg-white hover:bg-gray-50">
              <td class="px-3 py-2 border text-blue-600 hover:underline">
                <a href="view_job.php?id=<?= $row['id'] ?>">
                  <?= htmlspecialchars($row['contract_number']) ?>
                </a>
              </td>
              <td class="px-3 py-2 border"><?= htmlspecialchars($row['customer_name']) ?></td>
              <td class="px-3 py-2 border"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
              <td class="px-3 py-2 border text-center">
                <?= $row['status'] === 'completed'
                    ? '<span class="text-green-600">✅ เสร็จแล้ว</span>'
                    : '<span class="text-yellow-600">🟡 ยังไม่เสร็จ</span>' ?>
              </td>
              <td class="px-3 py-2 border text-center">
                <a href="view_job.php?id=<?= $row['id'] ?>"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm inline-block">
                  📋 ดูงาน
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <div class="pt-4">
      <a href="../auth/logout.php" class="text-red-600 hover:underline">🚪 ออกจากระบบ</a>
    </div>

  </div>

</body>
</html>
