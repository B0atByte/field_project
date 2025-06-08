<?php
require_once '../config/db.php';
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$job_id = $_GET['id'] ?? null;
if (!$job_id) {
    die("ไม่พบ ID งาน");
}

$stmt = $conn->prepare("SELECT * FROM job_logs WHERE job_id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray(['ID', 'user_id', 'ผลลัพธ์', 'Note', 'GPS', 'วันที่บันทึก'], NULL, 'A1');

$rowIndex = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A$rowIndex", $row['id']);
    $sheet->setCellValue("B$rowIndex", $row['user_id']);
    $sheet->setCellValue("C$rowIndex", $row['result']);
    $sheet->setCellValue("D$rowIndex", $row['note']);
    $sheet->setCellValue("E$rowIndex", $row['gps']);
    $sheet->setCellValue("F$rowIndex", $row['created_at']);
    $rowIndex++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="job_result_' . $job_id . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
