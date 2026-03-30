<!-- Export Loading Overlay – include once per page, before </body> -->
<div id="exportLoadingOverlay"
     class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-xs w-full mx-4 text-center animate-fade-in">
    <!-- Spinner -->
    <div class="flex items-center justify-center mb-5">
      <div class="relative w-16 h-16">
        <svg class="animate-spin w-16 h-16 text-blue-600" xmlns="http://www.w3.org/2000/svg"
             fill="none" viewBox="0 0 24 24">
          <circle class="opacity-20" cx="12" cy="12" r="10"
                  stroke="currentColor" stroke-width="3"></circle>
          <path class="opacity-90" fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
          <i class="fas fa-file-export text-blue-400 text-lg"></i>
        </div>
      </div>
    </div>
    <h3 class="text-base font-bold text-gray-900 mb-1">กำลังสร้างไฟล์...</h3>
    <p class="text-sm text-gray-500" id="exportLoadingText">กรุณารอสักครู่</p>
    <!-- Progress dots -->
    <div class="flex justify-center gap-1.5 mt-4">
      <span class="w-2 h-2 rounded-full bg-blue-400 animate-bounce" style="animation-delay:0s"></span>
      <span class="w-2 h-2 rounded-full bg-blue-400 animate-bounce" style="animation-delay:.15s"></span>
      <span class="w-2 h-2 rounded-full bg-blue-400 animate-bounce" style="animation-delay:.3s"></span>
    </div>
  </div>
</div>

<script>
(function () {
  const overlay = document.getElementById('exportLoadingOverlay');

  window.showExportLoading = function (text) {
    document.getElementById('exportLoadingText').textContent = text || 'กรุณารอสักครู่';
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
  };

  window.hideExportLoading = function () {
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
  };

  /**
   * ดาวน์โหลดไฟล์ผ่าน fetch (แสดง loading ระหว่างรอ server)
   * @param {string} url      – URL ของ export script
   * @param {string} filename – ชื่อไฟล์ที่บันทึก (optional)
   * @param {string} text     – ข้อความใน overlay (optional)
   */
  window.triggerExport = async function (url, filename, text) {
    showExportLoading(text || 'กรุณารอสักครู่');
    try {
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) throw new Error('Server error ' + res.status);

      // ดึง filename จาก Content-Disposition ถ้าไม่ได้ระบุ
      if (!filename) {
        const cd = res.headers.get('Content-Disposition') || '';
        const m  = cd.match(/filename[^;=\n]*=['"]?([^'"\n;]+)/i);
        filename = m ? m[1] : 'export';
      }

      const blob = await res.blob();
      const a    = document.createElement('a');
      a.href     = URL.createObjectURL(blob);
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(a.href);
    } catch (err) {
      alert('เกิดข้อผิดพลาด: ' + err.message);
    } finally {
      hideExportLoading();
    }
  };
})();
</script>
