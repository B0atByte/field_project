<?php
require_once __DIR__ . '/../includes/session_config.php';
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'field') {
  http_response_code(403);
  exit;
}

$jobId = intval($_POST['job_id'] ?? 0);
$set = intval($_POST['set'] ?? 0);
$userId = $_SESSION['user']['id'];

$stmt = $conn->prepare("UPDATE jobs SET is_favorite = ? WHERE id = ? AND assigned_to = ?");
$stmt->bind_param("iii", $set, $jobId, $userId);
$stmt->execute();

echo $stmt->affected_rows > 0 ? 'ok' : 'fail';
