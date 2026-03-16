<?php
require_once '../config/db.php';

// Check admin permission (optional, but good for safety)
// require_once __DIR__ . '/../includes/session_config.php';
// if (($_SESSION['user']['role'] ?? '') !== 'admin') {
//     die("Unauthorized");
// }

$sqlFile = __DIR__ . '/../SQL/update_schema.sql';

if (!file_exists($sqlFile)) {
    die("Error: SQL file not found at $sqlFile");
}

$sql = file_get_contents($sqlFile);

// Split SQL by semicolon to execute queries one by one
// This is a simple split and might not handle complex stored procedures, but sufficient for this schema
$queries = explode(';', $sql);

echo "<h1>Database Migration Status</h1>";
echo "<ul>";

$success = true;

foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query)) continue;

    try {
        if ($conn->query($query) === TRUE) {
            echo "<li style='color: green;'>Successfully executed query.</li>";
        } else {
            // Ignore "Duplicate column", "Duplicate key", or "Table exists" errors
            $err = $conn->error;
            if (strpos($err, 'Duplicate column') !== false || 
                strpos($err, 'Duplicate key') !== false || 
                strpos($err, 'already exists') !== false) {
                 echo "<li style='color: orange;'>Skipped (Already exists): " . htmlspecialchars(substr($query, 0, 50)) . "...</li>";
            } else {
                echo "<li style='color: red;'>Error executing query: " . htmlspecialchars($conn->error) . "<br><small>" . htmlspecialchars(substr($query, 0, 100)) . "...</small></li>";
                $success = false;
            }
        }
    } catch (Exception $e) {
        echo "<li style='color: red;'>Exception: " . htmlspecialchars($e->getMessage()) . "</li>";
        $success = false;
    }
}

echo "</ul>";

if ($success) {
    echo "<h2 style='color: green;'>Migration Completed Successfully!</h2>";
    echo "<p><a href='jobs.php'>Go back to Jobs</a></p>";
} else {
    echo "<h2 style='color: red;'>Migration Finished with Errors. Please check above.</h2>";
}
?>
