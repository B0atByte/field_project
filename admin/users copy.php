<?php
session_start();
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

/* ---------- เพิ่มแผนก ---------- */
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

/* ---------- แผนกทั้งหมด + map ---------- */
$departments   = $conn->query("SELECT * FROM departments");
$department_map = [];
$dept_result    = $conn->query("SELECT id, name FROM departments");
while ($row = $dept_result->fetch_assoc()) $department_map[$row['id']] = $row['name'];

/* ---------- เพิ่มผู้ใช้ใหม่ ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['username'], $_POST['password'], $_POST['role'], $_POST['department_id'])) {
    $name         = $_POST['name'];
    $username     = $_POST['username'];
    $password     = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role         = $_POST['role'];
    $department_id= $_POST['department_id'];
    $can_delete_jobs = isset($_POST['can_delete_jobs']) ? 1 : 0;

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
    $conn->query("DELETE FROM users WHERE id = $id");
    header("Location: users.php");
    exit;
}
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE users SET active = IF(active = 1, 0, 1) WHERE id = $id");
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

/* ---------- UI ---------- */
$page_title = "👤 จัดการผู้ใช้งาน";
include '../components/header.php';
include '../components/sidebar.php';
?>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<style>
  thead { background-color:#0f172a; color:#fff; }
  tbody tr:hover { background:#f8fafc; }
  .chip{display:inline-block;padding:.22rem .55rem;border-radius:9999px;font-size:.72rem;font-weight:600}
  .chip-green{background:#dcfce7;color:#166534}
  .chip-yellow{background:#fef9c3;color:#854d0e}
  .chip-red{background:#fee2e2;color:#991b1b}
  .chip-indigo{background:#e0e7ff;color:#3730a3}
  .btn-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:10px;border:1px solid #e5e7eb;background:#fff}
  .btn-icon:hover{background:#f3f4f6}
  .avatar{width:32px;height:32px;border-radius:9999px;background:#1e40af;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem}
  .toolbar .material-symbols-outlined{font-size:18px;vertical-align:middle}
</style>

<main class="p-6 md:ml-64 min-h-screen font-prompt bg-surface">
  <!-- Header + Toolbar -->
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl font-bold">ผู้ใช้งาน (Virtualized-ish)</h1>
      <p class="text-sm text-gray-500">จัดการผู้ใช้ ค้นหา กรอง และส่งออก</p>
    </div>
    <div class="toolbar flex items-center gap-2">
      <button id="toggleAddUser" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg shadow text-sm">
        <span class="material-symbols-outlined mr-1">person_add</span>เพิ่มผู้ใช้
      </button>
      <button id="exportCsv" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg shadow text-sm">
        <span class="material-symbols-outlined mr-1">file_download</span>นำออก CSV
      </button>
      <a href="../dashboard/admin.php" class="bg-gray-900 hover:bg-black text-white px-3 py-2 rounded-lg shadow text-sm">
        <span class="material-symbols-outlined mr-1">arrow_back</span>แดชบอร์ด
      </a>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow p-4">
      <div class="text-xs text-gray-500">ทั้งหมด</div>
      <div class="text-2xl font-bold"><?= $stats_total ?></div>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <div class="text-xs text-gray-500">ใช้งานอยู่</div>
      <div class="text-2xl font-bold text-green-600"><?= $stats_active ?></div>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <div class="text-xs text-gray-500">Manager</div>
      <div class="text-2xl font-bold text-indigo-600"><?= $stats_manager ?></div>
    </div>
    <div class="bg-white rounded-xl shadow p-4">
      <div class="text-xs text-gray-500">ถูกบล็อก</div>
      <div class="text-2xl font-bold text-red-600"><?= $stats_blocked ?></div>
    </div>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl shadow p-4 mb-6">
    <div class="flex flex-col lg:flex-row gap-3">
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
  </div>

  <!-- Add user panel (toggle) -->
  <div id="addUserPanel" class="bg-white rounded-xl shadow p-6 mb-6 hidden">
    <h2 class="text-lg font-semibold mb-4">เพิ่มผู้ใช้ใหม่</h2>
    <form method="post" class="grid gap-4 md:grid-cols-2">
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

    <div class="mt-6">
      <h3 class="font-semibold mb-2">เพิ่มแผนก</h3>
      <form method="post" class="flex gap-2 items-center">
        <input type="text" name="new_department" placeholder="ชื่อแผนกใหม่" class="border px-3 py-2 rounded w-full max-w-xs" required>
        <button type="submit" name="add_department" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
          เพิ่ม
        </button>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl shadow p-4">
    <div class="overflow-x-auto">
      <table id="usersTable" class="w-full text-sm">
        <thead>
          <tr>
            <th class="px-3 py-2">ผู้ใช้</th>
            <th class="px-3 py-2">Username</th>
            <th class="px-3 py-2">บทบาท</th>
            <th class="px-3 py-2">แผนก</th>
            <th class="px-3 py-2">สิทธิ์มองเห็น</th>
            <th class="px-3 py-2">สถานะ</th>
            <th class="px-3 py-2 text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($user = $users->fetch_assoc()): ?>
            <?php
              // รายการแผนกที่มองเห็น
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
                        : '<span class="chip chip-gray">field</span>');
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
                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-icon" title="แก้ไข"><span class="material-symbols-outlined">edit</span></a>
                <a href="?toggle=<?= $user['id'] ?>" class="btn-icon ml-1" title="<?= $user['active']?'บล็อก':'ปลดบล็อก' ?>">
                  <span class="material-symbols-outlined"><?= $user['active']?'lock':'lock_open_right' ?></span>
                </a>
                <a href="?delete=<?= $user['id'] ?>" onclick="return confirm('ลบผู้ใช้นี้หรือไม่?')" class="btn-icon ml-1" title="ลบ">
                  <span class="material-symbols-outlined">delete</span>
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
  // toggle add-user panel
  document.getElementById('toggleAddUser').addEventListener('click', ()=> {
    document.getElementById('addUserPanel').classList.toggle('hidden');
  });
  // show manager extra permissions
  document.getElementById('roleSelect')?.addEventListener('change', function(){
    document.getElementById('manager-permissions').classList.toggle('hidden', this.value!=='manager');
  });

  // DataTable
  const dt = $('#usersTable').DataTable({
    pageLength: 25,
    lengthMenu: [10,25,50,100],
    deferRender:true,
    scrollX:true,
    language: {
      search: "ค้นหา:",
      lengthMenu: "แสดง _MENU_ แถว",
      info: "แถว _START_–_END_ จาก _TOTAL_",
      paginate: { previous: "ก่อนหน้า", next: "ถัดไป" },
      zeroRecords: "ไม่พบข้อมูล", infoEmpty: "ไม่มีข้อมูล", infoFiltered: "(กรองจากทั้งหมด _MAX_ แถว)"
    },
    dom: 't<"flex items-center justify-between mt-3"lip>'
  });

  // quick search
  $('#quickSearch').on('keyup change', function(){ dt.search(this.value).draw(); });

  // custom filters by role/status
  $.fn.dataTable.ext.search.push(function(settings, data, dataIndex){
    const roleF = $('#roleFilter').val();
    const statF = $('#statusFilter').val();
    const node  = dt.row(dataIndex).node();
    const rRole = node.getAttribute('data-role');
    const rStat = node.getAttribute('data-status');
    if (roleF && rRole !== roleF) return false;
    if (statF && rStat !== statF) return false;
    return true;
  });
  $('#roleFilter,#statusFilter').on('change', ()=> dt.draw());

  // Export CSV (เฉพาะรายการที่กำลังแสดง)
  function toCSVRow(arr){return arr.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',');}
  document.getElementById('exportCsv').addEventListener('click', ()=> {
    const headers = ["ชื่อ","Username","บทบาท","แผนก","สิทธิ์มองเห็น","สถานะ"];
    const rows = [];
    dt.rows({search:'applied'}).every(function(){
      const tr = this.node();
      const tds = tr.querySelectorAll('td');
      rows.push([
        tds[0].innerText.trim().split('\n')[0],  // ชื่อ
        tds[1].innerText.trim(),                 // username
        tds[2].innerText.trim(),                 // role
        tds[3].innerText.trim(),                 // dept
        tds[4].innerText.trim(),                 // vis
        tds[5].innerText.trim()                  // status
      ]);
    });
    let csv = toCSVRow(headers) + '\n' + rows.map(toCSVRow).join('\n');
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href=url; a.download='users_export.csv'; a.click();
    URL.revokeObjectURL(url);
  });
</script>
