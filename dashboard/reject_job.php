<?php
require_once __DIR__ . '/../includes/session_config.php';
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$job_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user']['id'] ?? null;

if (!$job_id || !$user_id) {
    die("ข้อมูลไม่ครบ");
}

// ตรวจสอบว่าเป็นเจ้าของงานหรือไม่
$stmt = $conn->prepare("SELECT assigned_to FROM jobs WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$stmt->bind_result($assigned_to);
$stmt->fetch();
$stmt->close();

if ($assigned_to != $user_id) {
    die("คุณไม่มีสิทธิ์ปฏิเสธงานนี้");
}

// รีเซ็ตการมอบหมายงาน
$stmt = $conn->prepare("UPDATE jobs SET assigned_to = NULL, status = 'pending' WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$stmt->close();

header("Location: field.php");
exit;
