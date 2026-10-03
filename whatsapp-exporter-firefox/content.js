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
      const collected = {
        store: new Map(),
        order: [],
        cache: new WeakMap(),
        images: withImages ? new Map() : null, // message id -> { data, full }
        imageTried: new Set(),
      };
      collect(main, collected);

      if (options.loadHistory !== false) {
        await loadHistory(chatName, collected, maxMessages, overlay);
      }
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
      if (!realCount) throw new Error('No messages found in this chat.');

      overlay.set(`Building ${format.toUpperCase()} file (${realCount} messages)…`);
      const meta = {
        chat: chatName,
        exportedAt: new Date().toISOString(),
        messageCount: realCount,
      };
      const { ext, mime, build } = FORMATS[format];
      const content = build(meta, messages);
      const filename = `WhatsApp Chat - ${sanitizeFilename(chatName)} - ${today()}.${ext}`;

      const res = await api.runtime.sendMessage({
        type: 'WA_EXPORT_DOWNLOAD',
        filename,
        mime,
        content,
        saveAs: !!options.saveAs,
      });
      if (!res || !res.ok) throw new Error((res && res.error) || 'Download failed.');

      overlay.done(`✔ Exported ${realCount} messages to ${ext.toUpperCase()}`);
    } catch (err) {
      overlay.fail(`✖ ${(err && err.message) || err}`);
    } finally {
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
      if (collected.images) await captureImages(main, collected, scroller, overlay);
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
      if (collected.images) {
        await captureImages(main, collected, scroller, overlay);
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

  /* Save the pictures of the image/sticker/video messages currently in the
   * DOM as data: URLs. Images that are only a blurry preview get their
   * download button clicked (once) when on screen, and we wait briefly for
   * WhatsApp to load the real picture. */
  async function captureImages(main, collected, scroller, overlay) {
    const targets = [];
    for (const node of getMessageNodes(main)) {
      const msg = collected.cache.get(node);
      if (!msg || msg.kind !== 'message' || !PICTURE_MEDIA.has(msg.media)) continue;
      const prev = collected.images.get(msg.id);
      if (prev && prev.full) continue;
      const bubble = node.querySelector('.message-in, .message-out') || node;
      targets.push({ msg, node, bubble });
    }
    if (!targets.length) return;

    // ask WhatsApp to load full pictures that are visible but not loaded yet
    const waiting = [];
    for (const t of targets) {
      if (collected.imageTried.has(t.msg.id) || !isOnScreen(t.node, scroller)) continue;
      const img = bestImage(t.bubble);
      if (img && isFullImage(img)) continue;
      collected.imageTried.add(t.msg.id);
      if (t.msg.media !== 'video') clickMediaDownload(t.bubble);
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

    for (const t of targets) {
      if (cancelRequested) return;
      const img = bestImage(t.bubble);
      if (!img) continue;
      const full = isFullImage(img);
      const prev = collected.images.get(t.msg.id);
      if (prev && !full) continue; // already have the preview
      const data = await imageToDataUrl(img);
      if (data) collected.images.set(t.msg.id, { data, full });
    }
    overlay.set(`Reading messages… ${countMessages(collected)} found, ${collected.images.size} images saved`);
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

    // Firefox: content.fetch runs with the page's origin, which owns the blob: URL
    const fetchers = [];
    if (typeof content !== 'undefined' && content && typeof content.fetch === 'function') {
      fetchers.push((u) => content.fetch(u));
    }
    fetchers.push((u) => fetch(u));
    for (const doFetch of fetchers) {
      try {
        const blob = await (await doFetch(src)).blob();
        if (blob.size && blob.size <= MAX_IMAGE_BYTES && /^image\//.test(blob.type)) {
          return await blobToDataUrl(blob);
        }
        if (blob.size) break; // too big or unknown type: re-encode below
      } catch (e) {
        // try the next way
      }
    }
    return canvasDataUrl(img);
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

  function getMessageNodes(main) {
    const rows = main.querySelectorAll('[role="row"]');
    if (rows.length) return Array.from(rows);
    // fallback: outermost elements carrying a message id
    return Array.from(main.querySelectorAll('[data-id]')).filter(
      (el) => !el.parentElement || !el.parentElement.closest('[data-id]')
    );
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
    const bubble = node.matches('.message-in, .message-out')
      ? node
      : node.querySelector('.message-in, .message-out');

    if (!bubble) {
      const text = normalize(extractText(node));
      if (!text) return null;
      const kind = DATE_DIVIDER_RE.test(text) ? 'date' : 'system';
      return { id: id || `${kind}:${text}`, kind, text };
    }

    const fromMe = bubble.classList.contains('message-out') || (!!id && id.startsWith('true_'));

    let date = '';
    let time = '';
    let sender = '';
    const pre = bubble.querySelector('[data-pre-plain-text]');
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

    const deleted = !!bubble.querySelector('[data-icon="recalled"], [data-testid="recalled"]');
    if (deleted) {
      text = fromMe ? 'You deleted this message' : 'This message was deleted';
    }
    const media = deleted ? null : detectMedia(bubble);

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
      deleted,
    };
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
    if (has('audio, [data-icon="audio-play"], [data-icon="ptt-play"], [data-icon*="ptt"], [data-icon*="audio"]')) {
      return 'audio';
    }
    if (has('video, [data-icon="media-play"], [data-icon*="video"], [data-icon*="gif"]')) return 'video';
    if (has('[data-icon*="document"], [data-icon*="doc-"], [data-testid*="document"]')) return 'document';
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
    }
    for (const m of list) delete m.id;
    return list;
  }

  // ---------------------------------------------------------------------------
  // Output formats
  // ---------------------------------------------------------------------------

  function bodyOf(m) {
    let body = m.text || '';
    if (m.media) body = body ? `<Media omitted: ${m.media}> ${body}` : `<Media omitted: ${m.media}>`;
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
    const rows = [['date', 'time', 'sender', 'from_me', 'type', 'media', 'text', 'reply_to']];
    for (const m of messages) {
      if (m.kind === 'date') continue;
      rows.push([
        m.date || '',
        m.time || '',
        m.kind === 'message' ? m.sender : '',
        m.kind === 'message' ? (m.fromMe ? 'yes' : 'no') : '',
        m.kind,
        m.media || '',
        m.kind === 'message' && !m.text && m.media ? '<Media omitted>' : m.text,
        m.quote || '',
      ]);
    }
    // BOM so Excel opens UTF-8 (Bangla, emoji…) correctly
    return '﻿' + rows.map((r) => r.map(cell).join(',')).join('\r\n') + '\r\n';
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
      if (m.imageData) {
        const cls = m.media === 'sticker' ? 'photo sticker' : 'photo';
        const pic = `<img class="${cls}" src="${m.imageData}" alt="${esc(m.media)}" loading="lazy">`;
        media = m.media === 'video'
          ? `<div class="video">${pic}<span class="play">▶ video (thumbnail only)</span></div>`
          : pic;
      } else if (m.media) {
        media = `<div class="media">📎 ${esc(m.media)} (not included)</div>`;
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
  // On-page progress overlay
  // ---------------------------------------------------------------------------

  function createOverlay() {
    const old = document.getElementById('wa-chat-exporter-overlay');
    if (old) old.remove();

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
      setTimeout(() => box.remove(), 8000);
    };

    return {
      set: (text) => {
        status.textContent = text;
      },
      done: (text) => finish(text, '#a6f3c0'),
      fail: (text) => finish(text, '#ff9b9b'),
    };
  }
})();
