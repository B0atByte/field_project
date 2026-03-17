<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
requirePermission('action_export_word');
include '../config/db.php';
require_once '../includes/csrf.php';

$page_title = "Export Word จาก Excel";
?>
<!DOCTYPE html>
<html lang="th">
<?php include '../components/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  :root {
    --primary-color: #0f172a;
    --accent-color: #2563eb;
    --bg-color: #f8fafc;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
  }

  body {
    background-color: var(--bg-color);
    font-family: 'Sarabun', 'Inter', sans-serif;
    color: var(--text-primary);
  }

  .main-content {
    margin-left: 16rem;
    padding: 1.5rem;
  }

  .page-header {
    background: white;
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem 2rem;
    margin: -1.5rem -1.5rem 1.5rem -1.5rem;
  }

  .page-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  }

  .upload-zone {
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 3rem;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
  }

  .upload-zone:hover, .upload-zone.dragover {
    border-color: var(--accent-color);
    background: #eff6ff;
  }

  .upload-zone i {
    font-size: 3rem;
    color: var(--accent-color);
    margin-bottom: 1rem;
  }

  .btn-primary {
    background-color: var(--accent-color);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
  }

  .btn-primary:hover {
    background-color: #1d4ed8;
  }

  .btn-primary:disabled {
    background-color: #94a3b8;
    cursor: not-allowed;
  }

  .btn-secondary {
    background-color: white;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
    cursor: pointer;
  }

  .btn-secondary:hover {
    background-color: var(--bg-color);
  }

  .preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }

  .preview-table th {
    background: var(--bg-color);
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--border-color);
  }

  .preview-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
  }

  .preview-table tr:hover {
    background: #f1f5f9;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
  }

  .status-found {
    background: #dcfce7;
    color: #166534;
  }

  .status-not-found {
    background: #fee2e2;
    color: #991b1b;
  }

  .summary-box {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
  }

  .summary-item {
    flex: 1;
    background: var(--bg-color);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
  }

  .summary-item .number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent-color);
  }

  .summary-item .label {
    font-size: 0.875rem;
    color: var(--text-secondary);
  }

  .hidden { display: none; }

  .progress-bar {
    height: 8px;
    background: var(--border-color);
    border-radius: 4px;
    overflow: hidden;
    margin: 1rem 0;
  }

  .progress-bar .fill {
    height: 100%;
    background: var(--accent-color);
    transition: width 0.3s;
  }

  .step-indicator {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-bottom: 2rem;
  }

  .step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
  }

  .step.active {
    color: var(--accent-color);
    font-weight: 600;
  }

  .step.completed {
    color: #16a34a;
  }

  .step-number {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 600;
  }

  .step.active .step-number {
    background: var(--accent-color);
    color: white;
  }

  .step.completed .step-number {
    background: #16a34a;
    color: white;
  }
</style>

<body class="min-h-screen">
<?php include '../components/sidebar.php'; ?>

