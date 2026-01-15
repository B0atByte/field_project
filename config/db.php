<?php
// โหลด Environment Variables
require_once __DIR__ . '/env.php';

// ดึงค่าจาก .env file
$host = env('DB_HOST', 'localhost');
$user = env('DB_USER', 'root');
$pass = env('DB_PASS', '');
$db   = env('DB_NAME', 'field_project');

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
?>