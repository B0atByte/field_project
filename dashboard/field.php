<?php 
session_start();
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

$userId = $_SESSION['user']['id'];
$status = $_GET['status'] ?? '';

// งานของฉัน (เฉพาะงานที่ยังไม่เสร็จ)
$sqlMine = "SELECT j.*, u.name as imported_by_name 
            FROM jobs j 
            LEFT JOIN users u ON j.imported_by = u.id 
            WHERE j.assigned_to = ? AND j.status != 'completed'
            ORDER BY j.due_date DESC";
$stmtMine = $conn->prepare($sqlMine);
$stmtMine->bind_param("i", $userId);
$stmtMine->execute();
$mineJobs = $stmtMine->get_result();

// งานทั้งหมด (ยังไม่ได้รับ)
$sqlAll = "SELECT j.*, u.name as imported_by_name 
           FROM jobs j 
           LEFT JOIN users u ON j.imported_by = u.id 
           WHERE j.assigned_to IS NULL 
           ORDER BY j.due_date DESC";
$stmtAll = $conn->prepare($sqlAll);
$stmtAll->execute();
$allJobs = $stmtAll->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>📋 งานภาคสนาม</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4 sm:p-6">
  <div class="max-w-7xl mx-auto space-y-6">
    
    <!-- สรุป -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="bg-green-500 text-white p-6 rounded-xl shadow flex items-center justify-between">
        <div><h3 class="text-lg font-semibold">📌 งานของฉัน</h3><p class="text-sm">ยังไม่เสร็จ</p></div>
        <div class="text-2xl font-bold"><?= $mineJobs->num_rows ?> งาน</div>
      </div>
      <div class="bg-purple-500 text-white p-6 rounded-xl shadow flex items-center justify-between">
        <div><h3 class="text-lg font-semibold">📚 งานทั้งหมด</h3><p class="text-sm">ยังไม่ถูกมอบหมาย</p></div>
        <div class="text-2xl font-bold"><?= $allJobs->num_rows ?> งาน</div>
      </div>
    </div>

    <!-- ปุ่มต่างๆ -->
    <div class="text-center space-y-2">
      <a href="../admin/map.php" class="inline-block mt-4 bg-yellow-400 hover:bg-yellow-500 text-white px-5 py-2 rounded-full shadow text-sm sm:text-base">
        🗺️ ดูแผนที่รวมงานทั้งหมด
      </a><br>
      <a href="my_completed_jobs.php" class="inline-block bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-full shadow text-sm sm:text-base">
        ✅ งานที่เสร็จแล้วของฉัน
      </a>
    </div>

    <!-- งานของฉัน -->
    <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
      <h2 class="text-xl font-bold text-gray-700 mb-4">🧑‍💼 งานของฉัน</h2>
      <table class="min-w-full text-xs sm:text-sm border border-gray-300 datatable">
        <thead class="bg-gradient-to-r from-indigo-100 to-blue-200 text-gray-800">
          <tr>
            <th class="px-4 py-2 border">เลขสัญญา</th>
            <th class="px-4 py-2 border">ชื่อ</th>
            <th class="px-4 py-2 border">พื้นที่</th>
            <th class="px-4 py-2 border">โซน</th>
            <th class="px-4 py-2 border">รุ่น</th>
            <th class="px-4 py-2 border">ทะเบียน</th>
            <th class="px-4 py-2 border">จังหวัด</th>
            <th class="px-4 py-2 border">วันครบกำหนด</th>
            <th class="px-4 py-2 border">ผู้นำเข้า</th>
            <th class="px-4 py-2 border">ดำเนินการ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $mineJobs->fetch_assoc()): ?>
          <tr class="bg-white hover:bg-gray-50">
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['contract_number']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['location_info']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['location_area']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['zone']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['model_detail']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['plate']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['province']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['due_date']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['imported_by_name']) ?></td>
            <td class="px-4 py-1 border text-center">
              <a href="view_job.php?id=<?= $row['id'] ?>" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-full shadow transition">
                📄 ส่งงาน
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- ยังไม่รับ -->
    <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
      <h2 class="text-xl font-bold text-gray-700 mb-4">📄 งานที่ยังไม่ได้รับ</h2>
      <table class="min-w-full text-xs sm:text-sm border border-gray-300 datatable">
        <thead class="bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800">
          <tr>
            <th class="px-4 py-2 border">เลขสัญญา</th>
            <th class="px-4 py-2 border">ชื่อ</th>
            <th class="px-4 py-2 border">พื้นที่</th>
            <th class="px-4 py-2 border">โซน</th>
            <th class="px-4 py-2 border">รุ่น</th>
            <th class="px-4 py-2 border">ทะเบียน</th>
            <th class="px-4 py-2 border">วันครบกำหนด</th>
            <th class="px-4 py-2 border">ผู้นำเข้า</th>
            <th class="px-4 py-2 border">ดำเนินการ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $allJobs->fetch_assoc()): ?>
          <tr class="bg-white hover:bg-gray-50">
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['contract_number']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['location_info']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['location_area']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['zone']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['model_detail']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['plate']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['due_date']) ?></td>
            <td class="px-4 py-1 border"><?= htmlspecialchars($row['imported_by_name']) ?></td>
            <td class="px-4 py-1 border text-center">
              <a href="view_job.php?id=<?= $row['id'] ?>" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-1 rounded-full shadow transition">🔍 ดูรายละเอียด</a>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <div class="text-right pt-4">
      <a href="../auth/logout.php" class="inline-block bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-4 py-2 rounded-full transition">🚪 ออกจากระบบ</a>
    </div>
  </div>

  <script>
    $(document).ready(function () {
      $('.datatable').DataTable({
        pageLength: 10,
        language: {
          search: "🔍 ค้นหาทุกช่อง:",
          lengthMenu: "แสดง _MENU_ รายการต่อหน้า",
          zeroRecords: "ไม่พบข้อมูลที่ค้นหา",
          info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
          paginate: {
            first: "หน้าแรก",
            last: "หน้าสุดท้าย",
            next: "ถัดไป",
            previous: "ก่อนหน้า"
          }
        }
      });
    });
  </script>
</body>
</html>
