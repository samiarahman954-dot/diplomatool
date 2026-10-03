/* WhatsApp Chat Exporter - background script.
 * Receives the finished export from the content script and saves it
 * through the downloads API. */
'use strict';

const api = typeof browser !== 'undefined' ? browser : chrome;

// download id -> blob URL, revoked once the download finishes
const pendingUrls = new Map();

api.runtime.onMessage.addListener((msg) => {
  if (!msg || msg.type !== 'WA_EXPORT_DOWNLOAD') return undefined;

  const blob = new Blob([msg.content], { type: msg.mime || 'text/plain' });
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
