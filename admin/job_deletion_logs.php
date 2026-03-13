<?php
// Redirect to new unified logs page
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Redirect to new logs.php with job_deletion tab
$query_params = $_GET;
$query_params['tab'] = 'job_deletion';
// Standardize naming: date_from/date_to -> start_date/end_date
if (isset($query_params['date_from'])) {
    $query_params['start_date'] = $query_params['date_from'];
    unset($query_params['date_from']);
}
if (isset($query_params['date_to'])) {
    $query_params['end_date'] = $query_params['date_to'];
    unset($query_params['date_to']);
}
header("Location: logs.php?" . http_build_query($query_params));
exit;

// รับค่าจากฟอร์มกรอง (ไม่บังคับกรองวันที่แล้ว)
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$deleted_by = $_GET['deleted_by'] ?? '';
$per_page = $_GET['per_page'] ?? 50; // จำนวนรายการต่อหน้า (default 50)
$page = $_GET['page'] ?? 1; // หน้าปัจจุบัน

// ตรวจสอบค่า per_page
if ($per_page === 'all') {
    $limit = 999999; // แสดงทั้งหมด
    $offset = 0;
} else {
    $per_page = (int)$per_page;
    if (!in_array($per_page, [10, 25, 50, 100])) {
        $per_page = 50; // default
    }
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $per_page;
    $limit = $per_page;
}

// ดึงรายชื่อ admin ที่เคยลบงาน
$admins = [];
$stmt = $conn->prepare("SELECT DISTINCT u.id, u.name
                        FROM users u
                        INNER JOIN job_deletion_logs jdl ON u.id = jdl.deleted_by
                        ORDER BY u.name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $admins[] = $row;
}
$stmt->close();

// สร้าง WHERE clause
$where = "WHERE 1=1";
$params = [];
$types = "";

// กรองวันที่เฉพาะเมื่อมีการระบุ
if ($date_from && $date_to) {
    $where .= " AND DATE(jdl.deleted_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= "ss";
}

if ($deleted_by) {
    $where .= " AND jdl.deleted_by = ?";
    $params[] = $deleted_by;
    $types .= "i";
}

// นับจำนวนรายการทั้งหมด
$count_sql = "SELECT COUNT(*) as total
              FROM job_deletion_logs jdl
              LEFT JOIN users u ON jdl.deleted_by = u.id
              LEFT JOIN users u2 ON jdl.assigned_to = u2.id
              $where";

$stmt_count = $conn->prepare($count_sql);
if (count($params) > 0) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_logs = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

// คำนวณจำนวนหน้า
if ($per_page === 'all') {
    $total_pages = 1;
} else {
    $total_pages = ceil($total_logs / $per_page);
    $page = min($page, max(1, $total_pages)); // ป้องกันหน้าเกิน
}

// ดึงข้อมูล deletion logs พร้อม LIMIT
$sql = "SELECT jdl.*, u.name as deleted_by_name, u.role as deleted_by_role, u2.name as assigned_to_name
        FROM job_deletion_logs jdl
        LEFT JOIN users u ON jdl.deleted_by = u.id
        LEFT JOIN users u2 ON jdl.assigned_to = u2.id
        $where
        ORDER BY jdl.deleted_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$limit, $offset]));
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
$stmt->close();

