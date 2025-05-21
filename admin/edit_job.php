<?php
session_start();
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ไม่พบงาน"; exit;
}

$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if (!$job) {
    echo "ไม่พบข้อมูลงาน"; exit;
}

$users = $conn->query("SELECT id, name FROM users WHERE role = 'field'");
$updated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contract_number = $_POST['contract_number'];
    $customer_name = $_POST['customer_name'];
    $customer_address = $_POST['customer_address'];
    $customer_phone = $_POST['customer_phone'];
    $car_info = $_POST['car_info'];
    $debt_amount = $_POST['debt_amount'];
    $product = $_POST['product'];
    $location_info = $_POST['location_info'];
    $zone = $_POST['zone'];
    $due_date = $_POST['due_date'];
    $overdue_period = $_POST['overdue_period'];
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $color = $_POST['color'];
    $plate = $_POST['plate'];
    $province = $_POST['province'];
    $os = $_POST['os'];
    $assigned_to = $_POST['assigned_to'];
    $status = $_POST['status'];
    $job_date = $_POST['job_date'];

    // อัปเดตงาน
    $stmt = $conn->prepare("UPDATE jobs SET 
        contract_number=?, customer_name=?, customer_address=?, customer_phone=?, 
        car_info=?, debt_amount=?, product=?, location_info=?, zone=?, due_date=?, 
        overdue_period=?, brand=?, model=?, color=?, plate=?, province=?, os=?, 
        assigned_to=?, status=?, created_at=?
        WHERE id=?");

    $stmt->bind_param("sssssdssssssssssssssi", 
        $contract_number, $customer_name, $customer_address, $customer_phone,
        $car_info, $debt_amount, $product, $location_info, $zone, $due_date,
        $overdue_period, $brand, $model, $color, $plate, $province, $os,
        $assigned_to, $status, $job_date, $id);

    $stmt->execute();

    // ✅ เพิ่มบันทึก Log การแก้ไข
    $admin_id = $_SESSION['user']['id'];
    $summary = "แก้ไขงานโดย Admin ID: $admin_id, งาน ID: $id";
    $log_stmt = $conn->prepare("INSERT INTO job_edit_logs (job_id, edited_by, change_summary) VALUES (?, ?, ?)");
    $log_stmt->bind_param("iis", $id, $admin_id, $summary);
    $log_stmt->execute();

    $updated = true;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>✏️ แก้ไขข้อมูลงาน</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 min-h-screen flex justify-center items-start p-6">

  <div class="bg-white max-w-5xl w-full shadow-lg rounded-xl p-6 space-y-6 relative">

    <!-- 🔙 ปุ่มกลับมุมขวา -->
    <div class="absolute top-6 right-6">
      <a href="jobs.php" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">🔙 กลับหน้ารายการงาน</a>
    </div>

    <h2 class="text-2xl font-bold text-gray-800 mb-4">✏️ แก้ไขข้อมูลงาน</h2>

    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="text" name="contract_number" value="<?= htmlspecialchars($job['contract_number']) ?>" class="border px-3 py-2 rounded w-full" placeholder="เลขสัญญา" required>
      <input type="text" name="customer_name" value="<?= htmlspecialchars($job['customer_name']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ชื่อลูกค้า" required>
      <input type="text" name="customer_address" value="<?= htmlspecialchars($job['customer_address']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ที่อยู่">
      <input type="text" name="customer_phone" value="<?= htmlspecialchars($job['customer_phone']) ?>" class="border px-3 py-2 rounded w-full" placeholder="เบอร์โทร">
      <input type="text" name="car_info" value="<?= htmlspecialchars($job['car_info']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ข้อมูลรถ">
      <input type="number" step="any" name="debt_amount" value="<?= htmlspecialchars($job['debt_amount']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ยอดหนี้">
      <input type="text" name="product" value="<?= htmlspecialchars($job['product']) ?>" class="border px-3 py-2 rounded w-full" placeholder="Product">
      <input type="text" name="location_info" value="<?= htmlspecialchars($job['location_info']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ข้อมูลพื้นที่">
      <input type="text" name="zone" value="<?= htmlspecialchars($job['zone']) ?>" class="border px-3 py-2 rounded w-full" placeholder="โซน">
      <input type="date" name="due_date" value="<?= htmlspecialchars($job['due_date']) ?>" class="border px-3 py-2 rounded w-full">
      <input type="text" name="overdue_period" value="<?= htmlspecialchars($job['overdue_period']) ?>" class="border px-3 py-2 rounded w-full" placeholder="Overdue">
      <input type="text" name="brand" value="<?= htmlspecialchars($job['brand']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ยี่ห้อ">
      <input type="text" name="model" value="<?= htmlspecialchars($job['model']) ?>" class="border px-3 py-2 rounded w-full" placeholder="รุ่น">
      <input type="text" name="color" value="<?= htmlspecialchars($job['color']) ?>" class="border px-3 py-2 rounded w-full" placeholder="สี">
      <input type="text" name="plate" value="<?= htmlspecialchars($job['plate']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ทะเบียน">
      <input type="text" name="province" value="<?= htmlspecialchars($job['province']) ?>" class="border px-3 py-2 rounded w-full" placeholder="จังหวัด">
      <input type="text" name="os" value="<?= htmlspecialchars($job['os']) ?>" class="border px-3 py-2 rounded w-full" placeholder="OS">

      <!-- 👨‍💼 ผู้รับผิดชอบ -->
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">ผู้รับผิดชอบ</label>
        <select name="assigned_to" class="border px-3 py-2 rounded w-full">
          <?php while ($u = $users->fetch_assoc()): ?>
            <option value="<?= $u['id'] ?>" <?= $u['id'] == $job['assigned_to'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- ✅ สถานะ -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
        <select name="status" class="border px-3 py-2 rounded w-full">
          <option value="pending" <?= ($job['status'] ?? '') === 'pending' ? 'selected' : '' ?>>🟡 ยังไม่เสร็จ</option>
          <option value="completed" <?= ($job['status'] ?? '') === 'completed' ? 'selected' : '' ?>>✅ เสร็จแล้ว</option>
        </select>
      </div>

      <!-- 📅 วันที่เริ่ม -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">วันที่เริ่มงาน</label>
        <input type="datetime-local" name="job_date" value="<?= date('Y-m-d\TH:i', strtotime($job['created_at'])) ?>" class="border px-3 py-2 rounded w-full">
      </div>

      <div class="col-span-2 text-right mt-4">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">💾 บันทึกการแก้ไข</button>
      </div>
    </form>
  </div>

  <?php if ($updated): ?>
  <script>
    Swal.fire({
      icon: 'success',
      title: 'บันทึกสำเร็จ!',
      text: 'ข้อมูลได้รับการอัปเดตแล้ว',
      confirmButtonText: 'ตกลง',
      confirmButtonColor: '#3085d6'
    }).then(() => {
      window.location.href = 'jobs.php';
    });
  </script>
  <?php endif; ?>

</body>
</html>
