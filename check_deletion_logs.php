<?php
// ไฟล์ตรวจสอบตาราง job_deletion_logs
include('config/db.php');

echo "<h1>ตรวจสอบระบบ Deletion Logs</h1>";
echo "<hr>";

// 1. ตรวจสอบว่ามีตาราง job_deletion_logs หรือไม่
$result = $conn->query("SHOW TABLES LIKE 'job_deletion_logs'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✅ ตาราง job_deletion_logs มีอยู่แล้ว</p>";

    // 2. นับจำนวน records
    $count_result = $conn->query("SELECT COUNT(*) as total FROM job_deletion_logs");
    $count = $count_result->fetch_assoc()['total'];
    echo "<p><strong>📊 จำนวน logs ทั้งหมด:</strong> $count รายการ</p>";

    // 3. แสดง 10 รายการล่าสุด
    if ($count > 0) {
        echo "<h2>📋 Logs ล่าสุด 10 รายการ:</h2>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>
                <th>ID</th>
                <th>Job ID</th>
                <th>เลขสัญญา</th>
                <th>ลูกค้า</th>
                <th>ผู้ลบ ID</th>
                <th>วันที่ลบ</th>
              </tr>";

        $logs = $conn->query("SELECT * FROM job_deletion_logs ORDER BY deleted_at DESC LIMIT 10");
        while ($log = $logs->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$log['id']}</td>";
            echo "<td>#{$log['job_id']}</td>";
            echo "<td>{$log['contract_number']}</td>";
            echo "<td>{$log['location_info']}</td>";
            echo "<td>{$log['deleted_by']}</td>";
            echo "<td>{$log['deleted_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ ยังไม่มีข้อมูลการลบงานในระบบ</p>";
        echo "<p>ให้ลองลบงานดูผ่านหน้า <a href='admin/admin_delete_jobs.php'>admin_delete_jobs.php</a></p>";
    }

    // 4. แสดงโครงสร้างตาราง
    echo "<h2>🏗️ โครงสร้างตาราง:</h2>";
    $structure = $conn->query("DESCRIBE job_deletion_logs");
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($field = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$field['Field']}</td>";
        echo "<td>{$field['Type']}</td>";
        echo "<td>{$field['Null']}</td>";
        echo "<td>{$field['Key']}</td>";
        echo "<td>{$field['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";

} else {
    echo "<p style='color: red;'>❌ ตาราง job_deletion_logs ยังไม่มี!</p>";
    echo "<h2>🔧 วิธีแก้ไข:</h2>";
    echo "<ol>";
    echo "<li>ไปที่ phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
    echo "<li>เลือกฐานข้อมูล <strong>field_project</strong></li>";
    echo "<li>คลิกแท็บ <strong>SQL</strong></li>";
    echo "<li>รันไฟล์ <code>SQL/step1_create_tables.sql</code> (สร้างตาราง job_deletion_logs)</li>";
    echo "</ol>";
}

$conn->close();
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}
h1 { color: #333; }
h2 { color: #555; margin-top: 30px; }
table { margin-top: 10px; }
th { text-align: left; }
code {
    background: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
}
</style>
