<?php
// Redirect to new unified logs page
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Redirect to new logs.php with job_edit tab
header("Location: logs.php?tab=job_edit");
exit;

$cleared = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF validation failed");
    }

    $admin_pass = $_POST['admin_password'] ?? '';
    $admin_id = $_SESSION['user']['id'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->bind_result($hash);
    $stmt->fetch();
    $stmt->close();

    if (password_verify($admin_pass, $hash)) {
        $conn->begin_transaction();
        if ($conn->query("DELETE FROM job_edit_logs")) {
            $conn->commit();
            $cleared = true;
        } else {
            $conn->rollback();
            $error = "เกิดข้อผิดพลาดในการล้าง log";
        }
    } else {
        $error = "รหัสผ่านไม่ถูกต้อง";
    }
}

// Filter
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$editor = $_GET['editor'] ?? '';
$job_id = $_GET['job_id'] ?? '';

$conditions = [];
$params = [];
$types = '';

if ($start_date) {
    $conditions[] = "DATE(j.edited_at) >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if ($end_date) {
    $conditions[] = "DATE(j.edited_at) <= ?";
    $params[] = $end_date;
    $types .= 's';
}
if ($editor) {
    $conditions[] = "u.name LIKE ?";
    $params[] = "%$editor%";
    $types .= 's';
}
if ($job_id) {
    $conditions[] = "j.job_id = ?";
    $params[] = $job_id;
    $types .= 'i';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "
    SELECT j.job_id, j.change_summary, j.edited_at, u.name AS editor_name
    FROM job_edit_logs j
    LEFT JOIN users u ON j.edited_by = u.id
    $where
    ORDER BY j.edited_at DESC
";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$page_title = "ประวัติการแก้ไขงาน";
include '../components/header.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            'thai': ['Prompt', 'sans-serif']
          },
          colors: {
            'primary': '#dc2626',
            'secondary': '#991b1b',
            'surface': '#f8fafc'
          },
          animation: {
            'fade-in': 'fadeIn 0.6s ease-out',
            'slide-up': 'slideUp 0.5s ease-out'
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0', transform: 'translateY(20px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            },
            slideUp: {
              '0%': { opacity: '0', transform: 'translateY(30px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            }
          }
        }
      }
    }
  </script>
  <style>
    /* Custom DataTables styling */
    .dataTables_wrapper .dataTables_length select {
      padding: 0.5rem 2.5rem 0.5rem 1rem;
      border-radius: 0.75rem;
      border: 1px solid #d1d5db;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
      background-position: right 0.5rem center;
      background-repeat: no-repeat;
      background-size: 1.5em 1.5em;
      appearance: none;
    }
    
    .dataTables_wrapper .dataTables_filter input {
      border-radius: 0.75rem;
      border: 1px solid #d1d5db;
      padding: 0.5rem 1rem;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
      border-radius: 0.5rem;
      margin: 0 0.25rem;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
      background: #dc2626 !important;
      color: white !important;
      border-color: #dc2626 !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
      background: #f3f4f6 !important;
      border-color: #d1d5db !important;
      color: #111827 !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
      background: #b91c1c !important;
      border-color: #b91c1c !important;
      color: white !important;
    }
  </style>
</head>
<body class="font-thai bg-surface min-h-screen">

