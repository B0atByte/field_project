<?php
require_once __DIR__ . '/../includes/session_config.php';
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';
require_once '../includes/csrf.php';

$page_title = "Batch Optimize Images";
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
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        .log-entry {
            padding: 0.5rem;
            margin-bottom: 0.25rem;
            border-radius: 0.25rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }

        .log-success { background: #d1fae5; color: #065f46; }
        .log-error { background: #fee2e2; color: #991b1b; }
        .log-info { background: #dbeafe; color: #1e40af; }
    </style>
</head>

<body>
    <div class="flex">
        <?php include '../components/sidebar.php'; ?>

        <main class="flex-1 p-4 md:p-8 ml-0 md:ml-64">
            <div class="max-w-5xl mx-auto">

                <!-- Header -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 flex items-center gap-3">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Batch Optimize Images
                            </h1>
                            <p class="text-gray-600 mt-1">Optimize รูปภาพเก่าทั้งหมดในระบบ</p>
                        </div>
                        <a href="admin.php" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                            กลับ
                        </a>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-lg">
                    <div class="flex">
                        <svg class="w-6 h-6 text-blue-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h3 class="font-semibold text-blue-900">ก่อนเริ่มการทำงาน</h3>
                            <ul class="mt-2 text-blue-800 text-sm space-y-1">
                                <li>• ระบบจะ optimize รูปภาพเก่าทั้งหมดที่อยู่ใน uploads/job_photos/</li>
                                <li>• สร้าง thumbnail 300x300px และ resize original เป็น max 1920px</li>
                                <li>• ย้ายไฟล์ไปยังโครงสร้าง folder ใหม่ (YYYY/MM/DD/original + thumbs)</li>
                                <li>• อัปเดต path ใน database อัตโนมัติ</li>
                                <li>• <strong>แนะนำ:</strong> สำรองข้อมูลก่อนเริ่มทำงาน</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Control Panel -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4">การตั้งค่า</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" id="dryRun" checked class="w-4 h-4 text-blue-600 rounded">
                                <span class="ml-2 text-gray-700">Dry Run (ทดสอบไม่แก้ไขจริง)</span>
                            </label>
                            <p class="text-xs text-gray-500 ml-6">เปิดเพื่อดูผลการทำงานก่อนโดยไม่แก้ไขไฟล์จริง</p>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">จำนวนรูปต่อรอบ</label>
                            <select id="batchSize" class="w-full md:w-64 px-4 py-2 border rounded-lg">
                                <option value="10">10 รูป/รอบ (ช้า แต่ปลอดภัย)</option>
                                <option value="50" selected>50 รูป/รอบ (แนะนำ)</option>
                                <option value="100">100 รูป/รอบ (เร็ว)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button id="startBtn"
                                onclick="startOptimization()"
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            เริ่มการทำงาน
                        </button>

                        <button id="stopBtn"
                                onclick="stopOptimization()"
                                disabled
                                class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                            </svg>
                            หยุด
                        </button>
                    </div>
                </div>

                <!-- Progress -->
                <div id="progressSection" class="bg-white rounded-lg shadow-sm p-6 mb-6 hidden">
                    <h2 class="text-lg font-semibold mb-4">ความคืบหน้า</h2>

                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>กำลังประมวลผล...</span>
                            <span id="progressText">0 / 0 (0%)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div id="progressBar" class="progress-bar bg-blue-600 h-4 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        <div class="p-3 bg-green-50 rounded-lg">
                            <div id="successCount" class="text-2xl font-bold text-green-600">0</div>
                            <div class="text-xs text-gray-600">สำเร็จ</div>
                        </div>
                        <div class="p-3 bg-red-50 rounded-lg">
                            <div id="errorCount" class="text-2xl font-bold text-red-600">0</div>
                            <div class="text-xs text-gray-600">ผิดพลาด</div>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg">
                            <div id="skippedCount" class="text-2xl font-bold text-blue-600">0</div>
                            <div class="text-xs text-gray-600">ข้าม</div>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div id="totalCount" class="text-2xl font-bold text-gray-600">0</div>
                            <div class="text-xs text-gray-600">ทั้งหมด</div>
                        </div>
                    </div>
                </div>

                <!-- Logs -->
                <div id="logsSection" class="bg-white rounded-lg shadow-sm p-6 hidden">
                    <h2 class="text-lg font-semibold mb-4">Log การทำงาน</h2>
                    <div id="logs" class="max-h-96 overflow-y-auto bg-gray-50 p-4 rounded-lg space-y-1">
                        <!-- Logs will be added here -->
                    </div>

                    <button onclick="downloadLogs()"
                            class="mt-4 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        ดาวน์โหลด Logs
                    </button>
                </div>

            </div>
        </main>
    </div>

    <?php include '../components/footer.php'; ?>

    <script>
let isRunning = false;
let shouldStop = false;
let processedCount = 0;
let successCount = 0;
let errorCount = 0;
let skippedCount = 0;
let totalImages = 0;
let logs = [];

async function startOptimization() {
    if (isRunning) return;

    const dryRun = document.getElementById('dryRun').checked;
    const batchSize = parseInt(document.getElementById('batchSize').value);

    const result = await Swal.fire({
        title: dryRun ? 'ยืนยันการทดสอบ' : 'ยืนยันการ Optimize',
        html: dryRun
            ? 'คุณต้องการทดสอบการ optimize (ไม่แก้ไขไฟล์จริง)?'
            : '<strong class="text-red-600">⚠️ คำเตือน:</strong><br>การทำงานนี้จะแก้ไขไฟล์และ database จริง<br>แนะนำให้สำรองข้อมูลก่อน<br><br>ต้องการดำเนินการต่อ?',
        icon: dryRun ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280'
    });

    if (!result.isConfirmed) return;

    // Reset
    isRunning = true;
    shouldStop = false;
    processedCount = 0;
    successCount = 0;
    errorCount = 0;
    skippedCount = 0;
    logs = [];

    document.getElementById('startBtn').disabled = true;
    document.getElementById('stopBtn').disabled = false;
    document.getElementById('progressSection').classList.remove('hidden');
    document.getElementById('logsSection').classList.remove('hidden');
    document.getElementById('logs').innerHTML = '';

    addLog('info', `เริ่มการทำงาน (${dryRun ? 'Dry Run' : 'Live Mode'})`);

    try {
        const response = await fetch('api/batch_optimize_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'start',
                dryRun: dryRun,
                batchSize: batchSize
            })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'เกิดข้อผิดพลาด');
        }

        totalImages = data.total;
        document.getElementById('totalCount').textContent = totalImages;

        addLog('info', `พบรูปภาพทั้งหมด ${totalImages} รูป`);

        // Process in batches
        await processBatches(dryRun, batchSize);

    } catch (error) {
        addLog('error', 'Error: ' + error.message);
        Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
    } finally {
        isRunning = false;
        document.getElementById('startBtn').disabled = false;
        document.getElementById('stopBtn').disabled = true;

        if (shouldStop) {
            addLog('info', 'หยุดการทำงานโดยผู้ใช้');
        } else {
            addLog('info', `เสร็จสิ้น! สำเร็จ: ${successCount}, ผิดพลาด: ${errorCount}, ข้าม: ${skippedCount}`);
            Swal.fire({
                icon: successCount > 0 ? 'success' : 'info',
                title: 'เสร็จสิ้น!',
                html: `สำเร็จ: ${successCount}<br>ผิดพลาด: ${errorCount}<br>ข้าม: ${skippedCount}`
            });
        }
    }
}

