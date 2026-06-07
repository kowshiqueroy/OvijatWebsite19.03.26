self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'New Message';
  const options = {
    body: data.body || 'You have received a new secure message.',
    icon: 'https://www.gstatic.com/lamda/images/favicon_v1_150160c13ff2af13800c.png',
    badge: 'https://www.gstatic.com/lamda/images/favicon_v1_150160c13ff2af13800c.png',
    data: { url: 'index.php' }
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes('index.php') && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow('index.php');
      }
    })
  );
});
