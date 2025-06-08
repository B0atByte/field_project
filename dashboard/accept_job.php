<?php
session_start();
if ($_SESSION['user']['role'] !== 'field') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$user_id = $_SESSION['user']['id'];
$job_id = $_GET['id'] ?? null;

if ($job_id) {
    $stmt = $conn->prepare("UPDATE jobs SET assigned_to = ?, status = 'pending' WHERE id = ? AND assigned_to IS NULL");
    $stmt->bind_param("ii", $user_id, $job_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        header("Location: view_job.php?id=" . $job_id);
    } else {
        echo "<script>
          alert('❌ รับงานไม่สำเร็จ (มีคนรับไปแล้ว?)');
          window.location.href = 'field.php';
        </script>";
    }
    exit;
} else {
    echo "ไม่พบ ID งาน";
}
