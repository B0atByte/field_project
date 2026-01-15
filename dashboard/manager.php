<?php
require_once __DIR__ . '/../includes/session_config.php';
if ($_SESSION['user']['role'] !== 'manager') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

// Get user info
$user_id = $_SESSION['user']['id'];

// department หลัก
$stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($dept_id);
$stmt->fetch();
$stmt->close();

// department ที่ manager เห็นได้
$visible_dept_ids = [$dept_id];
$stmt = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $visible_dept_ids[] = $row['to_department_id'];
}
$dept_placeholders = implode(',', array_fill(0, count($visible_dept_ids), '?'));
$types = str_repeat('i', count($visible_dept_ids));

// Jobs summary
$stmt = $conn->prepare("SELECT COUNT(*) FROM jobs WHERE department_id IN ($dept_placeholders)");
$stmt->bind_param($types, ...$visible_dept_ids);
$stmt->execute();
$stmt->bind_result($total_jobs);
$stmt->fetch();
$stmt->close();

// Jobs by status
$status_counts = ['completed' => 0, 'pending' => 0];
foreach (['completed', 'pending'] as $status) {
    if ($status === 'pending') {
        $sql = "SELECT COUNT(*) FROM jobs WHERE department_id IN ($dept_placeholders) AND (status IS NULL OR status != 'completed')";
    } else {
        $sql = "SELECT COUNT(*) FROM jobs WHERE department_id IN ($dept_placeholders) AND status = 'completed'";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$visible_dept_ids);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    $status_counts[$status] = $count;
}

// ส่งแล้ว/ยังไม่ส่ง
$sql = "SELECT 
            SUM(EXISTS (SELECT 1 FROM job_logs jl WHERE jl.job_id = j.id)) AS sent_count,
            SUM(NOT EXISTS (SELECT 1 FROM job_logs jl WHERE jl.job_id = j.id)) AS unsent_count
        FROM jobs j
        WHERE j.department_id IN ($dept_placeholders)";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$visible_dept_ids);
$stmt->execute();
$stmt->bind_result($sent_jobs, $unsent_jobs);
$stmt->fetch();
$stmt->close();

// งานแยกตามแผนก
$dept_job_counts = [];
$sql = "SELECT d.name, COUNT(*) as count
        FROM jobs j
        JOIN departments d ON j.department_id = d.id
        WHERE j.department_id IN ($dept_placeholders)
        GROUP BY j.department_id";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$visible_dept_ids);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $dept_job_counts[] = $row;
}

