<?php
require_once __DIR__ . '/../includes/session_config.php';
include('../config/db.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ตรวจสอบสิทธิ์ admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];
$user_id = $_SESSION['user']['id'];

// ดึงรายชื่อพนักงาน field
$fieldUsers = [];
$stmt = $conn->prepare("SELECT id, name FROM users WHERE role = 'field'");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fieldUsers[] = $row;
}
$stmt->close();

// รับค่าจากฟอร์มกรอง
$selected_user = $_GET['user_id'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ลบงาน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_filter']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $del_user = (int)$_POST['user_id'];
    $del_status = $_POST['status'];
    $del_date_from = $_POST['date_from'];
    $del_date_to = $_POST['date_to'];

    // ดึงข้อมูลงานที่จะถูกลบก่อน เพื่อบันทึก log
    $sql_select = "SELECT * FROM jobs WHERE assigned_to = ? AND created_at BETWEEN ? AND ?";
    $params_select = [$del_user, $del_date_from, $del_date_to];

    if ($del_status === 'completed') {
        $sql_select .= " AND status = 'completed'";
    } elseif ($del_status === 'incomplete') {
        $sql_select .= " AND status != 'completed'";
    }

    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("iss", ...$params_select);
    $stmt_select->execute();
    $result_jobs = $stmt_select->get_result();

    $deleted_count = 0;

    // บันทึก log สำหรับแต่ละงานที่ถูกลบ
    while ($job = $result_jobs->fetch_assoc()) {
        // เตรียมข้อมูลงานในรูปแบบ JSON
        $job_data = json_encode($job, JSON_UNESCAPED_UNICODE);

        // บันทึก log ลงตาราง job_deletion_logs
        $sql_log = "INSERT INTO job_deletion_logs
                    (job_id, contract_number, product, location_info, assigned_to, status,
                     deleted_by, deletion_type, job_data)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'bulk', ?)";

        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param(
            "isssiiss",
            $job['id'],
            $job['contract_number'],
            $job['product'],
            $job['location_info'],
            $job['assigned_to'],
            $job['status'],
            $user_id,
            $job_data
        );
        $stmt_log->execute();
        $stmt_log->close();

        $deleted_count++;
    }
    $stmt_select->close();

    // ลบงานตามเงื่อนไข
    $sql_delete = "DELETE FROM jobs WHERE assigned_to = ? AND created_at BETWEEN ? AND ?";
    $params_delete = [$del_user, $del_date_from, $del_date_to];

    if ($del_status === 'completed') {
        $sql_delete .= " AND status = 'completed'";
    } elseif ($del_status === 'incomplete') {
        $sql_delete .= " AND status != 'completed'";
    }

    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("iss", ...$params_delete);
    $stmt_delete->execute();
    $deleted_rows = $stmt_delete->affected_rows;
    $stmt_delete->close();

    $_SESSION['deleted'] = $deleted_rows;
    header("Location: admin_delete_jobs.php?user_id=$del_user&status=$del_status&date_from=$del_date_from&date_to=$del_date_to");
    exit;
}

// คิวรีรายงานงาน
$jobs = [];
$total = $completed = $incomplete = 0;

