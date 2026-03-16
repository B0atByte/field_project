<?php
require_once __DIR__ . '/../includes/session_config.php';

// ปิดการแสดง Error เพื่อป้องกัน JSON Syntax Error (กรณีมี Warning)
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

include '../config/db.php';

$columns = [
  'contract_number',
  'product',
  'customer_id_card',
  'location_info',
  'province',
  'due_date',
  'created_at',
  'officer_name',
  'priority',
  'submission_status',
  'latest_result',
  'department_name',
  'updated_by_name',
  'last_submitted_at'
];

$draw   = intval($_POST['draw']);
$start  = intval($_POST['start']);
$length = intval($_POST['length']);
$search = $_POST['search']['value'] ?? '';
$order_col_index = intval($_POST['order'][0]['column'] ?? 0);
$order_dir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$order_col = $columns[$order_col_index] ?? 'created_at';

// ✅ Filters
$start_date        = $_POST['start_date'] ?? '';
$end_date          = $_POST['end_date'] ?? '';
$assigned_to       = $_POST['assigned_to'] ?? '';
$submitted_status  = $_POST['submitted_status'] ?? '';
$submitted_start   = $_POST['submitted_start'] ?? '';
$submitted_end     = $_POST['submitted_end'] ?? '';

// ✅ Save filters to session for use in job_result.php
$_SESSION['job_filters'] = [
  'start_date' => $start_date,
  'end_date' => $end_date,
  'assigned_to' => $assigned_to,
  'submitted_status' => $submitted_status,
  'submitted_start' => $submitted_start,
  'submitted_end' => $submitted_end
];

$where = "1=1";
$params = [];
$types = [];

// 🔒 Department visibility
$visible_dept_ids = [];

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  $user_id = $_SESSION['user']['id'];
  $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $stmt->bind_result($main_dept);
  $stmt->fetch();
  $stmt->close();

  if ($main_dept) $visible_dept_ids[] = $main_dept;

  $stmt = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_user_id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $visible_dept_ids[] = $row['to_department_id'];
  }
  $stmt->close();

  if (count($visible_dept_ids)) {
    $placeholders = implode(',', array_fill(0, count($visible_dept_ids), '?'));
    $where .= " AND j.department_id IN ($placeholders)";
    foreach ($visible_dept_ids as $id) {
      $params[] = $id;
      $types[] = 'i';
    }
  } else {
    echo json_encode([
      'draw' => $draw,
      'recordsTotal' => 0,
      'recordsFiltered' => 0,
      'data' => []
    ]);
    exit;
  }
}

// 🔍 Global search
if (!empty($search)) {
  $where .= " AND (
    j.contract_number LIKE ? OR 
    j.location_info LIKE ? OR 
    j.product LIKE ? OR 
    j.customer_id_card LIKE ? OR 
    j.plate LIKE ?
  )";
  $s = "%$search%";
  for ($i = 0; $i < 5; $i++) {
    $params[] = $s;
    $types[] = 's';
  }
}

// 🔍 Filters
if ($start_date) {
  $where .= " AND DATE(j.created_at) >= ?";
  $params[] = $start_date;
  $types[] = 's';
}
if ($end_date) {
  $where .= " AND DATE(j.created_at) <= ?";
  $params[] = $end_date;
  $types[] = 's';
}
if ($assigned_to !== '') {
  $where .= " AND j.assigned_to = ?";
  $params[] = $assigned_to;
  $types[] = 's';
}
if ($submitted_status === 'sent') {
  $where .= " AND EXISTS (SELECT 1 FROM job_logs jl WHERE jl.job_id = j.id)";
} elseif ($submitted_status === 'unsent') {
  $where .= " AND NOT EXISTS (SELECT 1 FROM job_logs jl WHERE jl.job_id = j.id)";
}
if ($submitted_start) {
  $where .= " AND EXISTS (
    SELECT 1 FROM job_logs jl 
    WHERE jl.job_id = j.id AND DATE(jl.created_at) >= ?
  )";
  $params[] = $submitted_start;
  $types[] = 's';
}
if ($submitted_end) {
  $where .= " AND EXISTS (
    SELECT 1 FROM job_logs jl 
    WHERE jl.job_id = j.id AND DATE(jl.created_at) <= ?
  )";
  $params[] = $submitted_end;
  $types[] = 's';
}