async function processBatches(dryRun, batchSize) {
    while (processedCount < totalImages && !shouldStop) {
        try {
            const response = await fetch('api/batch_optimize_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'process',
                    dryRun: dryRun,
                    offset: processedCount,
                    limit: batchSize
                })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message);
            }

            // Update stats
            data.results.forEach(result => {
                processedCount++;

                if (result.success) {
                    successCount++;
                    addLog('success', `✓ ${result.filename} → ${result.message}`);
                } else if (result.skipped) {
                    skippedCount++;
                    addLog('info', `⊘ ${result.filename} → ${result.message}`);
                } else {
                    errorCount++;
                    addLog('error', `✗ ${result.filename} → ${result.message}`);
                }

                updateProgress();
            });

            // Delay to prevent server overload
            await new Promise(resolve => setTimeout(resolve, 100));

        } catch (error) {
            addLog('error', 'Batch Error: ' + error.message);
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
    }
}

function updateProgress() {
    const percent = totalImages > 0 ? Math.round((processedCount / totalImages) * 100) : 0;
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressText').textContent = `${processedCount} / ${totalImages} (${percent}%)`;
    document.getElementById('successCount').textContent = successCount;
    document.getElementById('errorCount').textContent = errorCount;
    document.getElementById('skippedCount').textContent = skippedCount;
}

function stopOptimization() {
    if (isRunning) {
        shouldStop = true;
        document.getElementById('stopBtn').disabled = true;
        addLog('info', 'กำลังหยุดการทำงาน...');
    }
}

function addLog(type, message) {
    const timestamp = new Date().toLocaleTimeString('th-TH');
    logs.push({ timestamp, type, message });

    const logEntry = document.createElement('div');
    logEntry.className = `log-entry log-${type}`;
    logEntry.textContent = `[${timestamp}] ${message}`;

    const logsContainer = document.getElementById('logs');
    logsContainer.appendChild(logEntry);
    logsContainer.scrollTop = logsContainer.scrollHeight;
}

function downloadLogs() {
    const logText = logs.map(log => `[${log.timestamp}] [${log.type.toUpperCase()}] ${log.message}`).join('\n');
    const blob = new Blob([logText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `batch_optimize_log_${new Date().toISOString().slice(0, 10)}.txt`;
    a.click();
    URL.revokeObjectURL(url);
}
    </script>
</body>
</html>
