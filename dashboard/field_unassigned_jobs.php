<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'field') {
  header("Location: ../index.php");
  exit;
}
include '../config/db.php';

$sql = "SELECT j.*, u.name as imported_by_name 
        FROM jobs j 
        LEFT JOIN users u ON j.imported_by = u.id 
        WHERE j.assigned_to IS NULL 
        ORDER BY j.due_date ASC, j.id DESC";
$jobs = $conn->query($sql);
$showType = 'unassigned'; // สำหรับ component สรุปการ์ด

/* ---------- Helpers ---------- */
function daysDiffBadge($due) {
  if ($due === null) return '<span class="status-chip neutral">ไม่ระบุ</span>';
  $raw = trim((string)$due);
  if ($raw === '') return '<span class="status-chip neutral">ไม่ระบุ</span>';
  if (preg_match('/^-?\d+$/', $raw)) {
    $d = (int)$raw;
  } else {
    $formats = ['Y-m-d','Y-m-d H:i:s','d/m/Y','d-m-Y','m/d/Y'];
    $dueDate = null;
    foreach ($formats as $f) {
      $dt = DateTime::createFromFormat($f,$raw);
      if ($dt && $dt->format($f)===$raw) { $dueDate = $dt; break; }
    }
    if (!$dueDate) {
      $ts = strtotime($raw);
      if ($ts !== false) $dueDate = (new DateTime())->setTimestamp($ts);
    }
    if (!$dueDate) return '<span class="status-chip neutral">ไม่ระบุ</span>';
    $d = (int)(new DateTime())->diff($dueDate)->format('%r%a');
  }
  
  if ($d < 0) return '<span class="status-chip danger">เกิน '.abs($d).' วัน</span>';
  if ($d === 0) return '<span class="status-chip caution">วันนี้</span>';
  if ($d <= 3) return '<span class="status-chip warning">อีก '.$d.' วัน</span>';
  return '<span class="status-chip success">อีก '.$d.' วัน</span>';
}
function isNewJob($created) { return $created ? (time()-strtotime($created) <= 24*3600) : false; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>งานของฉัน | ระบบจัดการงาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --primary: #6366f1;
      --primary-dark: #4f46e5;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --neutral: #6b7280;
      --bg-primary: #f8fafc;
      --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --card-shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: 'Prompt', sans-serif;
      background: var(--bg-gradient);
      min-height: 100vh;
      color: #1f2937;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 1rem;
    }

    /* Header */
    .header {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .header-title {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 0.5rem;
    }

    .header-subtitle {
      color: var(--neutral);
      font-weight: 400;
      font-size: 0.95rem;
    }

    /* Navigation Pills */
    .nav-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }

    .nav-pill {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.25rem;
      border-radius: 50px;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.3s ease;
      box-shadow: var(--card-shadow);
      font-size: 0.9rem;
      min-width: fit-content;
      white-space: nowrap;
    }

    .nav-pill.unassigned { background: #8b5cf6; color: white; }
    .nav-pill.assigned { background: #10b981; color: white; }
    .nav-pill.pending { background: #3b82f6; color: white; }
    .nav-pill.shipping { background: #6b7280; color: white; }
    .nav-pill.inspection { background: #f59e0b; color: white; }
    .nav-pill.completed { background: #ef4444; color: white; }

    .nav-pill:hover {
      transform: translateY(-2px);
      box-shadow: var(--card-shadow-hover);
    }

    /* Search Bar */
    .search-container {
      position: relative;
      margin-bottom: 1.5rem;
    }

    .search-input {
      width: 100%;
      padding: 1rem 1rem 1rem 3rem;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 50px;
      font-size: 1rem;
      transition: all 0.3s ease;
      box-shadow: var(--card-shadow);
    }

    .search-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1), var(--card-shadow);
    }

    .search-icon {
      position: absolute;
      left: 1.25rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--neutral);
      font-size: 1.25rem;
    }

    /* Job Cards */
    .jobs-grid {
      display: grid;
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .job-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 1.5rem;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .job-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--card-shadow-hover);
    }

    .job-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--primary);
    }

    .job-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 1rem;
    }

    .contract-info h3 {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 0.25rem;
    }

    .contract-info .location {
      color: var(--neutral);
      font-size: 0.9rem;
    }

    .new-badge {
      background: linear-gradient(45deg, #ef4444, #f97316);
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 50px;
      font-size: 0.7rem;
      font-weight: 600;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.8; }
    }

    .job-details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
      margin-bottom: 1.25rem;
    }

    .detail-item {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    .detail-label {
      font-size: 0.75rem;
      color: var(--neutral);
      font-weight: 500;
    }

    .detail-value {
      font-size: 0.9rem;
      font-weight: 600;
      color: #374151;
    }

    .job-actions {
      display: flex;
      gap: 0.75rem;
      align-items: center;
    }

    .view-btn {
      flex: 1;
      background: var(--primary);
      color: white;
      padding: 0.75rem 1.5rem;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 500;
      text-align: center;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .view-btn:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
    }

    /* Status Chips */
    .status-chip {
      padding: 0.375rem 0.875rem;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-block;
      white-space: nowrap;
    }

    .status-chip.success { background: #dcfce7; color: #166534; }
    .status-chip.warning { background: #fef3c7; color: #92400e; }
    .status-chip.caution { background: #fde68a; color: #92400e; }
    .status-chip.danger { background: #fee2e2; color: #991b1b; }
    .status-chip.neutral { background: #f1f5f9; color: var(--neutral); }

    /* No Data State */
    .no-data {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--neutral);
    }

    .no-data-icon {
      font-size: 4rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }

    /* Responsive Design */
    @media (min-width: 640px) {
      .container { padding: 1.5rem; }
      .jobs-grid { grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); }
      .header { padding: 2rem; }
      .header-title { font-size: 1.75rem; }
    }

    @media (min-width: 768px) {
      .jobs-grid { grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); }
      .nav-pills { justify-content: center; }
    }

    @media (min-width: 1024px) {
      .container { padding: 2rem; }
      .jobs-grid { grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); }
    }

    @media (max-width: 640px) {
      .nav-pills {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
      }
      
      .nav-pill {
        padding: 0.625rem 1rem;
        font-size: 0.8rem;
        justify-content: center;
      }

      .job-details {
        grid-template-columns: 1fr;
        gap: 0.5rem;
      }

      .job-header {
        flex-direction: column;
        gap: 0.75rem;
      }

      .search-input {
        padding: 0.875rem 0.875rem 0.875rem 2.5rem;
        font-size: 0.9rem;
      }

      .search-icon {
        left: 1rem;
        font-size: 1.1rem;
      }
    }

    /* Loading Animation */
    .loading {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255,255,255,.3);
      border-radius: 50%;
      border-top-color: #fff;
      animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Table for larger screens */
    .table-container {
      display: none;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 1.5rem;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(255, 255, 255, 0.2);
      overflow-x: auto;
    }

    .jobs-table {
      width: 100%;
      border-collapse: collapse;
    }

    .jobs-table th,
    .jobs-table td {
      padding: 1rem 0.75rem;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }

    .jobs-table th {
      background: #f8fafc;
      font-weight: 600;
      color: var(--neutral);
      font-size: 0.875rem;
    }

    .jobs-table tbody tr:hover {
      background: #f8fafc;
    }

    @media (min-width: 1200px) {
      .jobs-grid { display: none; }
      .table-container { display: block; }
    }
  </style>
</head>

<body>
<div class="container">
  <header class="header">
    <div class="header-title">
      <i class="fas fa-clipboard-list mr-1"></i>งานที่ยังไม่ได้รับ (<?= $jobs->num_rows ?> งาน)
    </div>
    <div class="header-subtitle">
      จัดการและติดตามงานที่รอการรับผิดชอบ
    </div>
  </header>

  <?php include '../components/field_summary_cards.php'; ?>

  <nav class="nav-pills">
    <a href="field.php" class="nav-pill unassigned"><i class="fas fa-arrow-left mr-1"></i> กลับ</a>
    <a href="../admin/map.php" target="_blank" class="nav-pill assigned"><i class="fas fa-map-marked-alt mr-1"></i> งานกลาง</a>
    <a href="../auth/logout.php" class="nav-pill completed"><i class="fas fa-sign-out-alt mr-1"></i> ออกจากระบบ</a>
  </nav>

  <div class="search-container">
    <div class="search-icon"><i class="fas fa-search"></i></div>
    <input 
      type="text" 
      id="searchJobs" 
      class="search-input"
      placeholder="ค้นหาด้วยเลขสัญญา/ชื่อ/โซน/ทะเบียน..."
    >
  </div>

  <div class="jobs-grid" id="jobsGrid">
    <?php if ($jobs->num_rows > 0): ?>
      <?php $jobs->data_seek(0); // Reset pointer ?>
      <?php while ($row = $jobs->fetch_assoc()): ?>
        <?php $isNew = isNewJob($row['created_at'] ?? null); ?>
        <div class="job-card" data-search="<?= htmlspecialchars(strtolower(($row['contract_number']??'') . ' ' . ($row['location_info']??'') . ' ' . ($row['zone']??'') . ' ' . ($row['plate']??''))) ?>">
          <div class="job-header">
            <div class="contract-info">
              <h3><?= htmlspecialchars($row['contract_number'] ?? '') ?></h3>
              <div class="location"><?= htmlspecialchars($row['location_info'] ?? '') ?></div>
            </div>
            <?php if ($isNew): ?>
              <div class="new-badge">NEW</div>
            <?php endif; ?>
          </div>

          <div class="job-details">
            <div class="detail-item">
              <div class="detail-label">พื้นที่</div>
              <div class="detail-value"><?= htmlspecialchars($row['location_area'] ?? '') ?></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">โซน</div>
              <div class="detail-value"><?= htmlspecialchars($row['zone'] ?? '') ?></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">รุ่น</div>
              <div class="detail-value"><?= htmlspecialchars($row['model_detail'] ?? '') ?></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">ทะเบียน</div>
              <div class="detail-value"><i class="fas fa-car mr-1"></i><?= htmlspecialchars($row['plate'] ?? '') ?></div>
            </div>
          </div>

          <div class="job-actions">
            <?= daysDiffBadge($row['due_date'] ?? null) ?>
            <a href="view_job.php?id=<?= $row['id'] ?>" class="view-btn">
              <i class="fas fa-eye mr-1"></i>ดูรายละเอียด
            </a>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="no-data">
        <div class="no-data-icon"><i class="fas fa-edit"></i></div>
        <h3>ไม่มีงานที่ยังไม่ได้รับ</h3>
        <p>ไม่พบงานที่รอการรับผิดชอบในขณะนี้</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="table-container">
    <table class="jobs-table" id="jobsTable">
      <thead>
        <tr>
          <th>เลขสัญญา</th>
          <th>ข้อมูลสถานที่</th>
          <th>พื้นที่ / โซน</th>
          <th>รุ่น</th>
          <th>ทะเบียน</th>
          <th>สถานะกำหนด</th>
          <th>การดำเนินการ</th>
        </tr>
      </thead>
      <tbody>
      <?php $jobs->data_seek(0); // Reset pointer ?>
      <?php while ($row = $jobs->fetch_assoc()): ?>
        <?php $isNew = isNewJob($row['created_at'] ?? null); ?>
        <tr>
          <td>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <span style="font-weight: 600; color: var(--primary);"><?= htmlspecialchars($row['contract_number'] ?? '') ?></span>
              <?php if ($isNew): ?>
                <span class="new-badge">NEW</span>
              <?php endif; ?>
            </div>
          </td>
          <td><?= htmlspecialchars($row['location_info'] ?? '') ?></td>
          <td>
            <div>
              <div style="font-weight: 500;"><?= htmlspecialchars($row['location_area'] ?? '') ?></div>
              <div style="color: var(--neutral); font-size: 0.875rem;"><?= htmlspecialchars($row['zone'] ?? '') ?></div>
            </div>
          </td>
          <td><?= htmlspecialchars($row['model_detail'] ?? '') ?></td>
          <td><i class="fas fa-car mr-1"></i><?= htmlspecialchars($row['plate'] ?? '') ?></td>
          <td><?= daysDiffBadge($row['due_date'] ?? null) ?></td>
          <td>
            <a href="view_job.php?id=<?= $row['id'] ?>" class="view-btn" style="flex: none; padding: 0.5rem 1rem;">
              <i class="fas fa-eye mr-1"></i>ดู
            </a>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
$(function () {
  // Search functionality for cards
  $('#searchJobs').on('input', function() {
    const searchTerm = this.value.toLowerCase();
    const cards = document.querySelectorAll('.job-card');
    
    cards.forEach(card => {
      const searchData = card.getAttribute('data-search');
      if (searchData.includes(searchTerm)) {
        card.style.display = 'block';
      } else {
        card.style.display = 'none';
      }
    });
  });

  // Initialize DataTable for desktop view
  if (window.innerWidth >= 1200) {
    const table = $('#jobsTable').DataTable({
      dom: '<"dt-top"l>rt<"dt-bottom"ip>',
      pageLength: 15,
      lengthMenu: [[10,15,25,50,-1], [10,15,25,50,'ทั้งหมด']],
      order: [[5, 'asc']],
      language: {
        lengthMenu: "แสดง _MENU_ รายการ",
        zeroRecords: "ไม่พบข้อมูล",
        info: "แสดง _START_–_END_ จาก _TOTAL_ รายการ",
        infoEmpty: "ไม่มีข้อมูล",
        infoFiltered: "(กรองจากทั้งหมด _MAX_ รายการ)",
        paginate: { previous: "ก่อนหน้า", next: "ถัดไป" }
      },
      responsive: true
    });

    // Connect external search to DataTable
    $('#searchJobs').on('keyup', function() {
      table.search(this.value).draw();
    });
  }

  // Add loading states to buttons
  $('.view-btn').on('click', function() {
    const btn = $(this);
    const originalText = btn.html();
    btn.html('<span class="loading"></span> กำลังโหลด...');
    
    setTimeout(() => {
      btn.html(originalText);
    }, 2000);
  });

  // Smooth animations
  $('.job-card, .nav-pill').on('mouseenter', function() {
    $(this).css('transform', 'translateY(-4px)');
  }).on('mouseleave', function() {
    $(this).css('transform', 'translateY(0)');
  });
});

// Handle window resize
$(window).on('resize', function() {
  if (window.innerWidth >= 1200) {
    $('.jobs-grid').hide();
    $('.table-container').show();
  } else {
    $('.jobs-grid').show();
    $('.table-container').hide();
  }
});
</script>

</body>
</html>