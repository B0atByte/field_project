<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit;
}
include '../config/db.php';
require_once '../includes/csrf.php';

$current_user_id = $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role'];
$current_user_can_delete = $_SESSION['user']['can_delete_jobs'] ?? 0;

/* ====== ลบงานทั้งหมดตามสิทธิ์ Admin ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all']) && $current_user_role === 'admin') {
  requireCsrfToken();
  $password = $_POST['password'] ?? '';
  $delete_type = $_POST['delete_type'] ?? 'all';
  $delete_start = $_POST['delete_start'] ?? '';
  $delete_end = $_POST['delete_end'] ?? '';

  $stmt_pass = $conn->prepare("SELECT password FROM users WHERE id = ?");
  $stmt_pass->bind_param("i", $current_user_id);
  $stmt_pass->execute();
  $stmt_pass->bind_result($hashed);
  $stmt_pass->fetch();
  $stmt_pass->close();

  if (password_verify($password, $hashed)) {
    // สร้าง WHERE clause และ parameters แบบปลอดภัย
    $where_parts = [];
    $params = [];
    $param_types = "";

    // เงื่อนไข status
    if ($delete_type === 'pending') {
      $where_parts[] = "(status != 'completed' OR status IS NULL)";
    } elseif ($delete_type === 'completed') {
      $where_parts[] = "status = 'completed'";
    }

    // เงื่อนไข วันที่
    if ($delete_start) {
      $where_parts[] = "DATE(created_at) >= ?";
      $params[] = $delete_start;
      $param_types .= "s";
    }
    if ($delete_end) {
      $where_parts[] = "DATE(created_at) <= ?";
      $params[] = $delete_end;
      $param_types .= "s";
    }

    // สร้าง WHERE clause
    $where_clause = count($where_parts) > 0 ? "WHERE " . implode(" AND ", $where_parts) : "";

    // เริ่ม transaction เพื่อเพิ่มประสิทธิภาพ
    $conn->begin_transaction();

    try {
      // นับจำนวนที่จะลบก่อน
      $sql_count = "SELECT COUNT(*) as cnt FROM jobs $where_clause";
      if (!empty($params)) {
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bind_param($param_types, ...$params);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $deleted_count = $result_count->fetch_assoc()['cnt'] ?? 0;
        $stmt_count->close();
      } else {
        $result_count = $conn->query($sql_count);
        $deleted_count = $result_count->fetch_assoc()['cnt'] ?? 0;
      }

      // บันทึก log แบบ batch โดยใช้ INSERT ... SELECT (เร็วกว่าวนลูปมาก)
      $sql_log = "INSERT INTO job_deletion_logs
                        (job_id, contract_number, product, location_info, assigned_to, status, deleted_by, deletion_type, job_data)
                        SELECT id, contract_number, product, location_info, assigned_to, status, ?, 'bulk',
                        CONCAT('{\"id\":', id, ',\"contract_number\":\"', IFNULL(contract_number,''), '\",\"product\":\"', IFNULL(product,''), '\"}')
                        FROM jobs $where_clause";

      if (!empty($params)) {
        $log_params = array_merge([$current_user_id], $params);
        $log_types = "i" . $param_types;
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param($log_types, ...$log_params);
        $stmt_log->execute();
        $stmt_log->close();
      } else {
        $sql_log_simple = "INSERT INTO job_deletion_logs
                            (job_id, contract_number, product, location_info, assigned_to, status, deleted_by, deletion_type, job_data)
                            SELECT id, contract_number, product, location_info, assigned_to, status, $current_user_id, 'bulk',
                            CONCAT('{\"id\":', id, ',\"contract_number\":\"', IFNULL(contract_number,''), '\"}')
                            FROM jobs";
        $conn->query($sql_log_simple);
      }

      // ลบ job_edit_logs ก่อน (foreign key)
      $sql_del_logs = "DELETE FROM job_edit_logs WHERE job_id IN (SELECT id FROM jobs $where_clause)";
      if (!empty($params)) {
        $stmt_del_logs = $conn->prepare($sql_del_logs);
        $stmt_del_logs->bind_param($param_types, ...$params);
        $stmt_del_logs->execute();
        $stmt_del_logs->close();
      } else {
        $conn->query($sql_del_logs);
      }

      // ลบงานตามเงื่อนไข
      $sql_del_jobs = "DELETE FROM jobs $where_clause";
      if (!empty($params)) {
        $stmt_del_jobs = $conn->prepare($sql_del_jobs);
        $stmt_del_jobs->bind_param($param_types, ...$params);
        $stmt_del_jobs->execute();
        $stmt_del_jobs->close();
      } else {
        $conn->query($sql_del_jobs);
      }

      // Commit transaction
      $conn->commit();

      if ($delete_type === 'pending') {
        $_SESSION['message'] = "ลบงานที่ยังไม่เสร็จเรียบร้อยแล้ว ($deleted_count รายการ)";
      } elseif ($delete_type === 'completed') {
        $_SESSION['message'] = "ลบงานที่เสร็จแล้วเรียบร้อยแล้ว ($deleted_count รายการ)";
      } else {
        $_SESSION['message'] = "ลบงานทั้งหมดเรียบร้อยแล้ว ($deleted_count รายการ)";
      }
    } catch (Exception $e) {
      $conn->rollback();
      $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
  } else {
    $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง";
  }
  header("Location: jobs.php");
  exit;
}

/* ====== ลบงานทีละรายการ ====== */
if (isset($_GET['delete']) && ($current_user_role === 'admin' || ($current_user_role === 'manager' && $current_user_can_delete))) {
  $delete_id = intval($_GET['delete']);

  // ดึงข้อมูลงานก่อนลบ เพื่อบันทึก log
  $stmt_job = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
  $stmt_job->bind_param("i", $delete_id);
  $stmt_job->execute();
  $result_job = $stmt_job->get_result();

  if ($job = $result_job->fetch_assoc()) {
    // บันทึก log
    $job_data = json_encode($job, JSON_UNESCAPED_UNICODE);

    $sql_log = "INSERT INTO job_deletion_logs
                    (job_id, contract_number, product, location_info, assigned_to, status,
                     deleted_by, deletion_type, job_data)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'manual', ?)";

    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->bind_param(
      "isssiiss",
      $job['id'],
      $job['contract_number'],
      $job['product'],
      $job['location_info'],
      $job['assigned_to'],
      $job['status'],
      $current_user_id,
      $job_data
    );
    $stmt_log->execute();
    $stmt_log->close();
  }
  $stmt_job->close();

  // ลบงาน
  $stmt_del_logs = $conn->prepare("DELETE FROM job_edit_logs WHERE job_id = ?");
  $stmt_del_logs->bind_param("i", $delete_id);
  $stmt_del_logs->execute();
  $stmt_del_logs->close();

  $stmt_del_job = $conn->prepare("DELETE FROM jobs WHERE id = ?");
  $stmt_del_job->bind_param("i", $delete_id);
  $stmt_del_job->execute();
  $stmt_del_job->close();

  $_SESSION['message'] = "ลบงานรหัส $delete_id เรียบร้อยแล้ว";
  header("Location: jobs.php");
  exit;
}

