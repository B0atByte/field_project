<?php
require_once __DIR__ . '/config/db.php';

echo "Current PHP date: " . date('Y-m-d') . "\n";

$sql = "
    SELECT 
        DATE(created_at) AS d,
        COUNT(*) AS created_jobs,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_jobs
    FROM jobs
    WHERE created_at >= CURDATE() - INTERVAL 14 DAY
    GROUP BY DATE(created_at)
";
echo "Executing SQL: $sql\n";

$res = $conn->query($sql);
if (!$res) {
    die("Query failed: " . $conn->error);
}

$trendData = [];
echo "Results:\n";
while ($row = $res->fetch_assoc()) {
    echo "Date: " . $row['d'] . " (Len: " . strlen($row['d']) . ") - Created: " . $row['created_jobs'] . "\n";
    $trendData[$row['d']] = $row;
}

echo "\nChecking Loop Matches:\n";
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $match = isset($trendData[$d]) ? "YES" : "NO";
    echo "Checking key '$d': $match\n";
}
?>