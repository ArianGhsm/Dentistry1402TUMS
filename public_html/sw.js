const APP_VERSION = "20260713-150259";
const STATIC_CACHE = "dent1402-static-" + APP_VERSION;
const PAGE_CACHE = "dent1402-pages-" + APP_VERSION;
const MAX_PAGE_CACHE_ENTRIES = 40;

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
  "/api/admin_api.php",
  "/chat/chat_api.php",
  "/grades/grades_api.php",
  "/api/content_tools_api.php",
  "/api/exams_api.php",
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
    const keepCaches = [STATIC_CACHE, PAGE_CACHE];
    await Promise.all(keys.map((key) => {
      if (keepCaches.indexOf(key) === -1) {
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

self.addEventListener("push", (event) => {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (_jsonError) {
      try {
        data = { title: "اعلان جدید", body: event.data.text() };
      } catch (_textError) {
        data = {};
      }
    }
  }

  const title = data && data.title ? String(data.title) : "اعلان جدید";
  const url = data && data.url ? String(data.url) : "/account/#notifications";
  const tag = data && data.tag ? String(data.tag) : undefined;
  const options = {
    body: data && data.body ? String(data.body) : "",
    dir: "rtl",
    lang: "fa",
    tag: tag,
    renotify: !!tag,
    icon: "/assets/icons/icon-192.png?v=" + APP_VERSION,
    badge: "/assets/icons/icon-192.png?v=" + APP_VERSION,
    data: { url: url }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const targetUrl = event.notification.data && event.notification.data.url
    ? event.notification.data.url
    : "/account/#notifications";

  event.waitUntil((async () => {
    const windowClients = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    for (const client of windowClients) {
      try {
        const clientUrl = new URL(client.url);
        if (clientUrl.origin === self.location.origin && "focus" in client) {
          await client.focus();
          if ("navigate" in client) {
            try {
              await client.navigate(targetUrl);
            } catch (_navigateError) {
              // Navigation can fail on some browsers; focus is enough.
            }
          }
          return;
        }
      } catch (_clientError) {
        // Ignore malformed client URLs.
      }
    }
    if (self.clients.openWindow) {
      await self.clients.openWindow(targetUrl);
    }
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

async function trimPageCache(cache) {
  const requests = await cache.keys();
  if (requests.length <= MAX_PAGE_CACHE_ENTRIES) {
    return;
  }
  const removeCount = requests.length - MAX_PAGE_CACHE_ENTRIES;
  for (let i = 0; i < removeCount; i++) {
    await cache.delete(requests[i]);
  }
}

// Always fetch fresh while online (no-store), but keep a copy of each
// successfully-served page so previously-visited pages re-open offline
// instead of falling back to the generic offline screen. API responses stay
// network-only via DYNAMIC_BYPASS, so no live/paid data is cached.
async function networkFirstPage(request) {
  const cache = await caches.open(PAGE_CACHE);
  try {
    const response = await fetch(request, { cache: "no-store" });
    if (response && response.ok && response.type === "basic") {
      cache.put(request, response.clone())
        .then(() => trimPageCache(cache))
        .catch(() => {});
    }
    return response;
  } catch (error) {
    const cached = await cache.match(request);
    if (cached) {
      return cached;
    }
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
    event.respondWith(networkFirstPage(request));
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