<main class="main-content">
  <div class="page-header">
    <div class="flex justify-between items-center">
      <div>
        <h1 class="page-title">
          <i class="fas fa-file-word text-blue-600"></i>
          Export Word จาก Excel
        </h1>
        <p class="text-sm text-slate-500 mt-1">อัปโหลดไฟล์ Excel ที่กรองแล้ว เพื่อ Export เป็น Word</p>
      </div>
      <a href="jobs.php" class="btn-secondary">
        <i class="fas fa-arrow-left"></i>
        กลับ
      </a>
    </div>
  </div>

  <!-- Step Indicator -->
  <div class="step-indicator">
    <div class="step active" id="step1-indicator">
      <span class="step-number">1</span>
      <span>อัปโหลด Excel</span>
    </div>
    <div class="step" id="step2-indicator">
      <span class="step-number">2</span>
      <span>ตรวจสอบข้อมูล</span>
    </div>
    <div class="step" id="step3-indicator">
      <span class="step-number">3</span>
      <span>ดาวน์โหลด</span>
    </div>
  </div>

  <!-- Step 1: Upload -->
  <div id="step1" class="card">
    <h3 class="text-lg font-semibold mb-4">
      <i class="fas fa-upload text-blue-600 mr-2"></i>
      อัปโหลดไฟล์ Excel
    </h3>

    <div class="upload-zone" id="uploadZone">
      <i class="fas fa-file-excel"></i>
      <p class="text-lg font-medium mb-2">ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือก</p>
      <p class="text-sm text-slate-500">รองรับไฟล์ .xlsx ที่ Export จากระบบ</p>
      <input type="file" id="excelFile" accept=".xlsx,.xls" class="hidden">
    </div>

    <div id="fileInfo" class="hidden mt-4 p-4 bg-blue-50 rounded-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <i class="fas fa-file-excel text-green-600 text-2xl"></i>
          <div>
            <p class="font-medium" id="fileName"></p>
            <p class="text-sm text-slate-500" id="fileSize"></p>
          </div>
        </div>
        <button type="button" id="removeFile" class="text-red-500 hover:text-red-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>

    <div class="mt-4 flex gap-3">
      <button type="button" id="processBtn" class="btn-primary" disabled>
        <i class="fas fa-cogs"></i>
        ประมวลผล
      </button>
    </div>

    <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
      <p class="text-sm text-amber-800">
        <i class="fas fa-info-circle mr-2"></i>
        <strong>วิธีใช้:</strong>
        1. กรองข้อมูลในหน้า "รายการงาน" แล้ว Export Excel
        2. นำไฟล์ Excel มาอัปโหลดที่นี่
        3. ระบบจะจับคู่ข้อมูลและ Export เป็น Word
      </p>
    </div>
  </div>

  <!-- Step 2: Preview -->
  <div id="step2" class="card hidden">
    <h3 class="text-lg font-semibold mb-4">
      <i class="fas fa-list-check text-blue-600 mr-2"></i>
      ตรวจสอบข้อมูลที่พบ
    </h3>

    <div class="summary-box">
      <div class="summary-item">
        <div class="number" id="totalCount">0</div>
        <div class="label">รายการในไฟล์</div>
      </div>
      <div class="summary-item">
        <div class="number" id="foundCount" style="color: #16a34a;">0</div>
        <div class="label">พบในระบบ</div>
      </div>
      <div class="summary-item">
        <div class="number" id="notFoundCount" style="color: #dc2626;">0</div>
        <div class="label">ไม่พบ/ยังไม่ส่ง</div>
      </div>
    </div>

    <div class="overflow-x-auto" style="max-height: 400px; overflow-y: auto;">
      <table class="preview-table">
        <thead style="position: sticky; top: 0;">
          <tr>
            <th>#</th>
            <th>เลขที่สัญญา</th>
            <th>ชื่อลูกค้า</th>
            <th>วันที่ส่ง (Excel)</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody id="previewBody">
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex gap-3">
      <button type="button" id="backBtn" class="btn-secondary">
        <i class="fas fa-arrow-left"></i>
        กลับ
      </button>
      <button type="button" id="exportBtn" class="btn-primary" disabled>
        <i class="fas fa-download"></i>
        Export Word (<span id="exportCount">0</span> ไฟล์)
      </button>
    </div>
  </div>

  <!-- Step 3: Processing/Download -->
  <div id="step3" class="card hidden">
    <div class="text-center py-8">
      <div id="processingState">
        <i class="fas fa-spinner fa-spin text-4xl text-blue-600 mb-4"></i>
        <p class="text-lg font-medium">กำลังสร้างไฟล์ Word...</p>
        <div class="progress-bar mt-4" style="max-width: 400px; margin: 1rem auto;">
          <div class="fill" id="progressFill" style="width: 0%"></div>
        </div>
        <p class="text-sm text-slate-500" id="progressText">0%</p>
      </div>

      <div id="completeState" class="hidden">
        <i class="fas fa-check-circle text-5xl text-green-500 mb-4"></i>
        <p class="text-xl font-semibold text-green-700 mb-2">สร้างไฟล์เสร็จสิ้น!</p>
        <p class="text-slate-600 mb-4" id="completeText"></p>
        <a href="#" id="downloadLink" class="btn-primary">
          <i class="fas fa-download"></i>
          ดาวน์โหลด ZIP
        </a>
      </div>

      <div id="errorState" class="hidden">
        <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
        <p class="text-xl font-semibold text-red-700 mb-2">เกิดข้อผิดพลาด</p>
        <p class="text-slate-600" id="errorText"></p>
      </div>
    </div>

    <div class="mt-4 text-center">
      <button type="button" id="resetBtn" class="btn-secondary">
        <i class="fas fa-redo"></i>
        เริ่มใหม่
      </button>
    </div>
  </div>
</main>

<script>
const csrfToken = '<?= getCsrfToken() ?>';
let uploadedFile = null;
let matchedJobs = [];

// Upload zone events
const uploadZone = document.getElementById('uploadZone');
const fileInput = document.getElementById('excelFile');

uploadZone.addEventListener('click', () => fileInput.click());

uploadZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  uploadZone.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', () => {
  uploadZone.classList.remove('dragover');
});

uploadZone.addEventListener('drop', (e) => {
  e.preventDefault();
  uploadZone.classList.remove('dragover');
  const files = e.dataTransfer.files;
  if (files.length > 0) {
    handleFile(files[0]);
  }
});

fileInput.addEventListener('change', (e) => {
  if (e.target.files.length > 0) {
    handleFile(e.target.files[0]);
  }
});

function handleFile(file) {
  if (!file.name.match(/\.(xlsx|xls)$/i)) {
    Swal.fire('ไฟล์ไม่ถูกต้อง', 'กรุณาเลือกไฟล์ Excel (.xlsx)', 'error');
    return;
  }

  uploadedFile = file;
  document.getElementById('fileName').textContent = file.name;
  document.getElementById('fileSize').textContent = formatFileSize(file.size);
  document.getElementById('fileInfo').classList.remove('hidden');
  document.getElementById('processBtn').disabled = false;
}

