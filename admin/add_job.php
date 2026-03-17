<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
requirePermission('action_add_job');

include '../config/db.php';
require_once '../includes/csrf.php';

// เช็คคอลัมน์ auto_delete
function hasColumn(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}

$has_auto_delete_days = hasColumn($conn, 'jobs', 'auto_delete_days');
$has_auto_delete_at   = hasColumn($conn, 'jobs', 'auto_delete_at');

$imported_by   = $_SESSION['user']['id'] ?? null;
$department_id = null;

if ($imported_by) {
    $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $imported_by);
    $stmt->execute();
    $stmt->bind_result($department_id);
    $stmt->fetch();
    $stmt->close();
}

// ดึงพนักงานภาคสนาม
$field_staff = [];
$stmt = $conn->prepare("SELECT id, name FROM users WHERE role = 'field' ORDER BY name");
$stmt->execute();
$stmt->bind_result($staff_id, $staff_name);
while ($stmt->fetch()) {
    $field_staff[] = ['id' => $staff_id, 'name' => $staff_name];
}
$stmt->close();

$success_msg = '';
$error_msg   = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireCsrfToken();
    $product          = $_POST['product'] ?? '';
    $contract_number  = $_POST['contract_number'] ?? '';
    $customer_id_card = $_POST['customer_id_card'] ?? '';
    $customer_name    = $_POST['customer_name'] ?? '';
    $location_area    = $_POST['location_area'] ?? '';
    $zone             = $_POST['zone'] ?? '';
    $due_date         = $_POST['due_date'] ?? '';
    $overdue          = $_POST['overdue_period'] ?? '';
    $model            = $_POST['model'] ?? '';
    $model_detail     = $_POST['model_detail'] ?? '-';
    $color            = $_POST['color'] ?? '';
    $plate            = $_POST['plate'] ?? '';
    $province         = $_POST['province'] ?? '';
    $os               = $_POST['os'] ?? '';
    $assignee_name    = $_POST['assigned_name'] ?? '';

    $auto_delete_days = isset($_POST['auto_delete_days']) ? (int)$_POST['auto_delete_days'] : 0;
    $auto_delete_days = max(0, min(7, $auto_delete_days));
    $auto_delete_at   = $auto_delete_days > 0 ? date('Y-m-d 23:59:59', strtotime("+$auto_delete_days days")) : null;

    $assigned_to = null;
    if (!empty($assignee_name)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE name = ?");
        $stmt->bind_param("s", $assignee_name);
        $stmt->execute();
        $stmt->bind_result($assignee_id);
        if ($stmt->fetch()) {
            $assigned_to = $assignee_id;
        } else {
            $error_msg = "ไม่พบชื่อผู้รับงาน: $assignee_name";
        }
        $stmt->close();
    }

    if (empty($product) || empty($contract_number) || empty($customer_name)) {
        $error_msg = 'กรุณากรอก Product, สัญญา และ ชื่อลูกค้า';
    }

    if (empty($error_msg)) {
        $location_info = $customer_name;
        $status        = 'pending';

        $fields = [
            'product', 'contract_number', 'customer_id_card', 'location_info', 'location_area', 'zone',
            'due_date', 'overdue_period', 'model', 'model_detail', 'color',
            'plate', 'province', 'os', 'assigned_to', 'imported_by', 'department_id', 'status'
        ];
        $types  = 'ssssssssssssssiiis';
        $values = [
            $product, $contract_number, $customer_id_card, $location_info, $location_area, $zone,
            $due_date, $overdue, $model, $model_detail, $color,
            $plate, $province, $os, $assigned_to, $imported_by, $department_id, $status
        ];

        if ($has_auto_delete_days) {
            $fields[] = 'auto_delete_days';
            $types   .= 'i';
            $values[] = $auto_delete_days;
        }

        if ($has_auto_delete_at) {
            $fields[] = 'auto_delete_at';
            $types   .= 's';
            $values[] = $auto_delete_at;
        }

        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO jobs (" . implode(',', $fields) . ") VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);

        $bind = [];
        $bind[] = &$types;
        foreach ($values as $k => $v) {
            $bind[] = &$values[$k];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind);

        if ($stmt->execute()) {
            $success_msg = "บันทึกงานสำเร็จ";
        } else {
            $error_msg = "เกิดข้อผิดพลาด: " . $stmt->error;
        }

        $stmt->close();
    }
}

