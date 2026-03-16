<?php
require_once __DIR__ . '/../includes/session_config.php';

// ตรวจสอบว่า user login แล้วหรือยัง
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
  header("Location: ../index.php");
  exit;
}

if (!in_array($_SESSION['user']['role'], ['field', 'admin', 'manager'])) {
  header("Location: ../index.php");
  exit;
}
include '../config/db.php';
require_once '../includes/csrf.php';

$job_id = $_GET['id'] ?? null;
if (!$job_id) {
  die("ไม่พบงาน");
}

// สร้าง query string ย้อนกลับ (ไม่เอา id)
$filters = $_SESSION['job_filters'] ?? [];
$backQuery = http_build_query($filters);

// ถ้าเป็น role field ให้กลับไปที่ field.php
if ($_SESSION['user']['role'] === 'field') {
  $backUrl = "../dashboard/field.php" . ($backQuery ? "?$backQuery" : '');
} else {
  $backUrl = "../admin/jobs.php" . ($backQuery ? "?$backQuery" : '');
}

// ตรวจสอบสิทธิ์ของ role field
$isFieldRole = ($_SESSION['user']['role'] === 'field');

// ดึงข้อมูล job_logs ล่าสุด (เอา record ที่มี id มากสุด = บันทึกล่าสุด)
$stmt = $conn->prepare("SELECT j.*,
    l.note, l.result, l.gps, l.images, l.created_at AS log_time,
    u1.name AS assigned_name, u2.name AS imported_name, u3.name AS returned_by_name
    FROM jobs j
    LEFT JOIN (
        SELECT job_id, note, result, gps, images, created_at
        FROM job_logs
        WHERE job_id = ?
        ORDER BY id DESC
        LIMIT 1
    ) l ON j.id = l.job_id
    LEFT JOIN users u1 ON j.assigned_to = u1.id
    LEFT JOIN users u2 ON j.imported_by = u2.id
    LEFT JOIN users u3 ON j.returned_by = u3.id
    WHERE j.id = ?");
$stmt->bind_param("ii", $job_id, $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if (!$job) {
  die("ไม่พบงาน หรือคุณไม่มีสิทธิ์เข้าถึง");
}

$page_title = "รายละเอียดงาน";
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
            'primary': '#3b82f6',
            'secondary': '#6b7280',
            'surface': '#f5f5f5'
          },
          animation: {
            'fade-in': 'fadeIn 0.4s ease-out',
            'slide-up': 'slideUp 0.4s ease-out'
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0', transform: 'translateY(15px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            },
            slideUp: {
              '0%': { opacity: '0', transform: 'translateY(20px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            }
          }
        }
      }
    }
  </script>
  <style>
    /* Mobile Responsive Styles */
    @media (max-width: 640px) {

      /* Smaller padding for cards */
      .bg-white.rounded-lg {
        padding: 1rem !important;
      }

      /* Smaller text in info boxes */
      .bg-gray-50.p-4 {
        padding: 0.75rem !important;
      }

      /* Better text wrapping */
      .text-base {
        font-size: 0.875rem;
      }

      .text-lg {
        font-size: 1rem;
      }

      /* Grid adjustments */
      .grid-cols-2 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
      }

      /* Image gallery - 2 columns on mobile */
      .grid-cols-4 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (min-width: 640px) and (max-width: 768px) {

      /* Tablet adjustments */
      .grid-cols-4 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    /* Touch-friendly buttons */
    @media (max-width: 640px) {

      button,
      a.inline-flex {
        min-height: 44px;
        padding-top: 0.625rem;
        padding-bottom: 0.625rem;
      }
    }

    /* Prevent text overflow */
    .truncate-mobile {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    @media (max-width: 640px) {

      .font-semibold,
      .font-bold {
        word-break: break-word;
        overflow-wrap: break-word;
      }
    }
  </style>
</head>

<body class="font-thai bg-surface min-h-screen">

  <div class="flex min-h-screen bg-surface">
    <!-- Sidebar - ซ่อนบนมือถือ -->
    <div class="hidden lg:block">
      <?php include '../components/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 lg:ml-64 w-full">
      <!-- Header -->
      <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-40">
        <div class="px-3 sm:px-6 py-3 sm:py-4">
          <div class="flex items-center justify-between gap-2">
            <div class="flex-1 min-w-0">
              <h1 class="text-base sm:text-xl font-semibold text-gray-900 flex items-center truncate">
                <svg class="w-4 h-4 sm:w-6 sm:h-6 mr-1 sm:mr-2 text-gray-600 flex-shrink-0" fill="none"
                  stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="hidden sm:inline">รายละเอียดงาน #<?= $job_id ?></span>
                <span class="sm:hidden">#<?= $job_id ?></span>
              </h1>
              <p class="text-xs text-gray-500 mt-1 hidden sm:block">ข้อมูลงานและผลการดำเนินการ</p>
            </div>
            <div class="flex items-center gap-2">
              <!-- Status Badge -->
              <?php
              $status_colors = [
                'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                'completed' => 'bg-green-100 text-green-800 border-green-200'
              ];
              $status_text = [
                'pending' => 'รอดำเนินการ',
                'completed' => 'เสร็จแล้ว'
              ];
              ?>
              <span
                class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium border <?= $status_colors[$job['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200' ?>">
                <?= $status_text[$job['status']] ?? 'ไม่ระบุ' ?>
              </span>
            </div>
          </div>
        </div>
      </header>

      <!-- Body -->
      <main class="flex-1 p-3 sm:p-6 space-y-4 sm:space-y-6 overflow-y-auto">

        <!-- Action Buttons - จัดปุ่มให้อยู่ทางขวา -->
        <section class="flex flex-wrap justify-between items-center gap-2 sm:gap-3 animate-fade-in">
          <div class="flex flex-wrap gap-2 sm:gap-3">
            <?php if (!$isFieldRole): ?>
              <?php if ($job['status'] === 'completed'): ?>
                <!-- Export Word -->
                <form action="../admin/export_job_detail_word.php" method="post">
                  <input type="hidden" name="job_id" value="<?= htmlspecialchars($job_id) ?>">
                  <input type="hidden" name="team" value="-" />
                  <input type="hidden" name="outstanding" value="-" />
                  <button type="submit"
                    class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm sm:text-base font-medium transition-all duration-200 shadow-sm hover:shadow-md">
                    <svg class="w-4 h-4 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="hidden sm:inline">Export Word</span>
                    <span class="sm:hidden">Export</span>
                  </button>
                </form>
              <?php else: ?>
                <!-- Disabled Export -->
                <button onclick="alertExportDenied()"
                  class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-gray-400 text-white rounded-lg text-sm sm:text-base font-medium cursor-not-allowed">
                  <svg class="w-4 h-4 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <span class="hidden sm:inline">Export Word</span>
                  <span class="sm:hidden">Export</span>
                </button>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <!-- Back Button - ย้ายไปทางขวา -->
          <a href="<?= $backUrl ?>"
            class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm sm:text-base font-medium transition-all duration-200 shadow-sm hover:shadow-md ml-auto">
            <svg class="w-4 h-4 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            กลับ
          </a>
        </section>

        <!-- Contract Information -->
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-slide-up">
          <div class="mb-5">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
              <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              รายละเอียดสัญญา
            </h2>
            <div class="w-12 h-0.5 bg-gray-300 mt-2"></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
              <div class="flex items-center mb-2">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m-9 4v10a2 2 0 002 2h8a2 2 0 002-2V8M7 8h10M7 8V6a2 2 0 012-2h6a2 2 0 012 2v2" />
                </svg>
                <span class="text-sm font-medium text-gray-600">เลขที่สัญญา</span>
              </div>
              <p class="text-base font-semibold text-gray-900"><?= htmlspecialchars($job['contract_number'] ?? '') ?>
              </p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
              <div class="flex items-center mb-2">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span class="text-sm font-medium text-gray-600">Product</span>
              </div>
              <p class="text-base font-semibold text-gray-900"><?= htmlspecialchars($job['product'] ?? '') ?></p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
              <div class="flex items-center mb-2">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="text-sm font-medium text-gray-600">วันครบกำหนด</span>
              </div>
              <p class="text-base font-semibold text-gray-900"><?= htmlspecialchars($job['due_date'] ?? '') ?></p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
              <div class="flex items-center mb-2">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium text-gray-600">ระยะเวลาค้าง</span>
              </div>
              <p class="text-base font-semibold text-gray-900"><?= htmlspecialchars($job['overdue_period'] ?? '-') ?>
              </p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
              <div class="flex items-center mb-2">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
                <span class="text-sm font-medium text-gray-600">ยอดคงเหลือ OS</span>
              </div>
              <p class="text-base font-semibold text-gray-900">
                <?= number_format(floatval(str_replace(',', '', $job['os'])), 2) ?> บาท</p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
              <div class="flex items-center mb-2">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span class="text-sm font-medium text-gray-600">ความเร่งด่วน</span>
              </div>
              <p class="text-lg font-semibold">
                <?php
                if ($job['priority'] === 'urgent') {
                  echo '<span class="text-red-600">🔴 เร่งด่วน</span>';
                } elseif ($job['priority'] === 'high') {
                  echo '<span class="text-orange-600">🟠 ด่วน</span>';
                } else {
                  echo '<span class="text-green-600">🟢 ปกติ</span>';
                }
                ?>
              </p>
            </div>
          </div>

          <?php if (!empty($job['remark'])): ?>
            <div class="mt-5 bg-gray-50 p-4 rounded-lg border border-gray-200">
              <div class="flex items-center mb-2">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                </svg>
                <span class="text-sm font-medium text-gray-600">หมายเหตุ</span>
              </div>
              <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($job['remark'])) ?></p>
            </div>
          <?php endif; ?>
        </section>

        <!-- Customer & Vehicle Information -->
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6 animate-slide-up"
          style="animation-delay: 0.1s;">
          <div class="mb-4 sm:mb-5">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
              <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-gray-600" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              ข้อมูลลูกค้าและยานพาหนะ
            </h2>
            <div class="w-12 h-0.5 bg-gray-300 mt-2"></div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-8">
            <!-- Customer Info -->
            <div class="space-y-3">
              <h3 class="text-base font-semibold text-gray-800 flex items-center">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                ข้อมูลลูกค้า
              </h3>
              <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-2">
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">ชื่อ-สกุล:</span>
                  <span
                    class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['location_info'] ?? '') ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">เลขบัตรประชาชน:</span>
                  <span
                    class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['customer_id_card'] ?? '') ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">ที่อยู่:</span>
                  <span
                    class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['location_area'] ?? '') ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">โซน:</span>
                  <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['zone'] ?? '') ?></span>
                </div>
              </div>
            </div>

            <!-- Vehicle Info -->
            <div class="space-y-3">
              <h3 class="text-base font-semibold text-gray-800 flex items-center">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                ข้อมูลยานพาหนะ
              </h3>
              <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-2">
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">ยี่ห้อ:</span>
                  <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['model'] ?? '') ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">รุ่น:</span>
                  <span
                    class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['model_detail'] ?? '') ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">สี:</span>
                  <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['color'] ?? '') ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">ทะเบียน:</span>
                  <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['plate'] ?? '') ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-gray-600">จังหวัด:</span>
                  <span
                    class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['province'] ?? '') ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Staff Info -->
          <div class="mt-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
            <h3 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
              <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              ข้อมูลเจ้าหน้าที่
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">ผู้นำเข้า:</span>
                <span
                  class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['imported_name'] ?? '-') ?></span>
              </div>
              <div class="flex justify-between">
                <span class="text-sm text-gray-600">ผู้รับผิดชอบ:</span>
                <span
                  class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($job['assigned_name'] ?? '-') ?></span>
              </div>
            </div>
          </div>
        </section>

        <!-- Work Result -->
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-slide-up"
          style="animation-delay: 0.2s;">
          <div class="mb-5">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
              <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
              ผลการดำเนินงาน
            </h2>
            <div class="w-12 h-0.5 bg-gray-300 mt-2"></div>
          </div>

          <div class="space-y-4">
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
              <div class="flex items-center mb-2">
                <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium text-gray-600">ผลการทำงาน</span>
              </div>
              <p class="text-base font-semibold text-gray-900"><?= htmlspecialchars($job['result'] ?? '-') ?></p>
            </div>

            <?php if (!empty($job['note'])): ?>
              <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <div class="flex items-center mb-2">
                  <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                  </svg>
                  <span class="text-sm font-medium text-gray-600">หมายเหตุผลการทำงาน</span>
                </div>
                <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($job['note'])) ?></p>
              </div>
            <?php endif; ?>

            <?php if ($job['log_time']): ?>
              <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <div class="flex items-center mb-2">
                  <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span class="text-sm font-medium text-gray-600">วันที่บันทึก</span>
                </div>
                <p class="text-base font-semibold text-gray-900"><?= date('d/m/Y H:i น.', strtotime($job['log_time'])) ?>
                </p>
              </div>
            <?php endif; ?>

            <?php if (!empty($job['gps'])):
              $gps = explode(",", $job['gps']);
              $lat = trim($gps[0]);
              $lng = trim($gps[1]);
              ?>
              <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <div class="flex items-center justify-between mb-2">
                  <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-sm font-medium text-gray-600">พิกัด GPS</span>
                  </div>
                  <a href="https://www.google.com/maps/search/?api=1&query=<?= $lat ?>,<?= $lng ?>" target="_blank"
                    class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-md text-xs font-medium transition-colors duration-200">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    เปิด Google Maps
                  </a>
                </div>
                <p class="text-sm font-mono text-gray-700"><?= htmlspecialchars($job['gps']) ?></p>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- Images Gallery -->
        <?php if (!empty($job['images'])): ?>
          <?php $images = json_decode($job['images'], true); ?>
          <?php if (is_array($images) && count($images) > 0): ?>
            <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6 animate-slide-up"
              style="animation-delay: 0.3s;">
              <div class="mb-4 sm:mb-5">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 flex items-center">
                  <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-gray-600" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  รูปภาพประกอบ
                  <span class="ml-2 text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded-full"><?= count($images) ?>
                    รูป</span>
                </h2>
                <div class="w-12 h-0.5 bg-gray-300 mt-2"></div>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3 mb-4 sm:mb-5">
                <?php
                require_once __DIR__ . '/../includes/image_optimizer.php';
                foreach ($images as $index => $img):
                  $paths = ImageOptimizer::getImagePaths($img);
                  ?>
                  <div
                    class="group relative bg-gray-100 rounded-lg overflow-hidden hover:shadow-md transition-all duration-200">
                    <!-- ทุก Role สามารถคลิกดูภาพได้ -->
                    <button type="button"
                      onclick="showImageModal('../uploads/job_photos/<?= htmlspecialchars($paths['original']) ?>')"
                      class="block aspect-[3/4] w-full text-left">
                      <img src="../uploads/job_photos/<?= htmlspecialchars($paths['thumb']) ?>"
                        alt="รูปภาพงาน <?= $index + 1 ?>"
                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                        loading="lazy">

                      <!-- Overlay -->
                      <div
                        class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-300 flex items-center justify-center">
                        <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                          fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                      </div>
                    </button>

                    <!-- Image Number -->
                    <div class="absolute top-2 left-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded-full">
                      <?= $index + 1 ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Download All Button - ซ่อนสำหรับ Field Role -->
              <?php if (!$isFieldRole): ?>
                <div class="flex justify-center">
                  <a href="../download_images.php?job_id=<?= $job_id ?>"
                    class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-all duration-200 shadow-sm hover:shadow-md">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    ดาวน์โหลดรูปทั้งหมด
                  </a>
                </div>
              <?php else: ?>
                <div class="flex justify-center">
                  <div
                    class="inline-flex items-center px-5 py-2.5 bg-gray-400 text-white rounded-lg font-medium cursor-not-allowed">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    ไม่สามารถดาวน์โหลดได้
                  </div>
                </div>
              <?php endif; ?>
            </section>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Return Job Section (Admin/Manager Only) -->
        <?php if (!$isFieldRole && $job['status'] === 'completed'): ?>
          <section class="bg-white rounded-2xl shadow-lg border border-red-200 p-6 animate-slide-up">
            <div class="mb-4">
              <h2 class="text-xl font-semibold text-red-700 flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                ตีงานกลับเพื่อแก้ไข
              </h2>
              <div class="w-16 h-1 bg-red-500 rounded-full mt-2"></div>
              <p class="text-sm text-gray-600 mt-2">ใช้เมื่อพบว่างานที่ส่งมาไม่ถูกต้องหรือต้องการให้แก้ไข</p>
            </div>

            <div class="bg-red-50 p-4 rounded-xl mb-4">
              <p class="text-sm text-red-800 font-medium">
                ⚠️ งานที่ตีกลับจะถูกส่งกลับไปยังภาคสนามเพื่อแก้ไขและส่งใหม่
              </p>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">เหตุผลในการตีกลับ *</label>
              <textarea id="returnReason" rows="4"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent"
                placeholder="กรุณาระบุเหตุผลที่ต้องการให้แก้ไข เช่น รูปภาพไม่ชัด, ข้อมูลไม่ครบ, GPS ไม่ถูกต้อง"></textarea>
            </div>

            <button onclick="returnJob()"
              class="w-full bg-red-600 hover:bg-red-700 text-white py-3 px-6 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
              ตีงานกลับเพื่อแก้ไข
            </button>
          </section>
        <?php endif; ?>

        <!-- Return Reason (งานถูกตีกลับ) -->
        <?php if ($job['status'] === 'returned' && !empty($job['return_reason'])): ?>
          <section class="bg-white rounded-2xl shadow-lg border border-red-200 p-6 animate-slide-up">
            <div class="mb-4">
              <h2 class="text-xl font-semibold text-red-700 flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                งานถูกตีกลับเพื่อแก้ไข
              </h2>
              <div class="w-16 h-1 bg-red-500 rounded-full mt-2"></div>
            </div>

            <div class="bg-red-50 p-4 rounded-xl">
              <div class="space-y-3">
                <div>
                  <p class="text-sm font-semibold text-red-800 mb-1">เหตุผลในการตีกลับ:</p>
                  <p class="text-red-700"><?= nl2br(htmlspecialchars($job['return_reason'])) ?></p>
                </div>
                <?php if (!empty($job['returned_by_name'])): ?>
                  <div class="border-t border-red-200 pt-2">
                    <p class="text-sm text-red-600"><strong>ตีกลับโดย:</strong>
                      <?= htmlspecialchars($job['returned_by_name']) ?></p>
                  </div>
                <?php endif; ?>
                <?php if (!empty($job['returned_at'])): ?>
                  <div>
                    <p class="text-sm text-red-600"><strong>วันที่ตีกลับ:</strong>
                      <?= date('d/m/Y H:i น.', strtotime($job['returned_at'])) ?></p>
                  </div>
                <?php endif; ?>
                <?php if (!empty($job['revision_count']) && $job['revision_count'] > 0): ?>
                  <div>
                    <p class="text-sm text-red-600"><strong>จำนวนครั้งที่ตีกลับ:</strong> <?= $job['revision_count'] ?>
                      ครั้ง</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </section>
        <?php endif; ?>

        <!-- Summary Card -->
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-slide-up"
          style="animation-delay: 0.4s;">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-1">สรุปข้อมูลงาน</h3>
              <p class="text-sm text-gray-600">งาน #<?= $job_id ?> - <?= htmlspecialchars($job['contract_number']) ?>
              </p>
            </div>
            <div class="text-right">
              <div class="text-3xl mb-1">
                <?php if ($job['status'] === 'completed'): ?>
                  ✅
                <?php else: ?>
                  ⏳
                <?php endif; ?>
              </div>
              <p class="text-sm text-gray-600 font-medium">
                <?= $job['status'] === 'completed' ? 'งานเสร็จสิ้น' : 'รอดำเนินการ' ?>
              </p>
            </div>
          </div>
        </section>
      </main>
    </div>
  </div>

  <?php include '../components/footer.php'; ?>

  <script>
    const jobId = <?= $job_id ?>;
    const userRole = '<?= $_SESSION['user']['role'] ?>';
    const csrfToken = '<?= getCsrfToken() ?>';

    function alertExportDenied() {
      Swal.fire({
        icon: 'warning',
        title: 'ยังไม่สามารถ Export ได้',
        text: 'อนุญาตเฉพาะงานที่ "เสร็จแล้ว" เท่านั้น',
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#3b82f6'
      });
    }

    // Return job (Admin/Manager only)
    async function returnJob() {
      const returnReason = document.getElementById('returnReason')?.value.trim();

      if (!returnReason) {
        Swal.fire({
          icon: 'warning',
          title: 'กรุณาระบุเหตุผล',
          text: 'ต้องระบุเหตุผลในการตีงานกลับ'
        });
        return;
      }

      const result = await Swal.fire({
        title: 'ยืนยันการตีงานกลับ',
        html: `คุณต้องการตีงานนี้กลับเพื่อแก้ไขใช่หรือไม่?<br><br><strong>เหตุผล:</strong><br>${returnReason}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280'
      });

      if (!result.isConfirmed) return;

      try {
        const response = await fetch('../admin/api/return_job.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({
            job_id: jobId,
            return_reason: returnReason
          })
        });

        const data = await response.json();

        if (data.success) {
          await Swal.fire({
            icon: 'success',
            title: 'ตีงานกลับสำเร็จ',
            text: 'งานถูกส่งกลับไปยังภาคสนามเพื่อแก้ไขแล้ว'
          });

          // Reload page to update status
          window.location.reload();
        } else {
          throw new Error(data.message || 'ไม่สามารถตีงานกลับได้');
        }
      } catch (error) {
        console.error('Error returning job:', error);
        Swal.fire({
          icon: 'error',
          title: 'เกิดข้อผิดพลาด',
          text: error.message || 'ไม่สามารถตีงานกลับได้'
        });
      }
    }

    // Image Modal/Lightbox - แก้ไขให้ปุ่มปิดทำงานได้
    function showImageModal(imageSrc) {
      // สร้าง modal
      const modal = document.createElement('div');
      modal.id = 'imageModal';
      modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90 p-4 cursor-pointer';

      // คลิกพื้นหลังเพื่อปิด
      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          closeImageModal();
        }
      });

      modal.innerHTML = `
    <div class="relative max-w-7xl max-h-full">
      <!-- ปุ่มปิด X - แก้ไขให้ใช้งานได้ -->
      <button type="button" id="closeModal"
              class="absolute -top-12 right-0 text-white hover:text-red-400 transition-all duration-200 z-10 bg-red-600 hover:bg-red-700 rounded-full p-2 shadow-lg"
              title="ปิด (ESC)">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
      <img src="${imageSrc}"
           alt="Full image"
           class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl cursor-default"
           onclick="event.stopPropagation()">
      <a href="${imageSrc}"
         target="_blank"
         class="absolute -bottom-12 left-0 text-white hover:text-blue-400 flex items-center gap-2 transition-colors"
         onclick="event.stopPropagation()">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
        เปิดในแท็บใหม่
      </a>
    </div>
  `;

      document.body.appendChild(modal);
      document.body.style.overflow = 'hidden';

      // เพิ่ม event listener สำหรับปุ่มปิด
      setTimeout(() => {
        const closeBtn = document.getElementById('closeModal');
        if (closeBtn) {
          closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            closeImageModal();
          });
        }
      }, 100);

      // ESC key to close
      document.addEventListener('keydown', handleEscKey);
    }

    function closeImageModal() {
      const modal = document.getElementById('imageModal');
      if (modal) {
        modal.remove();
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleEscKey);
      }
    }

    function handleEscKey(e) {
      if (e.key === 'Escape') {
        closeImageModal();
      }
    }

    // Add image lazy loading and error handling
    document.addEventListener('DOMContentLoaded', function () {
      // Image error handling
      const images = document.querySelectorAll('img[src*="job_photos"]');
      images.forEach(img => {
        img.addEventListener('error', function () {
          this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTQgMTZMOC41ODYgMTEuNDE0QTIgMiAwIDAgMSAxMS40MTQgMTEuNDE0TDE2IDE2TTEyIDhIMTIuMDFNNiAyMEgxOEEyIDIgMCAwIDAgMjAgMThWNkEyIDIgMCAwIDAgMTggNEg2QTIgMiAwIDAgMCA0IDZWMThBMiAyIDAgMCAwIDYgMjBaIiBzdHJva2U9IiM5Q0E3QUYiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIi8+Cjwvc3ZnPgo=';
          this.parentElement.classList.add('bg-gray-200');
        });
      });
    });
  </script>

</body>

</html>