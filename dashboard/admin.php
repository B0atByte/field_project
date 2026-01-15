<?php
require_once __DIR__ . '/../includes/session_config.php';
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

/* -------------------- SUMMARY -------------------- */
$summary = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status IS NULL OR status != 'completed' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) AS unassigned,
        SUM(CASE WHEN (status IS NULL OR status != 'completed') 
                  AND due_date IS NOT NULL 
                  AND DATE(due_date) < CURDATE() THEN 1 ELSE 0 END) AS overdue
    FROM jobs
")->fetch_assoc();

$jobs_total     = (int)($summary['total'] ?? 0);
$jobs_completed = (int)($summary['completed'] ?? 0);
$jobs_pending   = (int)($summary['pending'] ?? 0);
$jobs_unassigned= (int)($summary['unassigned'] ?? 0);
$jobs_overdue   = (int)($summary['overdue'] ?? 0);

$jobs_sent   = (int)($conn->query("SELECT COUNT(DISTINCT j.id) AS total 
                                   FROM jobs j JOIN job_logs jl ON j.id = jl.job_id")->fetch_assoc()['total'] ?? 0);
$jobs_unsent = max(0, $jobs_total - $jobs_sent);
$completion_rate = $jobs_total > 0 ? round(($jobs_completed / $jobs_total) * 100, 1) : 0;

/* -------------------- USERS BY ROLE -------------------- */
$user_counts = ['admin'=>0,'manager'=>0,'field'=>0];
$res_users = $conn->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
while ($row = $res_users->fetch_assoc()) { $user_counts[$row['role']] = (int)$row['total']; }

/* -------------------- JOBS BY DEPT -------------------- */
$dept_jobs = [];
$res = $conn->query("
    SELECT d.name, COUNT(j.id) AS count 
    FROM jobs j 
    LEFT JOIN departments d ON j.department_id = d.id 
    GROUP BY d.id
");
while ($row = $res->fetch_assoc()) $dept_jobs[] = $row;

/* -------------------- PRIORITY COUNTS -------------------- */
$priority_counts = ['urgent'=>0,'high'=>0,'normal'=>0];
$resp = $conn->query("SELECT priority, COUNT(*) c FROM jobs GROUP BY priority");
while ($r = $resp->fetch_assoc()) { $priority_counts[$r['priority'] ?: 'normal'] = (int)$r['c']; }

/* -------------------- 14-DAY TREND (Query เดียว) -------------------- */
$days = []; 
$createdSeries = []; 
$completedSeries = [];

$trendData = [];
$sql = "
    SELECT 
        DATE(created_at) AS d,
        COUNT(*) AS created_jobs,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_jobs
    FROM jobs
    WHERE created_at >= CURDATE() - INTERVAL 14 DAY
    GROUP BY DATE(created_at)
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $trendData[$row['d']] = [
        'created_jobs'   => (int)$row['created_jobs'],
        'completed_jobs' => (int)$row['completed_jobs']
    ];
}
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[] = $d;
    $createdSeries[]   = $trendData[$d]['created_jobs']   ?? 0;
    $completedSeries[] = $trendData[$d]['completed_jobs'] ?? 0;
}

/* -------------------- TOP FIELD PERFORMERS (ใช้ index updated_at) -------------------- */
$top_field = [];
$resTop = $conn->query("
    SELECT u.name, COUNT(j.id) AS completed_jobs
    FROM jobs j
    JOIN users u ON j.assigned_to = u.id
    WHERE u.role = 'field'
      AND j.status = 'completed'
      AND j.updated_at >= CURDATE() - INTERVAL 30 DAY
    GROUP BY u.id
    ORDER BY completed_jobs DESC
    LIMIT 5
");
while ($row = $resTop->fetch_assoc()) { $top_field[] = $row; }

/* -------------------- ALL FIELD WORKERS (นอกเหนือจาก Top 5) -------------------- */
$all_field = [];
$resAllField = $conn->query("
    SELECT u.id, u.name,
           COUNT(CASE WHEN j.status = 'completed' AND j.updated_at >= CURDATE() - INTERVAL 30 DAY THEN 1 END) AS completed_jobs
    FROM users u
    LEFT JOIN jobs j ON j.assigned_to = u.id
    WHERE u.role = 'field'
    GROUP BY u.id
    ORDER BY completed_jobs DESC, u.name ASC
    LIMIT 5, 999
");
while ($row = $resAllField->fetch_assoc()) { $all_field[] = $row; }

/* -------------------- สถิติการลงงานแบบละเอียด -------------------- */
$job_result_stats = $conn->query("
    SELECT
        COUNT(*) AS total_logs,
        SUM(CASE WHEN result LIKE '%พบผู้เช่า%' OR result LIKE '%พบผู้ค้ำ%' OR result LIKE '%พบผู้ครอบครอง%'
                 AND result NOT LIKE '%ไม่พบผู้เช่า%' AND result NOT LIKE '%ไม่พบผู้ค้ำ%'
                 AND result NOT LIKE '%พบรถ ไม่พบผู้เช่า%' THEN 1 ELSE 0 END) AS found_tenant,
        SUM(CASE WHEN result LIKE '%ไม่พบผู้เช่า%' OR result LIKE '%ไม่พบผู้ค้ำ%' OR result LIKE '%ไม่พบผู้ครอบครอง%' THEN 1 ELSE 0 END) AS not_found_tenant,
        SUM(CASE WHEN result LIKE '%พบที่ตั้ง%' AND result LIKE '%ไม่พบรถ%' THEN 1 ELSE 0 END) AS found_location_no_car,
        SUM(CASE WHEN result LIKE '%ไม่พบที่ตั้ง%' OR (result LIKE '%ไม่พบที่ตั้ง%' AND result LIKE '%ไม่พบรถ%') THEN 1 ELSE 0 END) AS not_found_location,
        SUM(CASE WHEN result LIKE '%พบ บ.3%' OR result LIKE '%ฝากเรื่องติดต่อกลับ%' OR result LIKE '%ฝากเรื่อง%' THEN 1 ELSE 0 END) AS found_relative,
        SUM(CASE WHEN result LIKE '%พบรถ%' AND result LIKE '%ไม่พบผู้เช่า%' THEN 1 ELSE 0 END) AS found_car_no_tenant,
        SUM(CASE WHEN result LIKE '%นัดชำระ%' THEN 1 ELSE 0 END) AS appointment_payment,
        SUM(CASE WHEN result LIKE '%นัดคืนรถ%' THEN 1 ELSE 0 END) AS appointment_return
    FROM job_logs
")->fetch_assoc();

$total_logs = (int)($job_result_stats['total_logs'] ?? 0);
$found_tenant = (int)($job_result_stats['found_tenant'] ?? 0);
$not_found_tenant = (int)($job_result_stats['not_found_tenant'] ?? 0);
$found_location_no_car = (int)($job_result_stats['found_location_no_car'] ?? 0);
$not_found_location = (int)($job_result_stats['not_found_location'] ?? 0);
$found_relative = (int)($job_result_stats['found_relative'] ?? 0);
$found_car_no_tenant = (int)($job_result_stats['found_car_no_tenant'] ?? 0);
$appointment_payment = (int)($job_result_stats['appointment_payment'] ?? 0);
$appointment_return = (int)($job_result_stats['appointment_return'] ?? 0);

// คำนวณ %
$found_tenant_pct = $total_logs > 0 ? round(($found_tenant / $total_logs) * 100, 1) : 0;
$not_found_tenant_pct = $total_logs > 0 ? round(($not_found_tenant / $total_logs) * 100, 1) : 0;
$found_location_no_car_pct = $total_logs > 0 ? round(($found_location_no_car / $total_logs) * 100, 1) : 0;
$not_found_location_pct = $total_logs > 0 ? round(($not_found_location / $total_logs) * 100, 1) : 0;
$found_relative_pct = $total_logs > 0 ? round(($found_relative / $total_logs) * 100, 1) : 0;
$found_car_no_tenant_pct = $total_logs > 0 ? round(($found_car_no_tenant / $total_logs) * 100, 1) : 0;
$appointment_payment_pct = $total_logs > 0 ? round(($appointment_payment / $total_logs) * 100, 1) : 0;
$appointment_return_pct = $total_logs > 0 ? round(($appointment_return / $total_logs) * 100, 1) : 0;

$page_title = "แดชบอร์ดผู้ดูแลระบบ - Field Project";
include '../components/header.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            'surface': '#f8fafc',
            'warning': '#f59e0b'
          },
          animation: {
            'fade-in': 'fadeIn 0.6s ease-out',
            'slide-up': 'slideUp 0.5s ease-out',
            'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite'
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
</head>
<body class="font-thai bg-surface min-h-screen">

<div class="flex min-h-screen bg-surface">
  <!-- Sidebar -->
  <?php include '../components/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="flex flex-col flex-1 ml-64">
    <!-- Enhanced Header -->
    <header class="bg-white shadow-lg border-b border-red-100 sticky top-0 z-40">
      <div class="px-6 py-4">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-gray-800 flex items-center">
              <span class="text-red-600 mr-3">📊</span>
              ภาพรวมระบบ
            </h1>
            <p class="text-sm text-gray-500 mt-1">สรุปสถานะและกิจกรรมล่าสุดของระบบ</p>
          </div>
          <div class="flex items-center gap-6">
            <!-- Real-time Status -->
            <div class="flex items-center text-sm text-green-600 bg-green-50 px-3 py-1 rounded-full border border-green-200">
              <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2" id="refreshIndicator"></div>
              <span class="font-medium" id="refreshStatus">อัปเดตอัตโนมัติ</span>
            </div>

            <!-- Auto-refresh Toggle -->
            <button onclick="toggleAutoRefresh()" id="autoRefreshBtn"
                    class="text-sm text-gray-600 hover:text-gray-800 px-3 py-1 rounded-lg hover:bg-gray-100 transition-all duration-200"
                    title="เปิด/ปิด การอัปเดตอัตโนมัติ">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
            </button>
            
            <!-- User Info -->
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
              </div>
              <span class="font-medium text-gray-700">สวัสดี, <?= htmlspecialchars($_SESSION['user']['name']) ?></span>
            </div>
            
            <!-- Logout Button -->
            <a href="../auth/logout.php" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-300 shadow-lg hover:shadow-xl font-medium">
              ออกจากระบบ
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- Body -->
    <main class="flex-1 p-6 space-y-8 overflow-y-auto">
      <!-- Summary Cards -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 animate-fade-in">
        
        <!-- Total Jobs Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 group relative overflow-hidden">
          <div class="absolute top-0 right-0 w-20 h-20 bg-blue-500 bg-opacity-10 rounded-full -mr-8 -mt-8"></div>
          <div class="relative z-10">
            <div class="flex items-center justify-between mb-4">
              <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                  <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a2 2 0 002 2h8a2 2 0 002-2V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                </svg>
              </div>
              <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">งานทั้งหมด</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 mb-2 transition-colors duration-300" data-counter="jobs_total"><?= $jobs_total ?></p>
            <div class="flex items-center justify-between">
              <p class="text-sm text-orange-600 font-medium">ยังไม่ส่ง: <?= $jobs_unsent ?></p>
              <div class="text-right">
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div class="bg-blue-500 h-2 rounded-full" style="width: <?= $jobs_total > 0 ? (($jobs_total - $jobs_unsent) / $jobs_total) * 100 : 0 ?>%;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Completed Jobs Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 group relative overflow-hidden">
          <div class="absolute top-0 right-0 w-20 h-20 bg-green-500 bg-opacity-10 rounded-full -mr-8 -mt-8"></div>
          <div class="relative z-10">
            <div class="flex items-center justify-between mb-4">
              <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
              </div>
              <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">งานที่เสร็จแล้ว</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 mb-2"><?= $jobs_completed ?></p>
            <div class="flex items-center justify-between">
              <p class="text-sm text-green-600 font-medium">อัตราสำเร็จ: <?= $completion_rate ?>%</p>
              <div class="text-right">
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div class="bg-green-500 h-2 rounded-full" style="width: <?= $completion_rate ?>%;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pending Jobs Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 group relative overflow-hidden">
          <div class="absolute top-0 right-0 w-20 h-20 bg-yellow-500 bg-opacity-10 rounded-full -mr-8 -mt-8"></div>
          <div class="relative z-10">
            <div class="flex items-center justify-between mb-4">
              <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
              </div>
              <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">รอดำเนินการ</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 mb-2"><?= $jobs_pending ?></p>
            <div class="flex items-center justify-between">
              <p class="text-sm text-blue-600 font-medium">ส่งแล้ว: <?= $jobs_sent ?></p>
              <div class="text-right">
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div class="bg-yellow-500 h-2 rounded-full" style="width: <?= $jobs_total > 0 ? ($jobs_pending / $jobs_total) * 100 : 0 ?>%;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Unassigned Jobs Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 group relative overflow-hidden">
          <div class="absolute top-0 right-0 w-20 h-20 bg-red-500 bg-opacity-10 rounded-full -mr-8 -mt-8"></div>
          <div class="relative z-10">
            <div class="flex items-center justify-between mb-4">
              <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
              </div>
              <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">ยังไม่ได้รับ</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 mb-2"><?= $jobs_unassigned ?></p>
            <div class="flex items-center justify-between">
              <p class="text-sm text-red-600 font-medium">คงเหลือ: <?= $jobs_overdue ?></p>
              <div class="text-right">
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div class="bg-red-500 h-2 rounded-full" style="width: <?= $jobs_total > 0 ? ($jobs_unassigned / $jobs_total) * 100 : 0 ?>%;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Charts Section: Trend + Status -->
      <section class="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-slide-up" style="animation-delay: 0.2s;">
        
        <!-- Trend Chart (2/3 width) -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-shadow duration-300">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
              <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <span class="text-2xl mr-3"></span>
                เทรนด์ 14 วัน: ลงงาน vs เสร็จงาน
              </h2>
              <p class="text-sm text-gray-500 mt-1">ติดตามแนวโน้มการสร้างและการปิดงาน</p>
            </div>
            <div class="flex items-center mt-3 sm:mt-0 space-x-4">
              <div class="flex items-center">
                <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-600 font-medium">สร้างงาน</span>
              </div>
              <div class="flex items-center">
                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-600 font-medium">เสร็จงาน</span>
              </div>
              <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">อัปเดตอัตโนมัติ</span>
            </div>
          </div>
          <div class="h-80">
            <canvas id="trendChart"></canvas>
          </div>
        </div>

        <!-- Status Doughnut Chart (1/3 width) -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-shadow duration-300">
          <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-800 flex items-center">
              <span class="text-2xl mr-3"></span>
              สถานะงาน
            </h2>
            <p class="text-sm text-gray-500 mt-1">การกระจายตามสถานะปัจจุบัน</p>
          </div>
          <div class="h-80 flex items-center justify-center">
            <canvas id="statusChart"></canvas>
          </div>
        </div>
      </section>

      <!-- Job Results Stats Section -->
      <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up hover:shadow-xl transition-shadow duration-300" style="animation-delay: 0.3s;">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center">
              <span class="text-2xl mr-3">📊</span>
              สรุปผลการลงงาน - สถิติแยกตามประเภท
            </h2>
            <p class="text-sm text-gray-500 mt-1">สรุปข้อมูลการลงงานแยกตามผลลัพธ์</p>
          </div>
          <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700">เลือกเดือน:</label>
            <input type="month" id="statsMonthPicker" value="<?= date('Y-m') ?>"
                   class="px-4 py-2 border-2 border-blue-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all bg-white font-medium text-gray-700 shadow-sm hover:border-blue-300">
          </div>
        </div>

        <!-- Total Logs (Full Width) -->
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200 mb-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                  <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div>
                <p id="totalLogsCount" class="text-4xl font-bold text-blue-800"><?= $total_logs ?></p>
                <p class="text-sm text-blue-700 font-medium">ยอดลงงานทั้งหมด</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats Grid (8 Cards) -->
        <div id="statsCardsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <!-- 1. พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง -->
          <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-5 border border-green-200 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                </svg>
              </div>
            </div>
            <p class="text-2xl font-bold text-green-800 mb-1"><?= $found_tenant ?> <span class="text-sm text-green-600">(<?= $found_tenant_pct ?>%)</span></p>
            <p class="text-xs text-green-700 font-medium break-words">พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง</p>
            <div class="mt-2">
              <div class="w-full bg-green-200 rounded-full h-1.5">
                <div class="bg-green-600 h-1.5 rounded-full transition-all duration-500" style="width: <?= $found_tenant_pct ?>%;"></div>
              </div>
            </div>
          </div>

          <!-- 2. ไม่พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง -->
          <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 border border-red-200 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
                </svg>
              </div>
            </div>
            <p class="text-2xl font-bold text-red-800 mb-1"><?= $not_found_tenant ?> <span class="text-sm text-red-600">(<?= $not_found_tenant_pct ?>%)</span></p>
            <p class="text-xs text-red-700 font-medium break-words">ไม่พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง</p>
            <div class="mt-2">
              <div class="w-full bg-red-200 rounded-full h-1.5">
                <div class="bg-red-600 h-1.5 rounded-full transition-all duration-500" style="width: <?= $not_found_tenant_pct ?>%;"></div>
              </div>
            </div>
          </div>

          <!-- 3. พบที่ตั้ง/ไม่พบรถ -->
          <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-5 border border-yellow-200 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-10 h-10 bg-yellow-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                </svg>
              </div>
            </div>
            <p class="text-2xl font-bold text-yellow-800 mb-1"><?= $found_location_no_car ?> <span class="text-sm text-yellow-600">(<?= $found_location_no_car_pct ?>%)</span></p>
            <p class="text-xs text-yellow-700 font-medium">พบที่ตั้ง/ไม่พบรถ</p>
            <div class="mt-2">
              <div class="w-full bg-yellow-200 rounded-full h-1.5">
                <div class="bg-yellow-600 h-1.5 rounded-full transition-all duration-500" style="width: <?= $found_location_no_car_pct ?>%;"></div>
              </div>
            </div>
          </div>

          <!-- 4. ไม่พบที่ตั้ง/ไม่พบรถ -->
          <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5 border border-gray-200 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-10 h-10 bg-gray-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
              </div>
            </div>
            <p class="text-2xl font-bold text-gray-800 mb-1"><?= $not_found_location ?> <span class="text-sm text-gray-600">(<?= $not_found_location_pct ?>%)</span></p>
            <p class="text-xs text-gray-700 font-medium">ไม่พบที่ตั้ง/ไม่พบรถ</p>
            <div class="mt-2">
              <div class="w-full bg-gray-200 rounded-full h-1.5">
                <div class="bg-gray-600 h-1.5 rounded-full transition-all duration-500" style="width: <?= $not_found_location_pct ?>%;"></div>
              </div>
            </div>
          </div>

          <!-- 5. พบ บ.3 ฝากเรื่องติดต่อกลับ -->
          <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-5 border border-purple-200 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                </svg>
              </div>
            </div>
            <p class="text-2xl font-bold text-purple-800 mb-1"><?= $found_relative ?> <span class="text-sm text-purple-600">(<?= $found_relative_pct ?>%)</span></p>
            <p class="text-xs text-purple-700 font-medium">พบ บ.3 ฝากเรื่องติดต่อกลับ</p>
            <div class="mt-2">
              <div class="w-full bg-purple-200 rounded-full h-1.5">
                <div class="bg-purple-600 h-1.5 rounded-full transition-all duration-500" style="width: <?= $found_relative_pct ?>%;"></div>
              </div>
            </div>
          </div>

          <!-- 6. พบรถ ไม่พบผู้เช่า/ผู้ค้ำ -->
          <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-5 border border-orange-200 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-10 h-10 bg-orange-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                  <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z"/>
                </svg>
              </div>
            </div>
            <p class="text-2xl font-bold text-orange-800 mb-1"><?= $found_car_no_tenant ?> <span class="text-sm text-orange-600">(<?= $found_car_no_tenant_pct ?>%)</span></p>
            <p class="text-xs text-orange-700 font-medium break-words">พบรถ ไม่พบผู้เช่า/ผู้ค้ำ</p>
            <div class="mt-2">
              <div class="w-full bg-orange-200 rounded-full h-1.5">
                <div class="bg-orange-600 h-1.5 rounded-full transition-all duration-500" style="width: <?= $found_car_no_tenant_pct ?>%;"></div>
              </div>
            </div>
          </div>

          <!-- 7. นัดชำระ -->
          <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl p-5 border border-teal-200 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-10 h-10 bg-teal-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                </svg>
              </div>
            </div>
            <p class="text-2xl font-bold text-teal-800 mb-1"><?= $appointment_payment ?> <span class="text-sm text-teal-600">(<?= $appointment_payment_pct ?>%)</span></p>
            <p class="text-xs text-teal-700 font-medium">นัดชำระ</p>
            <div class="mt-2">
              <div class="w-full bg-teal-200 rounded-full h-1.5">
                <div class="bg-teal-600 h-1.5 rounded-full transition-all duration-500" style="width: <?= $appointment_payment_pct ?>%;"></div>
              </div>
            </div>
          </div>

          <!-- 8. นัดคืนรถ -->
          <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl p-5 border border-indigo-200 hover:shadow-lg transition-shadow">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
              </div>
            </div>
            <p class="text-2xl font-bold text-indigo-800 mb-1"><?= $appointment_return ?> <span class="text-sm text-indigo-600">(<?= $appointment_return_pct ?>%)</span></p>
            <p class="text-xs text-indigo-700 font-medium">นัดคืนรถ</p>
            <div class="mt-2">
              <div class="w-full bg-indigo-200 rounded-full h-1.5">
                <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-500" style="width: <?= $appointment_return_pct ?>%;"></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Bottom Section: Department + Top Performers -->
      <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 animate-slide-up" style="animation-delay: 0.4s;">

        <!-- Department Chart -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-shadow duration-300">
          <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-800 flex items-center">
              <span class="text-2xl mr-3"></span>
              งานตามแผนก
            </h2>
            <p class="text-sm text-gray-500 mt-1">จำนวนงานที่กระจายตามแผนกต่างๆ</p>
          </div>
          <div class="h-80">
            <canvas id="deptChart"></canvas>
          </div>
        </div>

        <!-- Top Field Performers -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-shadow duration-300">
          <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                  <span class="text-2xl mr-3">🏆</span>
                  Top 5 ภาคสนาม
                </h2>
                <p class="text-sm text-gray-500 mt-1">ผู้ปฏิบัติงานดีเด่นประจำเดือน</p>
              </div>
              <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full">นับจำนวนงานที่ปิดสำเร็จ</span>
            </div>
            <div class="flex items-center gap-3">
              <label class="text-sm font-medium text-gray-700">เลือกเดือน:</label>
              <input type="month" id="topFieldMonthPicker" value="<?= date('Y-m') ?>"
                     class="px-4 py-2 border-2 border-green-200 rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all bg-white font-medium text-gray-700 shadow-sm hover:border-green-300">
            </div>
          </div>

          <div id="topFieldContainer" class="space-y-4 max-h-80 overflow-y-auto">
            <?php if (empty($top_field)): ?>
              <div class="text-center py-8">
                <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                  <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                </div>
                <p class="text-gray-500 text-sm">ยังไม่มีข้อมูลช่วงนี้</p>
              </div>
            <?php else:
              $i = 1;
              foreach ($top_field as $t):
                $badgeColors = [
                  1 => 'from-yellow-400 to-yellow-600',
                  2 => 'from-gray-400 to-gray-600',
                  3 => 'from-orange-400 to-orange-600'
                ];
                $bgColors = [
                  1 => 'from-yellow-50 to-orange-50 border-yellow-200',
                  2 => 'from-gray-50 to-gray-100 border-gray-200',
                  3 => 'from-orange-50 to-red-50 border-orange-200'
                ];
            ?>
              <div class="flex items-center p-4 bg-gradient-to-r <?= $bgColors[$i] ?? 'from-gray-50 to-gray-100 border-gray-200' ?> rounded-xl border transition-transform duration-200 hover:scale-105">
                <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-r <?= $badgeColors[$i] ?? 'from-gray-400 to-gray-600' ?> text-white rounded-xl flex items-center justify-center font-bold text-lg shadow-lg">
                  <?= $i ?>
                </div>
                <div class="ml-4 flex-1">
                  <h3 class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($t['name']) ?></h3>
                  <p class="text-sm text-gray-600">ภาคสนาม</p>
                </div>
                <div class="text-right">
                  <p class="text-2xl font-bold text-gray-800"><?= (int)$t['completed_jobs'] ?></p>
                  <p class="text-xs text-gray-500">งานเสร็จ</p>
                </div>
              </div>
            <?php
              $i++;
              endforeach;
            endif; ?>

            <!-- รายชื่อภาคสนามทั้งหมด (นอกเหนือจาก Top 5) -->
            <?php if (!empty($all_field)): ?>
              <div class="pt-4 mt-4 border-t border-gray-200">
                <h3 class="text-md font-semibold text-gray-700 mb-3 flex items-center">
                  <span class="text-lg mr-2">👥</span>
                  รายชื่อภาคสนามทั้งหมด
                </h3>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                  <?php foreach ($all_field as $f): ?>
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors duration-200">
                      <div class="flex-shrink-0 w-8 h-8 bg-gray-300 text-gray-700 rounded-lg flex items-center justify-center font-medium text-sm">
                        <i class="fas fa-user"></i>
                      </div>
                      <div class="ml-3 flex-1">
                        <h4 class="font-medium text-gray-800"><?= htmlspecialchars($f['name']) ?></h4>
                        <p class="text-xs text-gray-500">ภาคสนาม</p>
                      </div>
                      <div class="text-right">
                        <p class="text-lg font-bold text-gray-700"><?= (int)$f['completed_jobs'] ?></p>
                        <p class="text-xs text-gray-500">งานเสร็จ</p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- Quick Stats Footer -->
      <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up" style="animation-delay: 0.6s;">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
          <div class="group">
            <div class="w-12 h-12 bg-blue-100 rounded-xl mx-auto mb-3 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"/>
              </svg>
            </div>
            <p class="text-2xl font-bold text-blue-600 mb-1"><?= $user_counts['admin'] ?></p>
            <p class="text-sm text-gray-600 font-medium">ผู้ดูแลระบบ</p>
          </div>
          <div class="group">
            <div class="w-12 h-12 bg-green-100 rounded-xl mx-auto mb-3 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
              </svg>
            </div>
            <p class="text-2xl font-bold text-green-600 mb-1"><?= $user_counts['manager'] ?></p>
            <p class="text-sm text-gray-600 font-medium">ผู้จัดการ</p>
          </div>
          <div class="group">
            <div class="w-12 h-12 bg-purple-100 rounded-xl mx-auto mb-3 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/>
              </svg>
            </div>
            <p class="text-2xl font-bold text-purple-600 mb-1"><?= $user_counts['field'] ?></p>
            <p class="text-sm text-gray-600 font-medium">ภาคสนาม</p>
          </div>
          <div class="group">
            <div class="w-12 h-12 bg-red-100 rounded-xl mx-auto mb-3 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2v1h12V6H4zm0 3v5h12v-5H4z" clip-rule="evenodd"/>
              </svg>
            </div>
            <p class="text-2xl font-bold text-red-600 mb-1"><?= count($dept_jobs) ?></p>
            <p class="text-sm text-gray-600 font-medium">แผนกงาน</p>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<?php include '../components/footer.php'; ?>

<script>
/* Trend Chart */
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($days) ?>.map(d => d.substr(5)),
    datasets: [
      { 
        label: 'สร้างงาน', 
        data: <?= json_encode($createdSeries) ?>, 
        borderColor: '#3b82f6', 
        backgroundColor: 'rgba(59,130,246,.15)', 
        fill: true, 
        tension: 0.4, 
        pointRadius: 4,
        pointHoverRadius: 6,
        borderWidth: 3,
        pointBackgroundColor: '#3b82f6',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2
      },
      { 
        label: 'เสร็จงาน', 
        data: <?= json_encode($completedSeries) ?>, 
        borderColor: '#10b981', 
        backgroundColor: 'rgba(16,185,129,.15)', 
        fill: true, 
        tension: 0.4, 
        pointRadius: 4,
        pointHoverRadius: 6,
        borderWidth: 3,
        pointBackgroundColor: '#10b981',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2
      }
    ]
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
        titleColor: 'white',
        bodyColor: 'white',
        borderColor: 'rgba(255, 255, 255, 0.1)',
        borderWidth: 1,
        cornerRadius: 8,
        displayColors: true
      }
    },
    scales: { 
      y: { 
        beginAtZero: true,
        grid: {
          color: 'rgba(0, 0, 0, 0.05)'
        },
        ticks: {
          color: '#6b7280'
        }
      },
      x: {
        grid: {
          color: 'rgba(0, 0, 0, 0.05)'
        },
        ticks: {
          color: '#6b7280'
        }
      }
    },
    interaction: {
      intersect: false,
      mode: 'index'
    }
  }
});

