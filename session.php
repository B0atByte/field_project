<?php
session_start();

// ถ้ายังไม่มี session user → กลับหน้า login
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}
?>
