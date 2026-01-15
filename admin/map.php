<?php
require_once __DIR__ . '/../includes/session_config.php';
if (!in_array($_SESSION['user']['role'], ['admin', 'field', 'manager'])) {
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';

$start   = $_GET['start']   ?? '';
$end     = $_GET['end']     ?? '';
$q       = $_GET['q']       ?? '';
$product = $_GET['product'] ?? '';
$job_id  = $_GET['job_id']  ?? null;

/* ตัวเลือก product */
$productOptions = [];
$prodRes = $conn->query("SELECT DISTINCT product FROM jobs WHERE product IS NOT NULL AND product != '' ORDER BY product ASC");
while ($row = $prodRes->fetch_assoc()) $productOptions[] = $row['product'];
?>
<!DOCTYPE html>
<html lang="th">
<?php include '../components/header.php'; ?>
<body class="bg-gray-100 text-gray-800 font-sans min-h-screen flex flex-col">
<div class="flex flex-1 flex-col md:flex-row">
  <?php include '../components/sidebar.php'; ?>
  <main class="flex-1 p-4 md:p-6 md:ml-64">
    <div class="bg-white rounded-xl shadow-lg p-6 space-y-6">
      <div class="flex justify-between items-center flex-wrap gap-2">
        <h1 class="text-2xl font-bold">🗺️ แผนที่ตำแหน่งงาน</h1>
        <a href="<?= $_SESSION['user']['role'] === 'admin' ? '../dashboard/admin.php' : ($_SESSION['user']['role'] === 'field' ? '../dashboard/field.php' : '../dashboard/manager.php') ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">🔙 กลับ</a>
      </div>

      <form id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-7 gap-4 items-end">
        <div>
          <label class="block text-sm mb-1">📅 เริ่มต้น</label>
          <input type="date" id="start" value="<?= htmlspecialchars($start) ?>" class="w-full border px-3 py-2 rounded" <?= $job_id ? 'disabled' : '' ?>>
        </div>
        <div>
          <label class="block text-sm mb-1">📅 สิ้นสุด</label>
          <input type="date" id="end" value="<?= htmlspecialchars($end) ?>" class="w-full border px-3 py-2 rounded" <?= $job_id ? 'disabled' : '' ?>>
        </div>
        <div>
          <label class="block text-sm mb-1">📦 Product</label>
          <select id="product" class="w-full border px-3 py-2 rounded" <?= $job_id ? 'disabled' : '' ?>>
            <option value="">-- ทั้งหมด --</option>
            <?php foreach ($productOptions as $prod): ?>
              <option value="<?= htmlspecialchars($prod) ?>" <?= $prod === $product ? 'selected' : '' ?>><?= htmlspecialchars($prod) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sm:col-span-2 md:col-span-2">
          <label class="block text-sm mb-1">🔎 ค้นหา</label>
          <input type="text" id="q" value="<?= htmlspecialchars($q) ?>" class="w-full border px-3 py-2 rounded" placeholder="เลขสัญญา หรือ พื้นที่" <?= $job_id ? 'disabled' : '' ?>>
        </div>

        <div class="flex items-center gap-2">
          <input type="checkbox" id="latestOnly" class="w-4 h-4">
          <label for="latestOnly" class="text-sm">เฉพาะมุดล่าสุดต่อสัญญา</label>
        </div>

        <div class="flex gap-2">
          <button type="button" id="btnSearch" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded w-full">🔍 ค้นหา</button>
        </div>
      </form>

      <div class="w-full">
        <div id="map" class="w-full h-[420px] sm:h-[540px] rounded border border-gray-300"></div>
        <div id="map-fallback" class="fallback text-red-600 mt-2 hidden">❌ ไม่สามารถโหลดแผนที่ได้</div>
        <div id="hint" class="text-sm text-gray-500 mt-2">แสดงเฉพาะหมุดในมุมมองปัจจุบัน (เลื่อน/ซูมเพื่อโหลดเพิ่ม)</div>
      </div>
    </div>
  </main>
</div>

<?php include '../components/footer.php'; ?>

<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB3NfHFEyJb3yltga-dX0C23jsLEAQpORc&callback=initMap" async defer></script>
<script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
<script>
let map, clusterer, lastController = null, mapLoaded = false;
const jobId = <?= $job_id ? intval($job_id) : 'null' ?>;

function debounce(fn, delay=400){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),delay); } }

