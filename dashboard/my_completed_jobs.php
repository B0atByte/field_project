<?php
require_once __DIR__ . '/../includes/session_config.php';
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

$userId = $_SESSION['user']['id'];

// Get statistics
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');

// Today's completed jobs
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE assigned_to = ? AND status = 'completed' AND DATE(updated_at) = ?");
$stmt->bind_param("is", $userId, $today);
$stmt->execute();
$todayCount = $stmt->get_result()->fetch_assoc()['count'];

// This week's completed jobs
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE assigned_to = ? AND status = 'completed' AND DATE(updated_at) >= ?");
$stmt->bind_param("is", $userId, $weekStart);
$stmt->execute();
$weekCount = $stmt->get_result()->fetch_assoc()['count'];

// This month's completed jobs
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE assigned_to = ? AND status = 'completed' AND DATE(updated_at) >= ?");
$stmt->bind_param("is", $userId, $monthStart);
$stmt->execute();
$monthCount = $stmt->get_result()->fetch_assoc()['count'];

// Total completed jobs
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE assigned_to = ? AND status = 'completed'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalCount = $stmt->get_result()->fetch_assoc()['count'];

// Average per day (last 30 days)
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM jobs 
    WHERE assigned_to = ? AND status = 'completed' 
    AND DATE(updated_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$last30DaysCount = $stmt->get_result()->fetch_assoc()['count'];
$avgPerDay = round($last30DaysCount / 30, 1);

// Daily stats for chart (last 7 days)
$dailyStats = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE assigned_to = ? AND status = 'completed' AND DATE(updated_at) = ?");
    $stmt->bind_param("is", $userId, $date);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    $dailyStats[] = [
        'date' => $date,
        'count' => $count,
        'day' => date('D', strtotime($date))
    ];
}

