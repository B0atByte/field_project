<?php
require_once __DIR__ . '/../includes/session_config.php';
if ($_SESSION['user']['role'] !== 'admin') {
  header("Location: ../index.php");
  exit;
}
include '../config/db.php';

// Filter Variables
$stats_month = $_GET['stats_month'] ?? date('Y-m');
$ranking_month = $_GET['ranking_month'] ?? date('Y-m');

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

$jobs_total = (int) ($summary['total'] ?? 0);
$jobs_completed = (int) ($summary['completed'] ?? 0);
$jobs_pending = (int) ($summary['pending'] ?? 0);
$jobs_unassigned = (int) ($summary['unassigned'] ?? 0);
$jobs_overdue = (int) ($summary['overdue'] ?? 0);

$jobs_sent = (int) ($conn->query("SELECT COUNT(DISTINCT j.id) AS total 
                                   FROM jobs j JOIN job_logs jl ON j.id = jl.job_id")->fetch_assoc()['total'] ?? 0);
$jobs_unsent = max(0, $jobs_total - $jobs_sent);
$completion_rate = $jobs_total > 0 ? round(($jobs_completed / $jobs_total) * 100, 1) : 0;

/* -------------------- USERS BY ROLE -------------------- */
$user_counts = ['admin' => 0, 'manager' => 0, 'field' => 0];
$res_users = $conn->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
while ($row = $res_users->fetch_assoc()) {
  $user_counts[$row['role']] = (int) $row['total'];
}

/* -------------------- JOBS BY DEPT -------------------- */
$dept_jobs = [];
$res = $conn->query("
    SELECT d.name, COUNT(j.id) AS count 
    FROM jobs j 
    LEFT JOIN departments d ON j.department_id = d.id 
    GROUP BY d.id
");
while ($row = $res->fetch_assoc())
  $dept_jobs[] = $row;

/* -------------------- 14-DAY TREND (Query เดียว) -------------------- */
date_default_timezone_set('Asia/Bangkok');
$days = [];
$createdSeries = [];
$completedSeries = [];

$trendData = [];
$sql = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m-%d') AS d,
        COUNT(*) AS created_jobs,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_jobs
    FROM jobs
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')
";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $trendData[$row['d']] = [
            'created_jobs'   => (int)$row['created_jobs'],
            'completed_jobs' => (int)$row['completed_jobs']
        ];
    }
}

for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[] = $d;
    $createdSeries[] = $trendData[$d]['created_jobs'] ?? 0;
    $completedSeries[] = $trendData[$d]['completed_jobs'] ?? 0;
}

/* -------------------- JOB RESULT STATISTICS (Filterable) -------------------- */
// Using created_at of job_logs. If job_logs doesn't have created_at, use timestamp related column.
// Generally logs have 'created_at'.
$sql_stats = "
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
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("s", $stats_month);
$stmt_stats->execute();
$job_result_stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

$total_logs = (int) ($job_result_stats['total_logs'] ?? 0);
function pct($val, $total)
{
  return $total > 0 ? round(($val / $total) * 100, 1) : 0;
}

