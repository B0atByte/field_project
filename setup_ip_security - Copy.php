<?php
/**
 * Setup / Management Page for IP Security
 * Allows Admin to authorize dynamic IPs via Cookie (Device Registration).
 */
session_start();

// Database Connection
require_once __DIR__ . '/config/db.php'; // Correct path to DB connection
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/ip_security.php';

// Authenticaton & Authorization Check
$isSetupAllowed = false;
$isAdmin = false;
$statusMessage = "";

// Helper: Get IP (Moved up for immediate use)
// Note: Function defined in includes/ip_security.php which is already required.
$currentIp = getClientIp();

// 2. Admin Logic (Check if user is logged in as Admin)
// Also allow Localhost or specific secure IP to act as Admin for setup purposes
// Added 172.23.1.254 based on your screenshot to prevent lockout
$localIPs = ['127.0.0.1', '::1', '172.23.1.254'];
if ((isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') || in_array($currentIp, $localIPs)) {
    $isSetupAllowed = true;
    $isAdmin = true;
}

// 3. Invite Link Logic (Allow access if valid invite code provided, but NOT ADMIN)
if (isset($_GET['invite'])) {
    $code = realpath_escape_string($conn, $_GET['invite']);

    // Check code ...

    // Create invite table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS device_invites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(64) NOT NULL,
        created_by VARCHAR(100),
        expires_at DATETIME,
        INDEX (code)
    )");

    // Check code
    $stmt = $conn->prepare("SELECT id FROM device_invites WHERE code = ? AND expires_at > NOW()");
    if ($stmt) {
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $isSetupAllowed = true;
            $isAdmin = false; // Invites are for users, not admins (unless Logic changed)
            // Actually, invites just let you see the register form.
        } else {
            $message = "Invalid or Expired Invite Link";
            $msgType = "error";
        }
    }
}

// Helper: Get IP (Function is already in includes/ip_security.php)
// Just call it here.
// $currentIp = getClientIp(); // Moved up

