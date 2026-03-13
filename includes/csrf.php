<?php
/**
 * CSRF Token Protection
 * ป้องกันการโจมตีแบบ Cross-Site Request Forgery
 */

// โหลด env สำหรับการตั้งค่า
require_once __DIR__ . '/../config/env.php';

// เริ่ม session ถ้ายังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * สร้าง CSRF Token ใหม่
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $length = (int)env('CSRF_TOKEN_LENGTH', 32);
    $_SESSION['csrf_token'] = bin2hex(random_bytes($length));
    $_SESSION['csrf_token_time'] = time();

    return $_SESSION['csrf_token'];
}

/**
 * ดึง CSRF Token ปัจจุบัน (หรือสร้างใหม่ถ้ายังไม่มี)
 */
function getCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // ถ้ายังไม่มี token หรือ token หมดอายุ (1 ชั่วโมง)
    if (!isset($_SESSION['csrf_token']) ||
        !isset($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > 3600) {
        return generateCsrfToken();
    }

    return $_SESSION['csrf_token'];
}

/**
 * ตรวจสอบ CSRF Token
 */
function verifyCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }

    // ตรวจสอบว่า token หมดอายุหรือไม่ (1 ชั่วโมง)
    if ((time() - $_SESSION['csrf_token_time']) > 3600) {
        return false;
    }

    // ใช้ hash_equals เพื่อป้องกัน timing attack
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Alias สำหรับ verifyCsrfToken() เพื่อความสะดวกในการใช้งาน
 */
function validateCsrfToken($token) {
    return verifyCsrfToken($token);
}

/**
 * สร้าง hidden input field สำหรับ CSRF token
 */
function csrfField() {
    $token = getCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * ตรวจสอบ CSRF token จาก POST request
 * ถ้าไม่ถูกต้อง จะ die() ทันที
 */
function requireCsrfToken() {
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        die('CSRF token validation failed. กรุณารีเฟรชหน้าและลองใหม่อีกครั้ง');
    }
}

/**
 * สร้าง meta tag สำหรับ AJAX requests
 */
function csrfMetaTag() {
    $token = getCsrfToken();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
?>
