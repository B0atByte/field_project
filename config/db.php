<?php
// โหลด Environment Variables
require_once __DIR__ . '/env.php';

// ดึงค่าจาก .env file
// ✅ จุดที่แก้ 1: เปลี่ยนจาก 'localhost' เป็น 'mysql' (เพื่อให้ตรงกับชื่อ Service ใน docker-compose)
$host = env('DB_HOST', 'db');

// ✅ จุดที่แก้ 2: ใส่รหัสผ่าน 'Zxcv1234' ให้ตรงกับที่คุณตั้งใน MySQL
$pass = env('DB_PASS', '    ');

$user = env('DB_USER', 'root');
$db = env('DB_NAME', 'field_project');

// สร้าง connection
$conn = new mysqli($host, $user, $pass, $db);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    // ไม่แสดง error message โดยตรง - ป้องกัน information disclosure
    error_log("Database connection failed: " . $conn->connect_error);

    if (env('APP_DEBUG', false)) {
        die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
    } else {
        die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาติดต่อผู้ดูแลระบบ");
    }
}

// ตั้งค่าภาษา/เข้ารหัส
$conn->set_charset("utf8mb4");

// ตั้งค่า Timezone เป็นเวลาไทย (GMT+7)
date_default_timezone_set('Asia/Bangkok');
$conn->query("SET time_zone = '+07:00'");
?>