<?php
// cron/cleanup_jobs.php
// ตั้งเวลาให้ Windows Task Scheduler รันไฟล์นี้เป็นระยะ

date_default_timezone_set('Asia/Bangkok');
require __DIR__ . '/../config/db.php';

// 1. ดึงรายการงานที่ถึงกำหนดลบ
$sqlSelect = "
    SELECT j.id, j.contract_number
    FROM jobs j
    LEFT JOIN job_logs l ON l.job_id = j.id
    WHERE j.auto_delete_at IS NOT NULL
      AND NOW() >= j.auto_delete_at
      AND j.status = 'pending'
      AND l.id IS NULL
";
$result = $conn->query($sqlSelect);

$deletedCount = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $jobId = (int)$row['id'];
        $contract = $row['contract_number'];

        // 2. ลบงานออกจาก jobs
        $stmtDel = $conn->prepare("DELETE FROM jobs WHERE id = ?");
        $stmtDel->bind_param("i", $jobId);
        $stmtDel->execute();
        $stmtDel->close();

        if ($conn->affected_rows > 0) {
            $deletedCount++;

            // 3. บันทึกเหตุการณ์ลง job_edit_logs
            $summary = "ระบบลบงานอัตโนมัติ (ครบกำหนด) | สัญญา: {$contract}";
            $systemUserId = null; // ถ้าต้องการใส่เป็น user id ของระบบ เช่น 0

            $stmtLog = $conn->prepare("
                INSERT INTO job_edit_logs (job_id, edited_by, change_summary, edited_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmtLog->bind_param("iis", $jobId, $systemUserId, $summary);
            $stmtLog->execute();
            $stmtLog->close();
        }
    }
}

echo date('Y-m-d H:i:s') . " | Deleted: {$deletedCount} jobs\n";
