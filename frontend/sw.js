/**
 * Service Worker for Church TV PWA
 * Handles caching, offline functionality, and push notifications
 */

const CACHE_NAME = 'lcmtv-v1.0.0';
const STATIC_CACHE_NAME = 'lcmtv-static-v1.0.0';
const DYNAMIC_CACHE_NAME = 'lcmtv-dynamic-v1.0.0';

// Resources to cache immediately (minimal set to avoid errors)
const STATIC_ASSETS = [
    '/LCMTVWebNew/frontend/index.html',
    '/LCMTVWebNew/frontend/manifest.json'
];

// API endpoints to cache with network-first strategy
const API_ENDPOINTS = [
    '/api/categories',
    '/api/videos/featured',
    '/api/languages',
    '/api/languages/translations'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker');

    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE_NAME).then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            }),
            // Skip waiting to activate immediately
            self.skipWaiting()
        ])
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker');

    event.waitUntil(
        Promise.all([
            // Clean up old caches
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== STATIC_CACHE_NAME &&
                            cacheName !== DYNAMIC_CACHE_NAME &&
                            !cacheName.startsWith('lcmtv-offline-')) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            // Take control of all clients
            self.clients.claim()
        ])
    );
});

// Fetch event - handle requests
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Handle API requests with network-first strategy
    if (isApiRequest(url)) {
        event.respondWith(networkFirstStrategy(request));
        return;
    }

    // Handle static assets with cache-first strategy
    if (isStaticAsset(request.url)) {
        event.respondWith(cacheFirstStrategy(request));
        return;
    }

    // Default: network-first for HTML, cache-first for others
    if (request.destination === 'document') {
        event.respondWith(networkFirstStrategy(request));
    } else {
        event.respondWith(cacheFirstStrategy(request));
    }
});

// Push notification event
self.addEventListener('push', (event) => {
    console.log('[SW] Push received:', event);

    let data = {};
    if (event.data) {
        data = event.data.json();
    }

    const options = {
        body: data.body || 'New content available!',
        icon: '/LCMTVWebNew/lctv-logo-white.png',
        badge: '/LCMTVWebNew/lctv-logo-white.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/'
        },
        actions: [
            {
                action: 'view',
                title: 'View'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Church TV', options)
    );
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event);

    event.notification.close();

    if (event.action === 'view') {
        const url = event.notification.data.url || '/';
        event.waitUntil(
            clients.openWindow(url)
        );
    }
});

// Background sync for offline actions
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync:', event.tag);

    if (event.tag === 'background-sync') {
        event.waitUntil(syncOfflineData());
    }
});

// Message event for communication with main thread
self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);

    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: '1.0.0' });
    }
});

// Helper functions

function isApiRequest(url) {
    return url.pathname.startsWith('/api/') ||
           url.pathname.startsWith('/backend/api/') ||
           API_ENDPOINTS.some(endpoint => url.pathname.includes(endpoint));
}

function isStaticAsset(url) {
    const staticExtensions = ['.js', '.css', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
    return staticExtensions.some(ext => url.endsWith(ext)) ||
           STATIC_ASSETS.includes(new URL(url).pathname);
}

function cacheFirstStrategy(request) {
    return caches.match(request)
        .then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request)
                .then((networkResponse) => {
                    // Cache successful GET responses only (POST requests cannot be cached)
                    if (networkResponse.ok && request.method === 'GET') {
                        const responseClone = networkResponse.clone();
                        caches.open(DYNAMIC_CACHE_NAME)
                            .then((cache) => cache.put(request, responseClone));
                    }
                    return networkResponse;
                })
                .catch(() => {
                    // Return offline fallback for documents
                    if (request.destination === 'document') {
                        return caches.match('/offline.html');
                    }
                });
        });
}

function networkFirstStrategy(request) {
    return fetch(request)
        .then((networkResponse) => {
            // Cache successful responses
            if (networkResponse.ok) {
                const responseClone = networkResponse.clone();
                caches.open(DYNAMIC_CACHE_NAME)
                    .then((cache) => cache.put(request, responseClone));
            }
            return networkResponse;
        })
        .catch(() => {
            // Try cache as fallback
            return caches.match(request)
                .then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }

                    // Return offline page for navigation requests
                    if (request.destination === 'document') {
                        return caches.match('/offline.html');
                    }
                });
        });
}

function syncOfflineData() {
    // Sync offline comments, reactions, and other data
    return Promise.resolve();
}

// Cache video content for offline viewing
function cacheVideo(videoId, videoUrl) {
    return caches.open('lcmtv-offline-videos')
        .then((cache) => {
            return cache.add(videoUrl);
        })
        .then(() => {
            // Notify client that video is cached
            return self.clients.matchAll()
                .then((clients) => {
                    clients.forEach((client) => {
                        client.postMessage({
                            type: 'VIDEO_CACHED',
                            videoId: videoId
                        });
                    });
                });
        });
}

// Remove cached video
function removeCachedVideo(videoId, videoUrl) {
    return caches.open('lcmtv-offline-videos')
        .then((cache) => {
            return cache.delete(videoUrl);
        })
        .then(() => {
            // Notify client that video is removed
            return self.clients.matchAll()
                .then((clients) => {
                    clients.forEach((client) => {
                        client.postMessage({
                            type: 'VIDEO_REMOVED',
                            videoId: videoId
                        });
                    });
                });
        });
}

// Periodic cleanup of old cache entries
function cleanupOldCaches() {
    const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 days
    const now = Date.now();

    return caches.open(DYNAMIC_CACHE_NAME)
        .then((cache) => {
            return cache.keys()
                .then((requests) => {
                    return Promise.all(
                        requests.map((request) => {
                            return cache.match(request)
                                .then((response) => {
                                    if (response) {
                                        const date = response.headers.get('date');
                                        if (date && (now - new Date(date).getTime()) > maxAge) {
                                            return cache.delete(request);
                                        }
                                    }
                                });
                        })
                    );
                });
        });
}

// Run cleanup periodically
setInterval(cleanupOldCaches, 24 * 60 * 60 * 1000); // Daily cleanup