if ($selected_user && $date_from && $date_to) {
    $sql = "SELECT * FROM jobs WHERE assigned_to = ? AND created_at BETWEEN ? AND ?";
    $params = [$selected_user, $date_from, $date_to];

    if ($status_filter === 'completed') {
        $sql .= " AND status = 'completed'";
    } elseif ($status_filter === 'incomplete') {
        $sql .= " AND status != 'completed'";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
        $total++;
        if ($row['status'] === 'completed') $completed++;
        else $incomplete++;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <?php include('../components/header.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลบงานภาคสนามตามเงื่อนไข</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out'
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">

<?php include('../components/sidebar.php'); ?>

<div class="ml-0 md:ml-64 p-4 sm:p-6 lg:p-8">
    <!-- Header Section -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center space-x-3 mb-2">
            <div class="bg-red-100 p-3 rounded-xl">
                <i class="fas fa-search text-red-600 text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">ค้นหาและลบงานภาคสนาม</h1>
                <p class="text-gray-600 mt-1">จัดการข้อมูลงานภาคสนามด้วยระบบค้นหาและลบที่ปลอดภัย</p>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-8 animate-slide-up">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 sm:p-6 rounded-t-2xl border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-filter mr-2 text-blue-600"></i>
                เงื่อนไขในการค้นหา
            </h2>
            <p class="text-sm text-gray-600 mt-1">กรุณาเลือกเงื่อนไขเพื่อค้นหางานที่ต้องการลบ</p>
        </div>
        
        <form method="GET" class="p-4 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- พนักงาน -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-user mr-1 text-blue-500"></i>
                        พนักงานภาคสนาม
                    </label>
                    <select name="user_id" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                        <option value="">-- เลือกพนักงาน --</option>
                        <?php foreach ($fieldUsers as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user['id'] == $selected_user ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- สถานะ -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-tasks mr-1 text-green-500"></i>
                        สถานะงาน
                    </label>
                    <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>ส่งแล้ว</option>
                        <option value="incomplete" <?= $status_filter === 'incomplete' ? 'selected' : '' ?>>ยังไม่ส่ง</option>
                    </select>
                </div>

                <!-- วันที่เริ่มต้น -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-calendar-alt mr-1 text-purple-500"></i>
                        วันที่เริ่มต้น
                    </label>
                    <input type="date" name="date_from" value="<?= $date_from ?>" 
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                </div>

                <!-- วันที่สิ้นสุด -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-calendar-alt mr-1 text-purple-500"></i>
                        วันที่สิ้นสุด
                    </label>
                    <input type="date" name="date_to" value="<?= $date_to ?>" 
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-xl font-medium transition duration-200 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-search mr-2"></i>
                    ค้นหางาน
                </button>
            </div>
        </form>
    </div>

    <?php if ($selected_user && $date_from && $date_to): ?>
        <!-- Summary Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8 animate-slide-up">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-2xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">งานทั้งหมด</p>
                        <p class="text-3xl font-bold"><?= $total ?></p>
                        <p class="text-blue-100 text-sm">รายการ</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                        <i class="fas fa-clipboard-list text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-2xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">ส่งแล้ว</p>
                        <p class="text-3xl font-bold"><?= $completed ?></p>
                        <p class="text-green-100 text-sm">รายการ</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-6 rounded-2xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-100 text-sm font-medium">ยังไม่ส่ง</p>
                        <p class="text-3xl font-bold"><?= $incomplete ?></p>
                        <p class="text-red-100 text-sm">รายการ</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($total > 0): ?>
            <!-- Delete Button -->
            <div class="bg-red-50 border-2 border-red-200 rounded-2xl p-6 mb-8 animate-slide-up">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between space-y-4 sm:space-y-0">
                    <div class="flex items-center space-x-3">
                        <div class="bg-red-100 p-3 rounded-xl">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-red-800">ลบข้อมูลงาน</h3>
                            <p class="text-red-600 text-sm">การลบข้อมูลจะไม่สามารถกู้คืนได้ กรุณาตรวจสอบให้แน่ใจก่อนดำเนินการ</p>
                        </div>
                    </div>
                    
                    <form method="POST" onsubmit="return confirmDelete()" class="flex-shrink-0">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="user_id" value="<?= $selected_user ?>">
                        <input type="hidden" name="status" value="<?= $status_filter ?>">
                        <input type="hidden" name="date_from" value="<?= $date_from ?>">
                        <input type="hidden" name="date_to" value="<?= $date_to ?>">
                        <button name="delete_filter" type="submit"
                                class="bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white px-6 py-3 rounded-xl font-medium transition duration-200 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-trash-alt mr-2"></i>
                            ลบงานทั้งหมด (<?= $total ?> รายการ)
                        </button>
                    </form>
                </div>
            </div>

            <!-- Jobs Table -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 animate-slide-up">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 sm:p-6 rounded-t-2xl border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-list mr-2 text-gray-600"></i>
                        รายการงานที่พบ
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รหัสงาน</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่องาน</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันที่สร้าง</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($jobs as $index => $job): ?>
                                <tr class="hover:bg-gray-50 transition duration-150 <?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-25' ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium text-gray-900">#<?= $job['id'] ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium"><?= htmlspecialchars($job['product']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($job['status'] === 'completed'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                ส่งแล้ว
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                <i class="fas fa-clock mr-1"></i>
                                                ยังไม่ส่ง
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($job['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- No Results -->
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-8 text-center animate-slide-up">
                <div class="bg-yellow-100 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                    <i class="fas fa-search text-yellow-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-yellow-800 mb-2">ไม่พบข้อมูลงาน</h3>
                <p class="text-yellow-600">ไม่มีงานที่ตรงกับเงื่อนไขการค้นหาที่คุณกำหนด กรุณาลองปรับเงื่อนไขใหม่</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- No Search -->
        <div class="bg-blue-50 border-2 border-blue-200 rounded-2xl p-8 text-center animate-slide-up">
            <div class="bg-blue-100 p-4 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                <i class="fas fa-info-circle text-blue-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-blue-800 mb-2">เริ่มต้นการค้นหา</h3>
            <p class="text-blue-600">กรุณาเลือกพนักงาน วันที่เริ่มต้น และวันที่สิ้นสุด เพื่อค้นหางานที่ต้องการลบ</p>
        </div>
    <?php endif; ?>

    <!-- Success Message -->
    <?php if (isset($_SESSION['deleted'])): ?>
        <div id="success-alert" class="fixed bottom-6 right-6 bg-green-500 text-white p-4 rounded-xl shadow-lg animate-slide-up z-50">
            <div class="flex items-center space-x-3">
                <div class="bg-white bg-opacity-20 p-2 rounded-full">
                    <i class="fas fa-check text-lg"></i>
                </div>
                <div>
                    <p class="font-semibold">ลบข้อมูลสำเร็จ</p>
                    <p class="text-sm opacity-90">ลบงานจำนวน <?= $_SESSION['deleted'] ?> รายการเรียบร้อยแล้ว</p>
                </div>
                <button onclick="closeAlert()" class="text-white hover:bg-white hover:bg-opacity-20 p-1 rounded">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['deleted']); ?>
    <?php endif; ?>
</div>

<script>
function confirmDelete() {
    return confirm('คำเตือน!\n\nคุณแน่ใจหรือไม่ที่ต้องการลบงานทั้งหมดตามเงื่อนไขนี้?\nการลบข้อมูลจะไม่สามารถกู้คืนได้\n\nกดตกลงเพื่อดำเนินการต่อ');
}

function closeAlert() {
    document.getElementById('success-alert').style.display = 'none';
}

// Auto close success alert after 5 seconds
setTimeout(() => {
    const alert = document.getElementById('success-alert');
    if (alert) {
        alert.style.animation = 'fadeOut 0.5s ease-in-out';
        setTimeout(() => {
            alert.style.display = 'none';
        }, 500);
    }
}, 5000);

// Add fadeOut animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(20px); }
    }
`;
document.head.appendChild(style);
</script>

</body>
</html>