<?php
// Login Logs Content
// POST handling is done in logs.php

// รับค่า filter (standardize naming)
$filter_user = $_GET['user'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_type = $_GET['type'] ?? '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? min(250, max(10, (int) $_GET['per_page'])) : 25;

// สร้าง WHERE conditions
$where_conditions = ["1=1"];
$params = [];
$types = '';

if ($filter_user !== '') {
  $where_conditions[] = "u.name LIKE ?";
  $params[] = "%$filter_user%";
  $types .= 's';
}
if ($start_date !== '' && $end_date !== '') {
  $where_conditions[] = "DATE(l.login_time) BETWEEN ? AND ?";
  $params[] = $start_date;
  $params[] = $end_date;
  $types .= 'ss';
}
if ($filter_type !== '') {
  $where_conditions[] = "l.type = ?";
  $params[] = $filter_type;
  $types .= 's';
}

// จำกัดการดึงข้อมูลเฉพาะ 30 วันล่าสุด (ถ้าไม่ได้ระบุวันที่)
if ($start_date === '' && $end_date === '') {
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
function formatIPAddress($ip)
{
  if ($ip === '::1' || $ip === '::ffff:127.0.0.1') {
    return '127.0.0.1 (localhost)';
  }
  if (strpos($ip, '::ffff:') === 0) {
    return substr($ip, 7);
  }
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    if (
      preg_match('/^10\./', $ip) ||
      preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) ||
      preg_match('/^192\.168\./', $ip)
    ) {
      return $ip . ' (private)';
    }
  }
  return $ip;
}

// ฟังก์ชันแสดง platform และ browser
function parseUserAgent($ua)
{
  $platform = 'Unknown';
  $browser = 'Unknown';

  if (preg_match('/windows/i', $ua))
    $platform = 'Windows';
  elseif (preg_match('/macintosh|mac os x/i', $ua))
    $platform = 'Mac';
  elseif (preg_match('/android/i', $ua))
    $platform = 'Android';
  elseif (preg_match('/iphone/i', $ua))
    $platform = 'iPhone';
  elseif (preg_match('/ipad/i', $ua))
    $platform = 'iPad';
  elseif (preg_match('/linux/i', $ua))
    $platform = 'Linux';

  if (preg_match('/chrome/i', $ua) && !preg_match('/edge|edg/i', $ua))
    $browser = 'Chrome';
  elseif (preg_match('/firefox/i', $ua))
    $browser = 'Firefox';
  elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua))
    $browser = 'Safari';
  elseif (preg_match('/edge|edg/i', $ua))
    $browser = 'Edge';
  elseif (preg_match('/msie|trident/i', $ua))
    $browser = 'IE';

  return "$platform | $browser";
}
?>

<!-- Summary Card -->
<section class="bg-gradient-to-r from-teal-500 to-teal-600 text-white p-6 rounded-2xl shadow-lg animate-slide-up mb-6">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-teal-100 text-sm font-medium">การเข้าสู่ระบบทั้งหมด</p>
      <p class="text-3xl font-bold"><?= number_format($total_records) ?></p>
      <p class="text-teal-100 text-sm">รายการ (30 วันล่าสุด)</p>
    </div>
    <div class="bg-white bg-opacity-20 p-3 rounded-xl">
      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </div>
  </div>
</section>

<!-- Filter Section -->
<section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up mb-6">
  <div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-800 flex items-center">
      <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
      </svg>
      กรองข้อมูล Log
    </h2>
    <p class="text-sm text-gray-500 mt-1">ค้นหาและกรองประวัติการเข้าใช้งานตามผู้ใช้ วันที่ และประเภท</p>
  </div>

  <form method="get" action="logs.php" class="space-y-4">
    <input type="hidden" name="tab" value="login">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">ชื่อผู้ใช้</label>
        <input type="text" name="user" value="<?= htmlspecialchars($filter_user) ?>" placeholder="ค้นหาชื่อผู้ใช้..."
          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">วันที่เริ่มต้น</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"
          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">วันที่สิ้นสุด</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"
          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">ประเภท</label>
        <select name="type"
          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent appearance-none bg-white"
          style="background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e&quot;); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em;">
          <option value="">ทั้งหมด</option>
          <option value="login" <?= $filter_type === 'login' ? 'selected' : '' ?>>Login</option>
          <option value="logout" <?= $filter_type === 'logout' ? 'selected' : '' ?>>Logout</option>
          <option value="login_fail" <?= $filter_type === 'login_fail' ? 'selected' : '' ?>>Failed</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">แสดงต่อหน้า</label>
        <select name="per_page"
          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent appearance-none bg-white"
          style="background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e&quot;); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em;">
          <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
          <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
          <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
          <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
          <option value="250" <?= $per_page == 250 ? 'selected' : '' ?>>250</option>
        </select>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 pt-4">
      <button type="submit"
        class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
        กรองข้อมูล
      </button>
      <a href="logs.php?tab=login"
        class="inline-flex items-center justify-center px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        รีเซ็ต
      </a>
    </div>
  </form>