function initMap() {
  mapLoaded = true;
  map = new google.maps.Map(document.getElementById('map'), {
    center: { lat: 13.7563, lng: 100.5018 },
    zoom: 10,
    mapTypeId: 'roadmap'
  });

  clusterer = new markerClusterer.MarkerClusterer({ map, markers: [] });

  // โหลดเมื่อ idle (เลื่อน/ซูมเสร็จ)
  google.maps.event.addListener(map, 'idle', debounce(() => loadMarkers(false)));

  // ปุ่มค้นหา (fitBounds หลังค้นหา)
  document.getElementById('btnSearch').addEventListener('click', () => loadMarkers(true));

  // โหลดแรกเริ่ม
  loadMarkers(false);
}

function getFilters() {
  return {
    start:   document.getElementById('start')?.value || '',
    end:     document.getElementById('end')?.value || '',
    product: document.getElementById('product')?.value || '',
    q:       document.getElementById('q')?.value || '',
    latest:  document.getElementById('latestOnly')?.checked ? 1 : 0
  };
}

async function loadMarkers(isSearch) {
  if (!map.getBounds()) return;

  const b  = map.getBounds();
  const ne = b.getNorthEast();
  const sw = b.getSouthWest();
  const f  = getFilters();

  const params = new URLSearchParams({
    north: ne.lat(), south: sw.lat(),
    east: ne.lng(),  west: sw.lng(),
    zoom: map.getZoom()
  });
  if (f.start)   params.append('start', f.start);
  if (f.end)     params.append('end', f.end);
  if (f.product) params.append('product', f.product);
  if (f.q)       params.append('q', f.q);
  if (f.latest)  params.append('latest_only', '1');
  if (jobId)     params.append('job_id', jobId);

  // ยกเลิกคำขอเก่า
  if (lastController) lastController.abort();
  lastController = new AbortController();

  try {
    const url = './api/map_markers.php?' + params.toString();
    const res  = await fetch(url, { signal: lastController.signal });
    if (!res.ok) throw new Error('API error: ' + res.status);
    const data = await res.json();

    clusterer.clearMarkers();

    const markers = (data.markers || []).map(m => {
      const marker = new google.maps.Marker({ position: {lat: m.lat, lng: m.lng}, title: m.product || '' });
      const info = new google.maps.InfoWindow({
        content: `<strong>${m.product ?? ''}</strong><br>
                  📄 สัญญา: ${m.contract ?? ''}<br>
                  📍 ชื่อลูกค้า: ${m.location ?? ''}<br>
                  🕒 ${m.created ? new Date(m.created).toLocaleString() : ''}<br>
                  <a href="/field_project/dashboard/job_result.php?id=${m.id}" class="text-blue-600 underline" target="_blank">ดูผลงาน</a>`
      });
      marker.addListener('click', () => info.open(map, marker));
      return marker;
    });

    clusterer.addMarkers(markers);

    // fit bounds เมื่อกดค้นหา และมีผลลัพธ์
    if (isSearch && markers.length > 0) {
      const bounds = new google.maps.LatLngBounds();
      markers.forEach(mk => bounds.extend(mk.getPosition()));
      map.fitBounds(bounds);
    }

    // แจ้งถ้าไม่พบมุด
    if (isSearch && markers.length === 0) {
      alert('ไม่พบมุดตามเงื่อนไขที่ค้นหา ลองปรับวันที่/คำค้น/ซูมแผนที่ใหม่');
    }

    if (data.truncated) console.warn('ผลลัพธ์ถูกตัดที่ LIMIT; ซูมใกล้ขึ้นหรือกรองเพิ่ม');

  } catch (e) {
    console.error(e);
  }
}

setTimeout(() => {
  if (!mapLoaded) {
    document.getElementById("map").style.display = "none";
    document.getElementById("map-fallback").classList.remove("hidden");
  }
}, 10000);
</script>
</body>
</html>
