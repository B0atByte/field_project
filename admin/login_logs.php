<?php
// Redirect to new unified logs page
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Redirect to new logs.php with login tab
$query_params = $_GET;
$query_params['tab'] = 'login';
// Standardize naming: date -> start_date & end_date
if (isset($query_params['date'])) {
    $query_params['start_date'] = $query_params['date'];
    $query_params['end_date'] = $query_params['date'];
    unset($query_params['date']);
}
unset($query_params['action']);
unset($query_params['cleared']);
header("Location: logs.php?" . http_build_query($query_params));
exit;

// สร้าง WHERE conditions
$where_conditions = ["1=1"];
$params = [];
$types = '';

if ($filter_user !== '') {
    $where_conditions[] = "u.name LIKE ?";
    $params[] = "%$filter_user%";
    $types .= 's';
}
if ($filter_date !== '') {
    $where_conditions[] = "DATE(l.login_time) = ?";
    $params[] = $filter_date;
    $types .= 's';
}
if ($filter_type !== '') {
    $where_conditions[] = "l.type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

// จำกัดการดึงข้อมูลเฉพาะ 30 วันล่าสุด (ถ้าไม่ได้ระบุวันที่)
if ($filter_date === '') {
    $where_conditions[] = "l.login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$where = implode(' AND ', $where_conditions);

// นับจำนวนทั้งหมด
$count_sql = "SELECT COUNT(*) as total 
              FROM login_logs l 
              JOIN users u ON l.user_id = u.id 
              WHERE $where";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// คำนวณ pagination
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Query logs พร้อม LIMIT
$sql = "SELECT l.id, l.user_id, l.ip_address, l.user_agent, l.login_time, l.type, u.name AS user_name
        FROM login_logs l
        FORCE INDEX (idx_login_time)
        JOIN users u ON l.user_id = u.id
        WHERE $where
        ORDER BY l.login_time DESC
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ฟังก์ชันแปลง IP Address ให้อ่านง่าย
function formatIPAddress($ip) {
    if ($ip === '::1' || $ip === '::ffff:127.0.0.1') {
        return '127.0.0.1 (localhost)';
    }
    
    if (strpos($ip, '::ffff:') === 0) {
        return substr($ip, 7);
    }
    
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (preg_match('/^10\./', $ip) || 
            preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) ||
            preg_match('/^192\.168\./', $ip)) {
            return $ip . ' (private)';
        }
    }
    
    return $ip;
}

// ฟังก์ชันแสดง platform และ browser
function parseUserAgent($ua) {
    $platform = 'Unknown';
    $browser = 'Unknown';

    if (preg_match('/windows/i', $ua)) $platform = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $platform = 'Mac';
    elseif (preg_match('/android/i', $ua)) $platform = 'Android';
    elseif (preg_match('/iphone/i', $ua)) $platform = 'iPhone';
    elseif (preg_match('/ipad/i', $ua)) $platform = 'iPad';
    elseif (preg_match('/linux/i', $ua)) $platform = 'Linux';

    if (preg_match('/chrome/i', $ua) && !preg_match('/edge|edg/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/edge|edg/i', $ua)) $browser = 'Edge';
    elseif (preg_match('/msie|trident/i', $ua)) $browser = 'IE';
    else $browser = 'Other';

    return "$platform | $browser";
}

