<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'manager'])) {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';
require_once '../includes/csrf.php';

$id = $_GET['id'] ?? null;
if (!$id) { echo "ไม่พบงาน"; exit; }

$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
if (!$job) { echo "ไม่พบข้อมูลงาน"; exit; }

$users = $conn->query("SELECT id, name FROM users WHERE role = 'field'");
$updated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $fields = [
        'contract_number' => 'เลขสัญญา',
        'customer_id_card' => 'บัตรประชาชน',
        'product' => 'ผลิตภัณฑ์',
        'location_info' => 'ข้อมูลพื้นที่',
        'zone' => 'โซน',
        'due_date' => 'ครบกำหนด',
        'overdue_period' => 'ระยะเวลาค้าง',
        'model' => 'รุ่น',
        'color' => 'สี',
        'plate' => 'ทะเบียน',
        'province' => 'จังหวัด',
        'os' => 'ยอดคงเหลือ OS',
        'assigned_to' => 'ผู้รับงาน',
        'status' => 'สถานะ',
        'priority' => 'ความเร่งด่วน',
        'remark' => 'หมายเหตุ'
    ];

    $changes = [];

    foreach ($fields as $key => $label) {
        $newValue = $_POST[$key] ?? '';
        $oldValue = $job[$key] ?? '';

        if ($key === 'assigned_to') {
            // แก้ไข SQL Injection - ใช้ Prepared Statement
            $oldUser = '-';
            if (!empty($oldValue)) {
                $stmt_old = $conn->prepare("SELECT name FROM users WHERE id = ?");
                $stmt_old->bind_param("i", $oldValue);
                $stmt_old->execute();
                $result_old = $stmt_old->get_result();
                if ($row_old = $result_old->fetch_assoc()) {
                    $oldUser = $row_old['name'];
                }
                $stmt_old->close();
            }

            $newUser = '-';
            if (!empty($newValue)) {
                $stmt_new = $conn->prepare("SELECT name FROM users WHERE id = ?");
                $stmt_new->bind_param("i", $newValue);
                $stmt_new->execute();
                $result_new = $stmt_new->get_result();
                if ($row_new = $result_new->fetch_assoc()) {
                    $newUser = $row_new['name'];
                }
                $stmt_new->close();
            }

            if ($oldValue != $newValue) {
                $changes[] = "$label: $oldUser → $newUser";
            }
        } else {
            if ($oldValue != $newValue) {
                $changes[] = "$label: $oldValue → $newValue";
            }
        }

        $$key = $newValue;
    }

    if (count($changes)) {
        $editor_id = $_SESSION['user']['id'];

        $stmt = $conn->prepare("UPDATE jobs SET 
            contract_number=?, customer_id_card=?, product=?, location_info=?, zone=?, due_date=?, 
            overdue_period=?, model=?, color=?, plate=?, province=?, os=?, 
            assigned_to=?, status=?, priority=?, remark=?, last_updated_by=? WHERE id=?");

        $stmt->bind_param("sssssssssssssssiii", 
            $contract_number, $customer_id_card, $product, $location_info, $zone, $due_date,
            $overdue_period, $model, $color, $plate, $province, $os,
            $assigned_to, $status, $priority, $remark, $editor_id, $id);

        $stmt->execute();

        $summary = "รายการแก้ไข: " . implode(', ', $changes);
        $log_stmt = $conn->prepare("INSERT INTO job_edit_logs (job_id, edited_by, change_summary) VALUES (?, ?, ?)");
        $log_stmt->bind_param("iis", $id, $editor_id, $summary);
        $log_stmt->execute();

        $updated = true;
    }
}

$page_title = "แก้ไขข้อมูลงาน";
include '../components/header.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
              <svg class="w-7 h-7 mr-3 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
              แก้ไขข้อมูลงาน
            </h1>
            <p class="text-sm text-gray-500 mt-1">แก้ไขและอัปเดตข้อมูลการทำงาน</p>
          </div>
          <div class="flex items-center gap-4">
            <a href="jobs.php" class="inline-flex items-center px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-xl font-medium transition-all duration-300 shadow-lg hover:shadow-xl">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
              กลับรายการงาน
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- Body -->
    <main class="flex-1 p-6 space-y-6 overflow-y-auto">
      
      <!-- Job Info Card -->
      <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-fade-in">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
              <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              ข้อมูลงาน ID: #<?= $job['id'] ?>
            </h2>
            <p class="text-sm text-gray-500 mt-1">กรอกข้อมูลที่ต้องการแก้ไข</p>
          </div>
          <div class="flex items-center gap-3">
            <?php 
            $priority_colors = [
              'urgent' => 'bg-red-100 text-red-800',
              'high' => 'bg-orange-100 text-orange-800',
              'normal' => 'bg-green-100 text-green-800'
            ];
            $priority_text = [
              'urgent' => 'ด่วนที่สุด',
              'high' => 'ด่วน',
              'normal' => 'ปกติ'
            ];
            ?>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= $priority_colors[$job['priority']] ?? 'bg-gray-100 text-gray-800' ?>">
              <?= $priority_text[$job['priority']] ?? 'ไม่ระบุ' ?>
            </span>
            
            <?php 
            $status_colors = [
              'pending' => 'bg-yellow-100 text-yellow-800',
              'completed' => 'bg-green-100 text-green-800'
            ];
            $status_text = [
              'pending' => 'รอดำเนินการ',
              'completed' => 'เสร็จแล้ว'
            ];
            ?>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= $status_colors[$job['status']] ?? 'bg-gray-100 text-gray-800' ?>">
              <?= $status_text[$job['status']] ?? 'ไม่ระบุ' ?>
            </span>
          </div>
        </div>

        <!-- Edit Form -->
        <form method="post" class="space-y-6" id="editJobForm">
          <?= csrfField() ?>

          <!-- Contract & Customer Section -->
          <div class="bg-gray-50 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              ข้อมูลสัญญาและลูกค้า
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">เลขสัญญา *</label>
                <input type="text" 
                       name="contract_number" 
                       value="<?= htmlspecialchars($job['contract_number']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300" 
                       placeholder="กรุณาใส่เลขสัญญา" 
                       required>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">เลขบัตรประชาชน</label>
                <input type="text" 
                       name="customer_id_card" 
                       value="<?= htmlspecialchars($job['customer_id_card'] ?? '') ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300" 
                       placeholder="เลขบัตรประชาชนลูกค้า">
              </div>
            </div>
          </div>

          <!-- Product & Vehicle Section -->
          <div class="bg-gray-50 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
              </svg>
              ข้อมูลผลิตภัณฑ์และยานพาหนะ
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">ผลิตภัณฑ์</label>
                <input type="text" 
                       name="product" 
                       value="<?= htmlspecialchars($job['product']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300" 
                       placeholder="ชื่อผลิตภัณฑ์">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">รุ่น</label>
                <input type="text" 
                       name="model" 
                       value="<?= htmlspecialchars($job['model']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300" 
                       placeholder="รุ่นของผลิตภัณฑ์">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">สี</label>
                <input type="text" 
                       name="color" 
                       value="<?= htmlspecialchars($job['color']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300" 
                       placeholder="สีของยานพาหนะ">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">ทะเบียน</label>
                <input type="text" 
                       name="plate" 
                       value="<?= htmlspecialchars($job['plate']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300" 
                       placeholder="หมายเลขทะเบียน">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">จังหวัด</label>
                <input type="text" 
                       name="province" 
                       value="<?= htmlspecialchars($job['province']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300" 
                       placeholder="จังหวัดจดทะเบียน">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">ยอดคงเหลือ OS</label>
                <input type="text" 
                       name="os" 
                       value="<?= htmlspecialchars($job['os']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300" 
                       placeholder="ยอดคงเหลือ">
              </div>
            </div>
          </div>

          <!-- Location & Schedule Section -->
          <div class="bg-gray-50 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              ข้อมูลที่ตั้งและกำหนดเวลา
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">ข้อมูลพื้นที่</label>
                <input type="text" 
                       name="location_info" 
                       value="<?= htmlspecialchars($job['location_info']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300" 
                       placeholder="รายละเอียดที่ตั้ง">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">โซน</label>
                <input type="text" 
                       name="zone" 
                       value="<?= htmlspecialchars($job['zone']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300" 
                       placeholder="โซนพื้นที่">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">วันครบกำหนด</label>
                <input type="date" 
                       name="due_date" 
                       value="<?= htmlspecialchars($job['due_date']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">ระยะเวลาค้าง</label>
                <input type="text" 
                       name="overdue_period" 
                       value="<?= htmlspecialchars($job['overdue_period']) ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300" 
                       placeholder="จำนวนวันที่ค้าง">
              </div>
            </div>
          </div>

          <!-- Assignment & Priority Section -->
          <div class="bg-gray-50 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
              </svg>
              การมอบหมายงานและสถานะ
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">ผู้รับงาน</label>
                <select name="assigned_to" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-300">
                  <option value="">เลือกผู้รับงาน</option>
                  <?php 
                  $users = $conn->query("SELECT id, name FROM users WHERE role = 'field'"); 
                  while ($u = $users->fetch_assoc()): 
                  ?>
                    <option value="<?= $u['id'] ?>" <?= $u['id'] == $job['assigned_to'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($u['name']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">ความเร่งด่วน</label>
                <select name="priority" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-300">
                  <option value="urgent" <?= $job['priority'] === 'urgent' ? 'selected' : '' ?>>งานด่วนที่สุด</option>
                  <option value="high" <?= $job['priority'] === 'high' ? 'selected' : '' ?>>งานด่วน</option>
                  <option value="normal" <?= $job['priority'] === 'normal' ? 'selected' : '' ?>>งานปกติ</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">สถานะงาน</label>
                <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-300">
                  <option value="pending" <?= ($job['status'] ?? '') === 'pending' ? 'selected' : '' ?>>ยังไม่เสร็จ</option>
                  <option value="completed" <?= ($job['status'] ?? '') === 'completed' ? 'selected' : '' ?>>เสร็จแล้ว</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Remarks Section -->
          <div class="bg-gray-50 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
              </svg>
              หมายเหตุเพิ่มเติม
            </h3>
            <textarea name="remark" 
                      rows="4" 
                      class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent transition-all duration-300" 
                      placeholder="เพิ่มหมายเหตุหรือรายละเอียดเพิ่มเติม..."><?= htmlspecialchars($job['remark'] ?? '') ?></textarea>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end space-x-4 pt-6">
            <a href="jobs.php" class="inline-flex items-center px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              ยกเลิก
            </a>
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              บันทึกการแก้ไข
            </button>
          </div>
        </form>
      </section>
    </main>
  </div>
</div>

<?php include '../components/footer.php'; ?>

<?php if ($updated): ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'บันทึกสำเร็จ!',
    text: 'ข้อมูลงานได้รับการอัปเดตเรียบร้อยแล้ว',
    confirmButtonText: 'ตกลง',
    confirmButtonColor: '#059669',
    timer: 3000,
    timerProgressBar: true,
    showClass: {
      popup: 'animate__animated animate__fadeInDown'
    },
    hideClass: {
      popup: 'animate__animated animate__fadeOutUp'
    }
  }).then(() => {
    window.location.href = 'jobs.php';
  });
</script>
<?php endif; ?>

<script>
// Add form validation
document.getElementById('editJobForm').addEventListener('submit', function(e) {
  const contractNumber = document.querySelector('input[name="contract_number"]').value;
  
  if (!contractNumber.trim()) {
    e.preventDefault();
    Swal.fire({
      icon: 'warning',
      title: 'กรุณาระบุข้อมูล',
      text: 'เลขสัญญาเป็นข้อมูลที่จำเป็นต้องกรอก',
      confirmButtonText: 'ตกลง',
      confirmButtonColor: '#f59e0b'
    });
    return;
  }

  // Show loading
  Swal.fire({
    title: 'กำลังบันทึกข้อมูล...',
    text: 'กรุณารอสักครู่',
    allowOutsideClick: false,
    showConfirmButton: false,
    willOpen: () => {
      Swal.showLoading();
    }
  });
});

// Add input animations
document.querySelectorAll('input, select, textarea').forEach(element => {
  element.addEventListener('focus', function() {
    this.parentElement.style.transform = 'translateY(-1px)';
    this.parentElement.style.transition = 'transform 0.2s ease';
  });
  
  element.addEventListener('blur', function() {
    this.parentElement.style.transform = 'translateY(0)';
  });
});

// Auto-resize textarea
document.querySelector('textarea[name="remark"]').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = (this.scrollHeight) + 'px';
});
</script>

</body>
</html>