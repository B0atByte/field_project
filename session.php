<?php
// ใช้ session แบบปกติ (ไม่ใช้ secure session config ชั่วคราว)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ถ้ายังไม่มี session user → กลับหน้า login
if (!isset($_SESSION['user'])) {
    header("Location: /index.php");
    exit;
}

// อัพเดท last activity (ถ้าต้องการ timeout)
$_SESSION['last_activity'] = time();
?>
