<?php
/**
 * Secure Session Configuration
 * การตั้งค่า Session ที่ปลอดภัย
 */

// ป้องกันการเรียกใช้ซ้ำ
if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

// โหลด environment variables
require_once __DIR__ . '/../config/env.php';

// Security: IP Whitelist Check
require_once __DIR__ . '/ip_security.php';
// เชื่อมต่อฐานข้อมูลเพื่อตรวจสอบ IP
if (!isset($conn)) {
    require_once __DIR__ . '/../config/db.php';
}

// ตรวจสอบสิทธิ์การเข้าถึงผ่าน IP Check
// สามารถข้ามการตรวจสอบได้โดยการประกาศ constant SKIP_IP_CHECK = true (สำหรับหน้า Setup)
if ((!defined('SKIP_IP_CHECK') || !SKIP_IP_CHECK) && !checkIpAccess($conn)) {
    // บันทึก log การพยายามเข้าถึง (Optional)
    error_log("Access Denied for IP: " . getClientIp());

    // ส่ง HTTP 403 Forbidden
    header('HTTP/1.1 403 Forbidden');
    echo "<!DOCTYPE html>
    <html>
    <head><title>Access Denied</title><script src='https://cdn.tailwindcss.com'></script></head>
    <body class='bg-gray-100 h-screen flex items-center justify-center'>
        <div class='bg-white p-8 rounded-lg shadow-md max-w-md text-center'>
            <svg class='w-16 h-16 text-red-500 mx-auto mb-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg>
            <h1 class='text-2xl font-bold text-gray-800 mb-2'>Access Denied</h1>
            <p class='text-gray-600'>Your IP address (<span class='font-mono bg-gray-200 px-1 rounded'>" . htmlspecialchars(getClientIp()) . "</span>) is not authorized to access this system.</p>
        </div>
    </body>
    </html>";
    exit;
}

// ตั้งค่า session cookie parameters ก่อน session_start()
$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
$sessionLifetime = (int) env('SESSION_LIFETIME', 2592000); // 30 วัน default

// ตั้งค่า gc_maxlifetime ให้ตรงกับ session lifetime
// ป้องกัน PHP garbage collector ลบ session file ก่อนเวลา (default เดิมคือ 24 นาที)
ini_set('session.gc_maxlifetime', $sessionLifetime);

session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'domain' => '', // ปล่อยว่างเพื่อให้ใช้ Host ปัจจุบัน (รองรับทั้ง IP และ localhost)
    'secure' => $isSecure,        // ใช้ HTTPS เท่านั้น
    'httponly' => true,            // ป้องกัน JavaScript access
    'samesite' => 'Lax'            // เปลี่ยนเป็น Lax เพื่อรองรับการ Redirect ทั่วไปได้ดีกว่า
]);

// ตั้งชื่อ session ที่ไม่เปิดเผย
session_name('FIELD_PROJECT_SESSION');

// เริ่ม session
session_start();

// Session Hijacking Protection
if (!isset($_SESSION['initialized'])) {
    session_regenerate_id(true);
    $_SESSION['initialized'] = true;
    $_SESSION['created_at'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
} else {
    // ตรวจสอบ User Agent (ป้องกัน Session Hijacking)
    $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentUserAgent) {
        // User Agent เปลี่ยน - อาจถูก hijack
        session_unset();
        session_destroy();
        header("Location: /index.php?error=session_invalid");
        exit;
    }

    // ตรวจสอบ session timeout (ปรับเป็น 7 วัน สำหรับ user ที่ไม่ได้ใช้งานนาน)
    if (isset($_SESSION['last_activity'])) {
        $inactiveTime = time() - $_SESSION['last_activity'];
        // เพิ่มเวลา timeout เป็น 7 วัน (604800 วินาที) - ถ้าไม่ได้ใช้งาน 7 วันถึงจะหมดอายุ
        if ($inactiveTime > 604800) {
            // Session หมดอายุ
            session_unset();
            session_destroy();
            header("Location: /index.php?error=session_timeout");
            exit;
        }
    }

    // Regenerate session ID ทุก 2 ชั่วโมง (ป้องกัน session fixation แต่ไม่บ่อยเกินไป)
    if (!isset($_SESSION['last_regenerate'])) {
        $_SESSION['last_regenerate'] = time();
    }

    $timeSinceRegenerate = time() - $_SESSION['last_regenerate'];
    if ($timeSinceRegenerate > 7200) { // 2 hours
        session_regenerate_id(true);
        $_SESSION['last_regenerate'] = time();
    }
}

// อัพเดท last activity time
$_SESSION['last_activity'] = time();

/* ---------- Auto Login (Remember Me) ---------- */
if (!isset($_SESSION['user']) && isset($_COOKIE['remember_user'])) {
    $cookie_data = json_decode(base64_decode($_COOKIE['remember_user']), true);

    if (is_array($cookie_data) && isset($cookie_data['id'], $cookie_data['token'])) {
        $chk_id = $cookie_data['id'];
        $chk_token = $cookie_data['token'];

        // Include DB if not connected
        if (!isset($conn)) {
            $db_file = __DIR__ . '/../config/db.php';
            if (file_exists($db_file))
                include $db_file;
        }

        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("SELECT id, name, username, role, department_id, active, can_delete_jobs, remember_token FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $chk_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $u = $res->fetch_assoc();
                $stmt->close();

                if ($u && $u['active'] == 1 && $u['remember_token'] === $chk_token) {
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id' => $u['id'],
                        'name' => $u['name'],
                        'username' => $u['username'],
                        'role' => $u['role'],
                        'department_id' => $u['department_id'],
                        'can_delete_jobs' => (int) ($u['can_delete_jobs'] ?? 0),
                    ];
                    $_SESSION['user_id'] = $u['id'];
                    $_SESSION['initialized'] = true;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                }
            }
        }
    }
}

/**
 * ฟังก์ชันสำหรับตรวจสอบ session ว่ายังใช้งานได้หรือไม่
 */
function isSessionValid()
{
    return isset($_SESSION['initialized']) && isset($_SESSION['user']);
}

/**
 * ทำลาย session อย่างปลอดภัย
 */
function destroySession()
{
    $_SESSION = [];

    // ลบ session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
?>