<?php
/**
 * Migration: สร้างตาราง user_permissions
 * รันครั้งเดียว แล้วลบไฟล์นี้ออก
 */
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

$results = [];
$errors  = [];

// สร้างตาราง user_permissions
$sql = "CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT NOT NULL,
    `permission` VARCHAR(100) NOT NULL,
    UNIQUE KEY `uq_user_permission` (`user_id`, `permission`),
    CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    $results[] = "สร้างตาราง user_permissions สำเร็จ";
} else {
    $errors[] = "สร้างตาราง user_permissions ล้มเหลว: " . $conn->error;
}

// Migrate ค่า can_delete_jobs เดิม → action_delete_job permission
$migrate = $conn->query("SELECT id FROM users WHERE can_delete_jobs = 1");
$migrated = 0;
if ($migrate) {
    while ($row = $migrate->fetch_assoc()) {
        $uid = $row['id'];
        $conn->query("INSERT IGNORE INTO user_permissions (user_id, permission) VALUES ({$uid}, 'action_delete_job')");
        $migrated++;
    }
    $results[] = "Migrate can_delete_jobs → action_delete_job: {$migrated} users";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ติดตั้งระบบ Permissions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
<div class="max-w-2xl w-full bg-white rounded-2xl shadow-xl p-8">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
            <i class="fas fa-key text-blue-600 text-3xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">ติดตั้งระบบ Permissions</h1>
        <p class="text-gray-600">สร้างตาราง user_permissions และ migrate ข้อมูลเดิม</p>
    </div>

    <?php foreach ($results as $r): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded mb-3">
            <p class="text-green-800 text-sm"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($r) ?></p>
        </div>
    <?php endforeach; ?>

    <?php foreach ($errors as $e): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded mb-3">
            <p class="text-red-800 text-sm"><i class="fas fa-times-circle mr-2"></i><?= htmlspecialchars($e) ?></p>
        </div>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6 mt-4">
            <h3 class="font-bold text-blue-900 mb-3"><i class="fas fa-check-circle mr-2"></i>ติดตั้งสำเร็จ!</h3>
            <ul class="text-blue-800 text-sm space-y-2">
                <li><i class="fas fa-arrow-right mr-2"></i>ไปที่หน้า <strong>จัดการผู้ใช้</strong> เพื่อตั้งค่า permissions</li>
                <li class="text-red-600 font-semibold"><i class="fas fa-trash mr-2"></i>ลบไฟล์ install_permissions.php ออกทันทีหลังจากนี้</li>
            </ul>
        </div>
        <a href="users.php" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg text-center transition">
            <i class="fas fa-users mr-2"></i>ไปที่หน้าจัดการผู้ใช้
        </a>
    <?php endif; ?>

    <div class="mt-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
        <p class="text-red-800 text-xs"><i class="fas fa-shield-alt mr-1"></i><strong>คำเตือน:</strong> ลบไฟล์นี้ออกหลังจากติดตั้งเสร็จเพื่อความปลอดภัย</p>
    </div>
</div>
</body>
</html>
