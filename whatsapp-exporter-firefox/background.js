/* WhatsApp Chat Exporter - background script.
 * Receives the finished export from the content script and saves it
 * through the downloads API. */
'use strict';

const api = typeof browser !== 'undefined' ? browser : chrome;

// download id -> blob URL, revoked once the download finishes
const pendingUrls = new Map();

api.runtime.onMessage.addListener((msg) => {
  if (!msg) return undefined;
  if (msg.type === 'WA_DISCARD_DOWNLOAD') return discardDownload(msg.id);
  if (msg.type !== 'WA_EXPORT_DOWNLOAD') return undefined;

  const blob =
    msg.blob instanceof Blob ? msg.blob : new Blob([msg.content || ''], { type: msg.mime || 'text/plain' });
  const url = URL.createObjectURL(blob);

  return api.downloads
    .download({
      url,
      filename: msg.filename,
      saveAs: !!msg.saveAs,
      conflictAction: 'uniquify',
    })
    .then((id) => {
      pendingUrls.set(id, url);
      return { ok: true, id };
    })
    .catch((err) => {
      URL.revokeObjectURL(url);
      return { ok: false, error: String((err && err.message) || err) };
    });
});

api.downloads.onChanged.addListener((delta) => {
  if (!pendingUrls.has(delta.id) || !delta.state) return;
  const state = delta.state.current;
  if (state === 'complete' || state === 'interrupted') {
    URL.revokeObjectURL(pendingUrls.get(delta.id));
    pendingUrls.delete(delta.id);
  }
});

/* During an export WhatsApp's own file downloads are normally intercepted in
 * the page. If one still reaches the browser, tell the WhatsApp tabs so the
 * exporter can copy the file into its folder (and then discard this one). */
api.downloads.onCreated.addListener((item) => {
  if (!/^blob:https:\/\/web\.whatsapp\.com\//.test(item.url || '')) return;
  api.tabs
    .query({ url: '*://web.whatsapp.com/*' })
    .then((tabs) => {
      for (const tab of tabs) {
        api.tabs
          .sendMessage(tab.id, { type: 'WA_PAGE_DOWNLOAD', id: item.id, url: item.url, filename: item.filename })
          .catch(() => {});
      }
    })
    .catch(() => {});
});

async function discardDownload(id) {
  try {
    await api.downloads.cancel(id);
  } catch (e) {
    // already finished
  }
  try {
    const [item] = await api.downloads.search({ id });
    if (item && item.state === 'complete' && item.exists) await api.downloads.removeFile(id);
  } catch (e) {
    // nothing to remove
  }
  try {
    await api.downloads.erase({ id });
  } catch (e) {
    // ignore
  }
  return { ok: true };
}
