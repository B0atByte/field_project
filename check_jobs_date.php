<?php
require_once __DIR__ . '/config/db.php';

echo "Checking 'jobs' table columns...\n";
$cols = $conn->query("SHOW COLUMNS FROM jobs");
$hasCreatedAt = false;
while ($r = $cols->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
    if ($r['Field'] === 'created_at')
        $hasCreatedAt = true;
}

if (!$hasCreatedAt) {
    echo "\nCRITICAL: 'created_at' column DOES NOT EXIST in jobs table!\n";
} else {
    echo "\n'created_at' column exists.\n";

    // Check data count
    echo "Checking recent data...\n";
    $sql = "SELECT created_at FROM jobs ORDER BY created_at DESC LIMIT 5";
    $res = $conn->query($sql);
    echo "Top 5 recent jobs:\n";
    while ($row = $res->fetch_assoc()) {
        echo $row['created_at'] . "\n";
    }

    $sqlCount = "SELECT COUNT(*) as c FROM jobs WHERE created_at >= CURDATE() - INTERVAL 14 DAY";
    $resCount = $conn->query($sqlCount);
    $row = $resCount->fetch_assoc();
    echo "\nJobs in last 14 days: " . $row['c'] . "\n";
}
?>