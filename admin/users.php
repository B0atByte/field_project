<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header("Location: ../index.php");
  exit;
}
include '../config/db.php';
require_once '../includes/csrf.php';
require_once '../includes/password_validation.php';

/* ---------- เพิ่มแผนก ---------- */
if (isset($_POST['add_department'])) {
  requireCsrfToken();
  $new_department = trim($_POST['new_department']);
  if ($new_department !== '') {
    $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
    $stmt->bind_param("s", $new_department);
    $stmt->execute();
    $_SESSION['success_message'] = "เพิ่มแผนกเรียบร้อยแล้ว";
    header("Location: users.php?tab=departments");
    exit;
  }
}

/* ---------- แก้ไขแผนก ---------- */
if (isset($_POST['edit_department'])) {
  requireCsrfToken();
  $dept_id = intval($_POST['department_id']);
  $dept_name = trim($_POST['department_name']);
  if ($dept_name !== '' && $dept_id > 0) {
    $stmt = $conn->prepare("UPDATE departments SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $dept_name, $dept_id);
    $stmt->execute();
    $_SESSION['success_message'] = "แก้ไขแผนกเรียบร้อยแล้ว";
  }
  header("Location: users.php?tab=departments");
  exit;
}

/* ---------- ลบแผนก ---------- */
if (isset($_GET['delete_department'])) {
  $dept_id = intval($_GET['delete_department']);
  $token = $_GET['token'] ?? '';
  if (!hash_equals(getCsrfToken(), $token)) {
    $_SESSION['error_message'] = "CSRF token ไม่ถูกต้อง";
    header("Location: users.php?tab=departments");
    exit;
  }
  // ตรวจสอบว่ามี user ใช้แผนกนี้อยู่หรือไม่
  $check = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE department_id = ?");
  $check->bind_param("i", $dept_id);
  $check->execute();
  $user_count = $check->get_result()->fetch_assoc()['cnt'] ?? 0;
  if ($user_count > 0) {
    $_SESSION['error_message'] = "ไม่สามารถลบแผนกนี้ได้ เนื่องจากมีผู้ใช้งาน {$user_count} คนอยู่ในแผนกนี้";
  } else {
    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $_SESSION['success_message'] = "ลบแผนกเรียบร้อยแล้ว";
  }
  header("Location: users.php?tab=departments");
  exit;
}

/* ---------- แผนกทั้งหมด + map ---------- */
$departments = $conn->query("SELECT d.*, COUNT(u.id) as user_count FROM departments d LEFT JOIN users u ON u.department_id = d.id GROUP BY d.id ORDER BY d.id");
$department_map = [];
$dept_result = $conn->query("SELECT id, name FROM departments");
while ($row = $dept_result->fetch_assoc())
  $department_map[$row['id']] = $row['name'];

/* ---------- เพิ่มผู้ใช้ใหม่ ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['username'], $_POST['password'], $_POST['role'], $_POST['department_id'])) {
  requireCsrfToken();
  $name = $_POST['name'];
  $username = $_POST['username'];
  $plain_password = $_POST['password'];
  $role = $_POST['role'];
  $department_id = $_POST['department_id'];
  $can_delete_jobs = isset($_POST['can_delete_jobs']) ? 1 : 0;

  // ตรวจสอบความแข็งแรงของรหัสผ่าน
  $validation = validatePasswordStrength($plain_password, 8, true, true, true, false);

  if (!$validation['valid']) {
    $_SESSION['error_message'] = "รหัสผ่านไม่ปลอดภัยพอ:\n" . implode("\n", $validation['errors']);
    header("Location: users.php");
    exit;
  }

  $password = password_hash($plain_password, PASSWORD_DEFAULT);

  // เช็ค Username ซ้ำ
  $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
  $check->bind_param("s", $username);
  $check->execute();
  if ($check->get_result()->num_rows > 0) {
    $_SESSION['error_message'] = "Username '$username' มีผู้ใช้งานแล้ว กรุณาใช้ชื่ออื่น";
    header("Location: users.php");
    exit;
  }

  $stmt = $conn->prepare("INSERT INTO users (name, username, password, role, department_id, active, can_delete_jobs)
                            VALUES (?, ?, ?, ?, ?, 1, ?)");
  $stmt->bind_param("ssssii", $name, $username, $password, $role, $department_id, $can_delete_jobs);

  if ($stmt->execute()) {
    $_SESSION['success_message'] = "เพิ่มผู้ใช้งานเรียบร้อยแล้ว";
  } else {
    $_SESSION['error_message'] = "เกิดข้อผิดพลาด: " . $conn->error;
  }

  header("Location: users.php");
  exit;
}

/* ---------- ลบ/บล็อก ---------- */
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  header("Location: users.php");
  exit;
}
if (isset($_GET['toggle'])) {
  $id = intval($_GET['toggle']);
  $stmt = $conn->prepare("UPDATE users SET active = IF(active = 1, 0, 1) WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: users.php");
  exit;
}

