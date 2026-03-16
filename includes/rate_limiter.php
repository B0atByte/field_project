<?php
/**
 * Rate Limiter - ป้องกัน Brute Force Attack
 * จำกัดจำนวนครั้งในการพยายาม login
 */

require_once __DIR__ . '/../config/env.php';

class RateLimiter {
    private $conn;
    private $maxAttempts;
    private $lockoutTime;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->maxAttempts = (int)env('MAX_LOGIN_ATTEMPTS', 5);
        $this->lockoutTime = (int)env('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
    }

    /**
     * ตรวจสอบว่า IP นี้ถูก lock หรือไม่
     */
    public function isLocked($ipAddress) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempt_count, MAX(attempted_at) as last_attempt
            FROM login_attempts
            WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->bind_param("si", $ipAddress, $this->lockoutTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row['attempt_count'] >= $this->maxAttempts) {
            $lockTimeRemaining = $this->lockoutTime - (time() - strtotime($row['last_attempt']));
            return [
                'locked' => true,
                'remaining' => max(0, $lockTimeRemaining),
                'attempts' => $row['attempt_count']
            ];
        }

        return [
            'locked' => false,
            'attempts' => $row['attempt_count']
        ];
    }

    /**
     * บันทึกการพยายาม login ที่ล้มเหลว
     */
    public function recordFailedAttempt($ipAddress, $username) {
        $stmt = $this->conn->prepare("
            INSERT INTO login_attempts (ip_address, username, attempted_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("ss", $ipAddress, $username);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * ล้างประวัติการพยายาม login เมื่อ login สำเร็จ
     */
    public function clearAttempts($ipAddress) {
        $stmt = $this->conn->prepare("
            DELETE FROM login_attempts WHERE ip_address = ?
        ");
        $stmt->bind_param("s", $ipAddress);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * ล้างประวัติเก่าที่หมดอายุแล้ว (เรียกเป็นระยะ)
     */
    public function cleanupOldAttempts() {
        $stmt = $this->conn->prepare("
            DELETE FROM login_attempts
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->bind_param("i", $this->lockoutTime);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * สร้างตาราง login_attempts ถ้ายังไม่มี
 */
function createLoginAttemptsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(255),
        attempted_at DATETIME NOT NULL,
        INDEX idx_ip_time (ip_address, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql);
}
?>
