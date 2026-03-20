<?php
ob_start();
ini_set('memory_limit', '256M');
set_time_limit(60);

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
requirePermission('page_logs');

require '../config/db.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Filters
$filter_date  = $_GET['date']    ?? date('Y-m-d');
$filter_user  = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from    = $_GET['date_from'] ?? $filter_date;
$date_to      = $_GET['date_to']   ?? $filter_date;
$range_mode   = isset($_GET['range']); // export as date range

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-d');
if ($date_from > $date_to) [$date_from, $date_to] = [$date_to, $date_from];

// Build query
$where  = ['DATE(wc.checkin_at) BETWEEN ? AND ?'];
$params = [$date_from, $date_to];
$types  = 'ss';

if ($filter_user > 0) {
    $where[] = 'wc.user_id = ?';
    $params[] = $filter_user;
    $types   .= 'i';
}

// Summary rows: one row per session (1 session = 1 checkin/checkout pair)
$sql = "
    SELECT
        DATE(wc.checkin_at)   AS work_date,
        u.id                  AS user_id,
        u.name                AS user_name,
        wc.checkin_at         AS first_checkin,
        wc.checkout_at        AS last_checkout,
        wc.checkin_lat        AS in_lat,
        wc.checkin_lng        AS in_lng,
        wc.checkin_address    AS in_address,
        wc.checkout_lat       AS out_lat,
        wc.checkout_lng       AS out_lng,
        wc.checkout_address   AS out_address
    FROM work_checkins wc
    JOIN users u ON wc.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY work_date ASC, wc.checkin_at ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('บันทึกเวลาทำงาน');

// ---- Header row ----
$headers = [
    'A' => 'วันที่',
    'B' => 'ชื่อพนักงาน',
    'C' => 'เวลาเข้างาน',
    'D' => 'เวลาออกงาน',
    'E' => 'ระยะเวลาทำงาน',
    'F' => 'พิกัดเข้างาน (lat,lng)',
    'G' => 'สถานที่เข้างาน',
    'H' => 'พิกัดออกงาน (lat,lng)',
    'I' => 'สถานที่ออกงาน',
    'J' => 'สถานะ',
    'K' => 'หมายเหตุ',
];

foreach ($headers as $col => $label) {
    $sheet->setCellValue("{$col}1", $label);
}

// Style header
$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
];
$sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(28);

// ---- Data rows ----
$rowNum = 2;
foreach ($rows as $r) {
    $isOut = !empty($r['last_checkout']);

    // Elapsed duration
    $duration = '';
    if ($r['first_checkin'] && $r['last_checkout']) {
        $diff = strtotime($r['last_checkout']) - strtotime($r['first_checkin']);
        $h    = floor($diff / 3600);
        $m    = floor(($diff % 3600) / 60);
        $s    = $diff % 60;
        $duration = sprintf('%d:%02d:%02d', $h, $m, $s);
    }

    $inCoord  = ($r['in_lat']  && $r['in_lng'])  ? number_format($r['in_lat'],  6) . ', ' . number_format($r['in_lng'],  6) : '';
    $outCoord = ($r['out_lat'] && $r['out_lng']) ? number_format($r['out_lat'], 6) . ', ' . number_format($r['out_lng'], 6) : '';

    $status = $isOut ? 'ออกงานแล้ว' : 'กำลังทำงาน';

    $sheet->setCellValue("A{$rowNum}", $r['work_date']);
    $sheet->setCellValue("B{$rowNum}", $r['user_name']);
    $sheet->setCellValue("C{$rowNum}", $r['first_checkin'] ? date('H:i:s', strtotime($r['first_checkin'])) : '');
    $sheet->setCellValue("D{$rowNum}", $r['last_checkout'] ? date('H:i:s', strtotime($r['last_checkout'])) : '');
    $sheet->setCellValue("E{$rowNum}", $duration);
    $sheet->setCellValue("F{$rowNum}", $inCoord);
    $sheet->setCellValue("G{$rowNum}", $r['in_address'] ?? '');
    $sheet->setCellValue("H{$rowNum}", $outCoord);
    $sheet->setCellValue("I{$rowNum}", $r['out_address'] ?? '');
    $sheet->setCellValue("J{$rowNum}", $status);
    $sheet->setCellValue("K{$rowNum}", '');

    // Row background: alternating + status color
    $bgColor = ($rowNum % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
    if (!$isOut && $r['first_checkin']) $bgColor = 'ECFDF5'; // working = light green

    $rowStyle = [
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ];
    $sheet->getStyle("A{$rowNum}:K{$rowNum}")->applyFromArray($rowStyle);
    $sheet->getRowDimension($rowNum)->setRowHeight(22);

    $rowNum++;
}

// ---- Column widths ----
$colWidths = ['A'=>14,'B'=>22,'C'=>14,'D'=>14,'E'=>18,'F'=>28,'G'=>40,'H'=>28,'I'=>40,'J'=>16,'K'=>20];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// ---- Summary sheet ----
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('สรุปรายคน');

// Get all field users and their stats for the period
$sql2 = "
    SELECT
        u.name                                  AS user_name,
        COUNT(DISTINCT DATE(wc.checkin_at))     AS days_present,
        COUNT(*)                                AS total_checkins,
        SUM(wc.checkout_at IS NOT NULL)         AS total_checkouts,
        MIN(wc.checkin_at)                      AS earliest_checkin,
        MAX(wc.checkout_at)                     AS latest_checkout
    FROM work_checkins wc
    JOIN users u ON wc.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY u.id, u.name
    ORDER BY u.name ASC
";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param($types, ...$params);
$stmt2->execute();
$summary = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$sheet2->setCellValue('A1', 'ชื่อพนักงาน');
$sheet2->setCellValue('B1', 'จำนวนวันที่มา');
$sheet2->setCellValue('C1', 'จำนวนครั้งเข้างาน');
$sheet2->setCellValue('D1', 'จำนวนครั้งออกงาน');
$sheet2->getStyle('A1:D1')->applyFromArray($headerStyle);
$sheet2->getRowDimension(1)->setRowHeight(28);

$r2 = 2;
foreach ($summary as $s) {
    $sheet2->setCellValue("A{$r2}", $s['user_name']);
    $sheet2->setCellValue("B{$r2}", (int)$s['days_present']);
    $sheet2->setCellValue("C{$r2}", (int)$s['total_checkins']);
    $sheet2->setCellValue("D{$r2}", (int)$s['total_checkouts']);

    $bg = ($r2 % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
    $sheet2->getStyle("A{$r2}:D{$r2}")->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet2->getRowDimension($r2)->setRowHeight(22);
    $r2++;
}

foreach (['A' => 28, 'B' => 18, 'C' => 22, 'D' => 22] as $col => $w) {
    $sheet2->getColumnDimension($col)->setWidth($w);
}

$spreadsheet->setActiveSheetIndex(0);

// ---- Output ----
ob_end_clean();

$label = ($date_from === $date_to)
    ? $date_from
    : "{$date_from}_to_{$date_to}";

$filename = "attendance_{$label}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