// คำนวณลำดับรายการที่แสดง
$showing_from = $total_logs > 0 ? $offset + 1 : 0;
$showing_to = min($offset + $limit, $total_logs);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <?php include('../components/header.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'thai': ['Prompt', 'sans-serif']
                    },
                    colors: {
                        'primary': '#dc2626',
                        'secondary': '#991b1b',
                        'surface': '#f8fafc'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-out',
                        'slide-up': 'slideUp 0.5s ease-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="font-thai bg-surface min-h-screen">

<div class="flex min-h-screen bg-surface">
    <!-- Sidebar -->
    <?php include('../components/sidebar.php'); ?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 ml-64">
        <!-- Header -->
        <header class="bg-white shadow-lg border-b border-red-100 sticky top-0 z-40">
            <div class="px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                            <svg class="w-7 h-7 mr-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <?= $page_title ?>
                        </h1>
                        <p class="text-sm text-gray-500 mt-1">บันทึกการลบงานทั้งหมดในระบบ (แสดงทั้งหมด)</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-lg">
                            <span class="font-semibold"><?= number_format($total_logs) ?></span> รายการ
                        </div>
                        <a href="../dashboard/admin.php" class="inline-flex items-center px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-xl font-medium transition-all duration-300 shadow-lg hover:shadow-xl">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            กลับหน้าหลัก
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Body -->
        <main class="flex-1 p-6 space-y-6 overflow-y-auto">

    <!-- Filter Section -->
    <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                กรองข้อมูลประวัติการลบ
            </h2>
            <p class="text-sm text-gray-500 mt-1">ค้นหาและกรองประวัติการลบงานตามวันที่ ผู้ลบ และจำนวนรายการที่ต้องการแสดง</p>
        </div>

        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        วันที่เริ่มต้น (ไม่บังคับ)
                    </label>
                    <input type="date" name="date_from" value="<?= $date_from ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        วันที่สิ้นสุด (ไม่บังคับ)
                    </label>
                    <input type="date" name="date_to" value="<?= $date_to ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                        </svg>
                        ผู้ลบ
                    </label>
                    <select name="deleted_by"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 appearance-none bg-white"
                            style="background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e&quot;); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em;">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= $admin['id'] == $deleted_by ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <svg class="w-4 h-4 inline mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        แสดงต่อหน้า
                    </label>
                    <select name="per_page"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 appearance-none bg-white"
                            style="background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e&quot;); background-position: right 0.75rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em;">
                        <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                        <option value="all" <?= $per_page === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between gap-3 pt-4">
                <button type="submit" class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    กรองข้อมูล
                </button>
                <a href="job_deletion_logs.php" class="inline-flex items-center justify-center px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    รีเซ็ต
                </a>
            </div>
        </form>
    </section>

    <!-- Summary -->
    <section class="bg-gradient-to-r from-red-500 to-red-600 text-white p-6 rounded-2xl shadow-lg animate-slide-up" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-red-100 text-sm font-medium">งานที่ถูกลบทั้งหมด</p>
                <p class="text-3xl font-bold"><?= number_format($total_logs) ?></p>
                <p class="text-red-100 text-sm">รายการ</p>
                <?php if ($per_page !== 'all' && $total_logs > 0): ?>
                    <p class="text-red-100 text-xs mt-2">
                        <i class="fas fa-eye mr-1"></i>
                        กำลังแสดง <?= number_format($showing_from) ?> - <?= number_format($showing_to) ?> รายการ
                    </p>
                <?php endif; ?>
            </div>
            <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
        </div>
    </section>

    <!-- Logs Table -->
    <?php if (count($logs) > 0): ?>
    <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 animate-slide-up" style="animation-delay: 0.2s;">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <svg class="w-6 h-6 mr-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    รายการประวัติการลบงาน
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    <?php if ($per_page !== 'all' && $total_logs > 0): ?>
                        แสดง <?= number_format($showing_from) ?> - <?= number_format($showing_to) ?> จาก <?= number_format($total_logs) ?> รายการ
                    <?php else: ?>
                        ทั้งหมด <?= number_format($total_logs) ?> รายการ
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($per_page !== 'all' && $total_pages > 1): ?>
            <div class="text-sm text-gray-600">
                หน้า <?= $page ?> / <?= $total_pages ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">เวลาที่ลบ</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">รหัสงาน</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">เลขที่สัญญา</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ลูกค้า</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ผู้ถูกมอบหมาย</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ผู้ลบ</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ประเภท</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($logs as $index => $log): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?= date('d/m/Y', strtotime($log['deleted_at'])) ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= date('H:i', strtotime($log['deleted_at'])) ?> น.
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-900">#<?= $log['job_id'] ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-blue-600"><?= htmlspecialchars($log['contract_number'] ?? '-') ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?= htmlspecialchars($log['location_info'] ?? '-') ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($log['product'] ?? '-') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-900"><?= htmlspecialchars($log['assigned_to_name'] ?? '-') ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($log['deleted_by_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= $log['deleted_by_role'] === 'admin' ? 'ผู้ดูแลระบบ' : $log['deleted_by_role'] ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $type_colors = [
                                    'manual' => 'bg-blue-100 text-blue-800',
                                    'bulk' => 'bg-yellow-100 text-yellow-800',
                                    'auto' => 'bg-green-100 text-green-800'
                                ];
                                $type_text = [
                                    'manual' => 'ลบเดี่ยว',
                                    'bulk' => 'ลบหลายรายการ',
                                    'auto' => 'ลบอัตโนมัติ'
                                ];
                                ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $type_colors[$log['deletion_type']] ?? 'bg-gray-100 text-gray-800' ?>">
                                    <?= $type_text[$log['deletion_type']] ?? $log['deletion_type'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button onclick="viewJobData(<?= $log['id'] ?>)"
                                        class="text-indigo-600 hover:text-indigo-900 font-medium">
                                    <i class="fas fa-eye mr-1"></i>ดูรายละเอียด
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($per_page !== 'all' && $total_pages > 1): ?>
        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
            <div>
                <p class="text-sm text-gray-700">
                    แสดง <span class="font-medium"><?= number_format($showing_from) ?></span> ถึง
                    <span class="font-medium"><?= number_format($showing_to) ?></span> จาก
                    <span class="font-medium"><?= number_format($total_logs) ?></span> รายการ
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <!-- Previous Button -->
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </a>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>"
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            1
                        </a>
                        <?php if ($start_page > 2): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                ...
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?= $i == $page ? 'bg-blue-600 text-white border-blue-600 z-10' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                ...
                            </span>
                        <?php endif; ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <?= $total_pages ?>
                        </a>
                    <?php endif; ?>

                    <!-- Next Button -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="bg-white rounded-2xl shadow-lg border border-gray-100 p-12 animate-slide-up" style="animation-delay: 0.2s;">
        <div class="flex flex-col items-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full mb-4 flex items-center justify-center">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">ไม่พบข้อมูลประวัติการลบ</h3>
            <p class="text-gray-500 text-sm">ไม่มีประวัติการลบงานในช่วงเวลาที่เลือก</p>
        </div>
    </section>
    <?php endif; ?>

</main>
    </div>
</div>

<script>
async function viewJobData(logId) {
    try {
        const response = await fetch(`api/get_deletion_log_detail.php?id=${logId}`);
        const data = await response.json();

        if (data.success) {
            const jobData = data.log.job_data ? JSON.parse(data.log.job_data) : {};

            Swal.fire({
                title: '<strong>รายละเอียดงานที่ถูกลบ</strong>',
                html: `
                    <div class="text-left space-y-2">
                        <p><strong>รหัสงาน:</strong> #${data.log.job_id}</p>
                        <p><strong>เลขที่สัญญา:</strong> ${data.log.contract_number || '-'}</p>
                        <p><strong>ชื่อลูกค้า:</strong> ${data.log.location_info || '-'}</p>
                        <p><strong>Product:</strong> ${data.log.product || '-'}</p>
                        <p><strong>สถานะก่อนลบ:</strong> ${data.log.status || '-'}</p>
                        <p><strong>ผู้ถูกมอบหมาย:</strong> ${data.log.assigned_to_name || '-'}</p>
                        <p><strong>ผู้ลบ:</strong> ${data.log.deleted_by_name}</p>
                        <p><strong>วันที่ลบ:</strong> ${new Date(data.log.deleted_at).toLocaleString('th-TH')}</p>
                        ${data.log.delete_reason ? `<p><strong>เหตุผล:</strong> ${data.log.delete_reason}</p>` : ''}
                    </div>
                `,
                width: 600,
                confirmButtonText: 'ปิด',
                confirmButtonColor: '#3b82f6'
            });
        } else {
            throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลได้');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถโหลดข้อมูลได้'
        });
    }
}
</script>

</body>
</html>
