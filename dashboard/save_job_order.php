<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'field') {
  exit('unauthorized');
}

include '../config/db.php';

$order = $_POST['order'] ?? [];

if (!is_array($order)) exit('invalid');

$userId = $_SESSION['user']['id'];

foreach ($order as $i => $jobId) {
  $stmt = $conn->prepare("UPDATE jobs SET job_order = ? WHERE id = ? AND assigned_to = ?");
  $stmt->bind_param("iii", $i, $jobId, $userId);
  $stmt->execute();
}

echo 'ok';
