<?php
require 'config/db.php';

echo "=== ตรวจสอบข้อมูล result ในฐานข้อมูล ===\n\n";

$sql = "SELECT DISTINCT result
        FROM job_logs
        WHERE result LIKE '%พบผู้เช่า%'
           OR result LIKE '%ผู้ค้ำ%'
           OR result LIKE '%ผู้ครอบครอง%'
        LIMIT 20";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $count = 1;
    while ($row = $result->fetch_assoc()) {
        echo $count . ". ";
        echo "ข้อความ: " . $row['result'] . "\n";
        echo "   ความยาว: " . mb_strlen($row['result'], 'UTF-8') . " ตัวอักษร\n";

        // ตรวจสอบว่าเป็นข้อความที่ถูกต้องหรือไม่
        if (strpos($row['result'], 'พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง') !== false) {
            echo "   ✅ ข้อความครบถ้วน\n";
        } elseif (strpos($row['result'], 'พบผู้เช่า/ผู้ค้ำ/ผู') !== false) {
            echo "   ❌ ข้อความถูกตัด!\n";
        }
        echo "\n";
        $count++;
    }
} else {
    echo "❌ ไม่พบข้อมูลในฐานข้อมูล\n";
}

echo "\n=== ตรวจสอบข้อมูลล่าสุด 5 รายการ ===\n\n";

$sql2 = "SELECT id, result, created_at
         FROM job_logs
         ORDER BY id DESC
         LIMIT 5";

$result2 = $conn->query($sql2);

if ($result2 && $result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "ผล: " . $row['result'] . "\n";
        echo "วันที่: " . $row['created_at'] . "\n";
        echo "---\n";
    }
}

$conn->close();
