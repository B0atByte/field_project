<?php
session_start();
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // ❌ เช็คสถานะบล็อกก่อน
        if ($user['active'] == 0) {
            $_SESSION['message'] = "🚫 บัญชีนี้ถูกบล็อก กรุณาติดต่อผู้ดูแลระบบ";
            header("Location: ../index.php");
            exit;
        }

        // ✅ ตรวจสอบรหัสผ่าน
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;

            if ($user['role'] === 'admin') {
                header("Location: ../dashboard/admin.php");
            } elseif ($user['role'] === 'field') {
                header("Location: ../dashboard/field.php");
            } else {
                echo "ไม่สามารถเข้าสู่ระบบได้: ไม่มี role ที่รองรับ";
            }
            exit;
        }
    }

    // 🔒 แจ้งเตือนกรณีไม่พบผู้ใช้หรือรหัสผ่านผิด
    $_SESSION['message'] = "❌ ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    header("Location: ../index.php");
    exit;
}