$stats = [
  'found_tenant' => ['val' => (int) $job_result_stats['found_tenant'], 'label' => 'พบผู้เช่า/ผู้ค้ำ', 'color' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
  'not_found_tenant' => ['val' => (int) $job_result_stats['not_found_tenant'], 'label' => 'ไม่พบผู้เช่า/ผู้ค้ำ', 'color' => 'bg-red-500', 'text' => 'text-red-700', 'bg' => 'bg-red-50'],
  'found_location' => ['val' => (int) $job_result_stats['found_location_no_car'], 'label' => 'พบที่ตั้ง/ไม่พบรถ', 'color' => 'bg-amber-500', 'text' => 'text-amber-700', 'bg' => 'bg-amber-50'],
  'not_found_location' => ['val' => (int) $job_result_stats['not_found_location'], 'label' => 'ไม่พบที่ตั้ง/ไม่พบรถ', 'color' => 'bg-gray-500', 'text' => 'text-gray-700', 'bg' => 'bg-gray-50'],
  'found_relative' => ['val' => (int) $job_result_stats['found_relative'], 'label' => 'พบญาติ/ฝากเรื่อง', 'color' => 'bg-purple-500', 'text' => 'text-purple-700', 'bg' => 'bg-purple-50'],
  'found_car' => ['val' => (int) $job_result_stats['found_car_no_tenant'], 'label' => 'พบรถ/ไม่พบคน', 'color' => 'bg-orange-500', 'text' => 'text-orange-700', 'bg' => 'bg-orange-50'],
  'pay_appt' => ['val' => (int) $job_result_stats['appointment_payment'], 'label' => 'นัดชำระ', 'color' => 'bg-teal-500', 'text' => 'text-teal-700', 'bg' => 'bg-teal-50'],
  'return_appt' => ['val' => (int) $job_result_stats['appointment_return'], 'label' => 'นัดคืนรถ', 'color' => 'bg-indigo-500', 'text' => 'text-indigo-700', 'bg' => 'bg-indigo-50'],
];

/* -------------------- FIELD PERFORMANCE (Filterable, All Agents) -------------------- */
$ranking_data = [];
$sql_ranking = "
    SELECT u.name, COUNT(j.id) AS completed_jobs
    FROM jobs j
    JOIN users u ON j.assigned_to = u.id
    WHERE u.role = 'field'
      AND j.status = 'completed'
      AND DATE_FORMAT(j.updated_at, '%Y-%m') = ?
    GROUP BY u.id
    ORDER BY completed_jobs DESC
";
// Showing all agents, no limit. 
$stmt_ranking = $conn->prepare($sql_ranking);
$stmt_ranking->bind_param("s", $ranking_month);
$stmt_ranking->execute();
$res_ranking = $stmt_ranking->get_result();
while ($row = $res_ranking->fetch_assoc()) {
  $ranking_data[] = $row;
}
$stmt_ranking->close();

$page_title = "Admin Dashboard";
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
      <!-- Top Bar -->
      <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-30">
        <div class="px-6 py-4 flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-bold text-corporate-900">ภาพรวมระบบ</h1>
            <p class="text-sm text-gray-500">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['user']['name']) ?></p>
          </div>
          <div class="flex items-center gap-4">
            <div class="text-sm text-gray-500 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-green-500"></span>
              System Online
            </div>
            <a href="../auth/logout.php" class="text-sm text-red-600 hover:text-red-800 font-medium">ออกจากระบบ</a>
          </div>
        </div>
      </header>

      <main class="flex-1 p-6 space-y-6">

        <!-- Key Matrices -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <!-- Total Jobs -->
          <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200 flex flex-col justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">งานทั้งหมด</p>
              <h3 class="text-3xl font-bold text-corporate-900 mt-2"><?= number_format($jobs_total) ?></h3>
            </div>
            <div class="mt-4 flex items-center text-sm text-gray-600">
              <span class="text-corporate-600 font-medium"><?= number_format($jobs_unsent) ?></span>
              <span class="ml-1">รายการที่ยังไม่ส่ง</span>
            </div>
          </div>

          <!-- Completed -->
          <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200 flex flex-col justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">ปิดงานสำเร็จ</p>
              <h3 class="text-3xl font-bold text-emerald-600 mt-2"><?= number_format($jobs_completed) ?></h3>
            </div>
            <div class="mt-4">
              <div class="w-full bg-gray-100 rounded-full h-1.5">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?= $completion_rate ?>%"></div>
              </div>
              <p class="text-xs text-gray-500 mt-1"><?= $completion_rate ?>% ความสำเร็จ</p>
            </div>
          </div>

          <!-- Pending -->
          <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200 flex flex-col justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">รอดำเนินการ</p>
              <h3 class="text-3xl font-bold text-amber-500 mt-2"><?= number_format($jobs_pending) ?></h3>
            </div>
            <div class="mt-4 text-sm text-gray-600">
              รอการติดตามจากเจ้าหน้าที่
            </div>
          </div>

          <!-- Unassigned -->
          <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200 flex flex-col justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">ยังไม่จ่ายงาน</p>
              <h3 class="text-3xl font-bold text-red-500 mt-2"><?= number_format($jobs_unassigned) ?></h3>
            </div>
            <div class="mt-4 text-sm text-red-600">
              ต้องการการจัดสรรด่วน
            </div>
          </div>
        </section>

        <!-- Charts Row -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Trend Chart -->
          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 lg:col-span-2">
            <h3 class="text-lg font-bold text-corporate-900 mb-4">ปริมาณงาน 14 วันย้อนหลัง</h3>
            <div class="h-64">
              <canvas id="trendChart"></canvas>
            </div>
          </div>

          <!-- Users Breakdown -->
          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-corporate-900 mb-4">ข้อมูลผู้ใช้งาน</h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                <div class="flex items-center gap-3">
                  <div
                    class="w-8 h-8 rounded bg-corporate-200 flex items-center justify-center text-corporate-700 font-bold">
                    A</div>
                  <span class="font-medium text-gray-700">Admin</span>
                </div>
                <span class="font-bold text-corporate-900"><?= $user_counts['admin'] ?></span>
              </div>
              <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                <div class="flex items-center gap-3">
                  <div
                    class="w-8 h-8 rounded bg-corporate-200 flex items-center justify-center text-corporate-700 font-bold">
                    M</div>
                  <span class="font-medium text-gray-700">Manager</span>
                </div>
                <span class="font-bold text-corporate-900"><?= $user_counts['manager'] ?></span>
              </div>
              <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
                <div class="flex items-center gap-3">
                  <div
                    class="w-8 h-8 rounded bg-corporate-200 flex items-center justify-center text-corporate-700 font-bold">
                    F</div>
                  <span class="font-medium text-gray-700">Field Agent</span>
                </div>
                <span class="font-bold text-corporate-900"><?= $user_counts['field'] ?></span>
              </div>
            </div>
          </div>
        </section>

        <!-- Detailed Stats Grid (With Filter) -->
        <section class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
            <h3 class="text-lg font-bold text-corporate-900 flex items-center">
              สถิติผลการติดตาม
              <span
                class="ml-2 text-sm font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"><?= number_format($total_logs) ?>
                records</span>
            </h3>
            <form method="GET" class="flex items-center gap-2">
              <input type="hidden" name="ranking_month" value="<?= $ranking_month ?>">
              <label class="text-sm text-gray-600 font-medium">ประจำเดือน:</label>
              <input type="month" name="stats_month" value="<?= $stats_month ?>" onchange="this.form.submit()"
                class="text-sm border-gray-300 rounded-md shadow-sm focus:border-corporate-500 focus:ring focus:ring-corporate-200 focus:ring-opacity-50">
            </form>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($stats as $key => $data):
              $percent = pct($data['val'], $total_logs);
              ?>
              <div
                class="<?= $data['bg'] ?> p-4 rounded-lg border border-opacity-20 border-gray-300 transition-all hover:shadow-md">
                <div class="flex justify-between items-start mb-2">
                  <span class="text-sm font-medium <?= $data['text'] ?>"><?= $data['label'] ?></span>
                  <span
                    class="text-xs font-bold bg-white px-2 py-0.5 rounded shadow-sm text-gray-600"><?= $percent ?>%</span>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?= number_format($data['val']) ?></div>
                <div class="w-full bg-white bg-opacity-50 h-1 mt-2 rounded-full overflow-hidden">
                  <div class="<?= $data['color'] ?> h-full" style="width: <?= $percent ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- Bottom Row: Performance & Departments -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Field Performance (With Filter) -->
          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 flex flex-col max-h-[500px]">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-4">
              <h3 class="text-lg font-bold text-corporate-900">ผลงานเจ้าหน้าที่ภาคสนาม</h3>
              <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="stats_month" value="<?= $stats_month ?>">
                <input type="month" name="ranking_month" value="<?= $ranking_month ?>" onchange="this.form.submit()"
                  class="text-sm border-gray-300 rounded-md shadow-sm focus:border-corporate-500 focus:ring focus:ring-corporate-200 focus:ring-opacity-50">
              </form>
            </div>

            <div class="overflow-y-auto flex-1 pr-2">
              <table class="min-w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-500 font-medium sticky top-0">
                  <tr>
                    <th class="px-4 py-3">อันดับ</th>
                    <th class="px-4 py-3">ชื่อเจ้าหน้าที่</th>
                    <th class="px-4 py-3 text-right">งานเสร็จ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php if (empty($ranking_data)): ?>
                    <tr>
                      <td colspan="3" class="px-4 py-8 text-center text-gray-500">ไม่มีข้อมูลในเดือนนี้</td>
                    </tr>
                  <?php else:
                    foreach ($ranking_data as $i => $tf):
                      $rank = $i + 1;
                      $rankColor = 'text-gray-500';
                      if ($rank == 1)
                        $rankColor = 'text-yellow-500';
                      if ($rank == 2)
                        $rankColor = 'text-gray-400';
                      if ($rank == 3)
                        $rankColor = 'text-orange-500';
                      ?>
                      <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 font-bold <?= $rankColor ?>">
                          <?php if ($rank <= 3): ?>
                            <span class="inline-block w-6 text-center">#<?= $rank ?></span>
                          <?php else: ?>
                            <span class="inline-block w-6 text-center text-gray-400"><?= $rank ?></span>
                          <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($tf['name']) ?></td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">
                          <?= number_format($tf['completed_jobs']) ?></td>
                      </tr>
                    <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Department Distribution -->
          <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-corporate-900 mb-4">งานตามแผนก</h3>
            <div class="h-64">
              <canvas id="deptChart"></canvas>
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

    // Trend Chart
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
      type: 'line',
      data: {
        labels: <?= json_encode($days) ?>.map(d => d.substr(5)),
        datasets: [
          {
            label: 'สร้างงานใหม่',
            data: <?= json_encode($createdSeries) ?>,
            borderColor: '#334e68',
            backgroundColor: 'rgba(51, 78, 104, 0.1)',
            tension: 0.3,
            fill: true
          },
          {
            label: 'ปิดงานสำเร็จ',
            data: <?= json_encode($completedSeries) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.3,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top', align: 'end' } },
        scales: { y: { beginAtZero: true, grid: { borderDash: [2, 4] } }, x: { grid: { display: false } } }
      }
    });

    // Dept Chart
    const ctxDept = document.getElementById('deptChart').getContext('2d');
    const deptData = <?= json_encode($dept_jobs, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?>;
    
    // ตรวจสอบข้อมูลก่อนวาดกราฟ
    if (deptData && deptData.length > 0) {
        new Chart(ctxDept, {
            type: 'bar',
            data: {
                labels: deptData.map(d => d.name),
                datasets: [{
                    label: 'จำนวนงาน',
                    data: deptData.map(d => d.count),
                    backgroundColor: '#627d98',
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
    } else {
        // กรณีไม่มีข้อมูล ให้แสดงข้อความ
        console.warn("No department data available");
        const ctx = document.getElementById('deptChart');
        ctx.style.display = 'none';
        ctx.parentNode.innerHTML += '<div class="flex items-center justify-center h-full text-gray-400">ไม่มีข้อมูลงานในระบบ</div>';
    }
  </script>
</body>

</html>