<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'manager'])) {
  header("Location: ../index.php");
  exit;
}

ini_set('memory_limit', '2048M');
set_time_limit(0);

include '../config/db.php';
require_once '../includes/csrf.php';
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$imported_data = [];
$failed_rows = [];
$imported_by = $_SESSION['user']['id'] ?? null;

$imported_by_name = '';
$department_id = null;
if ($imported_by) {
  $stmt_user = $conn->prepare("SELECT name, department_id FROM users WHERE id = ?");
  $stmt_user->bind_param("i", $imported_by);
  $stmt_user->execute();
  $stmt_user->bind_result($imported_by_name, $department_id);
  $stmt_user->fetch();
  $stmt_user->close();
}

/* ---------- helper: ตรวจว่าคอลัมน์มีอยู่จริงไหม ---------- */
function hasColumn(mysqli $conn, string $table, string $column): bool
{
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ss", $table, $column);
  $stmt->execute();
  $stmt->store_result();
  $ok = $stmt->num_rows > 0;
  $stmt->close();
  return $ok;
}

$page_title = "นำเข้า/ลบงาน - Field Project";
include '../components/header.php';

$total_rows = 0;
$imported_count = 0;
$is_delete_mode = false;
?>

<!-- Topbar (มือถือ) -->
<div class="md:hidden flex items-center justify-between px-6 py-4 bg-white shadow-sm border-b border-gray-200">
  <div class="flex items-center space-x-3">
    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
      <i class="fas fa-file-import text-gray-500 text-lg"></i>
    </div>
    <h1 class="text-gray-900 font-semibold text-lg">นำเข้างาน</h1>
  </div>
  <button onclick="toggleSidebar()"
    class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  </button>
</div>

