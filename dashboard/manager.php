<?php
session_start();
if ($_SESSION['user']['role'] !== 'manager') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>👨‍💼 แดชบอร์ดหัวหน้างาน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-white min-h-screen p-6">
  <div class="max-w-5xl mx-auto bg-white shadow-2xl rounded-2xl p-8 space-y-8">
    <div class="flex justify-between items-center">
      <h1 class="text-3xl font-bold text-blue-800">👨‍💼 หัวหน้างาน: <?= htmlspecialchars($_SESSION['user']['name']) ?></h1>
      <a href="../auth/logout.php" class="text-red-600 hover:underline">🚪 ออกจากระบบ</a>
    </div>

    <div class="grid gap-6 grid-cols-1 md:grid-cols-3">
      <a href="../admin/jobs.php" class="bg-blue-500 hover:bg-blue-600 text-white p-6 rounded-xl shadow text-center text-lg font-semibold">
        📋 ดูงานทั้งหมด
      </a>
      <a href="../admin/import_jobs.php" class="bg-purple-500 hover:bg-purple-600 text-white p-6 rounded-xl shadow text-center text-lg font-semibold">
        ⬆️ เพิ่มงานภาคสนาม (Excel)
      </a>
      <a href="../admin/users.php" class="bg-green-500 hover:bg-green-600 text-white p-6 rounded-xl shadow text-center text-lg font-semibold">
        👥 จัดการผู้ใช้งาน
      </a>
    </div>

    <div class="text-sm text-gray-500 text-right pt-6 border-t">
      Field Project © <?= date('Y') ?>
    </div>
  </div>
</body>
</html>
