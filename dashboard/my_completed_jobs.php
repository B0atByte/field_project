<?php
session_start();
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

$userId = $_SESSION['user']['id'];

$stmt = $conn->prepare("
    SELECT j.*, u.name AS imported_by_name
    FROM jobs j 
    LEFT JOIN users u ON j.imported_by = u.id 
    WHERE j.assigned_to = ? AND j.status = 'completed'
    ORDER BY j.due_date DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>📋 งานที่เสร็จแล้ว</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-7xl mx-auto bg-white rounded-xl shadow p-6">
  <div class="flex justify-between items-center mb-4">
    <h2 class="text-xl font-bold text-green-700">✅ งานที่คุณดำเนินการเสร็จแล้ว</h2>
    <a href="field.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">🔙 กลับหน้าหลัก</a>
  </div>

  <table class="min-w-full text-sm border border-gray-300 datatable">
    <thead class="bg-gradient-to-r from-green-100 to-green-200 text-gray-800">
      <tr>
        <th class="px-4 py-2 border">เลขสัญญา</th>
        <th class="px-4 py-2 border">ชื่อ</th>
        <th class="px-4 py-2 border">พื้นที่</th>
        <th class="px-4 py-2 border">โซน</th>
        <th class="px-4 py-2 border">ทะเบียน</th>
        <th class="px-4 py-2 border">วันครบกำหนด</th>
        <th class="px-4 py-2 border">ผู้ลงงาน</th>
        <th class="px-4 py-2 border">ดำเนินการ</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr class="hover:bg-green-50">
          <td class="border px-4 py-2"><?= htmlspecialchars($row['contract_number']) ?></td>
          <td class="border px-4 py-2"><?= htmlspecialchars($row['location_info']) ?></td>
          <td class="border px-4 py-2"><?= htmlspecialchars($row['location_area']) ?></td>
          <td class="border px-4 py-2"><?= htmlspecialchars($row['zone']) ?></td>
          <td class="border px-4 py-2"><?= htmlspecialchars($row['plate']) ?></td>
          <td class="border px-4 py-2"><?= htmlspecialchars($row['due_date']) ?></td>
          <td class="border px-4 py-2"><?= htmlspecialchars($row['imported_by_name'] ?? '-') ?></td>
          <td class="border px-4 py-2 text-center">
            <a href="job_result.php?id=<?= $row['id'] ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-1 rounded shadow">👁 ดู</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<script>
  $(document).ready(function () {
    $('.datatable').DataTable({
      pageLength: 10,
      language: {
        search: "🔍 ค้นหาทุกช่อง:",
        lengthMenu: "แสดง _MENU_ รายการต่อหน้า",
        zeroRecords: "ไม่พบข้อมูล",
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
