<?php
session_start();
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: users.php");
    exit;
}

// ดึงแผนกทั้งหมด
$departments = $conn->query("SELECT * FROM departments");

// ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "ไม่พบผู้ใช้";
    exit;
}

// ดึงแผนกที่มองเห็นได้
$visible_stmt = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_department_id = ?");
$visible_stmt->bind_param("i", $user['department_id']);
$visible_stmt->execute();
$visible_result = $visible_stmt->get_result();
$visible_ids = [];
while ($row = $visible_result->fetch_assoc()) {
    $visible_ids[] = $row['to_department_id'];
}

// อัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $role = $_POST['role'];
    $password = $_POST['password'];
    $department_id = $_POST['department_id'];
    $visible_departments = $_POST['visible_departments'] ?? [];

    // อัปเดตข้อมูลหลัก
    if ($password !== '') {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, username=?, role=?, password=?, department_id=? WHERE id=?");
        $stmt->bind_param("ssssii", $name, $username, $role, $password_hashed, $department_id, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, username=?, role=?, department_id=? WHERE id=?");
        $stmt->bind_param("sssii", $name, $username, $role, $department_id, $id);
    }
    $stmt->execute();

    // ลบของเก่า (ใช้ department เดิมของ user แทนใหม่ เพื่อความถูกต้อง)
    $old_department_id = $user['department_id'];
    $conn->query("DELETE FROM department_visibility WHERE from_department_id = $old_department_id");

    // เพิ่มของใหม่
    foreach ($visible_departments as $to_dept_id) {
        $stmt = $conn->prepare("INSERT INTO department_visibility (from_department_id, to_department_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $department_id, $to_dept_id);
        $stmt->execute();
    }

    header("Location: users.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>✏️ แก้ไขผู้ใช้</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4 py-10">

  <div class="w-full max-w-xl bg-white shadow-lg rounded-xl p-6">
    <h2 class="text-2xl font-bold text-gray-700 mb-6">✏️ แก้ไขข้อมูลผู้ใช้</h2>

    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm text-gray-600">ชื่อ - นามสกุล</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" 
               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
      </div>

      <div>
        <label class="block text-sm text-gray-600">Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
               class="w-full px-3 py-2 border rounded focus:outline-none" required>
      </div>

      <div>
        <label class="block text-sm text-gray-600">บทบาท</label>
        <select name="role" class="w-full px-3 py-2 border rounded focus:outline-none">
          <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>🛡️ Admin</option>
          <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>👨‍💼 Manager</option>
          <option value="field" <?= $user['role'] === 'field' ? 'selected' : '' ?>>🧑‍💼 Field Officer</option>
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-600">แผนกหลัก</label>
        <select name="department_id" class="w-full px-3 py-2 border rounded" required>
          <?php
          $departments->data_seek(0);
          while ($d = $departments->fetch_assoc()):
          ?>
            <option value="<?= $d['id'] ?>" <?= ($user['department_id'] == $d['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($d['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-600">สามารถเห็นงานของแผนก (กด Ctrl เพื่อเลือกหลายอัน)</label>
        <select name="visible_departments[]" multiple class="w-full px-3 py-2 border rounded h-32">
          <?php
          $departments->data_seek(0);
          while ($d = $departments->fetch_assoc()):
          ?>
            <option value="<?= $d['id'] ?>" <?= in_array($d['id'], $visible_ids) ? 'selected' : '' ?>>
              <?= htmlspecialchars($d['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-600">เปลี่ยนรหัสผ่าน (หากไม่ต้องการเปลี่ยน ให้เว้นว่าง)</label>
        <input type="password" name="password" placeholder="รหัสผ่านใหม่ (ถ้ามี)"
               class="w-full px-3 py-2 border rounded focus:outline-none">
      </div>

      <div class="flex justify-between items-center pt-4">
        <a href="users.php" class="text-blue-600 hover:underline">🔙 กลับ</a>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-semibold transition">
          💾 บันทึกการแก้ไข
        </button>
      </div>
    </form>
  </div>

</body>
</html>
