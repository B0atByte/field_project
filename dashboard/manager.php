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

$page_title = "Manager Dashboard";
include '../components/header.php';
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title><?= $page_title ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            'thai': ['Sarabun', 'sans-serif']
          },
          colors: {
            corporate: {
              50: '#f0f4f8',
              100: '#d9e2ec',
              500: '#627d98',
              700: '#334e68',
              800: '#243b53',
              900: '#102a43',
            }
          }
        }
      }
    }
  </script>
</head>

<body class="font-thai bg-corporate-50 text-gray-800">

  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <?php include '../components/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 lg:ml-64 transition-all duration-300">
      <!-- Header -->
      <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-30">
        <div class="px-6 py-4 flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-bold text-corporate-900">Manager Dashboard</h1>
            <p class="text-sm text-gray-500">จัดการงานในแผนกที่ดูแล</p>
          </div>
          <div class="flex items-center gap-4">
            <div class="text-right hidden sm:block">
              <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($_SESSION['user']['name']) ?></div>
              <div class="text-xs text-gray-500">Manager</div>
            </div>
            <a href="../auth/logout.php" class="text-sm text-red-600 hover:text-red-800 font-medium">Log Out</a>
          </div>
        </div>
      </header>

      <main class="flex-1 p-6 space-y-6">

        <!-- Summary Cards -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <!-- Card 1 -->
          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 uppercase font-medium">งานทั้งหมดในความดูแล</p>
                <h3 class="text-3xl font-bold text-corporate-900 mt-2"><?= number_format($total_jobs) ?></h3>
              </div>
              <div class="p-3 bg-corporate-100 rounded-full text-corporate-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                  </path>
                </svg>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex justify-between text-sm text-gray-600 mb-1">
                <span>ความคืบหน้า</span>
                <span><?= $total_jobs > 0 ? round(($status_counts['completed'] / $total_jobs) * 100) : 0 ?>%</span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-1.5">
                <div class="bg-corporate-500 h-1.5 rounded-full"
                  style="width: <?= $total_jobs > 0 ? ($status_counts['completed'] / $total_jobs) * 100 : 0 ?>%"></div>
              </div>
            </div>
          </div>

          <!-- Card 2 -->
          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 uppercase font-medium">เสร็จสิ้น</p>
                <h3 class="text-3xl font-bold text-emerald-600 mt-2"><?= number_format($status_counts['completed']) ?>
                </h3>
              </div>
              <div class="p-3 bg-emerald-50 rounded-full text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
              </div>
            </div>
            <p class="text-xs text-gray-400 mt-4">งานที่ปิดจบเรียบร้อยแล้ว</p>
          </div>

          <!-- Card 3 -->
          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 uppercase font-medium">รอดำเนินการ</p>
                <h3 class="text-3xl font-bold text-amber-500 mt-2"><?= number_format($status_counts['pending']) ?></h3>
              </div>
              <div class="p-3 bg-amber-50 rounded-full text-amber-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
            </div>
            <p class="text-xs text-gray-400 mt-4">อยู่ในระหว่างการติดตาม</p>
          </div>

          <!-- Card 4 : Sent vs Unsent -->
          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <div class="flex flex-col h-full justify-between">
              <p class="text-sm text-gray-500 uppercase font-medium">สถานะการส่งงาน</p>
              <div class="flex items-end gap-2 mt-2">
                <div class="text-3xl font-bold text-blue-600"><?= number_format($sent_jobs) ?></div>
                <div class="text-gray-400 mb-1">/</div>
                <div class="text-xl font-bold text-gray-400 mb-0.5"><?= number_format($unsent_jobs) ?></div>
              </div>
              <div class="mt-4 text-xs text-gray-500">
                <span class="text-blue-600 font-medium">ส่งแล้ว</span> vs <span class="text-gray-500">ยังไม่ส่ง</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Main Charts Area -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-corporate-900 mb-4">ปริมาณงานตามแผนก</h3>
            <div class="h-64">
              <canvas id="deptChart"></canvas>
            </div>
          </div>

          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-corporate-900 mb-4">สัดส่วนสถานะงาน</h3>
            <div class="h-64 flex justify-center">
              <canvas id="statusChart"></canvas>
            </div>
          </div>
        </section>

        <!-- Quick Links -->
        <section class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <h3 class="text-lg font-bold text-corporate-900 mb-4">เมนูด่วน</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="../admin/jobs.php"
              class="flex flex-col items-center justify-center p-4 bg-gray-50 border border-gray-200 rounded-lg hover:bg-white hover:shadow-md hover:border-corporate-200 transition-all cursor-pointer group">
              <div class="p-3 bg-white rounded-full shadow-sm group-hover:bg-corporate-50 mb-2">
                <svg class="w-6 h-6 text-corporate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                  </path>
                </svg>
              </div>
              <span class="text-sm font-semibold text-gray-700 group-hover:text-corporate-700">ดูรายชื่อธนาคาร</span>
            </a>
            <a href="../admin/map.php"
              class="flex flex-col items-center justify-center p-4 bg-gray-50 border border-gray-200 rounded-lg hover:bg-white hover:shadow-md hover:border-corporate-200 transition-all cursor-pointer group">
              <div class="p-3 bg-white rounded-full shadow-sm group-hover:bg-corporate-50 mb-2">
                <svg class="w-6 h-6 text-corporate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
              </div>
              <span class="text-sm font-semibold text-gray-700 group-hover:text-corporate-700">แผนที่ติดตามงาน</span>
            </a>
            <!-- Placeholders for future links -->
            <div
              class="flex flex-col items-center justify-center p-4 border border-dashed border-gray-300 rounded-lg text-gray-400">
              <span class="text-sm">Reports (Coming Soon)</span>
            </div>
            <div
              class="flex flex-col items-center justify-center p-4 border border-dashed border-gray-300 rounded-lg text-gray-400">
              <span class="text-sm">Analytics (Coming Soon)</span>
            </div>
          </div>
        </section>

      </main>
    </div>
  </div>

  <?php include '../components/footer.php'; ?>

  <script>
    Chart.defaults.font.family = 'Sarabun';
    Chart.defaults.color = '#4b5563';

    // Department Bar Chart
    const ctxDept = document.getElementById('deptChart').getContext('2d');
    new Chart(ctxDept, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($dept_job_counts, 'name')) ?>,
        datasets: [{
          label: 'จำนวนงาน',
          data: <?= json_encode(array_column($dept_job_counts, 'count')) ?>,
          backgroundColor: '#334e68', // Corporate 700
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true },
          x: { grid: { display: false } }
        }
      }
    });

    // Status Doughnut Chart
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
      type: 'doughnut',
      data: {
        labels: ['เสร็จแล้ว', 'รอดำเนินการ'],
        datasets: [{
          data: [<?= $status_counts['completed'] ?>, <?= $status_counts['pending'] ?>],
          backgroundColor: ['#10b981', '#f59e0b'], // Emerald, Amber
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  </script>
</body>

</html>