/* ====== รับค่าเริ่มต้นจาก URL (ใช้แสดงค่าในฟอร์ม) ====== */
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$submitted_status = $_GET['submitted_status'] ?? '';
$submitted_start = $_GET['submitted_start'] ?? '';
$submitted_end = $_GET['submitted_end'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<?php include '../components/header.php'; ?>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.tailwindcss.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  :root {
    --primary-color: #0f172a;
    /* Slate 900 */
    --accent-color: #2563eb;
    /* Blue 600 */
    --bg-color: #f8fafc;
    /* Slate 50 */
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    /* Slate 200 */
    --text-primary: #1e293b;
    /* Slate 800 */
    --text-secondary: #64748b;
    /* Slate 500 */
  }

  body {
    background-color: var(--bg-color);
    font-family: 'Sarabun', 'Inter', sans-serif;
    color: var(--text-primary);
  }

  .main-content {
    margin-left: 16rem;
    /* Match sidebar width */
    padding: 1.5rem;
  }

  /* Header */
  .page-header {
    background: white;
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem 2rem;
    margin: -1.5rem -1.5rem 1.5rem -1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .page-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .page-subtitle {
    color: var(--text-secondary);
    font-size: 0.875rem;
    margin-top: 0.25rem;
  }

  /* Filters */
  .filter-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  }

  .form-label {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    display: block;
    font-size: 0.875rem;
  }

  .form-input,
  .form-select {
    border-color: var(--border-color) !important;
    border-radius: 6px !important;
    font-size: 0.875rem !important;
  }

  .form-input:focus,
  .form-select:focus {
    border-color: var(--accent-color) !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1) !important;
    outline: none;
  }

  /* Buttons */
  .btn-action {
    background-color: var(--accent-color);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s;
  }

  .btn-action:hover {
    background-color: #1d4ed8;
  }

  .btn-secondary-action {
    background-color: white;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .btn-secondary-action:hover {
    background-color: var(--bg-color);
  }

  /* Table */
  .table-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  }

  #jobsTable thead th {
    background-color: var(--bg-color);
    color: var(--primary-color);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border-color);
  }

  #jobsTable tbody tr {
    border-bottom: 1px solid var(--border-color);
  }

  #jobsTable tbody tr:hover {
    background-color: #f1f5f9;
    /* Slate 100 */
    transform: none;
  }

  .dataTables_wrapper .dataTables_length select,
  .dataTables_wrapper .dataTables_filter input {
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0.4rem;
  }

  /* Table Actions */
  .btn-icon {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s;
    text-decoration: none;
    border: 1px solid transparent;
  }

  .btn-icon:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  }

  .btn-icon-primary {
    background-color: #eff6ff;
    color: var(--accent-color);
    border-color: #dbeafe;
  }

  .btn-icon-primary:hover {
    background-color: var(--accent-color);
    color: white;
    border-color: var(--accent-color);
  }

  .btn-icon-warning {
    background-color: #fefce8;
    color: #ca8a04;
    border-color: #fef08a;
  }

  .btn-icon-warning:hover {
    background-color: #fbbf24;
    color: white;
    border-color: #fbbf24;
  }

  .btn-icon-danger {
    background-color: #fef2f2;
    color: #dc2626;
    border-color: #fee2e2;
  }

  .btn-icon-danger:hover {
    background-color: #dc2626;
    color: white;
    border-color: #dc2626;
  }

  /* SweetAlert Override */
  .swal2-popup {
    border-radius: 12px !important;
  }