/* ---------- Users + Stats ---------- */
$stmt = $conn->prepare("SELECT users.*, departments.name as dept_name 
                        FROM users 
                        LEFT JOIN departments ON users.department_id = departments.id 
                        ORDER BY users.id DESC");
$stmt->execute();
$users = $stmt->get_result();

$stats_total = (int) ($conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0);
$stats_active = (int) ($conn->query("SELECT COUNT(*) c FROM users WHERE active=1")->fetch_assoc()['c'] ?? 0);
$stats_blocked = (int) ($conn->query("SELECT COUNT(*) c FROM users WHERE active=0")->fetch_assoc()['c'] ?? 0);
$stats_manager = (int) ($conn->query("SELECT COUNT(*) c FROM users WHERE role='manager'")->fetch_assoc()['c'] ?? 0);

$page_title = "จัดการผู้ใช้งาน";
include '../components/header.php';
include '../components/sidebar.php';
?>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  main {
    width: calc(100% - 16rem);
    /* Sidebar width compensation */
  }

  thead {
    background-color: #0f172a;
    color: #fff;
  }

  ห tbody tr:hover {
    background: #f8fafc;
  }

  .chip {
    display: inline-block;
    padding: .22rem .55rem;
    border-radius: 9999px;
    font-size: .72rem;
    font-weight: 600
  }

  .chip-green {
    background: #dcfce7;
    color: #166534
  }

  .chip-yellow {
    background: #fef9c3;
    color: #854d0e
  }

  .chip-red {
    background: #fee2e2;
    color: #991b1b
  }

  .chip-indigo {
    background: #e0e7ff;
    color: #3730a3
  }

  .avatar {
    width: 32px;
    height: 32px;
    border-radius: 9999px;
    background: #1e40af;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .8rem
  }

  #usersTable th,
  #usersTable td,
  #deptTable th,
  #deptTable td {
    white-space: nowrap;
    padding: 8px 12px;
  }

  .btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.5rem;
    background: #f1f5f9;
    color: #1e293b;
    transition: background 0.15s, color 0.15s;
    font-size: 1.15rem;
    border: none;
    outline: none;
    cursor: pointer;
  }

  .btn-icon:hover {
    background: #6366f1;
    color: #fff;
  }

  .btn-icon.edit {
    color: #2563eb;
  }

  .btn-icon.lock {
    color: #f59e42;
  }

  .btn-icon.delete {
    color: #ef4444;
  }

  .cursor-pointer {
    cursor: pointer;
  }

  #usersTable tbody tr:hover {
    background-color: #f9fafb;
  }

  .btn-top {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 1rem;
    font-weight: 500;
    border-radius: 0.5rem;
    transition: background 0.15s, color 0.15s;
  }

  .btn-top i {
    font-size: 1.15rem;
  }

  .btn-top.add {
    background: #6366f1;
    color: #fff;
  }

  .btn-top.add:hover {
    background: #4338ca;
  }

  .btn-top.export {
    background: #f1f5f9;
    color: #334155;
  }

  .btn-top.export:hover {
    background: #e0e7ff;
    color: #6366f1;
  }

  .btn-top.back {
    background: #0f172a;
    color: #fff;
  }

  .btn-top.back:hover {
    background: #1e293b;
  }