<div class="flex min-h-screen bg-surface">
  <!-- Sidebar -->
  <?php include '../components/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="flex flex-col flex-1 ml-64">
    <!-- Header -->
    <header class="bg-white shadow-lg border-b border-red-100 sticky top-0 z-40">
      <div class="px-6 py-4">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center">
              <svg class="w-7 h-7 mr-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              ประวัติการแก้ไขงาน
            </h1>
            <p class="text-sm text-gray-500 mt-1">ติดตามการเปลี่ยนแปลงข้อมูลงานทั้งหมด</p>
          </div>
          <div class="flex items-center gap-4">
            <a href="../dashboard/admin.php" class="inline-flex items-center px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-xl font-medium transition-all duration-300 shadow-lg hover:shadow-xl">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
              กลับแดชบอร์ด
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- Body -->
    <main class="flex-1 p-6 space-y-6 overflow-y-auto">
      
      <!-- Alert Messages -->
      <?php if (!empty($cleared)): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl animate-fade-in">
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="font-medium">ล้าง log เรียบร้อยแล้ว</span>
          </div>
        </div>
      <?php elseif (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl animate-fade-in">
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
          </div>
        </div>
      <?php endif; ?>

      <!-- Filter Section -->
      <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up">
        <div class="mb-6">
          <h2 class="text-xl font-semibold text-gray-800 flex items-center">
            <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            กรองข้อมูล Log
          </h2>
          <p class="text-sm text-gray-500 mt-1">ค้นหาและกรองประวัติการแก้ไขตาม Job ID, ผู้แก้ไข และช่วงวันที่</p>
        </div>

        <form method="get" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                </svg>
                Job ID
              </label>
              <input type="text" 
                     name="job_id" 
                     value="<?= htmlspecialchars($job_id) ?>" 
                     placeholder="ค้นหา Job ID..."
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-300">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
                ชื่อผู้แก้ไข
              </label>
              <input type="text" 
                     name="editor" 
                     value="<?= htmlspecialchars($editor) ?>" 
                     placeholder="ค้นหาชื่อผู้แก้ไข..."
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-300">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                จากวันที่
              </label>
              <input type="date" 
                     name="start_date" 
                     value="<?= htmlspecialchars($start_date) ?>"
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-300">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                ถึงวันที่
              </label>
              <input type="date" 
                     name="end_date" 
                     value="<?= htmlspecialchars($end_date) ?>"
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-300">
            </div>
          </div>
          
          <div class="flex flex-col sm:flex-row gap-3 pt-4">
            <button type="submit" class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
              </svg>
              กรองข้อมูล
            </button>
            <a href="job_logs.php" class="inline-flex items-center justify-center px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              รีเซ็ต
            </a>
          </div>
        </form>
      </section>

      <!-- Clear Logs Section -->
      <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up" style="animation-delay: 0.1s;">
        <div class="mb-6">
          <h2 class="text-xl font-semibold text-gray-800 flex items-center">
            <svg class="w-6 h-6 mr-3 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            จัดการข้อมูล Log
          </h2>
          <p class="text-sm text-gray-500 mt-1">ล้างประวัติการแก้ไขทั้งหมด (ต้องยืนยันรหัสผ่าน Admin)</p>
        </div>

        <form method="post" onsubmit="return confirm('คุณต้องการล้าง log ทั้งหมดหรือไม่?\n\nการดำเนินการนี้ไม่สามารถยกเลิกได้และจะลบประวัติการแก้ไขทั้งหมดออกจากระบบ');" class="flex flex-col sm:flex-row gap-4">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <div class="flex-1">
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
              </div>
              <input type="password" 
                     name="admin_password" 
                     placeholder="ใส่รหัสผ่าน Admin เพื่อยืนยัน" 
                     class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-300" 
                     required>
            </div>
          </div>
          <button type="submit" 
                  name="clear_logs" 
                  class="inline-flex items-center justify-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            ล้างทั้งหมด
          </button>
        </form>
      </section>

      <!-- Logs Table Section -->
      <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up" style="animation-delay: 0.2s;">
        <div class="mb-6">
          <h2 class="text-xl font-semibold text-gray-800 flex items-center">
            <svg class="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            รายการประวัติการแก้ไข
          </h2>
          <p class="text-sm text-gray-500 mt-1">แสดงรายการการแก้ไขงานทั้งหมดเรียงตามเวลาล่าสุด</p>
        </div>

        <div class="overflow-hidden">
          <div class="overflow-x-auto">
            <table id="logsTable" class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                      </svg>
                      Job ID
                    </div>
                  </th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                      </svg>
                      ผู้แก้ไข
                    </div>
                  </th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                      รายละเอียดการเปลี่ยนแปลง
                    </div>
                  </th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      วันเวลา
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($result->num_rows > 0): ?>
                  <?php while ($log = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                          <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                          </svg>
                          #<?= $log['job_id'] ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center shadow-md">
                            <span class="text-white font-semibold text-sm">
                              <?= strtoupper(substr($log['editor_name'] ?? 'N/A', 0, 2)) ?>
                            </span>
                          </div>
                          <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">
                              <?= htmlspecialchars($log['editor_name'] ?? 'ไม่ระบุ') ?>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 max-w-md">
                          <div class="line-clamp-2" title="<?= htmlspecialchars($log['change_summary']) ?>">
                            <?= htmlspecialchars($log['change_summary']) ?>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <div class="flex flex-col">
                          <span class="font-medium text-gray-900">
                            <?= date('d/m/Y', strtotime($log['edited_at'])) ?>
                          </span>
                          <span class="text-xs text-gray-500 mt-0.5">
                            <?= date('H:i:s น.', strtotime($log['edited_at'])) ?>
                          </span>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="px-6 py-12 text-center">
                      <div class="flex flex-col items-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full mb-4 flex items-center justify-center">
                          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                          </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">ไม่พบข้อมูลการแก้ไข</h3>
                        <p class="text-gray-500 text-sm">ยังไม่มีประวัติการแก้ไขงานในระบบ</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<script>
$(document).ready(function() {
  var table = $('#logsTable').DataTable({
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, 250], [10, 25, 50, 100, 250]],
    order: [[3, 'desc']], // Sort by date column (latest first)
    language: {
      search: "ค้นหา:",
      lengthMenu: "แสดง _MENU_ รายการ",
      info: "แสดง _START_ - _END_ จาก _TOTAL_ รายการ",
      infoEmpty: "ไม่มีข้อมูล",
      infoFiltered: "(กรองจาก _MAX_ รายการทั้งหมด)",
      paginate: {
        previous: "← ก่อนหน้า",
        next: "ถัดไป →",
        first: "หน้าแรก",
        last: "หน้าสุดท้าย"
      },
      zeroRecords: "ไม่พบข้อมูลที่ตรงกับการค้นหา",
      emptyTable: "ไม่มีข้อมูลในตาราง"
    },
    columnDefs: [
      {
        targets: 2, // Change summary column
        render: function(data, type, row) {
          if (type === 'display' && data.length > 80) {
            return '<div class="line-clamp-2" title="' + data + '">' + data + '</div>';
          }
          return data;
        }
      }
    ],
    responsive: true,
    dom: '<"flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4"<"flex items-center"l><"flex-1 text-right"f>>rtip',
    drawCallback: function() {
      // Update pagination styling
      $('.dataTables_paginate .paginate_button').removeClass('bg-red-600 text-white border-red-600');
      $('.dataTables_paginate .paginate_button.current').addClass('bg-red-600 text-white border-red-600');
    }
  });

  // Custom styling for DataTable controls
  $('.dataTables_length').addClass('flex items-center gap-2');
  $('.dataTables_length label').addClass('text-sm text-gray-700 font-medium flex items-center gap-2');
  $('.dataTables_length select').addClass('form-select text-sm');
  
  $('.dataTables_filter').addClass('flex items-center gap-2');
  $('.dataTables_filter label').addClass('text-sm text-gray-700 font-medium flex items-center gap-2');
  $('.dataTables_filter input').addClass('form-input text-sm').attr('placeholder', 'ค้นหาในตาราง...');

  // Add smooth transitions to alert messages
  $('[class*="bg-green-50"], [class*="bg-red-50"]').each(function() {
    $(this).hide().fadeIn(500);
    setTimeout(function() {
      $('[class*="bg-green-50"], [class*="bg-red-50"]').fadeOut(500);
    }, 5000);
  });
});
</script>

<?php include '../components/footer.php'; ?>

</body>
</html>