<?php
// admin/api/map_markers.php
require_once __DIR__ . '/../../includes/session_config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin','field','manager'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

require_once '../../config/db.php';

// ---- helpers ----
function g($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

$north = (float) g('north', 90);
$south = (float) g('south', -90);
$east  = (float) g('east', 180);
$west  = (float) g('west', -180);
$zoom  = (int) g('zoom', 10);

$start      = g('start');
$end        = g('end');
$q          = g('q');
$product    = g('product');
$job_id     = g('job_id');
$latestOnly = (int) g('latest_only', 0);

$LIMIT = 3000;
$crossDateLine = ($east < $west);

// ---- build filters ----
$where  = ["l.gps IS NOT NULL AND l.gps <> ''"];
$types  = "";
$params = [];

// viewport (gps เก็บเป็น 'lat,lng')
if (!$crossDateLine) {
    $where[] = "CAST(SUBSTRING_INDEX(l.gps, ',', 1) AS DECIMAL(10,7)) BETWEEN ? AND ?";
    $where[] = "CAST(SUBSTRING_INDEX(l.gps, ',', -1) AS DECIMAL(10,7)) BETWEEN ? AND ?";
    $types   .= "dddd";
    array_push($params, $south, $north, $west, $east);
} else {
    $where[] = "CAST(SUBSTRING_INDEX(l.gps, ',', 1) AS DECIMAL(10,7)) BETWEEN ? AND ?";
    $where[] = "(CAST(SUBSTRING_INDEX(l.gps, ',', -1) AS DECIMAL(10,7)) >= ? OR CAST(SUBSTRING_INDEX(l.gps, ',', -1) AS DECIMAL(10,7)) <= ?)";
    $types   .= "dddd";
    array_push($params, $south, $north, $west, $east);
}

// ตัวกรองอื่น ๆ
if ($start && $end) {
    $where[] = "DATE(l.created_at) BETWEEN ? AND ?";
    $types  .= "ss";
    array_push($params, $start, $end);
}
if (!empty($q)) {
    $where[] = "(j.contract_number LIKE CONCAT('%',?,'%') OR j.location_info LIKE CONCAT('%',?,'%'))";
    $types  .= "ss";
    array_push($params, $q, $q);
}
if (!empty($product)) {
    $where[] = "j.product = ?";
    $types  .= "s";
    $params[] = $product;
}
if (!empty($job_id)) {
    // ดึง location_info เดียวกันทั้งหมด
    $stmt_job = $conn->prepare("SELECT location_info FROM jobs WHERE id = ?");
    $stmt_job->bind_param("i", $job_id);
    $stmt_job->execute();
    $job = $stmt_job->get_result()->fetch_assoc();
    $stmt_job->close();
    if ($job && !empty($job['location_info'])) {
        $where[] = "j.location_info = ?";
        $types  .= "s";
        $params[] = $job['location_info'];
    }
}

// ---- query (ใช้ subquery MAX(created_at) -> ได้ทั้ง MySQL 5.7/8.0) ----
if ($latestOnly) {
    $sql = "SELECT l.job_id, l.gps, l.created_at, j.contract_number, j.product, j.location_info
            FROM job_logs l
            JOIN jobs j ON j.id = l.job_id
            JOIN (
                SELECT job_id, MAX(created_at) AS mx
                FROM job_logs
                GROUP BY job_id
            ) t ON t.job_id = l.job_id AND t.mx = l.created_at
            WHERE ".implode(" AND ", $where)."
            ORDER BY l.created_at DESC
            LIMIT {$LIMIT}";
} else {
    $sql = "SELECT l.job_id, l.gps, l.created_at, j.contract_number, j.product, j.location_info
            FROM job_logs l
            JOIN jobs j ON j.id = l.job_id
            WHERE ".implode(" AND ", $where)."
            ORDER BY l.created_at DESC
            LIMIT {$LIMIT}";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error, 'sql' => $sql]);
    exit;
}
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$markers = [];
while ($row = $res->fetch_assoc()) {
    $gps = explode(',', $row['gps']);
    if (count($gps) === 2) {
        $lat = (float)trim($gps[0]);
        $lng = (float)trim($gps[1]);
        if ($lat != 0 && $lng != 0) {
            $markers[] = [
                'id'       => (int)$row['job_id'],
                'lat'      => $lat,
                'lng'      => $lng,
                'product'  => (string)$row['product'],
                'contract' => (string)$row['contract_number'],
                'location' => (string)$row['location_info'],
                'created'  => (string)$row['created_at'],
            ];
        }
    }
}

echo json_encode([
    'markers'   => $markers,
    'count'     => count($markers),
    'truncated' => (count($markers) >= $LIMIT)
], JSON_UNESCAPED_UNICODE);
