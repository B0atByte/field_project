<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
if (!isset($_SESSION['user']) || !hasPermission('page_logs')) {
    http_response_code(403);
    echo '<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl"><p>ไม่มีสิทธิ์เข้าถึง</p></div>';
    exit;
}

include '../config/db.php';

$tab = $_GET['tab'] ?? 'job_edit';
$allowed_tabs = ['job_edit', 'job_deletion', 'login'];

if (!in_array($tab, $allowed_tabs)) {
    http_response_code(400);
    echo '<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl"><p>Tab ไม่ถูกต้อง</p></div>';
    exit;
}

// Generate CSRF Token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle actions based on tab
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo '<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl"><p>CSRF token validation failed</p></div>';
        exit;
    }
}

// Include appropriate content based on tab
switch ($tab) {
    case 'job_edit':
        include 'logs_partials/job_edit_logs.php';
        break;
    case 'job_deletion':
        include 'logs_partials/job_deletion_logs.php';
        break;
    case 'login':
        include 'logs_partials/login_logs.php';
        break;
    default:
        echo '<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-xl"><p>ยังไม่มีเนื้อหาสำหรับ Tab นี้</p></div>';
}
