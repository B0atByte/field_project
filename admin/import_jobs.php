<?php
session_start();
if (!in_array($_SESSION['user']['role'], ['admin', 'manager'])) {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$imported_data = [];
$imported_by = $_SESSION['user']['id'] ?? null;

// ✔️ ดึงชื่อจริงและ department_id จาก users
$imported_by_name = '';
$department_id = null;

if ($imported_by) {
    $stmt_user = $conn->prepare("SELECT name, department_id FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $imported_by);
    $stmt_user->execute();
    $stmt_user->bind_result($imported_by_name, $department_id);
    $stmt_user->fetch();
    $stmt_user->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['excel'])) {
    $file = $_FILES['excel']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    for ($i = 1; $i < count($rows); $i++) {
        [
            $product, $contract_number, $customer_name, $location_area, $zone,
            $due_date, $overdue, $model, $model_detail, $color,
            $plate, $province, $os, $assigned_to
        ] = array_pad($rows[$i], 14, null);

        if (is_null($model_detail) || strtolower(trim($model_detail)) === 'null' || trim($model_detail) === '') {
            $model_detail = '-';
        }

        $stmt = $conn->prepare("INSERT INTO jobs (
            product, contract_number, location_info, location_area, zone,
            due_date, overdue_period, model, model_detail, color,
            plate, province, os, assigned_to, imported_by, department_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "ssssssssssssssii",
            $product, $contract_number, $customer_name, $location_area, $zone,
            $due_date, $overdue, $model, $model_detail, $color,
            $plate, $province, $os, $assigned_to, $imported_by, $department_id
        );
        $stmt->execute();

        $rows[$i][] = $imported_by_name;
        $imported_data[] = $rows[$i];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>นำเข้าข้อมูล - Field Project</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-7xl mx-auto bg-white shadow-lg rounded-xl p-6 space-y-6 relative">
    <div class="absolute top-6 right-6">
      <a href="<?= $_SESSION['user']['role'] === 'manager' ? '../dashboard/manager.php' : '../dashboard/admin.php' ?>" 
         class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
         🔙 กลับแดชบอร์ด
      </a>
    </div>
    <h2 class="text-2xl font-bold text-gray-700 mb-4">📅 นำเข้างานภาคสนาม (Excel)</h2>

    <form method="post" enctype="multipart/form-data" class="space-y-4">
      <input type="file" name="excel" accept=".xlsx" class="block w-full border border-gray-300 rounded-lg px-4 py-2" required>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
        ⬆️ อัปโหลดและนำเข้า
      </button>
    </form>

    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mt-4 rounded">
      <p class="font-bold">📋 รูปแบบ Excel ที่รองรับ:</p>
      <p>Product | สัญญา | ชื่อลูกค้า | ข้อมูลพื้นที่ | โซน | Due Date | Overdue | ยี่ห้อ | รุ่น | สี | ทะเบียน | จังหวัด | OS | ผู้รับงาน</p>
    </div>

    <?php if (!empty($imported_data)): ?>
    <div class="mt-6">
      <h3 class="text-xl font-semibold text-green-700 mb-2">✅ ข้อมูลที่นำเข้าแล้ว</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-300 rounded text-sm">
          <thead class="bg-gray-200 text-gray-700">
            <tr>
              <?php
              $headers = ['Product', 'สัญญา', 'ชื่อลูกค้า', 'พื้นที่', 'โซน', 'Due Date', 'Overdue', 'ยี่ห้อ', 'รุ่น', 'สี', 'ทะเบียน', 'จังหวัด', 'OS', 'ผู้รับงาน', 'ผู้ลงงาน'];
              foreach ($headers as $h) {
                  echo "<th class='px-3 py-2 border'>{$h}</th>";
              }
              ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($imported_data as $row): ?>
            <tr class="bg-white hover:bg-gray-50">
              <?php for ($j = 0; $j < 15; $j++): ?>
                <td class="px-3 py-2 border"><?= htmlspecialchars($row[$j] ?? '') ?></td>
              <?php endfor; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