$page_title = "แดชบอร์ดผู้จัดการ";
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
            'surface': '#f8fafc'
          },
          animation: {
            'fade-in': 'fadeIn 0.6s ease-out',
            'slide-up': 'slideUp 0.5s ease-out',
            'counter': 'counter 2s ease-out'
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0', transform: 'translateY(20px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            },
            slideUp: {
              '0%': { opacity: '0', transform: 'translateY(30px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            },
            counter: {
              '0%': { opacity: '0', transform: 'scale(0.5)' },
              '100%': { opacity: '1', transform: 'scale(1)' }
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
    <!-- Header -->
    <header class="bg-white shadow-lg border-b border-red-100 sticky top-0 z-40">
      <div class="px-6 py-4">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center">
              <svg class="w-7 h-7 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
              แดชบอร์ดผู้จัดการ
            </h1>
            <p class="text-sm text-gray-500 mt-1">ภาพรวมงานภาคสนามของแผนกที่คุณดูแล</p>
          </div>
          <div class="flex items-center gap-3">
            <!-- Real-time Status -->
            <div class="flex items-center text-sm text-green-600 bg-green-50 px-3 py-1 rounded-full border border-green-200">
              <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></div>
              <span class="font-medium">ออนไลน์</span>
            </div>
            
            <!-- Current Time -->
            <div class="text-sm text-gray-500" id="currentTime"></div>
          </div>
        </div>
      </div>
    </header>

    <!-- Body -->
    <main class="flex-1 p-6 space-y-8 overflow-y-auto">
      
      <!-- Welcome Section - ปรับปรุงให้แสดงข้อมูลสรุปที่ชัดเจน -->
      <section class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl shadow-lg p-6 text-white animate-fade-in">
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <h2 class="text-2xl font-bold mb-2">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['user']['name']) ?></h2>
            <p class="text-blue-100 text-lg mb-4">พร้อมจัดการงานภาคสนามของคุณวันนี้</p>

            <!-- สรุปข้อมูลรวดเร็ว -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
              <div class="bg-white bg-opacity-20 rounded-lg p-3 backdrop-blur-sm">
                <div class="text-3xl font-bold"><?= $total_jobs ?></div>
                <div class="text-xs text-blue-100">งานทั้งหมด</div>
              </div>
              <div class="bg-white bg-opacity-20 rounded-lg p-3 backdrop-blur-sm">
                <div class="text-3xl font-bold text-green-300"><?= $status_counts['completed'] ?></div>
                <div class="text-xs text-blue-100">เสร็จแล้ว</div>
              </div>
              <div class="bg-white bg-opacity-20 rounded-lg p-3 backdrop-blur-sm">
                <div class="text-3xl font-bold text-yellow-300"><?= $status_counts['pending'] ?></div>
                <div class="text-xs text-blue-100">รอดำเนินการ</div>
              </div>
              <div class="bg-white bg-opacity-20 rounded-lg p-3 backdrop-blur-sm">
                <div class="text-3xl font-bold text-purple-300"><?= $total_jobs > 0 ? round(($status_counts['completed'] / $total_jobs) * 100, 1) : 0 ?>%</div>
                <div class="text-xs text-blue-100">อัตราสำเร็จ</div>
              </div>
            </div>
          </div>
          <div class="hidden md:block">
            <svg class="w-20 h-20 text-white opacity-30" fill="currentColor" viewBox="0 0 20 20">
              <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
            </svg>
          </div>
        </div>
      </section>

      <!-- Summary Cards - ปรับปรุงให้อ่านง่ายขึ้น -->
      <section class="animate-slide-up">
        <div class="mb-4">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            สรุปสถิติงาน
          </h3>
          <p class="text-sm text-gray-600 mt-1">ภาพรวมข้อมูลงานทั้งหมดที่อยู่ในความดูแลของคุณ</p>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Total Jobs Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 group">
          <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
            </div>
            <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">งานทั้งหมด</span>
          </div>
          <div class="text-3xl font-bold text-gray-800 mb-2 animate-counter"><?= $total_jobs ?></div>
          <div class="text-sm text-gray-500">งานในความดูแล</div>
          <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
            <div class="bg-blue-500 h-2 rounded-full" style="width: 100%;"></div>
          </div>
        </div>

        <!-- Completed Jobs Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 group">
          <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">เสร็จแล้ว</span>
          </div>
          <div class="text-3xl font-bold text-gray-800 mb-2 animate-counter"><?= $status_counts['completed'] ?></div>
          <div class="text-sm text-green-600 font-medium">
            อัตราสำเร็จ: <?= $total_jobs > 0 ? round(($status_counts['completed'] / $total_jobs) * 100, 1) : 0 ?>%
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
            <div class="bg-green-500 h-2 rounded-full" style="width: <?= $total_jobs > 0 ? ($status_counts['completed'] / $total_jobs) * 100 : 0 ?>%;"></div>
          </div>
        </div>

        <!-- Pending Jobs Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 group">
          <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">รอดำเนินการ</span>
          </div>
          <div class="text-3xl font-bold text-gray-800 mb-2 animate-counter"><?= $status_counts['pending'] ?></div>
          <div class="text-sm text-yellow-600 font-medium">ต้องดำเนินการ</div>
          <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
            <div class="bg-yellow-500 h-2 rounded-full" style="width: <?= $total_jobs > 0 ? ($status_counts['pending'] / $total_jobs) * 100 : 0 ?>%;"></div>
          </div>
        </div>

        <!-- Progress Tracking Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 group">
          <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
            </div>
            <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">ส่งงาน</span>
          </div>
          <div class="text-lg font-bold text-gray-800 mb-2">
            <span class="text-purple-600"><?= $sent_jobs ?></span>
            <span class="text-gray-400 mx-1">/</span>
            <span class="text-gray-500"><?= $unsent_jobs ?></span>
          </div>
          <div class="text-sm text-gray-500">ส่งแล้ว / ยังไม่ส่ง</div>
          <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
            <div class="bg-purple-500 h-2 rounded-full" style="width: <?= ($sent_jobs + $unsent_jobs) > 0 ? ($sent_jobs / ($sent_jobs + $unsent_jobs)) * 100 : 0 ?>%;"></div>
          </div>
        </div>
      </section>

      <!-- Charts Section -->
      <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 animate-slide-up" style="animation-delay: 0.2s;">
        
        <!-- Department Bar Chart -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
          <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
              <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
              จำนวนงานตามแผนก
            </h2>
            <p class="text-sm text-gray-500 mt-1">การกระจายงานแต่ละแผนกในความดูแล</p>
          </div>
          <div class="h-80">
            <canvas id="deptBarChart"></canvas>
          </div>
        </div>

        <!-- Status Pie Chart -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
          <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
              <svg class="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
              </svg>
              สัดส่วนสถานะงาน
            </h2>
            <p class="text-sm text-gray-500 mt-1">เปอร์เซ็นต์การเสร็จสิ้นงาน</p>
          </div>
          <div class="h-80 flex items-center justify-center">
            <canvas id="statusPieChart"></canvas>
          </div>
        </div>
      </section>

      <!-- Quick Actions -->
      <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up" style="animation-delay: 0.4s;">
        <div class="mb-6">
          <h2 class="text-xl font-semibold text-gray-800 flex items-center">
            <svg class="w-6 h-6 mr-3 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            การดำเนินการด่วน
          </h2>
          <p class="text-sm text-gray-500 mt-1">เข้าถึงฟังก์ชันสำคัญได้อย่างรวดเร็ว</p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <a href="../admin/import_jobs.php" class="group flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-xl transition-all duration-300 hover:scale-105">
            <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
              </svg>
            </div>
            <span class="text-sm font-semibold text-gray-800">เพิ่มงาน</span>
          </a>

          <a href="../admin/jobs.php" class="group flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 rounded-xl transition-all duration-300 hover:scale-105">
            <div class="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
            </div>
            <span class="text-sm font-semibold text-gray-800">รายการงาน</span>
          </a>

          <a href="../admin/map.php" class="group flex flex-col items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-xl transition-all duration-300 hover:scale-105">
            <div class="w-12 h-12 bg-purple-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <span class="text-sm font-semibold text-gray-800">แผนที่</span>
          </a>

          <div class="group flex flex-col items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all duration-300">
            <div class="w-12 h-12 bg-gray-600 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
            </div>
            <span class="text-sm font-semibold text-gray-800">รายงาน</span>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<?php include '../components/footer.php'; ?>

<script>
// Update current time
function updateTime() {
  const now = new Date();
  const timeString = now.toLocaleString('th-TH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
  document.getElementById('currentTime').textContent = timeString;
}
updateTime();
setInterval(updateTime, 1000);

// Chart data
const deptLabels = <?= json_encode(array_column($dept_job_counts, 'name')) ?>;
const deptCounts = <?= json_encode(array_map('intval', array_column($dept_job_counts, 'count'))) ?>;
const statusLabels = ['เสร็จแล้ว', 'รอดำเนินการ'];
const statusCounts = [<?= $status_counts['completed'] ?>, <?= $status_counts['pending'] ?>];

// Bar Chart
new Chart(document.getElementById('deptBarChart'), {
    type: 'bar',
    data: {
        labels: deptLabels,
        datasets: [{
            label: 'จำนวนงาน',
            data: deptCounts,
            backgroundColor: '#3B82F6',
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { 
            legend: { display: false },
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
                ticks: { precision: 0 },
                grid: { color: 'rgba(0, 0, 0, 0.05)' }
            },
            y: {
                grid: { display: false }
            }
        }
    }
});

// Pie Chart
new Chart(document.getElementById('statusPieChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusCounts,
            backgroundColor: ['#10B981', '#F59E0B'],
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

// Animate counters
document.addEventListener('DOMContentLoaded', function() {
    function animateCounter(element, target) {
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                clearInterval(timer);
            }
            element.textContent = Math.floor(current);
        }, 30);
    }

    // Animate all counter elements
    const counters = document.querySelectorAll('.animate-counter');
    counters.forEach(counter => {
        const target = parseInt(counter.textContent);
        if (!isNaN(target)) {
            counter.textContent = '0';
            setTimeout(() => animateCounter(counter, target), 500);
        }
    });
});
</script>

</body>
</html>