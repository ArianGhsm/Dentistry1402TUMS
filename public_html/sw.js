const STATIC_CACHE = "dent1402-static-20260422-v8";

const STATIC_ASSETS = [
  "/offline.html",
  "/manifest.webmanifest",
  "/assets/site/styles/core.css?v=20260422-cache1",
  "/assets/site/styles/app.css?v=20260422-cache1",
  "/assets/site/styles/content.css?v=20260422-cache1",
  "/assets/site/styles/hub.css?v=20260422-cache1",
  "/assets/site/styles/chat.css?v=20260422-cache1",
  "/assets/site/styles/grades.css?v=20260422-cache1",
  "/assets/site/styles/quiz.css?v=20260422-cache1",
  "/assets/site/styles/theme.css?v=20260422-cache1",
  "/assets/site/styles/account.css?v=20260422-cache1",
  "/assets/site/scripts/theme.js?v=20260422-cache1",
  "/assets/site/scripts/pwa.js?v=20260422-cache1",
  "/assets/site/scripts/shell.js?v=20260422-cache1",
  "/assets/site/scripts/auth.js?v=20260422-cache1",
  "/assets/site/scripts/account.js?v=20260422-cache1",
  "/assets/site/scripts/chat.js?v=20260422-cache1",
  "/assets/site/scripts/grades.js?v=20260422-cache1",
  "/assets/images/logo.png",
  "/assets/images/favicon.png",
  "/assets/icons/icon-192.png",
  "/assets/icons/icon-512.png",
  "/assets/icons/icon-maskable-192.png",
  "/assets/icons/icon-maskable-512.png",
  "/assets/icons/apple-touch-icon.png",
  "/fonts/AbarHigh-Regular.ttf",
  "/fonts/AbarHigh-SemiBold.ttf",
  "/fonts/AbarHigh-Bold.ttf",
  "/fonts/YekanBakh-VF.woff2",
  "/fonts/YekanBakh-VF.woff"
];

const DYNAMIC_BYPASS = [
  "/chat/chat_api.php",
  "/grades/grades_api.php",
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
       request.destination === "script" ||
       request.destination === "font" ||
       request.destination === "image")) {
    event.respondWith(staleWhileRevalidate(request));
  }
});
