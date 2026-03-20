<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
requirePermission('page_logs');
include '../config/db.php';

// Filters
$filter_date  = $_GET['date']    ?? date('Y-m-d');
$filter_user  = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$per_page = in_array((int)($_GET['per_page'] ?? 25), [10, 25, 50, 100]) ? (int)($_GET['per_page'] ?? 25) : 25;
$page     = max(1, (int)($_GET['page'] ?? 1));

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $filter_date = date('Y-m-d');
}

// List of field users
$field_users = [];
$res = $conn->query("SELECT id, name FROM users WHERE role = 'field' AND active = 1 ORDER BY name");
while ($r = $res->fetch_assoc()) $field_users[] = $r;

// Build query for attendance records on the selected date
$where  = ["DATE(wc.checkin_at) = ?"];
$params = [$filter_date];
$types  = 's';

if ($filter_user > 0) {
    $where[] = "wc.user_id = ?";
    $params[] = $filter_user;
    $types   .= 'i';
}

// นับ session ทั้งหมด (1 row = 1 session)
$count_sql = "
    SELECT COUNT(*) AS total
    FROM work_checkins wc
    JOIN users u ON wc.user_id = u.id
    WHERE " . implode(' AND ', $where);
$stmt_c = $conn->prepare($count_sql);
$stmt_c->bind_param($types, ...$params);
$stmt_c->execute();
$total_attendance = (int)$stmt_c->get_result()->fetch_assoc()['total'];
$stmt_c->close();
$total_pages = max(1, (int)ceil($total_attendance / $per_page));
$page   = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "
    SELECT wc.id,
           u.id AS user_id, u.name AS user_name,
           wc.checkin_at  AS first_checkin,
           wc.checkout_at AS last_checkout,
           wc.checkin_lat  AS in_lat,  wc.checkin_lng  AS in_lng,
           wc.checkout_lat AS out_lat, wc.checkout_lng AS out_lng
    FROM work_checkins wc
    JOIN users u ON wc.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY wc.checkin_at ASC
    LIMIT ? OFFSET ?
";
$params_p = array_merge($params, [$per_page, $offset]);
$types_p  = $types . 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types_p, ...$params_p);
$stmt->execute();
$attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Helper: สร้าง URL query ที่ preserve ค่า filter ปัจจุบัน
function attendanceUrl(array $overrides = []): string {
    global $filter_date, $filter_user, $per_page, $page;
    $base = [
        'date'    => $filter_date,
        'user_id' => $filter_user,
        'per_page'=> $per_page,
        'page'    => $page,
    ];
    return '?' . http_build_query(array_merge($base, $overrides));
}

// Users ที่ยังไม่ได้ check-in วันนี้
$checked_in_ids = array_column($attendance, 'user_id');
$absent_users   = array_filter($field_users, fn($u) => !in_array($u['id'], $checked_in_ids));

$page_title = 'บันทึกเวลาทำงาน';
include '../components/header.php';
?>

