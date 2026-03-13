<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ทดสอบความยาวข้อความ</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .box { border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
        .truncated { background: #ffebee; }
        .ok { background: #e8f5e9; }
    </style>
</head>
<body>
    <h1>ทดสอบข้อความใน job_logs</h1>

    <?php
    require 'config/db.php';

    // 1. เช็ค schema
    echo "<div class='box'>";
    echo "<h2>1. Schema ของตาราง job_logs</h2>";
    $schema = $conn->query("SHOW COLUMNS FROM job_logs LIKE 'result'");
    if ($row = $schema->fetch_assoc()) {
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    }
    echo "</div>";

    // 2. เช็คข้อมูลจริง
    echo "<div class='box'>";
    echo "<h2>2. ข้อมูลจริงในฐานข้อมูล (10 รายการล่าสุด)</h2>";
    $sql = "SELECT id, result, CHAR_LENGTH(result) as len, created_at
            FROM job_logs
            WHERE result LIKE '%พบผู้เช่า%'
               OR result LIKE '%ผู้ค้ำ%'
               OR result LIKE '%ผู้ครอบครอง%'
            ORDER BY id DESC
            LIMIT 10";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>ข้อความ</th><th>ความยาว</th><th>สถานะ</th><th>วันที่</th></tr>";

        while ($row = $result->fetch_assoc()) {
            $text = htmlspecialchars($row['result']);
            $len = $row['len'];
            $fullText = 'พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง';
            $fullLen = mb_strlen($fullText, 'UTF-8');

            $isTruncated = (strpos($text, 'พบผู้เช่า') !== false &&
                           strpos($text, 'ครอบครอง') === false);

            $class = $isTruncated ? 'truncated' : 'ok';
            $status = $isTruncated ? '❌ ถูกตัด!' : '✅ ครบถ้วน';

            echo "<tr class='$class'>";
            echo "<td>{$row['id']}</td>";
            echo "<td>$text</td>";
            echo "<td>$len ตัวอักษร</td>";
            echo "<td>$status</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>ไม่พบข้อมูล</p>";
    }
    echo "</div>";

    // 3. ทดสอบ INSERT
    echo "<div class='box'>";
    echo "<h2>3. ทดสอบความยาวสูงสุดที่บันทึกได้</h2>";
    $testText = 'พบผู้เช่า/ผู้ค้ำ/ผู้ครอบครอง';
    echo "<p>ข้อความทดสอบ: <strong>$testText</strong></p>";
    echo "<p>ความยาว: <strong>" . mb_strlen($testText, 'UTF-8') . " ตัวอักษร</strong></p>";
    echo "<p>ความยาว (bytes): <strong>" . strlen($testText) . " bytes</strong></p>";
    echo "</div>";

    $conn->close();
    ?>

    <div class='box'>
        <h2>คำแนะนำ</h2>
        <p>ถ้าพบว่าข้อมูลในฐานข้อมูลถูกตัด แสดงว่า:</p>
        <ul>
            <li>ตาราง job_logs -> field 'result' อาจมี VARCHAR ที่สั้นเกินไป</li>
            <li>ควรเปลี่ยนเป็น VARCHAR(500) หรือมากกว่า</li>
        </ul>
    </div>
</body>
</html>
