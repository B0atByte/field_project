<?php
/**
 * Password Validation Helper
 * ตรวจสอบความแข็งแรงของรหัสผ่าน
 */

/**
 * ตรวจสอบความแข็งแรงของรหัสผ่าน
 *
 * @param string $password รหัสผ่านที่ต้องการตรวจสอบ
 * @param int $minLength ความยาวขั้นต่ำ (default: 8)
 * @param bool $requireUppercase ต้องมีตัวพิมพ์ใหญ่ (default: true)
 * @param bool $requireLowercase ต้องมีตัวพิมพ์เล็ก (default: true)
 * @param bool $requireNumbers ต้องมีตัวเลข (default: true)
 * @param bool $requireSpecialChars ต้องมีอักขระพิเศษ (default: false)
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePasswordStrength(
    string $password,
    int $minLength = 8,
    bool $requireUppercase = true,
    bool $requireLowercase = true,
    bool $requireNumbers = true,
    bool $requireSpecialChars = false
): array {
    $errors = [];

    // ตรวจสอบความยาว
    if (strlen($password) < $minLength) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย {$minLength} ตัวอักษร";
    }

    // ตรวจสอบตัวพิมพ์ใหญ่
    if ($requireUppercase && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "รหัสผ่านต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว (A-Z)";
    }

    // ตรวจสอบตัวพิมพ์เล็ก
    if ($requireLowercase && !preg_match('/[a-z]/', $password)) {
        $errors[] = "รหัสผ่านต้องมีตัวพิมพ์เล็กอย่างน้อย 1 ตัว (a-z)";
    }

    // ตรวจสอบตัวเลข
    if ($requireNumbers && !preg_match('/[0-9]/', $password)) {
        $errors[] = "รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว (0-9)";
    }

    // ตรวจสอบอักขระพิเศษ
    if ($requireSpecialChars && !preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
        $errors[] = "รหัสผ่านต้องมีอักขระพิเศษอย่างน้อย 1 ตัว (!@#$%^&*...)";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * สร้างข้อความแสดงข้อกำหนดของรหัสผ่าน (สำหรับแสดงใน UI)
 *
 * @param int $minLength
 * @param bool $requireUppercase
 * @param bool $requireLowercase
 * @param bool $requireNumbers
 * @param bool $requireSpecialChars
 * @return array
 */
function getPasswordRequirements(
    int $minLength = 8,
    bool $requireUppercase = true,
    bool $requireLowercase = true,
    bool $requireNumbers = true,
    bool $requireSpecialChars = false
): array {
    $requirements = [];

    $requirements[] = "ความยาวอย่างน้อย {$minLength} ตัวอักษร";

    if ($requireUppercase) {
        $requirements[] = "มีตัวพิมพ์ใหญ่ (A-Z)";
    }

    if ($requireLowercase) {
        $requirements[] = "มีตัวพิมพ์เล็ก (a-z)";
    }

    if ($requireNumbers) {
        $requirements[] = "มีตัวเลข (0-9)";
    }

    if ($requireSpecialChars) {
        $requirements[] = "มีอักขระพิเศษ (!@#$%...)";
    }

    return $requirements;
}

/**
 * ตัวอย่างการใช้งาน:
 *
 * $result = validatePasswordStrength('MyP@ssw0rd');
 * if (!$result['valid']) {
 *     foreach ($result['errors'] as $error) {
 *         echo $error . "\n";
 *     }
 * }
 */
?>
