// Service Worker สำหรับ Web Push Notifications
const CACHE_NAME = 'event-schedule-v1';
const urlsToCache = [
  '/',
  '/index_desktop.php',
  '/images/logo.jpg'
];

// ติดตั้ง Service Worker
self.addEventListener('install', event => {
  console.log('Service Worker installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Cache opened');
        return cache.addAll(urlsToCache);
      })
  );
  self.skipWaiting();
});

// เปิดใช้งาน Service Worker
self.addEventListener('activate', event => {
  console.log('Service Worker activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

// รับ Push Notification
self.addEventListener('push', event => {
  console.log('Push notification received:', event);
  
  let notificationData = {
    title: 'การแจ้งเตือนกิจกรรม',
    body: 'มีกิจกรรมใหม่',
    icon: '/images/logo.jpg',
    badge: '/images/logo.jpg',
    vibrate: [200, 100, 200],
    tag: 'event-notification',
    requireInteraction: true
  };

  // ถ้ามีข้อมูลจาก server
  if (event.data) {
    try {
      const data = event.data.json();
      notificationData = {
        ...notificationData,
        ...data
      };
    } catch (e) {
      notificationData.body = event.data.text();
    }
  }

  event.waitUntil(
    self.registration.showNotification(notificationData.title, {
      body: notificationData.body,
      icon: notificationData.icon,
      badge: notificationData.badge,
      vibrate: notificationData.vibrate,
      tag: notificationData.tag,
      requireInteraction: notificationData.requireInteraction,
      data: notificationData.data || {}
    })
  );
});

// จัดการเมื่อคลิก Notification
self.addEventListener('notificationclick', event => {
  console.log('Notification clicked:', event);
  
  event.notification.close();
  
  // เปิดหน้าเว็บเมื่อคลิก notification
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        // ถ้ามีหน้าต่างเปิดอยู่แล้ว ให้ focus
        for (let client of clientList) {
          if (client.url === '/' && 'focus' in client) {
            return client.focus();
          }
        }
        // ถ้าไม่มี ให้เปิดหน้าต่างใหม่
        if (clients.openWindow) {
          return clients.openWindow('/');
        }
      })
  );
});

// จัดการ Fetch (cache first strategy)
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // ถ้ามีใน cache ให้ใช้จาก cache
        if (response) {
          return response;
        }
        // ถ้าไม่มี ให้ fetch จาก network
        return fetch(event.request);
      })
  );
});