// Handle Form Promotions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ACTION: Generate Invite Link (HTTPS)
    if (isset($_POST['gen_invite']) && $isAdmin) {
        $code = bin2hex(random_bytes(16));
        $expire = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $creator = $_SESSION['user']['username'] ?? 'Admin';

        $conn->query("CREATE TABLE IF NOT EXISTS device_invites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(64) NOT NULL,
            created_by VARCHAR(100),
            expires_at DATETIME,
            INDEX (code)
        )");

        $stmt = $conn->prepare("INSERT INTO device_invites (code, created_by, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $code, $creator, $expire);
        if ($stmt->execute()) {
            // Redirect to same page with GET param to show link nicely
            header("Location: " . $_SERVER['PHP_SELF'] . "?invite_created=1&invite_code=$code");
            exit;
        }
    }

    // ACTION: Add Manual IP (Admin Only)
    if (isset($_POST['add_manual']) && $isAdmin) {
        $ip = trim($_POST['manual_ip']);
        $desc = trim($_POST['manual_desc']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $conn->query("CREATE TABLE IF NOT EXISTS allowed_ips (id INT AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(45), description VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $conn->query("INSERT IGNORE INTO allowed_ips (ip_address, description) VALUES ('$ip', '$desc')");
            $message = "Added IP $ip";
            $msgType = "success";
        }
    }

    // ACTION: Delete IP (Admin Only)
    if (isset($_POST['delete_id']) && $isAdmin) {
        $delId = (int) $_POST['delete_id'];
        $conn->query("DELETE FROM allowed_ips WHERE id = $delId");
        $message = "Deleted IP";
        $msgType = "success";
    }

    // ACTION: Request Device Access (Pending)
    if (isset($_POST['add_device_current'])) {
        initDeviceTable($conn);

        $token = bin2hex(random_bytes(32));
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $desc = isset($_POST['device_desc']) ? trim($_POST['device_desc']) : "Device " . getClientIp();

        // Default status: PENDING
        $status = 'pending';
        if ($isAdmin)
            $status = 'approved';

        $stmt = $conn->prepare("INSERT IGNORE INTO allowed_devices (device_token, description, user_agent, status, last_used_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $token, $desc, $userAgent, $status);

        if ($stmt->execute()) {
            // Set Cookie
            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('device_perm_token', $token, time() + (86400 * 365 * 10), "/", "", $isSecure, true);

            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=req_sent");
            exit;
        } else {
            $message = "Error: " . $stmt->error;
            $msgType = "error";
        }
    }

    // ACTION: Delete Device
    if (isset($_POST['delete_device_id']) && $isAdmin) {
        $delId = (int) $_POST['delete_device_id'];
        $conn->query("DELETE FROM allowed_devices WHERE id = $delId");
        $message = "Deleted Device";
        $msgType = "success";
    }

    // ACTION: Approve Device
    if (isset($_POST['approve_device']) && $isAdmin) {
        $reqId = (int) $_POST['req_id'];
        $conn->query("UPDATE allowed_devices SET status = 'approved' WHERE id = $reqId");
        $message = "Approved Device";
        $msgType = "success";
    }

    // ACTION: Reject Device
    if (isset($_POST['reject_device']) && $isAdmin) {
        $reqId = (int) $_POST['req_id'];
        $conn->query("DELETE FROM allowed_devices WHERE id = $reqId");
        $message = "Rejected Request";
        $msgType = "success";
    }

    if (isset($_GET['msg']) && $_GET['msg'] == 'req_sent') {
        $message = "Request sent. Please wait for Admin approval.";
        $msgType = "success";
    }
}

// Function to escape string for query manually if needed (not used much)
function realpath_escape_string($conn, $str)
{
    return $conn->real_escape_string($str);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Gateway</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', 'Prompt', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex flex-col justify-center py-6 px-4 sm:py-10">

    <?php if (!$isSetupAllowed && !isset($_COOKIE['device_perm_token'])): ?>
        <!-- ACCESS DENIED VIEW -->
        <div class="max-w-md mx-auto w-full bg-white rounded-xl shadow-sm border border-slate-200 p-6 sm:p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-slate-100 mb-4">
                <svg class="h-6 w-6 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h2 class="text-xl font-semibold text-slate-900 mb-2">Access Restricted</h2>
            <p class="text-slate-500 text-sm mb-6">
                ระบบจำกัดการเข้าถึงเฉพาะอุปกรณ์ที่ได้รับอนุญาต<br>
                กรุณาใช้ <b>"ลิงก์เชิญ" (Invite Link)</b> ที่ได้รับจาก Admin
            </p>
            <a href="javascript:history.back()"
                class="text-sm font-medium text-slate-600 hover:text-slate-900 py-3 block sm:inline-block">
                &larr; กลับหน้าพนักงาน
            </a>
        </div>
    <?php else: ?>

        <div class="max-w-3xl mx-auto w-full space-y-6 sm:space-y-8">

            <!-- Logo / Header -->
            <div class="text-center">
                <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Security Gateway</h1>
                <p class="text-slate-500 text-sm mt-1">Device Registration & Access Control</p>
            </div>

            <!-- Message Alert -->
            <?php if (isset($message) && $message): ?>
                <div
                    class="rounded-lg p-4 <?php echo isset($msgType) && $msgType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100'; ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <?php if (isset($msgType) && $msgType === 'success'): ?>
                                <svg class="h-5 w-5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            <?php else: ?>
                                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd" />
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium break-words"><?php echo $message; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- MAIN CARD: Device Registration -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 sm:p-8">
                    <?php
                    $myStatus = 'none';
                    if (isset($_COOKIE['device_perm_token'])) {
                        $token = $_COOKIE['device_perm_token'];
                        initDeviceTable($conn);
                        $stmt = $conn->prepare("SELECT status FROM allowed_devices WHERE device_token = ?");
                        if ($stmt) {
                            $stmt->bind_param("s", $token);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            if ($res->num_rows > 0) {
                                $row = $res->fetch_assoc();
                                $myStatus = $row['status'] ?? 'approved';
                            }
                        }
                    }
                    ?>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
                        <div>
                            <h3 class="text-lg font-medium text-slate-900">Device Status</h3>
                            <p class="text-slate-500 text-sm">ตรวจสอบสถานะอุปกรณ์ของคุณ</p>
                        </div>
                        <div>
                            <?php if ($myStatus === 'approved'): ?>
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                    Approved
                                </span>
                            <?php elseif ($myStatus === 'pending'): ?>
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                    Pending Approval
                                </span>
                            <?php else: ?>
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                                    Not Registered
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($myStatus === 'approved'): ?>
                        <!-- APPROVED STATE -->
                        <div class="text-center py-6">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-4">
                                <svg class="h-8 w-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h4 class="text-lg font-medium text-slate-900 mb-2">อุปกรณ์ได้รับอนุมัติแล้ว</h4>
                            <p class="text-slate-500 text-sm mb-6">คุณสามารถเข้าใช้งานระบบได้ตามปกติ</p>

                            <a href="https://180.183.247.29:7080/"
                                class="block w-full sm:w-auto sm:inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-slate-900 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors">
                                เข้าสู่ระบบหลัก (Go to Main System) &rarr;
                            </a>
                        </div>

                    <?php elseif ($myStatus === 'pending'): ?>
                        <!-- PENDING STATE -->
                        <div class="text-center py-6">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-50 mb-4">
                                <svg class="h-8 w-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h4 class="text-lg font-medium text-slate-900 mb-1">รอการอนุมัติ (Waiting for Approval)</h4>
                            <p class="text-slate-500 text-sm">คำขอของคุณถูกส่งแล้ว กรุณารอ Admin อนุมัติสิทธิ์</p>
                        </div>

                    <?php else: ?>
                        <!-- REGISTER FORM -->
                        <?php if ($isSetupAllowed): ?>
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Device Name</label>
                                    <input type="text" name="device_desc" placeholder="e.g. Somchai iPhone, Site Laptop"
                                        class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-base py-3 px-3 border"
                                        required>
                                </div>
                                <button type="submit" name="add_device_current"
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-slate-900 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors">
                                    Request Access
                                </button>
                                <p class="text-xs text-slate-400 text-center mt-2">
                                    By clicking Request, you agree to register this device for system access.
                                </p>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-slate-500 text-sm">Access link invalid or expired.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ADMIN PANEL (Only visible to Admins) -->
            <?php if ($isAdmin): ?>
                <div class="border-t border-slate-200 pt-8 mt-8">
                    <h3 class="text-lg font-semibold text-slate-900 mb-4 px-1">Administrator Panel</h3>

                    <!-- Generate Invite -->
                    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-5 mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-3">Create Invitation Link</label>
                        <form method="POST" class="flex flex-col sm:flex-row gap-3">
                            <button type="submit" name="gen_invite"
                                class="w-full sm:w-auto bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors">
                                Generate Link (HTTPS)
                            </button>
                            <?php if (isset($_GET['invite_created'])):
                                $link = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?invite=" . htmlspecialchars($_GET['invite_code']);
                                ?>
                                <input type="text" readonly value="<?php echo $link; ?>" onclick="this.select()"
                                    class="w-full rounded-lg border-slate-300 bg-slate-50 text-slate-600 text-sm px-3 py-2.5 focus:ring-0">
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Pending Requests -->
                    <?php
                    initDeviceTable($conn); // Ensure table exists before querying
                    $pending = $conn->query("SELECT * FROM allowed_devices WHERE status = 'pending' ORDER BY created_at ASC");
                    if ($pending && $pending->num_rows > 0):
                        ?>
                        <div class="mb-6">
                            <div class="flex items-center mb-2 px-1">
                                <div class="h-2 w-2 rounded-full bg-amber-500 mr-2"></div>
                                <h4 class="text-sm font-medium text-slate-900">Pending Requests</h4>
                            </div>
                            <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
                                <ul class="divide-y divide-slate-100">
                                    <?php while ($req = $pending->fetch_assoc()): ?>
                                        <li class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-medium text-slate-900">
                                                    <?php echo htmlspecialchars($req['description']); ?>
                                                </p>
                                                <p class="text-xs text-slate-500 break-all">
                                                    <?php echo htmlspecialchars($req['user_agent']); ?>
                                                </p>
                                                <p class="text-xs text-slate-400 mt-1"><?php echo $req['created_at']; ?></p>
                                            </div>
                                            <div class="flex space-x-2 w-full sm:w-auto">
                                                <form method="POST" class="w-1/2 sm:w-auto">
                                                    <input type="hidden" name="req_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" name="approve_device"
                                                        class="w-full sm:w-auto text-center bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-100 text-sm font-medium px-4 py-2 rounded-lg transition">Approve</button>
                                                </form>
                                                <form method="POST" class="w-1/2 sm:w-auto"
                                                    onsubmit="return confirm('Reject this request?');">
                                                    <input type="hidden" name="req_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" name="reject_device"
                                                        class="w-full sm:w-auto text-center bg-red-50 text-red-600 hover:bg-red-100 border border-red-100 text-sm font-medium px-4 py-2 rounded-lg transition">Reject</button>
                                                </form>
                                            </div>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- All Approved -->
                    <div>
                        <h4 class="text-sm font-medium text-slate-900 mb-2 px-1">Approved Devices</h4>
                        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
                            <ul class="divide-y divide-slate-100 max-h-60 overflow-y-auto">
                                <?php
                                $allDev = $conn->query("SELECT * FROM allowed_devices WHERE status='approved' OR status IS NULL ORDER BY last_used_at DESC");
                                while ($d = $allDev->fetch_assoc()):
                                    ?>
                                    <li class="px-4 py-3 flex justify-between items-center hover:bg-slate-50">
                                        <div class="overflow-hidden mr-3">
                                            <div class="text-sm font-medium text-slate-700">
                                                <?php echo htmlspecialchars($d['description']); ?>
                                            </div>
                                            <div class="text-xs text-slate-400 truncate">
                                                <?php echo htmlspecialchars($d['user_agent']); ?>
                                            </div>
                                        </div>
                                        <form method="POST" onsubmit="return confirm('Remove access?');">
                                            <input type="hidden" name="delete_device_id" value="<?php echo $d['id']; ?>">
                                            <button class="text-slate-400 hover:text-red-500 text-xs p-2">Remove</button>
                                        </form>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- APPROVED IPS -->
                    <div class="mt-8">
                        <h4 class="text-sm font-medium text-slate-900 mb-2 px-1">Fixed IPs (Server/Office)</h4>
                        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-5">
                            <!-- Simple Form for Manual IP -->
                            <form method="POST" class="flex flex-col sm:flex-row gap-2 mb-4">
                                <input type="text" name="manual_ip" placeholder="IP Address"
                                    class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2.5 border"
                                    required>
                                <input type="text" name="manual_desc" placeholder="Details"
                                    class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:text-sm px-3 py-2.5 border"
                                    required>
                                <button type="submit" name="add_manual"
                                    class="w-full sm:w-auto bg-slate-900 text-white px-4 py-2.5 rounded-lg text-sm hover:bg-slate-800 transition">Add</button>
                            </form>

                            <ul class="divide-y divide-slate-100">
                                <?php
                                $ips = $conn->query("SELECT * FROM allowed_ips ORDER BY created_at DESC");
                                while ($ip = $ips->fetch_assoc()):
                                    ?>
                                    <li class="py-3 flex justify-between items-center text-sm">
                                        <div class="overflow-hidden mr-2">
                                            <span
                                                class="block font-mono font-bold text-slate-700"><?php echo htmlspecialchars($ip['ip_address']); ?></span>
                                            <span
                                                class="text-slate-500 text-xs block truncate"><?php echo htmlspecialchars($ip['description']); ?></span>
                                        </div>
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete IP?');">
                                            <input type="hidden" name="delete_id" value="<?php echo $ip['id']; ?>">
                                            <button class="text-slate-400 hover:text-red-500 text-xs p-2">Remove</button>
                                        </form>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                    </div>

                </div>
            <?php endif; ?>

            <div class="text-center pt-8 pb-10">
                <a href="index.php" class="text-xs text-slate-400 hover:text-slate-600 p-3">Back to Home</a>
            </div>

        </div>
    <?php endif; ?>
</body>

</html>