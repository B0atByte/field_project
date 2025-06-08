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

            // ✅ ส่งตาม role
            switch ($user['role']) {
                case 'admin':
                    header("Location: ../dashboard/admin.php");
                    break;
                case 'field':
                    header("Location: ../dashboard/field.php");
                    break;
                case 'manager':
                    header("Location: ../dashboard/manager.php"); // หรือใช้ admin.php ถ้ายังไม่มี
                    break;
                default:
                    $_SESSION['message'] = "❌ ไม่สามารถเข้าสู่ระบบได้: บทบาทไม่ถูกต้อง";
                    header("Location: ../index.php");
                    break;
            }
            exit;
        }
    }

    // 🔒 แจ้งเตือนกรณีไม่พบผู้ใช้หรือรหัสผ่านผิด
    $_SESSION['message'] = "❌ ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    header("Location: ../index.php");
    exit;
}
