<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'field') {
  header("Location: ../index.php");
  exit;
}
include '../config/db.php';

$userId = $_SESSION['user']['id'];

// รับค่าการค้นหาและกรอง
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active'; // เพิ่ม: active, returned, all
$favorite_only = isset($_GET['favorite']) && $_GET['favorite'] == '1' ? true : false;

// เพิ่ม pagination
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? min(100, max(10, (int) $_GET['per_page'])) : 50;
$offset = ($page - 1) * $per_page;

// สร้าง WHERE conditions
$where_conditions = ["j.assigned_to = ?"];

// เพิ่มเงื่อนไขสถานะงาน
if ($status_filter === 'active') {
  // งานปกติ: ยังไม่เสร็จและไม่ถูกตีกลับ
  $where_conditions[] = "(j.status IS NULL OR (j.status <> 'completed' AND j.status <> 'returned'))";
} elseif ($status_filter === 'returned') {
  // งานที่ถูกตีกลับ
  $where_conditions[] = "j.status = 'returned'";
} else {
  // ทั้งหมด: แสดงทุกงานยกเว้นที่เสร็จแล้ว
  $where_conditions[] = "(j.status IS NULL OR j.status <> 'completed')";
}

$bind_types = "i";
$bind_values = [$userId];

// เพิ่มเงื่อนไขการค้นหา
if (!empty($search)) {
  $search_like = "%{$search}%";
  $where_conditions[] = "(j.contract_number LIKE ? OR j.location_info LIKE ? OR j.zone LIKE ? OR j.plate LIKE ? OR j.location_area LIKE ? OR j.province LIKE ?)";
  $bind_types .= "ssssss";
  $bind_values[] = $search_like;
  $bind_values[] = $search_like;
  $bind_values[] = $search_like;
  $bind_values[] = $search_like;
  $bind_values[] = $search_like;
  $bind_values[] = $search_like;
}

// เพิ่มเงื่อนไขความเร่งด่วน
if ($priority_filter !== 'all') {
  $where_conditions[] = "j.priority = ?";
  $bind_types .= "s";
  $bind_values[] = $priority_filter;
}

// เพิ่มเงื่อนไขงานโปรด
if ($favorite_only) {
  $where_conditions[] = "j.is_favorite = 1";
}

$where_sql = implode(" AND ", $where_conditions);

// นับจำนวนทั้งหมด
$count_sql = "SELECT COUNT(*) as total 
              FROM jobs j
              WHERE {$where_sql}";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($bind_types, ...$bind_values);
$stmt->execute();
$total_jobs = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$total_pages = ceil($total_jobs / $per_page);

// Query jobs พร้อม LIMIT และ FORCE INDEX
$sql = "SELECT j.*, u.name AS imported_by_name
        FROM jobs j
        FORCE INDEX (idx_assigned_to)
        LEFT JOIN users u ON j.imported_by = u.id
        WHERE {$where_sql}
        ORDER BY j.is_favorite DESC, j.due_date ASC, j.id DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$bind_types_with_limit = $bind_types . "ii";
$bind_values_with_limit = array_merge($bind_values, [$per_page, $offset]);
$stmt->bind_param($bind_types_with_limit, ...$bind_values_with_limit);
$stmt->execute();
$res = $stmt->get_result();
$jobs = [];
while ($r = $res->fetch_assoc())
  $jobs[] = $r;

/* ---------- Helpers ---------- */
function daysDiffBadge($due)
{
  if ($due === null)
    return '<span class="status-chip neutral">ไม่ระบุ</span>';
  $raw = trim((string) $due);
  if ($raw === '')
    return '<span class="status-chip neutral">ไม่ระบุ</span>';
  if (preg_match('/^-?\d+$/', $raw)) {
    $d = (int) $raw;
  } else {
    $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y', 'm/d/Y'];
    $dueDate = null;
    foreach ($formats as $f) {
      $dt = DateTime::createFromFormat($f, $raw);
      if ($dt && $dt->format($f) === $raw) {
        $dueDate = $dt;
        break;
      }
    }
    if (!$dueDate) {
      $ts = strtotime($raw);
      if ($ts !== false)
        $dueDate = (new DateTime())->setTimestamp($ts);
    }
    if (!$dueDate)
      return '<span class="status-chip neutral">ไม่ระบุ</span>';
    $d = (int) (new DateTime())->diff($dueDate)->format('%r%a');
  }

  if ($d < 0)
    return '<span class="status-chip danger">เกิน ' . abs($d) . ' วัน</span>';
  if ($d === 0)
    return '<span class="status-chip warning">วันนี้</span>';
  if ($d <= 3)
    return '<span class="status-chip caution">อีก ' . $d . ' วัน</span>';
  return '<span class="status-chip success">อีก ' . $d . ' วัน</span>';
}