</section>

<!-- Clear All Section -->
<section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up mb-6">
  <div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-800 flex items-center">
      <svg class="w-6 h-6 mr-3 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      จัดการข้อมูล Log
    </h2>
    <p class="text-sm text-gray-500 mt-1">ล้างประวัติการเข้าสู่ระบบทั้งหมด (ต้องยืนยันรหัสผ่าน Admin)</p>
  </div>

  <form method="post" action="logs.php?tab=login"
    onsubmit="return confirm('คุณต้องการล้าง Log ทั้งหมดหรือไม่?\n\nการดำเนินการนี้จะลบประวัติการเข้าใช้งานทั้งหมดออกจากระบบ และไม่สามารถกู้คืนได้\n\nต้องเป็นผู้ดูแลระบบเท่านั้น');"
    class="flex flex-col sm:flex-row gap-4">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="log_type" value="login">
    <div class="flex-1">
      <input type="password" name="admin_password" placeholder="ใส่รหัสผ่าน Admin เพื่อยืนยัน"
        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
        required>
    </div>
    <button type="submit" name="clear_logs"
      class="inline-flex items-center justify-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
      </svg>
      ล้างทั้งหมด
    </button>
  </form>
</section>

<!-- Login Logs Table -->
<section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up">
  <div class="mb-6 flex items-center justify-between">
    <div>
      <h2 class="text-xl font-semibold text-gray-800 flex items-center">
        <svg class="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        รายการ Login Logs
      </h2>
      <p class="text-sm text-gray-500 mt-1">แสดง
        <?= $total_records > 0 ? number_format(($page - 1) * $per_page + 1) : 0 ?> -
        <?= number_format(min($page * $per_page, $total_records)) ?> จาก <?= number_format($total_records) ?> รายการ</p>
    </div>

    <?php if ($total_pages > 1): ?>
      <div class="text-sm text-gray-600">
        หน้า <?= $page ?> / <?= $total_pages ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">#</th>
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ผู้ใช้</th>
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">เวลา</th>
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ประเภท</th>
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">IP Address</th>
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">อุปกรณ์</th>
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
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                  <?= $row_number++ ?>
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div
                    class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-teal-400 to-teal-600 rounded-full flex items-center justify-center shadow-md">
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
                <div class="flex items-center space-x-2">
                  <div class="flex-shrink-0">
                    <div
                      class="w-10 h-10 bg-gradient-to-br from-indigo-100 to-indigo-200 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                    </div>
                  </div>
                  <div class="flex flex-col">
                    <span class="font-semibold text-gray-900 text-sm">
                      <?= date('d/m/Y', strtotime($row['login_time'])) ?>
                    </span>
                    <span class="text-xs text-indigo-600 font-medium mt-0.5 flex items-center">
                      <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <?= date('H:i:s', strtotime($row['login_time'])) ?> น.
                    </span>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <?php
                $type_class = '';
                $type_text = '';

                if ($row['type'] === 'login') {
                  $type_class = 'bg-green-100 text-green-800 border border-green-200';
                  $type_text = 'Login';
                } elseif ($row['type'] === 'logout') {
                  $type_class = 'bg-blue-100 text-blue-800 border border-blue-200';
                  $type_text = 'Logout';
                } else {
                  $type_class = 'bg-red-100 text-red-800 border border-red-200';
                  $type_text = 'Failed';
                }
                ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $type_class ?>">
                  <?= $type_text ?>
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <?php
                $formatted_ip = formatIPAddress($row['ip_address']);
                $is_local = (strpos($formatted_ip, 'localhost') !== false || strpos($formatted_ip, 'private') !== false);
                $ip_badge_class = $is_local ? 'bg-yellow-50 text-yellow-800 border-yellow-200' : 'bg-blue-50 text-blue-800 border-blue-200';
                ?>
                <span
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-mono font-medium border <?= $ip_badge_class ?>">
                  <?= htmlspecialchars($formatted_ip) ?>
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">
                  <?= htmlspecialchars(parseUserAgent($row['user_agent'])) ?>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
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

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
    <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
      <div>
        <p class="text-sm text-gray-700">
          แสดง <span class="font-medium"><?= $total_records > 0 ? number_format(($page - 1) * $per_page + 1) : 0 ?></span>
          ถึง
          <span class="font-medium"><?= number_format(min($page * $per_page, $total_records)) ?></span> จาก
          <span class="font-medium"><?= number_format($total_records) ?></span> รายการ
        </p>
      </div>
      <div>
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
          <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
              class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
              <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                  d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                  clip-rule="evenodd" />
              </svg>
            </a>
          <?php endif; ?>

          <?php
          $start_page = max(1, $page - 2);
          $end_page = min($total_pages, $page + 2);

          for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?= $i == $page ? 'bg-teal-600 text-white border-teal-600 z-10' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>

          <?php if ($page < $total_pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
              class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
              <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                  d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                  clip-rule="evenodd" />
              </svg>
            </a>
          <?php endif; ?>
        </nav>
      </div>
    </div>
  <?php endif; ?>
</section>