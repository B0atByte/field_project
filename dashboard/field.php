<?php
session_start();
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

$userId = $_SESSION['user']['id'];
$status = $_GET['status'] ?? '';

// ถ้ากดรับงาน
if (isset($_GET['accept_job'])) {
    $jobId = intval($_GET['accept_job']);
    $stmt = $conn->prepare("UPDATE jobs SET assigned_to = ?, status = 'pending' WHERE id = ? AND assigned_to IS NULL");
    $stmt->bind_param("ii", $userId, $jobId);
    $stmt->execute();
    header("Location: field.php");
    exit;
}

// งานของฉัน
$sqlMine = "SELECT * FROM jobs WHERE assigned_to = ?";
$paramsMine = [$userId];
$typesMine = 'i';

if (in_array($status, ['completed', 'pending'])) {
    $sqlMine .= " AND status = ?";
    $paramsMine[] = $status;
    $typesMine .= 's';
}
$sqlMine .= " ORDER BY created_at DESC";
$stmtMine = $conn->prepare($sqlMine);
$stmtMine->bind_param($typesMine, ...$paramsMine);
$stmtMine->execute();
$mineJobs = $stmtMine->get_result();

// งานทั้งหมดที่ยังไม่ถูก assigned
$sqlAll = "SELECT * FROM jobs WHERE assigned_to IS NULL ORDER BY created_at DESC";
$stmtAll = $conn->prepare($sqlAll);
$stmtAll->execute();
$allJobs = $stmtAll->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>📋 งานภาคสนาม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-7xl mx-auto space-y-6">

    <!-- กล่องสรุป -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-green-500 text-white p-6 rounded-xl shadow flex items-center justify-between">
        <div><h3 class="text-lg font-semibold">📌 งานของฉัน</h3><p class="text-sm">งานที่คุณรับแล้ว</p></div>
        <div class="text-2xl font-bold"><?= $mineJobs->num_rows ?> งาน</div>
      </div>
      <div class="bg-purple-500 text-white p-6 rounded-xl shadow flex items-center justify-between">
        <div><h3 class="text-lg font-semibold">📚 งานที่รอรับ</h3><p class="text-sm">สามารถเลือกรับได้</p></div>
        <div class="text-2xl font-bold"><?= $allJobs->num_rows ?> งาน</div>
      </div>
    </div>

    <!-- ฟิลเตอร์ -->
    <div class="flex justify-end gap-2">
      <a href="?status=" class="text-sm px-3 py-1 rounded <?= $status === '' ? 'bg-blue-600 text-white' : 'bg-gray-200' ?>">ทั้งหมด</a>
      <a href="?status=pending" class="text-sm px-3 py-1 rounded <?= $status === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-200' ?>">ยังไม่เสร็จ</a>
      <a href="?status=completed" class="text-sm px-3 py-1 rounded <?= $status === 'completed' ? 'bg-green-600 text-white' : 'bg-gray-200' ?>">เสร็จแล้ว</a>
    </div>

    <!-- ตาราง: งานของฉัน -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="text-xl font-bold text-gray-700 mb-4">🧑‍💼 งานของฉัน</h2>
      <table class="min-w-full text-sm border border-gray-300">
        <thead class="bg-gray-200">
          <tr>
            <th class="px-3 py-2 border">เลขสัญญา</th>
            <th class="px-3 py-2 border">ลูกค้า</th>
            <th class="px-3 py-2 border">วันที่</th>
            <th class="px-3 py-2 border text-center">สถานะ</th>
            <th class="px-3 py-2 border text-center">ดำเนินการ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $mineJobs->fetch_assoc()): ?>
            <tr class="bg-white hover:bg-gray-50">
              <td class="px-3 py-2 border"><?= htmlspecialchars($row['contract_number']) ?></td>
              <td class="px-3 py-2 border"><?= htmlspecialchars($row['customer_name']) ?></td>
              <td class="px-3 py-2 border"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
              <td class="px-3 py-2 border text-center"><?= $row['status'] === 'completed' ? '✅' : '🟡' ?></td>
              <td class="px-3 py-2 border text-center">
                <a href="view_job.php?id=<?= $row['id'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-3 py-1 rounded">📤 ส่งงาน</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- ตาราง: งานที่รอรับ -->
    <div class="bg-white rounded-xl shadow p-4">
      <h2 class="text-xl font-bold text-gray-700 mb-4">📝 งานทั้งหมด (ยังไม่รับ)</h2>
      <table class="min-w-full text-sm border border-gray-300">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-3 py-2 border">เลขสัญญา</th>
            <th class="px-3 py-2 border">ลูกค้า</th>
            <th class="px-3 py-2 border">วันที่</th>
            <th class="px-3 py-2 border text-center">รับ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $allJobs->fetch_assoc()): ?>
            <tr class="bg-white hover:bg-gray-50">
              <td class="px-3 py-2 border"><?= htmlspecialchars($row['contract_number']) ?></td>
              <td class="px-3 py-2 border"><?= htmlspecialchars($row['customer_name']) ?></td>
              <td class="px-3 py-2 border"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
              <td class="px-3 py-2 border text-center">
                <a href="?accept_job=<?= $row['id'] ?>" onclick="return confirm('คุณต้องการรับงานนี้ใช่หรือไม่?')" class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1 rounded">
                  📥 รับงาน
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <div class="text-right pt-4">
      <a href="../auth/logout.php" class="text-red-600 hover:underline">🚪 ออกจากระบบ</a>
    </div>

  </div>
</body>
</html>
