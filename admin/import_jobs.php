<?php
session_start();
if ($_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$imported_data = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['excel'])) {
    $file = $_FILES['excel']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    for ($i = 1; $i < count($rows); $i++) {
        [
            $contract, $name, $address, $phone, $car, $debt, $user_id,
            $product, $location_info, $zone, $due_date, $overdue_period,
            $brand, $model, $color, $plate, $province, $os
        ] = array_pad($rows[$i], 18, null);

        $stmt = $conn->prepare("INSERT INTO jobs 
            (contract_number, customer_name, customer_address, customer_phone, car_info, debt_amount, assigned_to,
             product, location_info, zone, due_date, overdue_period,
             brand, model, color, plate, province, os) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssdisssssssssss", 
            $contract, $name, $address, $phone, $car, $debt, $user_id,
            $product, $location_info, $zone, $due_date, $overdue_period,
            $brand, $model, $color, $plate, $province, $os);
        $stmt->execute();

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

    <!-- 🔙 ปุ่มกลับแดชบอร์ด -->
    <div class="absolute top-6 right-6">
      <a href="../dashboard/admin.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
        🔙 กลับแดชบอร์ด
      </a>
    </div>

    <h2 class="text-2xl font-bold text-gray-700 mb-4">📥 นำเข้างานภาคสนาม (Excel)</h2>

    <form method="post" enctype="multipart/form-data" class="space-y-4">
      <input type="file" name="excel" accept=".xlsx" class="block w-full border border-gray-300 rounded-lg px-4 py-2" required>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
        อัปโหลดและนำเข้า
      </button>
    </form>

    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mt-4" role="alert">
      <p class="font-bold">📝 รูปแบบ Excel ที่รองรับ:</p>
      <p>เลขสัญญา | ชื่อลูกค้า | ที่อยู่ | เบอร์โทร | ข้อมูลรถ | ยอดหนี้ | รหัส user | Product | ข้อมูลพื้นที่ | โซน | Due Date | Overdue | ยี่ห้อ | รุ่น | สี | ทะเบียน | จังหวัด | OS</p>
    </div>

    <?php if (!empty($imported_data)): ?>
    <div class="mt-6">
      <h3 class="text-xl font-semibold text-green-700 mb-2">✅ ข้อมูลที่นำเข้าแล้ว</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-300 rounded text-sm">
          <thead class="bg-gray-200 text-gray-700">
            <tr>
              <th class="px-3 py-2 border">สัญญา</th>
              <th class="px-3 py-2 border">ชื่อลูกค้า</th>
              <th class="px-3 py-2 border">ที่อยู่</th>
              <th class="px-3 py-2 border">เบอร์โทร</th>
              <th class="px-3 py-2 border">ข้อมูลรถ</th>
              <th class="px-3 py-2 border">ยอดหนี้</th>
              <th class="px-3 py-2 border">User ID</th>
              <th class="px-3 py-2 border">Product</th>
              <th class="px-3 py-2 border">ข้อมูลพื้นที่</th>
              <th class="px-3 py-2 border">โซน</th>
              <th class="px-3 py-2 border">Due Date</th>
              <th class="px-3 py-2 border">Overdue</th>
              <th class="px-3 py-2 border">ยี่ห้อ</th>
              <th class="px-3 py-2 border">รุ่น</th>
              <th class="px-3 py-2 border">สี</th>
              <th class="px-3 py-2 border">ทะเบียน</th>
              <th class="px-3 py-2 border">จังหวัด</th>
              <th class="px-3 py-2 border">OS</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($imported_data as $row): ?>
            <tr class="bg-white hover:bg-gray-50">
              <?php for ($j = 0; $j < 18; $j++): ?>
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
