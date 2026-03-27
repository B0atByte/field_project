<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/permissions.php';
requirePermission('page_map');
include '../config/db.php';
require_once '../config/env.php';
$googleMapsKey = env('GOOGLE_MAPS_API_KEY', '');

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$q = $_GET['q'] ?? '';
$product = $_GET['product'] ?? '';
$job_id = $_GET['job_id'] ?? null;

/* ตัวเลือก product */
$productOptions = [];
$prodRes = $conn->query("SELECT DISTINCT product FROM jobs WHERE product IS NOT NULL AND product != '' ORDER BY product ASC");
while ($row = $prodRes->fetch_assoc())
  $productOptions[] = $row['product'];
?>
<!DOCTYPE html>
<html lang="th">
<?php include '../components/header.php'; ?>

<body class="bg-gray-100 text-gray-800 font-sans min-h-screen flex flex-col">
  <div class="flex flex-1 flex-col md:flex-row">
    <?php include '../components/sidebar.php'; ?>
    <main class="flex-1 p-4 md:p-6 md:ml-64">
      <div class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <div class="flex justify-between items-center flex-wrap gap-4 pb-4 border-b border-gray-200">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-3 rounded-xl shadow-md">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
              </div>
              แผนที่ตำแหน่งงาน
            </h1>
            <p class="text-gray-600 mt-2 ml-1">ติดตามและจัดการงานภาคสนามบนแผนที่</p>
          </div>
          <a href="<?= $_SESSION['user']['role'] === 'admin' ? '../dashboard/admin.php' : ($_SESSION['user']['role'] === 'field' ? '../dashboard/field.php' : '../dashboard/manager.php') ?>"
            class="bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white px-6 py-3 rounded-lg font-medium shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            กลับ
          </a>
        </div>

        <form id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">เริ่มต้น</label>
            <input type="date" id="start" value="<?= htmlspecialchars($start) ?>"
              class="w-full border border-gray-300 px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
              <?= $job_id ? 'disabled' : '' ?>>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">สิ้นสุด</label>
            <input type="date" id="end" value="<?= htmlspecialchars($end) ?>"
              class="w-full border border-gray-300 px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
              <?= $job_id ? 'disabled' : '' ?>>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
            <select id="product"
              class="w-full border border-gray-300 px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
              <?= $job_id ? 'disabled' : '' ?>>
              <option value="">-- ทั้งหมด --</option>
              <?php foreach ($productOptions as $prod): ?>
                <option value="<?= htmlspecialchars($prod) ?>" <?= $prod === $product ? 'selected' : '' ?>>
                  <?= htmlspecialchars($prod) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="sm:col-span-2 md:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">ค้นหา</label>
            <input type="text" id="q" value="<?= htmlspecialchars($q) ?>"
              class="w-full border border-gray-300 px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
              placeholder="เลขสัญญา หรือ พื้นที่" <?= $job_id ? 'disabled' : '' ?>>
          </div>

          <div class="flex gap-2">
            <button type="button" id="btnSearch"
              class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-2.5 rounded-lg font-medium w-full shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              ค้นหา
            </button>
          </div>
        </form>

        <div class="w-full bg-gray-50 rounded-xl p-4 border border-gray-200">
          <div id="map" class="w-full h-[480px] sm:h-[600px] rounded-lg border-2 border-gray-300 shadow-inner"></div>
          <div id="map-fallback"
            class="fallback text-red-600 mt-3 p-4 bg-red-50 rounded-lg hidden flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            ไม่สามารถโหลดแผนที่ได้ กรุณาลองใหม่อีกครั้ง
          </div>
          <div id="hint"
            class="text-sm text-gray-600 mt-3 flex items-center gap-2 bg-blue-50 p-3 rounded-lg border border-blue-200">
            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-blue-800">แสดงเฉพาะหมุดในมุมมองปัจจุบัน (เลื่อน/ซูมเพื่อโหลดเพิ่ม)</span>
          </div>
        </div>
      </div>
    </main>
  </div>

  <?php include '../components/footer.php'; ?>

  <script>
    (g => { var h, a, k, p = "The Google Maps JavaScript API", c = "google", l = "importLibrary", q = "__ib__", m = document, b = window; b = b[c] || (b[c] = {}); var d = b.maps || (b.maps = {}), r = new Set, e = new URLSearchParams, u = () => h || (h = new Promise(async (f, n) => { await (a = m.createElement("script")); e.set("libraries", [...r]); for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]); e.set("callback", c + ".maps." + q); a.src = `https://maps.googleapis.com/maps/api/js?` + e; d[q] = f; a.onerror = () => h = n(Error(p + " could not load.")); a.nonce = m.querySelector("script[nonce]")?.nonce || ""; m.head.append(a) })); d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n)) })({
      key: "<?= htmlspecialchars($googleMapsKey) ?>"
    });
  </script>
  <script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js" async></script>
  <script>
    let map, clusterer, lastController = null, mapLoaded = false;
    const jobId = <?= $job_id ? intval($job_id) : 'null' ?>;

    function debounce(fn, delay = 400) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); } }

    async function initMap() {
      // โหลด Maps library ก่อน
      await google.maps.importLibrary("maps");
      await google.maps.importLibrary("marker");

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

    // เรียก initMap เมื่อหน้าโหลดเสร็จ
    window.addEventListener('load', initMap);

    function getFilters() {
      return {
        start: document.getElementById('start')?.value || '',
        end: document.getElementById('end')?.value || '',
        product: document.getElementById('product')?.value || '',
        q: document.getElementById('q')?.value || ''
      };
    }

    async function loadMarkers(isSearch) {
      if (!map.getBounds()) return;

      const b = map.getBounds();
      const ne = b.getNorthEast();
      const sw = b.getSouthWest();
      const f = getFilters();

      const params = new URLSearchParams({
        north: ne.lat(), south: sw.lat(),
        east: ne.lng(), west: sw.lng(),
        zoom: map.getZoom()
      });
      if (f.start) params.append('start', f.start);
      if (f.end) params.append('end', f.end);
      if (f.product) params.append('product', f.product);
      if (f.q) params.append('q', f.q);
      if (jobId) params.append('job_id', jobId);

      // ยกเลิกคำขอเก่า
      if (lastController) lastController.abort();
      lastController = new AbortController();

      try {
        const url = './api/map_markers.php?' + params.toString();
        const res = await fetch(url, { signal: lastController.signal });
        if (!res.ok) throw new Error('API error: ' + res.status);
        const data = await res.json();

        clusterer.clearMarkers();

        const markers = (data.markers || []).map(m => {
          const marker = new google.maps.Marker({ position: { lat: m.lat, lng: m.lng }, title: m.product || '' });
          const info = new google.maps.InfoWindow({
            content: `<div class="p-2">
                   <strong class="text-lg">${m.product ?? ''}</strong><br>
                   <div class="mt-2 space-y-1">
                     <div><i class="fas fa-file-alt mr-1"></i>สัญญา: <span class="font-semibold">${m.contract ?? ''}</span></div>
                     <div><i class="fas fa-map-marker-alt mr-1"></i>ชื่อลูกค้า: <span class="font-semibold">${m.location ?? ''}</span></div>
                     <div>${m.created ? new Date(m.created).toLocaleString('th-TH') : ''}</div>
                   </div>
                   <a href="../dashboard/job_result.php?id=${m.id}" 
                      class="inline-block mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors" 
                      target="_blank">ดูผลงาน →</a>
                 </div>`
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