$page_title = "ประวัติการเข้าใช้งาน";
include '../components/header.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              ประวัติการเข้าใช้งาน
            </h1>
            <p class="text-sm text-gray-500 mt-1">ติดตามการเข้าสู่ระบบและกิจกรรมของผู้ใช้ (แสดง 30 วันล่าสุด)</p>
          </div>
          <div class="flex items-center gap-4">
            <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-lg">
              <span class="font-semibold"><?= number_format($total_records) ?></span> รายการ
            </div>
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
      <?php if (isset($_GET['cleared'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl animate-fade-in">
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="font-medium">ล้าง log ทั้งหมดเรียบร้อยแล้ว</span>
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
          <p class="text-sm text-gray-500 mt-1">ค้นหาและกรองประวัติการเข้าใช้งานตามผู้ใช้ วันที่ และประเภท</p>
        </div>

        <form method="get" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
                ชื่อผู้ใช้
              </label>
              <input type="text" 
                     name="user" 
                     value="<?= htmlspecialchars($filter_user) ?>" 
                     placeholder="ค้นหาชื่อผู้ใช้..."
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                วันที่
              </label>
              <input type="date" 
                     name="date" 
                     value="<?= htmlspecialchars($filter_date) ?>"
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300">
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                ประเภท
              </label>
              <select name="type" 
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 appearance-none bg-white"
                      style="background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e&quot;); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em;">
                <option value="">ทั้งหมด</option>
                <option value="login" <?= $filter_type === 'login' ? 'selected' : '' ?>>Login</option>
                <option value="logout" <?= $filter_type === 'logout' ? 'selected' : '' ?>>Logout</option>
                <option value="login_fail" <?= $filter_type === 'login_fail' ? 'selected' : '' ?>>Failed</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                แสดงต่อหน้า
              </label>
              <select name="per_page" 
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 appearance-none bg-white"
                      style="background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e&quot;); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em;">
                <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                <option value="250" <?= $per_page == 250 ? 'selected' : '' ?>>250</option>
              </select>
            </div>
          </div>
          
          <div class="flex flex-col sm:flex-row justify-between gap-3 pt-4">
            <div class="flex flex-col sm:flex-row gap-3">
              <button type="submit" class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                กรองข้อมูล
              </button>
              <a href="login_logs.php" class="inline-flex items-center justify-center px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                รีเซ็ต
              </a>
            </div>
            
            <!-- Clear All Button -->
            <a href="?action=clear_all" 
               onclick="return confirm('คำเตือน!\n\nคุณต้องการล้าง Log ทั้งหมดหรือไม่?\n\nการดำเนินการนี้จะลบประวัติการเข้าใช้งานทั้งหมดออกจากระบบ และไม่สามารถกู้คืนได้!\n\nกด OK เพื่อยืนยันการลบ\nกด Cancel เพื่อยกเลิก')"
               class="inline-flex items-center justify-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
              ล้างทั้งหมด
            </a>
          </div>
        </form>
      </section>

      <!-- Login Logs Table Section -->
      <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up" style="animation-delay: 0.2s;">
        <div class="mb-6 flex items-center justify-between">
          <div>
            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
              <svg class="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              รายการ Login Logs
            </h2>
            <p class="text-sm text-gray-500 mt-1">แสดง <?= $total_records > 0 ? number_format(($page - 1) * $per_page + 1) : 0 ?> - <?= number_format(min($page * $per_page, $total_records)) ?> จาก <?= number_format($total_records) ?> รายการ</p>
          </div>
          
          <!-- Pagination Info -->
          <?php if ($total_pages > 1): ?>
          <div class="text-sm text-gray-600">
            หน้า <?= $page ?> / <?= $total_pages ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <span class="mr-2">#</span>
                      ลำดับ
                    </div>
                  </th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                      </svg>
                      ผู้ใช้
                    </div>
                  </th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      เวลา
                    </div>
                  </th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      ประเภท
                    </div>
                  </th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9"/>
                      </svg>
                      IP Address
                    </div>
                  </th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    <div class="flex items-center">
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                      </svg>
                      อุปกรณ์
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($result->num_rows > 0): ?>
                  <?php 
                  $row_number = ($page - 1) * $per_page + 1;
                  while ($row = $result->fetch_assoc()): 
                  ?>
                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                          <?= $row_number++ ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center shadow-md">
                            <span class="text-white font-semibold text-sm">
                              <?= strtoupper(substr($row['user_name'], 0, 2)) ?>
                            </span>
                          </div>
                          <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">
                              <?= htmlspecialchars($row['user_name']) ?>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <div class="flex flex-col">
                          <span class="font-medium text-gray-900">
                            <?= date('d/m/Y', strtotime($row['login_time'])) ?>
                          </span>
                          <span class="text-xs text-gray-500 mt-0.5">
                            <?= date('H:i:s น.', strtotime($row['login_time'])) ?>
                          </span>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $type_class = '';
                        $type_text = '';
                        $type_icon = '';
                        
                        if ($row['type'] === 'login') {
                          $type_class = 'bg-green-100 text-green-800 border border-green-200';
                          $type_text = 'Login';
                          $type_icon = '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                        } elseif ($row['type'] === 'logout') {
                          $type_class = 'bg-blue-100 text-blue-800 border border-blue-200';
                          $type_text = 'Logout';
                          $type_icon = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>';
                        } else {
                          $type_class = 'bg-red-100 text-red-800 border border-red-200';
                          $type_text = 'Failed';
                          $type_icon = '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
                        }
                        ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $type_class ?>">
                          <span class="mr-1.5"><?= $type_icon ?></span>
                          <?= $type_text ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php 
                        $formatted_ip = formatIPAddress($row['ip_address']);
                        $is_local = (strpos($formatted_ip, 'localhost') !== false || strpos($formatted_ip, 'private') !== false);
                        $ip_badge_class = $is_local ? 'bg-yellow-50 text-yellow-800 border-yellow-200' : 'bg-blue-50 text-blue-800 border-blue-200';
                        ?>
                        <div class="flex items-center">
                          <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-mono font-medium border <?= $ip_badge_class ?>">
                            <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9"/>
                            </svg>
                            <?= htmlspecialchars($formatted_ip) ?>
                          </span>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <?php
                          $device_info = parseUserAgent($row['user_agent']);
                          $parts = explode(' | ', $device_info);
                          $platform = $parts[0] ?? 'Unknown';
                          $browser = $parts[1] ?? 'Unknown';
                          
                          $platform_icon = '';
                          $platform_color = 'text-gray-600';
                          
                          if (strpos($platform, 'Windows') !== false) {
                            $platform_icon = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M0 3.449L9.75 2.1v9.451H0m10.949-9.602L24 0v11.4H10.949M0 12.6h9.75v9.451L0 20.699M10.949 12.6H24V24l-12.9-1.801"/></svg>';
                            $platform_color = 'text-blue-600';
                          } elseif (strpos($platform, 'Mac') !== false) {
                            $platform_icon = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>';
                            $platform_color = 'text-gray-700';
                          } elseif (strpos($platform, 'Android') !== false) {
                            $platform_icon = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.6 9.48l1.84-3.18c.16-.31.04-.69-.26-.85-.29-.15-.65-.06-.83.22l-1.88 3.24a11.5 11.5 0 00-8.94 0L5.65 5.67c-.19-.28-.54-.37-.83-.22-.3.16-.42.54-.26.85l1.84 3.18C4.5 11.03 3 13.47 3 16.25V17h18v-.75c0-2.78-1.5-5.22-3.4-6.77zM7 15.25c-.69 0-1.25-.56-1.25-1.25s.56-1.25 1.25-1.25 1.25.56 1.25 1.25-.56 1.25-1.25 1.25zm10 0c-.69 0-1.25-.56-1.25-1.25s.56-1.25 1.25-1.25 1.25.56 1.25 1.25-.56 1.25-1.25 1.25z"/></svg>';
                            $platform_color = 'text-green-600';
                          } elseif (strpos($platform, 'iPhone') !== false || strpos($platform, 'iPad') !== false) {
                            $platform_icon = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>';
                            $platform_color = 'text-gray-700';
                          } elseif (strpos($platform, 'Linux') !== false) {
                            $platform_icon = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.504 0c-.155 0-.315.008-.48.021-4.226.333-3.105 4.807-3.17 6.298-.076 1.092-.3 1.953-1.05 3.02-.885 1.051-2.127 2.75-2.716 4.521-.278.832-.41 1.684-.287 2.489a1.5 1.5 0 001.146 1.221c.337.105.577.114.716.114.285 0 .563-.08.811-.224.248-.144.472-.36.668-.632.196-.272.353-.593.489-.944.272-.702.471-1.513.628-2.368.314-1.71.491-3.63.491-5.388 0-.414-.013-.825-.04-1.23z"/></svg>';
                            $platform_color = 'text-orange-600';
                          } else {
                            $platform_icon = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';
                          }
                          ?>
                          <div class="flex flex-col">
                            <div class="flex items-center <?= $platform_color ?>">
                              <span class="mr-1.5"><?= $platform_icon ?></span>
                              <span class="text-xs font-semibold"><?= htmlspecialchars($platform) ?></span>
                            </div>
                            <div class="text-xs text-gray-500 mt-0.5 ml-5">
                              <?= htmlspecialchars($browser) ?>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="px-6 py-12 text-center">
                      <div class="flex flex-col items-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full mb-4 flex items-center justify-center">
                          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">ไม่พบข้อมูล Login Logs</h3>
                        <p class="text-gray-500 text-sm">ไม่มีประวัติการเข้าใช้งานตามเงื่อนไขที่กรอง</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
          <div class="flex-1 flex justify-between sm:hidden">
            <?php if ($page > 1): ?>
              <a href="?page=<?= $page - 1 ?>&per_page=<?= $per_page ?>&user=<?= urlencode($filter_user) ?>&date=<?= urlencode($filter_date) ?>&type=<?= urlencode($filter_type) ?>" 
                 class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                ก่อนหน้า
              </a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
              <a href="?page=<?= $page + 1 ?>&per_page=<?= $per_page ?>&user=<?= urlencode($filter_user) ?>&date=<?= urlencode($filter_date) ?>&type=<?= urlencode($filter_type) ?>" 
                 class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                ถัดไป
              </a>
            <?php endif; ?>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                แสดง <span class="font-medium"><?= $total_records > 0 ? number_format(($page - 1) * $per_page + 1) : 0 ?></span> ถึง 
                <span class="font-medium"><?= number_format(min($page * $per_page, $total_records)) ?></span> จาก 
                <span class="font-medium"><?= number_format($total_records) ?></span> รายการ
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <!-- Previous Page Link -->
                <?php if ($page > 1): ?>
                  <a href="?page=<?= $page - 1 ?>&per_page=<?= $per_page ?>&user=<?= urlencode($filter_user) ?>&date=<?= urlencode($filter_date) ?>&type=<?= urlencode($filter_type) ?>" 
                     class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Previous</span>
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                  </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1):
                ?>
                  <a href="?page=1&per_page=<?= $per_page ?>&user=<?= urlencode($filter_user) ?>&date=<?= urlencode($filter_date) ?>&type=<?= urlencode($filter_type) ?>" 
                     class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    1
                  </a>
                  <?php if ($start_page > 2): ?>
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                      ...
                    </span>
                  <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                  <a href="?page=<?= $i ?>&per_page=<?= $per_page ?>&user=<?= urlencode($filter_user) ?>&date=<?= urlencode($filter_date) ?>&type=<?= urlencode($filter_type) ?>" 
                     class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?= $i == $page ? 'bg-blue-600 text-white border-blue-600 z-10' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                    <?= $i ?>
                  </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                  <?php if ($end_page < $total_pages - 1): ?>
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                      ...
                    </span>
                  <?php endif; ?>
                  <a href="?page=<?= $total_pages ?>&per_page=<?= $per_page ?>&user=<?= urlencode($filter_user) ?>&date=<?= urlencode($filter_date) ?>&type=<?= urlencode($filter_type) ?>" 
                     class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <?= $total_pages ?>
                  </a>
                <?php endif; ?>

                <!-- Next Page Link -->
                <?php if ($page < $total_pages): ?>
                  <a href="?page=<?= $page + 1 ?>&per_page=<?= $per_page ?>&user=<?= urlencode($filter_user) ?>&date=<?= urlencode($filter_date) ?>&type=<?= urlencode($filter_type) ?>" 
                     class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Next</span>
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                  </a>
                <?php endif; ?>
              </nav>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</div>

<script>
// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('[class*="bg-green-50"], [class*="bg-red-50"]');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, 5000);
  });
});
</script>

</body>
</html>