</style>

<main class="p-6 ml-64 min-h-screen font-prompt bg-gray-100">

  <?php if (isset($_SESSION['success_message'])): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
          icon: 'success',
          title: 'สำเร็จ',
          text: <?= json_encode($_SESSION['success_message'], JSON_UNESCAPED_UNICODE) ?>,
          confirmButtonColor: '#16a34a',
          timer: 2000
        });
      });
    </script>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
          icon: 'error',
          title: 'เกิดข้อผิดพลาด',
          text: <?= json_encode($_SESSION['error_message'], JSON_UNESCAPED_UNICODE) ?>,
          confirmButtonColor: '#dc2626'
        });
      });
    </script>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <!-- Header + Toolbar -->
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl font-bold">ผู้ใช้งาน (Virtualized)</h1>
      <p class="text-sm text-gray-500">จัดการผู้ใช้ ค้นหา กรอง และส่งออก</p>
    </div>
    <div class="flex items-center gap-2">
      <button id="toggleAddUser" class="btn-top add px-3 py-2 shadow text-sm">
        <i class="fa-solid fa-user-plus"></i> เพิ่มผู้ใช้
      </button>
      <button id="exportCsv" class="btn-top export px-3 py-2 shadow text-sm">
        <i class="fa-solid fa-file-arrow-down"></i> นำออก CSV
      </button>
      <a href="../dashboard/admin.php" class="btn-top back px-3 py-2 shadow text-sm">
        <i class="fa-solid fa-arrow-left"></i> แดชบอร์ด
      </a>
    </div>
  </div>

  <!-- Add user panel (toggle) -->
  <div id="addUserPanel" class="bg-white rounded-xl shadow p-6 mb-6 hidden">
    <h2 class="text-lg font-semibold mb-4">เพิ่มผู้ใช้ใหม่</h2>
    <form method="post" class="grid gap-4 md:grid-cols-2">
      <?= csrfField() ?>
      <input type="text" name="name" placeholder="ชื่อ-นามสกุล" required class="border px-3 py-2 rounded w-full">
      <input type="text" name="username" placeholder="Username" required class="border px-3 py-2 rounded w-full">
      <input type="password" name="password" placeholder="Password" required class="border px-3 py-2 rounded w-full">

      <select name="role" id="roleSelect" required class="border px-3 py-2 rounded w-full">
        <option value="">-- เลือกบทบาท --</option>
        <option value="admin">Admin</option>
        <option value="manager">Manager</option>
        <option value="field">Field</option>
      </select>

      <select name="department_id" required class="border px-3 py-2 rounded w-full">
        <option value="">-- เลือกแผนก --</option>
        <?php $departments->data_seek(0);
        while ($dept = $departments->fetch_assoc()): ?>
          <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
        <?php endwhile; ?>
      </select>

      <div id="manager-permissions" class="md:col-span-2 hidden bg-gray-50 border p-4 rounded">
        <label class="block font-semibold mb-2 text-gray-700">สิทธิ์เพิ่มเติมสำหรับ Manager</label>
        <label class="inline-flex items-center">
          <input type="checkbox" name="can_delete_jobs" class="h-5 w-5 text-blue-600">
          <span class="ml-2">ลบงานได้</span>
        </label>
      </div>

      <div class="md:col-span-2">
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg shadow">
          บันทึกผู้ใช้
        </button>
      </div>
    </form>
  </div> <!-- <-- เพิ่มปิด div ตรงนี้ -->

  <!-- Stat Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow p-4 text-center">
      <div class="text-xs text-gray-500">ทั้งหมด</div>
      <div class="text-2xl font-bold"><?= $stats_total ?></div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
      <div class="text-xs text-gray-500">ใช้งานอยู่</div>
      <div class="text-2xl font-bold text-green-600"><?= $stats_active ?></div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
      <div class="text-xs text-gray-500">Manager</div>
      <div class="text-2xl font-bold text-indigo-600"><?= $stats_manager ?></div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
      <div class="text-xs text-gray-500">ถูกบล็อก</div>
      <div class="text-2xl font-bold text-red-600"><?= $stats_blocked ?></div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="bg-white rounded-xl shadow mb-6">
    <div class="flex border-b">
      <button id="tabUsers"
        class="tab-btn active px-6 py-3 font-medium text-sm border-b-2 border-blue-600 text-blue-600">
        <i class="fa-solid fa-users mr-2"></i>ผู้ใช้งาน
      </button>
      <button id="tabUnlock"
        class="tab-btn px-6 py-3 font-medium text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-700">
        <i class="fa-solid fa-unlock mr-2"></i>ปลดล็อค IP
      </button>
      <button id="tabDepartments"
        class="tab-btn px-6 py-3 font-medium text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-700">
        <i class="fa-solid fa-building mr-2"></i>แผนก
      </button>
    </div>
  </div>

  <!-- Tab Content: Users -->
  <div id="contentUsers" class="tab-content">
    <!-- Search and Filters -->
    <div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-col lg:flex-row gap-3">
      <div class="relative flex-1">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400">search</span>
        <input id="quickSearch" type="text" placeholder="ค้นหาชื่อ/Username/แผนก…"
          class="w-full border rounded-lg pl-10 pr-3 py-2">
      </div>
      <div class="flex gap-3">
        <select id="roleFilter" class="border rounded-lg px-3 py-2">
          <option value="">บทบาททั้งหมด</option>
          <option value="admin">Admin</option>
          <option value="manager">Manager</option>
          <option value="field">Field</option>
        </select>
        <select id="statusFilter" class="border rounded-lg px-3 py-2">
          <option value="">สถานะทั้งหมด</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </select>
      </div>
    </div>

    <!-- User Table -->
    <div class="bg-white rounded-xl shadow p-4 w-full">
      <div class="overflow-x-auto w-full">
        <table id="usersTable" class="w-full text-sm">
          <thead>
            <tr>
              <th>ผู้ใช้</th>
              <th>Username</th>
              <th>บทบาท</th>
              <th>แผนก</th>
              <th>สิทธิ์มองเห็น</th>
              <th>สถานะ</th>
              <th class="text-center">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($user = $users->fetch_assoc()): ?>
              <?php
              $visible_query = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_user_id = ?");
              $visible_query->bind_param("i", $user['id']);
              $visible_query->execute();
              $visible_result = $visible_query->get_result();
              $visible_names = [];
              while ($v = $visible_result->fetch_assoc())
                $visible_names[] = $department_map[$v['to_department_id']] ?? '-';
              $visible_string = count($visible_names) ? implode(', ', $visible_names) : '-';

              $status_tag = $user['active'] ? '<span class="chip chip-green">active</span>' : '<span class="chip chip-red">suspended</span>';
              $role_tag = $user['role'] === 'admin' ? '<span class="chip chip-indigo">admin</span>'
                : ($user['role'] === 'manager' ? '<span class="chip chip-yellow">manager</span>'
                  : '<span class="chip">field</span>');
              $initials = mb_substr($user['name'], 0, 2, 'UTF-8');
              ?>
              <tr data-role="<?= htmlspecialchars($user['role']) ?>"
                data-status="<?= $user['active'] ? 'active' : 'suspended' ?>" class="hover:bg-gray-50">
                <td class="px-3 py-2">
                  <div class="flex items-center gap-2">
                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                    <div>
                      <div class="font-medium"><?= htmlspecialchars($user['name']) ?></div>
                      <div class="text-xs text-gray-500">ID: <?= (int) $user['id'] ?></div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2"><?= htmlspecialchars($user['username']) ?></td>
                <td class="px-3 py-2"><?= $role_tag ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($user['dept_name'] ?? '-') ?></td>
                <td class="px-3 py-2 text-xs"><?= htmlspecialchars($visible_string) ?></td>
                <td class="px-3 py-2"><?= $status_tag ?></td>
                <td class="px-3 py-2 text-center whitespace-nowrap">
                  <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-icon edit" title="แก้ไข">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </a>
                  <a href="?toggle=<?= $user['id'] ?>" class="btn-icon lock ml-1"
                    title="<?= $user['active'] ? 'บล็อก' : 'ปลดบล็อก' ?>">
                    <?php if ($user['active']): ?>
                      <i class="fa-solid fa-lock"></i>
                    <?php else: ?>
                      <i class="fa-solid fa-lock-open"></i>
                    <?php endif; ?>
                  </a>
                  <a href="?delete=<?= $user['id'] ?>" onclick="return confirm('ลบผู้ใช้นี้หรือไม่?')"
                    class="btn-icon delete ml-1" title="ลบ">
                    <i class="fa-solid fa-trash"></i>
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Tab Content: Unlock IP -->
  <div id="contentUnlock" class="tab-content hidden">
    <div class="bg-white rounded-xl shadow p-6">
      <h3 class="text-lg font-semibold mb-4 flex items-center">
        <i class="fa-solid fa-shield-halved text-red-600 mr-2"></i>
        IP ที่ถูกล็อค (ใส่รหัสผิดเกิน 5 ครั้ง)
      </h3>
      <div id="lockedIPsContainer">
        <div class="text-center py-8">
          <i class="fa-solid fa-spinner fa-spin text-4xl text-gray-400 mb-3"></i>
          <p class="text-gray-500">กำลังโหลดข้อมูล...</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Tab Content: Departments -->
  <div id="contentDepartments" class="tab-content hidden">
    <div class="bg-white rounded-xl shadow p-6 mb-6">
      <h3 class="text-lg font-semibold mb-4 flex items-center">
        <i class="fa-solid fa-plus-circle text-green-600 mr-2"></i>
        เพิ่มแผนกใหม่
      </h3>
      <form method="post" class="flex gap-3 items-end">
        <?= csrfField() ?>
        <input type="text" name="new_department" placeholder="ชื่อแผนก" required
          class="border px-3 py-2 rounded-lg flex-1 max-w-md">
        <button type="submit" name="add_department" value="1"
          class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg shadow font-medium">
          <i class="fa-solid fa-plus mr-1"></i> เพิ่มแผนก
        </button>
      </form>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-col lg:flex-row gap-3">
      <div class="relative flex-1">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400">search</span>
        <input id="deptSearch" type="text" placeholder="ค้นหาชื่อแผนก…"
          class="w-full border rounded-lg pl-10 pr-3 py-2">
      </div>
      <div class="flex gap-3">
        <select id="deptUserFilter" class="border rounded-lg px-3 py-2">
          <option value="">ผู้ใช้ทั้งหมด</option>
          <option value="has_users">มีผู้ใช้</option>
          <option value="no_users">ไม่มีผู้ใช้</option>
        </select>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow p-4 w-full">
      <div class="overflow-x-auto w-full">
        <table id="deptTable" class="w-full text-sm">
          <thead>
            <tr>
              <th class="px-4 py-3 text-left" style="width:80px">ID</th>
              <th class="px-4 py-3 text-left">ชื่อแผนก</th>
              <th class="px-4 py-3 text-center" style="width:150px">จำนวนผู้ใช้</th>
              <th class="px-4 py-3 text-center" style="width:120px">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50" data-usercount="<?= (int)$dept['user_count'] ?>">
                <td class="px-4 py-3 text-gray-500"><?= (int)$dept['id'] ?></td>
                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($dept['name']) ?></td>
                <td class="px-4 py-3 text-center">
                  <span class="chip <?= $dept['user_count'] > 0 ? 'chip-green' : 'chip-yellow' ?>">
                    <?= (int)$dept['user_count'] ?> คน
                  </span>
                </td>
                <td class="px-4 py-3 text-center whitespace-nowrap">
                  <button onclick="editDepartment(<?= (int)$dept['id'] ?>, <?= htmlspecialchars(json_encode($dept['name']), ENT_QUOTES) ?>)"
                    class="btn-icon edit" title="แก้ไข">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>
                  <button onclick="deleteDepartment(<?= (int)$dept['id'] ?>, <?= htmlspecialchars(json_encode($dept['name']), ENT_QUOTES) ?>, <?= (int)$dept['user_count'] ?>)"
                    class="btn-icon delete ml-1" title="ลบ">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>

