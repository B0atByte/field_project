<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';
require_once '../includes/csrf.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: users.php");
    exit;
}

// ดึงข้อมูลผู้ใช้
$departments = $conn->query("SELECT * FROM departments ORDER BY name ASC");

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "ไม่พบผู้ใช้"; exit;
}

// ดึงสิทธิ์มองเห็นแผนก
$visible_stmt = $conn->prepare("SELECT to_department_id FROM department_visibility WHERE from_user_id = ?");
$visible_stmt->bind_param("i", $id);
$visible_stmt->execute();
$res = $visible_stmt->get_result();
$visible_ids = [];
while ($row = $res->fetch_assoc()) {
    $visible_ids[] = $row['to_department_id'];
}

// บันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $password = trim($_POST['password']);
    $department_id = (int)$_POST['department_id'];
    $visible_departments = $_POST['visible_departments'] ?? [];
    $can_delete_jobs = isset($_POST['can_delete_jobs']) ? 1 : 0;

    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, username=?, role=?, password=?, department_id=?, can_delete_jobs=? WHERE id=?");
        $stmt->bind_param("ssssiii", $name, $username, $role, $hashed, $department_id, $can_delete_jobs, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, username=?, role=?, department_id=?, can_delete_jobs=? WHERE id=?");
        $stmt->bind_param("sssiii", $name, $username, $role, $department_id, $can_delete_jobs, $id);
    }
    $stmt->execute();

    // ลบสิทธิ์เก่า
    $conn->query("DELETE FROM department_visibility WHERE from_user_id = {$id}");

    // เพิ่มสิทธิ์ใหม่
    foreach ($visible_departments as $dept_id) {
        $stmt = $conn->prepare("INSERT INTO department_visibility (from_user_id, to_department_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $id, $dept_id);
        $stmt->execute();
    }

    header("Location: users.php");
    exit;
}

$page_title = "✏️ แก้ไขผู้ใช้";
include '../components/header.php';
include '../components/sidebar.php';
?>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  :root {
    --primary-color: #0f172a; /* Slate 900 - Dark professional header */
    --accent-color: #2563eb; /* Blue 600 - Action buttons/links */
    --bg-color: #f8fafc; /* Slate 50 */
    --card-bg: #ffffff;
    --border-color: #e2e8f0; /* Slate 200 */
    --text-primary: #1e293b; /* Slate 800 */
    --text-secondary: #64748b; /* Slate 500 */
    --success: #059669;
    --danger: #dc2626;
  }

  body {
    background-color: var(--bg-color);
    font-family: 'Sarabun', 'Inter', sans-serif; /* Added Thai font support */
    color: var(--text-primary);
    line-height: 1.5;
  }


  .main-container {
    max-width: 1000px;
    margin: 2rem auto;
    padding: 0 1rem;
  }

  /* Header Section - Clean & Minimal */
  .header-section {
    background: white;
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem 0;
    margin-bottom: 2rem;
  }

  .header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .header-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .header-title i {
    color: var(--accent-color);
  }

  .btn-back {
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: white;
    transition: all 0.2s;
  }

  .btn-back:hover {
    background: var(--bg-color);
    color: var(--text-primary);
    border-color: var(--text-secondary);
  }

  /* Form Cards - Professional Look */
  .form-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05); /* Subtle shadow */
  }

  .card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .card-icon {
    color: var(--text-secondary);
  }

  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
  }

  .form-group {
    margin-bottom: 1.25rem;
  }

  .form-label {
    display: block;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
  }

  .form-input, .form-select {
    width: 100%;
    padding: 0.625rem 0.875rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: white;
    transition: border-color 0.2s;
  }

  .form-input:focus, .form-select:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }

  /* Permission Grid */
  .dept-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
    max-height: 300px;
    overflow-y: auto;
    padding: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-color);
  }

  .dept-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.9rem;
  }

  .permissions-card {
    background-color: #f0fdf4; /* Very subtle green tint for special permissions */
    border-color: #bbf7d0;
  }

  /* Buttons */
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
  }

  .btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 0.625rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: background-color 0.2s;
  }

  .btn-primary:hover {
    background-color: #020617;
  }

  .btn-secondary {
    background-color: white;
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    padding: 0.625rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
  }

  .btn-secondary:hover {
    background-color: var(--bg-color);
    border-color: var(--text-secondary);
  }

  /* Search Input */
  .search-input {
    position: relative;
    margin-bottom: 1rem;
  }
  
  .search-input i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
  }
  
  .search-input input {
    padding-left: 2.5rem;
  }

  .hidden {
    display: none;
  }
</style>

