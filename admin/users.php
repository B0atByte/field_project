<?php
session_start();
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

// เพิ่มแผนกใหม่
if (isset($_POST['add_department'])) {
    $new_department = trim($_POST['new_department']);
    if ($new_department !== '') {
        $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->bind_param("s", $new_department);
        $stmt->execute();
        header("Location: users.php");
        exit;
    }
}

// ดึงแผนกทั้งหมด
$departments = $conn->query("SELECT * FROM departments");

// เตรียมข้อมูลแผนกทั้งหมด
$department_map = [];
$dept_result = $conn->query("SELECT id, name FROM departments");
while ($row = $dept_result->fetch_assoc()) {
    $department_map[$row['id']] = $row['name'];
}

// เพิ่มผู้ใช้
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['username'], $_POST['password'], $_POST['role'], $_POST['department_id'])) {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $department_id = $_POST['department_id'];

    $stmt = $conn->prepare("INSERT INTO users (name, username, password, role, department_id, active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("ssssi", $name, $username, $password, $role, $department_id);
    $stmt->execute();

    header("Location: users.php");
    exit;
}

// ลบผู้ใช้
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id = $id");
    header("Location: users.php");
    exit;
}

// บล็อก/ปลดบล็อกผู้ใช้
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE users SET active = IF(active = 1, 0, 1) WHERE id = $id");
    header("Location: users.php");
    exit;
}

// ค้นหา
$search = $_GET['search'] ?? '';
$where = '1=1';
if ($search !== '') {
    $where .= " AND (name LIKE ? OR username LIKE ?)";
    $stmt = $conn->prepare("SELECT * FROM users WHERE $where ORDER BY id DESC");
    $param = "%$search%";
    $stmt->bind_param('ss', $param, $param);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query("SELECT users.*, departments.name as dept_name FROM users LEFT JOIN departments ON users.department_id = departments.id ORDER BY users.id DESC");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>👤 จัดการผู้ใช้งาน</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
<div class="max-w-6xl mx-auto bg-white rounded-xl shadow-xl p-6 space-y-6 relative">
  <div class="absolute top-4 right-6">
    <a href="../dashboard/admin.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
      🔙 กลับแดชบอร์ด
    </a>
  </div>
  <h2 class="text-3xl font-bold text-gray-800">👤 จัดการผู้ใช้งาน</h2>
  <form method="post" class="grid gap-4 md:grid-cols-2">
    <input type="text" name="name" placeholder="ชื่อ-นามสกุล" required class="border px-3 py-2 rounded w-full">
    <input type="text" name="username" placeholder="Username" required class="border px-3 py-2 rounded w-full">
    <input type="password" name="password" placeholder="Password" required class="border px-3 py-2 rounded w-full">
    <select name="role" required class="border px-3 py-2 rounded w-full">
      <option value="">-- เลือกบทบาท --</option>
      <option value="admin">🛡️ Admin</option>
      <option value="manager">👨‍💼 Manager</option>
      <option value="field">🧑‍💼 Field Officer</option>
    </select>
    <select name="department_id" required class="border px-3 py-2 rounded w-full">
      <option value="">-- เลือกแผนก --</option>
      <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
      <?php endwhile; ?>
    </select>
    <div class="md:col-span-2">
      <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded shadow">
        ✅ เพิ่มผู้ใช้
      </button>
    </div>
  </form>
  <hr class="my-6">
  <h3 class="text-xl font-semibold text-gray-700">➕ เพิ่มแผนก</h3>
  <form method="post" class="mt-2 flex gap-2 items-center">
    <input type="text" name="new_department" placeholder="ชื่อแผนกใหม่" class="border px-3 py-2 rounded w-full max-w-xs" required>
    <button type="submit" name="add_department" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
      ➕ เพิ่ม
    </button>
  </form>
  <form method="get" class="flex items-center gap-2 mt-6">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหา Username หรือชื่อ"
           class="border px-3 py-2 rounded w-full max-w-xs">
    <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">🔍 ค้นหา</button>
  </form>
  <div class="overflow-x-auto mt-4">
    <table class="min-w-full text-sm border border-gray-300 rounded">
      <thead class="bg-gray-100 text-gray-700">
        <tr>
          <th class="px-3 py-2 border">ชื่อ</th>
          <th class="px-3 py-2 border">Username</th>
          <th class="px-3 py-2 border">บทบาท</th>
          <th class="px-3 py-2 border">แผนก</th>
          <th class="px-3 py-2 border">เห็นงานของ</th>
          <th class="px-3 py-2 border">สถานะ</th>
          <th class="px-3 py-2 border">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($user = $users->fetch_assoc()): ?>
        <tr class="bg-white hover:bg-gray-50">
          <td class="px-3 py-2 border"><?= htmlspecialchars($user['name']) ?></td>
          <td class="px-3 py-2 border"><?= htmlspecialchars($user['username']) ?></td>
          <td class="px-3 py-2 border">
            <?php
              if ($user['role'] === 'admin') echo '🛡️ Admin';
              elseif ($user['role'] === 'manager') echo '👨‍💼 Manager';
              else echo '🧑‍💼 Field';
            ?>
          </td>
          <td class="px-3 py-2 border"><?= htmlspecialchars($user['dept_name'] ?? '-') ?></td>
          <td class="px-3 py-2 border">
            <?php
              $visible_query = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_department_id = ?");
              $visible_query->bind_param("i", $user['department_id']);
              $visible_query->execute();
              $visible_result = $visible_query->get_result();
              $visible_names = [];
              while ($v = $visible_result->fetch_assoc()) {
                  $visible_names[] = $department_map[$v['to_department_id']] ?? '-';
              }
              echo count($visible_names) > 0 ? implode(', ', $visible_names) : '-';
            ?>
          </td>
          <td class="px-3 py-2 border text-center">
            <?= $user['active'] ? '<span class="text-green-600">✅ ใช้งานได้</span>' : '<span class="text-red-600">🚫 ถูกบล็อก</span>' ?>
          </td>
          <td class="px-3 py-2 border text-center space-x-1">
            <a href="edit_user.php?id=<?= $user['id'] ?>" class="text-yellow-600 hover:underline">✏️ แก้ไข</a>
            <a href="?toggle=<?= $user['id'] ?>" class="text-indigo-600 hover:underline">
              <?= $user['active'] ? '🚫 บล็อก' : '✅ ปลดบล็อก' ?>
            </a>
            <a href="?delete=<?= $user['id'] ?>" onclick="return confirm('ลบผู้ใช้นี้หรือไม่?')" class="text-red-600 hover:underline">❌ ลบ</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