/* Status Doughnut Chart */
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: ['เสร็จแล้ว','รอดำเนินการ','ยังไม่ได้รับ','ค้าง'],
    datasets: [{
      data: [<?= $jobs_completed ?>, <?= $jobs_pending ?>, <?= $jobs_unassigned ?>, <?= $jobs_overdue ?>],
      backgroundColor: ['#10B981','#F59E0B','#6366F1','#EF4444'],
      borderWidth: 0,
      hoverOffset: 8
    }]
  },
  options: { 
    responsive: true, 
    maintainAspectRatio: false,
    plugins: { 
      legend: { 
        position: 'bottom',
        labels: {
          padding: 20,
          usePointStyle: true,
          font: {
            family: 'Prompt',
            size: 12
          }
        }
      },
      tooltip: {
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        titleColor: 'white',
        bodyColor: 'white',
        borderColor: 'rgba(255, 255, 255, 0.1)',
        borderWidth: 1,
        cornerRadius: 8
      }
    }, 
    cutout: '60%'
  }
});

/* Department Bar Chart */
new Chart(document.getElementById('deptChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($dept_jobs, 'name')) ?>,
    datasets: [{ 
      label: 'จำนวนงาน', 
      data: <?= json_encode(array_map('intval', array_column($dept_jobs, 'count'))) ?>, 
      backgroundColor: '#3B82F6',
      borderRadius: 8,
      borderSkipped: false,
      borderWidth: 0
    }]
  },
  options: { 
    responsive: true, 
    maintainAspectRatio: false, 
    indexAxis: 'y',
    plugins: { 
      legend: { 
        display: false 
      },
      tooltip: {
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        titleColor: 'white',
        bodyColor: 'white',
        borderColor: 'rgba(255, 255, 255, 0.1)',
        borderWidth: 1,
        cornerRadius: 8
      }
    },
    scales: {
      x: {
        beginAtZero: true,
        grid: {
          color: 'rgba(0, 0, 0, 0.05)'
        },
        ticks: {
          color: '#6b7280'
        }
      },
      y: {
        grid: {
          display: false
        },
        ticks: {
          color: '#6b7280'
        }
      }
    }
  }
});

