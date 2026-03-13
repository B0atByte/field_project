<?php
/**
 * ไฟล์ทดสอบการตั้งค่าระบบ
 * เปิดไฟล์นี้ในเบราว์เซอร์เพื่อดู error
 */

// เปิด error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 System Configuration Test</h1>";
echo "<style>body { font-family: Arial; padding: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>";

// 1. ทดสอบ PHP Version
echo "<h2>1. PHP Version</h2>";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "<p class='success'>✅ PHP Version: " . PHP_VERSION . " (OK)</p>";
} else {
    echo "<p class='error'>❌ PHP Version: " . PHP_VERSION . " (ต้องการ >= 7.4)</p>";
}

// 2. ทดสอบ PHP Extensions
echo "<h2>2. PHP Extensions</h2>";
$required_extensions = ['mysqli', 'fileinfo', 'mbstring', 'json', 'session'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p class='success'>✅ {$ext} loaded</p>";
    } else {
        echo "<p class='error'>❌ {$ext} NOT loaded</p>";
    }
}

// 3. ทดสอบ Environment Variables
echo "<h2>3. Environment Variables</h2>";
try {
    require_once 'config/env.php';
    echo "<p class='success'>✅ env.php โหลดได้</p>";

    if (file_exists('.env')) {
        echo "<p class='success'>✅ ไฟล์ .env มีอยู่</p>";

        $db_host = env('DB_HOST');
        $db_user = env('DB_USER');
        $db_name = env('DB_NAME');

        echo "<p class='info'>📋 DB_HOST: " . ($db_host ?: 'ไม่พบค่า') . "</p>";
        echo "<p class='info'>📋 DB_USER: " . ($db_user ?: 'ไม่พบค่า') . "</p>";
        echo "<p class='info'>📋 DB_NAME: " . ($db_name ?: 'ไม่พบค่า') . "</p>";
    } else {
        echo "<p class='error'>❌ ไม่พบไฟล์ .env</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error loading env.php: " . $e->getMessage() . "</p>";
}

// 4. ทดสอบ Database Connection
echo "<h2>4. Database Connection</h2>";
try {
    require_once 'config/db.php';
    if ($conn && $conn->ping()) {
        echo "<p class='success'>✅ เชื่อมต่อฐานข้อมูลสำเร็จ</p>";

        // ทดสอบ query
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p class='success'>✅ พบ Users จำนวน: " . $row['count'] . " คน</p>";
        }

        // ตรวจสอบตาราง
        $tables = ['users', 'jobs', 'job_logs', 'login_logs', 'login_attempts'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '{$table}'");
            if ($result && $result->num_rows > 0) {
                echo "<p class='success'>✅ ตาราง {$table} มีอยู่</p>";
            } else {
                echo "<p class='error'>❌ ไม่พบตาราง {$table}</p>";
            }
        }
    } else {
        echo "<p class='error'>❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Database Error: " . $e->getMessage() . "</p>";
}

// 5. ทดสอบ Session
echo "<h2>5. Session</h2>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "<p class='success'>✅ Session started successfully</p>";
    echo "<p class='info'>📋 Session ID: " . session_id() . "</p>";
    echo "<p class='info'>📋 Session Save Path: " . session_save_path() . "</p>";

    // ทดสอบเขียน session
    $_SESSION['test'] = 'test_value';
    if (isset($_SESSION['test'])) {
        echo "<p class='success'>✅ สามารถเขียน Session ได้</p>";
    } else {
        echo "<p class='error'>❌ ไม่สามารถเขียน Session ได้</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Session Error: " . $e->getMessage() . "</p>";
}

// 6. ทดสอบ File Permissions
echo "<h2>6. File Permissions</h2>";
$paths_to_check = [
    'uploads/job_photos' => 'Upload directory',
    'config' => 'Config directory',
    '.env' => '.env file'
];

foreach ($paths_to_check as $path => $name) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $writable = is_writable($path);

        if ($writable || $path === '.env') {
            echo "<p class='success'>✅ {$name}: {$perms} " . ($writable ? '(writable)' : '(read-only)') . "</p>";
        } else {
            echo "<p class='error'>❌ {$name}: {$perms} (NOT writable)</p>";
        }
    } else {
        echo "<p class='error'>❌ {$name}: ไม่พบ</p>";
    }
}

// 7. ทดสอบ CSRF Functions
echo "<h2>7. CSRF Token</h2>";
try {
    require_once 'includes/csrf.php';
    $token = getCsrfToken();
    if ($token) {
        echo "<p class='success'>✅ CSRF Token สร้างได้: " . substr($token, 0, 20) . "...</p>";
    } else {
        echo "<p class='error'>❌ ไม่สามารถสร้าง CSRF Token ได้</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ CSRF Error: " . $e->getMessage() . "</p>";
}

// 8. ทดสอบ Rate Limiter
echo "<h2>8. Rate Limiter</h2>";
try {
    require_once 'includes/rate_limiter.php';
    if (isset($conn)) {
        createLoginAttemptsTable($conn);
        echo "<p class='success'>✅ ตาราง login_attempts สร้างได้</p>";

        $rateLimiter = new RateLimiter($conn);
        echo "<p class='success'>✅ RateLimiter class ทำงานได้</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Rate Limiter Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>✅ Summary</h2>";
echo "<p>ถ้าทุกอย่างเป็น ✅ แสดงว่าระบบพร้อมใช้งาน</p>";
echo "<p>ถ้ามี ❌ ให้แก้ไขตามที่แสดง</p>";
echo "<hr>";
echo "<p><a href='index.php'>← กลับหน้า Login</a></p>";
?>