<main class="min-h-screen">
  <div class="header-section">
      <div class="main-container">
          <div class="header-content">
            <h1 class="header-title">
              <i class="fas fa-user-edit"></i>
              แก้ไขข้อมูลผู้ใช้
            </h1>
            <a href="users.php" class="btn-back">
              <i class="fas fa-arrow-left"></i>
              กลับไปหน้าผู้ใช้
            </a>
          </div>
      </div>
  </div>

  <div class="main-container">

    <!-- Form Section -->
    <div class="form-section">
      <form method="post" class="space-y-6">
        <?= csrfField() ?>
        <!-- Basic Information Card -->
        <div class="form-card">
          <h2 class="card-title">
            <i class="fas fa-id-card card-icon"></i>
            ข้อมูลพื้นฐาน
          </h2>
          
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">
                <i class="fas fa-user"></i>
                ชื่อ - นามสกุล
              </label>
              <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" 
                     class="form-input" required placeholder="กรอกชื่อ-นามสกุล">
            </div>
            
            <div class="form-group">
              <label class="form-label">
                <i class="fas fa-at"></i>
                Username
              </label>
              <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
                     class="form-input" required placeholder="กรอก username">
            </div>
          </div>
        </div>

        <!-- Role & Department Card -->
        <div class="form-card">
          <h2 class="card-title">
            <i class="fas fa-users-cog card-icon"></i>
            บทบาทและแผนก
          </h2>
          
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">
                <i class="fas fa-shield-alt"></i>
                บทบาท
              </label>
              <select name="role" id="role-select" class="form-select">
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>
                  🛡️ Admin - ผู้ดูแลระบบ
                </option>
                <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>
                  👔 Manager - ผู้จัดการ
                </option>
                <option value="field" <?= $user['role'] === 'field' ? 'selected' : '' ?>>
                  👥 Field - พนักงานภาคสนาม
                </option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">
                <i class="fas fa-building"></i>
                แผนก
              </label>
              <select name="department_id" class="form-select" required>
                <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
                  <option value="<?= $dept['id'] ?>" <?= $user['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dept['name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Permissions Card -->
        <div class="form-card">
          <h2 class="card-title">
            <i class="fas fa-eye card-icon"></i>
            สิทธิ์การมองเห็นงานในแผนก
          </h2>
          
          <div class="form-group">
            <div class="search-input">
              <i class="fas fa-search"></i>
              <input type="text" id="deptSearch" placeholder="ค้นหาแผนก..." class="form-input">
            </div>
          </div>
          
          <div id="deptList" class="dept-grid">
            <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
              <label class="dept-item">
                <input type="checkbox" name="visible_departments[]" value="<?= $dept['id'] ?>" 
                       <?= in_array($dept['id'], $visible_ids) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($dept['name']) ?></span>
              </label>
            <?php endwhile; ?>
          </div>
        </div>

        <!-- Manager Permissions Card -->
        <div id="manager-permissions" class="form-card permissions-card hidden">
          <h2 class="card-title">
            <i class="fas fa-crown card-icon"></i>
            สิทธิ์พิเศษสำหรับ Manager
          </h2>
          
          <div class="permission-item">
            <input type="checkbox" name="can_delete_jobs" class="permission-checkbox" 
                   <?= $user['can_delete_jobs'] ? 'checked' : '' ?>>
            <span>
              <i class="fas fa-trash-alt text-red-500"></i>
              สามารถลบงานได้
            </span>
          </div>
        </div>

        <!-- Password Card -->
        <div class="form-card">
          <h2 class="card-title">
            <i class="fas fa-lock card-icon"></i>
            เปลี่ยนรหัสผ่าน
          </h2>
          
          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-key"></i>
              รหัสผ่านใหม่ (เว้นว่างหากไม่เปลี่ยน)
            </label>
            <div class="password-input">
              <input type="password" name="password" id="passwordInput" 
                     class="form-input" placeholder="••••••••">
              <button type="button" id="togglePassword" class="password-toggle">
                <i class="fas fa-eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <a href="users.php" class="btn-secondary">
            <i class="fas fa-times"></i>
            ยกเลิก
          </a>
          <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i>
            บันทึกการแก้ไข
          </button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
  // Toggle Manager Permissions
  function togglePermissions() {
    const role = document.getElementById('role-select').value;
    const box = document.getElementById('manager-permissions');
    if (role === 'manager') {
      box.classList.remove('hidden');
    } else {
      box.classList.add('hidden');
    }
  }
  
  document.getElementById('role-select').addEventListener('change', togglePermissions);
  window.addEventListener('DOMContentLoaded', togglePermissions);

  // Department Search
  document.getElementById('deptSearch').addEventListener('input', function() {
    const keyword = this.value.toLowerCase();
    document.querySelectorAll('.dept-item').forEach((item) => {
      const text = item.textContent.toLowerCase();
      const shouldShow = text.includes(keyword);
      item.style.display = shouldShow ? 'flex' : 'none';
    });
  });

  // Toggle password visibility
  document.getElementById('togglePassword').addEventListener('click', function() {
    const input = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');
    
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  });

  // Form submission with loading state
  document.querySelector('form').addEventListener('submit', function(e) {
    const submitBtn = document.querySelector('.btn-primary');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
    submitBtn.disabled = true;
    
    // Fallback if request hangs
    setTimeout(() => {
      if (submitBtn.disabled) {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      }
    }, 5000);
  });
</script>

<?php include '../components/footer.php'; ?>