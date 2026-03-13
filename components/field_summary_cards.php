<?php
if (!isset($conn)) include '../config/db.php';
$userId = $_SESSION['user']['id'];
$showType = $showType ?? 'all';

// Count งานของฉัน
if ($showType === 'mine' || $showType === 'all') {
  $stmtMine = $conn->prepare("SELECT COUNT(*) FROM jobs WHERE assigned_to = ? AND status != 'completed'");
  $stmtMine->bind_param("i", $userId);
  $stmtMine->execute();
  $stmtMine->bind_result($countMine);
  $stmtMine->fetch();
  $stmtMine->close();
}

// Count งานยังไม่ถูกมอบหมาย
if ($showType === 'unassigned' || $showType === 'all') {
  $stmtAll = $conn->prepare("SELECT COUNT(*) FROM jobs WHERE assigned_to IS NULL");
  $stmtAll->execute();
  $stmtAll->bind_result($countAll);
  $stmtAll->fetch();
  $stmtAll->close();
}
?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

  <?php if ($showType === 'mine' || $showType === 'all'): ?>
    <div class="bg-green-500 text-white p-6 rounded-xl shadow flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold">📌 งานของฉันที่ต้องวิ่ง</h3>
        <p class="text-sm">ยังไม่เสร็จ</p>
      </div>
      <div class="text-2xl font-bold"><?= $countMine ?> งาน</div>
    </div>
  <?php endif; ?>

  <?php if ($showType === 'unassigned' || $showType === 'all'): ?>
    <div class="bg-purple-500 text-white p-6 rounded-xl shadow flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold">📚 งานทั้งหมด</h3>
        <p class="text-sm">ยังไม่ถูกมอบหมาย</p>
      </div>
      <div class="text-2xl font-bold"><?= $countAll ?> งาน</div>
    </div>
  <?php endif; ?>

</div>

<!-- ปุ่มส่วนกลาง -->
<div class="flex flex-col sm:flex-row sm:gap-4 gap-2 text-center mb-6">
  <a href="../admin/map.php" class="bg-yellow-400 hover:bg-yellow-500 text-white px-4 py-2 rounded-full shadow text-sm sm:text-base">🗺️ ดูแผนที่รวมงานทั้งหมด</a>
  <a href="my_completed_jobs.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-full shadow text-sm sm:text-base">✅ งานที่เสร็จแล้วของฉัน</a>
</div>
