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

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;

        // เปลี่ยนเส้นทางตาม role
        if ($user['role'] === 'admin') {
            header("Location: ../dashboard/admin.php");
        } elseif ($user['role'] === 'field') {
            header("Location: ../dashboard/field.php");
        } else {
            echo "Role ไม่ถูกต้อง";
        }
        exit;
    } else {
        echo "<script>alert('ชื่อผู้ใช้หรือรหัสผ่านผิด');window.location.href='../index.php';</script>";
    }
}
