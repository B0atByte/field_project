<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

$current_user_id = $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role'];

// ลบงาน (admin เท่านั้น)
if (isset($_GET['delete']) && $current_user_role === 'admin') {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM jobs WHERE id = $id");
    header("Location: jobs.php?deleted=1");
    exit;
}

// แผนกที่มองเห็น
$stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$stmt->bind_result($current_department_id);
$stmt->fetch();
$stmt->close();

$visible_dept_ids = [$current_department_id];
if ($current_user_role !== 'admin') {
    $stmt = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_department_id = ?");
    $stmt->bind_param("i", $current_department_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $visible_dept_ids[] = $row['to_department_id'];
    }
}

// WHERE
$where = '1=1';
$params = [];
$types = '';
if ($current_user_role !== 'admin') {
    $placeholders = implode(',', array_fill(0, count($visible_dept_ids), '?'));
    $where .= " AND j.department_id IN ($placeholders)";
    foreach ($visible_dept_ids as $id) {
        $params[] = $id;
        $types .= 's';
    }
}

// ดึงข้อมูลงาน
$sql = "SELECT j.*, u1.name AS officer_name, u2.name AS imported_by_name, d.name AS department_name,
        (SELECT result FROM job_logs WHERE job_id = j.id ORDER BY id DESC LIMIT 1) AS latest_result
        FROM jobs j
        LEFT JOIN users u1 ON j.assigned_to = u1.id
        LEFT JOIN users u2 ON j.imported_by = u2.id
        LEFT JOIN departments d ON j.department_id = d.id
        WHERE $where
        ORDER BY j.due_date DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ดึงข้อมูล dropdown
$dept_res = $conn->query("SELECT id, name FROM departments");
$users_res = $conn->query("SELECT id, name FROM users WHERE role='field'");
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>📋 รายการงาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-7xl mx-auto bg-white shadow rounded-xl p-6">
  <div class="flex justify-between mb-6 items-center">
    <h2 class="text-2xl font-bold text-blue-700">📋 รายการงาน</h2>
    <a href="../dashboard/admin.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition">🔙 กลับแดชบอร์ด</a>
  </div>

  <!-- 🎛️ Filter -->
  <!-- <div class="bg-gray-50 p-4 rounded-lg shadow-inner mb-6">
    <h3 class="text-md font-semibold text-gray-700 mb-3">🎛️ ค้นหาขั้นสูง</h3>
    <div class="grid md:grid-cols-4 sm:grid-cols-2 gap-4 text-sm">
      <select id="filter-department" class="border px-3 py-2 rounded w-full">
        <option value="">-- ทุกแผนก --</option>
        <?php while ($dept = $dept_res->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
        <?php endwhile; ?>
      </select>

      <select id="filter-officer" class="border px-3 py-2 rounded w-full">
        <option value="">-- ผู้รับงาน --</option>
        <?php while ($user = $users_res->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($user['name']) ?>"><?= htmlspecialchars($user['name']) ?></option>
        <?php endwhile; ?>
      </select>

      <select id="filter-status" class="border px-3 py-2 rounded w-full">
        <option value="">-- สถานะ --</option>
        <option value="completed">✅ สำเร็จ</option>
        <option value="pending">⏳ รอดำเนินการ</option>
      </select>

      <input type="date" id="filter-date" class="border px-3 py-2 rounded w-full" placeholder="วันที่ลงงาน">
    </div>
  </div> -->

  <!-- Export -->
  <div class="mb-4 flex flex-wrap gap-2">
    <a href="export_jobs_pdf.php" class="bg-gradient-to-r from-red-500 to-red-700 text-white px-4 py-2 rounded shadow hover:scale-105 transition">🧾 Export PDF</a>
    <a href="export_jobs.php?all=1" class="bg-gradient-to-r from-gray-600 to-gray-800 text-white px-4 py-2 rounded shadow hover:scale-105 transition">📁 Export</a>
  </div>

  <!-- ตาราง -->
  <div class="overflow-x-auto">
    <table id="jobsTable" class="display w-full border text-sm rounded shadow overflow-hidden">
      <thead class="bg-blue-100 text-gray-700 text-sm">
        <tr>
          <th class="border px-3 py-2">สัญญา</th>
          <th class="border px-3 py-2">ลูกค้า</th>
          <th class="border px-3 py-2">จังหวัด</th>
          <th class="border px-3 py-2">กำหนด</th>
          <th class="border px-3 py-2">ผู้รับงาน</th>
          <th class="border px-3 py-2">สถานะ</th>
          <th class="border px-3 py-2">ผลลงงาน</th>
          <th class="border px-3 py-2">แผนก</th>
          <th class="border px-3 py-2">การจัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr class="hover:bg-blue-50">
          <td class="border px-3 py-2"><?= htmlspecialchars($row['contract_number']) ?></td>
          <td class="border px-3 py-2"><?= htmlspecialchars($row['location_info']) ?></td>
          <td class="border px-3 py-2"><?= htmlspecialchars($row['province']) ?></td>
          <td class="border px-3 py-2"><?= htmlspecialchars($row['due_date']) ?></td>
          <td class="border px-3 py-2"><?= htmlspecialchars($row['officer_name'] ?? '-') ?></td>
          <td class="border px-3 py-2"><?= $row['status'] === 'completed' ? '✅ สำเร็จ' : '⏳ รอดำเนินการ' ?></td>
          <td class="border px-3 py-2"><?= $row['latest_result'] ?? '-' ?></td>
          <td class="border px-3 py-2"><?= htmlspecialchars($row['department_name']) ?></td>
          <td class="border px-3 py-2 text-center space-x-2">
            <a href="../dashboard/job_result.php?id=<?= $row['id'] ?>" title="ดู" class="text-blue-600 hover:text-blue-800">🔍</a>
            <?php if ($current_user_role === 'admin'): ?>
              <a href="edit_job.php?id=<?= $row['id'] ?>" title="แก้ไข" class="text-yellow-600 hover:text-yellow-800">🖊️</a>
              <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('ลบหรือไม่?')" class="text-red-600 hover:text-red-800">❌</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'ลบงานสำเร็จ!',
  text: 'รายการงานถูกลบเรียบร้อยแล้ว',
  confirmButtonText: 'ตกลง',
  confirmButtonColor: '#3085d6'
});
</script>
<?php endif; ?>

<script>
$(document).ready(function () {
  const table = $('#jobsTable').DataTable({
    language: {
      lengthMenu: "แสดง _MENU_ รายการต่อหน้า",
      zeroRecords: "ไม่พบข้อมูลที่ค้นหา",
      info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
      infoEmpty: "ไม่มีข้อมูล",
      infoFiltered: "(กรองจากทั้งหมด _MAX_ รายการ)",
      search: "🔍 ค้นหาทุกช่อง:",
      paginate: {
        first: "หน้าแรก", last: "หน้าสุดท้าย", next: "ถัดไป", previous: "ก่อนหน้า"
      }
    },
    ordering: false,
    responsive: true
  });

  $('#filter-department').on('change', function () {
    table.column(7).search(this.value).draw();
  });

  $('#filter-officer').on('change', function () {
    table.column(4).search(this.value).draw();
  });

  $('#filter-status').on('change', function () {
    table.column(5).search(this.value).draw();
  });

  $('#filter-date').on('change', function () {
    table.column(3).search(this.value).draw();
  });
});
</script>
</body>
</html>
