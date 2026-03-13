<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json');

if ($_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../../config/db.php';

$month = $_GET['month'] ?? date('Y-m');

// แยก year และ month
list($year, $monthNum) = explode('-', $month);

// Top 5 Field Performers
$top_field = [];
$resTop = $conn->query("
    SELECT u.id, u.name, COUNT(j.id) AS completed_jobs
    FROM jobs j
    JOIN users u ON j.assigned_to = u.id
    WHERE u.role = 'field'
      AND j.status = 'completed'
      AND YEAR(j.updated_at) = $year
      AND MONTH(j.updated_at) = $monthNum
    GROUP BY u.id
    ORDER BY completed_jobs DESC
    LIMIT 5
");
while ($row = $resTop->fetch_assoc()) {
    $top_field[] = [
        'id' => $row['id'],
        'name' => htmlspecialchars($row['name']),
        'completed_jobs' => (int)$row['completed_jobs']
    ];
}

// All Field Workers (นอกเหนือจาก Top 5)
$all_field = [];
$resAllField = $conn->query("
    SELECT u.id, u.name,
           COUNT(CASE WHEN j.status = 'completed'
                      AND YEAR(j.updated_at) = $year
                      AND MONTH(j.updated_at) = $monthNum THEN 1 END) AS completed_jobs
    FROM users u
    LEFT JOIN jobs j ON j.assigned_to = u.id
    WHERE u.role = 'field'
    GROUP BY u.id
    ORDER BY completed_jobs DESC, u.name ASC
    LIMIT 5, 999
");
while ($row = $resAllField->fetch_assoc()) {
    $all_field[] = [
        'id' => $row['id'],
        'name' => htmlspecialchars($row['name']),
        'completed_jobs' => (int)$row['completed_jobs']
    ];
}

echo json_encode([
    'success' => true,
    'month' => $month,
    'top_field' => $top_field,
    'all_field' => $all_field
]);