// Real-time Data Refresh
let refreshInterval = null;
const REFRESH_INTERVAL = 30000; // 30 seconds

function startAutoRefresh() {
  refreshInterval = setInterval(() => {
    refreshDashboardData();
  }, REFRESH_INTERVAL);
  console.log('🔄 Auto-refresh enabled (every 30 seconds)');
}

function stopAutoRefresh() {
  if (refreshInterval) {
    clearInterval(refreshInterval);
    refreshInterval = null;
    console.log('⏸️ Auto-refresh disabled');
  }
}

async function refreshDashboardData() {
  try {
    const response = await fetch('api/dashboard_realtime.php');
    const data = await response.json();

    if (data.success) {
      // Update summary cards
      updateCounter('jobs_total', data.summary.total);
      updateCounter('jobs_completed', data.summary.completed);
      updateCounter('jobs_pending', data.summary.pending);
      updateCounter('jobs_unassigned', data.summary.unassigned);

      // Update progress bars
      updateProgressBar('completion_rate', data.summary.completion_rate);
      updateProgressBar('sent_rate', data.summary.sent_rate);

      console.log('✅ Dashboard data refreshed at ' + new Date().toLocaleTimeString());
    }
  } catch (error) {
    console.error('❌ Failed to refresh dashboard data:', error);
  }
}

