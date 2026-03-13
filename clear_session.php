<?php
/**
 * ล้าง Session ทั้งหมดและเริ่มใหม่
 */

// ทำลาย session เก่า
session_start();
session_unset();
session_destroy();

// ลบ cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// ลบ remember_user cookie
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>ล้าง Session</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
        }
        h1 { color: #10b981; margin: 0 0 20px; }
        p { color: #666; line-height: 1.6; }
        .btn {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 5px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .btn-success {
            background: #10b981;
        }
        .btn-success:hover {
            background: #059669;
        }
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 3;
            stroke: #10b981;
            stroke-miterlimit: 10;
            margin: 20px auto;
            box-shadow: inset 0px 0px 0px #10b981;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 3;
            stroke-miterlimit: 10;
            stroke: #10b981;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        @keyframes stroke {
            100% { stroke-dashoffset: 0; }
        }
        @keyframes scale {
            0%, 100% { transform: none; }
            50% { transform: scale3d(1.1, 1.1, 1); }
        }
        @keyframes fill {
            100% { box-shadow: inset 0px 0px 0px 30px #10b981; }
        }
    </style>
</head>
<body>
    <div class="card">
        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
            <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
        </svg>

        <h1>✅ ล้าง Session สำเร็จ!</h1>
        <p>Session และ Cookies ทั้งหมดถูกลบออกแล้ว<br>ตอนนี้คุณสามารถ login ใหม่ได้</p>

        <div style="margin-top: 30px;">
            <a href="index.php" class="btn btn-success">🔑 ไปหน้า Login</a>
            <a href="check_session.php" class="btn">🔍 ตรวจสอบ Session</a>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <small style="color: #9ca3af;">
                ถ้ายังมีปัญหา ให้ลองปิดเบราว์เซอร์ทั้งหมดแล้วเปิดใหม่
            </small>
        </div>
    </div>
</body>
</html>
```
