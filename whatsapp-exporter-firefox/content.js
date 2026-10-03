/* WhatsApp Chat Exporter - content script (runs on web.whatsapp.com).
 *
 * Reads the currently open chat from the WhatsApp Web DOM, optionally
 * scrolls up to load the older history, then formats the messages and
 * hands the file to the background script for download.
 * Nothing is sent anywhere except to the local downloads folder. */
(() => {
  'use strict';

  if (window.__waChatExporterLoaded) return;
  window.__waChatExporterLoaded = true;

  const api = typeof browser !== 'undefined' ? browser : chrome;

  const TIME_RE = /^\d{1,2}[:.]\d{2}(\s?[AaPp]\.?\s?[Mm]\.?)?$/;
  const PRE_PLAIN_RE = /^\s*\[([^\]]+)\]\s?([\s\S]*?):\s*$/;
  const DATE_DIVIDER_RE =
    /^(\d{1,4}[/.\-]\d{1,2}[/.\-]\d{1,4}|today|yesterday|monday|tuesday|wednesday|thursday|friday|saturday|sunday|[a-z]+ \d{1,2}, \d{4}|\d{1,2} [a-z]+ \d{4})$/i;

  // media types whose picture (or thumbnail) is embedded in the HTML export
  const PICTURE_MEDIA = new Set(['image', 'sticker', 'video']);
  // images larger than this are scaled down so the HTML file stays openable
  const MAX_IMAGE_BYTES = 1.5 * 1024 * 1024;
  const MAX_IMAGE_SIDE = 1600;

  const FORMATS = {
    txt: { ext: 'txt', mime: 'text/plain;charset=utf-8', build: toTxt },
    html: { ext: 'html', mime: 'text/html;charset=utf-8', build: toHtml },
    json: { ext: 'json', mime: 'application/json;charset=utf-8', build: toJson },
    csv: { ext: 'csv', mime: 'text/csv;charset=utf-8', build: toCsv },
  };

  let running = false;
  let cancelRequested = false;

  // ---------------------------------------------------------------------------
  // Messaging with the popup
  // ---------------------------------------------------------------------------

  api.runtime.onMessage.addListener((msg) => {
    if (!msg || typeof msg.type !== 'string') return undefined;

    if (msg.type === 'WA_EXPORT_STATUS') {
      const main = getMain();
      return Promise.resolve({
        ready: !!main,
        chatName: main ? getChatName() : null,
        running,
      });
    }

    if (msg.type === 'WA_EXPORT_START') {
      if (running) return Promise.resolve({ ok: false, error: 'An export is already running.' });
      if (!getMain()) return Promise.resolve({ ok: false, error: 'Open a chat in WhatsApp Web first.' });
      runExport(msg.options || {});
      return Promise.resolve({ ok: true });
    }

    if (msg.type === 'WA_PAGE_DOWNLOAD') {
      // WhatsApp started a normal browser download that our click hook missed
      if (captureWaiter && /^blob:/.test(msg.url || '')) {
        const waiter = captureWaiter;
        captureWaiter = null;
        waiter.resolve({
          name: basename(msg.filename || ''),
          blobPromise: fetchBlob(msg.url),
          pageDownloadId: msg.id,
        });
      }
      return undefined;
    }

    if (msg.type === 'WA_EXPORT_CANCEL') {
      cancelRequested = true;
      return Promise.resolve({ ok: true });
    }

    return undefined;
  });

  // ---------------------------------------------------------------------------
  // Export flow
  // ---------------------------------------------------------------------------

  async function runExport(options) {
    running = true;
    cancelRequested = false;
    const format = FORMATS[options.format] ? options.format : 'txt';
    const maxMessages = Math.max(0, parseInt(options.maxMessages, 10) || 0);
    const overlay = createOverlay();

    try {
      const main = getMain();
      const chatName = getChatName();
      overlay.set(`Reading "${chatName}"…`);

      const withImages = format === 'html' && options.embedImages !== false;
      const withFiles = options.downloadAttachments !== false;
      const folder = `WhatsApp Chat - ${sanitizeFilename(chatName)} - ${today()}`;
      const collected = {
        store: new Map(),
        order: [],
        cache: new WeakMap(),
        images: withImages ? new Map() : null, // message id -> { data, full }
        imageTried: new Set(),
        files: withFiles ? new Map() : null, // message id -> { path, size }
        fileTried: new Set(),
        folder,
      };
      if (withFiles) installDownloadHook();
      collect(main, collected);

      if (options.loadHistory !== false) {
        collected.phase = 'history';
        await loadHistory(chatName, collected, maxMessages, overlay);
      }
      collected.phase = 'sweep';
      await sweepDown(chatName, collected, overlay);

      let messages = finalize(collected);
      if (!options.includeSystem) {
        messages = messages.filter((m) => m.kind === 'message');
      }
      if (maxMessages) {
        // keep the most recent N chat messages (plus the dividers between them)
        let count = 0;
        let cut = 0;
        for (let i = messages.length - 1; i >= 0; i--) {
          if (messages[i].kind === 'message' && ++count > maxMessages) {
            cut = i + 1;
            break;
          }
        }
        messages = messages.slice(cut);
      }

      const realCount = messages.filter((m) => m.kind === 'message').length;
      if (!realCount) {
        overlay.offerDebug(() => buildDebugReport(getMain()));
        throw new Error('No messages found in this chat. Click "Copy debug info" and send it to the developer.');
      }

      overlay.set(`Building ${format.toUpperCase()} file (${realCount} messages)…`);
      const meta = {
        chat: chatName,
        exportedAt: new Date().toISOString(),
        messageCount: realCount,
      };
      const { ext, mime, build } = FORMATS[format];
      const content = build(meta, messages);
      // with attachments everything goes into one folder: chat file + attachments/
      const filename = withFiles
        ? `${folder}/WhatsApp Chat - ${sanitizeFilename(chatName)}.${ext}`
        : `${folder}.${ext}`;

      const res = await api.runtime.sendMessage({
        type: 'WA_EXPORT_DOWNLOAD',
        filename,
        mime,
        content,
        saveAs: !withFiles && !!options.saveAs,
      });
      if (!res || !res.ok) throw new Error((res && res.error) || 'Download failed.');

      const fileNote = withFiles ? ` + ${collected.files.size} attachments in "${folder}"` : '';
      overlay.done(`✔ Exported ${realCount} messages to ${ext.toUpperCase()}${fileNote}`);
    } catch (err) {
      overlay.fail(`✖ ${(err && err.message) || err}`);
    } finally {
      uninstallDownloadHook();
      running = false;
    }
  }

  async function loadHistory(chatName, collected, maxMessages, overlay) {
    let idleRounds = 0;
    let scroller = null;

    while (!cancelRequested && idleRounds < 4) {
      const main = getMain();
      if (!main || getChatName() !== chatName) {
        throw new Error('The open chat changed during export. Please try again.');
      }
      if (!scroller || !scroller.isConnected) scroller = findScroller(main);
      if (!scroller) break;

      const before = collected.store.size;
      clickLoadOlderButtons(main);
      expandReadMore(main);
      const nodeCountBefore = getMessageNodes(main).length;
      const heightBefore = scroller.scrollHeight;
      scroller.scrollTop = 0;

      await waitFor(
        () =>
          getMessageNodes(main).length !== nodeCountBefore ||
          scroller.scrollHeight !== heightBefore,
        idleRounds ? 4000 : 2500
      );
      await sleep(350);

      collect(main, collected);
      if (collected.images || collected.files) await captureMedia(main, collected, scroller, overlay);
      const total = countMessages(collected);
      overlay.set(`Loading older messages… ${total} found`);

      idleRounds = collected.store.size === before ? idleRounds + 1 : 0;
      if (maxMessages && total >= maxMessages) break;
    }

    if (cancelRequested) overlay.set('Stopped loading – exporting what was loaded…');
  }

  /* Scroll back down through the conversation, reading every screen.
   * Needed when WhatsApp has unmounted messages while we scrolled up;
   * also re-reads everything fresh (long messages expanded, media loaded)
   * and leaves the chat scrolled to the newest message. */
  async function sweepDown(chatName, collected, overlay) {
    const main = getMain();
    if (!main || getChatName() !== chatName) return;
    const scroller = findScroller(main);
    if (!scroller) {
      collect(main, collected, true);
      return;
    }
    overlay.set(`Reading messages… ${countMessages(collected)} found`);
    let lastTop = -1;
    for (let guard = 0; guard < 5000; guard++) {
      expandReadMore(main);
      await sleep(120);
      collect(main, collected, true);
      if (collected.images || collected.files) {
        await captureMedia(main, collected, scroller, overlay);
      }
      const atBottom = scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 2;
      if (atBottom || scroller.scrollTop === lastTop || cancelRequested) break;
      lastTop = scroller.scrollTop;
      scroller.scrollTop += Math.max(200, Math.floor(scroller.clientHeight * 0.8));
    }
  }

  // ---------------------------------------------------------------------------
  // Images (HTML export)
  // ---------------------------------------------------------------------------

  /* Handle the media messages currently in the DOM:
   *  - attachments mode: save each message's file (photo, sticker, video,
   *    voice note, document/ZIP…) into <folder>/attachments/;
   *  - HTML images: keep the picture as a data: URL to embed.
   * Pictures that are only a blurry preview get their download button
   * clicked (once) when on screen, then we wait briefly for the real one. */
  async function captureMedia(main, collected, scroller, overlay) {
    const targets = [];
    for (const node of getMessageNodes(main)) {
      const msg = collected.cache.get(node);
      if (!msg || msg.kind !== 'message' || !msg.media) continue;
      const bubble = node.querySelector(MSG_CLASS_SEL) || node;
      targets.push({ msg, node, bubble, isPic: PICTURE_MEDIA.has(msg.media) });
    }
    if (!targets.length) return;

    // ask WhatsApp to load full pictures that are visible but not loaded yet
    const waiting = [];
    for (const t of targets) {
      if (!t.isPic || t.msg.media === 'video') continue;
      if (collected.imageTried.has(t.msg.id) || !isOnScreen(t.node, scroller)) continue;
      if (collected.files ? collected.files.has(t.msg.id) : !collected.images) continue;
      const img = bestImage(t.bubble);
      if (img && isFullImage(img)) continue;
      collected.imageTried.add(t.msg.id);
      clickMediaDownload(t.bubble);
      waiting.push(t);
    }
    if (waiting.length) {
      await waitFor(
        () => waiting.every((t) => {
          const img = bestImage(t.bubble);
          return img && isFullImage(img);
        }),
        3000
      );
    }

    if (collected.files) {
      for (const t of targets) {
        if (cancelRequested) return;
        if (collected.files.has(t.msg.id)) continue;
        await saveMessageFile(t, collected, scroller, overlay);
      }
    }

    if (collected.images) {
      for (const t of targets) {
        if (cancelRequested) return;
        if (!t.isPic) continue;
        // a saved photo/sticker file is shown from disk; video keeps its thumbnail
        if (collected.files && collected.files.has(t.msg.id) && t.msg.media !== 'video') continue;
        const img = bestImage(t.bubble);
        if (!img) continue;
        const full = isFullImage(img);
        const prev = collected.images.get(t.msg.id);
        if (prev && (prev.full || !full)) continue;
        const data = await imageToDataUrl(img);
        if (data) collected.images.set(t.msg.id, { data, full });
      }
    }

    const parts = [`${countMessages(collected)} messages`];
    if (collected.files) parts.push(`${collected.files.size} attachments`);
    if (collected.images) parts.push(`${collected.images.size} images`);
    overlay.set(`Reading chat… ${parts.join(', ')} saved`);
  }

  async function saveMessageFile(t, collected, scroller, overlay) {
    const { msg, bubble } = t;
    // while older messages are still loading, the topmost one may lack its
    // date/sender context; it is saved later, during the final pass
    const ctx = messageContext(collected, msg);
    if (collected.phase === 'history' && !ctx.date) return;
    let blob = null;
    let name = '';

    if (msg.media === 'image' || msg.media === 'sticker') {
      const img = bestImage(bubble);
      if (!img || !isFullImage(img)) return; // retried while it stays in the DOM
      blob = await fetchBlob(img.currentSrc || img.src);
      name = `${msg.media === 'sticker' ? 'STK' : 'IMG'}.${extFor(blob)}`;
    } else if (msg.media === 'video' || msg.media === 'audio') {
      const MEDIA_SRC = 'video[src^="blob:"], audio[src^="blob:"], source[src^="blob:"]';
      if (!bubble.querySelector(MEDIA_SRC) && !collected.fileTried.has(msg.id)) {
        // not loaded yet: press its download button and give WhatsApp a moment
        collected.fileTried.add(msg.id);
        if (bubble.querySelector('[data-icon*="download"]')) {
          overlay.set(`Downloading ${msg.media}…`);
          clickMediaDownload(bubble);
          await waitFor(() => !!bubble.querySelector(MEDIA_SRC), 8000);
        }
      }
      const el = bubble.querySelector(MEDIA_SRC);
      if (!el) return;
      blob = await fetchBlob(el.getAttribute('src'));
      name = `${msg.media === 'video' ? 'VID' : 'AUD'}.${extFor(blob)}`;
    } else {
      // document, ZIP, PDF, … : ask WhatsApp for the file, once
      if (collected.fileTried.has(msg.id)) return;
      collected.fileTried.add(msg.id);
      overlay.set(`Downloading ${msg.fileName || 'file'}…`);
      const got = await downloadViaWhatsApp(bubble, true);
      if (!got) return;
      name = got.name || msg.fileName || `file.${extFor(got.blob)}`;
      if (got.blob) {
        blob = got.blob;
        if (got.pageDownloadId !== undefined) discardPageDownload(got.pageDownloadId);
      } else if (got.pageDownloadId !== undefined) {
        // couldn't read it, but WhatsApp's own download is in the Downloads folder
        collected.files.set(msg.id, { path: `../${name}`, size: 0 });
        return;
      }
    }

    if (!blob || !blob.size) return;
    // "<date> <time> - <sender> - <original name>", e.g. "3-1-2026 10.25 - Rahim - project.zip"
    const stamp = [ctx.date.replace(/[/.:]/g, '-'), ctx.time.replace(/:/g, '.')].filter(Boolean).join(' ');
    const file = uniqueName(collected, safeFileName([stamp, ctx.sender, name].filter(Boolean).join(' - ')));
    const path = `attachments/${file}`;
    const res = await api.runtime
      .sendMessage({
        type: 'WA_EXPORT_DOWNLOAD',
        filename: `${collected.folder}/${path}`,
        mime: blob.type || 'application/octet-stream',
        blob,
        saveAs: false,
      })
      .catch((err) => ({ ok: false, error: String(err) }));
    if (res && res.ok) collected.files.set(msg.id, { path, size: blob.size });
  }

  /* Media messages often carry no date/sender of their own; borrow them
   * from the closest earlier message or date divider. */
  function messageContext(collected, msg) {
    const ctx = { date: msg.date, time: msg.time, sender: msg.sender || (msg.fromMe ? 'You' : '') };
    const idx = collected.order.indexOf(msg.id);
    for (let i = idx - 1; i >= 0 && (!ctx.date || !ctx.sender); i--) {
      const prev = collected.store.get(collected.order[i]);
      if (!prev) continue;
      if (prev.kind === 'date') {
        if (!ctx.date) ctx.date = prev.text;
        if (!ctx.sender) break; // sender runs don't cross days
        continue;
      }
      if (prev.kind !== 'message') continue;
      if (!ctx.date && prev.date) ctx.date = prev.date;
      if (!ctx.sender && !msg.fromMe && !prev.fromMe && prev.sender) ctx.sender = prev.sender;
    }
    return ctx;
  }

  function uniqueName(collected, file) {
    if (!collected.usedNames) collected.usedNames = new Set();
    const dot = file.lastIndexOf('.');
    const base = dot > 0 ? file.slice(0, dot) : file;
    const ext = dot > 0 ? file.slice(dot) : '';
    let name = file;
    for (let n = 2; collected.usedNames.has(name.toLowerCase()); n++) name = `${base} (${n})${ext}`;
    collected.usedNames.add(name.toLowerCase());
    return name;
  }

  // ---------------------------------------------------------------------------
  // Capturing WhatsApp's own file downloads
  // ---------------------------------------------------------------------------

  /* WhatsApp saves a file by clicking an <a download href="blob:…">. While an
   * export waits for a file we intercept that click (in the page itself via
   * Firefox's wrappedJSObject/exportFunction), read the blob and save it in
   * our folder instead. If the click slips through, the background script
   * reports the browser download (WA_PAGE_DOWNLOAD) and we use that. */
  let captureWaiter = null;
  let hook = null;

  function interceptAnchor(anchor) {
    if (!captureWaiter) return false;
    const href = String(anchor.href || '');
    if (!/^blob:/.test(href)) return false;
    const waiter = captureWaiter;
    captureWaiter = null;
    waiter.resolve({
      name: String(anchor.download || anchor.getAttribute('download') || ''),
      blobPromise: fetchBlob(href),
    });
    return true;
  }

  function onDocumentClick(event) {
    const a = event.target && event.target.closest && event.target.closest('a[download]');
    if (a && interceptAnchor(a)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }

  function installDownloadHook() {
    if (hook) return;
    const pageWindow = window.wrappedJSObject || window;
    const proto = pageWindow.HTMLAnchorElement.prototype;
    const original = proto.click;
    const replacement = function () {
      try {
        if (interceptAnchor(this)) return undefined;
      } catch (e) {
        // fall through to the normal click
      }
      return original.call(this);
    };
    try {
      if (window.wrappedJSObject && typeof exportFunction === 'function') {
        exportFunction(replacement, proto, { defineAs: 'click' });
      } else {
        proto.click = replacement;
      }
      hook = { proto, original };
    } catch (e) {
      hook = { proto: null, original: null };
    }
    document.addEventListener('click', onDocumentClick, true);
  }

  function uninstallDownloadHook() {
    if (!hook) return;
    try {
      if (hook.proto) hook.proto.click = hook.original;
    } catch (e) {
      // page reloaded or prototype gone
    }
    document.removeEventListener('click', onDocumentClick, true);
    captureWaiter = null;
    hook = null;
  }

  /* Click the message's download / open control and wait for WhatsApp to
   * hand over the file. Big files get more time while a progress bar shows. */
  async function downloadViaWhatsApp(bubble, isDocument) {
    let resolveFn;
    const got = new Promise((resolve) => {
      resolveFn = resolve;
    });
    const waiter = { resolve: resolveFn };
    captureWaiter = waiter;
    const dialogs = document.querySelectorAll('[role="dialog"]').length;

    if (!clickFileControl(bubble, isDocument)) {
      captureWaiter = null;
      return null;
    }

    let finished = false;
    got.then(() => {
      finished = true;
    });
    const start = Date.now();
    const timeout = (async () => {
      while (!finished && !cancelRequested) {
        const busy = bubble.isConnected && bubble.querySelector('[role="progressbar"], progress');
        if (Date.now() - start > (busy ? 180000 : 20000)) break;
        await sleep(250);
      }
      return null;
    })();
    const result = await Promise.race([got, timeout]);
    if (captureWaiter === waiter) captureWaiter = null;
    if (document.querySelectorAll('[role="dialog"]').length > dialogs) pressEscape();
    if (!result) return null;
    const blob = await result.blobPromise;
    return { name: result.name, blob, pageDownloadId: result.pageDownloadId };
  }

  function clickFileControl(bubble, isDocument) {
    const icon = bubble.querySelector('[data-icon*="download"]');
    let target = icon && (icon.closest('button, [role="button"]') || icon);
    if (!target && isDocument) {
      const named = findDocNameElement(bubble);
      target = named && (named.closest('button, [role="button"]') || named);
    }
    if (!target && isDocument) target = bubble.querySelector('[role="button"]');
    if (!target) return false;
    target.click();
    return true;
  }

  function pressEscape() {
    const target = document.activeElement || document.body;
    for (const type of ['keydown', 'keyup']) {
      target.dispatchEvent(new KeyboardEvent(type, { key: 'Escape', code: 'Escape', keyCode: 27, bubbles: true }));
    }
  }

  function discardPageDownload(id) {
    api.runtime.sendMessage({ type: 'WA_DISCARD_DOWNLOAD', id }).catch(() => {});
  }

  async function fetchBlob(url) {
    // Firefox: content.fetch runs with the page's origin, which owns the blob: URL
    const fetchers = [];
    if (typeof content !== 'undefined' && content && typeof content.fetch === 'function') {
      fetchers.push((u) => content.fetch(u));
    }
    fetchers.push((u) => fetch(u));
    for (const doFetch of fetchers) {
      try {
        const blob = await (await doFetch(url)).blob();
        if (blob && blob.size) return blob;
      } catch (e) {
        // try the next way
      }
    }
    return null;
  }

  const EXT_BY_MIME = {
    'image/jpeg': 'jpg',
    'image/png': 'png',
    'image/webp': 'webp',
    'image/gif': 'gif',
    'video/mp4': 'mp4',
    'video/webm': 'webm',
    'audio/ogg': 'ogg',
    'audio/mpeg': 'mp3',
    'audio/mp4': 'm4a',
    'audio/aac': 'aac',
    'application/pdf': 'pdf',
    'application/zip': 'zip',
  };

  function extFor(blob) {
    const type = ((blob && blob.type) || '').split(';')[0].trim().toLowerCase();
    return EXT_BY_MIME[type] || (type.split('/')[1] || 'bin').replace(/[^a-z0-9]/g, '').slice(0, 6) || 'bin';
  }

  function basename(path) {
    return String(path).split(/[\\/]/).pop();
  }

  function safeFileName(name) {
    const clean = name
      .replace(/[\\/:*?"<>|\u0000-\u001f]/g, '_')
      .replace(/\s+/g, ' ')
      .trim()
      .replace(/^[.\s]+|[.\s]+$/g, '');
    const dot = clean.lastIndexOf('.');
    const ext = dot > 0 && clean.length - dot <= 10 ? clean.slice(dot) : '';
    const base = ext ? clean.slice(0, dot) : clean;
    return (base.slice(0, 140) || 'file') + ext;
  }

  function bestImage(bubble) {
    let best = null;
    let bestScore = -1;
    for (const img of bubble.querySelectorAll('img')) {
      const src = img.currentSrc || img.src || '';
      if (!/^(blob:|data:image)/.test(src)) continue;
      if (img.classList.contains('emoji') || isInsideQuote(img, bubble) || img.closest('a[href]')) continue;
      const area = (img.naturalWidth || img.width || 0) * (img.naturalHeight || img.height || 0);
      const score = (src.startsWith('blob:') ? 1e9 : 0) + area;
      if (score > bestScore) {
        best = img;
        bestScore = score;
      }
    }
    return best;
  }

  function isFullImage(img) {
    return (img.currentSrc || img.src || '').startsWith('blob:') && img.complete && img.naturalWidth > 0;
  }

  function isOnScreen(node, scroller) {
    const r = node.getBoundingClientRect();
    const s = scroller.getBoundingClientRect();
    return r.bottom > s.top && r.top < s.bottom;
  }

  function clickMediaDownload(bubble) {
    if (bubble.querySelector('[data-icon*="document"], [data-testid*="document"]')) return;
    const icon = bubble.querySelector('[data-icon*="download"]');
    if (!icon) return;
    const target = icon.closest('button, [role="button"]') || icon;
    target.click();
  }

  async function imageToDataUrl(img) {
    const src = img.currentSrc || img.src;
    if (src.startsWith('data:image') && src.length < MAX_IMAGE_BYTES) return src;

    const blob = await fetchBlob(src);
    if (blob && blob.size <= MAX_IMAGE_BYTES && /^image\//.test(blob.type)) {
      return blobToDataUrl(blob).catch(() => canvasDataUrl(img));
    }
    return canvasDataUrl(img); // too big, unknown type or unreadable: re-encode
  }

  function canvasDataUrl(img) {
    try {
      if (!img.complete || !img.naturalWidth) return null;
      const scale = Math.min(1, MAX_IMAGE_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(img.naturalWidth * scale);
      canvas.height = Math.round(img.naturalHeight * scale);
      canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
      return canvas.toDataURL('image/jpeg', 0.88);
    } catch (e) {
      return null;
    }
  }

  function blobToDataUrl(blob) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => reject(reader.error);
      reader.readAsDataURL(blob);
    });
  }

  // ---------------------------------------------------------------------------
  // DOM helpers
  // ---------------------------------------------------------------------------

  function getMain() {
    return document.querySelector('#main');
  }

  function getChatName() {
    const header = document.querySelector('#main header');
    if (!header) return 'WhatsApp Chat';
    const el =
      header.querySelector('span[dir="auto"][title]') ||
      header.querySelector('span[dir="auto"]') ||
      header.querySelector('[title]');
    const name = el ? (el.getAttribute('title') || el.textContent || '').trim() : '';
    return name || 'WhatsApp Chat';
  }

  const MSG_CLASS_SEL = '.message-in, .message-out';
  const ANCHOR_SEL = '[data-pre-plain-text], .message-in, .message-out';

  /* WhatsApp Web changes its markup often, so try several ways of finding
   * the message rows and use the first one that actually contains messages. */
  function getMessageNodes(main) {
    const hasAnchors = !!main.querySelector(ANCHOR_SEL);
    const strategies = [
      () => Array.from(main.querySelectorAll('[role="row"]')),
      () =>
        Array.from(main.querySelectorAll('[data-id]')).filter(
          (el) => !el.parentElement || !el.parentElement.closest('[data-id]')
        ),
      () => nodesFromAnchors(main),
    ];
    for (const strategy of strategies) {
      const nodes = strategy();
      if (!nodes.length) continue;
      if (!hasAnchors || nodes.some((n) => n.matches(MSG_CLASS_SEL) || n.querySelector(ANCHOR_SEL))) {
        return nodes;
      }
    }
    return [];
  }

  /* Class-independent fallback: the message list is the closest common
   * ancestor of all elements carrying data-pre-plain-text; its children
   * (split further while one still holds several messages) are the rows. */
  function nodesFromAnchors(main) {
    const anchors = Array.from(main.querySelectorAll('[data-pre-plain-text]'));
    if (!anchors.length) return [];
    let list = anchors[0].parentElement;
    if (anchors.length > 1) {
      while (list && list !== main && !anchors.every((a) => list.contains(a))) list = list.parentElement;
    } else {
      while (list && list !== main && list.parentElement && list.parentElement.children.length < 3) {
        list = list.parentElement;
      }
      list = list && list.parentElement;
    }
    if (!list) return [];
    const count = (el) => el.querySelectorAll('[data-pre-plain-text]').length;
    const expand = (el) =>
      count(el) > 1 && el.children.length > 1 ? Array.from(el.children).flatMap(expand) : [el];
    return Array.from(list.children).flatMap(expand);
  }

  function findScroller(main) {
    const first = getMessageNodes(main)[0];
    for (let el = first && first.parentElement; el && el !== document.body; el = el.parentElement) {
      const oy = getComputedStyle(el).overflowY;
      if ((oy === 'auto' || oy === 'scroll') && el.scrollHeight > el.clientHeight) return el;
    }
    return (
      main.querySelector('[data-testid="conversation-panel-messages"]') ||
      main.querySelector('.copyable-area [tabindex="0"]') ||
      null
    );
  }

  function isReadMore(el) {
    return /^(read more|see more|more)$/i.test((el.textContent || '').trim());
  }

  function expandReadMore(main) {
    for (const el of main.querySelectorAll('.message-in [role="button"], .message-out [role="button"]')) {
      if (isReadMore(el)) el.click();
    }
  }

  function clickLoadOlderButtons(main) {
    // "Click here to get older messages from your phone" and similar prompts
    const candidates = main.querySelectorAll('button, [role="button"]');
    for (const el of candidates) {
      const text = (el.textContent || '').trim();
      if (text.length < 120 && /older messages|earlier messages|load more/i.test(text)) {
        el.click();
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Parsing
  // ---------------------------------------------------------------------------

  /* Read the messages currently in the DOM. While scrolling up, nodes seen
   * before are taken from the cache so each round stays cheap; `fresh`
   * forces a re-parse of every visible node. */
  function collect(main, collected, fresh) {
    const current = [];
    for (const node of getMessageNodes(main)) {
      let parsed = fresh ? undefined : collected.cache.get(node);
      if (parsed === undefined) {
        parsed = parseNode(node);
        collected.cache.set(node, parsed);
      }
      if (parsed) current.push(parsed);
    }
    collected.order = mergeOrdered(collected.store, collected.order, current);
  }

  /* Merge freshly read messages into the ordered store. WhatsApp may unmount
   * messages that are far off-screen, so we can't rely on the final DOM;
   * new ids are inserted right before the first already-known id that
   * follows them in the current DOM order. */
  function mergeOrdered(store, order, current) {
    const insertions = new Map();
    let pending = [];
    for (const m of current) {
      if (store.has(m.id)) {
        store.set(m.id, m);
        if (pending.length) {
          insertions.set(m.id, (insertions.get(m.id) || []).concat(pending));
          pending = [];
        }
      } else {
        store.set(m.id, m);
        pending.push(m.id);
      }
    }
    const next = [];
    for (const id of order) {
      const ins = insertions.get(id);
      if (ins) next.push(...ins);
      next.push(id);
    }
    next.push(...pending);
    return next;
  }

  function countMessages(collected) {
    let n = 0;
    for (const m of collected.store.values()) if (m.kind === 'message') n++;
    return n;
  }

  function parseNode(node) {
    const idEl = node.matches('[data-id]') ? node : node.querySelector('[data-id]');
    const id = idEl ? idEl.getAttribute('data-id') : null;
    const classBubble = node.matches(MSG_CLASS_SEL) ? node : node.querySelector(MSG_CLASS_SEL);
    const pre = node.matches('[data-pre-plain-text]') ? node : node.querySelector('[data-pre-plain-text]');
    const looksLikeMessage =
      classBubble ||
      pre ||
      node.querySelector('[data-icon^="tail-"], span.selectable-text') ||
      detectMedia(node);

    if (!looksLikeMessage) {
      const text = normalize(extractText(node));
      if (!text) return null;
      const kind = DATE_DIVIDER_RE.test(text) ? 'date' : 'system';
      return { id: id || `${kind}:${text}`, kind, text };
    }

    const bubble = classBubble || node;
    const fromMe = detectFromMe(node, classBubble, id, pre);

    let date = '';
    let time = '';
    let sender = '';
    if (pre) {
      const parsed = parsePrePlain(pre.getAttribute('data-pre-plain-text'));
      date = parsed.date;
      time = parsed.time;
      sender = parsed.sender;
    }
    if (!time) time = findTime(bubble);

    const quoteEl = bubble.querySelector('.quoted-mention');
    const quote = quoteEl ? normalize(extractText(quoteEl)) : '';

    const spans = Array.from(bubble.querySelectorAll('span.selectable-text')).filter(
      (s) => !isInsideQuote(s, bubble)
    );
    const topSpans = spans.filter((s) => !spans.some((o) => o !== s && o.contains(s)));
    let text = normalize(topSpans.map(extractText).join('\n'));
    if (!text && pre && !isInsideQuote(pre, bubble)) {
      // markup without .selectable-text: take the text container itself
      text = normalize(extractText(pre)).replace(/\s*\d{1,2}[:.]\d{2}(\s?[AaPp]\.?\s?[Mm]\.?)?$/, '');
    }

    const deleted = !!bubble.querySelector('[data-icon="recalled"], [data-testid="recalled"]');
    if (deleted) {
      text = fromMe ? 'You deleted this message' : 'This message was deleted';
    }
    const media = deleted ? null : detectMedia(bubble);
    let fileName = '';
    if (media === 'document') {
      const el = findDocNameElement(bubble);
      fileName = el ? (el.getAttribute('title') || el.textContent || '').trim() : '';
      if (text === fileName) text = '';
    }

    return {
      id: id || `msg:${date}|${time}|${sender}|${text}|${fromMe}`,
      kind: 'message',
      fromMe,
      date,
      time,
      sender,
      text,
      quote,
      media,
      fileName,
      deleted,
    };
  }

  function findDocNameElement(bubble) {
    const looksLikeFile = (t) =>
      t.length < 200 && /\.[A-Za-z0-9]{1,8}$/.test(t) && !/^\d+([.,]\d+)?\s?[kKmMgG]?[bB]$/.test(t);
    for (const el of bubble.querySelectorAll('[title]')) {
      if (looksLikeFile((el.getAttribute('title') || '').trim())) return el;
    }
    for (const el of bubble.querySelectorAll('span, div')) {
      if (el.children.length || isInsideQuote(el, bubble)) continue;
      if (looksLikeFile((el.textContent || '').trim())) return el;
    }
    return null;
  }

  function detectFromMe(node, classBubble, id, pre) {
    if (classBubble) return classBubble.classList.contains('message-out');
    if (/^true_/.test(id || '')) return true;
    if (/^false_/.test(id || '')) return false;
    if (node.querySelector('[data-icon="tail-out"], [data-icon^="msg-check"], [data-icon^="msg-dblcheck"], [data-icon="msg-time"]')) {
      return true;
    }
    if (node.querySelector('[data-icon="tail-in"]')) return false;
    // last resort: outgoing bubbles sit on the right side of the row
    const content = pre || node.querySelector('span.selectable-text, img');
    if (!content) return false;
    const n = node.getBoundingClientRect();
    const c = content.getBoundingClientRect();
    if (!n.width || !c.width) return false;
    const onRight = c.left - n.left > n.right - c.right;
    return document.dir === 'rtl' ? !onRight : onRight;
  }

  function parsePrePlain(value) {
    // e.g. "[10:24, 3/10/2026] Rahim: " (order of time/date depends on locale)
    const out = { date: '', time: '', sender: '' };
    const m = PRE_PLAIN_RE.exec(value || '');
    if (!m) return out;
    out.sender = m[2].trim();
    for (const part of m[1].split(/,\s*/)) {
      const p = part.trim();
      if (!p) continue;
      if (!out.time && TIME_RE.test(p)) out.time = p;
      else if (!out.date) out.date = p;
    }
    return out;
  }

  function findTime(bubble) {
    const spans = bubble.querySelectorAll('span');
    for (let i = spans.length - 1; i >= 0; i--) {
      const s = spans[i];
      if (s.children.length) continue;
      const t = (s.textContent || '').trim();
      if (TIME_RE.test(t) && !isInsideQuote(s, bubble)) return t;
    }
    return '';
  }

  function detectMedia(bubble) {
    const has = (sel) => !!bubble.querySelector(sel);
    // documents first: their download button may use an "audio-download" icon
    if (has('[data-icon*="document"], [data-icon*="doc-"], [data-testid*="document"]')) return 'document';
    if (has('audio, [data-icon="audio-play"], [data-icon*="ptt"], [data-icon*="audio"]:not([data-icon*="download"])')) {
      return 'audio';
    }
    if (has('video, [data-icon="media-play"], [data-icon*="video"], [data-icon*="gif"]')) return 'video';
    if (has('[data-testid*="sticker"], [data-icon*="sticker"]')) return 'sticker';
    if (has('[data-icon*="location"], [data-testid*="location"]')) return 'location';
    if (has('[data-icon*="vcard"], [data-testid*="vcard"]')) return 'contact';
    const imgs = bubble.querySelectorAll('img[src^="blob:"], img[src^="data:image"]');
    for (const img of imgs) {
      if (img.classList.contains('emoji') || isInsideQuote(img, bubble) || img.closest('a[href]')) continue;
      // rendered size: a not-yet-downloaded photo is a tiny preview shown large
      const w = img.getBoundingClientRect().width || img.width || img.naturalWidth || 0;
      if (w && w < 40) continue; // emoji / tiny icons
      return 'image';
    }
    return null;
  }

  function isInsideQuote(el, bubble) {
    for (let cur = el; cur && cur !== bubble; cur = cur.parentElement) {
      if (cur.classList && cur.classList.contains('quoted-mention')) return true;
      const label = cur.getAttribute && cur.getAttribute('aria-label');
      if (label && /quoted/i.test(label)) return true;
      const testId = cur.getAttribute && cur.getAttribute('data-testid');
      if (testId && /quoted/i.test(testId)) return true;
    }
    return false;
  }

  function extractText(node) {
    let out = '';
    for (const child of node.childNodes) {
      if (child.nodeType === Node.TEXT_NODE) {
        out += child.nodeValue;
      } else if (child.nodeType === Node.ELEMENT_NODE) {
        const tag = child.tagName;
        if (tag === 'IMG') out += child.getAttribute('alt') || '';
        else if (child.getAttribute('role') === 'button' && isReadMore(child)) continue;
        else if (tag === 'BR') out += '\n';
        else if (tag !== 'SCRIPT' && tag !== 'STYLE' && tag !== 'svg' && tag !== 'SVG') {
          const block = /^(DIV|P|LI)$/.test(tag);
          const inner = extractText(child);
          out += block && out && !out.endsWith('\n') && inner ? '\n' + inner : inner;
        }
      }
    }
    return out;
  }

  function normalize(text) {
    return (text || '')
      .replace(/‎|‏/g, '')
      .replace(/[ \t]+\n/g, '\n')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
  }

  /* Produce the final ordered list and fill in missing dates (media
   * messages have no data-pre-plain-text) from the closest date seen. */
  function finalize(collected) {
    const list = collected.order.map((id) => ({ ...collected.store.get(id) }));
    let lastDate = '';
    let lastIncomingSender = '';
    for (const m of list) {
      if (m.kind === 'date') {
        lastDate = m.text;
        continue;
      }
      if (m.kind !== 'message') {
        m.date = m.date || lastDate;
        continue;
      }
      if (m.date) lastDate = m.date;
      else m.date = lastDate;
      if (!m.sender) m.sender = m.fromMe ? 'You' : lastIncomingSender;
      if (!m.fromMe && m.sender) lastIncomingSender = m.sender;
      const pic = collected.images && collected.images.get(m.id);
      if (pic) m.imageData = pic.data;
      const file = collected.files && collected.files.get(m.id);
      if (file) {
        m.attachment = file.path;
        m.attachmentSize = file.size;
      }
    }
    for (const m of list) delete m.id;
    return list;
  }

  // ---------------------------------------------------------------------------
  // Output formats
  // ---------------------------------------------------------------------------

  function bodyOf(m) {
    let body = m.text || '';
    if (m.attachment) {
      body = body ? `<attached: ${m.attachment}> ${body}` : `<attached: ${m.attachment}>`;
    } else if (m.media) {
      const what = m.fileName ? `${m.media} "${m.fileName}"` : m.media;
      body = body ? `<Media omitted: ${what}> ${body}` : `<Media omitted: ${what}>`;
    }
    if (!body) body = '<Media omitted>';
    if (m.quote) body = `[Reply to: "${shorten(m.quote, 80)}"] ${body}`;
    return body;
  }

  function toTxt(meta, messages) {
    const lines = [
      `WhatsApp chat: ${meta.chat}`,
      `Exported: ${new Date(meta.exportedAt).toLocaleString()} (${meta.messageCount} messages)`,
      '',
    ];
    for (const m of messages) {
      if (m.kind === 'date') continue;
      const stamp = [m.date, m.time].filter(Boolean).join(', ');
      if (m.kind === 'system') {
        lines.push(stamp ? `${stamp} - ${m.text}` : m.text);
      } else {
        lines.push(`${stamp ? stamp + ' - ' : ''}${m.sender || 'Unknown'}: ${bodyOf(m)}`);
      }
    }
    return lines.join('\n') + '\n';
  }

  function toJson(meta, messages) {
    return JSON.stringify({ ...meta, messages }, null, 2);
  }

  function toCsv(meta, messages) {
    const cell = (v) => {
      const s = v === null || v === undefined ? '' : String(v);
      // guard against spreadsheet formula injection
      const safe = /^[=+\-@\t\r]/.test(s) ? `'${s}` : s;
      return `"${safe.replace(/"/g, '""')}"`;
    };
    const rows = [['date', 'time', 'sender', 'from_me', 'type', 'media', 'text', 'reply_to', 'file_name', 'attachment']];
    for (const m of messages) {
      if (m.kind === 'date') continue;
      rows.push([
        m.date || '',
        m.time || '',
        m.kind === 'message' ? m.sender : '',
        m.kind === 'message' ? (m.fromMe ? 'yes' : 'no') : '',
        m.kind,
        m.media || '',
        m.kind === 'message' && !m.text && m.media && !m.attachment ? '<Media omitted>' : m.text,
        m.quote || '',
        m.fileName || '',
        m.attachment || '',
      ]);
    }
    // BOM so Excel opens UTF-8 (Bangla, emoji…) correctly
    return '\ufeff' + rows.map((r) => r.map(cell).join(',')).join('\r\n') + '\r\n';
  }

  function toHtml(meta, messages) {
    const esc = (s) =>
      String(s || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const parts = [];
    let prevSender = null;
    for (const m of messages) {
      if (m.kind === 'date') {
        parts.push(`<div class="divider"><span>${esc(m.text)}</span></div>`);
        prevSender = null;
        continue;
      }
      if (m.kind === 'system') {
        parts.push(`<div class="system">${esc(m.text)}</div>`);
        prevSender = null;
        continue;
      }
      const showName = !m.fromMe && m.sender && m.sender !== prevSender;
      prevSender = m.fromMe ? null : m.sender;
      let media = '';
      const href = m.attachment ? esc(m.attachment.split('/').map(encodeURIComponent).join('/')) : '';
      const label = m.attachment ? m.attachment.split('/').pop() : '';
      if (m.attachment && (m.media === 'image' || m.media === 'sticker')) {
        const cls = m.media === 'sticker' ? 'photo sticker' : 'photo';
        media = `<img class="${cls}" src="${href}" alt="${esc(label)}" loading="lazy">`;
      } else if (m.attachment && m.media === 'video') {
        const poster = m.imageData ? ` poster="${m.imageData}"` : '';
        media = `<video class="clip" controls preload="metadata" src="${href}"${poster}></video>`;
      } else if (m.attachment && m.media === 'audio') {
        media = `<audio controls preload="metadata" src="${href}"></audio>`;
      } else if (m.attachment) {
        const size = m.attachmentSize ? ` · ${formatSize(m.attachmentSize)}` : '';
        media = `<a class="file" href="${href}"><span class="file-icon">📄</span><span class="file-name">${esc(m.fileName || label)}</span><span class="file-size">${esc(fileExt(label))}${size}</span></a>`;
      } else if (m.imageData) {
        const cls = m.media === 'sticker' ? 'photo sticker' : 'photo';
        const pic = `<img class="${cls}" src="${m.imageData}" alt="${esc(m.media)}" loading="lazy">`;
        media = m.media === 'video'
          ? `<div class="video">${pic}<span class="play">▶ video (thumbnail only)</span></div>`
          : pic;
      } else if (m.media) {
        const what = m.fileName ? `${m.media} "${m.fileName}"` : m.media;
        media = `<div class="media">📎 ${esc(what)} (not downloaded)</div>`;
      }
      const quote = m.quote ? `<div class="quote">${esc(m.quote)}</div>` : '';
      const text = m.text ? `<div class="text${m.deleted ? ' deleted' : ''}">${esc(m.text)}</div>` : '';
      parts.push(
        `<div class="msg ${m.fromMe ? 'out' : 'in'}"><div class="bubble">` +
          (showName ? `<div class="sender">${esc(m.sender)}</div>` : '') +
          quote +
          media +
          text +
          `<div class="meta">${esc([m.date, m.time].filter(Boolean).join(' · '))}</div>` +
          `</div></div>`
      );
    }

    return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(meta.chat)} – WhatsApp chat</title>
<style>
  :root { --bg:#efeae2; --in:#ffffff; --out:#d9fdd3; --text:#111b21; --muted:#667781; --pill:#ffffff; --accent:#008069; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#0b141a; --in:#202c33; --out:#005c4b; --text:#e9edef; --muted:#8696a0; --pill:#182229; --accent:#00a884; }
  }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font:14.2px/1.45 system-ui, -apple-system, "Segoe UI", "Noto Sans Bengali", Roboto, sans-serif; }
  header { position:sticky; top:0; background:var(--accent); color:#fff; padding:14px 20px; z-index:1; }
  header h1 { margin:0; font-size:18px; }
  header p { margin:2px 0 0; font-size:12px; opacity:.85; }
  main { max-width:900px; margin:0 auto; padding:16px; }
  .msg { display:flex; margin:2px 0; }
  .msg.out { justify-content:flex-end; }
  .bubble { max-width:min(75%, 600px); padding:6px 9px 4px; border-radius:8px; background:var(--in); box-shadow:0 1px .5px rgba(0,0,0,.13); overflow-wrap:anywhere; }
  .out .bubble { background:var(--out); }
  .sender { font-weight:600; font-size:12.8px; color:var(--accent); margin-bottom:2px; }
  .text { white-space:pre-wrap; }
  .text.deleted { font-style:italic; color:var(--muted); }
  .quote { border-left:4px solid var(--accent); background:rgba(0,0,0,.05); padding:4px 8px; border-radius:4px; margin-bottom:4px; font-size:13px; color:var(--muted); white-space:pre-wrap; }
  .media { font-size:13px; color:var(--muted); margin-bottom:2px; }
  .file { display:flex; align-items:center; gap:10px; padding:10px 12px; margin:2px 0 4px; border-radius:6px; background:rgba(0,0,0,.06); color:inherit; text-decoration:none; }
  .file:hover { background:rgba(0,0,0,.1); }
  .file-icon { font-size:26px; }
  .file-name { flex:1; font-weight:500; overflow-wrap:anywhere; }
  .file-size { font-size:11.5px; color:var(--muted); text-transform:uppercase; white-space:nowrap; }
  .clip { display:block; max-width:100%; max-height:420px; border-radius:6px; margin:2px 0 4px; background:#000; }
  audio { display:block; max-width:100%; margin:2px 0 4px; }
  .photo { display:block; max-width:100%; max-height:420px; border-radius:6px; margin:2px 0 4px; cursor:zoom-in; }
  .photo.sticker { max-width:160px; max-height:160px; }
  .video { position:relative; }
  .video .play { position:absolute; left:8px; bottom:12px; background:rgba(0,0,0,.6); color:#fff; font-size:12px; padding:2px 8px; border-radius:10px; }
  #lightbox { position:fixed; inset:0; background:rgba(0,0,0,.88); display:flex; align-items:center; justify-content:center; z-index:10; cursor:zoom-out; }
  #lightbox[hidden] { display:none; }
  #lightbox img { max-width:96vw; max-height:96vh; }
  .meta { font-size:11px; color:var(--muted); text-align:right; margin-top:2px; }
  .divider { text-align:center; margin:12px 0; }
  .divider span, .system { display:inline-block; background:var(--pill); color:var(--muted); font-size:12.5px; padding:5px 12px; border-radius:8px; box-shadow:0 1px .5px rgba(0,0,0,.13); }
  .system { display:block; width:fit-content; max-width:90%; margin:8px auto; text-align:center; }
</style>
</head>
<body>
<header><h1>${esc(meta.chat)}</h1><p>${meta.messageCount} messages · exported ${esc(new Date(meta.exportedAt).toLocaleString())}</p></header>
<main>
${parts.join('\n')}
</main>
<div id="lightbox" hidden><img alt=""></div>
<script>
  document.addEventListener('click', function (e) {
    var box = document.getElementById('lightbox');
    if (e.target.classList && e.target.classList.contains('photo')) {
      box.firstChild.src = e.target.src;
      box.hidden = false;
    } else if (e.target.closest && e.target.closest('#lightbox')) {
      box.hidden = true;
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.getElementById('lightbox').hidden = true;
  });
</script>
</body>
</html>
`;
  }

  // ---------------------------------------------------------------------------
  // Small utilities
  // ---------------------------------------------------------------------------

  function sleep(ms) {
    return new Promise((r) => setTimeout(r, ms));
  }

  async function waitFor(check, timeout) {
    const start = Date.now();
    while (Date.now() - start < timeout) {
      if (cancelRequested) return false;
      if (check()) return true;
      await sleep(200);
    }
    return false;
  }

  function formatSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let n = bytes;
    let i = 0;
    while (n >= 1024 && i < units.length - 1) {
      n /= 1024;
      i++;
    }
    return `${n < 10 && i ? n.toFixed(1) : Math.round(n)} ${units[i]}`;
  }

  function fileExt(name) {
    const m = /\.([A-Za-z0-9]{1,8})$/.exec(name || '');
    return m ? m[1] : 'file';
  }

  function shorten(s, n) {
    const one = s.replace(/\s+/g, ' ');
    return one.length > n ? one.slice(0, n - 1) + '…' : one;
  }

  function sanitizeFilename(name) {
    const clean = name.replace(/[\\/:*?"<>|\u0000-\u001f]/g, '_').replace(/\s+/g, ' ').trim();
    return (clean || 'chat').slice(0, 80);
  }

  function today() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  // ---------------------------------------------------------------------------
  // Debug report (structure only – all text, names and numbers are masked)
  // ---------------------------------------------------------------------------

  function mask(value) {
    return String(value).replace(/\p{L}/gu, 'a').replace(/\d/g, '9');
  }

  function describe(el) {
    const parts = [el.tagName.toLowerCase()];
    if (el.id) parts.push(`#${el.id}`);
    const cls = typeof el.className === 'string' ? el.className.trim().split(/\s+/).filter(Boolean) : [];
    if (cls.length) parts.push('.' + cls.slice(0, 8).join('.'));
    for (const attr of ['role', 'data-icon', 'data-testid', 'tabindex', 'dir']) {
      if (el.hasAttribute(attr)) parts.push(`[${attr}="${el.getAttribute(attr)}"]`);
    }
    for (const attr of ['data-id', 'data-pre-plain-text', 'aria-label', 'title']) {
      if (el.hasAttribute(attr)) parts.push(`[${attr}="${mask(el.getAttribute(attr)).slice(0, 60)}"]`);
    }
    if (el.tagName === 'IMG') parts.push(`{src:${(el.getAttribute('src') || '').slice(0, 5)}}`);
    return parts.join('');
  }

  function skeleton(el, depth, lines, maxDepth) {
    if (lines.length > 400) return;
    const text = Array.from(el.childNodes)
      .filter((c) => c.nodeType === Node.TEXT_NODE && c.nodeValue.trim())
      .map((c) => c.nodeValue.trim().length);
    lines.push(`${'  '.repeat(depth)}${describe(el)}${text.length ? ` "text(${text.join(',')})"` : ''}`);
    if (depth >= maxDepth) return;
    const kids = Array.from(el.children);
    kids.slice(0, 8).forEach((k) => skeleton(k, depth + 1, lines, maxDepth));
    if (kids.length > 8) lines.push(`${'  '.repeat(depth + 1)}… ${kids.length - 8} more`);
  }

  function buildDebugReport(main) {
    const lines = [
      'WhatsApp Chat Exporter debug report v' + api.runtime.getManifest().version,
      navigator.userAgent,
    ];
    if (!main) return lines.concat('#main not found').join('\n');
    const count = (sel) => main.querySelectorAll(sel).length;
    lines.push(
      'counts: ' +
        JSON.stringify({
          rows: count('[role="row"]'),
          dataId: count('[data-id]'),
          prePlain: count('[data-pre-plain-text]'),
          msgIn: count('.message-in'),
          msgOut: count('.message-out'),
          selectable: count('span.selectable-text'),
          copyable: count('.copyable-text'),
          tails: count('[data-icon^="tail-"]'),
          nodesFound: getMessageNodes(main).length,
        })
    );
    const anchor = main.querySelector('[data-pre-plain-text]') || main.querySelector('span.selectable-text');
    if (anchor) {
      lines.push('', '--- ancestors of first message text (outermost last) ---');
      for (let el = anchor; el && el !== main.parentElement; el = el.parentElement) {
        lines.push(`${describe(el)} children=${el.children.length}`);
      }
      // the row-level element: highest ancestor below a node with many children
      let row = anchor;
      while (row.parentElement && row.parentElement !== main && row.parentElement.children.length < 4) {
        row = row.parentElement;
      }
      lines.push('', '--- message list (2 rows) ---');
      const list = row.parentElement || row;
      skeleton(list, 0, lines, 1);
      lines.push('', '--- one message row ---');
      skeleton(row, 0, lines, 14);
    } else {
      lines.push('', '--- #main (no message text element found) ---');
      skeleton(main, 0, lines, 9);
    }
    return lines.join('\n');
  }

  // ---------------------------------------------------------------------------
  // On-page progress overlay
  // ---------------------------------------------------------------------------

  function createOverlay() {
    const old = document.getElementById('wa-chat-exporter-overlay');
    if (old) old.remove();

    let keepOpen = false;
    const box = document.createElement('div');
    box.id = 'wa-chat-exporter-overlay';
    Object.assign(box.style, {
      position: 'fixed',
      top: '16px',
      right: '16px',
      zIndex: '2147483647',
      background: '#111b21',
      color: '#e9edef',
      font: '13px/1.4 system-ui, sans-serif',
      padding: '12px 14px',
      borderRadius: '10px',
      boxShadow: '0 6px 24px rgba(0,0,0,.35)',
      maxWidth: '320px',
      display: 'flex',
      gap: '10px',
      alignItems: 'center',
    });

    const label = document.createElement('div');
    label.style.flex = '1';
    const title = document.createElement('div');
    title.textContent = 'WhatsApp Chat Exporter';
    Object.assign(title.style, { fontWeight: '600', color: '#25d366', marginBottom: '2px' });
    const status = document.createElement('div');
    label.append(title, status);

    const btn = document.createElement('button');
    btn.textContent = 'Stop';
    Object.assign(btn.style, {
      background: '#2a3942',
      color: '#e9edef',
      border: '0',
      borderRadius: '6px',
      padding: '6px 10px',
      cursor: 'pointer',
      font: 'inherit',
    });
    btn.addEventListener('click', () => {
      if (running) {
        cancelRequested = true;
        btn.disabled = true;
        btn.textContent = 'Stopping…';
      } else {
        box.remove();
      }
    });

    box.append(label, btn);
    document.body.appendChild(box);

    const finish = (text, color) => {
      status.textContent = text;
      status.style.color = color;
      btn.disabled = false;
      btn.textContent = 'Close';
      setTimeout(() => {
        if (!keepOpen) box.remove();
      }, 8000);
    };

    return {
      set: (text) => {
        status.textContent = text;
      },
      offerDebug: (makeReport) => {
        keepOpen = true;
        const copy = document.createElement('button');
        copy.textContent = 'Copy debug info';
        Object.assign(copy.style, { background: '#00a884', color: '#111b21', border: '0', borderRadius: '6px', padding: '6px 10px', cursor: 'pointer', font: 'inherit', marginTop: '8px', display: 'block' });
        copy.addEventListener('click', () => {
          const report = makeReport();
          let area = label.querySelector('textarea');
          if (!area) {
            area = document.createElement('textarea');
            area.readOnly = true;
            Object.assign(area.style, { width: '100%', height: '160px', marginTop: '6px', fontSize: '11px' });
            label.appendChild(area);
          }
          area.value = report;
          area.select();
          copy.textContent = 'Select all + Ctrl+C if not copied';
          const timeout = new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 1500));
          Promise.race([navigator.clipboard.writeText(report), timeout])
            .then(() => {
              copy.textContent = 'Copied ✔ – paste it in the chat';
            })
            .catch(() => {});
        });
        label.appendChild(copy);
      },
      done: (text) => finish(text, '#a6f3c0'),
      fail: (text) => finish(text, '#ff9b9b'),
    };
  }
})();
