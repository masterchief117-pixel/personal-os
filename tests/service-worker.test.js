"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const vm = require("node:vm");

const serviceWorkerSource = fs.readFileSync(path.join(__dirname, "..", "sw.js"), "utf8");

function createWorker(fetchImplementation) {
  const handlers = {};
  const state = {
    cacheLookups: [],
    cacheWrites: [],
    deletedCaches: [],
    installedAssets: []
  };
  const offlinePage = () => new Response(
    "<h1>You’re offline</h1><p>Your dashboard data is not stored for offline use.</p>",
    { headers: { "Content-Type": "text/html; charset=utf-8" } }
  );
  const cache = {
    addAll: async (assets) => { state.installedAssets = assets.slice(); },
    put: async (request) => { state.cacheWrites.push(request.url || request); }
  };
  const caches = {
    open: async () => cache,
    match: async (key) => {
      state.cacheLookups.push(typeof key === "string" ? key : key.url);
      return key === "/personal-os/offline.html" ? offlinePage() : undefined;
    },
    keys: async () => ["personal-os-v3", "personal-os-v4"],
    delete: async (key) => { state.deletedCaches.push(key); return true; }
  };
  const self = {
    addEventListener: (type, handler) => { handlers[type] = handler; },
    skipWaiting: async () => {},
    clients: { claim: async () => {} },
    location: { origin: "https://personal-os.test" }
  };

  vm.runInNewContext(serviceWorkerSource, {
    self,
    caches,
    fetch: fetchImplementation,
    URL,
    Response,
    Promise
  }, { filename: "sw.js" });

  return { handlers, state };
}

function dispatchFetch(worker, request) {
  let response;
  worker.handlers.fetch({
    request,
    respondWith: (responsePromise) => { response = Promise.resolve(responsePromise); }
  });
  return response;
}

test("install caches static shell assets, not authenticated routes", async () => {
  const worker = createWorker(async () => new Response("online"));
  let installation;
  worker.handlers.install({ waitUntil: (promise) => { installation = promise; } });
  await installation;

  assert.ok(worker.state.installedAssets.includes("/personal-os/offline.html"));
  assert.ok(worker.state.installedAssets.every((asset) =>
    !asset.endsWith(".php") && asset !== "/personal-os/"
  ));
});

test("activation removes the previous cache generation", async () => {
  const worker = createWorker(async () => new Response("online"));
  let activation;
  worker.handlers.activate({ waitUntil: (promise) => { activation = promise; } });
  await activation;

  assert.deepEqual(worker.state.deletedCaches, ["personal-os-v3"]);
});

test("private responses are never written to CacheStorage", async () => {
  const worker = createWorker(async () => new Response("<h1>Private dashboard</h1>"));
  const response = await dispatchFetch(worker, {
    method: "GET",
    mode: "navigate",
    url: "https://personal-os.test/personal-os/dashboard.php"
  });

  assert.equal(response.status, 200);
  assert.deepEqual(worker.state.cacheWrites, []);
});

test("offline private navigation uses only the generic offline shell", async () => {
  const worker = createWorker(async () => { throw new Error("offline"); });
  const response = await dispatchFetch(worker, {
    method: "GET",
    mode: "navigate",
    url: "https://personal-os.test/personal-os/dashboard.php"
  });

  assert.equal(response.status, 200);
  assert.match(await response.text(), /You’re offline/);
  assert.deepEqual(worker.state.cacheLookups, ["/personal-os/offline.html"]);
});

test("offline private data requests return 503 without cached data", async () => {
  const worker = createWorker(async () => { throw new Error("offline"); });
  const response = await dispatchFetch(worker, {
    method: "GET",
    mode: "cors",
    url: "https://personal-os.test/personal-os/api/tasks.php"
  });

  assert.equal(response.status, 503);
  assert.equal(await response.text(), "Personal OS is offline.");
  assert.deepEqual(worker.state.cacheLookups, []);
});
