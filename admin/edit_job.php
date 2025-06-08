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
    $fields = [
        'contract_number' => 'เลขสัญญา',
        'product' => 'ผลิตภัณฑ์',
        'location_info' => 'ข้อมูลพื้นที่',
        'zone' => 'โซน',
        'due_date' => 'ครบกำหนด',
        'overdue_period' => 'ระยะเวลาค้าง',
        'model' => 'รุ่น',
        'color' => 'สี',
        'plate' => 'ทะเบียน',
        'province' => 'จังหวัด',
        'os' => 'ยอดคงเหลือ OS',
        'assigned_to' => 'ผู้รับงาน',
        'status' => 'สถานะ'
    ];

    $changes = [];

    foreach ($fields as $key => $label) {
        $newValue = $_POST[$key];
        $oldValue = $job[$key];

        // แปลงชื่อ user หากเป็น assigned_to
        if ($key === 'assigned_to') {
            $oldUser = $conn->query("SELECT name FROM users WHERE id = '$oldValue'")->fetch_assoc()['name'] ?? '-';
            $newUser = $conn->query("SELECT name FROM users WHERE id = '$newValue'")->fetch_assoc()['name'] ?? '-';
            if ($oldValue != $newValue) {
                $changes[] = "$label: `$oldUser` → `$newUser`";
            }
        } else {
            if ($oldValue != $newValue) {
                $changes[] = "$label: `$oldValue` → `$newValue`";
            }
        }

        // ตั้งค่าตัวแปร
        $$key = $newValue;
    }

    // ถ้ามีการเปลี่ยนแปลง ค่อยอัปเดต
    if (count($changes)) {
        $stmt = $conn->prepare("UPDATE jobs SET 
            contract_number=?, product=?, location_info=?, zone=?, due_date=?, 
            overdue_period=?, model=?, color=?, plate=?, province=?, os=?, 
            assigned_to=?, status=? WHERE id=?");

        $stmt->bind_param("sssssssssssssi", 
            $contract_number, $product, $location_info, $zone, $due_date,
            $overdue_period, $model, $color, $plate, $province, $os,
            $assigned_to, $status, $id);

        $stmt->execute();

        // เพิ่ม log
        $admin_id = $_SESSION['user']['id'];
        $summary = "📝 รายการแก้ไข: " . implode(', ', $changes);
        $log_stmt = $conn->prepare("INSERT INTO job_edit_logs (job_id, edited_by, change_summary) VALUES (?, ?, ?)");
        $log_stmt->bind_param("iis", $id, $admin_id, $summary);
        $log_stmt->execute();

        $updated = true;
    }
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

  <div class="bg-white max-w-4xl w-full shadow-lg rounded-xl p-6 space-y-6 relative">
    <div class="absolute top-6 right-6">
      <a href="jobs.php" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">🔙 กลับหน้ารายการงาน</a>
    </div>

    <h2 class="text-2xl font-bold text-gray-800 mb-4">✏️ แก้ไขข้อมูลงาน</h2>

    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="text" name="contract_number" value="<?= htmlspecialchars($job['contract_number']) ?>" class="border px-3 py-2 rounded w-full" placeholder="เลขสัญญา" required>
      <input type="text" name="product" value="<?= htmlspecialchars($job['product']) ?>" class="border px-3 py-2 rounded w-full" placeholder="Product">
      <input type="text" name="location_info" value="<?= htmlspecialchars($job['location_info']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ข้อมูลพื้นที่">
      <input type="text" name="zone" value="<?= htmlspecialchars($job['zone']) ?>" class="border px-3 py-2 rounded w-full" placeholder="โซน">
      <input type="date" name="due_date" value="<?= htmlspecialchars($job['due_date']) ?>" class="border px-3 py-2 rounded w-full">
      <input type="text" name="overdue_period" value="<?= htmlspecialchars($job['overdue_period']) ?>" class="border px-3 py-2 rounded w-full" placeholder="Overdue">
      <input type="text" name="model" value="<?= htmlspecialchars($job['model']) ?>" class="border px-3 py-2 rounded w-full" placeholder="รุ่น">
      <input type="text" name="color" value="<?= htmlspecialchars($job['color']) ?>" class="border px-3 py-2 rounded w-full" placeholder="สี">
      <input type="text" name="plate" value="<?= htmlspecialchars($job['plate']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ทะเบียน">
      <input type="text" name="province" value="<?= htmlspecialchars($job['province']) ?>" class="border px-3 py-2 rounded w-full" placeholder="จังหวัด">
      <input type="text" name="os" value="<?= htmlspecialchars($job['os']) ?>" class="border px-3 py-2 rounded w-full" placeholder="ยอดคงเหลือ OS">

      <!-- ผู้รับผิดชอบ -->
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">ผู้รับผิดชอบ</label>
        <select name="assigned_to" class="border px-3 py-2 rounded w-full">
          <?php
          $users = $conn->query("SELECT id, name FROM users WHERE role = 'field'");
          while ($u = $users->fetch_assoc()):
          ?>
            <option value="<?= $u['id'] ?>" <?= $u['id'] == $job['assigned_to'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- สถานะ -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
        <select name="status" class="border px-3 py-2 rounded w-full">
          <option value="pending" <?= ($job['status'] ?? '') === 'pending' ? 'selected' : '' ?>>🟡 ยังไม่เสร็จ</option>
          <option value="completed" <?= ($job['status'] ?? '') === 'completed' ? 'selected' : '' ?>>✅ เสร็จแล้ว</option>
        </select>
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
      title: '✅ บันทึกสำเร็จ',
      text: 'คุณได้ทำการอัปเดตข้อมูลงานเรียบร้อยแล้ว',
      confirmButtonText: 'ตกลง',
      confirmButtonColor: '#3085d6'
    }).then(() => {
      window.location.href = 'jobs.php';
    });
  </script>
  <?php endif; ?>

</body>
</html>
