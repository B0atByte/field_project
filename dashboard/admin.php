<?php
session_start();
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>แดชบอร์ดผู้ดูแลระบบ - Field Project</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center py-10 px-4">

  <div class="w-full max-w-2xl bg-white shadow-lg rounded-xl p-6 space-y-6">
    
    <h1 class="text-2xl font-bold text-gray-800 text-center">
      📊 ยินดีต้อนรับ Admin: <span class="text-blue-600"><?= htmlspecialchars($_SESSION['user']['name']) ?></span>
    </h1>

    <div class="flex flex-col gap-4">
      <a href="../admin/import_jobs.php"
         class="block bg-blue-600 hover:bg-blue-700 text-white font-semibold text-center py-3 rounded-lg transition">
        📥 นำเข้างานจาก Excel
      </a>
      <a href="../admin/jobs.php"
         class="block bg-blue-600 hover:bg-blue-700 text-white font-semibold text-center py-3 rounded-lg transition">
        📋 ดูรายการงานทั้งหมด
      </a>
      <a href="../admin/map.php"
         class="block bg-blue-600 hover:bg-blue-700 text-white font-semibold text-center py-3 rounded-lg transition">
        🗺️ ดูงานทั้งหมดบนแผนที่
      </a>
      <a href="../admin/users.php"
         class="block bg-blue-600 hover:bg-blue-700 text-white font-semibold text-center py-3 rounded-lg transition">
        👤 จัดการผู้ใช้งาน / Field Officer
      </a>
      <a href="../auth/logout.php"
         class="block bg-red-500 hover:bg-red-600 text-white font-semibold text-center py-3 rounded-lg transition">
        🚪 ออกจากระบบ
      </a>
    </div>
  </div>

</body>
</html>
