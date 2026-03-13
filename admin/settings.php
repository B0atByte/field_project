<?php
require_once __DIR__ . '/../includes/session_config.php';
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';
require_once '../includes/csrf.php';
require_once '../config/env.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    requireCsrfToken();

    $google_maps_key = trim($_POST['google_maps_api_key'] ?? '');

    // Update in database
    if (updateSetting('GOOGLE_MAPS_API_KEY', $google_maps_key)) {
        $success_message = 'บันทึกการตั้งค่าสำเร็จ';
    } else {
        $error_message = 'เกิดข้อผิดพลาดในการบันทึก';
    }
}

// Get current settings
$google_maps_key = getSetting('GOOGLE_MAPS_API_KEY', '');

$page_title = "⚙️ ตั้งค่าระบบ";
include '../components/header.php';
include '../components/sidebar.php';
?>

<style>
    .settings-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .settings-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        padding: 30px;
        margin-bottom: 20px;
    }

    .settings-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e9ecef;
    }

    .settings-header i {
        font-size: 24px;
        color: #007bff;
    }

    .settings-header h2 {
        margin: 0;
        font-size: 22px;
        color: #333;
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #495057;
        font-size: 14px;
    }

    .form-group input[type="text"],
    .form-group input[type="password"] {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #ced4da;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-group input[type="text"]:focus,
    .form-group input[type="password"]:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

    .form-help {
        display: block;
        margin-top: 6px;
        font-size: 13px;
        color: #6c757d;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .alert-success {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .alert-danger {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    .alert-warning {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
    }

    .btn-save {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        border: none;
        padding: 12px 32px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,123,255,0.3);
    }

    .security-notice {
        background: #f8f9fa;
        border-left: 4px solid #ffc107;
        padding: 16px;
        border-radius: 4px;
        margin-bottom: 24px;
    }

    .security-notice h4 {
        margin: 0 0 8px 0;
        font-size: 14px;
        color: #856404;
        font-weight: 600;
    }

    .security-notice p {
        margin: 0;
        font-size: 13px;
        color: #6c757d;
        line-height: 1.5;
    }

    .toggle-visibility {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        font-size: 16px;
        padding: 4px 8px;
    }

    .toggle-visibility:hover {
        color: #007bff;
    }

    .input-wrapper {
        position: relative;
    }
</style>

<div class="main-content">
    <div class="settings-container">
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-map-marked-alt"></i>
                <h2>Google Maps API</h2>
            </div>

            <div class="security-notice">
                <h4><i class="fas fa-shield-alt"></i> หมายเหตุด้านความปลอดภัย</h4>
                <p>
                    API Key ที่บันทึกในระบบจะถูกเก็บในฐานข้อมูล ไม่ต้องกังวลเรื่องการ commit ลง Git<br>
                    สำหรับความปลอดภัยสูงสุด แนะนำให้จำกัดการใช้งาน API Key ใน Google Cloud Console
                </p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="google_maps_api_key">
                        <i class="fas fa-key"></i> Google Maps API Key
                    </label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="google_maps_api_key"
                            name="google_maps_api_key"
                            value="<?php echo htmlspecialchars($google_maps_key); ?>"
                            placeholder="AIzaSy..."
                        >
                        <button type="button" class="toggle-visibility" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>
                    <small class="form-help">
                        <i class="fas fa-info-circle"></i>
                        ใช้สำหรับแสดงแผนที่ในหน้า Map และรายละเอียดงาน |
                        <a href="https://console.cloud.google.com/google/maps-apis/credentials" target="_blank" rel="noopener">
                            สร้าง API Key <i class="fas fa-external-link-alt"></i>
                        </a>
                    </small>
                </div>

                <div class="form-group">
                    <button type="submit" name="save_settings" class="btn-save">
                        <i class="fas fa-save"></i> บันทึกการตั้งค่า
                    </button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-question-circle"></i>
                <h2>วิธีการตั้งค่า Google Maps API</h2>
            </div>

            <ol style="line-height: 1.8; color: #495057;">
                <li>เข้าไปที่ <a href="https://console.cloud.google.com/google/maps-apis/credentials" target="_blank" rel="noopener">Google Cloud Console</a></li>
                <li>สร้างโปรเจกต์ใหม่หรือเลือกโปรเจกต์ที่มีอยู่</li>
                <li>ไปที่เมนู "Credentials" และคลิก "Create Credentials" → "API Key"</li>
                <li>คัดลอก API Key ที่ได้มาวางในช่องด้านบน</li>
                <li><strong>สำคัญ:</strong> จำกัดการใช้งาน API Key โดย:
                    <ul style="margin-top: 8px;">
                        <li>กำหนด Application restrictions (HTTP referrers)</li>
                        <li>กำหนด API restrictions (Maps JavaScript API)</li>
                    </ul>
                </li>
                <li>คลิก "บันทึกการตั้งค่า" เพื่อบันทึก</li>
            </ol>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const input = document.getElementById('google_maps_api_key');
    const icon = document.getElementById('toggle-icon');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php include '../components/footer.php'; ?>