function updateCounter(elementId, newValue) {
  const element = document.querySelector(`[data-counter="${elementId}"]`);
  if (element) {
    const currentValue = parseInt(element.textContent);
    if (currentValue !== newValue) {
      animateCounterUpdate(element, currentValue, newValue);
    }
  }
}

function animateCounterUpdate(element, from, to) {
  const duration = 800;
  const steps = 30;
  const stepValue = (to - from) / steps;
  let current = from;
  let step = 0;

  const timer = setInterval(() => {
    current += stepValue;
    step++;

    if (step >= steps) {
      element.textContent = to;
      clearInterval(timer);
      element.classList.add('text-green-600');
      setTimeout(() => element.classList.remove('text-green-600'), 1000);
    } else {
      element.textContent = Math.floor(current);
    }
  }, duration / steps);
}

function updateProgressBar(type, percentage) {
  const bar = document.querySelector(`[data-progress="${type}"]`);
  if (bar) {
    bar.style.width = percentage + '%';
  }
}

// Add loading animations
document.addEventListener('DOMContentLoaded', function() {
  // Animate counters
  function animateCounter(element, target) {
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        current = target;
        clearInterval(timer);
      }
      element.textContent = Math.floor(current);
    }, 30);
  }

  // Animate all counter elements
  const counters = document.querySelectorAll('[class*="text-3xl"][class*="font-bold"]');
  counters.forEach(counter => {
    const target = parseInt(counter.textContent);
    if (!isNaN(target)) {
      counter.textContent = '0';
      setTimeout(() => animateCounter(counter, target), 500);
    }
  });

  // Start auto-refresh after initial load
  setTimeout(() => {
    startAutoRefresh();
  }, 5000);

  // ==== Month Picker for Job Stats ====
  const statsMonthPicker = document.getElementById('statsMonthPicker');
  if (statsMonthPicker) {
    statsMonthPicker.addEventListener('change', function() {
      loadJobStatsByMonth(this.value);
    });
  }

  // ==== Month Picker for Top Field ====
  const topFieldMonthPicker = document.getElementById('topFieldMonthPicker');
  if (topFieldMonthPicker) {
    topFieldMonthPicker.addEventListener('change', function() {
      loadTopFieldByMonth(this.value);
    });
  }
});

