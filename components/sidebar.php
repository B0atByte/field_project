<button onclick="toggleSidebar()"
  class="md:hidden fixed top-4 left-4 z-50 bg-white shadow-lg text-gray-700 p-3 rounded-xl hover:shadow-xl transition-all duration-300 border border-gray-200">
  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
  </svg>
</button>

<aside id="sidebar" class="transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out
          bg-white shadow-2xl border-r border-gray-200
          w-64 fixed z-40 top-0 left-0 h-full flex flex-col">
  <div class="p-5 border-b border-gray-100 shrink-0">
    <div class="flex items-center justify-between">
      <div class="flex items-center">
        <div
          class="w-10 h-10 bg-gradient-to-r from-red-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg shadow-red-200">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <div class="ml-3">
          <h2 class="text-lg font-bold text-gray-800 tracking-tight">Field System</h2>
          <p class="text-xs text-gray-500 font-medium bg-gray-100 px-2 py-0.5 rounded-full inline-block mt-1">
            <?= isset($_SESSION['user']['role']) ? ucfirst($_SESSION['user']['role']) : 'Admin' ?> Panel
          </p>
        </div>
      </div>

      <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-gray-600">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
  </div>

  <div class="px-4 py-3 shrink-0">
    <div class="p-3 bg-gray-50 border border-gray-100 rounded-2xl flex items-center">
      <div
        class="w-9 h-9 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-sm border-2 border-white">
        <span class="text-white font-bold text-sm">
          <?= strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)) ?>
        </span>
      </div>
      <div class="ml-3 flex-1 min-w-0">
        <p class="text-sm font-bold text-gray-800 truncate">
          <?= htmlspecialchars($_SESSION['user']['name'] ?? 'User') ?>
        </p>
        <p class="text-xs text-gray-500 truncate">
          <?= isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้จัดการ' ?>
        </p>
      </div>
    </div>
  </div>

  <nav class="flex-1 overflow-y-auto p-4 space-y-1 custom-scrollbar">

    <a href="../dashboard/<?= isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : 'admin' ?>.php"
      class="group flex items-center px-4 py-2.5 text-gray-600 rounded-xl hover:bg-red-50 hover:text-red-600 transition-all duration-200 font-medium mb-4">
      <div
        class="w-8 h-8 bg-white border border-gray-100 group-hover:bg-red-100 group-hover:border-red-100 rounded-lg flex items-center justify-center transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
      </div>
      <span class="ml-3 text-sm">หน้าหลัก</span>
    </a>

    <?php if (isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], ['admin', 'manager'])): ?>
      <div class="px-4 pb-2 pt-1">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">การจัดการงาน</p>
      </div>

      <a href="../admin/import_jobs.php"
        class="group flex items-center px-4 py-2.5 text-gray-600 rounded-xl hover:bg-blue-50 hover:text-blue-600 transition-all font-medium">
        <div
          class="w-8 h-8 bg-white border border-gray-100 group-hover:bg-blue-100 group-hover:border-blue-100 rounded-lg flex items-center justify-center shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
          </svg>
        </div>
        <span class="ml-3 text-sm">นำเข้างาน</span>
      </a>

      <a href="../admin/jobs.php"
        class="group flex items-center px-4 py-2.5 text-gray-600 rounded-xl hover:bg-green-50 hover:text-green-600 transition-all font-medium">
        <div
          class="w-8 h-8 bg-white border border-gray-100 group-hover:bg-green-100 group-hover:border-green-100 rounded-lg flex items-center justify-center shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2" />
          </svg>
        </div>
        <span class="ml-3 text-sm">รายการงาน</span>
      </a>

      <a href="../admin/map.php"
        class="group flex items-center px-4 py-2.5 text-gray-600 rounded-xl hover:bg-purple-50 hover:text-purple-600 transition-all font-medium">
        <div
          class="w-8 h-8 bg-white border border-gray-100 group-hover:bg-purple-100 group-hover:border-purple-100 rounded-lg flex items-center justify-center shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          </svg>
        </div>
        <span class="ml-3 text-sm">แผนที่</span>
      </a>
    <?php endif; ?>

    <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
      <div class="my-2 border-t border-gray-100"></div>
      <div class="px-4 pb-2 pt-1">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">ผู้ดูแลระบบ</p>
      </div>

      <a href="../admin/users.php"
        class="group flex items-center px-4 py-2.5 text-gray-600 rounded-xl hover:bg-indigo-50 hover:text-indigo-600 transition-all font-medium">
        <div
          class="w-8 h-8 bg-white border border-gray-100 group-hover:bg-indigo-100 group-hover:border-indigo-100 rounded-lg flex items-center justify-center shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197" />
          </svg>
        </div>
        <span class="ml-3 text-sm">ผู้ใช้งาน</span>
      </a>

      <a href="../setup_ip_security.php"
        class="group flex items-center px-4 py-2.5 text-gray-600 rounded-xl hover:bg-teal-50 hover:text-teal-600 transition-all font-medium">
        <div
          class="w-8 h-8 bg-white border border-gray-100 group-hover:bg-teal-100 group-hover:border-teal-100 rounded-lg flex items-center justify-center shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <span class="ml-3 text-sm">ความปลอดภัย (IP)</span>
      </a>

      <a href="../admin/logs.php"
        class="group flex items-center px-4 py-2.5 text-gray-600 rounded-xl hover:bg-indigo-50 hover:text-indigo-600 transition-all font-medium">
        <div
          class="w-8 h-8 bg-white border border-gray-100 group-hover:bg-indigo-100 group-hover:border-indigo-100 rounded-lg flex items-center justify-center shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <span class="ml-3 text-sm">ระบบ Logs</span>
      </a>

      <a href="../admin/admin_delete_jobs.php"
        class="group flex items-center px-4 py-2.5 text-gray-600 rounded-xl hover:bg-rose-50 hover:text-rose-600 transition-all font-medium">
        <div
          class="w-8 h-8 bg-white border border-gray-100 group-hover:bg-rose-100 group-hover:border-rose-100 rounded-lg flex items-center justify-center shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </div>
        <span class="ml-3 text-sm">ลบงาน</span>
      </a>
    <?php endif; ?>

    <div class="h-4"></div>
  </nav>

  <div class="p-4 border-t border-gray-200 bg-gray-50 shrink-0">
    <a href="../auth/logout.php"
      class="group flex items-center px-4 py-2.5 text-gray-600 rounded-xl hover:bg-white hover:shadow-md hover:text-gray-900 transition-all duration-200 font-medium w-full">
      <div
        class="w-8 h-8 bg-gray-200 group-hover:bg-gray-100 rounded-lg flex items-center justify-center transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7" />
        </svg>
      </div>
      <span class="ml-3 text-sm">ออกจากระบบ</span>
    </a>
    <div class="mt-2 text-center">
      <p class="text-[10px] text-gray-400">Field System v2.0 © 2025</p>
    </div>
  </div>

</aside>

<style>
  /* สำหรับ Chrome, Edge, Safari */
  .custom-scrollbar::-webkit-scrollbar {
    width: 4px;
  }

  .custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
  }

  .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #e5e7eb;
    border-radius: 20px;
  }

  /* สำหรับ Firefox */
  .custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #e5e7eb transparent;
  }
</style>