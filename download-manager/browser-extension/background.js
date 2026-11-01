// SwiftGet browser integration (Chrome / Edge / Brave, Manifest V3).
// Catches downloads of the file types chosen in SwiftGet > Options > Browser
// and hands them to the desktop app, which listens on 127.0.0.1 only.

const DEFAULT_PORT = 9614;
const bypass = new Set(); // URLs the browser should download itself (fallback)

async function settings() {
  const s = await chrome.storage.local.get({ port: DEFAULT_PORT, intercept: true });
  return s;
}

async function api(path, options = {}) {
  const { port } = await settings();
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 2000);
  try {
    const res = await fetch(`http://127.0.0.1:${port}${path}`, { ...options, signal: ctrl.signal });
    if (!res.ok) return null;
    return await res.json();
  } catch (e) {
    return null; // app not running
  } finally {
    clearTimeout(timer);
  }
}

async function appConfig() {
  return api("/config");
}

async function cookiesFor(url) {
  try {
    const list = await chrome.cookies.getAll({ url });
    return list.map((c) => `${c.name}=${c.value}`).join("; ");
  } catch (e) {
    return "";
  }
}

async function sendToApp({ url, filename, referrer, fileSize }) {
  const body = {
    url,
    filename: filename || "",
    referrer: referrer || "",
    cookies: await cookiesFor(url),
    userAgent: navigator.userAgent,
    fileSize: fileSize || -1,
  };
  const res = await api("/download", {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-SwiftGet": "1" },
    body: JSON.stringify(body),
  });
  return !!(res && res.ok);
}

function baseName(path) {
  return (path || "").split(/[\\/]/).pop();
}

function extensionOf(name) {
  const clean = (name || "").split("?")[0].split("#")[0];
  const i = clean.lastIndexOf(".");
  return i >= 0 ? clean.slice(i + 1).toLowerCase() : "";
}

// Decide once the browser knows the real file name (Content-Disposition etc.)
chrome.downloads.onDeterminingFilename.addListener((item, suggest) => {
  handleDownload(item).finally(() => {
    try { suggest(); } catch (e) { /* already cancelled */ }
  });
  return true; // we call suggest() asynchronously
});

async function handleDownload(item) {
  const url = item.finalUrl || item.url;
  if (!/^(https?|ftp):/i.test(url)) return; // blob:, data:, file: stay in the browser
  if (bypass.has(url) || bypass.has(item.url)) {
    bypass.delete(url);
    bypass.delete(item.url);
    return;
  }
  const { intercept } = await settings();
  if (!intercept) return;
  const cfg = await appConfig();
  if (!cfg || !cfg.enabled) return;

  const name = baseName(item.filename) || baseName(new URL(url).pathname);
  const ext = extensionOf(name) || extensionOf(url);
  if (!cfg.extensions.includes(ext)) return;
  const size = item.totalBytes > 0 ? item.totalBytes : item.fileSize;
  if (cfg.minSizeKb > 0 && size > 0 && size < cfg.minSizeKb * 1024) return;

  try {
    await chrome.downloads.cancel(item.id);
    await chrome.downloads.erase({ id: item.id });
  } catch (e) { /* ignore */ }

  const ok = await sendToApp({ url, filename: name, referrer: item.referrer, fileSize: size });
  if (!ok) {
    // SwiftGet refused or stopped - let the browser download it instead.
    bypass.add(url);
    chrome.downloads.download({ url });
  }
}

// Right-click menu
chrome.runtime.onInstalled.addListener(() => {
  chrome.contextMenus.removeAll(() => {
    chrome.contextMenus.create({
      id: "swiftget-link",
      title: "Download with SwiftGet",
      contexts: ["link", "image", "video", "audio"],
    });
  });
});

chrome.contextMenus.onClicked.addListener(async (info) => {
  if (info.menuItemId !== "swiftget-link") return;
  const url = info.linkUrl || info.srcUrl;
  if (!url) return;
  const ok = await sendToApp({ url, referrer: info.pageUrl });
  if (!ok) {
    chrome.action.setBadgeBackgroundColor({ color: "#c62828" });
    chrome.action.setBadgeText({ text: "off" });
    setTimeout(() => chrome.action.setBadgeText({ text: "" }), 4000);
  }
});
