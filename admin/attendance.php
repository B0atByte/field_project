<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
requirePermission('page_logs');
include '../config/db.php';

// Filters
$filter_date  = $_GET['date']    ?? date('Y-m-d');
$filter_user  = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $filter_date = date('Y-m-d');
}

// List of field users
$field_users = [];
$res = $conn->query("SELECT id, name FROM users WHERE role = 'field' AND active = 1 ORDER BY name");
while ($r = $res->fetch_assoc()) $field_users[] = $r;

// Build query for attendance records on the selected date
$where = ["DATE(wc.checked_at) = ?"];
$params = [$filter_date];
$types  = 's';

if ($filter_user > 0) {
    $where[] = "wc.user_id = ?";
    $params[] = $filter_user;
    $types   .= 'i';
}

$sql = "
    SELECT u.id AS user_id, u.name AS user_name,
           MIN(CASE WHEN wc.type='checkin'  THEN wc.checked_at END) AS first_checkin,
           MAX(CASE WHEN wc.type='checkout' THEN wc.checked_at END) AS last_checkout,
           SUM(wc.type='checkin')  AS checkin_count,
           SUM(wc.type='checkout') AS checkout_count,
           MIN(CASE WHEN wc.type='checkin' THEN wc.latitude  END) AS in_lat,
           MIN(CASE WHEN wc.type='checkin' THEN wc.longitude END) AS in_lng,
           MAX(CASE WHEN wc.type='checkout' THEN wc.latitude  END) AS out_lat,
           MAX(CASE WHEN wc.type='checkout' THEN wc.longitude END) AS out_lng
    FROM work_checkins wc
    JOIN users u ON wc.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY u.id, u.name
    ORDER BY first_checkin ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$attendance = $stmt->get_result()->fetchAll(MYSQLI_ASSOC);
$stmt->close();

// ดึง raw records ทั้งหมดของวันที่เลือก (timeline)
$raw_sql = "
    SELECT wc.*, u.name AS user_name
    FROM work_checkins wc
    JOIN users u ON wc.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY wc.checked_at ASC
";
$stmt2 = $conn->prepare($raw_sql);
$stmt2->bind_param($types, ...$params);
$stmt2->execute();
$raw_records = $stmt2->get_result()->fetchAll(MYSQLI_ASSOC);
$stmt2->close();

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
      </div>

      <!-- Filter -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6 shadow-sm">
        <form method="GET" class="flex flex-wrap items-end gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">วันที่</label>
            <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"
              class="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">พนักงาน</label>
            <select name="user_id" class="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-[160px]">
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
        <div class="p-5 border-b border-gray-100 flex items-center gap-2">
          <i class="fas fa-table text-gray-500"></i>
          <h2 class="font-bold text-gray-800">รายละเอียดเวลาทำงาน —
            <?= date('d/m/Y', strtotime($filter_date)) ?>
          </h2>
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

      <!-- Timeline of raw records -->
      <?php if (!empty($raw_records)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="p-5 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-history text-gray-500"></i>
            <h2 class="font-bold text-gray-800">Timeline การลงเวลาทั้งหมด</h2>
          </div>
          <div class="divide-y divide-gray-50">
            <?php foreach ($raw_records as $rec): ?>
              <div class="flex items-start gap-4 px-5 py-3 hover:bg-gray-50 transition">
                <div class="mt-0.5 flex-shrink-0">
                  <?php if ($rec['type'] === 'checkin'): ?>
                    <span class="w-8 h-8 bg-green-100 text-green-700 rounded-full flex items-center justify-center">
                      <i class="fas fa-sign-in-alt text-xs"></i>
                    </span>
                  <?php else: ?>
                    <span class="w-8 h-8 bg-red-100 text-red-600 rounded-full flex items-center justify-center">
                      <i class="fas fa-sign-out-alt text-xs"></i>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($rec['user_name']) ?></span>
                    <span class="text-xs <?= $rec['type'] === 'checkin' ? 'text-green-700 bg-green-50' : 'text-red-600 bg-red-50' ?> px-2 py-0.5 rounded-full font-semibold">
                      <?= $rec['type'] === 'checkin' ? 'เข้างาน' : 'ออกงาน' ?>
                    </span>
                  </div>
                  <div class="text-xs text-gray-500 mt-0.5">
                    <?= date('H:i:s', strtotime($rec['checked_at'])) ?>
                    <?php if ($rec['latitude'] && $rec['longitude']): ?>
                      — <a href="https://maps.google.com/?q=<?= $rec['latitude'] ?>,<?= $rec['longitude'] ?>"
                           target="_blank" class="text-blue-500 hover:underline">
                           <i class="fas fa-map-pin"></i> ดูพิกัด
                         </a>
                    <?php else: ?>
                      — ไม่ระบุพิกัด
                    <?php endif; ?>
                    <?php if ($rec['address']): ?>
                      <span class="block truncate"><?= htmlspecialchars(mb_strimwidth($rec['address'], 0, 100, '...')) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<?php include '../components/footer.php'; ?>
