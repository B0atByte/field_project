<?php
/**
 * Simple Environment Variables Loader
 * โหลดค่าจากไฟล์ .env
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // ข้ามบรรทัดที่เป็น comment
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // แยก key=value
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // ลบ quotes ถ้ามี
        if (preg_match('/^"(.+)"$/', $value, $matches) || preg_match("/^'(.+)'$/", $value, $matches)) {
            $value = $matches[1];
        }

        // ตรวจสอบว่ามี Environment Variable นี้อยู่แล้วหรือไม่ (เช่นมาจาก Docker หรือ OS)
        if (getenv($name) !== false) {
            continue;
        }

        // เซ็ตเป็น environment variable
        if (!array_key_exists($name, $_ENV)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    return true;
}

function env($key, $default = null) {
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    // แปลงค่าพิเศษ
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }

    return $value;
}

// โหลด .env file
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);
?>
