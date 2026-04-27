const APP_VERSION = "20260427-235833";
const STATIC_CACHE = "dent1402-static-" + APP_VERSION;

const STATIC_ASSETS = [
  "/offline.html",
  "/manifest.webmanifest?v=" + APP_VERSION,
  "/assets/site/styles/core.css?v=20260422-cache2",
  "/assets/site/styles/app.css?v=20260422-cache1",
  "/assets/site/styles/app.css?v=20260422-navidhome1",
  "/assets/site/styles/app.css?v=20260422-homeall1",
  "/assets/site/styles/app.css?v=20260422-resources2",
  "/assets/site/styles/content.css?v=20260422-cache1",
  "/assets/site/styles/hub.css?v=20260422-cache1",
  "/assets/site/styles/hub.css?v=20260422-archive2",
  "/assets/site/styles/chat.css?v=20260422-chatfix5",
  "/assets/site/styles/chat.css?v=20260422-chatfix6",
  "/assets/site/styles/chat.css?v=20260423-chatflowfix2",
  "/assets/site/styles/chat.css?v=20260423-messengerux2",
  "/assets/site/styles/chat.css?v=20260423-messengerux8",
  "/assets/site/styles/chat.css?v=20260423-mobilescale2",
  "/assets/site/styles/chat.css?v=20260423-mobilescale3",
  "/assets/site/styles/chat.css?v=20260423-mobilescale4",
  "/assets/site/styles/chat.css?v=20260425-chatlayout1",
  "/assets/site/styles/navid.css?v=20260422-navid1",
  "/assets/site/styles/navid.css?v=20260422-navid2",
  "/assets/site/styles/polls.css?v=20260422-polls2",
  "/assets/site/styles/polls.css?v=20260422-polls3",
  "/assets/site/styles/grades.css?v=20260422-cache1",
  "/assets/site/styles/quiz.css?v=20260422-cache1",
  "/assets/site/styles/theme.css?v=20260422-cache1",
  "/assets/site/styles/theme.css?v=20260422-cache2",
  "/assets/site/styles/account.css?v=20260422-accountfix4",
  "/assets/site/styles/account.css?v=20260422-accountfix5",
  "/assets/site/styles/account.css?v=20260422-cache1",
  "/assets/site/styles/account.css?v=20260423-ownercreate1",
  "/assets/site/scripts/theme.js?v=20260422-utf8fix1",
  "/assets/site/scripts/pwa.js?v=" + APP_VERSION,
  "/assets/site/scripts/shell.js?v=20260422-utf8fix1",
  "/assets/site/scripts/shell.js?v=20260422-shellpoll1",
  "/assets/site/scripts/app-home.js?v=20260422-navidhome1",
  "/assets/site/scripts/app-home.js?v=20260422-homeall1",
  "/assets/site/scripts/navid-page.js?v=20260422-navid1",
  "/assets/site/scripts/navid-page.js?v=20260422-navid2",
  "/assets/site/scripts/auth.js?v=20260422-authfix3",
  "/assets/site/scripts/auth.js?v=20260422-authfix5",
  "/assets/site/scripts/auth.js?v=20260422-authfix6",
  "/assets/site/scripts/account.js?v=20260422-navid2",
  "/assets/site/scripts/account.js?v=20260422-accountfix3",
  "/assets/site/scripts/account.js?v=20260422-accountfix5",
  "/assets/site/scripts/account.js?v=20260422-accountfix6",
  "/assets/site/scripts/account.js?v=20260423-ownercreate1",
  "/assets/site/scripts/chat.js?v=20260422-chatfix5",
  "/assets/site/scripts/chat.js?v=20260422-chatfix6",
  "/assets/site/scripts/chat.js?v=20260423-chatflowfix2",
  "/assets/site/scripts/chat.js?v=20260423-messengerux2",
  "/assets/site/scripts/chat.js?v=20260423-messengerux8",
  "/assets/site/scripts/chat.js?v=20260423-messengerux10",
  "/assets/site/scripts/chat.js?v=20260423-messengerux11",
  "/assets/site/scripts/chat.js?v=20260425-chatlayout1",
  "/assets/site/scripts/polls.js?v=20260422-polls2",
  "/assets/site/scripts/polls.js?v=20260422-polls3",
  "/assets/site/scripts/poll-view.js?v=20260422-polls3",
  "/assets/site/scripts/poll-view.js?v=20260422-polls4",
  "/assets/site/scripts/grades.js?v=20260422-gradesfix3",
  "/assets/site/scripts/grades.js?v=20260422-gradesfix4",
  "/assets/images/logo.png?v=20260422-brand1",
  "/assets/images/favicon.png?v=" + APP_VERSION,
  "/assets/icons/icon-192.png?v=" + APP_VERSION,
  "/assets/icons/icon-512.png?v=" + APP_VERSION,
  "/assets/icons/icon-maskable-192.png?v=" + APP_VERSION,
  "/assets/icons/icon-maskable-512.png?v=" + APP_VERSION,
  "/assets/icons/apple-touch-icon.png?v=" + APP_VERSION,
  "/fonts/AbarHigh-Regular.ttf",
  "/fonts/AbarHigh-SemiBold.ttf",
  "/fonts/AbarHigh-Bold.ttf",
  "/fonts/YekanBakh-VF.woff2",
  "/fonts/YekanBakh-VF.woff"
];

const DYNAMIC_BYPASS = [
  "/app-version.json",
  "/chat/chat_api.php",
  "/grades/grades_api.php",
  "/api/navid_api.php",
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