<div class="flex">
  <?php include '../components/sidebar.php'; ?>

  <main class="flex-1 p-6 ml-64 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto">

      <!-- Header Section -->
      <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-lg mb-4">
          <i class="fas fa-file-import text-gray-500 text-2xl"></i>
        </div>
        <h1 class="text-2xl font-semibold text-gray-900 mb-2">
          นำเข้าและจัดการงาน
        </h1>
        <p class="text-sm text-gray-600 max-w-2xl mx-auto">
          อัปโหลดไฟล์ Excel เพื่อเพิ่มหรือลบงานในระบบอย่างง่ายดาย
        </p>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 xl:grid-cols-4 gap-8">

        <!-- Form Section -->
        <div class="xl:col-span-3">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <!-- Form Header -->
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
              <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <span class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center mr-2">
                  <i class="fas fa-clipboard"></i>
                </span>
                ฟอร์มนำเข้าข้อมูล
              </h2>
            </div>

            <!-- Form Body -->
            <div class="p-8">
              <form id="importForm" method="post" enctype="multipart/form-data" class="space-y-8">
                <?= csrfField() ?>

                <!-- File Upload Section -->
                <div class="space-y-3">
                  <label class="block text-base font-semibold text-gray-800 mb-3">
                    <i class="fas fa-folder-open mr-1"></i>เลือกไฟล์ Excel
                  </label>

                  <div class="relative">
                    <input type="file" name="excel" accept=".xlsx" required id="fileInput" class="hidden">
                    <div onclick="document.getElementById('fileInput').click()"
                      class="border-2 border-dashed border-gray-300 hover:border-blue-400 rounded-lg p-6 text-center cursor-pointer transition-all duration-200 hover:bg-gray-50 group">
                      <div class="flex flex-col items-center space-y-3">
                        <div
                          class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                          <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                          </svg>
                        </div>
                        <div>
                          <p class="text-base font-medium text-gray-700 group-hover:text-blue-600">คลิกเพื่อเลือกไฟล์
                          </p>
                          <p class="text-sm text-gray-500 mt-1">รองรับไฟล์ .xlsx เท่านั้น</p>
                        </div>
                      </div>
                    </div>
                    <div id="fileName" class="mt-2 text-sm text-gray-600 hidden"></div>
                  </div>
                </div>

                <?php /* DISABLED TEMPORARILY - Settings Grid */ if (false): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                  <!-- Auto Delete Settings -->
                  <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                    <div class="flex items-center mb-3">
                      <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center mr-2">
                        <i class="fas fa-broom text-gray-500"></i>
                      </div>
                      <h3 class="text-base font-semibold text-gray-800">ลบงานอัตโนมัติ</h3>
                    </div>

                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      ลบงานอัตโนมัติภายใน (สูงสุด 7 วัน) หากไม่มีการวิ่งงาน
                    </label>
                    <select name="auto_delete_days"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                      <option value="0">ไม่ลบอัตโนมัติ</option>
                      <option value="1">1 วัน</option>
                      <option value="2">2 วัน</option>
                      <option value="3">3 วัน</option>
                      <option value="4">4 วัน</option>
                      <option value="5">5 วัน</option>
                      <option value="6">6 วัน</option>
                      <option value="7">7 วัน</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-2">
                      * หากฐานข้อมูลไม่มีคอลัมน์สำหรับบันทึก ระบบจะข้ามการตั้งค่านี้
                    </p>
                  </div>

                  <!-- Delete Mode -->
                  <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                    <div class="flex items-center mb-3">
                      <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center mr-2">
                        <i class="fas fa-trash text-gray-500"></i>
                      </div>
                      <h3 class="text-base font-semibold text-gray-800">โหมดลบงาน ปิดการใช้งานชั่วคราว</h3>
                    </div>

                    <label class="inline-flex items-center cursor-pointer group">
                      <!-- <input type="checkbox" name="delete_mode" id="delete_mode" 
                           class="w-5 h-5 text-red-600 border-2 border-red-300 rounded focus:ring-4 focus:ring-red-100"> -->
                      <span class="ml-3 text-sm font-medium text-red-700 group-hover:text-red-800">
                        ลบงานตาม "เลขสัญญา" ในไฟล์
                      </span>
                    </label>
                    <p class="text-xs text-red-600 mt-3 bg-red-100 p-2 rounded-lg">
                      <i class="fas fa-exclamation-triangle mr-1"></i>ระบบจะลบเฉพาะรายการที่มีเลขสัญญาตรงกัน
                    </p>
                  </div>
                </div>

                <?php endif; /* END DISABLED */ ?>
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 pt-4">
                  <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium text-base shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center">
                    <span class="mr-2">⬆️</span>
                    เริ่มนำเข้าข้อมูล
                  </button>

                  <a href="../assets/templates/ตัวอย่างไฟล์ อัพโหลด111.xlsx"
                    class="bg-green-600 hover:bg-green-700 text-white px-5 py-3 rounded-lg font-medium shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center">
                    <i class="fas fa-file-import mr-2"></i>
                    ดาวน์โหลดไฟล์ตัวอย่าง
                  </a>

                  <a href="add_job.php"
                    class="bg-gray-600 hover:bg-gray-700 text-white px-5 py-3 rounded-lg font-medium shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center">
                    <span class="mr-2"><i class="fas fa-pen"></i></span>
                    เพิ่มงานด้วยตนเอง
                  </a>
                </div>
              </form>
            </div>
          </div>

          <!-- Progress Section -->
          <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center mb-4">
              <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center mr-2">
                <i class="fas fa-spinner fa-spin text-gray-500"></i>
              </div>
              <h3 class="text-base font-semibold text-gray-800">ความคืบหน้าการนำเข้า</h3>
            </div>

            <div class="space-y-3">
              <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                <div id="progressBar" class="bg-blue-600 h-3 rounded-full transition-all duration-500 ease-out"
                  style="width: 0%"></div>
              </div>
              <div id="progressText" class="text-center text-sm text-gray-600 font-medium">รอการนำเข้าข้อมูล...</div>
            </div>
          </div>
        </div>

        <!-- Info Sidebar -->
        <div class="xl:col-span-1">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden sticky top-6">
            <!-- Sidebar Header -->
            <div class="bg-gray-50 px-5 py-4 border-b border-gray-200">
              <div class="flex items-center">
                <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center mr-2">
                  <span class="text-gray-700"><i class="fas fa-lightbulb"></i></span>
                </div>
                <h3 class="text-base font-semibold text-gray-800">คู่มือการใช้งาน</h3>
              </div>
            </div>

            <!-- Sidebar Content -->
            <div class="p-6 space-y-6">
              <!-- Requirements -->
              <div class="space-y-3">
                <h4 class="font-semibold text-gray-800 flex items-center">
                  <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                  ข้อกำหนดไฟล์
                </h4>
                <ul class="space-y-2 text-sm text-gray-600 ml-4">
                  <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    ไฟล์ต้องเป็น .xlsx เท่านั้น
                  </li>
                  <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    แถวแรกเป็นหัวตาราง
                  </li>
                  <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    ต้องมี Product, สัญญา, ชื่อลูกค้า
                  </li>
                </ul>
              </div>

              <!-- Features -->
              <div class="space-y-3">
                <h4 class="font-semibold text-gray-800 flex items-center">
                  <span class="w-2 h-2 bg-purple-500 rounded-full mr-2"></span>
                  ฟีเจอร์เด่น
                </h4>
                <ul class="space-y-2 text-sm text-gray-600 ml-4">
                  <li class="flex items-start">
                    <span class="text-purple-500 mr-2"><i class="fas fa-rocket"></i></span>
                    นำเข้าข้อมูลจำนวนมาก
                  </li>
                  <li class="flex items-start">
                    <span class="text-purple-500 mr-2"><i class="fas fa-bullseye"></i></span>
                    ตรวจสอบข้อมูลก่อนนำเข้า
                  </li>
                </ul>
              </div>

              <!-- User Info -->
              <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h4 class="font-semibold text-gray-800 mb-3 text-sm">ข้อมูลผู้ใช้</h4>
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-600">ผู้ลงงาน:</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($imported_by_name ?: '-') ?></span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">แผนก:</span>
                    <span
                      class="font-medium text-gray-800"><?= htmlspecialchars((string) $department_id ?: '-') ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php
      if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['excel'])) {
        requireCsrfToken();

        // Check for upload errors
        if ($_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
          $uploadErrorMessages = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์กำหนด (upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่ฟอร์มกำหนด (MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'ไฟล์ถูกอัปโหลดเพียงบางส่วน',
            UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ถูกอัปโหลด',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราว (Missing temporary folder)',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์ (Failed to write file to disk)',
            UPLOAD_ERR_EXTENSION => 'การอัปโหลดไฟล์ถูกหยุดโดยส่วนขยาย PHP (File upload stopped by extension)',
          ];
          $errorMessage = $uploadErrorMessages[$_FILES['excel']['error']] ?? 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุในการอัปโหลดไฟล์';

          echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>ผิดพลาด!</strong>
                <span class='block sm:inline'>{$errorMessage}</span>
              </div>";
          // Stop execution if upload failed
          return;
        }

        $file = $_FILES['excel']['tmp_name'];

        if (empty($file) || !file_exists($file)) {
          echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>ผิดพลาด!</strong>
                <span class='block sm:inline'>ไม่พบไฟล์ข้อมูลชั่วคราว (File not found). กรุณาลองใหม่อีกครั้ง</span>
              </div>";
          return;
        }

        try {
          $spreadsheet = IOFactory::load($file);
        } catch (\Exception $e) {
          echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <strong class='font-bold'>ผิดพลาด!</strong>
                <span class='block sm:inline'>ไม่สามารถอ่านไฟล์ Excel ได้: " . htmlspecialchars($e->getMessage()) . "</span>
              </div>";
          return;
        }
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $total_rows = max(0, count($rows) - 1);
        $imported_count = 0;
        $is_delete_mode = isset($_POST['delete_mode']);

        $auto_delete_days = isset($_POST['auto_delete_days']) ? (int) $_POST['auto_delete_days'] : 0;
        $auto_delete_days = max(0, min(7, $auto_delete_days));
        $auto_delete_at = $auto_delete_days > 0 ? date('Y-m-d 23:59:59', strtotime("+{$auto_delete_days} days")) : null;

        $has_auto_delete_days = hasColumn($conn, 'jobs', 'auto_delete_days');
        $has_auto_delete_at = hasColumn($conn, 'jobs', 'auto_delete_at');

        @ob_flush();
        @flush();

        for ($i = 1; $i < count($rows); $i++) {
          [
            $product,
            $contract_number,
            $customer_name,
            $customer_id_card,
            $location_area,
            $zone,
            $due_date,
            $overdue,
            $model,
            $model_detail,
            $color,
            $plate,
            $province,
            $os,
            $assignee_name
          ] = array_pad($rows[$i], 15, null);

          if ($is_delete_mode) {
            if (!empty(trim((string) $contract_number))) {
              $stmt = $conn->prepare("DELETE FROM jobs WHERE contract_number = ?");
              $stmt->bind_param("s", $contract_number);
              $stmt->execute();
              $stmt->close();
              $imported_count++;
              $percent = $total_rows ? round(($imported_count / $total_rows) * 100) : 100;
              echo "<script>
                    document.getElementById('progressBar').style.width = '{$percent}%';
                    document.getElementById('progressText').innerText = 'ลบ: {$imported_count}/{$total_rows} ({$percent}%)';
                </script>";
              @ob_flush();
              @flush();
              continue;
            } else {
              $failed_rows[] = ['row' => $i + 1, 'reason' => 'ไม่มีเลขสัญญาในโหมดลบ'];
              continue;
            }
          }

          // 1. Normalize inputs: Convert to string and trim
          $product = trim((string) $product);
          $contract_number = trim((string) $contract_number);
          $customer_name = trim((string) $customer_name);
          $customer_id_card = trim((string) $customer_id_card);
          $location_area = trim((string) $location_area);
          $zone = trim((string) $zone);
          $due_date = trim((string) $due_date);
          $overdue = trim((string) $overdue);
          $model = trim((string) $model);
          $model_detail = trim((string) $model_detail);
          $color = trim((string) $color);
          $plate = trim((string) $plate);
          $province = trim((string) $province);
          $os = trim((string) $os);
          $assignee_name = trim((string) $assignee_name);

          // 2. Validate required fields
          if ($product === '' || $contract_number === '' || $customer_name === '') {
            $failed_rows[] = ['row' => $i + 1, 'reason' => 'ข้อมูลไม่ครบ (ต้องมี Product, สัญญา, ชื่อลูกค้า)'];
            continue;
          }

          $assigned_to = null;
          if ($assignee_name !== '') {
            $stmt = $conn->prepare("SELECT id FROM users WHERE name = ?");
            $stmt->bind_param("s", $assignee_name);
            $stmt->execute();
            $stmt->bind_result($assigned_to_id);
            if ($stmt->fetch()) {
              $assigned_to = $assigned_to_id;
            } else {
              $failed_rows[] = ['row' => $i + 1, 'reason' => "ไม่พบผู้รับงาน: $assignee_name"];
              $stmt->close();
              continue;
            }
            $stmt->close();
          }

          $model_detail = $model_detail ?: '-';
          $status = 'pending';
          $location_info = $customer_name;

          // 3. Truncate data to safe limits
          $product = mb_substr($product, 0, 100);
          $contract_number = mb_substr($contract_number, 0, 50);
          $customer_id_card = mb_substr($customer_id_card, 0, 20);
          $location_info = mb_substr($location_info, 0, 255);
          $location_area = mb_substr($location_area, 0, 255);
          $zone = mb_substr($zone, 0, 50);
          $model = mb_substr($model, 0, 100);
          $model_detail = mb_substr($model_detail, 0, 100);
          $color = mb_substr($color, 0, 20);
          $plate = mb_substr($plate, 0, 20);
          $province = mb_substr($province, 0, 50);
          $os = mb_substr($os, 0, 20);

          $fields = [
            'product',
            'contract_number',
            'customer_id_card',
            'location_info',
            'location_area',
            'zone',
            'due_date',
            'overdue_period',
            'model',
            'model_detail',
            'color',
            'plate',
            'province',
            'os',
            'assigned_to',
            'imported_by',
            'department_id',
            'status'
          ];
          $types = 'ssssssssssssssiiis';
          $values = [
            $product,
            $contract_number,
            $customer_id_card,
            $location_info,
            $location_area,
            $zone,
            $due_date,
            $overdue,
            $model,
            $model_detail,
            $color,
            $plate,
            $province,
            $os,
            $assigned_to,
            $imported_by,
            $department_id,
            $status
          ];

          if ($has_auto_delete_days) {
            $fields[] = 'auto_delete_days';
            $types .= 'i';
            $values[] = $auto_delete_days;
          }
          if ($has_auto_delete_at) {
            $fields[] = 'auto_delete_at';
            $types .= 's';
            $values[] = $auto_delete_at;
          }

          $placeholders = implode(',', array_fill(0, count($fields), '?'));
          $sql = "INSERT INTO jobs (" . implode(',', $fields) . ") VALUES ($placeholders)";
          $stmt = $conn->prepare($sql);

          $bind = [];
          $bind[] = &$types;
          foreach ($values as $k => $v) {
            $bind[] = &$values[$k];
          }
          call_user_func_array([$stmt, 'bind_param'], $bind);

          try {
            $stmt->execute();
            $stmt->close();
          } catch (Exception $e) {
            $failed_rows[] = ['row' => $i + 1, 'reason' => 'Database Error: ' . $e->getMessage()];
            continue;
          }

          $imported_count++;
          $percent = $total_rows ? round(($imported_count / $total_rows) * 100) : 100;
          echo "<script>
            document.getElementById('progressBar').style.width = '{$percent}%';
            document.getElementById('progressText').innerText = 'นำเข้า: {$imported_count}/{$total_rows} ({$percent}%)';
        </script>";
          @ob_flush();
          @flush();

          $rows[$i][] = $imported_by_name;
          $imported_data[] = $rows[$i];
        }
      }
      ?>

      <!-- Results Section -->
      <?php if ($_SERVER["REQUEST_METHOD"] === "POST"): ?>
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Total Rows -->
          <div class="bg-white rounded-lg shadow-sm p-5 border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-gray-600 text-sm font-medium">จำนวนแถวทั้งหมด</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($total_rows) ?></p>
              </div>
              <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-chart-bar text-gray-500 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Success Count -->
          <div class="bg-white rounded-lg shadow-sm p-5 border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-gray-600 text-sm font-medium"><?= $is_delete_mode ? 'ลบสำเร็จ' : 'นำเข้าสำเร็จ' ?></p>
                <p class="text-2xl font-bold text-green-600 mt-1"><?= number_format($imported_count) ?></p>
              </div>
              <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-check-circle text-green-500 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Error Count -->
          <div class="bg-white rounded-lg shadow-sm p-5 border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-gray-600 text-sm font-medium">ผิดพลาด</p>
                <p class="text-2xl font-bold text-red-600 mt-1"><?= number_format(count($failed_rows)) ?></p>
              </div>
              <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-times-circle text-red-500 text-xl"></i>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Success Data Table -->
      <?php if (!empty($imported_data)): ?>
        <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900 flex items-center">
              <span class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-2"><i class="fas fa-check text-green-600 text-sm"></i></span>
              รายการที่นำเข้าสำเร็จ
            </h3>
          </div>

          <div class="overflow-x-auto">
            <div class="max-h-96 overflow-y-auto">
              <table class="min-w-full">
                <thead class="bg-gray-50 sticky top-0 z-10">
                  <tr>
                    <?php
                    $headers = ['Product', 'สัญญา', 'เลขบัตร ปชช.', 'ชื่อลูกค้า', 'พื้นที่', 'โซน', 'Due Date', 'Overdue', 'ยี่ห้อ', 'รุ่น', 'สี', 'ทะเบียน', 'จังหวัด', 'OS', 'ผู้รับงาน', 'ผู้ลงงาน'];
                    foreach ($headers as $h):
                      ?>
                      <th
                        class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                        <?= $h ?>
                      </th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                  <?php
                  $order = [0, 1, 3, 2, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
                  foreach ($imported_data as $index => $row):
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                      <?php foreach ($order as $idx): ?>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 border-b border-gray-100">
                          <?= htmlspecialchars($row[$idx] ?? '') ?>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Error List -->
      <?php if (!empty($failed_rows)): ?>
        <div class="mt-6 bg-white border border-red-200 rounded-lg shadow-sm overflow-hidden">
          <div class="bg-red-50 px-6 py-4 border-b border-red-200">
            <h3 class="text-base font-semibold text-red-900 flex items-center">
              <span class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-2"><i class="fas fa-exclamation-triangle text-red-500"></i></span>
              รายการที่มีข้อผิดพลาด
            </h3>
          </div>

          <div class="p-6">
            <div class="space-y-3">
              <?php foreach ($failed_rows as $fail): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                  <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                      <span class="text-red-700 font-bold text-sm"><?= (int) $fail['row'] ?></span>
                    </div>
                    <div>
                      <p class="text-red-900 font-medium text-sm">แถวที่ <?= (int) $fail['row'] ?></p>
                      <p class="text-red-700 text-sm mt-1"><?= htmlspecialchars($fail['reason']) ?></p>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div> <!-- /container -->
  </main>
</div>

<?php include '../components/footer.php'; ?>

<script>
  // File input handler
  document.getElementById('fileInput').addEventListener('change', function (e) {
    const fileName = e.target.files[0]?.name;
    const fileNameDiv = document.getElementById('fileName');

    if (fileName) {
      fileNameDiv.innerHTML = `
      <div class="flex items-center space-x-2 text-green-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span>ไฟล์ที่เลือก: ${fileName}</span>
      </div>
    `;
      fileNameDiv.classList.remove('hidden');
    } else {
      fileNameDiv.classList.add('hidden');
    }
  });

  // Form submission confirmation
  document.getElementById('importForm')?.addEventListener('submit', function (e) {
    const deleteMode = document.getElementById('delete_mode')?.checked;
    const fileInput = document.getElementById('fileInput');

    // Check if file is selected
    if (!fileInput.files.length) {
      e.preventDefault();
      alert('กรุณาเลือกไฟล์ก่อนดำเนินการ');
      return;
    }

    // Confirm delete mode
    if (deleteMode) {
      if (!confirm('คุณแน่ใจหรือไม่ที่จะลบงานตามเลขสัญญาในไฟล์นี้?\n\nการดำเนินการนี้ไม่สามารถยกเลิกได้!')) {
        e.preventDefault();
        return;
      }
    }

    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = `
    <div class="flex items-center justify-center">
      <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
      กำลังประมวลผล...
    </div>
  `;
    submitBtn.disabled = true;

    // Update progress text
    document.getElementById('progressText').innerText = 'เริ่มต้นการประมวลผล...';
  });

  // Auto-hide alerts after 5 seconds
  setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
      alert.style.transition = 'opacity 0.5s ease-out';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    });
  }, 5000);

  // Add smooth scrolling to results
  if (window.location.hash) {
    document.querySelector(window.location.hash)?.scrollIntoView({
      behavior: 'smooth'
    });
  }

  // Drag and drop functionality
  const dropZone = document.querySelector('[onclick*="fileInput"]');
  const fileInput = document.getElementById('fileInput');

  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  ['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
  });

  function highlight(e) {
    dropZone.classList.add('border-blue-400', 'bg-blue-50');
  }

  function unhighlight(e) {
    dropZone.classList.remove('border-blue-400', 'bg-blue-50');
  }

  dropZone.addEventListener('drop', handleDrop, false);

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;

    if (files.length > 0) {
      fileInput.files = files;
      fileInput.dispatchEvent(new Event('change'));
    }
  }

  // Add tooltips for better UX
  const tooltips = {
    'auto_delete_days': 'ระบบจะลบงานอัตโนมัติหากไม่มีการอัปเดตสถานะภายในระยะเวลาที่กำหนด',
    'delete_mode': 'เปิดใช้งานโหมดนี้เพื่อลบงานที่มีอยู่ในระบบตามเลขสัญญาในไฟล์',
  };

  Object.keys(tooltips).forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      element.title = tooltips[id];
    }
  });
</script>