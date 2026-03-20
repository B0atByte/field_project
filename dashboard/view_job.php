<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'field') {
  header("Location: ../index.php");
  exit;
}
include '../config/db.php';
require_once '../includes/csrf.php';

$job_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user']['id'];

$stmt = $conn->prepare("SELECT j.*, u.name AS imported_name, u2.name AS returned_by_name FROM jobs j LEFT JOIN users u ON j.imported_by = u.id LEFT JOIN users u2 ON j.returned_by = u2.id WHERE j.id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
  die("ไม่พบงาน");
}

$can_submit = $job['assigned_to'] == $user_id;
$date_now = date("Y-m-d\TH:i");
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>รายละเอียดงาน | ระบบจัดการงาน</title>
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes, maximum-scale=5.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      -webkit-tap-highlight-color: transparent;
    }

    body {
      font-family: 'Sarabun', sans-serif;
      background: #ffffff;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Card Styling */
    .card {
      background: #ffffff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }

    .card:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    /* Form Styling */
    .form-input {
      border: 2px solid #e5e7eb;
      background: #f9fafb;
      border-radius: 12px;
      transition: all 0.2s ease;
      padding: 12px 16px;
      font-weight: 500;
      width: 100%;
    }

    .form-input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
      outline: none;
      background: #ffffff;
    }

    .form-input:read-only {
      background: #f3f4f6;
      cursor: not-allowed;
    }

    /* Button Styling */
    .btn {
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      justify-content: center;
      border: none;
      cursor: pointer;
      font-size: 14px;
    }

    .btn:active {
      transform: scale(0.98);
    }

    .btn-primary {
      background: #3b82f6;
      color: white;
    }

    .btn-primary:hover {
      background: #2563eb;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-success {
      background: #10b981;
      color: white;
    }

    .btn-success:hover {
      background: #059669;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn-danger {
      background: #ef4444;
      color: white;
    }

    .btn-danger:hover {
      background: #dc2626;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .btn-warning {
      background: #f59e0b;
      color: white;
    }

    .btn-purple {
      background: #8b5cf6;
      color: white;
    }

    .btn-purple:hover {
      background: #7c3aed;
    }

    .btn-secondary {
      background: #6b7280;
      color: white;
    }

    .btn-secondary:hover {
      background: #4b5563;
    }

    /* Status Badges */
    .status-badge {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .priority-urgent {
      background: #fef2f2;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    .priority-high {
      background: #fff7ed;
      color: #ea580c;
      border: 1px solid #fed7aa;
    }

    .priority-normal {
      background: #f0fdf4;
      color: #16a34a;
      border: 1px solid #bbf7d0;
    }

    /* Info Box */
    .info-box {
      padding: 16px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      transition: all 0.2s ease;
    }

    .info-box:hover {
      background: #f3f4f6;
    }

    .info-box-highlight {
      background: #eff6ff;
      border-color: #bfdbfe;
    }

    /* Copy Button */
    .copy-btn {
      background: #eff6ff;
      color: #2563eb;
      border: 1px solid #bfdbfe;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.2s ease;
      cursor: pointer;
      white-space: nowrap;
    }

    .copy-btn:hover {
      background: #dbeafe;
      transform: scale(1.05);
    }

    /* Map Styling */
    #map {
      width: 100%;
      height: 400px;
      border-radius: 16px;
      border: 2px solid #e5e7eb;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    /* File Upload */
    .file-upload {
      border: 2px dashed #d1d5db;
      border-radius: 16px;
      padding: 32px 20px;
      text-align: center;
      transition: all 0.3s ease;
      background: #f9fafb;
      cursor: pointer;
    }

    .file-upload:hover {
      border-color: #3b82f6;
      background: #eff6ff;
    }

    .file-upload.dragover {
      border-color: #2563eb;
      background: #dbeafe;
      transform: scale(1.02);
    }

    /* Image Preview */
    .image-preview-container {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      border: 2px solid #e5e7eb;
      transition: all 0.2s ease;
    }

    .image-preview-container:hover {
      transform: scale(1.02);
      border-color: #3b82f6;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    .image-preview {
      width: 100%;
      height: 120px;
      object-fit: cover;
    }

    .remove-image-btn {
      position: absolute;
      top: 8px;
      right: 8px;
      background: #ef4444;
      color: white;
      border-radius: 50%;
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      cursor: pointer;
      transition: all 0.2s ease;
      border: 2px solid white;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .remove-image-btn:hover {
      background: #dc2626;
      transform: scale(1.1);
    }

    /* Progress Ring */
    .progress-ring {
      transform: rotate(-90deg);
    }

    .progress-ring__circle {
      stroke: #e5e7eb;
      stroke-linecap: round;
      stroke-width: 4;
      fill: transparent;
      r: 20;
      cx: 24;
      cy: 24;
    }

    .progress-ring__circle--progress {
      stroke: #3b82f6;
      stroke-dasharray: 125.6;
      stroke-dashoffset: 125.6;
      transition: stroke-dashoffset 0.3s ease;
    }

    /* Validation */
    .required::after {
      content: " *";
      color: #ef4444;
      font-weight: bold;
    }

    .validation-message {
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 13px;
      margin-top: 8px;
      font-weight: 500;
    }

    .validation-success {
      background: #ecfdf5;
      color: #059669;
      border: 1px solid #d1fae5;
    }

    .validation-error {
      background: #fee2e2;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    /* Radio Button Styling */
    .radio-card {
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      padding: 16px;
      cursor: pointer;
      transition: all 0.2s ease;
      background: #ffffff;
    }

    .radio-card:hover {
      border-color: #d1d5db;
      background: #f9fafb;
    }

    .radio-card input:checked~* {
      border-color: #3b82f6;
    }

    input[type="radio"]:checked~.radio-card {
      border-color: #3b82f6;
      background: #eff6ff;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
      .card {
        padding: 16px !important;
      }

      .btn {
        padding: 10px 16px;
        font-size: 13px;
      }

      #map {
        height: 300px;
      }

      .image-preview {
        height: 100px;
      }

      .copy-btn {
        padding: 4px 10px;
        font-size: 11px;
      }
    }

    /* Loading State */
    .loading {
      opacity: 0.7;
      pointer-events: none;
      position: relative;
    }

    .loading::after {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      width: 20px;
      height: 20px;
      margin: -10px 0 0 -10px;
      border: 2px solid #3b82f6;
      border-radius: 50%;
      border-top-color: transparent;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    /* Animations */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .fade-in {
      animation: fadeIn 0.3s ease-out;
    }

    /* Sticky Header */
    .sticky-header {
      position: sticky;
      top: 0;
      z-index: 40;
      background: #ffffff;
      border-bottom: 1px solid #e5e7eb;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
  </style>
</head>

<body class="min-h-screen bg-white">

  <div class="max-w-6xl mx-auto p-3 sm:p-4 space-y-4 sm:space-y-6 pb-8">

    <!-- Header -->
    <div class="sticky-header -mx-3 sm:-mx-4 px-3 sm:px-4 py-3 sm:py-4 fade-in">
      <div class="flex items-center justify-between gap-3">
        <a href="field.php" class="btn btn-secondary">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
              d="M7.707 14.707a1 1 0 01-1.414 0L2.586 11l3.707-3.707a1 1 0 011.414 1.414L5.414 10H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z"
              clip-rule="evenodd" />
          </svg>
          <span class="hidden sm:inline">กลับ</span>
        </a>
        <h1 class="text-lg sm:text-xl font-bold text-gray-900 truncate flex-1 text-center">
          รายละเอียดงาน
        </h1>
        <div class="text-xs sm:text-sm text-gray-500 font-semibold whitespace-nowrap">
          #<?= $job['id'] ?>
        </div>
      </div>
    </div>

    <!-- Job Info -->
    <div class="card p-4 sm:p-6 fade-in">
      <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
          </path>
        </svg>
        ข้อมูลงาน
      </h3>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="space-y-3">
          <div class="info-box info-box-highlight">
            <div class="flex flex-col gap-2">
              <div class="flex items-center justify-between gap-2">
                <div class="flex-1 min-w-0">
                  <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">เลขที่สัญญา</label>
                  <p class="font-bold text-base sm:text-lg text-gray-900 truncate">
                    <?= htmlspecialchars($job['contract_number'] ?? '') ?>
                  </p>
                </div>
                <button type="button" onclick="copyText('<?= addslashes($job['contract_number'] ?? '') ?>')"
                  class="copy-btn flex-shrink-0">
                  <i class="fas fa-clipboard mr-1"></i>คัดลอก
                </button>
              </div>
              <button type="button" onclick="showJobHistory()" id="historyBtn"
                class="btn btn-primary text-xs w-full py-2">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                ดูประวัติงานเดียวกัน
              </button>
            </div>
          </div>
          <div class="info-box">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">Product</label>
            <p class="font-semibold text-gray-900"><?= htmlspecialchars($job['product'] ?? '') ?></p>
          </div>
          <div class="info-box">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">วันครบกำหนด</label>
            <p class="font-semibold text-red-600"><?= htmlspecialchars($job['due_date'] ?? '') ?></p>
          </div>
        </div>
        <div class="space-y-3">
          <div class="info-box">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">จำนวนวันครบกำหนด</label>
            <p class="font-semibold text-gray-900"><?= htmlspecialchars($job['overdue_period'] ?? '-') ?> วัน</p>
          </div>
          <div class="info-box info-box-highlight">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1"><i class="fas fa-coins mr-1"></i>ยอดคงเหลือ OS</label>
            <p class="font-bold text-lg text-red-600">
              <?php
              $os_value = $job['os'] ?? '0';
              // ลบเครื่องหมาย comma ออกก่อนแปลงเป็นตัวเลข
              $os_clean = str_replace(',', '', $os_value);
              echo number_format(floatval($os_clean), 2);
              ?> บาท
            </p>
          </div>
          <div class="info-box">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">ผู้บันทึกข้อมูล</label>
            <p class="font-semibold text-gray-900"><?= htmlspecialchars($job['imported_name'] ?? '-') ?></p>
          </div>
          <div class="info-box">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">ลำดับความเร่งด่วน</label>
            <div class="mt-2">
              <?php
              $priority = $job['priority'];
              if ($priority === 'urgent')
                echo '<span class="status-badge priority-urgent">ด่วนที่สุด</span>';
              elseif ($priority === 'high')
                echo '<span class="status-badge priority-high">ด่วน</span>';
              else
                echo '<span class="status-badge priority-normal">ปกติ</span>';
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Customer & Vehicle Info -->
    <div class="card p-4 sm:p-6 fade-in">
      <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
        </svg>
        ข้อมูลลูกค้าและยานพาหนะ
      </h3>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="space-y-3">
          <div class="info-box info-box-highlight">
            <div class="flex items-center justify-between gap-2">
              <div class="flex-1 min-w-0">
                <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">ชื่อ-สกุล ลูกค้า</label>
                <p class="font-bold text-base sm:text-lg text-gray-900 break-words">
                  <?= htmlspecialchars($job['location_info'] ?? '') ?>
                </p>
              </div>
              <button type="button" onclick="copyText('<?= addslashes($job['location_info'] ?? '') ?>')"
                class="copy-btn flex-shrink-0">
                <i class="fas fa-clipboard"></i>
              </button>
            </div>
          </div>
          <div class="info-box">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">เลขบัตรประชาชน</label>
            <p class="font-mono text-sm"><?= htmlspecialchars($job['customer_id_card'] ?? '-') ?></p>
          </div>
          <div class="info-box">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">ข้อมูลพื้นที่</label>
            <p class="text-sm text-gray-700 break-words"><?= htmlspecialchars($job['location_area'] ?? '') ?></p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="info-box">
              <label class="text-xs text-gray-600 font-medium block mb-1">โซน</label>
              <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($job['zone'] ?? '') ?></p>
            </div>
            <div class="info-box">
              <label class="text-xs text-gray-600 font-medium block mb-1">จังหวัด</label>
              <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($job['province'] ?? '') ?></p>
            </div>
          </div>
        </div>
        <div class="space-y-3">
          <div class="info-box">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">ยี่ห้อ</label>
            <p class="font-semibold text-gray-900"><?= htmlspecialchars($job['model'] ?? '') ?></p>
          </div>
          <div class="info-box">
            <label class="text-xs sm:text-sm text-gray-600 font-medium block mb-1">รุ่น</label>
            <p class="font-semibold text-gray-900"><?= htmlspecialchars($job['model_detail'] ?? '') ?></p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="info-box">
              <label class="text-xs text-gray-600 font-medium block mb-1">สี</label>
              <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($job['color'] ?? '') ?></p>
            </div>
            <div class="info-box info-box-highlight">
              <label class="text-xs text-gray-600 font-medium block mb-1">ทะเบียน</label>
              <p class="font-bold text-base text-gray-900"><?= htmlspecialchars($job['plate'] ?? '') ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Remarks -->
    <?php if (!empty($job['remark'])): ?>
      <div class="card p-4 sm:p-6 fade-in">
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-xl">
          <div class="flex items-start gap-3">
            <div class="text-yellow-500 text-2xl flex-shrink-0"><i class="fas fa-edit"></i></div>
            <div class="flex-1 min-w-0">
              <h4 class="font-bold text-yellow-800 mb-2">หมายเหตุสำคัญ</h4>
              <p class="text-yellow-700 break-words"><?= nl2br(htmlspecialchars($job['remark'])) ?></p>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Return Reason (งานถูกตีกลับ) -->
    <?php if ($job['status'] === 'returned' && !empty($job['return_reason'])): ?>
      <div class="card p-4 sm:p-6 fade-in">
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl">
          <div class="flex items-start gap-3">
            <div class="text-red-500 text-2xl flex-shrink-0"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="flex-1 min-w-0">
              <h4 class="font-bold text-red-800 mb-2">งานถูกตีกลับเพื่อแก้ไข</h4>
              <div class="space-y-2">
                <p class="text-red-700 break-words"><strong>เหตุผล:</strong>
                  <?= nl2br(htmlspecialchars($job['return_reason'])) ?></p>
                <?php if (!empty($job['returned_by_name'])): ?>
                  <p class="text-red-600 text-sm"><strong>ตีกลับโดย:</strong>
                    <?= htmlspecialchars($job['returned_by_name']) ?></p>
                <?php endif; ?>
                <?php if (!empty($job['returned_at'])): ?>
                  <p class="text-red-600 text-sm"><strong>วันที่ตีกลับ:</strong>
                    <?= date('d/m/Y H:i น.', strtotime($job['returned_at'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($job['revision_count']) && $job['revision_count'] > 0): ?>
                  <p class="text-red-600 text-sm"><strong>จำนวนครั้งที่ตีกลับ:</strong> <?= $job['revision_count'] ?> ครั้ง
                  </p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Accept Job -->
    <?php if (!$can_submit): ?>
      <div class="card p-6 sm:p-8 text-center fade-in">
        <div class="mb-6">
          <div class="text-6xl sm:text-7xl mb-4"><i class="fas fa-bullseye"></i></div>
          <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">พร้อมรับงานนี้หรือยัง?</h3>
          <p class="text-gray-600">กดปุ่มด้านล่างเพื่อรับงานและเริ่มดำเนินการ</p>
        </div>
        <button onclick="confirmAcceptJob(<?= $job['id'] ?>)"
          class="btn btn-success text-base sm:text-lg px-8 py-4 w-full sm:w-auto">
          <i class="fas fa-check mr-2"></i>รับงานนี้
        </button>
      </div>

    <?php else: ?>
      <!-- Job Form -->
      <form method="post" action="save_job.php" enctype="multipart/form-data" id="jobForm" class="space-y-4 sm:space-y-6">
        <?= csrfField() ?>
        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">

        <!-- Progress -->
        <div class="card p-4 sm:p-6 fade-in">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-base sm:text-lg font-bold text-gray-900">บันทึกผลการปฏิบัติงาน</h3>
            <div class="flex items-center gap-3">
              <span class="text-xs sm:text-sm text-gray-600 font-medium hidden sm:inline">ความคืบหน้า</span>
              <div class="progress-ring">
                <svg width="48" height="48">
                  <circle class="progress-ring__circle" />
                  <circle class="progress-ring__circle progress-ring__circle--progress" id="progressCircle" />
                </svg>
              </div>
              <span id="progressText" class="text-sm sm:text-base font-bold text-blue-600">0%</span>
            </div>
          </div>

          <!-- Result -->
          <div class="mb-6">
            <label class="block text-sm sm:text-base font-bold text-gray-900 mb-3 required">ผลการลงพื้นที่</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <label class="radio-card">
                <input type="radio" name="result" value="พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง" required class="sr-only"
                  onchange="updateProgress()">
                <div class="flex items-center gap-2">
                  <div class="text-xl"><i class="fas fa-check-circle text-green-600"></i></div>
                  <div>
                    <div class="font-bold text-green-700 text-sm">พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง</div>
                  </div>
                </div>
              </label>
              <label class="radio-card">
                <input type="radio" name="result" value="ไม่พบผู้เช่า/ผู้คำ/ผู้ครอบครอง" required class="sr-only"
                  onchange="updateProgress()">
                <div class="flex items-center gap-2">
                  <div class="text-xl"><i class="fas fa-times-circle text-red-500"></i></div>
                  <div>
                    <div class="font-bold text-red-700 text-sm">ไม่พบผู้เช่า/ผู้คำ/ผู้ครอบครอง</div>
                  </div>
                </div>
              </label>
              <label class="radio-card">
                <input type="radio" name="result" value="พบที่ตั้ง /ไม่พบรถ" required class="sr-only"
                  onchange="updateProgress()">
                <div class="flex items-center gap-2">
                  <div class="text-xl"><i class="fas fa-home"></i></div>
                  <div>
                    <div class="font-bold text-blue-700 text-sm">พบที่ตั้ง /ไม่พบรถ</div>
                  </div>
                </div>
              </label>
              <label class="radio-card">
                <input type="radio" name="result" value="ไม่พบที่ตั้ง/ไม่พบรถ" required class="sr-only"
                  onchange="updateProgress()">
                <div class="flex items-center gap-2">
                  <div class="text-xl"><i class="fas fa-question-circle"></i></div>
                  <div>
                    <div class="font-bold text-gray-700 text-sm">ไม่พบที่ตั้ง/ไม่พบรถ</div>
                  </div>
                </div>
              </label>
              <label class="radio-card">
                <input type="radio" name="result" value="พบ บ.3 ฝากเรื่องติดต่อกลับ" required class="sr-only"
                  onchange="updateProgress()">
                <div class="flex items-center gap-2">
                  <div class="text-xl"><i class="fas fa-clipboard"></i></div>
                  <div>
                    <div class="font-bold text-purple-700 text-sm">พบ บ.3 ฝากเรื่องติดต่อกลับ</div>
                  </div>
                </div>
              </label>
              <label class="radio-card">
                <input type="radio" name="result" value="พบรถ ไม่พบผู้เช่า/ผู้ค้ำ" required class="sr-only"
                  onchange="updateProgress()">
                <div class="flex items-center gap-2">
                  <div class="text-xl"><i class="fas fa-car"></i></div>
                  <div>
                    <div class="font-bold text-orange-700 text-sm">พบรถ ไม่พบผู้เช่า/ผู้ค้ำ</div>
                  </div>
                </div>
              </label>
              <label class="radio-card">
                <input type="radio" name="result" value="นัดชำระ" required class="sr-only" onchange="updateProgress()">
                <div class="flex items-center gap-2">
                  <div class="text-xl"><i class="fas fa-coins"></i></div>
                  <div>
                    <div class="font-bold text-indigo-700 text-sm">นัดชำระ</div>
                  </div>
                </div>
              </label>
              <label class="radio-card">
                <input type="radio" name="result" value="นัดคืนรถ" required class="sr-only" onchange="updateProgress()">
                <div class="flex items-center gap-2">
                  <div class="text-xl"><i class="fas fa-undo text-teal-600"></i></div>
                  <div>
                    <div class="font-bold text-teal-700 text-sm">นัดคืนรถ</div>
                  </div>
                </div>
              </label>
            </div>
            <div id="resultValidation" class="validation-message validation-error hidden">
              กรุณาเลือกผลการลงพื้นที่
            </div>
          </div>

          <!-- DateTime -->
          <div class="mb-6">
            <label class="block text-sm sm:text-base font-bold text-gray-900 mb-2 required">วันที่และเวลา</label>
            <input type="datetime-local" name="log_time" class="form-input" value="<?= $date_now ?>" readonly>
            <p class="text-xs text-gray-500 mt-2">⏰ เวลาจะถูกบันทึกอัตโนมัติตามเวลาปัจจุบัน</p>
          </div>

          <!-- Notes -->
          <div class="mb-6">
            <label class="block text-sm sm:text-base font-bold text-gray-900 mb-2 required">รายละเอียดและหมายเหตุ</label>
            <textarea name="note" rows="4" required class="form-input resize-none"
              placeholder="กรุณาระบุรายละเอียดการปฏิบัติงาน เช่น สภาพรถ, สถานที่พบ, ผู้ติดต่อ, อุปสรรค หรือข้อมูลเพิ่มเติมอื่นๆ"
              onchange="updateProgress()" oninput="updateNoteCounter()"></textarea>
            <div id="noteValidation" class="validation-message validation-error hidden">
              กรุณากรอกรายละเอียดและหมายเหตุ
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-2">
              <span><i class="fas fa-lightbulb mr-1"></i>ข้อมูลรายละเอียดจะช่วยในการติดตามงาน</span>
              <span id="noteCounter" class="font-semibold">0 ตัวอักษร</span>
            </div>
          </div>

          <!-- GPS -->
          <div class="mb-6">
            <label class="block text-sm sm:text-base font-bold text-gray-900 mb-2 required">พิกัด GPS</label>
            <div class="space-y-3">
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <input type="text" name="gps" id="gps" class="form-input col-span-1 sm:col-span-2" readonly
                  value="<?= htmlspecialchars($job['gps'] ?? '') ?>" placeholder="กรุณาดึงพิกัด GPS">
                <button type="button" onclick="getLocation()" class="btn btn-primary" id="getLocationBtn">
                  <i class="fas fa-satellite-dish mr-1"></i>ดึงพิกัด
                </button>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <input type="text" id="search_latlng" placeholder="13.9212182,100.6560147"
                  class="form-input col-span-1 sm:col-span-2">
                <button type="button" onclick="searchLatLng()" class="btn btn-purple">
                  <i class="fas fa-search-location mr-1"></i>ค้นหาพิกัด
                </button>
              </div>
              <button type="button" onclick="clearGps()" class="btn btn-danger w-full sm:w-auto">
                <i class="fas fa-trash mr-1"></i>ลบพิกัด
              </button>
            </div>
            <div id="gpsValidation" class="validation-message validation-error hidden">
              กรุณาดึงพิกัด GPS ก่อนส่งงาน
            </div>
            <div id="gpsSuccess" class="validation-message validation-success hidden">
              <i class="fas fa-check-circle mr-1"></i>พิกัด GPS ถูกต้อง
            </div>
          </div>

          <!-- Map -->
          <div class="mb-6">
            <div id="map"></div>
            <p class="text-xs text-gray-500 mt-2"><i class="fas fa-map-marker-alt mr-1"></i>คลิกและลากเครื่องหมายบนแผนที่เพื่อปรับตำแหน่งพิกัด</p>
          </div>

          <!-- Images -->
          <div class="mb-6">
            <label class="block text-sm sm:text-base font-bold text-gray-900 mb-2 required"><i class="fas fa-camera mr-1"></i>รูปถ่าย (ต้องอัปโหลด 6 รูป)</label>
            <div class="file-upload" id="fileUploadArea">
              <input type="file" name="images[]" multiple accept="image/*" class="hidden" id="imageInput"
                onchange="handleFileSelection(this)">
              <div class="upload-content">
                <div class="text-4xl sm:text-5xl mb-3"><i class="fas fa-camera text-gray-400"></i></div>
                <p class="font-bold text-sm sm:text-base mb-2">คลิกเพื่อเลือกรูปภาพ</p>
                <p class="text-xs sm:text-sm text-gray-500 mb-1">หรือลากไฟล์มาวางที่นี่</p>
                <p class="text-xs text-gray-400">รองรับไฟล์ JPG, PNG ขนาดไม่เกิน 10MB ต่อไฟล์</p>
              </div>
            </div>
            <div id="imageValidation" class="validation-message validation-error hidden">
              กรุณาอัปโหลดรูปภาพ 6 รูป
            </div>
            <div id="imageSuccess" class="validation-message validation-success hidden">
              <i class="fas fa-check-circle mr-1"></i>อัปโหลดรูปภาพครบ 6 รูปแล้ว
            </div>

            <div id="imagePreview" class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 hidden"></div>
          </div>

          <!-- Submit -->
          <div class="text-center space-y-4">
            <button type="submit" class="btn btn-success text-base sm:text-lg px-8 py-4 w-full sm:w-auto" id="submitBtn"
              disabled>
              <i class="fas fa-save mr-2"></i>บันทึกผลภาคสนาม
            </button>
            <div id="submitValidation" class="validation-message validation-error hidden">
              กรุณากรอกข้อมูลให้ครบถ้วนก่อนส่งงาน
            </div>
            <div class="text-xs text-gray-500">
              <i class="fas fa-exclamation-triangle mr-1 text-yellow-500"></i>ตรวจสอบข้อมูลให้ครบถ้วนก่อนกดส่ง การส่งงานจะไม่สามารถแก้ไขได้
            </div>
          </div>
        </div>

        <!-- Reject -->
        <div class="card p-4 sm:p-6 text-center border-2 border-red-200 fade-in">
          <h4 class="font-bold text-red-700 mb-3 text-sm sm:text-base">ไม่สามารถปฏิบัติงานได้?</h4>
          <a href="reject_job.php?id=<?= $job['id'] ?>" onclick="return confirmRejectJob();"
            class="btn btn-danger w-full sm:w-auto">
            <i class="fas fa-times mr-2"></i>ปฏิเสธงานนี้
          </a>
          <p class="text-xs text-gray-500 mt-2">
            <i class="fas fa-comment mr-1"></i>ติดต่อหัวหน้างานก่อนปฏิเสธงานนี้
          </p>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <script>
    let map;
    let marker;
    let uploadedImages = [];
    let isFormValid = false;

    document.addEventListener('DOMContentLoaded', function () {
      initializeForm();
      setupEventListeners();
      updateProgress();
    });

    function initializeForm() {
      const noteTextarea = document.querySelector('textarea[name="note"]');
      const noteCounter = document.getElementById('noteCounter');

      noteTextarea.addEventListener('input', function () {
        updateNoteCounter();
        updateProgress();
      });

      const fileUploadArea = document.getElementById('fileUploadArea');
      const imageInput = document.getElementById('imageInput');

      fileUploadArea.addEventListener('click', () => imageInput.click());

      fileUploadArea.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.classList.add('dragover');
      });

      fileUploadArea.addEventListener('dragleave', function (e) {
        e.preventDefault();
        this.classList.remove('dragover');
      });

      fileUploadArea.addEventListener('drop', function (e) {
        e.preventDefault();
        this.classList.remove('dragover');
        const files = e.dataTransfer.files;
        handleFiles(files);
      });
    }

    function updateNoteCounter() {
      const noteTextarea = document.querySelector('textarea[name="note"]');
      const noteCounter = document.getElementById('noteCounter');
      noteCounter.textContent = noteTextarea.value.length + ' ตัวอักษร';
    }

    function setupEventListeners() {
      document.getElementById('jobForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!validateForm()) {
          showValidationErrors();
          return;
        }

        // ยืนยันก่อนส่ง
        const confirm = await Swal.fire({
          title: 'ยืนยันการส่งงาน?',
          text: 'หลังจากส่งแล้วจะไม่สามารถแก้ไขได้',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'ส่งงาน',
          cancelButtonText: 'ยกเลิก',
          confirmButtonColor: '#22c55e',
          cancelButtonColor: '#6b7280',
          reverseButtons: true
        });

        if (!confirm.isConfirmed) return;

        // แสดง loading
        Swal.fire({
          title: 'กำลังบันทึก...',
          text: 'กรุณารอสักครู่',
          allowOutsideClick: false,
          allowEscapeKey: false,
          didOpen: () => { Swal.showLoading(); }
        });

        try {
          const formData = new FormData(this);
          const res = await fetch('save_job.php', {
            method: 'POST',
            body: formData
          });

          const text = await res.text();
          let data;
          try {
            data = JSON.parse(text);
          } catch {
            Swal.fire({
              title: 'เกิดข้อผิดพลาด',
              text: 'เซิร์ฟเวอร์ตอบกลับผิดพลาด กรุณาลองใหม่',
              icon: 'error',
              footer: `<small style="word-break:break-all">${text.substring(0, 200)}</small>`,
              confirmButtonText: 'ตกลง',
              confirmButtonColor: '#ef4444'
            });
            return;
          }

          if (data.success) {
            localStorage.removeItem(`job_draft_${<?= $job['id'] ?>}`);
            await Swal.fire({
              title: 'บันทึกสำเร็จ!',
              text: 'ส่งผลการปฏิบัติงานเรียบร้อยแล้ว',
              icon: 'success',
              confirmButtonText: 'ตกลง',
              confirmButtonColor: '#22c55e',
              timer: 2500,
              timerProgressBar: true
            });
            window.location.href = 'field.php';
          } else {
            Swal.fire({
              title: 'เกิดข้อผิดพลาด',
              text: data.message || 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่',
              icon: 'error',
              confirmButtonText: 'ตกลง',
              confirmButtonColor: '#ef4444'
            });
          }
        } catch (err) {
          Swal.fire({
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่',
            icon: 'error',
            footer: `<small>${err.message}</small>`,
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#ef4444'
          });
        }
      });

      document.querySelectorAll('input[name="result"]').forEach(radio => {
        radio.addEventListener('change', function () {
          this.closest('label').parentElement.querySelectorAll('label').forEach(l => {
            l.style.borderColor = '#e5e7eb';
            l.style.background = '#ffffff';
          });
          this.closest('label').style.borderColor = '#3b82f6';
          this.closest('label').style.background = '#eff6ff';
          updateProgress();
        });
      });
    }

    function updateProgress() {
      const result = document.querySelector('input[name="result"]:checked');
      const note = document.querySelector('textarea[name="note"]').value.trim();
      const gps = document.getElementById('gps').value.trim();
      const images = uploadedImages.length;

      let progress = 0;
      let completedSteps = 0;
      const totalSteps = 4;

      if (result) {
        completedSteps++;
        hideValidation('resultValidation');
      }

      if (note.length > 0) {
        completedSteps++;
        hideValidation('noteValidation');
      }

      if (gps.length > 0) {
        completedSteps++;
        hideValidation('gpsValidation');
        showValidation('gpsSuccess', 'success');
      } else {
        hideValidation('gpsSuccess');
      }

      if (images === 6) {
        completedSteps++;
        hideValidation('imageValidation');
        showValidation('imageSuccess', 'success');
      } else {
        hideValidation('imageSuccess');
      }

      progress = (completedSteps / totalSteps) * 100;

      const circle = document.getElementById('progressCircle');
      const circumference = 2 * Math.PI * 20;
      const offset = circumference - (progress / 100) * circumference;
      circle.style.strokeDashoffset = offset;

      document.getElementById('progressText').textContent = Math.round(progress) + '%';

      const submitBtn = document.getElementById('submitBtn');
      isFormValid = completedSteps === totalSteps;

      if (isFormValid) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        hideValidation('submitValidation');
      } else {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
      }
    }

    function showValidation(elementId, type) {
      const element = document.getElementById(elementId);
      if (element) {
        element.className = `validation-message validation-${type}`;
        element.classList.remove('hidden');
      }
    }

    function hideValidation(elementId) {
      const element = document.getElementById(elementId);
      if (element) {
        element.classList.add('hidden');
      }
    }

    function validateForm() {
      const result = document.querySelector('input[name="result"]:checked');
      const note = document.querySelector('textarea[name="note"]').value.trim();
      const gps = document.getElementById('gps').value.trim();
      const images = uploadedImages.length;

      if (!result || !note || !gps || images !== 6) {
        return false;
      }
      return true;
    }

    function showValidationErrors() {
      showValidation('submitValidation', 'error');
    }

    function getLocation() {
      const btn = document.getElementById('getLocationBtn');
      btn.classList.add('loading');
      btn.textContent = 'กำลังดึงพิกัด...';

      if (!navigator.geolocation) {
        showNotification('เบราว์เซอร์ไม่รองรับ GPS', 'error');
        resetLocationButton();
        return;
      }

      navigator.geolocation.getCurrentPosition(
        function (position) {
          const latlng = position.coords.latitude.toFixed(6) + ',' + position.coords.longitude.toFixed(6);
          const accuracy = position.coords.accuracy;

          document.getElementById('gps').value = latlng;
          setMapMarker(position.coords.latitude, position.coords.longitude);

          showNotification(`พิกัด GPS: ${latlng}<br>ความแม่นยำ: ${Math.round(accuracy)} เมตร`, 'success');
          updateProgress();
          resetLocationButton();
        },
        function (error) {
          let errorMsg = 'ไม่สามารถดึงพิกัดได้';
          switch (error.code) {
            case error.PERMISSION_DENIED:
              errorMsg = 'การเข้าถึงตำแหน่งถูกปฏิเสธ กรุณาอนุญาตการเข้าถึงตำแหน่ง';
              break;
            case error.POSITION_UNAVAILABLE:
              errorMsg = 'ไม่สามารถหาตำแหน่งได้ กรุณาลองใหม่';
              break;
            case error.TIMEOUT:
              errorMsg = 'หมดเวลาในการค้นหาตำแหน่ง กรุณาลองใหม่';
              break;
          }
          showNotification(errorMsg, 'error');
          resetLocationButton();
        },
        {
          enableHighAccuracy: true,
          timeout: 15000,
          maximumAge: 60000
        }
      );
    }

    function resetLocationButton() {
      const btn = document.getElementById('getLocationBtn');
      btn.classList.remove('loading');
      btn.innerHTML = '<i class="fas fa-satellite-dish mr-1"></i>ดึงพิกัด';
    }

    function searchLatLng() {
      const input = document.getElementById('search_latlng').value.trim();
      const regex = /^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/;

      if (!regex.test(input)) {
        showNotification('รูปแบบพิกัดไม่ถูกต้อง<br>ตัวอย่าง: 13.9212182,100.6560147', 'error');
        return;
      }

      const [lat, lng] = input.split(',').map(Number);

      if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        showNotification('พิกัดอยู่นอกช่วงที่ถูกต้อง', 'error');
        return;
      }

      setMapMarker(lat, lng);
      document.getElementById('gps').value = lat.toFixed(6) + "," + lng.toFixed(6);
      showNotification(`ปักพิกัดสำเร็จ: ${lat.toFixed(6)}, ${lng.toFixed(6)}`, 'success');
      updateProgress();
    }

    function clearGps() {
      Swal.fire({
        title: 'ยืนยันการลบพิกัด',
        text: 'คุณต้องการลบพิกัด GPS หรือไม่?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#ef4444'
      }).then((result) => {
        if (result.isConfirmed) {
          document.getElementById('gps').value = '';
          document.getElementById('search_latlng').value = '';
          if (marker) {
            marker.setMap(null);
            marker = null;
          }
          map.setCenter({ lat: 13.736717, lng: 100.523186 });
          map.setZoom(14);
          showNotification('ลบพิกัดแล้ว', 'success');
          updateProgress();
        }
      });
    }

    function initMap() {
      let gpsValue = document.getElementById('gps').value;
      let defaultPos = { lat: 13.736717, lng: 100.523186 };

      if (gpsValue) {
        const [lat, lng] = gpsValue.split(',').map(Number);
        if (!isNaN(lat) && !isNaN(lng)) {
          defaultPos = { lat: lat, lng: lng };
        }
      }

      map = new google.maps.Map(document.getElementById("map"), {
        zoom: 16,
        center: defaultPos,
        mapTypeControl: true,
        streetViewControl: true,
        fullscreenControl: true
      });

      if (gpsValue) {
        marker = new google.maps.Marker({
          position: defaultPos,
          map: map,
          draggable: true,
          animation: google.maps.Animation.DROP,
          title: 'พิกัดงาน'
        });

        marker.addListener("dragend", function () {
          const pos = marker.getPosition();
          document.getElementById("gps").value = pos.lat().toFixed(6) + "," + pos.lng().toFixed(6);
          updateProgress();
        });
      }
    }

    function setMapMarker(lat, lng) {
      const pos = new google.maps.LatLng(lat, lng);
      map.setCenter(pos);
      map.setZoom(16);

      if (!marker) {
        marker = new google.maps.Marker({
          position: pos,
          map: map,
          draggable: true,
          animation: google.maps.Animation.DROP,
          title: 'พิกัดงาน'
        });

        marker.addListener("dragend", function () {
          const pos = marker.getPosition();
          document.getElementById("gps").value = pos.lat().toFixed(6) + "," + pos.lng().toFixed(6);
          updateProgress();
        });
      } else {
        marker.setPosition(pos);
      }
    }

    function handleFileSelection(input) {
      handleFiles(input.files);
    }

    function handleFiles(files) {
      if (files.length > 6) {
        showNotification('อัปโหลดได้ไม่เกิน 6 รูป', 'error');
        return;
      }

      uploadedImages = [];
      const preview = document.getElementById('imagePreview');
      preview.innerHTML = '';
      preview.classList.remove('hidden');

      for (let i = 0; i < files.length; i++) {
        const file = files[i];

        if (!file.type.startsWith('image/')) {
          showNotification(`ไฟล์ ${file.name} ไม่ใช่รูปภาพ`, 'error');
          continue;
        }

        if (file.size > 10 * 1024 * 1024) {
          showNotification(`ไฟล์ ${file.name} มีขนาดใหญ่เกิน 10MB`, 'error');
          continue;
        }

        uploadedImages.push(file);

        const reader = new FileReader();
        reader.onload = function (e) {
          const div = document.createElement('div');
          div.className = 'image-preview-container';
          div.innerHTML = `
        <img src="${e.target.result}" class="image-preview">
        <button type="button" onclick="removeImage(${i})" class="remove-image-btn">
          ×
        </button>
        <div class="text-xs text-gray-600 mt-2 px-2 truncate font-medium">${file.name}</div>
      `;
          preview.appendChild(div);
        };
        reader.readAsDataURL(file);
      }

      updateProgress();
    }

    function removeImage(index) {
      uploadedImages.splice(index, 1);

      const dt = new DataTransfer();
      uploadedImages.forEach(file => dt.items.add(file));
      document.getElementById('imageInput').files = dt.files;

      handleFiles(uploadedImages);
      updateProgress();
    }

    function copyText(text) {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
          showNotification(`คัดลอกแล้ว: ${text}`, 'success');
        }).catch(() => {
          fallbackCopyText(text);
        });
      } else {
        fallbackCopyText(text);
      }
    }

    function fallbackCopyText(text) {
      const textArea = document.createElement('textarea');
      textArea.value = text;
      document.body.appendChild(textArea);
      textArea.select();
      try {
        document.execCommand('copy');
        showNotification(`คัดลอกแล้ว: ${text}`, 'success');
      } catch (err) {
        showNotification('ไม่สามารถคัดลอกได้', 'error');
      }
      document.body.removeChild(textArea);
    }

    function showNotification(message, type = 'info') {
      const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
      };

      const icons = {
        success: '<i class="fas fa-check-circle"></i>',
        error: '<i class="fas fa-times-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>',
        info: '<i class="fas fa-info-circle"></i>'
      };

      Swal.fire({
        html: `<div class="flex items-center gap-3"><span class="text-2xl">${icons[type]}</span><span>${message}</span></div>`,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        background: colors[type],
        color: 'white'
      });
    }

    function confirmAcceptJob(jobId) {
      Swal.fire({
        title: 'ยืนยันการรับงาน',
        text: 'คุณต้องการรับงานนี้ใช่หรือไม่?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'รับงาน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#10b981'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'accept_job.php?id=' + jobId;
        }
      });
    }

    function confirmRejectJob() {
      return new Promise((resolve) => {
        Swal.fire({
          title: 'ยืนยันการปฏิเสธงาน',
          text: 'คุณแน่ใจหรือไม่ที่จะปฏิเสธงานนี้? งานจะถูกส่งกลับให้ผู้อื่นรับได้',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'ปฏิเสธงาน',
          cancelButtonText: 'ยกเลิก',
          confirmButtonColor: '#ef4444'
        }).then((result) => {
          resolve(result.isConfirmed);
        });
      });
    }

    function showJobHistory() {
      const contractNumber = '<?= addslashes($job['contract_number']) ?>';
      const currentJobId = <?= $job['id'] ?>;

      Swal.fire({
        title: '<div class="flex items-center gap-2"><svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span>ประวัติงานสัญญา ' + contractNumber + '</span></div>',
        html: '<div class="text-center py-8"><div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-gray-200 border-t-blue-600"></div><p class="mt-4 text-gray-600">กำลังโหลดข้อมูล...</p></div>',
        showConfirmButton: false,
        showCancelButton: false,
        allowOutsideClick: true,
        width: '90%',
        maxWidth: '800px',
        didOpen: () => {
          fetch(`api/get_job_history.php?contract_number=${encodeURIComponent(contractNumber)}&current_job_id=${currentJobId}`)
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                if (data.count === 0) {
                  Swal.update({
                    html: `
                  <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">ไม่พบประวัติงาน</h3>
                    <p class="text-gray-600">ไม่มีงานอื่นของสัญญานี้ในระบบ</p>
                  </div>
                `,
                    showConfirmButton: true,
                    confirmButtonText: 'ปิด',
                    confirmButtonColor: '#3b82f6'
                  });
                } else {
                  let historyHtml = `
                <div class="text-left">
                  <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800">
                      <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                      พบ <strong>${data.count}</strong> งานก่อนหน้านี้
                    </p>
                  </div>
                  <div class="space-y-3 max-h-96 overflow-y-auto">
              `;

                  data.history.forEach((job, index) => {
                    const statusBadge = job.status === 'completed'
                      ? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">เสร็จแล้ว</span>'
                      : '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">รอดำเนินการ</span>';

                    const createdDate = job.created_at
                      ? new Date(job.created_at).toLocaleString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' })
                      : '-';

                    const logTime = job.log_time
                      ? new Date(job.log_time).toLocaleString('th-TH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                      : '-';

                    // GPS Link
                    const hasGPS = job.gps && job.gps.trim() !== '';
                    const gpsButton = hasGPS
                      ? `<button onclick="openGoogleMaps('${job.gps}')"
                             class="w-full px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-semibold transition-colors flex items-center justify-center gap-1">
                       <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                       </svg>
                       นำทางด้วย Google Maps
                     </button>`
                      : `<button disabled class="w-full px-3 py-2 bg-gray-300 text-gray-500 rounded-lg text-xs font-semibold cursor-not-allowed flex items-center justify-center gap-1">
                       <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                       </svg>
                       ไม่มีพิกัด GPS
                     </button>`;

                    historyHtml += `
                  <div class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-md transition-all duration-200 bg-white">
                    <div class="p-4">
                      <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex-1 min-w-0">
                          <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-bold text-blue-600">#${job.id}</span>
                            ${statusBadge}
                          </div>
                          <div class="text-sm font-semibold text-gray-900 mb-1">
                            <i class="fas fa-map-marker-alt mr-1"></i>${job.location_info || '-'}
                          </div>
                          <div class="text-xs text-gray-500">
                            <i class="fas fa-tag mr-1"></i>${job.zone || '-'} | ${job.province || '-'}
                          </div>
                          <div class="text-xs text-gray-400 mt-1">
                            <i class="fas fa-calendar-alt mr-1"></i>สร้างเมื่อ: ${createdDate}
                          </div>
                        </div>
                        <button onclick="toggleDetails(${index})" id="toggleBtn_${index}"
                                class="flex-shrink-0 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition-colors">
                          <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                          </svg>
                          ดูเพิ่ม
                        </button>
                      </div>

                      <!-- Expandable Details -->
                      <div id="details_${index}" class="hidden border-t border-gray-100 pt-3 mt-3 space-y-3">
                        ${job.result ? `
                          <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="text-xs font-semibold text-blue-800 mb-1">ผลการลงพื้นที่</div>
                            <div class="text-sm text-blue-900">${job.result}</div>
                          </div>
                        ` : ''}

                        ${job.note ? `
                          <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-xs font-semibold text-gray-700 mb-1"><i class="fas fa-comment mr-1"></i> หมายเหตุ</div>
                            <div class="text-sm text-gray-600 whitespace-pre-wrap">${job.note}</div>
                          </div>
                        ` : ''}

                        ${job.log_time ? `
                          <div class="bg-green-50 p-3 rounded-lg">
                            <div class="text-xs font-semibold text-green-800 mb-1"><i class="fas fa-calendar-alt mr-1"></i> วันที่บันทึกผล</div>
                            <div class="text-sm text-green-900">${logTime}</div>
                          </div>
                        ` : ''}

                        ${hasGPS ? `
                          <div class="bg-purple-50 p-3 rounded-lg">
                            <div class="text-xs font-semibold text-purple-800 mb-1"><i class="fas fa-map-marker-alt mr-1"></i> พิกัด GPS</div>
                            <div class="text-sm text-purple-900 font-mono">${job.gps}</div>
                          </div>
                        ` : ''}

                        ${job.assigned_name ? `
                          <div class="text-xs text-gray-500">
                            <i class="fas fa-user mr-1"></i> ผู้รับผิดชอบ: <span class="font-semibold">${job.assigned_name}</span>
                          </div>
                        ` : ''}

                        <div class="pt-2">
                          ${gpsButton}
                        </div>

                        <div class="flex gap-2">
                          <a href="job_result.php?id=${job.id}" target="_blank"
                             class="flex-1 px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-semibold transition-colors text-center">
                            <i class="fas fa-chart-bar mr-1"></i> ดูผลงานที่บันทึก
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                `;
                  });

                  historyHtml += `
                  </div>
                </div>
              `;

                  Swal.update({
                    html: historyHtml,
                    showConfirmButton: true,
                    confirmButtonText: 'ปิด',
                    confirmButtonColor: '#3b82f6'
                  });
                }
              } else {
                Swal.update({
                  html: `
                <div class="text-center py-12">
                  <svg class="w-16 h-16 mx-auto text-red-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  <h3 class="text-lg font-semibold text-gray-900 mb-2">เกิดข้อผิดพลาด</h3>
                  <p class="text-gray-600">${data.message || 'ไม่สามารถโหลดข้อมูลได้'}</p>
                </div>
              `,
                  showConfirmButton: true,
                  confirmButtonText: 'ปิด',
                  confirmButtonColor: '#ef4444'
                });
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.update({
                html: `
              <div class="text-center py-12">
                <svg class="w-16 h-16 mx-auto text-red-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">เกิดข้อผิดพลาด</h3>
                <p class="text-gray-600">ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้</p>
              </div>
            `,
                showConfirmButton: true,
                confirmButtonText: 'ปิด',
                confirmButtonColor: '#ef4444'
              });
            });
        }
      });
    }

    function toggleDetails(index) {
      const detailsDiv = document.getElementById(`details_${index}`);
      const toggleBtn = document.getElementById(`toggleBtn_${index}`);

      if (detailsDiv.classList.contains('hidden')) {
        detailsDiv.classList.remove('hidden');
        toggleBtn.innerHTML = `
      <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
      </svg>
      ซ่อน
    `;
      } else {
        detailsDiv.classList.add('hidden');
        toggleBtn.innerHTML = `
      <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
      </svg>
      ดูเพิ่ม
    `;
      }
    }

    function openGoogleMaps(gps) {
      if (!gps || gps.trim() === '') {
        showNotification('ไม่มีพิกัด GPS', 'error');
        return;
      }

      // แยก latitude และ longitude
      const coords = gps.split(',').map(c => c.trim());
      if (coords.length !== 2) {
        showNotification('รูปแบบพิกัด GPS ไม่ถูกต้อง', 'error');
        return;
      }

      const lat = coords[0];
      const lng = coords[1];

      // สร้าง URL สำหรับ Google Maps พร้อมปักมุด
      const googleMapsUrl = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;

      // เปิดในหน้าต่างใหม่
      window.open(googleMapsUrl, '_blank');

      showNotification('เปิด Google Maps แล้ว', 'success');
    }

    function saveDraft() {
      const formData = {
        result: document.querySelector('input[name="result"]:checked')?.value,
        note: document.querySelector('textarea[name="note"]').value,
        gps: document.getElementById('gps').value
      };

      localStorage.setItem(`job_draft_${<?= $job['id'] ?>}`, JSON.stringify(formData));
    }

    function loadDraft() {
      const draft = localStorage.getItem(`job_draft_${<?= $job['id'] ?>}`);
      if (draft) {
        const data = JSON.parse(draft);
        if (data.result) {
          const radio = document.querySelector(`input[name="result"][value="${data.result}"]`);
          if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
          }
        }
        if (data.note) {
          document.querySelector('textarea[name="note"]').value = data.note;
          updateNoteCounter();
        }
        if (data.gps) {
          document.getElementById('gps').value = data.gps;
        }
        updateProgress();
      }
    }

    setInterval(saveDraft, 30000);

    document.addEventListener('DOMContentLoaded', loadDraft);

  </script>

  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB3NfHFEyJb3yltga-dX0C23jsLEAQpORc&callback=initMap"
    async defer></script>

</body>

</html>