<style>
  .tab-btn {
    transition: all 0.2s;
  }

  .tab-btn.active {
    color: #2563eb;
    border-color: #2563eb;
  }

  .tab-content {
    animation: fadeIn 0.3s;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(10px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>

<script>
  const csrfToken = '<?= getCsrfToken() ?>';

  // Tab Switching
  function switchTab(activeTab) {
    const tabs = ['tabUsers', 'tabUnlock', 'tabDepartments'];
    const contents = ['contentUsers', 'contentUnlock', 'contentDepartments'];
    tabs.forEach((t, i) => {
      document.getElementById(t).classList.remove('active');
      document.getElementById(contents[i]).classList.add('hidden');
    });
    document.getElementById(activeTab).classList.add('active');
    const contentId = activeTab.replace('tab', 'content');
    document.getElementById(contentId).classList.remove('hidden');
  }

  document.getElementById('tabUsers').addEventListener('click', function () {
    switchTab('tabUsers');
  });

  document.getElementById('tabUnlock').addEventListener('click', function () {
    switchTab('tabUnlock');
    loadLockedIPs();
  });

  document.getElementById('tabDepartments').addEventListener('click', function () {
    switchTab('tabDepartments');
  });

  // Auto-switch to departments tab if requested via URL
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('tab') === 'departments') {
    switchTab('tabDepartments');
  }

  // Load Locked IPs
  async function loadLockedIPs() {
    try {
      const response = await fetch('api/get_locked_ips.php');
      const data = await response.json();

      const container = document.getElementById('lockedIPsContainer');

      if (!data.success || data.locked_ips.length === 0) {
        container.innerHTML = `
          <div class="text-center py-12">
            <i class="fa-solid fa-check-circle text-6xl text-green-500 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">ไม่มี IP ที่ถูกล็อค</h3>
            <p class="text-gray-600">ระบบปลอดภัย ไม่มีการพยายาม login ที่ผิดปกติ</p>
          </div>
        `;
        return;
      }

      let html = `
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">จำนวนครั้ง</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ครั้งล่าสุด</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">ดำเนินการ</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
      `;

      data.locked_ips.forEach(row => {
        html += `
          <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="font-mono text-sm font-semibold text-gray-900">${escapeHtml(row.ip_address)}</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${escapeHtml(row.username || '-')}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                ${row.attempt_count} ครั้ง
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${escapeHtml(row.last_attempt)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-center">
              <button onclick="unlockIP('${escapeHtml(row.ip_address)}')"
                      class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
                <i class="fa-solid fa-unlock mr-1"></i>
                ปลดล็อค
              </button>
            </td>
          </tr>
        `;
      });

      html += `
            </tbody>
          </table>
        </div>
      `;

      container.innerHTML = html;
    } catch (error) {
      console.error('Error loading locked IPs:', error);
      document.getElementById('lockedIPsContainer').innerHTML = `
        <div class="text-center py-8">
          <i class="fa-solid fa-exclamation-triangle text-4xl text-red-500 mb-3"></i>
          <p class="text-red-600">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>
        </div>
      `;
    }
  }

  // Unlock IP
  async function unlockIP(ipAddress) {
    const result = await Swal.fire({
      title: 'ยืนยันการปลดล็อค',
      html: `คุณต้องการปลดล็อค IP <strong>${ipAddress}</strong> ใช่หรือไม่?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'ยืนยัน',
      cancelButtonText: 'ยกเลิก',
      confirmButtonColor: '#10b981',
      cancelButtonColor: '#6b7280'
    });

    if (!result.isConfirmed) return;

    try {
      const response = await fetch('api/unlock_ip.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ ip_address: ipAddress })
      });

      const data = await response.json();

      if (data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'ปลดล็อคสำเร็จ',
          text: `IP ${ipAddress} สามารถ login ได้แล้ว`,
          confirmButtonColor: '#10b981'
        });
        loadLockedIPs();
      } else {
        throw new Error(data.message || 'ไม่สามารถปลดล็อคได้');
      }
    } catch (error) {
      console.error('Error unlocking IP:', error);
      Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: error.message || 'ไม่สามารถปลดล็อคได้'
      });
    }
  }

  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  // Users Table (DataTables)
      const dt = $('#usersTable').DataTable({
        scrollX: true,
      autoWidth: false,
      responsive: true,
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100],
      language: {
        search: "ค้นหา:",
      lengthMenu: "แสดง _MENU_ แถว",
      info: "แถว _START_–_END_ จาก _TOTAL_",
      paginate: {previous: "ก่อนหน้า", next: "ถัดไป" },
      zeroRecords: "ไม่พบข้อมูล",
      infoEmpty: "ไม่มีข้อมูล",
      infoFiltered: "(กรองจากทั้งหมด _MAX_ แถว)"
    },
      dom: 't<"flex items-center justify-between mt-3"lip>',
      createdRow: function (row, data, dataIndex) {
        // เพิ่ม cursor pointer
        $(row).addClass('cursor-pointer');

      // เมื่อคลิกแถว ให้ไปหน้าแก้ไข
      $(row).on('click', function (e) {
        // ถ้าคลิกที่ปุ่ม action (แก้ไข/ล็อค/ลบ) ให้ทำงานตามปกติ
        if ($(e.target).is('a') || $(e.target).closest('a').length ||
      $(e.target).is('i') || $(e.target).closest('.btn-icon').length) {
          return;
        }
      // หา user id จาก cell แรก
      const userId = $(row).find('td:first .text-xs').text().replace('ID: ', '').trim();
      if (userId) {
        window.location.href = `edit_user.php?id=${userId}`;
        }
      });
    }
  });

      $('#quickSearch').on('keyup change', function () {
        dt.search(this.value).draw();
  });

      $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    if (settings.nTable.id !== 'usersTable') return true;
    const roleF = $('#roleFilter').val();
      const statF = $('#statusFilter').val();
      const node = dt.row(dataIndex).node();
      const rRole = node.getAttribute('data-role');
      const rStat = node.getAttribute('data-status');
      if (roleF && rRole !== roleF) return false;
      if (statF && rStat !== statF) return false;
      return true;
  });

  $('#roleFilter,#statusFilter').on('change', () => dt.draw());

  document.getElementById('exportCsv').addEventListener('click', () => {
        let headers = ["ชื่อ", "Username", "บทบาท", "แผนก", "สิทธิ์มองเห็น", "สถานะ"];
      let rows = [];
      dt.rows({search: 'applied' }).every(function () {
        let tr = this.node();
      let tds = tr.querySelectorAll('td');
      rows.push([
      tds[0].innerText.trim().split('\n')[0],
      tds[1].innerText.trim(),
      tds[2].innerText.trim(),
      tds[3].innerText.trim(),
      tds[4].innerText.trim(),
      tds[5].innerText.trim()
      ]);
    });
    let csv = [headers.join(',')].concat(rows.map(r => r.map(v => `"${v}"`).join(','))).join('\n');
      let blob = new Blob([csv], {type: 'text/csv;charset=utf-8;' });
      let url = URL.createObjectURL(blob);
      let a = document.createElement('a');
      a.href = url; a.download = 'users_export.csv'; a.click();
      URL.revokeObjectURL(url);
  });

      // เพิ่มโค้ด toggle panel สำหรับเพิ่มสมาชิก
      document.getElementById('toggleAddUser').addEventListener('click', function () {
    const panel = document.getElementById('addUserPanel');
      panel.classList.toggle('hidden');
  });

  // Departments Table (DataTables)
  const dtDept = $('#deptTable').DataTable({
    autoWidth: false,
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    language: {
      search: "ค้นหา:",
      lengthMenu: "แสดง _MENU_ แถว",
      info: "แถว _START_–_END_ จาก _TOTAL_",
      paginate: { previous: "ก่อนหน้า", next: "ถัดไป" },
      zeroRecords: "ไม่พบข้อมูล",
      infoEmpty: "ไม่มีข้อมูล",
      infoFiltered: "(กรองจากทั้งหมด _MAX_ แถว)"
    },
    dom: 't<"flex items-center justify-between mt-3"lip>',
    order: [[0, 'asc']]
  });

  $('#deptSearch').on('keyup change', function () {
    dtDept.search(this.value).draw();
  });

  $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    if (settings.nTable.id !== 'deptTable') return true;
    const filterVal = $('#deptUserFilter').val();
    if (!filterVal) return true;
    const node = dtDept.row(dataIndex).node();
    const count = parseInt(node.getAttribute('data-usercount')) || 0;
    if (filterVal === 'has_users') return count > 0;
    if (filterVal === 'no_users') return count === 0;
    return true;
  });

  $('#deptUserFilter').on('change', () => dtDept.draw());

  // Department: Edit
  function editDepartment(id, currentName) {
    Swal.fire({
      title: 'แก้ไขชื่อแผนก',
      input: 'text',
      inputValue: currentName,
      inputPlaceholder: 'ชื่อแผนกใหม่',
      showCancelButton: true,
      confirmButtonText: 'บันทึก',
      cancelButtonText: 'ยกเลิก',
      confirmButtonColor: '#2563eb',
      inputValidator: (value) => {
        if (!value || !value.trim()) return 'กรุณากรอกชื่อแผนก';
      }
    }).then((result) => {
      if (result.isConfirmed) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'users.php';
        form.innerHTML = `
          <input type="hidden" name="csrf_token" value="${csrfToken}">
          <input type="hidden" name="edit_department" value="1">
          <input type="hidden" name="department_id" value="${id}">
          <input type="hidden" name="department_name" value="${result.value.trim()}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    });
  }

  // Department: Delete
  function deleteDepartment(id, name, userCount) {
    if (userCount > 0) {
      Swal.fire({
        icon: 'error',
        title: 'ไม่สามารถลบได้',
        html: `แผนก <strong>${escapeHtml(name)}</strong> มีผู้ใช้งาน <strong>${userCount}</strong> คน<br>กรุณาย้ายผู้ใช้ออกจากแผนกก่อนลบ`,
        confirmButtonColor: '#dc2626'
      });
      return;
    }
    Swal.fire({
      title: 'ยืนยันการลบแผนก',
      html: `คุณต้องการลบแผนก <strong>${escapeHtml(name)}</strong> ใช่หรือไม่?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'ลบ',
      cancelButtonText: 'ยกเลิก',
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#6b7280'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = `users.php?delete_department=${id}&token=${csrfToken}`;
      }
    });
  }
</script>