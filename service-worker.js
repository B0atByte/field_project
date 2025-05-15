const CACHE_NAME = "field-cache-v1";
const urlsToCache = [
  "/field_project/index.php",
  "/field_project/dashboard/admin.php",
  "/field_project/dashboard/field.php",
  "/field_project/admin/jobs.php",
  "/field_project/view_job.php",
  "/field_project/assets/icon-192.png",
  "/field_project/assets/icon-512.png"
];

// ติดตั้ง (แคชไฟล์)
self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
  );
});

// อัปเดตเวอร์ชันใหม่ (ลบ cache เก่า)
self.addEventListener("activate", event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(name => {
          if (name !== CACHE_NAME) {
            return caches.delete(name);
          }
        })
      );
    })
  );
});

// ดึงข้อมูล (cache-first strategy)
self.addEventListener("fetch", event => {
  event.respondWith(
    caches.match(event.request).then(response => response || fetch(event.request))
  );
});
