const statusEl = document.getElementById("status");
const interceptEl = document.getElementById("intercept");
const portEl = document.getElementById("port");

async function refresh() {
  const s = await chrome.storage.local.get({ port: 9614, intercept: true });
  interceptEl.checked = s.intercept;
  portEl.value = s.port;
  try {
    const res = await fetch(`http://127.0.0.1:${s.port}/ping`);
    const info = await res.json();
    statusEl.textContent = `Connected to ${info.app} ${info.version}`;
    statusEl.className = "status on";
  } catch (e) {
    statusEl.textContent = "SwiftGet is not running";
    statusEl.className = "status off";
  }
}

interceptEl.addEventListener("change", () => chrome.storage.local.set({ intercept: interceptEl.checked }));
portEl.addEventListener("change", async () => {
  const port = parseInt(portEl.value, 10);
  if (port >= 1024 && port <= 65535) {
    await chrome.storage.local.set({ port });
    refresh();
  }
});

refresh();
