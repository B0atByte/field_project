<?php
/**
 * สคริปต์แก้ไขปัญหา Session ด่วน
 * เปิดไฟล์นี้เพื่อปิด Secure Session Config ชั่วคราว
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 แก้ไขปัญหา Session</h1>";
echo "<style>body { font-family: Arial; padding: 20px; } .success { color: green; } .error { color: red; }</style>";

$session_config = __DIR__ . '/includes/session_config.php';
$session_backup = __DIR__ . '/includes/session_config.php.disabled';

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'disable') {
        // ปิดการใช้งาน secure session
        if (file_exists($session_config)) {
            if (rename($session_config, $session_backup)) {
                echo "<p class='success'>✅ ปิดการใช้งาน Secure Session Config แล้ว</p>";
                echo "<p>ระบบจะใช้ session แบบปกติ (ไม่มี security features พิเศษ)</p>";
            } else {
                echo "<p class='error'>❌ ไม่สามารถปิดการใช้งานได้ ตรวจสอบ file permissions</p>";
            }
        } else {
            echo "<p>Secure Session Config ถูกปิดอยู่แล้ว</p>";
        }
    } elseif ($action === 'enable') {
        // เปิดการใช้งาน secure session กลับ
        if (file_exists($session_backup)) {
            if (rename($session_backup, $session_config)) {
                echo "<p class='success'>✅ เปิดการใช้งาน Secure Session Config แล้ว</p>";
                echo "<p>ระบบจะใช้ session แบบปลอดภัย</p>";
            } else {
                echo "<p class='error'>❌ ไม่สามารถเปิดการใช้งานได้</p>";
            }
        } else {
            echo "<p>Secure Session Config เปิดอยู่แล้ว</p>";
        }
    }
}

echo "<hr>";
echo "<h2>สถานะปัจจุบัน:</h2>";

if (file_exists($session_config)) {
    echo "<p class='success'>🔒 Secure Session Config: <strong>เปิดใช้งาน</strong></p>";
    echo "<p><a href='?action=disable' style='background: #dc2626; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>ปิดการใช้งาน (แก้ไขปัญหา)</a></p>";
} else {
    echo "<p class='error'>🔓 Secure Session Config: <strong>ปิดการใช้งาน</strong></p>";
    echo "<p><a href='?action=enable' style='background: #16a34a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>เปิดการใช้งานกลับ</a></p>";
}

echo "<hr>";
echo "<h2>คำแนะนำ:</h2>";
echo "<ol>";
echo "<li>ถ้าเข้าระบบไม่ได้ หรือมีปัญหา session → <strong>ปิดการใช้งาน</strong> Secure Session Config</li>";
echo "<li>หลังจากแก้ไขปัญหาแล้ว → ลองเข้าระบบดู</li>";
echo "<li>ถ้าทำงานได้ปกติแล้ว → <strong>เปิดการใช้งาน</strong> กลับเพื่อความปลอดภัย</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='test_config.php'>🔍 ทดสอบการตั้งค่าระบบ</a> | <a href='index.php'>← กลับหน้า Login</a></p>";
?>
