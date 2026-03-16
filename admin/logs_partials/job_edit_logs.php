<?php
// Job Edit Logs Content with Pagination

// Filter
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$editor = $_GET['editor'] ?? '';
$job_id = $_GET['job_id'] ?? '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
if (!in_array($per_page, [10, 25, 50, 100])) {
    $per_page = 50;
}

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

// Count total
$count_sql = "SELECT COUNT(*) as total FROM job_edit_logs j LEFT JOIN users u ON j.edited_by = u.id $where";
$stmt_count = $conn->prepare($count_sql);
if ($params) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_logs = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_count->close();

$total_pages = ceil($total_logs / $per_page);
$page = min($page, max(1, $total_pages));
$offset = ($page - 1) * $per_page;

$showing_from = $total_logs > 0 ? $offset + 1 : 0;
$showing_to = min($offset + $per_page, $total_logs);

// Query with LIMIT
$sql = "
    SELECT j.job_id, j.change_summary, j.edited_at, u.name AS editor_name
    FROM job_edit_logs j
    LEFT JOIN users u ON j.edited_by = u.id
    $where
    ORDER BY j.edited_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$per_page, $offset]));
} else {
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- Filter Section -->
<section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up mb-6">
  <div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-800 flex items-center">
      <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
      </svg>
      กรองข้อมูล
    </h2>
    <p class="text-sm text-gray-500 mt-1">ค้นหาและกรองประวัติการแก้ไขตาม Job ID, ผู้แก้ไข และช่วงวันที่</p>
  </div>

  <form method="get" class="space-y-4">
    <input type="hidden" name="tab" value="job_edit">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Job ID</label>
        <input type="text" name="job_id" value="<?= htmlspecialchars($job_id) ?>" placeholder="ค้นหา Job ID..."
               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">ชื่อผู้แก้ไข</label>
        <input type="text" name="editor" value="<?= htmlspecialchars($editor) ?>" placeholder="ค้นหาชื่อผู้แก้ไข..."
               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">จากวันที่</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"
               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">ถึงวันที่</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"
               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">แสดงต่อหน้า</label>
        <select name="per_page"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent appearance-none bg-white"
                style="background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e&quot;); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em;">
          <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
          <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
          <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
          <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
        </select>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 pt-4">
      <button type="submit" class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
        กรองข้อมูล
      </button>
      <a href="logs.php?tab=job_edit" class="inline-flex items-center justify-center px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        รีเซ็ต
      </a>
    </div>
  </form>
</section>

<!-- Clear Logs Section -->
<section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up mb-6">
  <div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-800 flex items-center">
      <svg class="w-6 h-6 mr-3 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
      </svg>
      จัดการข้อมูล Log
    </h2>
    <p class="text-sm text-gray-500 mt-1">ล้างประวัติการแก้ไขทั้งหมด (ต้องยืนยันรหัสผ่าน Admin)</p>
  </div>

  <form method="post" action="logs.php?tab=job_edit" onsubmit="return confirm('คุณต้องการล้าง log ทั้งหมดหรือไม่?\n\nการดำเนินการนี้ไม่สามารถยกเลิกได้และต้องเป็นผู้ดูแลระบบเท่านั้น');" class="flex flex-col sm:flex-row gap-4">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="log_type" value="job_edit">
    <div class="flex-1">
      <input type="password" name="admin_password" placeholder="ใส่รหัสผ่าน Admin เพื่อยืนยัน"
             class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" required>
    </div>
    <button type="submit" name="clear_logs"
            class="inline-flex items-center justify-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
      ล้างทั้งหมด
    </button>
  </form>
</section>

<!-- Logs Table -->
<section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up">
  <div class="mb-6 flex items-center justify-between">
    <div>
      <h2 class="text-xl font-semibold text-gray-800 flex items-center">
        <svg class="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        รายการประวัติการแก้ไข
      </h2>
      <p class="text-sm text-gray-500 mt-1">
        <?php if ($total_logs > 0): ?>
          แสดง <?= number_format($showing_from) ?> - <?= number_format($showing_to) ?> จาก <?= number_format($total_logs) ?> รายการ
        <?php else: ?>
          ไม่พบรายการ
        <?php endif; ?>
      </p>
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
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Job ID</th>
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ผู้แก้ไข</th>
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">รายละเอียดการเปลี่ยนแปลง</th>
          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">วันเวลา</th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <?php if ($result->num_rows > 0): ?>
          <?php while ($log = $result->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50 transition-colors duration-200">
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                  #<?= $log['job_id'] ?>
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center shadow-md">
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
                  <?= htmlspecialchars($log['change_summary']) ?>
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

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
    <div>
      <p class="text-sm text-gray-700">
        แสดง <span class="font-medium"><?= number_format($showing_from) ?></span> ถึง
        <span class="font-medium"><?= number_format($showing_to) ?></span> จาก
        <span class="font-medium"><?= number_format($total_logs) ?></span> รายการ
      </p>
    </div>
    <div>
      <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
             class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
          </a>
        <?php endif; ?>

        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        for ($i = $start_page; $i <= $end_page; $i++): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
             class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?= $i == $page ? 'bg-yellow-600 text-white border-yellow-600 z-10' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
             class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
            </svg>
          </a>
        <?php endif; ?>
      </nav>
    </div>
  </div>
  <?php endif; ?>
</section>
