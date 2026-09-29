/* Just Move DFW – Quote Builder. Each .jmq element on the page is an independent builder. */
(function () {
  'use strict';

  var TOTAL_STEPS = 7;
  var MAX_IMAGE_EDGE = 1600;   // photos are downscaled before upload
  var jspdfPromise = null;

  function fmt(tpl) {
    var args = Array.prototype.slice.call(arguments, 1), i = 0;
    return String(tpl).replace(/%(\d\$)?[sd]/g, function (m, pos) {
      return pos ? args[parseInt(pos, 10) - 1] : args[i++];
    });
  }

  function loadJsPDF(url) {
    if (window.jspdf && window.jspdf.jsPDF) { return Promise.resolve(); }
    if (!jspdfPromise) {
      jspdfPromise = new Promise(function (res, rej) {
        var s = document.createElement('script');
        s.src = url; s.async = true;
        s.onload = function () { res(); };
        s.onerror = function () { jspdfPromise = null; rej(new Error('jsPDF')); };
        document.head.appendChild(s);
      });
    }
    return jspdfPromise;
  }

  function toDataURL(blob) {
    return new Promise(function (res, rej) {
      var r = new FileReader();
      r.onload = function () { res(r.result); };
      r.onerror = rej;
      r.readAsDataURL(blob);
    });
  }

  /**
   * Shrink big phone photos so 10 of them fit under PHP's upload limits.
   * Resolves null for a JPEG/PNG/WebP the browser can't decode (not a real image).
   */
  function shrinkImage(file) {
    if (!/^image\/(jpeg|png|webp)$/i.test(file.type) || !window.createImageBitmap) { return Promise.resolve(file); }
    return createImageBitmap(file).then(function (bmp) {
      var scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(bmp.width, bmp.height));
      if (scale === 1 && file.size < 900 * 1024) { return file; }
      var c = document.createElement('canvas');
      c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
      c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
      return new Promise(function (res) {
        c.toBlob(function (b) {
          res(b && b.size < file.size ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', 0.82);
      });
    }, function () { return null; });
  }

  // jsPDF's built-in Helvetica only covers Latin-1.
  function pdfText(s) {
    return String(s == null ? '' : s)
      .replace(/[–—]/g, '-').replace(/×/g, 'x').replace(/[‘’]/g, "'").replace(/[“”]/g, '"').replace(/…/g, '...')
      .replace(/[^\x00-\xFF]/g, '');
  }

  function init(root) {
    if (root.getAttribute('data-jmq-ready')) { return; }
    root.setAttribute('data-jmq-ready', '1');

    var cfg;
    try { cfg = JSON.parse(root.getAttribute('data-jmq') || '{}'); } catch (e) { cfg = {}; }
    var i18n = cfg.i18n || {};
    var $ = function (s) { return root.querySelector(s); };
    var $$ = function (s) { return Array.prototype.slice.call(root.querySelectorAll(s)); };

    var state = {
      moveType: '', size: '', flex: 'Exact date', crew: 0, crewAuto: true,
      pack: 'no', supplies: 'no', specialty: [], files: [], result: null
    };
    var step = 1, sending = false;

    var form = $('.jmq-body');
    var nextBtn = $('.jmq-btn-next'), backBtn = $('.jmq-btn-back'),
        skipBtn = $('.jmq-btn-skip'), restartBtn = $('.jmq-btn-restart');
    var errBox = $('.jmq-error'), fileMsg = $('.jmq-file-msg');
    var field = function (n) { return form.elements[n]; };

    // Earliest selectable date: today (local).
    var d = new Date();
    field('move_date').min = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

    /* ---------- choices ---------- */
    $$('[data-single]').forEach(function (group) {
      var key = group.getAttribute('data-single');
      group.addEventListener('click', function (e) {
        var opt = e.target.closest('.jmq-opt');
        if (!opt) { return; }
        group.querySelectorAll('.jmq-opt').forEach(function (o) {
          o.classList.toggle('is-selected', o === opt);
          o.setAttribute('aria-checked', o === opt ? 'true' : 'false');
        });
        state[key] = opt.getAttribute('data-val');
        if (key === 'size') { state.crewAuto = true; }
        validate();
      });
    });

    $$('[data-toggle]').forEach(function (group) {
      var key = group.getAttribute('data-toggle');
      group.addEventListener('click', function (e) {
        var chip = e.target.closest('.jmq-chip');
        if (!chip) { return; }
        group.querySelectorAll('.jmq-chip').forEach(function (c) {
          c.classList.toggle('is-selected', c === chip);
          c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
        });
        var val = chip.getAttribute('data-val');
        if (key === 'crew') { state.crew = parseInt(val, 10); state.crewAuto = false; } else { state[key] = val; }
      });
    });

    $$('[data-multi]').forEach(function (group) {
      group.addEventListener('click', function (e) {
        var chip = e.target.closest('.jmq-chip');
        if (!chip) { return; }
        var on = !chip.classList.contains('is-selected');
        chip.classList.toggle('is-selected', on);
        chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        state.specialty = Array.prototype.map.call(group.querySelectorAll('.jmq-chip.is-selected'), function (c) { return c.getAttribute('data-val'); });
      });
    });

    form.addEventListener('input', validate);
    form.addEventListener('change', validate);
    form.addEventListener('submit', function (e) { e.preventDefault(); if (!nextBtn.disabled) { advance(); } });

    /* ---------- photos ---------- */
    var fileInput = $('.jmq-file'), drop = $('.jmq-upload'), thumbs = $('.jmq-thumbs');
    var maxFile = (cfg.maxFileMb || 10) * 1024 * 1024;

    function totalBytes() { return state.files.reduce(function (s, f) { return s + f.blob.size; }, 0); }

    function renderThumbs() {
      thumbs.innerHTML = '';
      state.files.forEach(function (f, i) {
        var t = document.createElement('div');
        t.className = 'jmq-thumb';
        if (f.preview) {
          var img = document.createElement('img'); img.src = f.preview; img.alt = ''; t.appendChild(img);
        } else {
          var doc = document.createElement('div'); doc.className = 'jmq-doc'; doc.textContent = f.name.slice(0, 18); t.appendChild(doc);
        }
        var rm = document.createElement('button');
        rm.type = 'button'; rm.className = 'jmq-rm'; rm.textContent = '×';
        rm.setAttribute('aria-label', 'Remove ' + f.name); rm.setAttribute('data-i', i);
        t.appendChild(rm);
        thumbs.appendChild(t);
      });
    }

    function addFiles(list) {
      var incoming = Array.prototype.slice.call(list || []);
      var msgs = [];
      fileInput.value = '';
      return incoming.reduce(function (p, file) {
        return p.then(function () {
          if (state.files.length >= cfg.maxFiles) { msgs.tooMany = true; return; }
          if (!/^image\//.test(file.type) && file.type !== 'application/pdf') { return; }
          return shrinkImage(file).then(function (blob) {
            if (!blob) { msgs.bad = true; return; }
            if (blob.size > maxFile || (cfg.maxTotal && totalBytes() + blob.size > cfg.maxTotal)) { msgs.tooBig = true; return; }
            var canPreview = /^image\/(jpeg|png|gif|webp)$/i.test(blob.type);
            state.files.push({ blob: blob, name: file.name, preview: canPreview ? URL.createObjectURL(blob) : null });
          });
        });
      }, Promise.resolve()).then(function () {
        renderThumbs();
        var out = [];
        if (msgs.bad) { out.push(i18n.badFile); }
        if (msgs.tooBig) { out.push(i18n.tooBig); }
        if (msgs.tooMany) { out.push(fmt(i18n.tooMany, cfg.maxFiles)); }
        fileMsg.textContent = out.join(' ');
      });
    }

    drop.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () { addFiles(fileInput.files); });
    ['dragover', 'dragenter'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-drag'); }); });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) { addFiles(e.dataTransfer.files); } });
    thumbs.addEventListener('click', function (e) {
      var rm = e.target.closest('.jmq-rm');
      if (!rm) { return; }
      var f = state.files.splice(parseInt(rm.getAttribute('data-i'), 10), 1)[0];
      if (f && f.preview) { URL.revokeObjectURL(f.preview); }
      renderThumbs();
    });

    /* ---------- navigation ---------- */
    function applyCrewRec() {
      if (!state.crewAuto || !state.size || !cfg.crewRec) { return; }
      state.crew = parseInt(cfg.crewRec[state.size], 10) || 2;
      $$('[data-toggle="crew"] .jmq-chip').forEach(function (c) {
        var on = parseInt(c.getAttribute('data-val'), 10) === state.crew;
        c.classList.toggle('is-selected', on);
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      $('.jmq-crew-hint').textContent = fmt(i18n.crewHint, (cfg.sizes && cfg.sizes[state.size]) || '', state.crew);
    }

    function validate() {
      var ok = true, v = function (n) { return (field(n).value || '').trim(); };
      if (step === 1) { ok = !!state.moveType; }
      if (step === 2) { ok = !!state.size; }
      if (step === 3) { ok = !!(v('from_addr') && v('to_addr')); }
      if (step === 4) { ok = !!v('move_date') && v('move_date') >= field('move_date').min; }
      if (step === 6) { ok = !!(v('name') && (v('phone') || v('email'))); }
      nextBtn.disabled = !ok || sending;
    }

    function show(n) {
      $$('.jmq-step').forEach(function (s) { s.classList.toggle('is-active', parseInt(s.getAttribute('data-step'), 10) === n); });
      $('.jmq-progress-bar').style.width = Math.max(10, (n / TOTAL_STEPS) * 100) + '%';
      $('.jmq-progress-label').textContent = n >= TOTAL_STEPS ? i18n.ready : fmt(i18n.step, n, TOTAL_STEPS - 1);
      var last = n === TOTAL_STEPS;
      // No way back once submitted: the request is already stored.
      backBtn.hidden = n === 1 || last;
      nextBtn.hidden = last;
      restartBtn.hidden = !last;
      skipBtn.hidden = n !== 5;
      nextBtn.textContent = n === 6 ? i18n.getQuote : i18n.next;
      if (n === 5) { applyCrewRec(); }
      errBox.classList.remove('is-on');
      validate();
    }

    function go(n) {
      step = n;
      show(step);
      var top = root.getBoundingClientRect().top;
      if (top < 0) { root.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    function advance() {
      if (step === 6) { submit(); return; }
      if (step < TOTAL_STEPS) { go(step + 1); }
    }

    nextBtn.addEventListener('click', function () { if (!nextBtn.disabled) { advance(); } });
    skipBtn.addEventListener('click', function () { if (step === 5) { go(6); } });
    backBtn.addEventListener('click', function () { if (step > 1 && !sending) { go(step - 1); } });
    restartBtn.addEventListener('click', function () { window.location.reload(); });

    /* ---------- submit ---------- */
    function showError(msg) {
      errBox.textContent = msg || i18n.error;
      errBox.classList.add('is-on');
    }

    function submit() {
      var email = field('email').value.trim();
      if (email && !field('email').checkValidity()) { showError(i18n.badEmail); return; }

      var fd = new FormData();
      fd.append('action', cfg.action);
      fd.append('move_type', state.moveType);
      fd.append('size', state.size);
      ['from_addr', 'from_access', 'to_addr', 'to_access', 'move_date', 'name', 'phone', 'email', 'website'].forEach(function (n) {
        fd.append(n, field(n).value.trim());
      });
      fd.append('flex', state.flex);
      fd.append('crew', state.crew || '');
      fd.append('pack', state.pack);
      fd.append('supplies', state.supplies);
      state.specialty.forEach(function (s) { fd.append('specialty[]', s); });
      state.files.forEach(function (f) { fd.append('files[]', f.blob, f.blob.name || f.name); });
      fd.append('page_url', window.location.href);

      sending = true;
      nextBtn.classList.add('is-busy');
      nextBtn.textContent = i18n.sending;
      validate();

      fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return null; }); })
        .then(function (res) {
          if (!res || !res.success) {
            var err = new Error((res && res.data && res.data.message) || i18n.error);
            err.fromServer = true;
            throw err;
          }
          if (res.data && res.data.ignored) { throw new Error(i18n.error); }
          state.result = res.data;
          renderQuote();
          go(7);
        })
        .catch(function (e) { showError(e && e.fromServer ? e.message : i18n.error); })
        .then(function () {
          sending = false;
          nextBtn.classList.remove('is-busy');
          if (step === 6) { nextBtn.textContent = i18n.getQuote; }
          validate();
        });
    }

    function renderQuote() {
      var r = state.result, local = $('.jmq-local'), custom = $('.jmq-custom');
      local.hidden = !r.instant;
      custom.hidden = !!r.instant;
      if (r.instant && r.view) {
        $('.jmq-total-amt').textContent = r.view.total;
        $('.jmq-total-sub').textContent = r.view.basis;
        var lines = $('.jmq-lines');
        lines.innerHTML = '';
        r.view.rows.concat([['Estimated total', '', r.view.total]]).forEach(function (row, i, all) {
          var line = document.createElement('div');
          line.className = 'jmq-line' + (i === all.length - 1 ? ' is-sub' : '');
          var left = document.createElement('div');
          var desc = document.createElement('div'); desc.className = 'jmq-desc';
          if (i === all.length - 1) { var b = document.createElement('b'); b.textContent = row[0]; desc.appendChild(b); } else { desc.textContent = row[0]; }
          left.appendChild(desc);
          if (row[1]) { var meta = document.createElement('div'); meta.className = 'jmq-meta'; meta.textContent = row[1]; left.appendChild(meta); }
          var amt = document.createElement('div'); amt.className = 'jmq-amt'; amt.textContent = row[2];
          line.appendChild(left); line.appendChild(amt);
          lines.appendChild(line);
        });
      } else {
        var what = r.quote.moveType === 'Office / Commercial' ? 'Commercial and office moves' : 'Long-distance moves';
        $('.jmq-callout-d').textContent = fmt(i18n.custom, what);
      }
      var status = 'Request #' + r.estNo + ' received — we’ll be in touch soon!';
      if (r.emailed) { status += ' A copy was sent to your email.'; }
      $('.jmq-status-text').textContent = status;
    }

    /* ---------- PDF ---------- */
    function logoData() {
      if (!cfg.logo) { return Promise.resolve(null); }
      return fetch(cfg.logo, { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.blob() : null; })
        .then(function (b) { return b && /image\/(jpeg|png)/.test(b.type) ? toDataURL(b).then(function (u) { return { url: u, fmt: /png/.test(b.type) ? 'PNG' : 'JPEG' }; }) : null; })
        .catch(function () { return null; });
    }

    function generatePDF(logo) {
      var r = state.result, q = r.quote, co = cfg.company || {};
      var doc = new window.jspdf.jsPDF({ unit: 'pt', format: 'letter' });
      var W = 612, navy = [12, 58, 82], blue = [39, 167, 224], gray = [110, 124, 136], ink = [15, 22, 32];
      var T = function (s, x, y, o) { doc.text(pdfText(s), x, y, o); };

      // header wave
      var pts = [[W, 0], [0, 52]], px = W, py = 52, steps = 26, base = 52, amp = 11;
      for (var i = 1; i <= steps; i++) {
        var x = W - (W * i / steps), y0 = base - amp + amp * Math.sin(i / steps * Math.PI * 3);
        pts.push([x - px, y0 - py]); px = x; py = y0;
      }
      pts.push([0 - px, 0 - py]);
      doc.setFillColor.apply(doc, navy); doc.lines(pts, 0, 0, [1, 1], 'F', true);
      if (logo) { try { doc.addImage(logo.url, logo.fmt, 42, 70, 92, 92); } catch (e) { /* logo is optional */ } }

      doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor.apply(doc, gray);
      var ry = 80;
      [co.phone, co.email, co.site, co.location].forEach(function (t) { if (t) { T(t, W - 42, ry, { align: 'right' }); ry += 14; } });
      doc.setFont('helvetica', 'bold'); doc.setFontSize(24); doc.setTextColor.apply(doc, blue);
      T(r.instant ? 'Moving Estimate' : 'Quote Request', W - 42, 205, { align: 'right' });

      doc.setFontSize(9.5); doc.setTextColor.apply(doc, ink);
      doc.setFont('helvetica', 'bold'); T('For:', 42, 235);
      doc.setFont('helvetica', 'normal'); T(q.name || '-', 150, 235); T(q.contact || '', 150, 249);
      doc.setFont('helvetica', 'bold'); T('Quote No:', W - 180, 235); T('Date:', W - 180, 249);
      doc.setFont('helvetica', 'normal'); T(String(r.estNo), W - 42, 235, { align: 'right' }); T(r.estDate, W - 42, 249, { align: 'right' });

      var y = 280;
      doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor.apply(doc, navy); T('Move details', 42, y);
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9.5); y += 18;
      [['Type', q.moveType], ['Size', q.size], ['From', q.from], ['To', q.to], ['Date', q.date], ['Specialty', q.specialty]].forEach(function (row) {
        if (!row[1]) { return; }
        doc.setTextColor.apply(doc, gray); T(row[0] + ':', 42, y);
        doc.setTextColor.apply(doc, ink);
        var wrapped = doc.splitTextToSize(pdfText(row[1]), W - 162);
        doc.text(wrapped, 120, y); y += 15 * wrapped.length;
      });

      y += 12;
      if (r.instant && r.view) {
        doc.setFillColor(233, 246, 253); doc.rect(42, y - 14, W - 84, 22, 'F');
        doc.setDrawColor.apply(doc, blue); doc.setLineWidth(1.4); doc.line(42, y + 8, W - 42, y + 8);
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5); doc.setTextColor.apply(doc, ink);
        T('Line item', 48, y); T('Details', 250, y); T('Estimate', W - 48, y, { align: 'right' });
        y += 26; doc.setFont('helvetica', 'normal');
        r.view.rows.forEach(function (row) {
          doc.setTextColor.apply(doc, ink); T(row[0], 48, y);
          doc.setTextColor.apply(doc, gray); T(row[1], 250, y);
          doc.setTextColor.apply(doc, ink); T(row[2], W - 48, y, { align: 'right' });
          doc.setDrawColor(238, 243, 247); doc.setLineWidth(0.6); doc.line(42, y + 9, W - 42, y + 9); y += 24;
        });
        y += 10; doc.setDrawColor.apply(doc, blue); doc.setLineWidth(1.6); doc.line(320, y - 6, W - 42, y - 6);
        doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor.apply(doc, navy); T('Estimated total', 330, y + 14);
        doc.setTextColor.apply(doc, ink); T(r.view.total, W - 42, y + 14, { align: 'right' });
        doc.setDrawColor.apply(doc, blue); doc.line(320, y + 24, W - 42, y + 24);
        y += 54; doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor.apply(doc, gray);
        doc.text(doc.splitTextToSize('Local moves are billed hourly; the final price reflects actual time on move day. This range covers the typical span for a move this size. Truck and basic equipment included. A coordinator confirms all details before booking.', W - 84), 42, y);
      } else {
        doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor.apply(doc, navy); T('Custom quote in progress', 42, y + 6);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(9.5); doc.setTextColor.apply(doc, gray);
        doc.text(doc.splitTextToSize('Long-distance and commercial moves are priced individually based on distance, volume, and timing. A coordinator will reach out with your detailed quote.', W - 84), 42, y + 26);
      }
      doc.setFontSize(8); doc.setTextColor.apply(doc, gray);
      T([co.name, co.phone, co.email, co.site].filter(Boolean).join('  ·  '), W / 2, 772, { align: 'center' });
      doc.save(pdfText((co.name || 'Moving') + '-Estimate-' + (q.name || 'Quote')).replace(/[^\w-]+/g, '_') + '-' + r.estNo + '.pdf');
    }

    $$('.jmq-dlbtn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!state.result || btn.disabled) { return; }
        var label = btn.innerHTML;
        btn.disabled = true; btn.textContent = i18n.pdf;
        Promise.all([loadJsPDF(cfg.jspdf), logoData()])
          .then(function (out) { generatePDF(out[1]); })
          .catch(function () { window.alert(i18n.pdfError); })
          .then(function () { btn.disabled = false; btn.innerHTML = label; });
      });
    });

    show(1);
  }

  function initAll(scope) {
    Array.prototype.forEach.call((scope || document).querySelectorAll('.jmq[data-jmq]'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
  } else {
    initAll(document);
  }

  // Elementor editor re-renders widgets without reloading the page.
  function hookElementor() {
    if (!window.elementorFrontend || !window.elementorFrontend.hooks || hookElementor.done) { return; }
    hookElementor.done = true;
    window.elementorFrontend.hooks.addAction('frontend/element_ready/jmk-quote-builder.default', function ($scope) {
      if ($scope && $scope[0]) { initAll($scope[0]); }
    });
  }
  hookElementor();
  if (window.jQuery) { window.jQuery(window).on('elementor/frontend/init', hookElementor); }
})();
