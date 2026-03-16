<?php
// โหลด secure session config ก่อน (รวม auto-login จาก remember_me cookie)
require_once 'includes/session_config.php';
// จากนั้นโหลด CSRF functions
require_once 'includes/csrf.php';

// ถ้า user login อยู่แล้ว (หรือ auto-login จาก remember_me สำเร็จ) → redirect ไป dashboard เลย
if (isset($_SESSION['user']) && !isset($_GET['status'])) {
    $role = $_SESSION['user']['role'] ?? '';
    switch ($role) {
        case 'admin':
            header("Location: dashboard/admin.php");
            exit;
        case 'manager':
            header("Location: dashboard/manager.php");
            exit;
        case 'field':
            header("Location: dashboard/field.php");
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>เข้าสู่ระบบ - ระบบบริหารจัดการงานภาคสนาม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="image/BPL.png">
  <?= csrfMetaTag() ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            'thai': ['Sarabun', 'sans-serif']
          },
          colors: {
            corporate: {
              50: '#f0f4f8',
              100: '#d9e2ec',
              500: '#334e68', // Primary Slate
              800: '#102a43', // Dark Navy
              900: '#06152a', // Deep Blue
              'accent': '#2cb1bc' // Teal accent
            }
          }
        }
      }
    }
  </script>
</head>

<body class="font-thai bg-corporate-50 min-h-screen flex items-center justify-center p-4">

  <div class="w-full max-w-md bg-white rounded-lg shadow-xl overflow-hidden border border-corporate-100">
    <!-- Header -->
    <div class="bg-corporate-800 p-8 text-center relative overflow-hidden">
      <!-- Background Graphic -->
      <div class="absolute inset-0 opacity-10">
        <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
          <path d="M0 100 C 20 0 50 0 100 100 Z" fill="white" />
        </svg>
      </div>

      <div class="relative z-10 flex flex-col items-center">
        <div class="w-20 h-20 bg-white rounded-full p-1 shadow-lg mb-4 flex items-center justify-center">
          <img src="image/BPL.png" alt="Logo" class="w-16 h-16 object-contain">
        </div>
        <h2 class="text-2xl font-bold text-white tracking-wide">FIELD MANAGEMENT</h2>
        <p class="text-corporate-100 text-sm mt-1">ระบบบริหารจัดการงานภาคสนาม</p>
      </div>
    </div>

    <!-- Login Form -->
    <div class="p-8">
      <form method="post" action="auth/login.php" id="loginForm" class="space-y-6">
        <?= csrfField() ?>

        <div>
          <label for="username" class="block text-sm font-medium text-corporate-800 mb-1">ชื่อผู้ใช้</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </span>
            <input type="text" name="username" id="username" required placeholder="Username"
              class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-md text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-corporate-500 focus:border-transparent transition-all text-sm">
          </div>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-corporate-800 mb-1">รหัสผ่าน</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </span>
            <input type="password" name="password" id="password" required placeholder="Password"
              class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-md text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-corporate-500 focus:border-transparent transition-all text-sm">
            <button type="button" id="togglePassword"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
              <svg id="eyeIcon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input id="remember" name="remember" type="checkbox"
              class="h-4 w-4 text-corporate-800 focus:ring-corporate-500 border-gray-300 rounded">
            <label for="remember" class="ml-2 block text-sm text-gray-700">จำฉันไว้ในระบบ</label>
          </div>
        </div>

        <div>
          <button type="submit"
            class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-corporate-800 hover:bg-corporate-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-corporate-500 transition-colors">
            เข้าสู่ระบบ
          </button>
        </div>
      </form>
    </div>

    <div class="bg-gray-50 px-8 py-4 border-t border-gray-200">
      <p class="text-xs text-center text-gray-500">
        &copy; 2026 FIELD MANAGEMENT All rights reserved.<br>
        System Version 3.0 By Boat Patthanapong
      </p>
    </div>
  </div>

  <?php
  // Logic การแสดง Alert
  // 1. ตรวจสอบ Success จาก URL Parameter
  if (isset($_GET['status']) && $_GET['status'] == 'success' && isset($_GET['redirect'])) {
    $redirectUrl = htmlspecialchars($_GET['redirect']);
    echo "
      <script>
        Swal.fire({
          icon: 'success',
          title: 'เข้าสู่ระบบสำเร็จ',
          text: 'กำลังนำท่านเข้าสู่ระบบ...',
          showConfirmButton: false,
          timer: 1500,
          background: '#fff',
          iconColor: '#102a43'
        }).then(() => {
            window.location.href = '$redirectUrl';
        });
      </script>
      ";
  }

  // 2. ตรวจสอบ Error จาก Session
  if (isset($_SESSION['message'])) {
    $msg = addslashes($_SESSION['message']);
    echo "
      <script>
        Swal.fire({
          icon: 'error',
          title: 'ข้อผิดพลาด',
          text: '$msg',
          confirmButtonColor: '#102a43',
          background: '#fff'
        });
      </script>
      ";
    unset($_SESSION['message']);
  }
  ?>

  <script>
    // Toggle Password
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    toggleBtn.addEventListener('click', () => {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);

      if (type === 'text') {
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.99 9.99 0 012.149-3.63m3.364-2.766A9.953 9.953 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.972 9.972 0 01-1.272 2.592M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />';
      } else {
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
      }
    });

    // LocalStorage Logic for Remember Me
    document.addEventListener("DOMContentLoaded", () => {
      const savedUsername = localStorage.getItem("saved_username_corp");
      if (savedUsername) {
        document.getElementById("username").value = savedUsername;
        document.getElementById("remember").checked = true;
      }
    });

    document.getElementById("loginForm").addEventListener("submit", () => {
      const remember = document.getElementById("remember").checked;
      const username = document.getElementById("username").value;
      if (remember) {
        localStorage.setItem("saved_username_corp", username);
      } else {
        localStorage.removeItem("saved_username_corp");
      }
    });
  </script>
</body>

</html>