// ==== Load Job Stats by Month ====
function loadJobStatsByMonth(month) {
  const container = document.getElementById('statsCardsContainer');
  const totalLogsCount = document.getElementById('totalLogsCount');

  // Show loading
  container.innerHTML = '<div class="col-span-full text-center py-12"><div class="inline-block w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div><p class="mt-4 text-gray-600">กำลังโหลดข้อมูล...</p></div>';

  fetch(`api/get_job_stats_by_month.php?month=${month}`)
    .then(res => res.json())
    .then(data => {
      if (!data.success) throw new Error(data.message);

      // Update total logs
      totalLogsCount.textContent = data.total_logs;

      // Build cards HTML
      const stats = data.stats;
      const cardsHTML = `
        <!-- 1. พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง -->
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-5 border border-green-200 hover:shadow-lg transition-shadow">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
              </svg>
            </div>
          </div>
          <p class="text-2xl font-bold text-green-800 mb-1">${stats.found_tenant.count} <span class="text-sm text-green-600">(${stats.found_tenant.pct}%)</span></p>
          <p class="text-xs text-green-700 font-medium break-words">พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง</p>
          <div class="mt-2">
            <div class="w-full bg-green-200 rounded-full h-1.5">
              <div class="bg-green-600 h-1.5 rounded-full transition-all duration-500" style="width: ${stats.found_tenant.pct}%;"></div>
            </div>
          </div>
        </div>

        <!-- 2. ไม่พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง -->
        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 border border-red-200 hover:shadow-lg transition-shadow">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
              </svg>
            </div>
          </div>
          <p class="text-2xl font-bold text-red-800 mb-1">${stats.not_found_tenant.count} <span class="text-sm text-red-600">(${stats.not_found_tenant.pct}%)</span></p>
          <p class="text-xs text-red-700 font-medium break-words">ไม่พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง</p>
          <div class="mt-2">
            <div class="w-full bg-red-200 rounded-full h-1.5">
              <div class="bg-red-600 h-1.5 rounded-full transition-all duration-500" style="width: ${stats.not_found_tenant.pct}%;"></div>
            </div>
          </div>
        </div>

        <!-- 3. พบที่ตั้ง/ไม่พบรถ -->
        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-5 border border-yellow-200 hover:shadow-lg transition-shadow">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-10 h-10 bg-yellow-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
              </svg>
            </div>
          </div>
          <p class="text-2xl font-bold text-yellow-800 mb-1">${stats.found_location_no_car.count} <span class="text-sm text-yellow-600">(${stats.found_location_no_car.pct}%)</span></p>
          <p class="text-xs text-yellow-700 font-medium">พบที่ตั้ง/ไม่พบรถ</p>
          <div class="mt-2">
            <div class="w-full bg-yellow-200 rounded-full h-1.5">
              <div class="bg-yellow-600 h-1.5 rounded-full transition-all duration-500" style="width: ${stats.found_location_no_car.pct}%;"></div>
            </div>
          </div>
        </div>

        <!-- 4. ไม่พบที่ตั้ง/ไม่พบรถ -->
        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5 border border-gray-200 hover:shadow-lg transition-shadow">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-10 h-10 bg-gray-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
              </svg>
            </div>
          </div>
          <p class="text-2xl font-bold text-gray-800 mb-1">${stats.not_found_location.count} <span class="text-sm text-gray-600">(${stats.not_found_location.pct}%)</span></p>
          <p class="text-xs text-gray-700 font-medium">ไม่พบที่ตั้ง/ไม่พบรถ</p>
          <div class="mt-2">
            <div class="w-full bg-gray-200 rounded-full h-1.5">
              <div class="bg-gray-600 h-1.5 rounded-full transition-all duration-500" style="width: ${stats.not_found_location.pct}%;"></div>
            </div>
          </div>
        </div>

        <!-- 5. พบ บ.3 ฝากเรื่องติดต่อกลับ -->
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-5 border border-purple-200 hover:shadow-lg transition-shadow">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
              </svg>
            </div>
          </div>
          <p class="text-2xl font-bold text-purple-800 mb-1">${stats.found_relative.count} <span class="text-sm text-purple-600">(${stats.found_relative.pct}%)</span></p>
          <p class="text-xs text-purple-700 font-medium">พบ บ.3 ฝากเรื่องติดต่อกลับ</p>
          <div class="mt-2">
            <div class="w-full bg-purple-200 rounded-full h-1.5">
              <div class="bg-purple-600 h-1.5 rounded-full transition-all duration-500" style="width: ${stats.found_relative.pct}%;"></div>
            </div>
          </div>
        </div>

        <!-- 6. พบรถ ไม่พบผู้เช่า/ผู้ค้ำ -->
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-5 border border-orange-200 hover:shadow-lg transition-shadow">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-10 h-10 bg-orange-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z"/>
              </svg>
            </div>
          </div>
          <p class="text-2xl font-bold text-orange-800 mb-1">${stats.found_car_no_tenant.count} <span class="text-sm text-orange-600">(${stats.found_car_no_tenant.pct}%)</span></p>
          <p class="text-xs text-orange-700 font-medium break-words">พบรถ ไม่พบผู้เช่า/ผู้ค้ำ</p>
          <div class="mt-2">
            <div class="w-full bg-orange-200 rounded-full h-1.5">
              <div class="bg-orange-600 h-1.5 rounded-full transition-all duration-500" style="width: ${stats.found_car_no_tenant.pct}%;"></div>
            </div>
          </div>
        </div>

        <!-- 7. นัดชำระ -->
        <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl p-5 border border-teal-200 hover:shadow-lg transition-shadow">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-10 h-10 bg-teal-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
              </svg>
            </div>
          </div>
          <p class="text-2xl font-bold text-teal-800 mb-1">${stats.appointment_payment.count} <span class="text-sm text-teal-600">(${stats.appointment_payment.pct}%)</span></p>
          <p class="text-xs text-teal-700 font-medium">นัดชำระ</p>
          <div class="mt-2">
            <div class="w-full bg-teal-200 rounded-full h-1.5">
              <div class="bg-teal-600 h-1.5 rounded-full transition-all duration-500" style="width: ${stats.appointment_payment.pct}%;"></div>
            </div>
          </div>
        </div>

        <!-- 8. นัดคืนรถ -->
        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl p-5 border border-indigo-200 hover:shadow-lg transition-shadow">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
              </svg>
            </div>
          </div>
          <p class="text-2xl font-bold text-indigo-800 mb-1">${stats.appointment_return.count} <span class="text-sm text-indigo-600">(${stats.appointment_return.pct}%)</span></p>
          <p class="text-xs text-indigo-700 font-medium">นัดคืนรถ</p>
          <div class="mt-2">
            <div class="w-full bg-indigo-200 rounded-full h-1.5">
              <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-500" style="width: ${stats.appointment_return.pct}%;"></div>
            </div>
          </div>
        </div>
      `;

      container.innerHTML = cardsHTML;
    })
    .catch(err => {
      container.innerHTML = '<div class="col-span-full text-center py-8 text-red-600">เกิดข้อผิดพลาด: ' + err.message + '</div>';
    });
}

