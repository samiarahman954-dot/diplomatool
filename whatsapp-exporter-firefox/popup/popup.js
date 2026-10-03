'use strict';

const api = typeof browser !== 'undefined' ? browser : chrome;
const $ = (id) => document.getElementById(id);

const DEFAULTS = {
  format: 'txt',
  loadHistory: true,
  includeSystem: true,
  saveAs: false,
  maxMessages: 0,
};

let tabId = null;

async function sendToTab(msg) {
  try {
    return await api.tabs.sendMessage(tabId, msg);
  } catch (e) {
    // The tab was opened before the add-on was installed/reloaded:
    // inject the content script and try again.
    await api.tabs.executeScript(tabId, { file: '/content.js' });
    return api.tabs.sendMessage(tabId, msg);
  }
}

function showMessage(text, isError) {
  const el = $('message');
  el.textContent = text;
  el.classList.toggle('error', !!isError);
}

function readForm() {
  return {
    format: document.querySelector('input[name="format"]:checked').value,
    loadHistory: $('loadHistory').checked,
    includeSystem: $('includeSystem').checked,
    saveAs: $('saveAs').checked,
    maxMessages: Math.max(0, parseInt($('maxMessages').value, 10) || 0),
  };
}

function fillForm(opts) {
  const radio = document.querySelector(`input[name="format"][value="${opts.format}"]`);
  if (radio) radio.checked = true;
  $('loadHistory').checked = !!opts.loadHistory;
  $('includeSystem').checked = !!opts.includeSystem;
  $('saveAs').checked = !!opts.saveAs;
  $('maxMessages').value = opts.maxMessages || 0;
}

async function init() {
  const stored = await api.storage.local.get(DEFAULTS).catch(() => DEFAULTS);
  fillForm({ ...DEFAULTS, ...stored });

  const [tab] = await api.tabs.query({ active: true, currentWindow: true });
  const onWhatsApp = tab && typeof tab.url === 'string' && /^https:\/\/web\.whatsapp\.com\//.test(tab.url);

  if (!onWhatsApp) {
    $('status').textContent = 'WhatsApp Web is not open in this tab.';
    $('not-on-wa').hidden = false;
    return;
  }

  tabId = tab.id;
  $('export-form').hidden = false;

  let status;
  try {
    status = await sendToTab({ type: 'WA_EXPORT_STATUS' });
  } catch (e) {
    status = null;
  }

  if (!status) {
    $('status').textContent = 'Could not connect to WhatsApp Web. Reload the page and try again.';
    $('export').disabled = true;
  } else if (!status.ready) {
    $('status').textContent = 'Open a chat in WhatsApp Web first.';
    $('export').disabled = true;
  } else {
    $('status').textContent = `Chat: ${status.chatName}`;
    if (status.running) {
      $('export').disabled = true;
      showMessage('An export is already running – see the WhatsApp tab.');
    }
  }
}

$('open-wa').addEventListener('click', async () => {
  await api.tabs.create({ url: 'https://web.whatsapp.com/' });
  window.close();
});

$('export-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const options = readForm();
  await api.storage.local.set(options).catch(() => {});

  $('export').disabled = true;
  showMessage('Starting…');
  try {
    const res = await sendToTab({ type: 'WA_EXPORT_START', options });
    if (!res || !res.ok) throw new Error((res && res.error) || 'Could not start the export.');
    showMessage('Export started. Keep the WhatsApp tab open – progress is shown on the page.');
    setTimeout(() => window.close(), 1800);
  } catch (err) {
    showMessage((err && err.message) || String(err), true);
    $('export').disabled = false;
  }
});

init().catch((err) => {
  $('status').textContent = (err && err.message) || String(err);
});
