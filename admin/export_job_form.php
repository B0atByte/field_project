<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!in_array($_SESSION['user']['role'], ['admin', 'field'])) {
    die("Unauthorized");
}
$jobId = $_GET['id'] ?? null;
if (!$jobId) die("ไม่พบงาน");
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>กรอกข้อมูลเพื่อ Export</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

  <!-- ฟอร์มส่งไป PDF -->
  <form id="exportForm" action="export_job_detail_pdf.php" method="post" target="exportFrame" class="bg-white p-6 rounded shadow w-full max-w-md">
    <input type="hidden" name="job_id" value="<?= htmlspecialchars($jobId) ?>">
    <h2 class="text-xl font-semibold mb-4">📝 ข้อมูลก่อน Export PDF</h2>

    <label class="block mb-2">📍 ทีมที่ไปปฏิบัติงาน:</label>
    <input name="team" type="text" required class="w-full mb-4 px-4 py-2 border rounded">

    <label class="block mb-2">💰 ค้างชำระปัจจุบัน (เช่น: 2 งวด, 24,000.00):</label>
    <input name="outstanding" type="text" required class="w-full mb-4 px-4 py-2 border rounded">

    <div class="flex justify-between mt-6">
      <a href="jobs.php?id=<?= htmlspecialchars($jobId) ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
        🔙 กลับ
      </a>
      <button type="submit" onclick="onExport()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
        📤 สร้าง PDF
      </button>
    </div>
  </form>

  <!-- iframe สำหรับดาวน์โหลด pdf -->
  <iframe name="exportFrame" style="display:none;"></iframe>

  <!-- script redirect -->
  <script>
    function onExport() {
      setTimeout(() => {
        window.location.href = "jobs.php?id=<?= htmlspecialchars($jobId) ?>";
      }, 2000); // 2 วิหลังส่งออก pdf
    }
  </script>

</body>
</html>