// ==== Auto-refresh Toggle ====
function toggleAutoRefresh() {
  if (refreshInterval) {
    stopAutoRefresh();
    document.getElementById('refreshStatus').textContent = 'อัปเดตด้วยตนเอง';
    document.getElementById('refreshIndicator').classList.remove('animate-pulse');
    document.getElementById('autoRefreshBtn').classList.add('bg-gray-200');
  } else {
    startAutoRefresh();
    document.getElementById('refreshStatus').textContent = 'อัปเดตอัตโนมัติ';
    document.getElementById('refreshIndicator').classList.add('animate-pulse');
    document.getElementById('autoRefreshBtn').classList.remove('bg-gray-200');
  }
}

// ==== Load Top Field by Month ====
function loadTopFieldByMonth(month) {
  const container = document.getElementById('topFieldContainer');

  // Show loading
  container.innerHTML = '<div class="text-center py-12"><div class="inline-block w-8 h-8 border-4 border-green-500 border-t-transparent rounded-full animate-spin"></div><p class="mt-4 text-gray-600">กำลังโหลดข้อมูล...</p></div>';

  fetch(`api/get_top_field_by_month.php?month=${month}`)
    .then(res => res.json())
    .then(data => {
      if (!data.success) throw new Error(data.message);

      let html = '';

      // Top 5
      if (data.top_field.length === 0) {
        html = `
          <div class="text-center py-8">
            <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <p class="text-gray-500 text-sm">ยังไม่มีข้อมูลช่วงนี้</p>
          </div>
        `;
      } else {
        const badgeColors = {
          1: 'from-yellow-400 to-yellow-600',
          2: 'from-gray-400 to-gray-600',
          3: 'from-orange-400 to-orange-600'
        };
        const bgColors = {
          1: 'from-yellow-50 to-orange-50 border-yellow-200',
          2: 'from-gray-50 to-gray-100 border-gray-200',
          3: 'from-orange-50 to-red-50 border-orange-200'
        };

        data.top_field.forEach((t, i) => {
          const rank = i + 1;
          const badgeColor = badgeColors[rank] || 'from-gray-400 to-gray-600';
          const bgColor = bgColors[rank] || 'from-gray-50 to-gray-100 border-gray-200';

          html += `
            <div class="flex items-center p-4 bg-gradient-to-r ${bgColor} rounded-xl border transition-transform duration-200 hover:scale-105">
              <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-r ${badgeColor} text-white rounded-xl flex items-center justify-center font-bold text-lg shadow-lg">
                ${rank}
              </div>
              <div class="ml-4 flex-1">
                <h3 class="font-bold text-gray-800 text-lg">${t.name}</h3>
                <p class="text-sm text-gray-600">ภาคสนาม</p>
              </div>
              <div class="text-right">
                <p class="text-2xl font-bold text-gray-800">${t.completed_jobs}</p>
                <p class="text-xs text-gray-500">งานเสร็จ</p>
              </div>
            </div>
          `;
        });
      }

      // All Field (outside Top 5)
      if (data.all_field.length > 0) {
        html += `
          <div class="pt-4 mt-4 border-t border-gray-200">
            <h3 class="text-md font-semibold text-gray-700 mb-3 flex items-center">
              <span class="text-lg mr-2">👥</span>
              รายชื่อภาคสนามทั้งหมด
            </h3>
            <div class="space-y-2 max-h-60 overflow-y-auto">
        `;

        data.all_field.forEach(f => {
          html += `
            <div class="flex items-center p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors duration-200">
              <div class="flex-shrink-0 w-8 h-8 bg-gray-300 text-gray-700 rounded-lg flex items-center justify-center font-medium text-sm">
                <i class="fas fa-user"></i>
              </div>
              <div class="ml-3 flex-1">
                <h4 class="font-medium text-gray-800">${f.name}</h4>
                <p class="text-xs text-gray-500">ภาคสนาม</p>
              </div>
              <div class="text-right">
                <p class="text-lg font-bold text-gray-700">${f.completed_jobs}</p>
                <p class="text-xs text-gray-500">งานเสร็จ</p>
              </div>
            </div>
          `;
        });

        html += `
            </div>
          </div>
        `;
      }

      container.innerHTML = html;
    })
    .catch(err => {
      container.innerHTML = '<div class="text-center py-8 text-red-600">เกิดข้อผิดพลาด: ' + err.message + '</div>';
    });
}
</script>

</body>
</html>