// 🔢 Count filtered
$count_sql = "SELECT COUNT(*) AS filtered FROM jobs j WHERE $where";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
  $count_stmt->bind_param(implode('', $types), ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result()->fetch_assoc();
$filtered_rows = $count_result['filtered'] ?? 0;
$count_stmt->close();

// 🔢 Count total
$total_sql = "SELECT COUNT(*) AS total FROM jobs j WHERE 1=1";
$total_params = [];
$total_types = '';
if (count($visible_dept_ids)) {
  $placeholders = implode(',', array_fill(0, count($visible_dept_ids), '?'));
  $total_sql .= " AND j.department_id IN ($placeholders)";
  foreach ($visible_dept_ids as $id) {
    $total_params[] = $id;
    $total_types .= 'i';
  }
}
$total_stmt = $conn->prepare($total_sql);
if (!empty($total_params)) {
  $total_stmt->bind_param($total_types, ...$total_params);
}
$total_stmt->execute();
$total_rows = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$total_stmt->close();

// 🔍 Main query
$sql = "SELECT
  j.*,
  (SELECT name FROM users WHERE id = j.assigned_to) AS officer_name,
  (SELECT name FROM users WHERE id = j.imported_by) AS imported_by_name,
  (SELECT name FROM users WHERE id = j.last_updated_by) AS updated_by_name,
  (SELECT name FROM departments WHERE id = j.department_id) AS department_name,
  (SELECT result FROM job_logs WHERE job_id = j.id ORDER BY id DESC LIMIT 1) AS latest_result,
  (SELECT MAX(created_at) FROM job_logs WHERE job_id = j.id) AS last_submitted_at,
  (SELECT COUNT(*) FROM job_logs WHERE job_id = j.id) AS submission_count
FROM jobs j
WHERE $where
ORDER BY $order_col $order_dir
LIMIT ?, ?";

$params[] = $start;
$params[] = $length;
$types[] = 'i';
$types[] = 'i';

$stmt = $conn->prepare($sql);
$stmt->bind_param(implode('', $types), ...$params);
$stmt->execute();
$res = $stmt->get_result();

// ✨ ทำให้ค่ากรองติดไปกับลิงก์ (ใช้ session ที่เราเพิ่งเก็บไว้)
$queryString = http_build_query($_SESSION['job_filters'] ?? []);

$data = [];
while ($row = $res->fetch_assoc()) {
  $job_id = intval($row['id']);
  $submission_count = (int)$row['submission_count'];

  // กำหนดสถานะการส่งงานตาม job_logs และ status field
  if ($row['status'] === 'returned') {
    $submission_status = 'งานตีกลับ';
  } else {
    $submission_status = $submission_count > 0 ? 'เสร็จสิ้น' : 'รอดำเนินการ';
  }

  $data[] = [
    'id'                  => $job_id,
    'contract_number'     => htmlspecialchars($row['contract_number']),
    'product'             => htmlspecialchars($row['product']),
    'customer_id_card'    => htmlspecialchars($row['customer_id_card']),
    'location_info'       => htmlspecialchars($row['location_info']),
    'province'            => htmlspecialchars($row['province']),
    'due_date'            => $row['due_date'],
    'created_at'          => date('Y-m-d H:i', strtotime($row['created_at'])),
    'officer_name'        => htmlspecialchars($row['officer_name'] ?? '-'),
    'priority'            => ['urgent' => '🔴 งานด่วนที่สุด', 'high' => '🟠 งานด่วน', 'normal' => '🟢 งานปกติ'][$row['priority']] ?? '🟢 งานปกติ',
    'submission_status'   => $submission_status,
    'latest_result'       => htmlspecialchars($row['latest_result'] ?? '-'),
    'department_name'     => htmlspecialchars($row['department_name'] ?? '-'),
    'updated_by_name'     => htmlspecialchars($row['updated_by_name'] ?? '-'),
    'last_submitted_at'   => $row['last_submitted_at'] ? date('Y-m-d H:i', strtotime($row['last_submitted_at'])) : '-',
    'actions' => '
      <div class="flex items-center justify-center gap-2">
        <a href="../dashboard/job_result.php?id=' . $job_id . '&' . $queryString . '" 
           class="btn-icon btn-icon-primary" title="ดูรายละเอียด">
           <i class="fas fa-eye"></i>
        </a>
        <a href="edit_job.php?id=' . $job_id . '&' . $queryString . '" 
           class="btn-icon btn-icon-warning" title="แก้ไข">
           <i class="fas fa-pencil-alt"></i>
        </a>
        <a href="jobs.php?delete=' . $job_id . '&' . $queryString . '" 
           onclick="return confirm(\'ยืนยันการลบงานนี้?\')" 
           class="btn-icon btn-icon-danger" title="ลบ">
           <i class="fas fa-trash-alt"></i>
        </a>
      </div>
    '
  ];
}

echo json_encode([
  'draw' => $draw,
  'recordsTotal' => $total_rows,
  'recordsFiltered' => $filtered_rows,
  'data' => $data
]);