document.getElementById('removeFile').addEventListener('click', () => {
  uploadedFile = null;
  fileInput.value = '';
  document.getElementById('fileInfo').classList.add('hidden');
  document.getElementById('processBtn').disabled = true;
});

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Process Excel
document.getElementById('processBtn').addEventListener('click', async () => {
  if (!uploadedFile) return;

  const formData = new FormData();
  formData.append('excel_file', uploadedFile);
  formData.append('csrf_token', csrfToken);

  try {
    Swal.fire({
      title: 'กำลังประมวลผล...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    const response = await fetch('api/process_excel_for_word.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();
    Swal.close();

    if (data.success) {
      matchedJobs = data.matched;
      showPreview(data);
    } else {
      throw new Error(data.message || 'เกิดข้อผิดพลาด');
    }
  } catch (error) {
    Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
  }
});

function showPreview(data) {
  // Update step indicators
  document.getElementById('step1-indicator').classList.remove('active');
  document.getElementById('step1-indicator').classList.add('completed');
  document.getElementById('step2-indicator').classList.add('active');

  // Update summary
  document.getElementById('totalCount').textContent = data.total;
  document.getElementById('foundCount').textContent = data.found;
  document.getElementById('notFoundCount').textContent = data.not_found;
  document.getElementById('exportCount').textContent = data.found;

  // Build preview table
  const tbody = document.getElementById('previewBody');
  tbody.innerHTML = '';

  data.items.forEach((item, index) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${index + 1}</td>
      <td>${escapeHtml(item.contract_number)}</td>
      <td>${escapeHtml(item.customer_name || '-')}</td>
      <td>${escapeHtml(item.submit_date || '-')}</td>
      <td>
        ${item.found
          ? '<span class="status-badge status-found"><i class="fas fa-check mr-1"></i>พบ</span>'
          : '<span class="status-badge status-not-found"><i class="fas fa-times mr-1"></i>ไม่พบ</span>'
        }
      </td>
    `;
    tbody.appendChild(tr);
  });

  // Toggle visibility
  document.getElementById('step1').classList.add('hidden');
  document.getElementById('step2').classList.remove('hidden');
  document.getElementById('exportBtn').disabled = data.found === 0;
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text || '';
  return div.innerHTML;
}

// Back button
document.getElementById('backBtn').addEventListener('click', () => {
  document.getElementById('step1-indicator').classList.add('active');
  document.getElementById('step1-indicator').classList.remove('completed');
  document.getElementById('step2-indicator').classList.remove('active');

  document.getElementById('step1').classList.remove('hidden');
  document.getElementById('step2').classList.add('hidden');
});

// Export Word
document.getElementById('exportBtn').addEventListener('click', async () => {
  if (matchedJobs.length === 0) return;

  // Switch to step 3
  document.getElementById('step2-indicator').classList.remove('active');
  document.getElementById('step2-indicator').classList.add('completed');
  document.getElementById('step3-indicator').classList.add('active');
  document.getElementById('step2').classList.add('hidden');
  document.getElementById('step3').classList.remove('hidden');

  // Reset states
  document.getElementById('processingState').classList.remove('hidden');
  document.getElementById('completeState').classList.add('hidden');
  document.getElementById('errorState').classList.add('hidden');

  try {
    const response = await fetch('api/export_word_zip.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ job_ids: matchedJobs })
    });

    const data = await response.json();

    if (data.success) {
      document.getElementById('progressFill').style.width = '100%';
      document.getElementById('progressText').textContent = '100%';

      setTimeout(() => {
        document.getElementById('processingState').classList.add('hidden');
        document.getElementById('completeState').classList.remove('hidden');
        document.getElementById('completeText').textContent = `สร้างไฟล์ Word ${data.count} ไฟล์เรียบร้อย`;
        document.getElementById('downloadLink').href = data.download_url;
      }, 500);
    } else {
      throw new Error(data.message || 'เกิดข้อผิดพลาด');
    }
  } catch (error) {
    document.getElementById('processingState').classList.add('hidden');
    document.getElementById('errorState').classList.remove('hidden');
    document.getElementById('errorText').textContent = error.message;
  }
});

// Reset button
document.getElementById('resetBtn').addEventListener('click', () => {
  // Reset all states
  uploadedFile = null;
  matchedJobs = [];
  fileInput.value = '';
  document.getElementById('fileInfo').classList.add('hidden');
  document.getElementById('processBtn').disabled = true;

  // Reset step indicators
  document.querySelectorAll('.step').forEach(el => {
    el.classList.remove('active', 'completed');
  });
  document.getElementById('step1-indicator').classList.add('active');

  // Show step 1
  document.getElementById('step1').classList.remove('hidden');
  document.getElementById('step2').classList.add('hidden');
  document.getElementById('step3').classList.add('hidden');

  // Reset progress
  document.getElementById('progressFill').style.width = '0%';
  document.getElementById('progressText').textContent = '0%';
});
</script>

</body>
</html>
