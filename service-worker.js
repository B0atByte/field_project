// Service Worker ถูกปิดการใช้งานเพื่อหลีกเลี่ยงปัญหา CSP และ CDN

// Unregister เมื่อมีการโหลด Service Worker นี้
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    // ลบ cache ทั้งหมด
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => caches.delete(cacheName))
      );
    }).then(() => {
      // Unregister ตัวเอง
      return self.registration.unregister();
    }).then(() => {
      // Reload หน้าทั้งหมด
      return clients.matchAll({ type: 'window' }).then(clients => {
        clients.forEach(client => client.navigate(client.url));
      });
    })
  );
});