$page_title = "เพิ่มงานใหม่";
include '../components/header.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Field Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }

        /* Floating Input Style */
        .floating-input {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-input input,
        .floating-input textarea,
        .floating-input select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #ffffff;
            font-size: 15px;
            transition: all 0.2s ease;
            color: #1f2937;
        }

        .floating-input input:focus,
        .floating-input textarea:focus,
        .floating-input select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
            background: #ffffff;
        }

        .floating-input label {
            position: absolute;
            left: 1rem;
            top: 0.875rem;
            background: white;
            padding: 0 0.375rem;
            color: #6b7280;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s ease;
            pointer-events: none;
        }

        .floating-input input:focus + label,
        .floating-input textarea:focus + label,
        .floating-input select:focus + label,
        .floating-input input:not(:placeholder-shown) + label,
        .floating-input textarea:not(:placeholder-shown) + label,
        .floating-input select:not([value=""]) + label {
            top: -0.5rem;
            left: 0.75rem;
            font-size: 13px;
            color: #3b82f6;
            font-weight: 500;
        }

        .floating-input.required label::after {
            content: " *";
            color: #dc2626;
            font-weight: 600;
        }

        /* Card Styling */
        .form-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .form-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        /* Section Headers */
        .section-header {
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
        }

        /* Button Styling */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn-primary:hover {
            background: #2563eb;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-outline {
            background: #ffffff;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* Animation */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-in {
            animation: slideInUp 0.4s ease-out;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .form-card {
                margin: 1rem;
                border-radius: 6px;
            }

            .floating-input {
                margin-bottom: 1.25rem;
            }

            .grid-cols-2 {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                margin-bottom: 0.75rem;
            }
        }

        /* Success/Error Messages */
        .message-card {
            border-radius: 6px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid;
        }

        .message-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        .message-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }
    </style>
</head>

