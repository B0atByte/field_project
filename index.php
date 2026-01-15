<?php
// โหลด secure session config ก่อน
require_once 'includes/session_config.php';
// จากนั้นโหลด CSRF functions
require_once 'includes/csrf.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เข้าสู่ระบบ - ภาคสนาม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="image/BPL.png">
  <?= csrfMetaTag() ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            'thai': ['Prompt', 'sans-serif']
          },
          animation: {
            'fade-in': 'fadeIn 0.6s ease-out',
            'slide-up': 'slideUp 0.5s ease-out',
            'float': 'float 3s ease-in-out infinite'
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0', transform: 'translateY(20px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            },
            slideUp: {
              '0%': { opacity: '0', transform: 'translateY(30px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            },
            float: {
              '0%, 100%': { transform: 'translateY(0px)' },
              '50%': { transform: 'translateY(-10px)' }
            }
          }
        }
      }
    }
  </script>
</head>
<body class="font-thai min-h-screen bg-gradient-to-br from-gray-50 via-white to-red-50">
  
  <!-- Background Pattern -->
  <div class="absolute inset-0 opacity-5">
    <div class="h-full w-full bg-[linear-gradient(45deg,transparent_35%,rgba(220,38,38,.1)_35%,rgba(220,38,38,.1)_65%,transparent_65%),linear-gradient(-45deg,transparent_35%,rgba(220,38,38,.05)_35%,rgba(220,38,38,.05)_65%,transparent_65%)] bg-[length:60px_60px]"></div>
  </div>

  <!-- Floating Elements -->
  <div class="absolute inset-0 overflow-hidden pointer-events-none">
    <div class="absolute top-20 left-20 w-2 h-2 bg-red-400 rounded-full animate-float opacity-60"></div>
    <div class="absolute top-40 right-32 w-1 h-1 bg-red-300 rounded-full animate-float opacity-40" style="animation-delay: 1s;"></div>
    <div class="absolute bottom-32 left-40 w-3 h-3 bg-red-500 rounded-full animate-float opacity-30" style="animation-delay: 2s;"></div>
    <div class="absolute bottom-20 right-20 w-2 h-2 bg-red-400 rounded-full animate-float opacity-50" style="animation-delay: 0.5s;"></div>
  </div>

  <!-- Main Container -->
  <div class="relative z-10 min-h-screen flex items-center justify-center px-4 py-8">
    
    <!-- Login Card -->
    <div class="w-full max-w-md animate-fade-in">
      <div class="bg-white rounded-3xl shadow-2xl border border-red-100 p-8 space-y-8">
        
        <!-- Header Section -->
        <div class="text-center space-y-4 animate-slide-up">
          <!-- Logo -->
          <div class="flex justify-center">
            <div class="relative">
              <img src="image/BPL.png" alt="Logo" class="w-20 h-20 rounded-2xl object-cover shadow-lg border-2 border-red-200 transition-transform duration-300 hover:scale-105">
              <div class="absolute -inset-1 bg-gradient-to-r from-red-500 to-red-600 rounded-2xl opacity-0 hover:opacity-20 transition-opacity duration-300"></div>
            </div>
          </div>
          
          <!-- Title -->
          <div class="space-y-2">
            <h1 class="text-3xl font-bold text-gray-800">เข้าสู่ระบบ</h1>
            <p class="text-gray-500"> ระบบติดตามงานภาคสนาม บริษัท Bargainpoint </p>
            <div class="w-16 h-1 bg-red-500 mx-auto rounded-full"></div>
          </div>
        </div>

        <!-- Login Form -->
        <form method="post" action="auth/login.php" class="space-y-6" id="loginForm">
          <?= csrfField() ?>

          <!-- Username Input -->
          <div class="space-y-2 animate-slide-up" style="animation-delay: 0.1s;">
            <label class="block text-sm font-semibold text-gray-700">ชื่อผู้ใช้</label>
            <div class="relative">
              <input type="text" 
                     name="username" 
                     id="username" 
                     placeholder="กรุณาใส่ชื่อผู้ใช้" 
                     required
                     class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-200 rounded-2xl text-gray-700 placeholder-gray-400 focus:outline-none focus:border-red-500 focus:bg-white transition-all duration-300 hover:border-red-300">
              
              <!-- Icon -->
              <div class="absolute inset-y-0 right-4 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
              </div>
            </div>
          </div>

          <!-- Password Input -->
          <div class="space-y-2 animate-slide-up" style="animation-delay: 0.2s;">
            <label class="block text-sm font-semibold text-gray-700">รหัสผ่าน</label>
            <div class="relative">
              <input type="password" 
                     name="password" 
                     id="password" 
                     placeholder="กรุณาใส่รหัสผ่าน" 
                     required
                     class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-200 rounded-2xl text-gray-700 placeholder-gray-400 focus:outline-none focus:border-red-500 focus:bg-white transition-all duration-300 hover:border-red-300 pr-12">
              
              <!-- Toggle Password Button -->
              <button type="button" 
                      id="togglePassword" 
                      class="absolute inset-y-0 right-4 flex items-center text-gray-400 hover:text-red-500 transition-colors duration-200">
                <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Remember Me & Forgot Password -->
          <div class="flex items-center justify-between animate-slide-up" style="animation-delay: 0.3s;">
            <!-- Remember Me Checkbox -->
            <label class="flex items-center cursor-pointer group">
              <div class="relative">
                <input id="remember" 
                       type="checkbox" 
                       name="remember" 
                       class="sr-only">
                <div class="w-5 h-5 bg-white border-2 border-gray-300 rounded transition-all duration-200 group-hover:border-red-400">
                  <svg class="w-3 h-3 text-white absolute top-0.5 left-0.5 hidden" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                  </svg>
                </div>
              </div>
              <span class="ml-3 text-sm text-gray-600 group-hover:text-gray-800 transition-colors duration-200">จำฉันไว้</span>
            </label>
          </div>

          <!-- Login Button -->
          <div class="animate-slide-up" style="animation-delay: 0.4s;">
            <button type="submit" 
                    class="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold py-4 rounded-2xl shadow-lg hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-red-500 focus:ring-opacity-50">
              <span class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                </svg>
                เข้าสู่ระบบ
              </span>
            </button>
          </div>
        </form>

        <!-- Divider -->
        <div class="relative animate-slide-up" style="animation-delay: 0.5s;">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-200"></div>
          </div>
          <div class="relative flex justify-center text-sm">
            <span class="bg-white px-4 text-gray-500">หรือ</span>
          </div>
        </div>

        <!-- Footer -->
        <div class="text-center space-y-3 animate-slide-up" style="animation-delay: 0.7s;">
          <p class="text-xs text-gray-400">
            © 2025 Boat_IT BPL Version 1.5 03/09/2568
          </p>
          
          <!-- Status Indicator -->
          <div class="flex items-center justify-center space-x-2 text-xs text-green-600">
            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            <span>ระบบพร้อมใช้งาน</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($alertMessage)): ?>
  <script>
    Swal.fire({
      icon: 'warning',
      title: 'แจ้งเตือน',
      text: '<?= $alertMessage ?>',
      confirmButtonText: 'ตกลง',
      confirmButtonColor: '#dc2626',
      timer: 3500,
      timerProgressBar: true,
      background: '#ffffff',
      showClass: {
        popup: 'animate__animated animate__fadeInDown'
      },
      hideClass: {
        popup: 'animate__animated animate__fadeOutUp'
      }
    });
  </script>
  <?php endif; ?>

  <script>
    // Toggle Password Visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', () => {
      const type = passwordInput.type === 'password' ? 'text' : 'password';
      passwordInput.type = type;

      document.getElementById('eyeIcon').outerHTML = type === 'text'
        ? `<svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.99 9.99 0 012.149-3.63m3.364-2.766A9.953 9.953 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.972 9.972 0 01-1.272 2.592M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
           </svg>`
        : `<svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
           </svg>`;
    });

    // Custom Checkbox Functionality
    document.getElementById('remember').addEventListener('change', function() {
      const checkmark = this.parentElement.querySelector('svg');
      if (this.checked) {
        checkmark.classList.remove('hidden');
        this.parentElement.querySelector('div').classList.add('bg-red-500', 'border-red-500');
        this.parentElement.querySelector('div').classList.remove('bg-white', 'border-gray-300');
      } else {
        checkmark.classList.add('hidden');
        this.parentElement.querySelector('div').classList.remove('bg-red-500', 'border-red-500');
        this.parentElement.querySelector('div').classList.add('bg-white', 'border-gray-300');
      }
    });

    // LocalStorage: Remember Me (เก็บเฉพาะ username เท่านั้น - ปลอดภัยกว่า)
    document.addEventListener("DOMContentLoaded", () => {
      const savedUsername = localStorage.getItem("saved_username");

      if (savedUsername) {
        document.getElementById("username").value = savedUsername;
        document.getElementById("remember").checked = true;
        document.getElementById("remember").dispatchEvent(new Event('change'));
      }

      document.getElementById("loginForm").addEventListener("submit", () => {
        const remember = document.getElementById("remember").checked;
        const username = document.getElementById("username").value;

        if (remember) {
          localStorage.setItem("saved_username", username);
        } else {
          localStorage.removeItem("saved_username");
        }
      });
    });

    // Add smooth input focus animations
    document.querySelectorAll('input[type="text"], input[type="password"]').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateY(-2px)';
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateY(0)';
      });
    });

    // Service Worker - ปิดการใช้งานและ unregister existing workers
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(registrations => {
        for(let registration of registrations) {
          registration.unregister().then(success => {
            if (success) console.log('Service Worker unregistered successfully');
          });
        }
      });

      // ลบ cache ทั้งหมด
      if ('caches' in window) {
        caches.keys().then(cacheNames => {
          return Promise.all(
            cacheNames.map(cacheName => caches.delete(cacheName))
          );
        }).then(() => console.log('All caches cleared'));
      }
    }
  </script>
</body>
</html>