function priorityChip($p)
{
  $p = $p ?: 'normal';
  if ($p === 'urgent')
    return '<span class="priority-chip urgent">ด่วนที่สุด</span>';
  if ($p === 'high')
    return '<span class="priority-chip high">ด่วน</span>';
  return '<span class="priority-chip normal">ปกติ</span>';
}

function isNewJob($created)
{
  return $created ? (time() - strtotime($created) <= 24 * 3600) : false;
}

// Helper function สำหรับสร้าง URL พร้อม query parameters
function buildUrl($params = [])
{
  global $search, $priority_filter, $status_filter, $favorite_only, $per_page;

  $defaults = [
    'search' => $search,
    'priority' => $priority_filter,
    'status' => $status_filter,
    'favorite' => $favorite_only ? '1' : '0',
    'per_page' => $per_page
  ];

  $merged = array_merge($defaults, $params);

  // ลบค่าที่เป็นค่าเริ่มต้น
  if (empty($merged['search']))
    unset($merged['search']);
  if ($merged['priority'] === 'all')
    unset($merged['priority']);
  if ($merged['status'] === 'active')
    unset($merged['status']); // active เป็นค่าเริ่มต้น
  if ($merged['favorite'] === '0')
    unset($merged['favorite']);

  return '?' . http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <title>งานของฉัน (<?= number_format($total_jobs) ?>) | ระบบจัดการงาน</title>
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes, maximum-scale=5.0" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    * {
      -webkit-tap-highlight-color: transparent;
    }

    body {
      font-family: 'Sarabun', sans-serif;
      background: #ffffff;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Header styling */
    .main-header {
      position: sticky;
      top: 0;
      z-index: 50;
      background: #ffffff;
      border-bottom: 1px solid #e5e7eb;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    /* Floating Action Button */
    .fab-container {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 40;
    }

    .fab-main {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
      cursor: pointer;
      transition: all 0.3s ease;
      border: none;
    }

    .fab-main:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
    }

    .fab-main:active {
      transform: scale(0.95);
    }

    .fab-menu {
      position: absolute;
      bottom: 70px;
      right: 0;
      display: flex;
      flex-direction: column;
      gap: 12px;
      opacity: 0;
      transform: translateY(20px);
      pointer-events: none;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .fab-menu.active {
      opacity: 1;
      transform: translateY(0);
      pointer-events: all;
    }

    .fab-item {
      display: flex;
      align-items: center;
      gap: 12px;
      background: white;
      padding: 12px 16px;
      border-radius: 50px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      cursor: pointer;
      transition: all 0.2s ease;
      white-space: nowrap;
      border: 1px solid #e5e7eb;
    }

    .fab-item:hover {
      transform: translateX(-4px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .fab-item-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    /* Collapsible menu */
    .menu-container {
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .menu-collapsed {
      max-height: 0;
      opacity: 0;
    }

    .menu-expanded {
      max-height: 1000px;
      opacity: 1;
    }

    /* Status chips */
    .status-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      white-space: nowrap;
    }

    .status-chip.success {
      background: #ecfdf5;
      color: #059669;
      border: 1px solid #d1fae5;
    }

    .status-chip.warning {
      background: #fef3c7;
      color: #d97706;
      border: 1px solid #fde68a;
    }

    .status-chip.caution {
      background: #ffedd5;
      color: #ea580c;
      border: 1px solid #fed7aa;
    }

    .status-chip.danger {
      background: #fee2e2;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    .status-chip.neutral {
      background: #f3f4f6;
      color: #6b7280;
      border: 1px solid #e5e7eb;
    }

    /* Priority chips */
    .priority-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      white-space: nowrap;
    }

    .priority-chip.urgent {
      background: #fef2f2;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    .priority-chip.high {
      background: #fff7ed;
      color: #ea580c;
      border: 1px solid #fed7aa;
    }

    .priority-chip.normal {
      background: #f0fdf4;
      color: #16a34a;
      border: 1px solid #bbf7d0;
    }

    /* Card styling */
    .job-card {
      background: #ffffff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      transition: all 0.3s ease;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .job-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
      border-color: #d1d5db;
    }

    /* Table styling */
    .table-view {
      background: #ffffff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      overflow: hidden;
    }

    .table-view table {
      width: 100%;
      border-collapse: collapse;
    }

    .table-view th {
      background: #f9fafb;
      color: #374151;
      padding: 16px;
      text-align: left;
      font-weight: 600;
      font-size: 0.875rem;
      border-bottom: 2px solid #e5e7eb;
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .table-view td {
      padding: 16px;
      border-bottom: 1px solid #f3f4f6;
      font-size: 0.875rem;
    }

    .table-view tbody tr {
      transition: all 0.2s ease;
    }

    .table-view tbody tr:hover {
      background: #f9fafb;
    }

    .table-view tbody tr:last-child td {
      border-bottom: none;
    }

    /* Button styling */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 12px;
      font-size: 0.875rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s ease;
      border: none;
      cursor: pointer;
      white-space: nowrap;
    }

    .btn:active {
      transform: scale(0.98);
    }

    .btn-primary {
      background: #3b82f6;
      color: white;
    }

    .btn-primary:hover {
      background: #2563eb;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    /* Favorite button */
    .fav-btn {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      transition: all 0.2s ease;
      border: 2px solid;
      cursor: pointer;
      flex-shrink: 0;
    }

    .fav-btn:active {
      transform: scale(0.9);
    }

    .fav-btn.active {
      background: #fef3c7;
      border-color: #f59e0b;
      color: #d97706;
    }

    .fav-btn.inactive {
      background: #f9fafb;
      border-color: #e5e7eb;
      color: #9ca3af;
    }

    /* View toggle */
    .view-toggle {
      display: inline-flex;
      background: #f3f4f6;
      border-radius: 12px;
      padding: 4px;
      gap: 4px;
    }

    .view-toggle button {
      padding: 8px 16px;
      border-radius: 8px;
      border: none;
      background: transparent;
      color: #6b7280;
      font-size: 0.875rem;
      font-weight: 600;
      transition: all 0.2s ease;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .view-toggle button.active {
      background: #ffffff;
      color: #3b82f6;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* Pagination */
    .pagination {
      display: flex;
      gap: 8px;
      align-items: center;
      justify-content: center;
      margin-top: 32px;
      flex-wrap: wrap;
    }

    .pagination a,
    .pagination span {
      padding: 8px 16px;
      border-radius: 10px;
      border: 1px solid #e5e7eb;
      background: #ffffff;
      color: #6b7280;
      text-decoration: none;
      font-size: 0.875rem;
      font-weight: 600;
      transition: all 0.2s ease;
      min-width: 44px;
      text-align: center;
    }

    .pagination a:hover {
      background: #f9fafb;
      border-color: #3b82f6;
      color: #3b82f6;
    }

    .pagination a:active {
      transform: scale(0.95);
    }

    .pagination .current {
      background: #3b82f6;
      color: white;
      border-color: #3b82f6;
    }

    .pagination .disabled {
      opacity: 0.4;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* Search input */
    .search-input {
      background: #f9fafb;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      padding: 12px 44px 12px 16px;
      font-size: 0.875rem;
      transition: all 0.2s ease;
      width: 100%;
      font-weight: 500;
    }

    .search-input:focus {
      outline: none;
      border-color: #3b82f6;
      background: #ffffff;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .search-input::placeholder {
      color: #9ca3af;
    }

    /* Filter buttons */
    .filter-btn {
      padding: 8px 16px;
      border-radius: 10px;
      border: 1px solid #e5e7eb;
      background: #ffffff;
      color: #6b7280;
      font-size: 0.8125rem;
      font-weight: 600;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .filter-btn:hover {
      background: #f9fafb;
      border-color: #d1d5db;
    }

    .filter-btn.active {
      background: #3b82f6;
      border-color: #3b82f6;
      color: white;
    }

    /* Active filter badge */
    .active-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 12px;
    }

    .filter-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      font-size: 0.8125rem;
      font-weight: 600;
      color: #1e40af;
    }

    .filter-badge button {
      display: flex;
      align-items: center;
      background: none;
      border: none;
      cursor: pointer;
      color: #1e40af;
      padding: 0;
    }

    .filter-badge button:hover {
      color: #1e3a8a;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
      .fab-container {
        bottom: 16px;
        right: 16px;
      }

      .fab-main {
        width: 52px;
        height: 52px;
      }

      .fab-item {
        padding: 10px 14px;
        font-size: 0.875rem;
      }

      .fab-item-icon {
        width: 32px;
        height: 32px;
      }

      .job-card {
        padding: 16px !important;
      }

      .fav-btn {
        width: 40px;
        height: 40px;
        font-size: 1.125rem;
      }

      .status-chip,
      .priority-chip {
        font-size: 0.7rem;
        padding: 3px 10px;
      }

      .table-view {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .table-view table {
        min-width: 800px;
      }

      .table-view th,
      .table-view td {
        padding: 12px 8px;
        font-size: 0.8125rem;
      }

      .view-toggle button {
        padding: 6px 12px;
        font-size: 0.8125rem;
      }

      .btn {
        padding: 8px 16px;
        font-size: 0.8125rem;
      }
    }

    /* Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #f3f4f6;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
      background: #d1d5db;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #9ca3af;
    }

    /* Animations */
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.7;
      }
    }

    .animate-slide-in {
      animation: slideIn 0.3s ease-out;
    }

    .animate-pulse-custom {
      animation: pulse 2s infinite;
    }

    /* Toast */
    .toast {
      position: fixed;
      top: 24px;
      right: 24px;
      z-index: 60;
      background: white;
      border-radius: 12px;
      padding: 16px 20px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
      border: 1px solid #e5e7eb;
      display: flex;
      align-items: center;
      gap: 12px;
      max-width: 400px;
      transform: translateX(calc(100% + 24px));
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .toast.show {
      transform: translateX(0);
    }

    .toast-success {
      border-left: 4px solid #10b981;
    }

    .toast-error {
      border-left: 4px solid #ef4444;
    }

    .toast-info {
      border-left: 4px solid #3b82f6;
    }
  </style>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            'sans': ['Sarabun', 'system-ui', 'sans-serif'],
          }
        }
      }
    }
  </script>
</head>

<body class="min-h-screen bg-white">
  <div class="max-w-7xl mx-auto">

    <!-- Main Header -->
    <header class="main-header">
      <div class="px-4 py-4">
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <div
              class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                </path>
              </svg>
            </div>
            <div class="min-w-0">
              <h1 class="text-xl font-bold text-gray-900 truncate">งานของฉัน</h1>
              <p class="text-sm text-gray-500 font-medium">
                <?php if (!empty($search) || $priority_filter !== 'all' || $favorite_only): ?>
                  พบ <?= number_format($total_jobs) ?> งาน
                <?php else: ?>
                  ทั้งหมด <?= number_format($total_jobs) ?> งาน
                <?php endif; ?>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <!-- View Toggle -->
            <div class="view-toggle">
              <button id="btnCards" class="active" type="button">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                  </path>
                </svg>
                <span class="hidden sm:inline">การ์ด</span>
              </button>
              <button id="btnTable" type="button">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                <span class="hidden sm:inline">ตาราง</span>
              </button>
            </div>

            <!-- Menu Toggle -->
            <button id="menuToggle"
              class="p-2.5 rounded-xl bg-gray-50 hover:bg-gray-100 border border-gray-200 transition-all">
              <svg id="menuIcon" class="w-5 h-5 text-gray-600 transition-transform duration-300" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- Collapsible Menu -->
        <div id="collapsibleMenu" class="menu-container menu-collapsed">
          <div class="pt-4 space-y-4">

            <!-- Search Form -->
            <form method="GET" action="" id="searchForm">
              <input type="hidden" name="page" value="1">
              <input type="hidden" name="per_page" value="<?= $per_page ?>">
              <input type="hidden" name="priority" value="<?= htmlspecialchars($priority_filter) ?>">
              <input type="hidden" name="favorite" value="<?= $favorite_only ? '1' : '0' ?>">

              <div class="relative mb-3">
                <input id="searchInput" name="search" type="text" value="<?= htmlspecialchars($search) ?>"
                  placeholder="ค้นหาด้วย สัญญา / ชื่อ / โซน / ทะเบียน / ที่อยู่ / จังหวัด..." class="search-input">
                <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-2">
                  <?php if (!empty($search)): ?>
                    <a href="<?= buildUrl(['search' => '', 'page' => 1]) ?>"
                      class="cursor-pointer text-gray-400 hover:text-gray-600 p-1" title="ล้างการค้นหา">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                      </svg>
                    </a>
                  <?php endif; ?>
                  <button type="submit" class="text-blue-500 hover:text-blue-600 p-1" title="ค้นหา">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                  </button>
                </div>
              </div>
            </form>

            <!-- Active Filters -->
            <?php if (!empty($search) || $priority_filter !== 'all' || $favorite_only): ?>
              <div class="active-filters">
                <?php if (!empty($search)): ?>
                  <div class="filter-badge">
                    <span>ค้นหา: "<?= htmlspecialchars($search) ?>"</span>
                    <a href="<?= buildUrl(['search' => '', 'page' => 1]) ?>">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                      </svg>
                    </a>
                  </div>
                <?php endif; ?>

                <?php if ($priority_filter !== 'all'): ?>
                  <div class="filter-badge">
                    <span>
                      <?php
                      $pri_labels = ['normal' => 'ปกติ', 'high' => 'ด่วน', 'urgent' => 'ด่วนที่สุด'];
                      echo 'ความเร่งด่วน: ' . ($pri_labels[$priority_filter] ?? $priority_filter);
                      ?>
                    </span>
                    <a href="<?= buildUrl(['priority' => 'all', 'page' => 1]) ?>">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                      </svg>
                    </a>
                  </div>
                <?php endif; ?>

                <?php if ($favorite_only): ?>
                  <div class="filter-badge">
                    <span>งานโปรดเท่านั้น</span>
                    <a href="<?= buildUrl(['favorite' => '0', 'page' => 1]) ?>">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                      </svg>
                    </a>
                  </div>
                <?php endif; ?>

                <a href="?" class="filter-badge" style="background: #fef2f2; border-color: #fecaca; color: #dc2626;">
                  <span>ล้างตัวกรองทั้งหมด</span>
                </a>
              </div>
            <?php endif; ?>

            <!-- Status Filter -->
            <?php
            // นับจำนวนงานที่ถูกตีกลับ
            $count_returned_sql = "SELECT COUNT(*) as total FROM jobs WHERE assigned_to = ? AND status = 'returned'";
            $stmt_count = $conn->prepare($count_returned_sql);
            $stmt_count->bind_param("i", $userId);
            $stmt_count->execute();
            $returned_count = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
            $stmt_count->close();
            ?>
            <div class="flex flex-col sm:flex-row gap-3 items-start">
              <span class="text-sm font-semibold text-gray-700">สถานะงาน:</span>
              <div class="flex flex-wrap gap-2">
                <a href="<?= buildUrl(['status' => 'active', 'page' => 1]) ?>"
                  class="filter-btn <?= $status_filter === 'active' ? 'active' : '' ?>">
                  งานปกติ
                </a>
                <a href="<?= buildUrl(['status' => 'returned', 'page' => 1]) ?>"
                  class="filter-btn <?= $status_filter === 'returned' ? 'active' : '' ?>" style="position: relative;">
                  งานที่ถูกตีกลับ
                  <?php if ($returned_count > 0): ?>
                    <span
                      class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center shadow-lg">
                      <?= $returned_count ?>
                    </span>
                  <?php endif; ?>
                </a>
                <a href="<?= buildUrl(['status' => 'all', 'page' => 1]) ?>"
                  class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">
                  ทั้งหมด
                </a>
              </div>
            </div>

            <!-- Priority Filter -->
            <div class="flex flex-col sm:flex-row gap-3 items-start">
              <span class="text-sm font-semibold text-gray-700">ความเร่งด่วน:</span>
              <div class="flex flex-wrap gap-2">
                <a href="<?= buildUrl(['priority' => 'all', 'page' => 1]) ?>"
                  class="filter-btn <?= $priority_filter === 'all' ? 'active' : '' ?>">ทั้งหมด</a>
                <a href="<?= buildUrl(['priority' => 'normal', 'page' => 1]) ?>"
                  class="filter-btn <?= $priority_filter === 'normal' ? 'active' : '' ?>">ปกติ</a>
                <a href="<?= buildUrl(['priority' => 'high', 'page' => 1]) ?>"
                  class="filter-btn <?= $priority_filter === 'high' ? 'active' : '' ?>">ด่วน</a>
                <a href="<?= buildUrl(['priority' => 'urgent', 'page' => 1]) ?>"
                  class="filter-btn <?= $priority_filter === 'urgent' ? 'active' : '' ?>">ด่วนที่สุด</a>
              </div>
            </div>

            <!-- Pagination Controls -->
            <div class="flex flex-wrap items-center gap-3">
              <span class="text-sm font-semibold text-gray-700">แสดงต่อหน้า:</span>
              <select id="perPageSelect"
                class="px-4 py-2 border border-gray-200 rounded-xl bg-white text-sm font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
              </select>
              <span class="px-4 py-2 bg-blue-50 text-blue-700 rounded-xl text-sm font-semibold border border-blue-100">
                หน้า <?= $page ?> / <?= max(1, $total_pages) ?>
              </span>
            </div>

          </div>
        </div>
      </div>
    </header>

    <!-- Content Area -->
    <main class="p-4 pb-24">

      <?php if (empty($jobs)): ?>
        <!-- Empty State -->
        <div class="text-center py-16">
          <div class="bg-gray-50 rounded-2xl p-12 border-2 border-dashed border-gray-200">
            <div class="text-6xl mb-4">🔍</div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">
              <?php if (!empty($search) || $priority_filter !== 'all' || $favorite_only): ?>
                ไม่พบงานที่ตรงกับเงื่อนไข
              <?php else: ?>
                ยังไม่มีงานในขณะนี้
              <?php endif; ?>
            </h3>
            <p class="text-gray-600 mb-4">
              <?php if (!empty($search) || $priority_filter !== 'all' || $favorite_only): ?>
                ลองเปลี่ยนเงื่อนไขการค้นหาหรือตัวกรอง
              <?php else: ?>
                งานใหม่จะปรากฏที่นี่เมื่อได้รับมอบหมาย
              <?php endif; ?>
            </p>
            <?php if (!empty($search) || $priority_filter !== 'all' || $favorite_only): ?>
              <a href="?" class="btn btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                  </path>
                </svg>
                แสดงงานทั้งหมด
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>

        <!-- Cards View -->
        <div id="cardsView" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <?php foreach ($jobs as $row): ?>
            <?php $isNew = isNewJob($row['created_at'] ?? null); ?>
            <div class="job-card p-5 animate-slide-in">

              <div class="flex gap-4">
                <button class="fav-btn <?= $row['is_favorite'] ? 'active' : 'inactive' ?> toggle-fav" title="ปักงานโปรด"
                  data-id="<?= $row['id'] ?>">
                  <?= $row['is_favorite'] ? '★' : '☆' ?>
                </button>

                <div class="flex-1 min-w-0">
                  <div class="flex flex-wrap items-center gap-2 mb-3">
                    <div class="font-bold text-gray-900 truncate text-lg">
                      <?= htmlspecialchars($row['contract_number'] ?? '') ?></div>
                    <?php if ($isNew): ?><span class="status-chip danger animate-pulse-custom">NEW</span><?php endif; ?>
                  </div>

                  <div class="flex flex-wrap gap-2 mb-4">
                    <?= priorityChip($row['priority'] ?? 'normal'); ?>
                    <?= daysDiffBadge($row['due_date'] ?? null); ?>
                  </div>

                  <div class="text-sm text-gray-600 space-y-2.5 mb-4">
                    <div class="flex items-start gap-2.5">
                      <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                      </svg>
                      <span class="font-medium"><?= htmlspecialchars($row['location_info'] ?? '') ?></span>
                    </div>
                    <div class="flex items-start gap-2.5">
                      <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      </svg>
                      <span class="text-xs leading-relaxed"><?= htmlspecialchars($row['location_area'] ?? '') ?></span>
                    </div>
                    <div class="flex items-center gap-4 text-xs flex-wrap">
                      <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064">
                          </path>
                        </svg>
                        <span class="font-medium"><?= htmlspecialchars($row['zone'] ?? '') ?></span>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0">
                          </path>
                        </svg>
                        <span class="font-medium"><?= htmlspecialchars($row['plate'] ?? '') ?></span>
                      </div>
                    </div>
                    <?php if (!empty($row['province'])): ?>
                      <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor"
                          viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>
                        </svg>
                        <span class="text-xs font-medium"><?= htmlspecialchars($row['province']) ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <a href="view_job.php?id=<?= $row['id'] ?>" class="btn btn-primary w-full">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                      </path>
                    </svg>
                    ดูรายละเอียดงาน
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Table View -->
        <div id="tableView" class="table-view hidden">
          <div class="overflow-x-auto">
            <table>
              <thead>
                <tr>
                  <th class="text-center w-16">
                    <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                      </path>
                    </svg>
                  </th>
                  <th>สัญญา</th>
                  <th>ชื่อ</th>
                  <th>ที่อยู่</th>
                  <th>โซน</th>
                  <th>ทะเบียน</th>
                  <th>จังหวัด</th>
                  <th>ความเร่งด่วน</th>
                  <th>กำหนดเสร็จ</th>
                  <th class="text-center">ดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($jobs as $row): ?>
                  <?php $isNew = isNewJob($row['created_at'] ?? null); ?>
                  <tr class="animate-slide-in">
                    <td class="text-center">
                      <button class="fav-btn <?= $row['is_favorite'] ? 'active' : 'inactive' ?> toggle-fav mx-auto"
                        title="ปักงานโปรด" data-id="<?= $row['id'] ?>">
                        <?= $row['is_favorite'] ? '★' : '☆' ?>
                      </button>
                    </td>
                    <td>
                      <div class="flex items-center gap-2">
                        <span
                          class="font-semibold text-gray-900"><?= htmlspecialchars($row['contract_number'] ?? '') ?></span>
                        <?php if ($isNew): ?><span class="status-chip danger animate-pulse-custom">NEW</span><?php endif; ?>
                      </div>
                    </td>
                    <td class="font-medium text-gray-700"><?= htmlspecialchars($row['location_info'] ?? '') ?></td>
                    <td>
                      <div class="max-w-xs">
                        <span class="text-xs text-gray-600"><?= htmlspecialchars($row['location_area'] ?? '') ?></span>
                      </div>
                    </td>
                    <td class="font-medium text-gray-700"><?= htmlspecialchars($row['zone'] ?? '') ?></td>
                    <td class="font-medium text-gray-700"><?= htmlspecialchars($row['plate'] ?? '') ?></td>
                    <td class="text-gray-600"><?= htmlspecialchars($row['province'] ?? '') ?></td>
                    <td><?= priorityChip($row['priority'] ?? 'normal'); ?></td>
                    <td><?= daysDiffBadge($row['due_date'] ?? null); ?></td>
                    <td class="text-center">
                      <a href="view_job.php?id=<?= $row['id'] ?>" class="btn btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                          </path>
                        </svg>
                        ดูรายละเอียด
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="pagination">
            <?php if ($page > 1): ?>
              <a href="<?= buildUrl(['page' => $page - 1]) ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
              </a>
            <?php else: ?>
              <span class="disabled">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
              </span>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1):
              ?>
              <a href="<?= buildUrl(['page' => 1]) ?>">1</a>
              <?php if ($start_page > 2): ?>
                <span>...</span>
              <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
              <?php if ($i == $page): ?>
                <span class="current"><?= $i ?></span>
              <?php else: ?>
                <a href="<?= buildUrl(['page' => $i]) ?>"><?= $i ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages): ?>
              <?php if ($end_page < $total_pages - 1): ?>
                <span>...</span>
              <?php endif; ?>
              <a href="<?= buildUrl(['page' => $total_pages]) ?>"><?= $total_pages ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
              <a href="<?= buildUrl(['page' => $page + 1]) ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
              </a>
            <?php else: ?>
              <span class="disabled">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </main>
  </div>

  <!-- FAB -->
  <div class="fab-container">
    <div class="fab-menu" id="fabMenu">
      <div class="fab-item" onclick="window.location.href='../admin/map.php'">
        <div class="fab-item-icon bg-indigo-100 text-indigo-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7">
            </path>
          </svg>
        </div>
        <span class="text-sm font-semibold text-gray-700">แผนที่รวม</span>
      </div>
      <div class="fab-item" onclick="window.location.href='my_completed_jobs.php'">
        <div class="fab-item-icon bg-emerald-100 text-emerald-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <span class="text-sm font-semibold text-gray-700">งานเสร็จแล้ว</span>
      </div>
      <div class="fab-item" onclick="window.location.href='field_unassigned_jobs.php'">
        <div class="fab-item-icon bg-blue-100 text-blue-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
        </div>
        <span class="text-sm font-semibold text-gray-700">งานว่าง</span>
      </div>
      <div class="fab-item" onclick="toggleFavoriteView()">
        <div class="fab-item-icon bg-yellow-100 text-yellow-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
            </path>
          </svg>
        </div>
        <span class="text-sm font-semibold text-gray-700" id="favText">
          <?= $favorite_only ? 'ทั้งหมด' : 'งานโปรด' ?>
        </span>
      </div>
      <div class="fab-item" onclick="location.reload()">
        <div class="fab-item-icon bg-gray-100 text-gray-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
            </path>
          </svg>
        </div>
        <span class="text-sm font-semibold text-gray-700">รีเฟรช</span>
      </div>
      <div class="fab-item" onclick="window.location.href='../auth/logout.php'">
        <div class="fab-item-icon bg-red-100 text-red-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
          </svg>
        </div>
        <span class="text-sm font-semibold text-gray-700">ออกจากระบบ</span>
      </div>
    </div>
    <button class="fab-main" id="fabButton">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
      </svg>
    </button>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(function () {
      let menuExpanded = false;
      let currentView = 'cards';
      let fabOpen = false;

      // FAB Toggle
      $('#fabButton').on('click', function () {
        fabOpen = !fabOpen;
        $('#fabMenu').toggleClass('active', fabOpen);

        if (fabOpen) {
          $(this).find('svg').html('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>');
        } else {
          $(this).find('svg').html('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>');
        }
      });

      // Close FAB when clicking outside
      $(document).on('click', function (e) {
        if (fabOpen && !$(e.target).closest('.fab-container').length) {
          $('#fabButton').click();
        }
      });

      // Menu toggle
      $('#menuToggle').on('click', function () {
        const menu = $('#collapsibleMenu');
        const icon = $('#menuIcon');

        menuExpanded = !menuExpanded;

        if (menuExpanded) {
          menu.removeClass('menu-collapsed').addClass('menu-expanded');
          icon.css('transform', 'rotate(180deg)');
        } else {
          menu.removeClass('menu-expanded').addClass('menu-collapsed');
          icon.css('transform', 'rotate(0deg)');
        }
      });

      // View toggle
      $('#btnCards').on('click', function () {
        if (currentView === 'cards') return;

        currentView = 'cards';
        $(this).addClass('active');
        $('#btnTable').removeClass('active');

        $('#cardsView').removeClass('hidden');
        $('#tableView').addClass('hidden');
      });

      $('#btnTable').on('click', function () {
        if (currentView === 'table') return;

        currentView = 'table';
        $(this).addClass('active');
        $('#btnCards').removeClass('active');

        $('#tableView').removeClass('hidden');
        $('#cardsView').addClass('hidden');
      });

      // Per page change
      $('#perPageSelect').on('change', function () {
        const perPage = $(this).val();
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('per_page', perPage);
        urlParams.set('page', '1');
        window.location.href = '?' + urlParams.toString();
      });

      // Search form submit on Enter
      $('#searchInput').on('keypress', function (e) {
        if (e.which === 13) {
          $('#searchForm').submit();
        }
      });

      // Toggle favorite view
      window.toggleFavoriteView = function () {
        const urlParams = new URLSearchParams(window.location.search);
        const currentFav = urlParams.get('favorite') === '1';

        if (currentFav) {
          urlParams.delete('favorite');
        } else {
          urlParams.set('favorite', '1');
        }
        urlParams.set('page', '1');

        window.location.href = '?' + urlParams.toString();

        if (fabOpen) {
          $('#fabButton').click();
        }
      };

      // Toggle favorite
      function updateFavBtn(btn, isFav) {
        const $btn = $(btn);
        $btn.text(isFav ? '★' : '☆');

        if (isFav) {
          $btn.removeClass('inactive').addClass('active');
        } else {
          $btn.removeClass('active').addClass('inactive');
        }

        $btn.addClass('animate-pulse-custom');
        setTimeout(() => $btn.removeClass('animate-pulse-custom'), 600);
      }

      $(document).on('click', '.toggle-fav', function () {
        const btn = this;
        const $btn = $(btn);
        const id = btn.getAttribute('data-id');
        const currentFav = (btn.textContent.trim() === '★');
        const newFav = currentFav ? 0 : 1;

        $.post('toggle_favorite.php', {
          job_id: id,
          set: newFav
        })
          .done(function (res) {
            if (res === 'ok') {
              updateFavBtn(btn, newFav === 1);
              showToast(newFav ? 'เพิ่มงานโปรดสำเร็จ' : 'ลบงานโปรดสำเร็จ', 'success');
            } else {
              showToast('เปลี่ยนสถานะงานโปรดไม่สำเร็จ', 'error');
            }
          })
          .fail(function () {
            showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
          });
      });

      // Toast
      function showToast(message, type = 'info') {
        const icons = {
          success: '<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
          error: '<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
          info: '<svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        };

        const toast = $(`
          <div class="toast toast-${type}">
            ${icons[type]}
            <span class="text-sm font-semibold text-gray-900 flex-1">${message}</span>
            <button class="text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        `);

        toast.find('button').on('click', function () {
          toast.removeClass('show');
          setTimeout(() => toast.remove(), 300);
        });

        $('body').append(toast);
        setTimeout(() => toast.addClass('show'), 100);
        setTimeout(() => {
          toast.removeClass('show');
          setTimeout(() => toast.remove(), 300);
        }, 4000);
      }

      // Keyboard shortcuts
      $(document).on('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
          e.preventDefault();
          $('#searchInput').focus();
        }

        if (e.key === 'Escape') {
          if ($('#searchInput').is(':focus')) {
            $('#searchInput').blur();
          } else if (fabOpen) {
            $('#fabButton').click();
          }
        }
      });

      // Prevent double-tap zoom on specific elements only
      let lastTouchEnd = 0;
      $('.view-toggle button, .fab-main').on('touchend', function (e) {
        const now = (new Date()).getTime();
        if (now - lastTouchEnd <= 300) {
          e.preventDefault();
        }
        lastTouchEnd = now;
      });

      // Auto-expand menu if there are active filters
      <?php if (!empty($search) || $priority_filter !== 'all' || $favorite_only): ?>
        $('#menuToggle').click();
      <?php endif; ?>

      // Save view preference
      $(window).on('beforeunload', function () {
        localStorage.setItem('preferredView', currentView);
      });

      // Load saved view preference
      const savedView = localStorage.getItem('preferredView');
      if (savedView === 'table') {
        $('#btnTable').click();
      }

      // Welcome message
      <?php if (empty($search) && $priority_filter === 'all' && !$favorite_only): ?>
        setTimeout(() => {
          const jobCount = <?= $total_jobs ?>;
          if (jobCount > 0) {
            showToast('ยินดีต้อนรับ! คุณมี ' + jobCount.toLocaleString() + ' งานรอดำเนินการ', 'info');
          }
        }, 1000);
      <?php endif; ?>
    });
  </script>
</body>

</html>