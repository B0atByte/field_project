<?php
/**
 * Installation Script for System Settings
 * ติดตั้งตาราง system_settings และ migrate API key เดิม
 *
 * คำเตือน: ลบไฟล์นี้หลังจากติดตั้งเสร็จแล้ว
 */

require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

$results = [];
$errors = [];

// ขั้นตอนที่ 1: สร้างตาราง system_settings
try {
    $sql = "CREATE TABLE IF NOT EXISTS `system_settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `setting_key` varchar(100) NOT NULL,
      `setting_value` text DEFAULT NULL,
      `description` varchar(255) DEFAULT NULL,
      `is_encrypted` tinyint(1) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        $results[] = "✅ สร้างตาราง system_settings สำเร็จ";
    }
} catch (Exception $e) {
    $errors[] = "❌ สร้างตาราง system_settings ล้มเหลว: " . $e->getMessage();
}

// ขั้นตอนที่ 2: ตรวจสอบว่ามี API key เดิมอยู่ในฐานข้อมูลหรือไม่
try {
    $check = $conn->query("SELECT COUNT(*) as count FROM system_settings WHERE setting_key = 'GOOGLE_MAPS_API_KEY'");
    $row = $check->fetch_assoc();

    if ($row['count'] == 0) {
        // ใส่ API key เดิมที่ hardcode ไว้
        $old_api_key = 'AIzaSyB3NfHFEyJb3yltga-dX0C23jsLEAQpORc';

        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        $desc = 'Google Maps JavaScript API Key for map display';
        $stmt->bind_param("sss", $key, $old_api_key, $desc);
        $key = 'GOOGLE_MAPS_API_KEY';

        if ($stmt->execute()) {
            $results[] = "✅ บันทึก Google Maps API Key เดิมลงฐานข้อมูลสำเร็จ";
            $results[] = "🔑 API Key: " . substr($old_api_key, 0, 20) . "...";
        }
    } else {
        $results[] = "ℹ️ Google Maps API Key มีอยู่ในระบบแล้ว";

        // แสดง API key ปัจจุบัน
        $current = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'GOOGLE_MAPS_API_KEY'");
        $current_row = $current->fetch_assoc();
        if ($current_row) {
            $results[] = "🔑 API Key ปัจจุบัน: " . substr($current_row['setting_value'], 0, 20) . "...";
        }
    }
} catch (Exception $e) {
    $errors[] = "❌ บันทึก API key ล้มเหลว: " . $e->getMessage();
}

// ขั้นตอนที่ 3: ทดสอบฟังก์ชัน getSetting()
try {
    require_once '../config/env.php';
    $test_key = getSetting('GOOGLE_MAPS_API_KEY', '');

    if (!empty($test_key)) {
        $results[] = "✅ ทดสอบฟังก์ชัน getSetting() สำเร็จ";
        $results[] = "🔑 ดึง API Key ได้: " . substr($test_key, 0, 20) . "...";
    } else {
        $errors[] = "⚠️ ฟังก์ชัน getSetting() ดึง API key ไม่ได้ (ค่าว่าง)";
    }
} catch (Exception $e) {
    $errors[] = "❌ ทดสอบฟังก์ชัน getSetting() ล้มเหลว: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตั้งระบบ Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                <i class="fas fa-cog text-blue-600 text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">ติดตั้งระบบ Settings</h1>
            <p class="text-gray-600">ตรวจสอบและติดตั้งตาราง system_settings</p>
        </div>

        <!-- Results -->
        <?php if (!empty($results)): ?>
            <div class="mb-6 space-y-2">
                <?php foreach ($results as $result): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <p class="text-green-800 text-sm"><?php echo htmlspecialchars($result); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 space-y-2">
                <?php foreach ($errors as $error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <p class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Success Actions -->
        <?php if (empty($errors)): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                <h3 class="font-bold text-blue-900 mb-3 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    การติดตั้งเสร็จสมบูรณ์!
                </h3>
                <p class="text-blue-800 text-sm mb-4">
                    ระบบได้ติดตั้งและ migrate API key เดิมเรียบร้อยแล้ว คุณสามารถ:
                </p>
                <ul class="text-blue-800 text-sm space-y-2 mb-4">
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right mt-1 mr-2"></i>
                        <span>ไปที่หน้า <strong>ตั้งค่าระบบ</strong> เพื่อแก้ไข API key</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right mt-1 mr-2"></i>
                        <span>ทดสอบแผนที่ว่าทำงานได้แล้ว</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-trash mt-1 mr-2 text-red-500"></i>
                        <span><strong class="text-red-600">ลบไฟล์นี้ (install_settings.php) ออกเพื่อความปลอดภัย</strong></span>
                    </li>
                </ul>
            </div>

            <div class="flex gap-3">
                <a href="settings.php" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg text-center transition">
                    <i class="fas fa-cog mr-2"></i>
                    ไปที่หน้าตั้งค่า
                </a>
                <a href="map.php" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-6 rounded-lg text-center transition">
                    <i class="fas fa-map-marked-alt mr-2"></i>
                    ทดสอบแผนที่
                </a>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                <h3 class="font-bold text-yellow-900 mb-3 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    มีข้อผิดพลาดเกิดขึ้น
                </h3>
                <p class="text-yellow-800 text-sm mb-4">
                    กรุณาตรวจสอบ error ด้านบนและลองอีกครั้ง หรือติดต่อผู้ดูแลระบบ
                </p>
            </div>

            <div class="flex gap-3">
                <button onclick="location.reload()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition">
                    <i class="fas fa-redo mr-2"></i>
                    ลองอีกครั้ง
                </button>
                <a href="../dashboard/admin.php" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-6 rounded-lg text-center transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    กลับหน้าหลัก
                </a>
            </div>
        <?php endif; ?>

        <!-- Security Warning -->
        <div class="mt-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
            <p class="text-red-800 text-xs">
                <i class="fas fa-shield-alt mr-1"></i>
                <strong>คำเตือน:</strong> ลบไฟล์ install_settings.php ออกทันทีหลังจากติดตั้งเสร็จ เพื่อป้องกันการเข้าถึงโดยไม่ได้รับอนุญาต
            </p>
        </div>
    </div>
</body>
</html>
