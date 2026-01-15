<?php
require_once __DIR__ . '/../includes/session_config.php';
if ($_SESSION['user']['role'] !== 'admin') {
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
        header("Location: users.php");
        exit;
    }
}

/* ---------- แผนกทั้งหมด + map ---------- */
$departments   = $conn->query("SELECT * FROM departments");
$department_map = [];
$dept_result    = $conn->query("SELECT id, name FROM departments");
while ($row = $dept_result->fetch_assoc()) $department_map[$row['id']] = $row['name'];

/* ---------- เพิ่มผู้ใช้ใหม่ ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['username'], $_POST['password'], $_POST['role'], $_POST['department_id'])) {
    requireCsrfToken();
    $name         = $_POST['name'];
    $username     = $_POST['username'];
    $plain_password = $_POST['password'];
    $role         = $_POST['role'];
    $department_id= $_POST['department_id'];
    $can_delete_jobs = isset($_POST['can_delete_jobs']) ? 1 : 0;

    // ตรวจสอบความแข็งแรงของรหัสผ่าน
    $validation = validatePasswordStrength($plain_password, 8, true, true, true, false);

    if (!$validation['valid']) {
        $_SESSION['error_message'] = "รหัสผ่านไม่ปลอดภัยพอ:\n" . implode("\n", $validation['errors']);
        header("Location: users.php");
        exit;
    }

    $password = password_hash($plain_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, username, password, role, department_id, active, can_delete_jobs)
                            VALUES (?, ?, ?, ?, ?, 1, ?)");
    $stmt->bind_param("ssssii", $name, $username, $password, $role, $department_id, $can_delete_jobs);
    $stmt->execute();
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

$stats_total   = (int)($conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0);
$stats_active  = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE active=1")->fetch_assoc()['c'] ?? 0);
$stats_blocked = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE active=0")->fetch_assoc()['c'] ?? 0);
$stats_manager = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='manager'")->fetch_assoc()['c'] ?? 0);

$page_title = "👤 จัดการผู้ใช้งาน";
include '../components/header.php';
include '../components/sidebar.php';
?>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  main {
    width: calc(100% - 16rem); /* Sidebar width compensation */
  }
  thead { background-color:#0f172a; color:#fff; }ห
  tbody tr:hover { background:#f8fafc; }
  .chip{display:inline-block;padding:.22rem .55rem;border-radius:9999px;font-size:.72rem;font-weight:600}
  .chip-green{background:#dcfce7;color:#166534}
  .chip-yellow{background:#fef9c3;color:#854d0e}
  .chip-red{background:#fee2e2;color:#991b1b}
  .chip-indigo{background:#e0e7ff;color:#3730a3}
  .avatar{width:32px;height:32px;border-radius:9999px;background:#1e40af;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem}
  #usersTable th, #usersTable td { white-space: nowrap; padding: 8px 12px; }

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
  .btn-icon.edit { color: #2563eb; }
  .btn-icon.lock { color: #f59e42; }
  .btn-icon.delete { color: #ef4444; }

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
  .btn-top.add { background: #6366f1; color: #fff; }
  .btn-top.add:hover { background: #4338ca; }
  .btn-top.export { background: #f1f5f9; color: #334155; }
  .btn-top.export:hover { background: #e0e7ff; color: #6366f1; }
  .btn-top.back { background: #0f172a; color: #fff; }
  .btn-top.back:hover { background: #1e293b; }
</style>

<main class="p-6 ml-64 min-h-screen font-prompt bg-gray-100">
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
        <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
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

  <!-- Search and Filters -->
  <div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-col lg:flex-row gap-3">
    <div class="relative flex-1">
      <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400">search</span>
      <input id="quickSearch" type="text" placeholder="ค้นหาชื่อ/Username/แผนก…" class="w-full border rounded-lg pl-10 pr-3 py-2">
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
              while ($v = $visible_result->fetch_assoc()) $visible_names[] = $department_map[$v['to_department_id']] ?? '-';
              $visible_string = count($visible_names) ? implode(', ', $visible_names) : '-';

              $status_tag = $user['active'] ? '<span class="chip chip-green">active</span>' : '<span class="chip chip-red">suspended</span>';
              $role_tag = $user['role']==='admin' ? '<span class="chip chip-indigo">admin</span>'
                        : ($user['role']==='manager' ? '<span class="chip chip-yellow">manager</span>'
                        : '<span class="chip">field</span>');
              $initials = mb_substr($user['name'],0,2,'UTF-8');
            ?>
            <tr data-role="<?= htmlspecialchars($user['role']) ?>" data-status="<?= $user['active'] ? 'active' : 'suspended' ?>" class="hover:bg-gray-50">
              <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                  <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                  <div>
                    <div class="font-medium"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="text-xs text-gray-500">ID: <?= (int)$user['id'] ?></div>
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
                <a href="?toggle=<?= $user['id'] ?>" class="btn-icon lock ml-1" title="<?= $user['active']?'บล็อก':'ปลดบล็อก' ?>">
                  <?php if ($user['active']): ?>
                    <i class="fa-solid fa-lock"></i>
                  <?php else: ?>
                    <i class="fa-solid fa-lock-open"></i>
                  <?php endif; ?>
                </a>
                <a href="?delete=<?= $user['id'] ?>" onclick="return confirm('ลบผู้ใช้นี้หรือไม่?')" class="btn-icon delete ml-1" title="ลบ">
                  <i class="fa-solid fa-trash"></i>
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script>
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
      paginate: { previous: "ก่อนหน้า", next: "ถัดไป" },
      zeroRecords: "ไม่พบข้อมูล",
      infoEmpty: "ไม่มีข้อมูล",
      infoFiltered: "(กรองจากทั้งหมด _MAX_ แถว)"
    },
    dom: 't<"flex items-center justify-between mt-3"lip>',
    createdRow: function(row, data, dataIndex) {
      // เพิ่ม cursor pointer
      $(row).addClass('cursor-pointer');

      // เมื่อคลิกแถว ให้ไปหน้าแก้ไข
      $(row).on('click', function(e) {
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

  $('#quickSearch').on('keyup change', function() {
    dt.search(this.value).draw();
  });

  $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
    const roleF = $('#roleFilter').val();
    const statF = $('#statusFilter').val();
    const node  = dt.row(dataIndex).node();
    const rRole = node.getAttribute('data-role');
    const rStat = node.getAttribute('data-status');
    if (roleF && rRole !== roleF) return false;
    if (statF && rStat !== statF) return false;
    return true;
  });

  $('#roleFilter,#statusFilter').on('change', () => dt.draw());

  document.getElementById('exportCsv').addEventListener('click', () => {
    let headers = ["ชื่อ","Username","บทบาท","แผนก","สิทธิ์มองเห็น","สถานะ"];
    let rows = [];
    dt.rows({search:'applied'}).every(function() {
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
    let blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url; a.download = 'users_export.csv'; a.click();
    URL.revokeObjectURL(url);
  });

  // เพิ่มโค้ด toggle panel สำหรับเพิ่มสมาชิก
  document.getElementById('toggleAddUser').addEventListener('click', function() {
    const panel = document.getElementById('addUserPanel');
    panel.classList.toggle('hidden');
  });
</script>