<body>
    <div class="flex">
        <?php include '../components/sidebar.php'; ?>

        <main class="flex-1 p-4 md:p-8 ml-0 md:ml-64">
            <div class="max-w-6xl mx-auto">
                
                <!-- Header Card -->
                <div class="form-card p-6 mb-6 animate-slide-in">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">
                                เพิ่มงานใหม่
                            </h1>
                            <p class="text-gray-600 mt-1 text-sm">สร้างงานใหม่เพื่อเพิ่มเข้าสู่ระบบจัดการ</p>
                        </div>
                        <div class="flex gap-3">
                            <a href="<?= $_SESSION['user']['role'] === 'manager' ? '../dashboard/manager.php' : '../dashboard/admin.php' ?>"
                               class="btn btn-outline">
                                กลับแดชบอร์ด
                            </a>
                            <a href="import_jobs.php" class="btn btn-primary">
                                นำเข้าจากไฟล์
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($success_msg): ?>
                    <div class="message-card message-success animate-slide-in">
                        <div class="flex items-center gap-3">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <span class="font-semibold"><?= htmlspecialchars($success_msg) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="message-card message-error animate-slide-in">
                        <div class="flex items-center gap-3">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span class="font-semibold"><?= htmlspecialchars($error_msg) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" id="jobForm" class="space-y-5">
                    <?= csrfField() ?>

                    <!-- ข้อมูลสัญญา -->
                    <div class="form-card p-6 animate-slide-in" style="animation-delay: 0.1s">
                        <h2 class="section-header">ข้อมูลสัญญา</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="floating-input required">
                                <input type="text" name="product" required placeholder=" ">
                                <label>แผนก</label>
                            </div>
                            <div class="floating-input required">
                                <input type="text" name="contract_number" required placeholder=" ">
                                <label>เลขที่สัญญา</label>
                            </div>
                            <div class="floating-input">
                                <input type="text" name="customer_id_card" placeholder=" " maxlength="13">
                                <label>เลขบัตรประชาชน</label>
                            </div>
                            <div class="floating-input">
                                <input type="date" name="due_date" placeholder=" " min="<?= date('Y-m-d') ?>">
                                <label>วันครบกำหนด</label>
                            </div>
                            <div class="floating-input">
                                <input type="text" name="overdue_period" placeholder=" ">
                                <label>จำนวนวันเกินกำหนด</label>
                            </div>
                        </div>
                    </div>

                    <!-- ข้อมูลลูกค้า -->
                    <div class="form-card p-6 animate-slide-in" style="animation-delay: 0.2s">
                        <h2 class="section-header">ข้อมูลลูกค้า</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="floating-input required">
                                <input type="text" name="customer_name" required placeholder=" ">
                                <label>ชื่อ-สกุล ลูกค้า</label>
                            </div>
                            <div class="floating-input">
                                <input type="text" name="zone" placeholder=" ">
                                <label>โซน</label>
                            </div>
                            <div class="floating-input md:col-span-2">
                                <textarea name="location_area" placeholder=" " rows="4" style="resize: none;"></textarea>
                                <label>ที่อยู่</label>
                            </div>
                            <div class="floating-input">
                                <input type="text" name="province" placeholder=" ">
                                <label>จังหวัด</label>
                            </div>
                            <div class="floating-input">
                                <input type="text" name="os" placeholder=" ">
                                <label>ยอดคงเหลือ OS</label>
                            </div>
                        </div>
                    </div>

                    <!-- ข้อมูลยานพาหนะ -->
                    <div class="form-card p-6 animate-slide-in" style="animation-delay: 0.3s">
                        <h2 class="section-header">ข้อมูลยานพาหนะ</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="floating-input">
                                <input type="text" name="model" placeholder=" ">
                                <label>ยี่ห้อ</label>
                            </div>
                            <div class="floating-input">
                                <input type="text" name="model_detail" placeholder=" ">
                                <label>รุ่น</label>
                            </div>
                            <div class="floating-input">
                                <input type="text" name="color" placeholder=" ">
                                <label>สี</label>
                            </div>
                            <div class="floating-input">
                                <input type="text" name="plate" placeholder=" ">
                                <label>ทะเบียน</label>
                            </div>
                        </div>
                    </div>

                    <!-- การมอบหมายงาน -->
                    <div class="form-card p-6 animate-slide-in" style="animation-delay: 0.4s">
                        <h2 class="section-header">การมอบหมายงาน</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="floating-input">
                                <select name="assigned_name">
                                    <option value="">-- เลือกพนักงานภาคสนาม --</option>
                                    <?php foreach ($field_staff as $staff): ?>
                                        <option value="<?= htmlspecialchars($staff['name']) ?>">
                                            <?= htmlspecialchars($staff['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label>ผู้รับงาน</label>
                            </div>
                            
                            <div class="floating-input">
                                <select name="auto_delete_days">
                                    <option value="0">ไม่ลบอัตโนมัติ</option>
                                    <?php for ($i = 1; $i <= 7; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?> วัน</option>
                                    <?php endfor; ?>
                                </select>
                                <label>ลบอัตโนมัติ</label>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 text-sm text-gray-600">
                            <p>หากไม่เลือกผู้รับงาน งานจะอยู่ในสถานะรอรับ</p>
                            <p>ระบบจะลบงานอัตโนมัติหากไม่มีการดำเนินการ</p>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="form-card p-6 animate-slide-in" style="animation-delay: 0.5s">
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                บันทึกงาน
                            </button>
                            <button type="button" onclick="resetForm()" class="btn btn-outline">
                                รีเซ็ตฟอร์ม
                            </button>
                            <button type="button" onclick="showPreview()" class="btn btn-secondary">
                                ดูตัวอย่าง
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <?php include '../components/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initializeForm();
        handleFloatingLabels();
        
        <?php if ($success_msg): ?>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: '<?= addslashes($success_msg) ?>',
            confirmButtonColor: '#3b82f6'
        });
        <?php endif; ?>

        <?php if ($error_msg): ?>
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: '<?= addslashes($error_msg) ?>',
            confirmButtonColor: '#3b82f6'
        });
        <?php endif; ?>
    });

    function handleFloatingLabels() {
        // Handle floating labels for inputs that may have values
        document.querySelectorAll('.floating-input input, .floating-input textarea, .floating-input select').forEach(element => {
            // Check on load
            if (element.value !== '') {
                element.parentElement.classList.add('has-value');
            }
            
            // Check on change
            element.addEventListener('input', function() {
                if (this.value !== '') {
                    this.parentElement.classList.add('has-value');
                } else {
                    this.parentElement.classList.remove('has-value');
                }
            });
        });
    }

    function initializeForm() {
        // Auto-format ID card
        const idCardInput = document.querySelector('input[name="customer_id_card"]');
        if (idCardInput) {
            idCardInput.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length > 13) {
                    value = value.substring(0, 13);
                }
                this.value = value;
            });
        }

        // Auto-uppercase plate number
        const plateInput = document.querySelector('input[name="plate"]');
        if (plateInput) {
            plateInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        }

        // Form validation
        document.getElementById('jobForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            } else {
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '<div class="animate-spin inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full mr-2"></div>กำลังบันทึก...';
                submitBtn.disabled = true;
            }
        });
    }

    function validateForm() {
        const product = document.querySelector('input[name="product"]').value.trim();
        const contract = document.querySelector('input[name="contract_number"]').value.trim();
        const customer = document.querySelector('input[name="customer_name"]').value.trim();

        if (!product || !contract || !customer) {
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ครบ',
                text: 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน',
                confirmButtonColor: '#3b82f6'
            });
            return false;
        }

        return true;
    }

    function resetForm() {
        Swal.fire({
            title: 'ยืนยันการรีเซ็ต',
            text: 'คุณต้องการลบข้อมูลทั้งหมดในฟอร์มหรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'รีเซ็ต',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('jobForm').reset();
                document.querySelectorAll('.floating-input').forEach(el => {
                    el.classList.remove('has-value');
                });
                Swal.fire({
                    icon: 'success',
                    title: 'รีเซ็ตเรียบร้อย',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    }

    function showPreview() {
        const formData = new FormData(document.getElementById('jobForm'));
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value || '-';
        }

        const previewHTML = `
            <div class="text-left space-y-3">
                <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                    <h3 class="font-semibold text-base mb-3 text-gray-800">ข้อมูลสัญญา</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                        <div><strong>ผลิตภัณฑ์:</strong> ${data.product}</div>
                        <div><strong>เลขสัญญา:</strong> ${data.contract_number}</div>
                        <div><strong>เลขบัตร ปชช.:</strong> ${data.customer_id_card}</div>
                        <div><strong>วันครบกำหนด:</strong> ${data.due_date}</div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                    <h3 class="font-semibold text-base mb-3 text-gray-800">ข้อมูลลูกค้า</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                        <div><strong>ชื่อลูกค้า:</strong> ${data.customer_name}</div>
                        <div><strong>โซน:</strong> ${data.zone}</div>
                        <div class="col-span-2"><strong>ที่อยู่:</strong> ${data.location_area}</div>
                        <div><strong>จังหวัด:</strong> ${data.province}</div>
                        <div><strong>OS:</strong> ${data.os}</div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                    <h3 class="font-semibold text-base mb-3 text-gray-800">ข้อมูลยานพาหนะ</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                        <div><strong>ยี่ห้อ:</strong> ${data.model}</div>
                        <div><strong>รุ่น:</strong> ${data.model_detail}</div>
                        <div><strong>สี:</strong> ${data.color}</div>
                        <div><strong>ทะเบียน:</strong> ${data.plate}</div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                    <h3 class="font-semibold text-base mb-3 text-gray-800">การมอบหมายงาน</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                        <div><strong>ผู้รับงาน:</strong> ${data.assigned_name || 'ไม่ระบุ'}</div>
                        <div><strong>ลบอัตโนมัติ:</strong> ${data.auto_delete_days === '0' ? 'ไม่ลบ' : data.auto_delete_days + ' วัน'}</div>
                    </div>
                </div>
            </div>
        `;

        Swal.fire({
            title: 'ตัวอย่างข้อมูลงาน',
            html: previewHTML,
            width: '700px',
            confirmButtonText: 'ปิด',
            confirmButtonColor: '#3b82f6'
        });
    }

    // Auto-save draft functionality
    function saveDraft() {
        const formData = new FormData(document.getElementById('jobForm'));
        const draft = {};
        for (let [key, value] of formData.entries()) {
            if (value.trim()) {
                draft[key] = value;
            }
        }
        
        if (Object.keys(draft).length > 0) {
            localStorage.setItem('jobDraft', JSON.stringify(draft));
        }
    }

    function loadDraft() {
        const draft = localStorage.getItem('jobDraft');
        if (draft) {
            Swal.fire({
                title: 'พบข้อมูลที่บันทึกไว้',
                text: 'คุณต้องการโหลดข้อมูลที่บันทึกไว้หรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'โหลดข้อมูล',
                cancelButtonText: 'เริ่มใหม่',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    const data = JSON.parse(draft);
                    Object.keys(data).forEach(key => {
                        const input = document.querySelector(`[name="${key}"]`);
                        if (input) {
                            input.value = data[key];
                            if (input.value !== '') {
                                input.parentElement.classList.add('has-value');
                            }
                        }
                    });

                    Swal.fire({
                        icon: 'success',
                        title: 'โหลดข้อมูลเรียบร้อย',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    localStorage.removeItem('jobDraft');
                }
            });
        }
    }

    // Auto-save every 30 seconds
    setInterval(saveDraft, 30000);

    // Load draft on page load
    setTimeout(loadDraft, 1000);

    // Clear draft on successful submit
    document.getElementById('jobForm').addEventListener('submit', function() {
        localStorage.removeItem('jobDraft');
    });

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            document.getElementById('jobForm').submit();
        }
        
        // Ctrl/Cmd + R to reset
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            resetForm();
        }
    });

    // Show welcome message
    setTimeout(() => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: 'info',
            title: 'ยินดีต้อนรับสู่ระบบเพิ่มงานใหม่'
        });
    }, 500);
    </script>
</body>
</html>