const STATIC_CACHE = "dent1402-static-20260421-v2";
const PAGE_CACHE = "dent1402-pages-20260421-v2";
const STATIC_ASSETS = [
  "/",
  "/app/",
  "/chat/",
  "/grades/",
  "/notes/",
  "/notes/1403/",
  "/resources/",
  "/offline.html",
  "/manifest.webmanifest",
  "/assets/site/styles/core.css?v=20260421-pwa",
  "/assets/site/styles/app.css?v=20260421-pwa",
  "/assets/site/styles/content.css?v=20260421-pwa",
  "/assets/site/styles/hub.css?v=20260421-pwa",
  "/assets/site/styles/chat.css?v=20260421-telegram",
  "/assets/site/styles/grades.css?v=20260421-pwa",
  "/assets/site/styles/quiz.css?v=20260421-pwa",
  "/assets/site/scripts/pwa.js?v=20260421-pwa",
  "/assets/site/scripts/shell.js?v=20260421-pwa",
  "/assets/images/logo.png",
  "/assets/images/favicon.png",
  "/assets/icons/icon-192.png",
  "/assets/icons/icon-512.png",
  "/assets/icons/icon-maskable-192.png",
  "/assets/icons/icon-maskable-512.png",
  "/assets/icons/apple-touch-icon.png",
  "/fonts/YekanBakh-VF.woff2",
  "/fonts/YekanBakh-VF.woff"
];

const PAGE_ROUTES = [
  "/exams/",
  "/exams/partialprosthesis/",
  "/exams/partialprosthesis/1/",
  "/exams/partialprosthesis/2/",
  "/exams/partialprosthesis/3/",
  "/exams/partialprosthesis/4/",
  "/exams/partialprosthesis/5/",
  "/exams/partialprosthesis/6/",
  "/exams/partialprosthesis/7/",
  "/exams/completeprosthesis/",
  "/exams/completeprosthesis/1/",
  "/exams/completeprosthesis/2/",
  "/exams/completeprosthesis/3/",
  "/exams/completeprosthesis/4/",
  "/exams/completeprosthesis/5/",
  "/exams/pharmacology/",
  "/exams/pharmacology/1/",
  "/exams/pharmacology/2/",
  "/exams/pharmacology/3/",
  "/exams/pharmacology/4-1/",
  "/exams/pharmacology/4-2/",
  "/exams/pharmacology/5/",
  "/exams/pharmacology/6/",
  "/exams/pharmacology/8/",
  "/exams/systemicdiseases/",
  "/exams/systemicdiseases/1/",
  "/exams/systemicdiseases/2/",
  "/exams/systemicdiseases/3/",
  "/exams/systemicdiseases/4/",
  "/exams/systemicdiseases/5/",
  "/exams/systemicdiseases/6/",
  "/exams/systemicdiseases/7/",
  "/exams/systemicdiseases/8/",
  "/exams/systemicdiseases/9/",
  "/exams/systemicdiseases/10/",
  "/exams/systemicdiseases/11/",
  "/exams/systemicdiseases/12/",
  "/exams/systemicdiseases/13/",
  "/exams/systemicdiseases/14/",
  "/exams/systemicdiseases/15/",
  "/exams/systemicdiseases/16/",
  "/exams/systemicdiseases/17/"
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

    const pageCache = await caches.open(PAGE_CACHE);
    await pageCache.addAll(PAGE_ROUTES);
    self.skipWaiting();
  })());
});

self.addEventListener("activate", (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((key) => {
      if (key !== STATIC_CACHE && key !== PAGE_CACHE) {
        return caches.delete(key);
      }
      return Promise.resolve();
    }));
    await self.clients.claim();
  })());
});

function shouldBypass(requestUrl, request) {
  if (request.method !== "GET") {
    return true;
  }

  if (requestUrl.origin !== self.location.origin) {
    return false;
  }

  return DYNAMIC_BYPASS.some((segment) => requestUrl.pathname.includes(segment));
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

async function networkFirstPage(request) {
  const cache = await caches.open(PAGE_CACHE);
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    const cached = await cache.match(request);
    return cached || caches.match("/offline.html");
  }
}

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (shouldBypass(url, request)) {
    return;
  }

  if (request.mode === "navigate" && url.origin === self.location.origin) {
    event.respondWith(networkFirstPage(request));
    return;
  }

  if (url.origin === self.location.origin && (request.destination === "style" || request.destination === "script" || request.destination === "font" || request.destination === "image")) {
    event.respondWith(staleWhileRevalidate(request));
  }
});
