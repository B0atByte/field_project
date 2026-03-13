<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session_config.php';

if ($_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include __DIR__ . '/../../config/db.php';

// Get summary statistics
$summary = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status IS NULL OR status != 'completed' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) AS unassigned,
        SUM(CASE WHEN (status IS NULL OR status != 'completed')
                  AND due_date IS NOT NULL
                  AND DATE(due_date) < CURDATE() THEN 1 ELSE 0 END) AS overdue
    FROM jobs
")->fetch_assoc();

$jobs_total = (int)($summary['total'] ?? 0);
$jobs_completed = (int)($summary['completed'] ?? 0);
$jobs_pending = (int)($summary['pending'] ?? 0);
$jobs_unassigned = (int)($summary['unassigned'] ?? 0);

// Calculate rates
$completion_rate = $jobs_total > 0 ? round(($jobs_completed / $jobs_total) * 100, 1) : 0;

// Jobs sent vs unsent
$jobs_sent = (int)($conn->query("SELECT COUNT(DISTINCT j.id) AS total
                                 FROM jobs j JOIN job_logs jl ON j.id = jl.job_id")->fetch_assoc()['total'] ?? 0);
$jobs_unsent = max(0, $jobs_total - $jobs_sent);
$sent_rate = $jobs_total > 0 ? round(($jobs_sent / $jobs_total) * 100, 1) : 0;

echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'total' => $jobs_total,
        'completed' => $jobs_completed,
        'pending' => $jobs_pending,
        'unassigned' => $jobs_unassigned,
        'sent' => $jobs_sent,
        'unsent' => $jobs_unsent,
        'completion_rate' => $completion_rate,
        'sent_rate' => $sent_rate
    ]
]);
