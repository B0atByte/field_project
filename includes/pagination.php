<?php
class Pagination {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * ดึงข้อมูล login logs แบบมี pagination
     */
    public function getLoginLogs($page = 1, $per_page = 25, $filters = []) {
        $offset = ($page - 1) * $per_page;
        
        $where_conditions = ["1=1"];
        $params = [];
        $types = '';
        
        // Filter by user
        if (!empty($filters['user'])) {
            $where_conditions[] = "u.name LIKE ?";
            $params[] = "%{$filters['user']}%";
            $types .= 's';
        }
        
        // Filter by date
        if (!empty($filters['date'])) {
            $where_conditions[] = "DATE(l.login_time) = ?";
            $params[] = $filters['date'];
            $types .= 's';
        }
        
        // Filter by type
        if (!empty($filters['type'])) {
            $where_conditions[] = "l.type = ?";
            $params[] = $filters['type'];
            $types .= 's';
        }
        
        // Filter by date range (แทนที่จะดึงข้อมูลทั้งหมด)
        if (empty($filters['date']) && empty($filters['start_date'])) {
            $where_conditions[] = "l.login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
        
        $where = implode(' AND ', $where_conditions);
        
        // Count total
        $count_sql = "SELECT COUNT(*) as total 
                      FROM login_logs l 
                      JOIN users u ON l.user_id = u.id 
                      WHERE $where";
        
        $stmt = $this->conn->prepare($count_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        
        // Get data
        $sql = "SELECT l.*, u.name AS user_name
                FROM login_logs l
                JOIN users u ON l.user_id = u.id
                WHERE $where
                ORDER BY l.login_time DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return [
            'data' => $result->fetch_all(MYSQLI_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }
    
    /**
     * ดึงข้อมูล job edit logs แบบมี pagination
     */
    public function getJobEditLogs($page = 1, $per_page = 25, $filters = []) {
        $offset = ($page - 1) * $per_page;
        
        $where_conditions = ["1=1"];
        $params = [];
        $types = '';
        
        if (!empty($filters['job_id'])) {
            $where_conditions[] = "j.job_id = ?";
            $params[] = $filters['job_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['editor'])) {
            $where_conditions[] = "u.name LIKE ?";
            $params[] = "%{$filters['editor']}%";
            $types .= 's';
        }
        
        if (!empty($filters['start_date'])) {
            $where_conditions[] = "DATE(j.edited_at) >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = "DATE(j.edited_at) <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        // Default: เฉพาะ 60 วันล่าสุด
        if (empty($filters['start_date']) && empty($filters['end_date'])) {
            $where_conditions[] = "j.edited_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)";
        }
        
        $where = implode(' AND ', $where_conditions);
        
        // Count
        $count_sql = "SELECT COUNT(*) as total 
                      FROM job_edit_logs j 
                      LEFT JOIN users u ON j.edited_by = u.id 
                      WHERE $where";
        
        $stmt = $this->conn->prepare($count_sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        
        // Get data
        $sql = "SELECT j.job_id, j.change_summary, j.edited_at, u.name AS editor_name
                FROM job_edit_logs j
                LEFT JOIN users u ON j.edited_by = u.id
                WHERE $where
                ORDER BY j.edited_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return [
            'data' => $result->fetch_all(MYSQLI_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    }
}