<?php
/**
 * Permission Helper Functions
 * ระบบตรวจสอบสิทธิ์แบบ Granular
 *
 * Admin role bypasses all permission checks (full access).
 * Manager and Field roles require explicit permission grants stored in user_permissions table.
 */

/**
 * รายการ permissions ทั้งหมดในระบบ (whitelist)
 */
function getAllPermissions(): array {
    return [
        'pages' => [
            'page_jobs'     => 'หน้าข้อมูลงาน',
            'page_import'   => 'หน้านำเข้างาน',
            'page_map'      => 'หน้าแผนที่',
            'page_logs'     => 'หน้า Logs',
            'page_users'    => 'หน้าจัดการผู้ใช้',
            'page_settings' => 'หน้าตั้งค่าระบบ',
        ],
        'jobs' => [
            'action_add_job'          => 'เพิ่มงานใหม่',
            'action_edit_job'         => 'แก้ไขงาน',
            'action_delete_job'       => 'ลบงาน (รายการ)',
            'action_delete_jobs_bulk' => 'ลบงานทั้งหมด (Bulk)',
        ],
        'export' => [
            'action_export_excel' => 'Export Excel',
            'action_export_word'  => 'Export Word',
            'action_export_pdf'   => 'Export PDF',
        ],
        'manage' => [
            'action_import_jobs'  => 'นำเข้างานจาก Excel',
            'action_manage_users' => 'จัดการผู้ใช้งาน',
            'action_view_logs'    => 'ดู Logs ระบบ',
        ],
    ];
}

/**
 * ตรวจสอบว่า user ปัจจุบันมีสิทธิ์ที่ระบุหรือไม่
 * Admin role มีสิทธิ์ทั้งหมดโดยอัตโนมัติ
 */
function hasPermission(string $perm): bool {
    if (!isset($_SESSION['user'])) return false;
    if ($_SESSION['user']['role'] === 'admin') return true;
    $perms = $_SESSION['user']['permissions'] ?? [];
    return in_array($perm, $perms, true);
}

/**
 * บังคับให้มีสิทธิ์ ถ้าไม่มีให้ redirect ออก
 */
function requirePermission(string $perm): void {
    if (!isset($_SESSION['user'])) {
        header("Location: /index.php");
        exit;
    }
    if (!hasPermission($perm)) {
        header("Location: /index.php?error=no_permission");
        exit;
    }
}

/**
 * โหลด permissions ของ user จาก DB
 * ใช้ใน login.php และ session_config.php
 */
function loadUserPermissions(mysqli $conn, int $userId): array {
    $perm_stmt = $conn->prepare("SELECT permission FROM user_permissions WHERE user_id = ?");
    if (!$perm_stmt) return [];
    $perm_stmt->bind_param("i", $userId);
    $perm_stmt->execute();
    $res = $perm_stmt->get_result();
    $permissions = [];
    while ($p = $res->fetch_assoc()) {
        $permissions[] = $p['permission'];
    }
    $perm_stmt->close();
    return $permissions;
}
