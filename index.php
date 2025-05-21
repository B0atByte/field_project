<?php
session_start();

// แสดงข้อความแจ้งเตือน
$alertMessage = '';
if (isset($_SESSION['message'])) {
    $alertMessage = addslashes($_SESSION['message']); // ป้องกัน JS injection
    unset($_SESSION['message']);
}

// เข้าระบบทางลัด (DEV)
if (isset($_GET['quick'])) {
    $role = $_GET['quick'];
    if ($role === 'admin') {
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'Test Admin',
            'role' => 'admin'
        ];
        header("Location: dashboard/admin.php");
        exit;
    } elseif ($role === 'field') {
        $_SESSION['user'] = [
            'id' => 2,
            'name' => 'Test Field',
            'role' => 'field'
        ];
        header("Location: dashboard/field.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เข้าสู่ระบบ - ภาคสนาม DEMO</title>
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#007BFF">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

  <div class="bg-white shadow-md rounded-xl p-8 w-full max-w-md space-y-6">
    
    <!-- Logo Placeholder -->
    <div class="text-center mb-4">
      <div class="w-20 h-20 mx-auto bg-gray-200 rounded-full flex items-center justify-center text-gray-500">
        ภาคสนาม
      </div>
    </div>

    <h2 class="text-2xl font-semibold text-center text-gray-800">
      เข้าสู่ระบบ ภาคสนาม DEMO<br><span class="text-sm text-gray-500">15/5/2568</span>
    </h2>

    <form method="post" action="auth/login.php" class="space-y-4">
      <input type="text" name="username" placeholder="Username" required
             class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      <input type="password" name="password" placeholder="Password" required
             class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition">
        เข้าสู่ระบบ
      </button>
    </form>

    <div class="border-t pt-4">
      <h3 class="text-center text-gray-600 mb-2 font-medium">ทางลัด (Dev Quick Login)</h3>
      <div class="flex justify-center gap-4">
        <a href="?quick=admin" class="text-blue-600 hover:underline">🔑 Admin</a>
        <a href="?quick=field" class="text-green-600 hover:underline">👤 Field Officer</a>
      </div>
    </div>
  </div>

  <!-- แจ้งเตือน SweetAlert2 -->
  <?php if (!empty($alertMessage)): ?>
  <script>
    Swal.fire({
      icon: 'warning',
      title: 'แจ้งเตือน',
      text: '<?= $alertMessage ?>',
      confirmButtonText: 'ตกลง',
      timer: 3500,
      timerProgressBar: true
    });
  </script>
  <?php endif; ?>

  <!-- Register Service Worker -->
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('service-worker.js')
        .then(() => console.log('✅ Service Worker registered!'))
        .catch(err => console.error('❌ Service Worker registration failed:', err));
    }
  </script>
</body>
</html>
