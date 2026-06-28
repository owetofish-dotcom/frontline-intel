/* CzaseoPraceo — Service Worker. Cache app-shell, by kiosk działał offline.
   Odbicia i dane idą przez IndexedDB w app.js; API nie cache'ujemy. */
'use strict';

const CACHE = 'czaseo-kiosk-v2';
const SHELL = [
  './',
  './index.html',
  './style.css',
  './app.js',
  './imieniny.js',
  './manifest.webmanifest',
  './icon.svg'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;                 // POST (sync) zawsze do sieci
  const url = new URL(req.url);
  if (url.pathname.includes('/api/')) return;        // API nie z cache

  // App-shell: cache-first, z aktualizacją w tle.
  e.respondWith(
    caches.match(req).then(cached => {
      const net = fetch(req).then(res => {
        if (res && res.ok) caches.open(CACHE).then(c => c.put(req, res.clone()));
        return res;
      }).catch(() => cached);
      return cached || net;
    })
  );
});
