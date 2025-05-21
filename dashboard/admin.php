<?php
session_start();
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

// ดึงจำนวนงาน
$jobs_count = $conn->query("SELECT COUNT(*) as total FROM jobs")->fetch_assoc()['total'];

// ดึงจำนวนผู้ใช้ field
$users_count = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'field'")->fetch_assoc()['total'];
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

  <div class="w-full max-w-4xl bg-white shadow-xl rounded-xl p-8 space-y-8">

    <h1 class="text-3xl font-bold text-center text-gray-800">
      📊 ยินดีต้อนรับ Admin: <span class="text-blue-600"><?= htmlspecialchars($_SESSION['user']['name']) ?></span>
    </h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- การนำเข้างาน -->
      <a href="../admin/import_jobs.php" class="block p-6 rounded-lg bg-gradient-to-r from-blue-500 to-indigo-600 text-white shadow hover:shadow-xl transition">
        <div class="text-xl font-semibold mb-2">📥 นำเข้างานจาก Excel</div>
        <p class="text-sm opacity-90">อัปโหลดและเพิ่มงานลงระบบโดยใช้ไฟล์ Excel</p>
      </a>

      <!-- รายการงานทั้งหมด -->
      <a href="../admin/jobs.php" class="block p-6 rounded-lg bg-gradient-to-r from-green-500 to-teal-600 text-white shadow hover:shadow-xl transition">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xl font-semibold">📋 รายการงานทั้งหมด</div>
          <span class="bg-white text-green-600 font-bold px-3 py-1 rounded-full text-sm"><?= $jobs_count ?> งาน</span>
        </div>
        <p class="text-sm opacity-90">ดูงานทั้งหมดที่มีในระบบ</p>
      </a>

      <!-- แผนที่ -->
      <a href="../admin/map.php" class="block p-6 rounded-lg bg-gradient-to-r from-yellow-500 to-orange-500 text-white shadow hover:shadow-xl transition">
        <div class="text-xl font-semibold mb-2">🗺️ ดูงานบนแผนที่</div>
        <p class="text-sm opacity-90">ดูตำแหน่งของงานทั้งหมดที่มีการระบุตำแหน่ง GPS</p>
      </a>

      <!-- จัดการผู้ใช้งาน -->
      <a href="../admin/users.php" class="block p-6 rounded-lg bg-gradient-to-r from-purple-500 to-pink-500 text-white shadow hover:shadow-xl transition">
        <div class="flex items-center justify-between mb-2">
          <div class="text-xl font-semibold">👤 จัดการผู้ใช้งาน</div>
          <span class="bg-white text-purple-600 font-bold px-3 py-1 rounded-full text-sm"><?= $users_count ?> คน</span>
        </div>
        <p class="text-sm opacity-90">เพิ่ม แก้ไข หรือจัดการ Field Officer</p>
      </a>
    </div>

    <div class="text-center pt-4">
      <a href="../auth/logout.php" class="inline-block px-6 py-3 rounded-lg bg-red-500 hover:bg-red-600 text-white font-semibold transition">
        🚪 ออกจากระบบ
      </a>
    </div>

  </div>

</body>
</html>
