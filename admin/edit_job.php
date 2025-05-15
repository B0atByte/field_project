<?php
session_start();
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: jobs.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contract = $_POST['contract_number'];
    $name = $_POST['customer_name'];
    $phone = $_POST['customer_phone'];
    $address = $_POST['customer_address'];
    $car = $_POST['car_info'];
    $debt = $_POST['debt_amount'];
    $assigned_to = $_POST['assigned_to'];
    $created_at = $_POST['created_at'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE jobs SET contract_number=?, customer_name=?, customer_phone=?, customer_address=?, car_info=?, debt_amount=?, assigned_to=?, created_at=?, status=? WHERE id=?");
    $stmt->bind_param("sssssdissi", $contract, $name, $phone, $address, $car, $debt, $assigned_to, $created_at, $status, $id);
    $stmt->execute();

    header("Location: jobs.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if (!$job) {
    echo "ไม่พบบันทึกนี้";
    exit;
}

$officers = $conn->query("SELECT id, name FROM users WHERE role = 'field'");
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>✏️ แก้ไขงาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4 py-10">

  <div class="w-full max-w-2xl bg-white shadow-xl rounded-xl p-6">
    <h2 class="text-2xl font-bold text-gray-700 mb-6">✏️ แก้ไขข้อมูลงาน</h2>

    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-600">เลขสัญญา</label>
        <input type="text" name="contract_number" value="<?= htmlspecialchars($job['contract_number']) ?>"
               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600">ชื่อลูกค้า</label>
        <input type="text" name="customer_name" value="<?= htmlspecialchars($job['customer_name']) ?>"
               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600">เบอร์โทร</label>
        <input type="text" name="customer_phone" value="<?= htmlspecialchars($job['customer_phone']) ?>"
               class="w-full px-3 py-2 border rounded focus:outline-none">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600">ที่อยู่</label>
        <textarea name="customer_address" rows="3"
                  class="w-full px-3 py-2 border rounded focus:outline-none"><?= htmlspecialchars($job['customer_address']) ?></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600">ข้อมูลรถ</label>
        <input type="text" name="car_info" value="<?= htmlspecialchars($job['car_info']) ?>"
               class="w-full px-3 py-2 border rounded focus:outline-none">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600">ยอดหนี้</label>
        <input type="number" step="0.01" name="debt_amount" value="<?= $job['debt_amount'] ?>"
               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600">ผู้รับผิดชอบ (Field Officer)</label>
        <select name="assigned_to" required
                class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- เลือก --</option>
          <?php while ($officer = $officers->fetch_assoc()): ?>
            <option value="<?= $officer['id'] ?>" <?= $job['assigned_to'] == $officer['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($officer['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600">วันที่เริ่มงาน</label>
        <input type="datetime-local" name="created_at"
               value="<?= date('Y-m-d\TH:i', strtotime($job['created_at'])) ?>"
               class="w-full px-3 py-2 border rounded focus:outline-none">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600">สถานะ</label>
        <select name="status" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="new" <?= $job['status'] === 'new' ? 'selected' : '' ?>>🟡 ยังไม่เสร็จ</option>
          <option value="completed" <?= $job['status'] === 'completed' ? 'selected' : '' ?>>✅ เสร็จแล้ว</option>
        </select>
      </div>

      <div class="flex justify-between items-center pt-4">
        <a href="jobs.php" class="text-blue-600 hover:underline">🔙 กลับหน้ารายการงาน</a>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-semibold transition">
          💾 บันทึกการแก้ไข
        </button>
      </div>
    </form>
  </div>

</body>
</html>
