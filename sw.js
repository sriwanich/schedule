const CACHE_NAME = 'pdrro-calendar-v1';
const OFFLINE_URL = '/offline.html';
const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/m_dashboard',
  '/dashboard',
  '/styles.css',
  '/images/logo.jpg',
  '/images/icon-72x72.png',
  '/images/icon-192x192.png',
  '/images/icon-512x512.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css',
  'https://fonts.googleapis.com/css2?family=Anuphan:wght@100;200;300;400&display=swap',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js'
];

// ติดตั้ง Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Opened cache');
        return cache.addAll(ASSETS_TO_CACHE);
      })
      .then(() => self.skipWaiting())
  );
});

// เปิดใช้งาน Service Worker
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// ดึงข้อมูลจาก cache หรือ network
self.addEventListener('fetch', (event) => {
  // ข้ามคำขอสำหรับข้อมูลแบบ real-time
  if (event.request.url.includes('/api/') || 
      event.request.url.includes('events') || 
      event.request.method !== 'GET') {
    return fetch(event.request);
  }

  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // คืนค่าจาก cache ถ้ามี
        if (response) {
          return response;
        }

        // ไม่มีใน cache ให้ดึงจาก network
        return fetch(event.request).then((response) => {
          // ตรวจสอบว่าคำขอสำเร็จหรือไม่
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          // คัดลอก response เพื่อเก็บใน cache
          const responseToCache = response.clone();
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(event.request, responseToCache);
            });

          return response;
        }).catch(() => {
          // ถ้า offline และเป็นหน้าแรก ให้แสดง offline page
          if (event.request.mode === 'navigate') {
            return caches.match(OFFLINE_URL);
          }
          return new Response('You are offline', { status: 503, statusText: 'Service Unavailable' });
        });
      })
  );
});

// การจัดการ Push Notification
self.addEventListener('push', (event) => {
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: '/images/icon-192x192.png',
    badge: '/images/icon-72x72.png',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then((clientList) => {
      if (clientList.length > 0) {
        return clientList[0].focus();
      }
      return clients.openWindow(event.notification.data.url || '/');
    })
  );
});

// ตรวจสอบการอัพเดทเมื่อได้รับคำสั่งจากหน้าเว็บ
self.addEventListener('message', (event) => {
  if (event.data.type === 'CHECK_FOR_UPDATES') {
    self.registration.update().then(() => {
      console.log('Service Worker updated');
    });
  }
});