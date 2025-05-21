<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "field_project";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ✅ ตั้งค่าภาษาและการเข้ารหัส
$conn->set_charset("utf8mb4");
?>