// Get completed jobs
$stmt = $conn->prepare("
    SELECT j.*, u.name AS imported_by_name,
           CASE 
               WHEN DATE(j.updated_at) = CURDATE() THEN 'today'
               WHEN DATE(j.updated_at) >= ? THEN 'week'
               WHEN DATE(j.updated_at) >= ? THEN 'month'
               ELSE 'older'
           END as period
    FROM jobs j 
    LEFT JOIN users u ON j.imported_by = u.id 
    WHERE j.assigned_to = ? AND j.status = 'completed'
    ORDER BY j.updated_at DESC
");
$stmt->bind_param("ssi", $weekStart, $monthStart, $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>งานที่เสร็จแล้ว | ระบบจัดการงาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes, maximum-scale=5.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    
    /* Card Styling */
    .card {
      background: #ffffff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }
    
    .card:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    
    /* Stats Cards */
    .stat-card {
      background: #ffffff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 24px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }
    
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
    }
    
    .stat-card-today::before {
      background: linear-gradient(90deg, #10b981, #059669);
    }
    
    .stat-card-week::before {
      background: linear-gradient(90deg, #3b82f6, #2563eb);
    }
    
    .stat-card-month::before {
      background: linear-gradient(90deg, #8b5cf6, #7c3aed);
    }
    
    .stat-card-avg::before {
      background: linear-gradient(90deg, #f59e0b, #d97706);
    }
    
    .stat-card-total::before {
      background: linear-gradient(90deg, #6366f1, #4f46e5);
    }
    
    /* Button Styling */
    .btn {
      padding: 10px 20px;
      border-radius: 12px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      justify-content: center;
      border: none;
      cursor: pointer;
      font-size: 14px;
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
    
    .btn-success {
      background: #10b981;
      color: white;
    }
    
    .btn-success:hover {
      background: #059669;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .btn-secondary {
      background: #6b7280;
      color: white;
    }
    
    .btn-secondary:hover {
      background: #4b5563;
    }
    
    /* Table Styling */
    .table-container {
      border-radius: 16px;
      overflow: hidden;
      background: white;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
    }
    
    table.dataTable {
      border: none;
      font-size: 13px;
    }
    
    table.dataTable thead th {
      background: #f9fafb;
      color: #374151;
      font-weight: 600;
      padding: 16px 12px;
      border-bottom: 2px solid #e5e7eb;
      font-size: 13px;
    }
    
    table.dataTable tbody td {
      padding: 12px;
      border-bottom: 1px solid #f3f4f6;
      vertical-align: middle;
    }
    
    table.dataTable tbody tr:hover {
      background: #f9fafb;
    }
    
    /* Period Badges */
    .period-badge {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    
    .period-today {
      background: #ecfdf5;
      color: #059669;
      border: 1px solid #d1fae5;
    }
    
    .period-week {
      background: #eff6ff;
      color: #2563eb;
      border: 1px solid #bfdbfe;
    }
    
    .period-month {
      background: #fef3c7;
      color: #d97706;
      border: 1px solid #fde68a;
    }
    
    .period-older {
      background: #f3f4f6;
      color: #6b7280;
      border: 1px solid #e5e7eb;
    }
    
    /* Chart Container */
    .chart-container {
      position: relative;
      height: 320px;
      background: #ffffff;
      border-radius: 12px;
      padding: 20px;
    }
    
    /* Achievement Badges */
    .achievement {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      background: #fef3c7;
      color: #d97706;
      border: 1px solid #fde68a;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
    }
    
    /* Filter Buttons */
    .filter-btn {
      padding: 8px 16px;
      border-radius: 10px;
      border: 1px solid #e5e7eb;
      background: #ffffff;
      color: #6b7280;
      font-size: 13px;
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
    
    /* Header */
    .page-header {
      position: sticky;
      top: 0;
      z-index: 40;
      background: #ffffff;
      border-bottom: 1px solid #e5e7eb;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
      .card {
        padding: 16px !important;
      }
      
      .stat-card {
        padding: 16px;
      }
      
      .btn {
        padding: 8px 16px;
        font-size: 13px;
      }
      
      table.dataTable thead th {
        font-size: 11px;
        padding: 12px 6px;
      }
      
      table.dataTable tbody td {
        font-size: 11px;
        padding: 8px 4px;
      }
      
      .chart-container {
        height: 250px;
        padding: 12px;
      }
      
      .period-badge {
        font-size: 10px;
        padding: 3px 8px;
      }
      
      .achievement {
        font-size: 10px;
        padding: 4px 8px;
      }
    }
    
    /* Animations */
    @keyframes countUp {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .count-up {
      animation: countUp 0.4s ease forwards;
    }
    
    /* DataTables Custom */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
      font-size: 13px;
      color: #6b7280;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
      padding: 6px 12px !important;
      margin: 0 4px !important;
      border-radius: 8px !important;
      border: 1px solid #e5e7eb !important;
      background: #fff !important;
      color: #374151 !important;
      transition: all 0.2s ease !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
      background: #f9fafb !important;
      border-color: #3b82f6 !important;
      color: #3b82f6 !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
      background: #3b82f6 !important;
      color: #fff !important;
      border-color: #3b82f6 !important;
    }
    
    .dataTables_filter input {
      border: 2px solid #e5e7eb !important;
      background: #f9fafb !important;
      border-radius: 10px !important;
      padding: 8px 12px !important;
      transition: all 0.2s ease !important;
      font-weight: 500 !important;
    }
    
    .dataTables_filter input:focus {
      border-color: #3b82f6 !important;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
      outline: none !important;
      background: #ffffff !important;
    }
  </style>
</head>

<body class="min-h-screen bg-white">

<div class="max-w-7xl mx-auto p-3 sm:p-4 space-y-4 sm:space-y-6">
  
  <!-- Header -->
  <div class="page-header -mx-3 sm:-mx-4 px-3 sm:px-4 py-4">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
      <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center gap-2">
          📊 งานที่เสร็จแล้ว
        </h1>
        <p class="text-sm sm:text-base text-gray-600 mt-1">สถิติและประวัติการทำงานของคุณ</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="field.php" class="btn btn-secondary">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0L2.586 11l3.707-3.707a1 1 0 011.414 1.414L5.414 10H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
          </svg>
          กลับ
        </a>
        <button onclick="refreshStats()" class="btn btn-primary">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
          </svg>
          <span class="hidden sm:inline">รีเฟรช</span>
        </button>
        <button onclick="exportData()" class="btn btn-success">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          <span class="hidden sm:inline">ส่งออก</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
    <div class="stat-card stat-card-today count-up" style="animation-delay: 0.1s">
      <div class="flex items-center justify-between mb-3">
        <div class="text-3xl">📅</div>
        <?php if ($todayCount >= 5): ?>
          <div class="achievement">🏆 เก่ง!</div>
        <?php endif; ?>
      </div>
      <p class="text-xs sm:text-sm text-gray-600 mb-1 font-medium">วันนี้</p>
      <p class="text-2xl sm:text-3xl font-bold text-green-600" id="todayCount"><?= $todayCount ?></p>
      <p class="text-xs text-gray-500">งานที่เสร็จ</p>
    </div>

    <div class="stat-card stat-card-week count-up" style="animation-delay: 0.2s">
      <div class="flex items-center justify-between mb-3">
        <div class="text-3xl">📆</div>
        <?php if ($weekCount >= 25): ?>
          <div class="achievement">🌟 ยอดเยี่ยม!</div>
        <?php endif; ?>
      </div>
      <p class="text-xs sm:text-sm text-gray-600 mb-1 font-medium">สัปดาห์นี้</p>
      <p class="text-2xl sm:text-3xl font-bold text-blue-600" id="weekCount"><?= $weekCount ?></p>
      <p class="text-xs text-gray-500">งานที่เสร็จ</p>
    </div>

    <div class="stat-card stat-card-month count-up" style="animation-delay: 0.3s">
      <div class="flex items-center justify-between mb-3">
        <div class="text-3xl">🗓️</div>
        <?php if ($monthCount >= 100): ?>
          <div class="achievement">🎯 นักสู้!</div>
        <?php endif; ?>
      </div>
      <p class="text-xs sm:text-sm text-gray-600 mb-1 font-medium">เดือนนี้</p>
      <p class="text-2xl sm:text-3xl font-bold text-purple-600" id="monthCount"><?= $monthCount ?></p>
      <p class="text-xs text-gray-500">งานที่เสร็จ</p>
    </div>

    <div class="stat-card stat-card-avg count-up" style="animation-delay: 0.4s">
      <div class="flex items-center justify-between mb-3">
        <div class="text-3xl">📈</div>
      </div>
      <p class="text-xs sm:text-sm text-gray-600 mb-1 font-medium">เฉลี่ย/วัน</p>
      <p class="text-2xl sm:text-3xl font-bold text-orange-600" id="avgCount"><?= $avgPerDay ?></p>
      <p class="text-xs text-gray-500">30 วันที่ผ่านมา</p>
    </div>

    <div class="stat-card stat-card-total count-up" style="animation-delay: 0.5s">
      <div class="flex items-center justify-between mb-3">
        <div class="text-3xl">🏆</div>
        <?php if ($totalCount >= 500): ?>
          <div class="achievement">👑 ตำนาน!</div>
        <?php endif; ?>
      </div>
      <p class="text-xs sm:text-sm text-gray-600 mb-1 font-medium">ทั้งหมด</p>
      <p class="text-2xl sm:text-3xl font-bold text-indigo-600" id="totalCount"><?= $totalCount ?></p>
      <p class="text-xs text-gray-500">งานที่เสร็จ</p>
    </div>
  </div>

  <!-- Performance Chart -->
  <div class="card p-4 sm:p-6">
    <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
      <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
      </svg>
      ประสิทธิภาพ 7 วันที่ผ่านมา
    </h3>
    <div class="chart-container">
      <canvas id="performanceChart"></canvas>
    </div>
  </div>

  <!-- Quick Filters -->
  <div class="card p-4 sm:p-6">
    <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
      <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
      </svg>
      ตัวกรองด่วน
    </h3>
    <div class="flex flex-wrap gap-2 sm:gap-3">
      <button onclick="filterPeriod('all')" class="filter-btn active" data-period="all">
        ทั้งหมด
      </button>
      <button onclick="filterPeriod('today')" class="filter-btn" data-period="today">
        📅 วันนี้ (<?= $todayCount ?>)
      </button>
      <button onclick="filterPeriod('week')" class="filter-btn" data-period="week">
        📆 สัปดาห์นี้ (<?= $weekCount ?>)
      </button>
      <button onclick="filterPeriod('month')" class="filter-btn" data-period="month">
        🗓️ เดือนนี้ (<?= $monthCount ?>)
      </button>
    </div>
  </div>

  <!-- Jobs Table -->
  <div class="card p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
      <h3 class="text-base sm:text-lg font-bold text-gray-900 flex items-center gap-2">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        รายการงานที่เสร็จแล้ว
        <span id="jobCount" class="text-xs sm:text-sm bg-green-50 text-green-700 px-2 sm:px-3 py-1 rounded-lg font-semibold border border-green-200"><?= $totalCount ?> งาน</span>
      </h3>
      <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
        </svg>
        <span class="font-medium">คลิกที่รายการเพื่อดูรายละเอียดงานได้ทันที</span>
      </p>
      <button onclick="toggleDetails()" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
        </svg>
        แสดง/ซ่อน
      </button>
    </div>
    
    <div class="table-container">
      <table id="jobsTable" class="display w-full">
        <thead>
          <tr>
            <th>เลขสัญญา</th>
            <th>ชื่อลูกค้า</th>
            <th>พื้นที่</th>
            <th>โซน</th>
            <th>ทะเบียน</th>
            <th>วันครบกำหนด</th>
            <th>ผู้ลงงาน</th>
            <th>เสร็จเมื่อ</th>
            <th>ช่วงเวลา</th>
            <th>ดำเนินการ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr data-period="<?= $row['period'] ?>"
                class="cursor-pointer hover:bg-blue-50 transition-all duration-200"
                onclick="window.location.href='job_result.php?id=<?= $row['id'] ?>'"
                title="คลิกเพื่อดูรายละเอียดงาน">
              <td class="font-semibold text-gray-900"><?= htmlspecialchars($row['contract_number']) ?></td>
              <td class="text-gray-700"><?= htmlspecialchars($row['location_info']) ?></td>
              <td class="text-xs sm:text-sm text-gray-600"><?= htmlspecialchars($row['location_area']) ?></td>
              <td class="text-center text-gray-700"><?= htmlspecialchars($row['zone']) ?></td>
              <td class="font-mono text-xs sm:text-sm text-gray-700"><?= htmlspecialchars($row['plate']) ?></td>
              <td class="text-xs sm:text-sm text-gray-600"><?= htmlspecialchars($row['due_date']) ?></td>
              <td class="text-xs sm:text-sm text-gray-600"><?= htmlspecialchars($row['imported_by_name'] ?? '-') ?></td>
              <td class="text-xs sm:text-sm text-gray-600"><?= date('d/m/Y H:i', strtotime($row['updated_at'])) ?></td>
              <td class="text-center">
                <span class="period-badge period-<?= $row['period'] ?>">
                  <?php
                    switch($row['period']) {
                      case 'today': echo '📅 วันนี้'; break;
                      case 'week': echo '📆 สัปดาห์นี้'; break;
                      case 'month': echo '🗓️ เดือนนี้'; break;
                      default: echo '📁 เก่ากว่า'; break;
                    }
                  ?>
                </span>
              </td>
              <td class="text-center" onclick="event.stopPropagation()">
                <a href="job_result.php?id=<?= $row['id'] ?>"
                   class="btn btn-success text-xs px-3 py-1.5 inline-flex items-center gap-1" title="ดูผลงาน">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                  </svg>
                  <span class="hidden sm:inline">ดูรายละเอียด</span>
                  <span class="sm:hidden">ดู</span>
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
let jobsTable;
let performanceChart;

$(document).ready(function() {
  initializeTable();
  initializeChart();
  animateCounters();
});

function initializeTable() {
  jobsTable = $('#jobsTable').DataTable({
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'ทั้งหมด']],
    order: [[7, 'desc']],
    language: {
      lengthMenu: "แสดง _MENU_ รายการ",
      zeroRecords: "ไม่พบข้อมูลที่ตรงกับการค้นหา",
      info: "แสดง _START_–_END_ จาก _TOTAL_ รายการ",
      infoEmpty: "ไม่มีข้อมูล",
      infoFiltered: "(กรองจากทั้งหมด _MAX_ รายการ)",
      paginate: {
        previous: "‹ ก่อนหน้า",
        next: "ถัดไป ›",
        first: "‹‹ แรก",
        last: "สุดท้าย ››"
      },
      processing: "กำลังประมวลผล...",
      search: "ค้นหา:",
      searchPlaceholder: "ค้นหาในตาราง..."
    },
    responsive: true,
    dom: '<"flex flex-col sm:flex-row justify-between items-center gap-4 mb-4"lf>rt<"flex flex-col sm:flex-row justify-between items-center gap-4 mt-4"ip>',
    initComplete: function() {
      showNotification('โหลดข้อมูลเรียบร้อย', 'success');
    }
  });
}

function initializeChart() {
  const ctx = document.getElementById('performanceChart').getContext('2d');
  const dailyData = <?= json_encode($dailyStats) ?>;
  
  performanceChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: dailyData.map(item => item.day),
      datasets: [{
        label: 'งานที่เสร็จ',
        data: dailyData.map(item => item.count),
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#3b82f6',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 6,
        pointHoverRadius: 8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleColor: '#fff',
          bodyColor: '#fff',
          borderColor: '#3b82f6',
          borderWidth: 1,
          cornerRadius: 8,
          padding: 12,
          callbacks: {
            label: function(context) {
              return `งานที่เสร็จ: ${context.parsed.y} งาน`;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1,
            color: '#6b7280',
            font: {
              size: 12,
              family: 'Sarabun'
            }
          },
          grid: {
            color: '#f3f4f6'
          }
        },
        x: {
          ticks: {
            color: '#6b7280',
            font: {
              size: 12,
              family: 'Sarabun'
            }
          },
          grid: {
            display: false
          }
        }
      }
    }
  });
}

function animateCounters() {
  const counters = [
    { id: 'todayCount', target: <?= $todayCount ?>, duration: 1000 },
    { id: 'weekCount', target: <?= $weekCount ?>, duration: 1200 },
    { id: 'monthCount', target: <?= $monthCount ?>, duration: 1400 },
    { id: 'avgCount', target: <?= $avgPerDay ?>, duration: 1600, decimal: true },
    { id: 'totalCount', target: <?= $totalCount ?>, duration: 1800 }
  ];

  counters.forEach(counter => {
    animateCounter(counter.id, counter.target, counter.duration, counter.decimal);
  });
}

function animateCounter(elementId, target, duration, isDecimal = false) {
  const element = document.getElementById(elementId);
  const start = 0;
  const startTime = performance.now();

  function updateCounter(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const value = start + (target - start) * easeOutCubic(progress);
    
    if (isDecimal) {
      element.textContent = value.toFixed(1);
    } else {
      element.textContent = Math.floor(value).toLocaleString();
    }

    if (progress < 1) {
      requestAnimationFrame(updateCounter);
    }
  }

  requestAnimationFrame(updateCounter);
}

function easeOutCubic(t) {
  return 1 - Math.pow(1 - t, 3);
}

function filterPeriod(period) {
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.classList.remove('active');
    if (btn.dataset.period === period) {
      btn.classList.add('active');
    }
  });

  if (period === 'all') {
    jobsTable.column(8).search('').draw();
  } else {
    const searchTerms = {
      today: 'วันนี้',
      week: 'สัปดาห์นี้',
      month: 'เดือนนี้'
    };
    jobsTable.column(8).search(searchTerms[period]).draw();
  }

  setTimeout(() => {
    const info = jobsTable.page.info();
    document.getElementById('jobCount').textContent = `${info.recordsDisplay} งาน`;
  }, 100);
}

function toggleDetails() {
  const columns = [2, 5, 6];
  columns.forEach(col => {
    const column = jobsTable.column(col);
    column.visible(!column.visible());
  });
}

function refreshStats() {
  showNotification('กำลังรีเฟรชข้อมูล...', 'info');
  location.reload();
}

function exportData() {
  showNotification('กำลังเตรียมไฟล์ส่งออก...', 'info');
  
  const data = [];
  jobsTable.rows({ search: 'applied' }).every(function() {
    const rowData = this.data();
    data.push({
      'เลขสัญญา': rowData[0],
      'ชื่อลูกค้า': rowData[1],
      'พื้นที่': rowData[2],
      'โซน': rowData[3],
      'ทะเบียน': rowData[4],
      'วันครบกำหนด': rowData[5],
      'ผู้ลงงาน': rowData[6],
      'เสร็จเมื่อ': rowData[7],
      'ช่วงเวลา': $(rowData[8]).text().trim()
    });
  });

  if (data.length === 0) {
    showNotification('ไม่มีข้อมูลให้ส่งออก', 'warning');
    return;
  }

  const headers = Object.keys(data[0]);
  const csvContent = [
    headers.join(','),
    ...data.map(row => headers.map(header => `"${row[header]}"`).join(','))
  ].join('\n');

  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', `งานที่เสร็จแล้ว_${new Date().toISOString().split('T')[0]}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  
  showNotification('ส่งออกข้อมูลเรียบร้อย', 'success');
}

function showNotification(message, type = 'info') {
  const colors = {
    success: '#10b981',
    error: '#ef4444',
    warning: '#f59e0b',
    info: '#3b82f6'
  };

  const icons = {
    success: '✅',
    error: '❌',
    warning: '⚠️',
    info: 'ℹ️'
  };

  Swal.fire({
    html: `<div class="flex items-center gap-3"><span class="text-2xl">${icons[type]}</span><span>${message}</span></div>`,
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: colors[type],
    color: 'white'
  });
}

$(document).on('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
    e.preventDefault();
    $('.dataTables_filter input').focus();
  }
  
  if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
    e.preventDefault();
    refreshStats();
  }
  
  if (e.key >= '1' && e.key <= '4') {
    const filters = ['all', 'today', 'week', 'month'];
    const filterIndex = parseInt(e.key) - 1;
    if (filters[filterIndex]) {
      filterPeriod(filters[filterIndex]);
    }
  }
});

if (window.innerWidth <= 768) {
  if (jobsTable) {
    jobsTable.columns([2, 5, 6]).visible(false);
  }
}

setTimeout(() => {
  const currentHour = new Date().getHours();
  let greeting;
  
  if (currentHour < 12) greeting = 'สวัสดีตอนเช้า';
  else if (currentHour < 17) greeting = 'สวัสดีตอนบ่าย';
  else greeting = 'สวัสดีตอนเย็น';
  
  showNotification(greeting + '! คุณได้เสร็จงานไปแล้ว ' + <?= $totalCount ?> + ' งาน', 'success');
}, 1000);

window.addEventListener('beforeunload', function() {
  if (performanceChart) {
    performanceChart.destroy();
  }
});
</script>

</body>
</html>