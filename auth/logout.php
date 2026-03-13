<?php
require_once '../includes/session_config.php';
include '../config/db.php';
require_once '../includes/ip_security.php';

if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id'];
    $ip_address = getClientIp();
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    // ✅ บันทึก log logout
    $stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, type) VALUES (?, ?, ?, 'logout')");
    $stmt->bind_param("iss", $user_id, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();

    // ✅ ล้าง remember_token ใน database
    $stmt_clear = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt_clear->bind_param("i", $user_id);
    $stmt_clear->execute();
    $stmt_clear->close();
}

// ✅ ลบ session อย่างปลอดภัย
destroySession();

// ✅ ลบ cookie remember_user
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

header("Location: ../index.php");
exit;
