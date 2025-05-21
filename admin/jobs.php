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
$keyword = $_GET['keyword'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$status_filter = $_GET['status'] ?? '';
$month = $_GET['month'] ?? '';
$export = $_GET['export'] ?? null;

// ลบงาน
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM jobs WHERE id = $id");
    header("Location: jobs.php");
    exit;
}

// เปลี่ยนสถานะ
if (isset($_GET['mark_done'])) {
    $id = intval($_GET['mark_done']);
    $conn->query("UPDATE jobs SET status = 'completed' WHERE id = $id");
    header("Location: jobs.php");
    exit;
}

// เงื่อนไขกรอง
$where = '1=1';
$params = [];

if ($start && $end) {
    $where .= " AND DATE(j.created_at) BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;
}

if ($keyword !== '') {
    $where .= " AND (
        j.contract_number LIKE ?
        OR j.customer_name LIKE ?
        OR j.customer_phone LIKE ?
        OR j.customer_address LIKE ?
        OR j.debt_amount LIKE ?
        OR u.name LIKE ?
    )";
    for ($i = 0; $i < 6; $i++) {
        $params[] = "%$keyword%";
    }
}

if (!empty($assigned_to)) {
    $where .= " AND j.assigned_to = ?";
    $params[] = $assigned_to;
}

if (!empty($status_filter)) {
    $where .= " AND j.status = ?";
    $params[] = $status_filter;
}

if (!empty($month)) {
    $where .= " AND DATE_FORMAT(j.created_at, '%Y-%m') = ?";
    $params[] = $month;
}

// ดึงข้อมูล
$sql = "SELECT j.*, u.name AS officer_name
        FROM jobs j
        LEFT JOIN users u ON j.assigned_to = u.id
        WHERE $where
        ORDER BY j.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Export
if ($export === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['เลขสัญญา', 'ลูกค้า', 'เบอร์โทร', 'ที่อยู่', 'ยอดหนี้', 'ผู้รับผิดชอบ', 'สถานะ', 'วันที่เริ่ม'], NULL, 'A1');
    $i = 2;
    while ($r = $result->fetch_assoc()) {
        $sheet->fromArray([
            $r['contract_number'],
            $r['customer_name'],
            $r['customer_phone'],
            $r['customer_address'],
            $r['debt_amount'],
            $r['officer_name'] ?? '-',
            $r['status'],
            $r['created_at']
        ], NULL, "A{$i}");
        $i++;
    }

    $filename = "jobs_export_" . date("Ymd_His") . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment;filename=\"$filename\"");
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>📋 รายการงานทั้งหมด</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

<div class="max-w-7xl mx-auto bg-white shadow-lg rounded-xl p-6">

  <!-- Header -->
  <div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-700">📋 รายการงานทั้งหมด</h2>
    <a href="../dashboard/admin.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow">🔙 กลับแดชบอร์ด</a>
  </div>

  <!-- ฟอร์มค้นหาและ Export -->
  <form method="get" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end mb-6">
    <div>
      <label class="text-sm">วันที่เริ่ม</label>
      <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="border px-3 py-2 rounded w-full">
    </div>
    <div>
      <label class="text-sm">ถึง</label>
      <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="border px-3 py-2 rounded w-full">
    </div>
    <div>
      <label class="text-sm">ค้นหา</label>
      <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" class="border px-3 py-2 rounded w-full" placeholder="สัญญา/ลูกค้า/ที่อยู่">
    </div>
    <div>
      <label class="text-sm">เดือน</label>
      <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="border px-3 py-2 rounded w-full">
    </div>
    <div>
      <label class="text-sm">ผู้รับผิดชอบ</label>
      <select name="assigned_to" class="border px-3 py-2 rounded w-full">
        <option value="">-- ทุกคน --</option>
        <?php
          $user_q = $conn->query("SELECT id, name FROM users WHERE role = 'field'");
          while ($u = $user_q->fetch_assoc()):
        ?>
        <option value="<?= $u['id'] ?>" <?= $assigned_to == $u['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($u['name']) ?>
        </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label class="text-sm">สถานะ</label>
      <select name="status" class="border px-3 py-2 rounded w-full">
        <option value="">-- ทุกสถานะ --</option>
        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>🟡 ยังไม่เสร็จ</option>
        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>✅ เสร็จแล้ว</option>
      </select>
    </div>
    <div class="col-span-6 md:col-span-1">
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded w-full hover:bg-blue-700">🔍 ค้นหา</button>
    </div>
    <div class="col-span-6 md:col-span-1">
      <button type="submit" name="export" value="excel" class="bg-green-600 text-white px-4 py-2 rounded w-full hover:bg-green-700">📥 Export</button>
    </div>
  </form>

  <!-- ตารางแสดงข้อมูล -->
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm border border-gray-300">
      <thead class="bg-gray-100 text-gray-700 text-xs">
        <tr>
          <th class="px-2 py-1 border">เลขสัญญา</th>
          <th class="px-2 py-1 border">ลูกค้า</th>
          <th class="px-2 py-1 border">โทร</th>
          <th class="px-2 py-1 border">ที่อยู่</th>
          <th class="px-2 py-1 border">ยอดหนี้</th>
          <th class="px-2 py-1 border">รับผิดชอบ</th>
          <th class="px-2 py-1 border">สถานะ</th>
          <th class="px-2 py-1 border">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr class="bg-white hover:bg-gray-50">
            <td class="px-2 py-1 border"><?= htmlspecialchars($row['contract_number']) ?></td>
            <td class="px-2 py-1 border"><?= htmlspecialchars($row['customer_name']) ?></td>
            <td class="px-2 py-1 border"><?= htmlspecialchars($row['customer_phone']) ?></td>
            <td class="px-2 py-1 border"><?= htmlspecialchars($row['customer_address']) ?></td>
            <td class="px-2 py-1 border text-right"><?= number_format($row['debt_amount'], 2) ?></td>
            <td class="px-2 py-1 border"><?= htmlspecialchars($row['officer_name'] ?? '-') ?></td>
            <td class="px-2 py-1 border text-center">
              <?= $row['status'] === 'completed' ? '<span class="text-green-600">✅ เสร็จแล้ว</span>' : '<span class="text-yellow-600">🟡 ยังไม่เสร็จ</span>' ?>
            </td>
            <td class="px-2 py-1 border text-center space-x-1">
              <a href="edit_job.php?id=<?= $row['id'] ?>" class="text-yellow-600 hover:text-yellow-800" title="แก้ไข">📝</a>
              <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('ลบงานนี้ใช่ไหม?')" class="text-red-600 hover:text-red-800" title="ลบ">❌</a>
              <?php if ($row['status'] !== 'completed'): ?>
                <a href="?mark_done=<?= $row['id'] ?>" onclick="return confirm('เปลี่ยนสถานะเป็นเสร็จแล้ว?')" class="text-green-600 hover:text-green-800" title="เสร็จแล้ว">✅</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