</style>

<body class="min-h-screen">
  <?php include '../components/sidebar.php'; ?>

  <main class="main-content">

    <!-- Header Section - เอาเมนูซ้ำออก เหลือเพียงชุดเดียว -->
    <div class="page-header">
      <div>
        <h1 class="page-title">
          <i class="fas fa-tasks text-blue-600"></i>
          รายการงานทั้งหมด
        </h1>
        <p class="page-subtitle">จัดการและติดตามงานทั้งหมดในระบบ</p>
      </div>

      <div class="flex gap-3">
        <a href="<?= $current_user_role === 'manager' ? '../dashboard/manager.php' : '../dashboard/admin.php' ?>"
          class="btn-secondary-action">
          <i class="fas fa-arrow-left"></i>
          กลับแดชบอร์ด
        </a>

        <?php if ($current_user_role === 'admin'): ?>
          <button onclick="confirmDeleteAll()" class="btn-action bg-red-600 hover:bg-red-700">
            <i class="fas fa-trash-alt"></i>
            ลบงาน
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
      <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
        <i class="fas fa-filter text-blue-600"></i>
        ตัวกรองข้อมูล
      </h3>

      <form id="filtersForm" method="get" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- วันที่เริ่มต้น -->
        <div>
          <label class="form-label">
            จากวันที่
          </label>
          <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full form-input">
        </div>

        <!-- วันที่สิ้นสุด -->
        <div>
          <label class="form-label">
            ถึงวันที่
          </label>
          <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full form-input">
        </div>

        <!-- ผู้รับงาน -->
        <div>
          <label class="form-label">
            ผู้รับงาน
          </label>
          <select name="assigned_to" class="w-full form-select">
            <option value="">ทั้งหมด</option>
            <?php
            $users = $conn->query("SELECT id, name FROM users WHERE role = 'field' ORDER BY name ASC");
            while ($u = $users->fetch_assoc()): ?>
              <option value="<?= $u['id'] ?>" <?= $assigned_to == $u['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- สถานะการส่งงาน -->
        <div>
          <label class="form-label">
            สถานะการส่งงาน
          </label>
          <select name="submitted_status" class="w-full form-select">
            <option value="">ทั้งหมด</option>
            <option value="unsent" <?= $submitted_status === 'unsent' ? 'selected' : '' ?>>
              ยังไม่ส่งงาน
            </option>
            <option value="sent" <?= $submitted_status === 'sent' ? 'selected' : '' ?>>
              ส่งงานแล้ว
            </option>
          </select>
        </div>

        <!-- Action Buttons -->
        <div class="col-span-1 md:col-span-2 lg:col-span-4 flex flex-wrap gap-2 pt-2 border-t border-slate-100 mt-2">
          <button type="submit" class="btn-action">
            <i class="fas fa-search"></i>
            กรองข้อมูล
          </button>

          <button type="button" id="resetFilters" class="btn-secondary-action">
            <i class="fas fa-undo"></i>
            รีเซ็ต
          </button>

          <button type="button" id="exportBtn"
            class="btn-secondary-action text-green-600 border-green-200 hover:bg-green-50">
            <i class="fas fa-file-export"></i>
            ส่งออก Excel
          </button>

          <a href="export_word_from_excel.php"
            class="btn-secondary-action text-blue-600 border-blue-200 hover:bg-blue-50">
            <i class="fas fa-file-word"></i>
            Export Word (จาก Excel)
          </a>
        </div>
      </form>
    </div>

    <!-- Table Section -->
    <div class="table-container">
      <div class="overflow-x-auto">
        <table id="jobsTable" class="w-full text-sm">
          <thead>
            <tr>
              <th class="px-4 py-3 text-left">สัญญา</th>
              <th class="px-4 py-3 text-left">Product</th>
              <th class="px-4 py-3 text-left">บัตร ปชช.</th>
              <th class="px-4 py-3 text-left">ลูกค้า</th>
              <th class="px-4 py-3 text-left">จังหวัด</th>
              <th class="px-4 py-3 text-left">กำหนด</th>
              <th class="px-4 py-3 text-left">ลงเมื่อ</th>
              <th class="px-4 py-3 text-left">ผู้รับ</th>
              <th class="px-4 py-3 text-left">ลำดับ</th>
              <th class="px-4 py-3 text-left">สถานะ</th>
              <th class="px-4 py-3 text-left">ผล</th>
              <th class="px-4 py-3 text-left">แผนก</th>
              <th class="px-4 py-3 text-left">แก้ไขล่าสุด</th>
              <th class="px-4 py-3 text-left">ส่งงานเมื่อ</th>
              <th class="px-4 py-3 text-center">จัดการ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <!-- Data will be loaded by DataTables -->
          </tbody>
        </table>
      </div>
    </div>

  </main>

  <!-- Hidden Delete Form -->
  <form id="deleteAllForm" method="post" class="hidden">
    <?= csrfField() ?>
    <input type="hidden" name="delete_all" value="1">
    <input type="password" name="password" id="deletePassword">
    <input type="hidden" name="delete_type" id="deleteType">
    <input type="hidden" name="delete_start" id="deleteStart">
    <input type="hidden" name="delete_end" id="deleteEnd">
  </form>


  <script>
    /* ===== Utils ===== */
    function esc(s) {
      return (s ?? '').toString().replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    function isNew(createdAt) {
      if (!createdAt) return false;
      const d = new Date(createdAt.replace(' ', 'T'));
      if (isNaN(d.getTime())) return false;
      return (Date.now() - d.getTime()) <= 86400000;
    }

    /* ===== Delete All (SweetAlert) ===== */
    function confirmDeleteAll() {
      Swal.fire({
        title: '<i class="fas fa-trash-alt text-red-500"></i> ลบงานตามเงื่อนไข',
        html: `
      <div class="grid gap-4 text-left mt-4">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">
            <i class="fas fa-filter text-blue-500 mr-1"></i>
            ประเภทงาน
          </label>
          <select id="deleteTypeInput" class="w-full px-4 py-3 text-base border-2 border-slate-200 rounded-lg focus:border-blue-500 bg-white">
            <option value="pending">ลบเฉพาะงานที่ยังไม่เสร็จ</option>
            <option value="completed">ลบเฉพาะงานที่เสร็จแล้ว</option>
            <option value="all">ลบทั้งหมด</option>
          </select>
        </div>
        
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">
              <i class="fas fa-calendar-alt text-blue-500 mr-1"></i>
              จากวันที่ลงงาน
            </label>
            <input type="date" id="deleteStartInput" class="w-full px-4 py-3 text-base border-2 border-slate-200 rounded-lg focus:border-blue-500 bg-white">
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">
              <i class="fas fa-calendar-alt text-blue-500 mr-1"></i>
              ถึงวันที่ลงงาน
            </label>
            <input type="date" id="deleteEndInput" class="w-full px-4 py-3 text-base border-2 border-slate-200 rounded-lg focus:border-blue-500 bg-white">
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">
            <i class="fas fa-lock text-red-500 mr-1"></i>
            รหัสผ่านยืนยัน
          </label>
          <input type="password" id="deletePwdInput" placeholder="กรุณาใส่รหัสผ่านเพื่อยืนยัน" 
                 class="w-full px-4 py-3 text-base border-2 border-slate-200 rounded-lg focus:border-blue-500 bg-white">
        </div>
      </div>
    `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-trash-alt mr-2"></i>ลบงาน',
        confirmButtonColor: '#dc2626',
        cancelButtonText: '<i class="fas fa-times mr-2"></i>ยกเลิก',
        cancelButtonColor: '#6b7280',
        buttonsStyling: false,
        customClass: {
          confirmButton: 'btn-action mx-2',
          cancelButton: 'btn-secondary-action mx-2'
        },
        preConfirm: () => {
          const type = document.getElementById('deleteTypeInput').value;
          const pwd = document.getElementById('deletePwdInput').value;
          const start = document.getElementById('deleteStartInput').value;
          const end = document.getElementById('deleteEndInput').value;

          if (!pwd) {
            Swal.showValidationMessage('<i class="fas fa-exclamation-triangle text-red-500 mr-1"></i>กรุณาใส่รหัสผ่าน');
            return false;
          }

          document.getElementById('deletePassword').value = pwd;
          document.getElementById('deleteType').value = type;
          document.getElementById('deleteStart').value = start;
          document.getElementById('deleteEnd').value = end;
          document.getElementById('deleteAllForm').submit();
        }
      });
    }

    /* ====== Persistence Config ====== */
    const LS_KEY = 'jobsFiltersV1';
    const DT_STATE_ID = 'jobsTableState';

    function getFiltersFromForm() {
      return {
        start_date: $('input[name="start_date"]').val() || '',
        end_date: $('input[name="end_date"]').val() || '',
        assigned_to: $('select[name="assigned_to"]').val() || '',
        submitted_status: $('select[name="submitted_status"]').val() || '',
        submitted_start: $('input[name="submitted_start"]').val() || '',
        submitted_end: $('input[name="submitted_end"]').val() || ''
      };
    }

    function setFiltersToForm(f) {
      if (!f) return;
      $('input[name="start_date"]').val(f.start_date || '');
      $('input[name="end_date"]').val(f.end_date || '');
      $('select[name="assigned_to"]').val(f.assigned_to || '');
      $('select[name="submitted_status"]').val(f.submitted_status || '');
      $('input[name="submitted_start"]').val(f.submitted_start || '');
      $('input[name="submitted_end"]').val(f.submitted_end || '');
    }

    function saveFiltersToLocal(f) {
      localStorage.setItem(LS_KEY, JSON.stringify(f));
    }

    function loadFiltersFromLocal() {
      try { return JSON.parse(localStorage.getItem(LS_KEY) || '{}'); } catch { return {}; }
    }

    function syncURLWithFilters(f) {
      const params = new URLSearchParams(f);
      const q = $('#jobsTable').DataTable().search();
      if (q) params.set('q', q);
      const url = window.location.pathname + '?' + params.toString();
      window.history.replaceState({}, '', url);
    }

    /* ====== Main ====== */
    $(document).ready(function () {
      // 1) โหลดค่าจาก URL ถ้ามี; ไม่งั้นโหลดจาก localStorage
      const url = new URL(window.location.href);
      const fromURL = {
        start_date: url.searchParams.get('start_date') || '',
        end_date: url.searchParams.get('end_date') || '',
        assigned_to: url.searchParams.get('assigned_to') || '',
        submitted_status: url.searchParams.get('submitted_status') || '',
        submitted_start: url.searchParams.get('submitted_start') || '',
        submitted_end: url.searchParams.get('submitted_end') || ''
      };
      const hasURLFilters = Object.values(fromURL).some(v => v);
      const saved = loadFiltersFromLocal();
      setFiltersToForm(hasURLFilters ? fromURL : saved);

      // 2) สร้าง DataTable + เปิด stateSave ให้ search/paging/order ถูกจดจำ
      const table = $('#jobsTable').DataTable({
        processing: true,
        serverSide: true,
        stateSave: true,
        stateDuration: 0,
        language: {
          processing: '<div class="flex items-center justify-center gap-2"><i class="fas fa-spinner fa-spin text-blue-600"></i><span class="text-slate-600">กำลังโหลด...</span></div>',
          search: 'ค้นหา:',
          lengthMenu: 'แสดง _MENU_ รายการต่อหน้า',
          info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
          infoEmpty: 'ไม่มีข้อมูล',
          infoFiltered: '(กรองจากทั้งหมด _MAX_ รายการ)',
          paginate: {
            first: 'แรก',
            last: 'สุดท้าย',
            next: 'ถัดไป',
            previous: 'ก่อนหน้า'
          },
          emptyTable: 'ไม่มีข้อมูลในตาราง'
        },
        stateSaveParams: function (settings, data) {
          saveFiltersToLocal(getFiltersFromForm());
        },
        stateLoadParams: function (settings, data) {
          // ให้ DataTables restore search/paging/order เอง
        },
        ajax: {
          url: 'jobs_data.php',
          type: 'POST',
          data: d => {
            const f = getFiltersFromForm();
            d.start_date = f.start_date;
            d.end_date = f.end_date;
            d.assigned_to = f.assigned_to;
            d.submitted_status = f.submitted_status;
            d.submitted_start = f.submitted_start;
            d.submitted_end = f.submitted_end;
          }
        },
        columns: [
          {
            data: 'contract_number',
            render: (data, type, row) => {
              let html = `<div class="font-medium text-slate-700">${esc(data)}</div>`;
              if (isNew(row.created_at)) {
                html += '<span class="inline-flex items-center px-2 py-1 mt-1 text-xs font-bold text-white bg-red-600 rounded-full animate-pulse-soft">NEW</span>';
              }
              return html;
            }
          },
          {
            data: 'product',
            render: (data) => `<div class="text-slate-600">${esc(data)}</div>`
          },
          {
            data: 'customer_id_card',
            render: (data) => `<div class="font-mono text-slate-700">${esc(data)}</div>`
          },
          {
            data: 'location_info',
            render: (data) => `<div class="text-slate-600 max-w-xs truncate" title="${esc(data)}">${esc(data)}</div>`
          },
          {
            data: 'province',
            render: (data) => `<div class="text-slate-600">${esc(data)}</div>`
          },
          {
            data: 'due_date',
            render: (data) => `<div class="text-slate-700">${esc(data)}</div>`
          },
          {
            data: 'created_at',
            render: (data) => {
              if (!data) return '<span class="text-slate-400">-</span>';
              const parts = data.split(' ');
              const date = parts[0] || '';
              const time = parts[1] || '';
              return `
            <div class="text-sm">
              <div class="text-slate-900 font-medium">${esc(date)}</div>
              <div class="text-slate-500 text-xs mt-0.5">${esc(time)}</div>
            </div>
          `;
            }
          },
          {
            data: 'officer_name',
            render: (data) => `<div class="text-slate-700">${esc(data)}</div>`
          },
          {
            data: 'priority',
            render: (data) => {
              const priority = parseInt(data) || 0;
              let colorClass = 'bg-slate-100 text-slate-600';
              if (priority <= 3) colorClass = 'bg-red-100 text-red-700';
              else if (priority <= 7) colorClass = 'bg-yellow-100 text-yellow-700';
              else colorClass = 'bg-green-100 text-green-700';

              return `<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full ${colorClass}">${priority}</span>`;
            }
          },
          {
            data: 'submission_status',
            render: (data) => {
              const status = data || 'รอดำเนินการ';
              let badge = '';

              if (status === 'เสร็จสิ้น') {
                badge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full"><i class="fas fa-check mr-1"></i>เสร็จสิ้น</span>';
              } else if (status === 'งานตีกลับ') {
                badge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-full"><i class="fas fa-undo mr-1"></i>งานตีกลับ</span>';
              } else {
                badge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-orange-700 bg-orange-100 rounded-full"><i class="fas fa-clock mr-1"></i>รอดำเนินการ</span>';
              }
              return badge;
            }
          },
          {
            data: 'latest_result',
            render: (data) => `<div class="text-slate-600 max-w-xs truncate" title="${esc(data)}">${esc(data || '-')}</div>`
          },
          {
            data: 'department_name',
            render: (data) => `<div class="text-slate-600">${esc(data || '-')}</div>`
          },
          {
            data: 'updated_by_name',
            render: (data) => `<div class="text-slate-600">${esc(data || '-')}</div>`
          },
          {
        data: 'last_submitted_at',
        render: (data) => {
          if (!data || data === '-') {
            return '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-orange-700 bg-orange-100 rounded-lg">ยังไม่ส่ง</span>';
          }
          const parts = data.split(' ');
          const date = parts[0] || '';
          const time = parts[1] || '';
          return `
            <div class="text-sm">
              <div class="text-slate-900 font-medium">${esc(date)}</div>
              <div class="text-slate-500 text-xs mt-0.5">${esc(time)}</div>
            </div>
          `;
        }
      },
          {
            data: 'actions',
            orderable: false,
            searchable: false,
            render: (data, type, row) => {
              return `<div class="flex items-center justify-center gap-1">${data}</div>`;
            }
          }
        ],
        rowCallback: function (row, data) {
          // Row hover effect
          // New row highlight
          if (isNew(row.created_at)) {
            $(row).addClass('bg-orange-50'); // Subtle highlight for new items
          }

          // Click to view details
          $(row).on('click', function (e) {
            if ($(e.target).is('a') || $(e.target).closest('a').length) {
              return;
            }
            window.location.href = `../dashboard/job_result.php?id=${data.id}`;
          });
        },
        initComplete: function () {
          // ถ้า URL มี q ให้ใช้เป็นค่า search เริ่มต้น
          const q = url.searchParams.get('q');
          if (q) {
            table.search(q).draw(false);
          }
          // อัปเดต URL ให้ตรงกับสภาพปัจจุบัน
          syncURLWithFilters(getFiltersFromForm());

          // ปรับแต่ง DataTables UI
          $('.dataTables_length select').addClass('px-3 py-2 text-sm border-2 border-slate-200 rounded-lg focus:border-blue-500 bg-white');
          $('.dataTables_filter input').addClass('px-4 py-2 text-sm border-2 border-slate-200 rounded-lg focus:border-blue-500 bg-white ml-2');
          $('.dataTables_paginate .paginate_button').addClass('px-3 py-2 mx-1 text-sm border border-slate-200 rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-all duration-200');
          $('.dataTables_paginate .paginate_button.current').addClass('bg-blue-600 text-white border-blue-600 hover:bg-blue-700');
        }
      });

      // 3) เมื่อ submit ฟอร์ม: เซฟ -> รีโหลดตาราง -> อัปเดต URL
      $('#filtersForm').on('submit', function (e) {
        e.preventDefault();
        const f = getFiltersFromForm();
        saveFiltersToLocal(f);
        table.ajax.reload(null, true);
        syncURLWithFilters(f);

        // แสดงข้อความยืนยัน
        Swal.fire({
          icon: 'success',
          title: 'กรองข้อมูลสำเร็จ',
          text: 'ระบบได้ปรับปรุงข้อมูลตามเงื่อนไขที่กำหนดแล้ว',
          timer: 2000,
          showConfirmButton: false,
          toast: true,
          position: 'top-end'
        });
      });

      // 4) ปุ่มรีเซ็ต: ล้างฟิลด์ + ล้าง state DataTables + ล้าง localStorage
      $('#resetFilters').on('click', function () {
        Swal.fire({
          title: 'รีเซ็ตตัวกรอง',
          text: 'คุณต้องการล้างตัวกรองทั้งหมดหรือไม่?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: '<i class="fas fa-check mr-2"></i>ใช่, รีเซ็ต',
          cancelButtonText: '<i class="fas fa-times mr-2"></i>ยกเลิก',
          confirmButtonColor: '#3b82f6',
          cancelButtonColor: '#6b7280',
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn-action mx-2',
            cancelButton: 'btn-secondary-action mx-2'
          }
        }).then((result) => {
          if (result.isConfirmed) {
            localStorage.removeItem(LS_KEY);
            $('input[name="start_date"], input[name="end_date"], input[name="submitted_start"], input[name="submitted_end"]').val('');
            $('select[name="assigned_to"], select[name="submitted_status"]').val('');
            table.search('').state.clear();
            table.ajax.reload(null, true);
            history.replaceState({}, '', window.location.pathname);

            Swal.fire({
              icon: 'success',
              title: 'รีเซ็ตสำเร็จ',
              text: 'ตัวกรองทั้งหมดได้ถูกล้างแล้ว',
              timer: 2000,
              showConfirmButton: false,
              toast: true,
              position: 'top-end'
            });
          }
        });
      });

      // 5) เมื่อผู้ใช้พิมพ์ในกล่องค้นหา (ของ DataTables) ให้ sync URL/LocalStorage ด้วย
      $('#jobsTable').on('search.dt', function () {
        syncURLWithFilters(getFiltersFromForm());
      });

      // 6) ปุ่ม Export Excel – ใช้ตัวกรองปัจจุบัน
      $('#exportBtn').on('click', async function () {
        const f = getFiltersFromForm();
        const q = table.search();

        // สร้าง filter text สำหรับแสดงใน dialog
        let filterText = [];
        if (f.start_date) filterText.push(`จากวันที่: ${f.start_date}`);
        if (f.end_date) filterText.push(`ถึงวันที่: ${f.end_date}`);
        if (f.assigned_to) {
          const assignedName = $('select[name="assigned_to"] option:selected').text();
          filterText.push(`ผู้รับงาน: ${assignedName}`);
        }
        if (f.submitted_status) {
          const statusText = f.submitted_status === 'sent' ? 'ส่งงานแล้ว' : 'ยังไม่ส่งงาน';
          filterText.push(`สถานะ: ${statusText}`);
        }
        if (q) filterText.push(`คำค้นหา: ${q}`);

        const filterHtml = filterText.length > 0
          ? `<div class="text-left text-sm text-slate-600 mb-3">${filterText.map(t => `<div>• ${t}</div>`).join('')}</div>`
          : '<div class="text-slate-500 text-sm mb-3">ไม่มีตัวกรอง (ส่งออกทั้งหมด)</div>';

        // ดึงจำนวนจาก DataTable
        const recordsTotal = table.page.info().recordsDisplay;

        Swal.fire({
          title: '<i class="fas fa-file-excel text-green-500"></i> ส่งออก Excel',
          html: `
        <div class="text-left mt-4">
          <div class="text-sm font-semibold text-slate-700 mb-2">ตัวกรองที่ใช้:</div>
          ${filterHtml}
          <div class="bg-green-100 border border-green-300 rounded-lg p-4 text-center">
            <i class="fas fa-file-excel text-green-600 text-2xl mb-2"></i>
            <div class="font-semibold text-green-700">จะส่งออก <span class="text-2xl">${recordsTotal.toLocaleString()}</span> รายการ</div>
          </div>
        </div>
      `,
          showCancelButton: true,
          confirmButtonText: '<i class="fas fa-download mr-2"></i>ส่งออก Excel',
          confirmButtonColor: '#16a34a',
          cancelButtonText: '<i class="fas fa-times mr-2"></i>ยกเลิก',
          cancelButtonColor: '#6b7280',
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn-action bg-green-600 hover:bg-green-700 mx-2',
            cancelButton: 'btn-secondary-action mx-2'
          },
          preConfirm: () => {
            if (recordsTotal === 0) {
              Swal.showValidationMessage('<i class="fas fa-exclamation-triangle text-red-500 mr-1"></i>ไม่มีข้อมูลที่จะส่งออก');
              return false;
            }

            // สร้าง params จากตัวกรองปัจจุบัน
            const params = new URLSearchParams(f);
            if (q) params.set('q', q);

            window.location.href = 'export_jobs.php?' + params.toString();
            return false;
          }
        });
      });

      // 7) แสดงข้อความจาก session
      <?php if (isset($_SESSION['message'])): ?>
        Swal.fire({
          icon: 'success',
          title: 'สำเร็จ',
          text: <?= json_encode($_SESSION['message'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
          timer: 3000,
          showConfirmButton: false,
          toast: true,
          position: 'top-end'
        });
        <?php unset($_SESSION['message']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
          icon: 'error',
          title: 'เกิดข้อผิดพลาด',
          text: <?= json_encode($_SESSION['error'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
          confirmButtonColor: '#dc2626'
        });
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>
    });

    // เพิ่มฟังก์ชันสำหรับ responsive behavior
    $(window).on('resize', function () {
      $('#jobsTable').DataTable().columns.adjust();
    });

    // Keyboard shortcuts
    $(document).on('keydown', function (e) {
      // Ctrl/Cmd + F สำหรับค้นหา
      if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        $('.dataTables_filter input').focus();
      }

      // ESC สำหรับล้างการค้นหา
      if (e.key === 'Escape') {
        const searchInput = $('.dataTables_filter input');
        if (searchInput.val()) {
          searchInput.val('').trigger('input');
          $('#jobsTable').DataTable().search('').draw();
        }
      }
    });
  </script>

</body>

</html>