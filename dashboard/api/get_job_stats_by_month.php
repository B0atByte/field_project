<?php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../../config/db.php';

$month = $_GET['month'] ?? date('Y-m');

// แยก year และ month
list($year, $monthNum) = explode('-', $month);

// สถิติการลงงานแบบละเอียด
$job_result_stats = $conn->query("
    SELECT
        COUNT(*) AS total_logs,
        SUM(CASE WHEN result LIKE '%พบผู้เช่า%' OR result LIKE '%พบผู้ค้ำ%' OR result LIKE '%พบผู้ครอบครอง%'
                 AND result NOT LIKE '%ไม่พบผู้เช่า%' AND result NOT LIKE '%ไม่พบผู้ค้ำ%'
                 AND result NOT LIKE '%พบรถ ไม่พบผู้เช่า%' THEN 1 ELSE 0 END) AS found_tenant,
        SUM(CASE WHEN result LIKE '%ไม่พบผู้เช่า%' OR result LIKE '%ไม่พบผู้ค้ำ%' OR result LIKE '%ไม่พบผู้ครอบครอง%' THEN 1 ELSE 0 END) AS not_found_tenant,
        SUM(CASE WHEN result LIKE '%พบที่ตั้ง%' AND result LIKE '%ไม่พบรถ%' THEN 1 ELSE 0 END) AS found_location_no_car,
        SUM(CASE WHEN result LIKE '%ไม่พบที่ตั้ง%' OR (result LIKE '%ไม่พบที่ตั้ง%' AND result LIKE '%ไม่พบรถ%') THEN 1 ELSE 0 END) AS not_found_location,
        SUM(CASE WHEN result LIKE '%พบ บ.3%' OR result LIKE '%ฝากเรื่องติดต่อกลับ%' OR result LIKE '%ฝากเรื่อง%' THEN 1 ELSE 0 END) AS found_relative,
        SUM(CASE WHEN result LIKE '%พบรถ%' AND result LIKE '%ไม่พบผู้เช่า%' THEN 1 ELSE 0 END) AS found_car_no_tenant,
        SUM(CASE WHEN result LIKE '%นัดชำระ%' THEN 1 ELSE 0 END) AS appointment_payment,
        SUM(CASE WHEN result LIKE '%นัดคืนรถ%' THEN 1 ELSE 0 END) AS appointment_return
    FROM job_logs
    WHERE YEAR(created_at) = $year AND MONTH(created_at) = $monthNum
")->fetch_assoc();

$total_logs = (int)($job_result_stats['total_logs'] ?? 0);
$found_tenant = (int)($job_result_stats['found_tenant'] ?? 0);
$not_found_tenant = (int)($job_result_stats['not_found_tenant'] ?? 0);
$found_location_no_car = (int)($job_result_stats['found_location_no_car'] ?? 0);
$not_found_location = (int)($job_result_stats['not_found_location'] ?? 0);
$found_relative = (int)($job_result_stats['found_relative'] ?? 0);
$found_car_no_tenant = (int)($job_result_stats['found_car_no_tenant'] ?? 0);
$appointment_payment = (int)($job_result_stats['appointment_payment'] ?? 0);
$appointment_return = (int)($job_result_stats['appointment_return'] ?? 0);

// คำนวณ %
$found_tenant_pct = $total_logs > 0 ? round(($found_tenant / $total_logs) * 100, 1) : 0;
$not_found_tenant_pct = $total_logs > 0 ? round(($not_found_tenant / $total_logs) * 100, 1) : 0;
$found_location_no_car_pct = $total_logs > 0 ? round(($found_location_no_car / $total_logs) * 100, 1) : 0;
$not_found_location_pct = $total_logs > 0 ? round(($not_found_location / $total_logs) * 100, 1) : 0;
$found_relative_pct = $total_logs > 0 ? round(($found_relative / $total_logs) * 100, 1) : 0;
$found_car_no_tenant_pct = $total_logs > 0 ? round(($found_car_no_tenant / $total_logs) * 100, 1) : 0;
$appointment_payment_pct = $total_logs > 0 ? round(($appointment_payment / $total_logs) * 100, 1) : 0;
$appointment_return_pct = $total_logs > 0 ? round(($appointment_return / $total_logs) * 100, 1) : 0;

echo json_encode([
    'success' => true,
    'month' => $month,
    'total_logs' => $total_logs,
    'stats' => [
        'found_tenant' => ['count' => $found_tenant, 'pct' => $found_tenant_pct],
        'not_found_tenant' => ['count' => $not_found_tenant, 'pct' => $not_found_tenant_pct],
        'found_location_no_car' => ['count' => $found_location_no_car, 'pct' => $found_location_no_car_pct],
        'not_found_location' => ['count' => $not_found_location, 'pct' => $not_found_location_pct],
        'found_relative' => ['count' => $found_relative, 'pct' => $found_relative_pct],
        'found_car_no_tenant' => ['count' => $found_car_no_tenant, 'pct' => $found_car_no_tenant_pct],
        'appointment_payment' => ['count' => $appointment_payment, 'pct' => $appointment_payment_pct],
        'appointment_return' => ['count' => $appointment_return, 'pct' => $appointment_return_pct]
    ]
]);
