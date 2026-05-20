const APP_VERSION = "20260520-080537";
const STATIC_CACHE = "dent1402-static-" + APP_VERSION;

const STATIC_ASSETS = [
  "/offline.html",
  "/manifest.webmanifest?v=" + APP_VERSION,
  "/assets/site/styles/core.css?v=" + APP_VERSION,
  "/assets/site/styles/theme.css?v=" + APP_VERSION,
  "/assets/site/scripts/theme.js?v=" + APP_VERSION,
  "/assets/images/logo.png?v=" + APP_VERSION,
  "/assets/images/favicon.png?v=" + APP_VERSION,
  "/assets/icons/icon-192.png?v=" + APP_VERSION,
  "/assets/icons/icon-512.png?v=" + APP_VERSION,
  "/assets/icons/icon-maskable-192.png?v=" + APP_VERSION,
  "/assets/icons/icon-maskable-512.png?v=" + APP_VERSION,
  "/assets/icons/apple-touch-icon.png?v=" + APP_VERSION,
  "/fonts/AbarHigh-Regular.woff2",
  "/fonts/AbarHigh-SemiBold.woff2",
  "/fonts/AbarHigh-Bold.woff2",
  "/fonts/AbarHigh-ExtraBold.woff2",
  "/fonts/AbarHigh-Black.woff2",
  "/fonts/YekanBakh-VF.woff2",
  "/fonts/YekanBakh-VF.woff"
];

const DYNAMIC_BYPASS = [
  "/app-version.json",
  "/chat/chat_api.php",
  "/grades/grades_api.php",
  "/api/content_tools_api.php",
  "/api/forms_api.php",
  "/api/navid_api.php",
  "/api/payments_api.php",
  "/chat/data/",
  "/messages.json",
  "/state.json",
  "/users.csv",
  "/grades.csv"
];

self.addEventListener("install", (event) => {
  event.waitUntil((async () => {
    const staticCache = await caches.open(STATIC_CACHE);
    await staticCache.addAll(STATIC_ASSETS);
    self.skipWaiting();
  })());
});

self.addEventListener("activate", (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((key) => {
      if (key !== STATIC_CACHE) {
        return caches.delete(key);
      }
      return Promise.resolve();
    }));
    await self.clients.claim();
  })());
});

self.addEventListener("message", (event) => {
  if (!event.data || event.data.type !== "SKIP_WAITING") {
    return;
  }
  self.skipWaiting();
});

function shouldBypass(url, request) {
  if (request.method !== "GET") {
    return true;
  }

  if (url.origin !== self.location.origin) {
    return false;
  }

  return DYNAMIC_BYPASS.some((segment) => url.pathname.includes(segment));
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cached = await cache.match(request);
  const networkPromise = fetch(request).then((response) => {
    if (response && response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  }).catch(() => cached);
  return cached || networkPromise;
}

async function networkFirstAsset(request) {
  const cache = await caches.open(STATIC_CACHE);
  try {
    const response = await fetch(request, { cache: "no-store" });
    if (response && response.ok) {
      await cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    const cached = await cache.match(request);
    if (cached) {
      return cached;
    }
    throw error;
  }
}

async function networkOnlyPage(request) {
  try {
    return await fetch(request, { cache: "no-store" });
  } catch (error) {
    return caches.match("/offline.html");
  }
}

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (shouldBypass(url, request)) {
    return;
  }

  if (request.mode === "navigate" && url.origin === self.location.origin) {
    event.respondWith(networkOnlyPage(request));
    return;
  }

  if (url.origin === self.location.origin &&
      (request.destination === "style" ||
       request.destination === "script")) {
    event.respondWith(networkFirstAsset(request));
    return;
  }

  if (url.origin === self.location.origin &&
      (request.destination === "font" ||
       request.destination === "image")) {
    event.respondWith(staleWhileRevalidate(request));
  }
});
