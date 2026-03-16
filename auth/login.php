<?php
require_once __DIR__ . '/../includes/session_config.php';
include '../config/db.php';
require_once '../includes/csrf.php';
require_once '../includes/rate_limiter.php';
require_once '../includes/ip_security.php';

// สร้างตาราง login_attempts ถ้ายังไม่มี
createLoginAttemptsTable($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ตรวจสอบ CSRF Token
    requireCsrfToken();

    $username = trim(htmlspecialchars($_POST['username']));
    $password = trim($_POST['password']);
    $remember = isset($_POST['remember']);

    $ip_address = getClientIp();
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    // ตรวจสอบ Rate Limiting
    $rateLimiter = new RateLimiter($conn);
    $lockStatus = $rateLimiter->isLocked($ip_address);

    if ($lockStatus['locked']) {
        $minutes = ceil($lockStatus['remaining'] / 60);
        $_SESSION['message'] = "🚫 คุณพยายาม login ผิดพลาดหลายครั้ง กรุณารอ {$minutes} นาที";
        header("Location: ../index.php");
        exit;
    }

    // ล้างประวัติเก่า
    $rateLimiter->cleanupOldAttempts();

    // 🔎 ตรวจสอบผู้ใช้จาก username
    $stmt = $conn->prepare("SELECT id, name, username, password, role, department_id, active, can_delete_jobs FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // 🚫 บัญชีถูกบล็อก
        if ($user['active'] == 0) {
            $_SESSION['message'] = "🚫 บัญชีนี้ถูกบล็อก กรุณาติดต่อผู้ดูแลระบบ";
            header("Location: ../index.php");
            exit;
        }

        // ✅ ตรวจสอบรหัสผ่าน
        if (password_verify($password, $user['password'])) {
            // ✅ เก็บ session
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'role' => $user['role'],
                'department_id' => $user['department_id'],
                'can_delete_jobs' => (int) $user['can_delete_jobs'],
            ];

            // ✅ เพิ่ม session แยกสำหรับ user_id
            $_SESSION['user_id'] = $user['id'];

            // ✅ ล้างประวัติ login attempts เมื่อ login สำเร็จ
            $rateLimiter->clearAttempts($ip_address);

            // ✅ บันทึก login log
            $stmt_log = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
            $stmt_log->bind_param("iss", $user['id'], $ip_address, $user_agent);
            $stmt_log->execute();
            $stmt_log->close();

            // ✅ จำฉันไว้ (90 วัน / 3 เดือน) - ใช้ random token แทน password hash
            if ($remember) {
                // สร้าง random token
                $remember_token = bin2hex(random_bytes(32));

                // บันทึก token ลง database
                $stmt_token = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt_token->bind_param("si", $remember_token, $user['id']);
                $stmt_token->execute();
                $stmt_token->close();

                // เก็บ cookie (90 วัน)
                $cookie_data = base64_encode(json_encode([
                    'id' => $user['id'],
                    'token' => $remember_token
                ]));
                setcookie('remember_user', $cookie_data, [
                    'expires' => time() + (86400 * 90), // 90 วัน
                    'path' => '/',
                    'httponly' => true,
                    'secure' => isset($_SERVER['HTTPS']),
                    'samesite' => 'Lax'
                ]);
            }

            // 👉 ส่งค่ากลับไปที่ index.php เพื่อแสดง Alert Success แล้วค่อย Redirect
            $redirect_url = "../index.php";
            switch ($user['role']) {
                case 'admin':
                    $redirect_url = "dashboard/admin.php";
                    break;
                case 'manager':
                    $redirect_url = "dashboard/manager.php";
                    break;
                case 'field':
                    $redirect_url = "dashboard/field.php";
                    break;
                default:
                    $_SESSION['message'] = "❌ บทบาทไม่ถูกต้อง";
                    header("Location: ../index.php");
                    exit;
            }

            // Redirect ไปที่ index.php พร้อม parameter
            header("Location: ../index.php?status=success&redirect=" . urlencode($redirect_url));
            exit;
        }
    }

    // ❌ รหัสผิดหรือไม่พบ user
    $rateLimiter->recordFailedAttempt($ip_address, $username);

    $lockStatus = $rateLimiter->isLocked($ip_address);
    $attemptsLeft = max(0, env('MAX_LOGIN_ATTEMPTS', 5) - $lockStatus['attempts']);

    if ($attemptsLeft > 0) {
        $_SESSION['message'] = "❌ ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง (เหลือ {$attemptsLeft} ครั้ง)";
    } else {
        $_SESSION['message'] = "🚫 คุณพยายาม login ผิดพลาดหลายครั้ง กรุณารอสักครู่";
    }

    header("Location: ../index.php");
    exit;
}