<div class="flex h-screen overflow-hidden">
  <?php include '../components/sidebar.php'; ?>

  <main class="flex-1 overflow-y-auto md:ml-64 bg-gray-50">
    <div class="p-6 max-w-7xl mx-auto">

      <!-- Page Title -->
      <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <i class="fas fa-clock text-blue-600"></i> บันทึกเวลาทำงาน
          </h1>
          <p class="text-sm text-gray-500 mt-1">ดูเวลาเข้า-ออกงานของพนักงานภาคสนาม</p>
        </div>
        <!-- Export Button -->
        <div class="flex items-center gap-2">
          <a id="exportBtn"
             href="export_attendance.php?date=<?= urlencode($filter_date) ?>&user_id=<?= $filter_user ?>"
             class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
            <i class="fas fa-file-excel"></i> Export Excel
          </a>
        </div>
      </div>

      <!-- Filter -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6 shadow-sm">
        <form method="GET" id="filterForm" class="flex flex-wrap items-end gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">วันที่</label>
            <input type="date" name="date" id="fDate" value="<?= htmlspecialchars($filter_date) ?>"
              class="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">พนักงาน</label>
            <select name="user_id" id="fUser" class="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-[160px]">
              <option value="0">ทุกคน</option>
              <?php foreach ($field_users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($u['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-xl text-sm font-semibold hover:bg-blue-700 transition flex items-center gap-2">
            <i class="fas fa-filter"></i> กรอง
          </button>
          <a href="attendance.php" class="text-sm text-gray-500 hover:text-gray-700 underline">รีเซ็ต</a>
        </form>

        <!-- Export Range -->
        <div class="mt-4 pt-4 border-t border-gray-100">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Export Excel ช่วงวันที่</p>
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">ตั้งแต่วันที่</label>
              <input type="date" id="expFrom" value="<?= htmlspecialchars($filter_date) ?>"
                class="border border-gray-300 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">ถึงวันที่</label>
              <input type="date" id="expTo" value="<?= htmlspecialchars($filter_date) ?>"
                class="border border-gray-300 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <button onclick="doExport()" class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-5 py-2 rounded-xl transition shadow-sm">
              <i class="fas fa-file-excel"></i> Download Excel
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm text-center">
          <div class="text-3xl font-bold text-blue-600"><?= count($attendance) ?></div>
          <div class="text-sm text-gray-500 mt-1">คนที่ลงเวลาแล้ว</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm text-center">
          <div class="text-3xl font-bold text-red-500"><?= count($absent_users) ?></div>
          <div class="text-sm text-gray-500 mt-1">คนที่ยังไม่ลงเวลา</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm text-center">
          <?php $checked_out = count(array_filter($attendance, fn($a) => !empty($a['last_checkout']))); ?>
          <div class="text-3xl font-bold text-green-600"><?= $checked_out ?></div>
          <div class="text-sm text-gray-500 mt-1">คนที่ออกงานแล้ว</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm text-center">
          <div class="text-3xl font-bold text-yellow-500"><?= count($field_users) ?></div>
          <div class="text-sm text-gray-500 mt-1">พนักงานทั้งหมด</div>
        </div>
      </div>

      <!-- Attendance Table -->
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-6 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <i class="fas fa-table text-gray-500"></i>
            <h2 class="font-bold text-gray-800">รายละเอียดเวลาทำงาน —
              <?= date('d/m/Y', strtotime($filter_date)) ?>
            </h2>
            <span class="text-xs text-gray-400">(<?= $total_attendance ?> รายการ)</span>
          </div>
          <div class="flex items-center gap-2 text-sm">
            <span class="text-gray-500">แสดง</span>
            <select onchange="window.location.href=this.value" class="border border-gray-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
              <?php foreach ([10, 25, 50, 100] as $opt): ?>
                <option value="<?= attendanceUrl(['per_page' => $opt, 'page' => 1]) ?>"
                  <?= $per_page === $opt ? 'selected' : '' ?>>
                  <?= $opt ?> รายการ
                </option>
              <?php endforeach; ?>
            </select>
            <span class="text-gray-500">/ หน้า</span>
          </div>
        </div>
        <?php if (empty($attendance)): ?>
          <div class="p-10 text-center text-gray-400">
            <i class="fas fa-inbox text-4xl mb-3 block"></i>
            ไม่มีข้อมูลการลงเวลาในวันนี้
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-gray-600 font-semibold text-xs uppercase">
                <tr>
                  <th class="px-5 py-3 text-left">ชื่อพนักงาน</th>
                  <th class="px-5 py-3 text-left">เวลาเข้างาน</th>
                  <th class="px-5 py-3 text-left">เวลาออกงาน</th>
                  <th class="px-5 py-3 text-left">ระยะเวลา</th>
                  <th class="px-5 py-3 text-left">พิกัดเข้างาน</th>
                  <th class="px-5 py-3 text-left">พิกัดออกงาน</th>
                  <th class="px-5 py-3 text-center">สถานะ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <?php foreach ($attendance as $row):
                  $duration = '';
                  if ($row['first_checkin'] && $row['last_checkout']) {
                      $diff = strtotime($row['last_checkout']) - strtotime($row['first_checkin']);
                      $h = floor($diff / 3600);
                      $m = floor(($diff % 3600) / 60);
                      $duration = "{$h} ชม. {$m} นาที";
                  }
                  $isOut = !empty($row['last_checkout']);
                ?>
                  <tr class="hover:bg-gray-50 transition">
                    <td class="px-5 py-4 font-semibold text-gray-900">
                      <?= htmlspecialchars($row['user_name']) ?>
                    </td>
                    <td class="px-5 py-4 text-gray-700">
                      <?= $row['first_checkin']
                          ? '<span class="text-green-700 font-semibold">' . date('H:i:s', strtotime($row['first_checkin'])) . '</span>'
                          : '<span class="text-gray-400">—</span>' ?>
                    </td>
                    <td class="px-5 py-4 text-gray-700">
                      <?= $row['last_checkout']
                          ? '<span class="text-red-600 font-semibold">' . date('H:i:s', strtotime($row['last_checkout'])) . '</span>'
                          : '<span class="text-gray-400">—</span>' ?>
                    </td>
                    <td class="px-5 py-4 text-gray-700">
                      <?= $duration ?: '<span class="text-gray-400">กำลังทำงาน</span>' ?>
                    </td>
                    <td class="px-5 py-4">
                      <?php if ($row['in_lat'] && $row['in_lng']): ?>
                        <a href="https://maps.google.com/?q=<?= $row['in_lat'] ?>,<?= $row['in_lng'] ?>"
                           target="_blank" class="text-blue-500 hover:underline text-xs">
                          <i class="fas fa-map-marker-alt"></i>
                          <?= number_format($row['in_lat'], 5) ?>, <?= number_format($row['in_lng'], 5) ?>
                        </a>
                      <?php else: ?>
                        <span class="text-gray-400 text-xs">ไม่ระบุ</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-5 py-4">
                      <?php if ($row['out_lat'] && $row['out_lng']): ?>
                        <a href="https://maps.google.com/?q=<?= $row['out_lat'] ?>,<?= $row['out_lng'] ?>"
                           target="_blank" class="text-blue-500 hover:underline text-xs">
                          <i class="fas fa-map-marker-alt"></i>
                          <?= number_format($row['out_lat'], 5) ?>, <?= number_format($row['out_lng'], 5) ?>
                        </a>
                      <?php else: ?>
                        <span class="text-gray-400 text-xs">ไม่ระบุ</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 text-center">
                      <?php if ($isOut): ?>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                          <i class="fas fa-check"></i> ออกงานแล้ว
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 animate-pulse">
                          <i class="fas fa-circle text-[8px]"></i> กำลังทำงาน
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($total_pages > 1): ?>
            <div class="px-5 py-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
              <span class="text-sm text-gray-500">
                หน้า <?= $page ?> / <?= $total_pages ?>
                &nbsp;·&nbsp; แสดง <?= count($attendance) ?> จาก <?= $total_attendance ?> รายการ
              </span>
              <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                  <a href="<?= attendanceUrl(['page' => 1]) ?>" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">«</a>
                  <a href="<?= attendanceUrl(['page' => $page - 1]) ?>" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">‹</a>
                <?php endif; ?>
                <?php
                $start_p = max(1, $page - 2);
                $end_p   = min($total_pages, $page + 2);
                for ($p = $start_p; $p <= $end_p; $p++): ?>
                  <a href="<?= attendanceUrl(['page' => $p]) ?>"
                     class="px-3 py-1.5 rounded-lg text-sm font-semibold transition
                            <?= $p === $page ? 'bg-blue-600 text-white border border-blue-600' : 'border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                    <?= $p ?>
                  </a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                  <a href="<?= attendanceUrl(['page' => $page + 1]) ?>" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">›</a>
                  <a href="<?= attendanceUrl(['page' => $total_pages]) ?>" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">»</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Absent Users -->
      <?php if (!empty($absent_users)): ?>
        <div class="bg-white rounded-2xl border border-red-100 shadow-sm mb-6 overflow-hidden">
          <div class="p-5 border-b border-red-50 flex items-center gap-2">
            <i class="fas fa-user-times text-red-500"></i>
            <h2 class="font-bold text-gray-800">ยังไม่ลงเวลาวันนี้ (<?= count($absent_users) ?> คน)</h2>
          </div>
          <div class="p-5 flex flex-wrap gap-2">
            <?php foreach ($absent_users as $u): ?>
              <span class="inline-flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-100 rounded-xl text-sm text-red-700 font-medium">
                <i class="fas fa-user"></i> <?= htmlspecialchars($u['name']) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>


    </div>
  </main>
</div>

<script>
  function doExport() {
    const from = document.getElementById('expFrom').value;
    const to   = document.getElementById('expTo').value;
    const user = document.getElementById('fUser').value;
    if (!from || !to) { alert('กรุณาระบุช่วงวันที่'); return; }
    if (from > to) { alert('วันเริ่มต้นต้องไม่มากกว่าวันสิ้นสุด'); return; }
    const params = new URLSearchParams({ date_from: from, date_to: to, user_id: user, range: '1' });
    window.location.href = 'export_attendance.php?' + params.toString();
  }

  // Sync export header button with filter date
  document.getElementById('exportBtn').addEventListener('click', function(e) {
    e.preventDefault();
    const date = document.getElementById('fDate').value;
    const user = document.getElementById('fUser').value;
    const params = new URLSearchParams({ date_from: date, date_to: date, user_id: user, range: '1' });
    window.location.href = 'export_attendance.php?' + params.toString();
  });

  // Auto-set export range to match current filter date
  document.getElementById('fDate').addEventListener('change', function() {
    document.getElementById('expFrom').value = this.value;
    document.getElementById('expTo').value   = this.value;
  });
</script>

<?php include '../components/footer.php'; ?>
