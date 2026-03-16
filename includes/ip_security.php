<?php
/**
 * IP Address Security Helper
 * Handles IP detection and Whitelist checking
 */

/**
 * Get the real client IP address, trusting X-Forwarded-For from Caddy
 */
function getClientIp()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // X-Forwarded-For can be a comma-separated list of IPs.
        // The first one is the original client IP.
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Check if the current client is allowed to access the system
 * @param mysqli $conn Database connection
 * @return bool True if allowed, False if denied
 */
function checkIpAccess($conn)
{
    if (!$conn || $conn->connect_error) {
        // If DB is down, fail open (allow) or closed (deny)?
        // Fail open allows debugging. Fail closed is more secure.
        // Letting it fail open for now to avoid accidental lockouts during DB issues.
        return true;
    }

    $ip = getClientIp();

    // 1. Always allow localhost (Loopback)
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }

    // 2. Check if the 'allowed_ips' table exists
    // We cache this check slightly by assuming if query fails, table missing.
    // Or use explicit check:
    $result = $conn->query("SHOW TABLES LIKE 'allowed_ips'");
    if (!$result || $result->num_rows === 0) {
        return true; // Table does not exist yet -> specific security not enabled
    }

    // 3. Check if Security is Enabled (Has IPs or Devices in whitelist)
    $ipCount = 0;
    $r = $conn->query("SELECT COUNT(*) FROM allowed_ips");
    if ($r)
        $ipCount = $r->fetch_row()[0];

    // Check device count safely
    $deviceCount = 0;
    $chkTable = $conn->query("SHOW TABLES LIKE 'allowed_devices'");
    if ($chkTable && $chkTable->num_rows > 0) {
        $r2 = $conn->query("SELECT COUNT(*) FROM allowed_devices");
        if ($r2)
            $deviceCount = $r2->fetch_row()[0];
    }

    if ($ipCount == 0 && $deviceCount == 0) {
        return true; // Whitelist empty -> Allow everyone (Setup mode)
    }

    // 4. Check if the specific IP is allowed
    $stmt = $conn->prepare("SELECT id FROM allowed_ips WHERE ip_address = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $res = $stmt->get_result();
        $allowed = $res->num_rows > 0;
        $stmt->close();
        if ($allowed)
            return true;
    }

    // 4. Check Allowed Devices (Cookie Token) - with Status Check
    if (checkDeviceAccess($conn)) {
        return true; // Access Granted
    }

    // --- ACCESS DENIED UI ---
    // Instead of simple text, we render a nice checking page

    // Check Status for better UI message
    $statusUI = 'denied'; // denied, pending, approved (technically approved returns true above, but maybe DB lag?)

    if (isset($_COOKIE['device_perm_token'])) {
        $token = $_COOKIE['device_perm_token'];
        // We need to re-query status specifically to show user
        // (Re-using conn since checkDeviceAccess might have failed or pending)
        initDeviceTable($conn);
        $stmt = $conn->prepare("SELECT status FROM allowed_devices WHERE device_token = ?");
        if ($stmt) {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $statusUI = $row['status'] ?? 'approved';
            }
        }
    }

    // Force HTTPS for the redirect link if needed
    $redirectUrl = "https://180.183.247.29:7080/";

    http_response_code(403);
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
        <?php if ($statusUI === 'approved'): ?>
            <meta http-equiv="refresh" content="3;url=<?php echo $redirectUrl; ?>">
        <?php elseif ($statusUI === 'pending'): ?>
            <meta http-equiv="refresh" content="10">
        <?php endif; ?>
    </head>

    <body class="bg-slate-50 min-h-screen flex flex-col items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-sm border border-slate-200 p-8 text-center">

            <?php if ($statusUI === 'approved'): ?>
                <!-- APPROVED UI -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                    <svg class="h-8 w-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-2xl font-semibold text-slate-800 mb-2">Access Granted</h2>
                <p class="text-slate-500 mb-8">สิทธิ์ของคุณได้รับการอนุมัติแล้ว<br>กำลังเข้าสู่ระบบหลัก...</p>
                <a href="<?php echo $redirectUrl; ?>"
                    class="block w-full py-3 px-4 rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors">
                    เข้าสู่ระบบหลัก (Go to Main System)
                </a>

            <?php elseif ($statusUI === 'pending'): ?>
                <!-- PENDING UI -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-50 mb-6 animate-pulse">
                    <svg class="h-8 w-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-slate-800 mb-2">Waiting for Approval</h2>
                <p class="text-slate-500 mb-8">
                    คำขอของคุณอยู่ระหว่างการตรวจสอบ<br>
                    <span class="text-xs">หน้านี้จะรีเฟรชอัตโนมัติทุก 10 วินาที</span>
                </p>
                <button disabled
                    class="block w-full py-3 px-4 rounded-md border border-slate-200 text-sm font-medium text-slate-400 bg-slate-50 cursor-not-allowed">
                    รอการอนุมัติ (Pending...)
                </button>

            <?php else: ?>
                <!-- DENIED UI -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-slate-100 mb-6">
                    <svg class="h-8 w-8 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-slate-800 mb-2">Access Restricted</h2>
                <p class="text-slate-500 mb-8 max-w-xs mx-auto text-sm">
                    IP นี้ยังไม่ได้รับอนุญาต<br>
                    กรุณาติดต่อ Admin เพื่อขอ Invite Link
                </p>
                <div class="space-y-3">
                    <p class="text-xs text-slate-400">IP: <?php echo getClientIp(); ?></p>
                </div>
            <?php endif; ?>

        </div>
    </body>

    </html>
    <?php
    exit; // Stop execution of the main page
}

/**
 * Ensure the allowed_devices table exists and has necessary columns
 */
function initDeviceTable($conn)
{
    // 1. Create table if not exists with status column
    $sql = "CREATE TABLE IF NOT EXISTS allowed_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_token VARCHAR(255) NOT NULL,
        description VARCHAR(255),
        user_agent TEXT,
        status VARCHAR(20) DEFAULT 'approved', -- approved, pending
        last_used_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (device_token)
    )";
    $conn->query($sql);

    // 2. Migration: Add status column if it doesn't exist (for existing tables)
    $checkCol = $conn->query("SHOW COLUMNS FROM allowed_devices LIKE 'status'");
    if ($checkCol && $checkCol->num_rows === 0) {
        $conn->query("ALTER TABLE allowed_devices ADD COLUMN status VARCHAR(20) DEFAULT 'approved'");
    }
}

/**
 * Check if the client provides a valid Device Authorization Token
 */
function checkDeviceAccess($conn)
{
    if (!isset($_COOKIE['device_perm_token'])) {
        return false;
    }

    $token = $_COOKIE['device_perm_token'];

    // Ensure table exists (Lazy check)
    $stmt = $conn->prepare("SELECT id, status FROM allowed_devices WHERE device_token = ? LIMIT 1");
    if (!$stmt) {
        initDeviceTable($conn);
        $stmt = $conn->prepare("SELECT id, status FROM allowed_devices WHERE device_token = ? LIMIT 1");
    }

    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            // Allow if status is 'approved' OR if column is missing/null (legacy support)
            if (!isset($row['status']) || $row['status'] === 'approved') {
                $stmt->close();
                // Update last_used
                $conn->query("UPDATE allowed_devices SET last_used_at = NOW() WHERE device_token = '" . $conn->real_escape_string($token) . "'");
                return true;
            }
        }
        $stmt->close();
    }

    return false;
}
?>