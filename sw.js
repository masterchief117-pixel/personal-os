const CACHE = "personal-os-v4";
const OFFLINE_PAGE = "/personal-os/offline.html";
const CORE_ASSETS = [
  "/personal-os/public/css/style.css",
  "/personal-os/public/js/app.js",
  "/personal-os/public/assets/icon.svg",
  "/personal-os/manifest.webmanifest",
  OFFLINE_PAGE
];

async function offlineResponse(request) {
  if (request.mode === "navigate") {
    const page = await caches.match(OFFLINE_PAGE);
    if (page) return page;
  }
  return new Response("Personal OS is offline.", {
    status: 503,
    headers: { "Content-Type": "text/plain; charset=utf-8" }
  });
}

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(CORE_ASSETS)));
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") return;
  const url = new URL(event.request.url);
  const isPrivate =
    url.pathname === "/personal-os/" ||
    url.pathname.endsWith(".php") ||
    url.pathname.startsWith("/personal-os/api/");
  if (isPrivate) {
    event.respondWith(fetch(event.request).catch(() => offlineResponse(event.request)));
    return;
  }
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response.ok && event.request.url.startsWith(self.location.origin)) {
          const copy = response.clone();
          caches.open(CACHE).then((cache) => cache.put(event.request, copy));
        }
        return response;
      })
      .catch(() => caches.match(event.request).then((cached) => cached || offlineResponse(event.request)))
  );
});
