<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
echo json_encode(['status' => 'ok', 'message' => 'File access successful']);
