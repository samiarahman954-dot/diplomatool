/* Douce Paresse Kit — mobile menu, pop-ups, forms, active links.
 * Everything uses event delegation so it keeps working when Elementor re-renders a widget in the editor. */
(function () {
	'use strict';

	var cfg = window.DPK || { ajax: '', i18n: {} };
	var i18n = cfg.i18n || {};

	function closest(el, sel) {
		return el && el.closest ? el.closest(sel) : null;
	}

	function lock(on) {
		document.documentElement.classList.toggle('dpk-lock', !!on);
	}

	/* ---------- Mobile menu ---------- */
	function setMenu(header, open) {
		if (!header) { return; }
		header.classList.toggle('is-open', open);
		var t = header.querySelector('.dpk-header__toggle');
		if (t) { t.setAttribute('aria-expanded', open ? 'true' : 'false'); }
		lock(open);
	}

	/* ---------- Pop-ups ---------- */
	function showPanel(modal, index) {
		var panels = modal.querySelectorAll('[data-dpk-panel]');
		if (!panels.length) { return; }
		index = (index + panels.length) % panels.length;
		for (var i = 0; i < panels.length; i++) { panels[i].hidden = i !== index; }
		modal.setAttribute('data-current', index);
		var dialog = modal.querySelector('.dpk-modal__dialog');
		if (dialog) { dialog.scrollTop = 0; }
	}

	function openModal(room, index, trigger) {
		var modal = room && room.querySelector('[data-dpk-modal]');
		if (!modal) { return; }
		modal._dpkTrigger = trigger || null;
		modal.hidden = false;
		showPanel(modal, index);
		lock(true);
		var dialog = modal.querySelector('.dpk-modal__dialog');
		if (dialog) { dialog.focus({ preventScroll: true }); }
	}

	function closeModal(modal) {
		if (!modal || modal.hidden) { return; }
		modal.hidden = true;
		lock(false);
		if (modal._dpkTrigger) { modal._dpkTrigger.focus({ preventScroll: true }); }
	}

	document.addEventListener('click', function (e) {
		var t = e.target;
		var el;

		if ((el = closest(t, '[data-dpk-menu-toggle]'))) {
			var header = closest(el, '[data-dpk-header]');
			setMenu(header, !header.classList.contains('is-open'));
			e.preventDefault();
			return;
		}
		if ((el = closest(t, '.dpk-header__overlay a'))) {
			setMenu(closest(el, '[data-dpk-header]'), false);
			return;
		}
		if ((el = closest(t, '[data-dpk-modal-open]'))) {
			openModal(closest(el, '[data-dpk-room]'), parseInt(el.getAttribute('data-dpk-modal-open'), 10) || 0, el);
			return;
		}
		if ((el = closest(t, '[data-dpk-modal-close]'))) {
			closeModal(closest(el, '[data-dpk-modal]'));
			return;
		}
		if ((el = closest(t, '[data-dpk-modal-step]'))) {
			var modal = closest(el, '[data-dpk-modal]');
			var cur = parseInt(modal.getAttribute('data-current'), 10) || 0;
			showPanel(modal, cur + (parseInt(el.getAttribute('data-dpk-modal-step'), 10) || 1));
			return;
		}
		if (t && t.hasAttribute && t.hasAttribute('data-dpk-modal')) {
			closeModal(t); // click on the backdrop
			return;
		}
		if ((el = closest(t, '[data-dpk-form-reset]'))) {
			var wrap = closest(el, '[data-dpk-formwrap]');
			var form = wrap.querySelector('[data-dpk-form]');
			form.reset();
			form.hidden = false;
			wrap.querySelector('[data-dpk-success]').hidden = true;
		}
	});

	// The overlay only exists below 1080px: close it (and release scrolling) when the window grows.
	window.addEventListener('resize', function () {
		if (window.innerWidth < 1080) { return; }
		var headers = document.querySelectorAll('[data-dpk-header].is-open');
		for (var i = 0; i < headers.length; i++) { setMenu(headers[i], false); }
	});

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		var modals = document.querySelectorAll('[data-dpk-modal]:not([hidden])');
		for (var i = 0; i < modals.length; i++) { closeModal(modals[i]); }
		var headers = document.querySelectorAll('[data-dpk-header].is-open');
		for (var j = 0; j < headers.length; j++) { setMenu(headers[j], false); }
	});

	/* ---------- Forms ---------- */
	function fieldError(input, msg) {
		var field = closest(input, '.dpk-field');
		if (!field) { return; }
		field.classList.toggle('has-error', !!msg);
		var out = field.querySelector('.dpk-field__error');
		if (out) { out.textContent = msg || ''; }
	}

	function validate(form) {
		var ok = true;
		var first = null;
		var inputs = form.querySelectorAll('.dpk-field__input');
		for (var i = 0; i < inputs.length; i++) {
			var input = inputs[i];
			var val = String(input.value || '').trim();
			var msg = '';
			if (input.hasAttribute('data-required') && !val) {
				msg = input.tagName === 'SELECT' ? (i18n.select || i18n.required) : i18n.required;
			} else if (val && input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
				msg = i18n.email;
			}
			fieldError(input, msg);
			if (msg) {
				ok = false;
				first = first || input;
			}
		}
		if (first) { first.focus(); }
		return ok;
	}

	/* ---------- Anti-spam: signed time token (fetched fresh, so page caching never breaks it) ---------- */
	var tok = { value: '', at: 0, min: 4, promise: null };

	function fetchToken() {
		if (tok.promise) { return tok.promise; }
		var body = new FormData();
		body.append('action', 'dpk_form_token');
		tok.promise = fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				tok.value = res.data.token;
				tok.min = parseInt(res.data.min, 10) || 0;
				tok.at = Date.now();
				tok.promise = null;
				return tok.value;
			})
			.catch(function () { tok.promise = null; return ''; });
		return tok.promise;
	}

	/* Resolves with a token old enough for the server's minimum delay. */
	function readyToken() {
		return (tok.value ? Promise.resolve(tok.value) : fetchToken()).then(function (value) {
			var wait = tok.min * 1000 + 300 - (Date.now() - tok.at);
			return wait > 0 ? new Promise(function (r) { setTimeout(function () { r(tok.value); }, wait); }) : value;
		});
	}

	/* ---------- Cloudflare Turnstile ---------- */
	var ts = cfg.turnstile;

	function renderTurnstile(scope) {
		if (!ts || !window.turnstile) { return; }
		var els = (scope || document).querySelectorAll('[data-dpk-turnstile]');
		for (var i = 0; i < els.length; i++) {
			if (els[i].getAttribute('data-widget-id')) { continue; }
			var id = window.turnstile.render(els[i], {
				sitekey: ts.sitekey,
				theme: ts.theme,
				appearance: ts.appearance,
				action: 'dpk-form',
				'response-field-name': 'cf-turnstile-response'
			});
			els[i].setAttribute('data-widget-id', id);
		}
	}

	function whenTurnstile(cb) {
		if (!ts) { return; }
		if (!window.turnstile && !document.querySelector('script[src*="challenges.cloudflare.com/turnstile"]')) {
			var sc = document.createElement('script');
			sc.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
			sc.async = true;
			document.head.appendChild(sc);
		}
		var n = 0;
		(function poll() {
			if (window.turnstile) { cb(); return; }
			if (++n < 150) { setTimeout(poll, 200); }
		})();
	}

	function resetTurnstile(form) {
		var el = form.querySelector('[data-dpk-turnstile]');
		var id = el && el.getAttribute('data-widget-id');
		if (id && window.turnstile) { window.turnstile.reset(id); }
	}

	/* Turnstile fills its hidden field asynchronously; wait a little for it. */
	function turnstileReady(form, ms) {
		if (!ts || !form.querySelector('[data-dpk-turnstile]')) { return Promise.resolve(true); }
		return new Promise(function (resolve) {
			var start = Date.now();
			(function poll() {
				var input = form.querySelector('[name="cf-turnstile-response"]');
				if (input && input.value) { resolve(true); return; }
				if (Date.now() - start > ms) { resolve(false); return; }
				setTimeout(poll, 200);
			})();
		});
	}

	function setStatus(form, msg) {
		var status = form.querySelector('.dpk-form__status');
		if (status) { status.textContent = msg || ''; }
	}

	function send(form, allowRetry) {
		form.classList.add('is-sending');
		readyToken().then(function (token) {
			if (!token) { throw new Error('token'); }
			form.querySelector('[name="dpk_token"]').value = token;
			return turnstileReady(form, 6000);
		}).then(function (tsOk) {
			if (!tsOk) {
				form.classList.remove('is-sending');
				setStatus(form, i18n.challenge);
				return null;
			}
			var data = new FormData(form);
			data.append('action', 'dpk_form');
			data.append('post_id', form.getAttribute('data-post'));
			data.append('widget_id', form.getAttribute('data-widget'));
			return fetch(cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					form.classList.remove('is-sending');
					resetTurnstile(form);
					if (res && res.success) {
						tok.value = '';
						fetchToken(); // ready for a new message
						var wrap = closest(form, '[data-dpk-formwrap]');
						form.hidden = true;
						wrap.querySelector('[data-dpk-success]').hidden = false;
						return;
					}
					var d = (res && res.data) || {};
					if (d.retry && allowRetry) {
						tok.value = ''; // expired / used / too fast: get a fresh token and try once more
						send(form, false);
						return;
					}
					var errs = d.fields || {};
					Object.keys(errs).forEach(function (name) {
						var input = form.querySelector('[name="' + name + '"]');
						if (input) { fieldError(input, errs[name]); }
					});
					setStatus(form, d.message || i18n.error);
				});
		}).catch(function () {
			form.classList.remove('is-sending');
			setStatus(form, i18n.error);
		});
	}

	document.addEventListener('submit', function (e) {
		var form = closest(e.target, '[data-dpk-form]');
		if (!form) { return; }
		e.preventDefault();
		setStatus(form, '');
		if (!validate(form) || form.classList.contains('is-sending')) { return; }
		send(form, true);
	});

	function initForms(scope) {
		if (!(scope || document).querySelector('[data-dpk-form]')) { return; }
		if (ts) {
			var forms = (scope || document).querySelectorAll('[data-dpk-form]');
			for (var i = 0; i < forms.length; i++) {
				if (forms[i].querySelector('[data-dpk-turnstile]')) { continue; }
				var box = document.createElement('div');
				box.className = 'dpk-turnstile';
				box.setAttribute('data-dpk-turnstile', '');
				var anchor = forms[i].querySelector('.dpk-form__privacy') || forms[i].querySelector('.dpk-form__footer');
				forms[i].insertBefore(box, anchor);
			}
		}
		if (!tok.value) { fetchToken(); }
		whenTurnstile(function () { renderTurnstile(scope); });
	}

	document.addEventListener('input', function (e) {
		if (e.target.classList && e.target.classList.contains('dpk-field__input')) { fieldError(e.target, ''); }
	});

	/* ---------- Active menu links ---------- */
	var NOISE = /^(utm_.*|fbclid|gclid|msclkid|ver|elementor-preview|preview|preview_id|preview_nonce|_ga|lang)$/;

	/* Comparable form of a URL: path + meaningful query (?page_id=12 with "Plain" permalinks), no hash. */
	function norm(url) {
		try {
			var u = new URL(url, window.location.href);
			if (u.origin !== window.location.origin) { return null; }
			var params = [];
			u.searchParams.forEach(function (v, k) { if (!NOISE.test(k)) { params.push(k + '=' + v); } });
			params.sort();
			var path = u.pathname.replace(/\/index\.php$/, '/').replace(/\/+$/, '') || '/';
			return path + (params.length ? '?' + params.join('&') : '');
		} catch (err) {
			return null;
		}
	}

	function markActive(scope) {
		var here = norm(window.location.href);
		var links = (scope || document).querySelectorAll('.dpk-header__menu a, .dpk-header__overlay-links a');
		for (var i = 0; i < links.length; i++) {
			var href = links[i].getAttribute('href');
			var match = href && href.charAt(0) !== '#' && norm(links[i].href) === here;
			if (match) { links[i].setAttribute('aria-current', 'page'); } else { links[i].removeAttribute('aria-current'); }
		}
	}

	/* ---------- Fixed header ----------
	 * The original header is position:fixed directly under <body>. In Elementor it sits inside several
	 * wrappers (Theme Builder document, containers, widget). position:fixed breaks as soon as one of them
	 * has a transform / filter / will-change (entrance animation, motion effect, theme CSS), and the header
	 * slides under the page content when one of them is a stacking context (z-index, opacity…).
	 * On the live site the header widget is therefore moved into its own wrapper directly under <body>;
	 * the wrapper repeats the document class (elementor-123) so the widget's Style-tab CSS still applies. */
	var BREAKS_FIXED = ['transform', 'filter', 'backdropFilter', 'perspective'];

	function inEditor() {
		return document.body.classList.contains('elementor-editor-active') ||
			/[?&]elementor-preview=/.test(window.location.search) ||
			!!(window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode());
	}

	function neutralise(el) {
		var cs = getComputedStyle(el);
		// Always set (not only when present now): an entrance animation may add a transform later, and
		// Chrome keeps a containing block while a transform animation is in effect, even when overridden.
		BREAKS_FIXED.forEach(function (prop) {
			el.style.setProperty(prop.replace(/[A-Z]/g, function (c) { return '-' + c.toLowerCase(); }), 'none', 'important');
		});
		if (cs.animationName !== 'none' || el.classList.contains('elementor-invisible') || /"(_?animation|motion_fx)/.test(el.getAttribute('data-settings') || '')) {
			el.style.setProperty('animation', 'none', 'important');
			el.style.setProperty('visibility', 'visible', 'important');
		}
		if (/transform|filter|perspective/.test(cs.willChange)) { el.style.setProperty('will-change', 'auto', 'important'); }
		if (cs.contain && cs.contain !== 'none') { el.style.setProperty('contain', 'none', 'important'); }
		if (cs.containerType && cs.containerType !== 'normal') { el.style.setProperty('container-type', 'normal', 'important'); }
	}

	function liftHeader(root) {
		var widget = closest(root, '.elementor-widget') || root;
		var parent = widget.parentNode;
		if (!parent || parent === document.body || (parent.classList && parent.classList.contains('dpk-header-portal'))) { return; }
		var doc = closest(widget, '[data-elementor-id]');
		var wrap = document.createElement('div');
		wrap.className = 'dpk-header-portal' + (doc ? ' elementor elementor-' + doc.getAttribute('data-elementor-id') : '');
		document.body.insertBefore(wrap, document.body.firstChild);
		wrap.appendChild(widget);
	}

	function keepHeaderFixed() {
		var roots = document.querySelectorAll('[data-dpk-header]');
		var editor = inEditor();
		var state = window.__dpkKit = window.__dpkKit || { runs: 0, lifted: 0 };
		state.runs++;
		state.editor = editor;
		if (!roots.length) { return; }
		for (var i = 0; i < roots.length; i++) {
			var lift = !editor && roots[i].classList.contains('dpk-header--fixed');
			if (lift) { liftHeader(roots[i]); state.lifted = document.querySelectorAll('.dpk-header-portal [data-dpk-header]').length; }
			// Otherwise (editor, or header not fixed but with its full-screen mobile menu) the widget stays
			// in place and its ancestors are neutralised instead.
			for (var el = roots[i].parentElement; el && el !== document.documentElement; el = el.parentElement) {
				if (!lift || el === document.body) { neutralise(el); }
			}
		}
		neutralise(document.documentElement);
	}

	/* ---------- Languages: FR | EN switcher driving GTranslate 5 ----------
	 * GTranslate (free plan) translates with its own library (cdn.gtranslate.net/widgets/latest/lib.min.js,
	 * window.__GT.translator) and keeps the visitor's language in localStorage "__GT_TRANSLATE_LANGS"
	 * ({ tgtLang }). On every page its widget script loads the library when tgtLang is not the original
	 * language, and the library translates the page. Back to the original is doGTranslate('fr|fr'):
	 * no reload, no cookie. When GTranslate's widget is not printed on the page (floating selector off),
	 * the kit loads the same library the same way. Paid plans (/en/… or en.example.com): plain links. */
	var GT_STORE = '__GT_TRANSLATE_LANGS';
	var GT_LIB = 'https://cdn.gtranslate.net/widgets/latest/lib.min.js';
	var pendingLang = null;

	function gtSettings() {
		var g = window.gtranslateSettings;
		if (!g || typeof g !== 'object') { return {}; }
		if (g.default_language) { return g; }
		for (var k in g) { if (g[k] && g[k].default_language) { return g[k]; } }
		return {};
	}

	// GTranslate's settings: from its widget on the page, else from the server (the kit reads its option).
	function gtConfig() {
		var c = cfg.gt || {};
		var g = gtSettings();
		return { source: g.default_language || c.source || '', mode: g.url_structure || c.mode || 'none', languages: g.languages || c.languages || [] };
	}

	function paidMode() {
		var m = gtConfig().mode;
		return m === 'sub_directory' || m === 'sub_domain';
	}

	function sourceLang(sw) {
		sw = sw || document.querySelector('[data-dpk-lang-switch]');
		return gtConfig().source || (sw && sw.getAttribute('data-source')) || 'fr';
	}

	function sameLang(a, b) {
		a = String(a || '').toLowerCase();
		b = String(b || '').toLowerCase();
		return a === b || a.split('-')[0] === b.split('-')[0];
	}

	function switchCodes() {
		return Array.prototype.map.call(document.querySelectorAll('[data-dpk-lang]'), function (b) { return b.getAttribute('data-dpk-lang'); })
			.filter(function (c, i, all) { return all.indexOf(c) === i; });
	}

	function storedLang() {
		try {
			var st = JSON.parse(window.localStorage.getItem(GT_STORE));
			return st && typeof st.tgtLang === 'string' && st.tgtLang ? st.tgtLang : null;
		} catch (e) {
			return null;
		}
	}

	function currentLang(sw) {
		var src = sourceLang(sw);
		if (paidMode()) {
			// The language is in the URL. Not <html lang>: it follows the WordPress site language.
			var codes = gtConfig().languages.concat(switchCodes());
			var first = gtConfig().mode === 'sub_domain' ? window.location.hostname.split('.')[0] : (window.location.pathname.split('/')[1] || '');
			return first && first !== src && codes.indexOf(first) > -1 ? first : src;
		}
		var stored = storedLang();
		if (pendingLang && Date.now() - pendingLang.at < 15000 && !sameLang(stored || src, pendingLang.code)) {
			return pendingLang.code; // Just clicked: GTranslate's library is still loading / translating.
		}
		pendingLang = null;
		return stored || src;
	}

	function markLang() {
		var sws = document.querySelectorAll('[data-dpk-lang-switch]');
		for (var i = 0; i < sws.length; i++) {
			var cur = currentLang(sws[i]);
			var btns = sws[i].querySelectorAll('[data-dpk-lang]');
			for (var j = 0; j < btns.length; j++) {
				btns[j].setAttribute('aria-pressed', sameLang(cur, btns[j].getAttribute('data-dpk-lang')) ? 'true' : 'false');
			}
		}
		syncFixes();
	}

	/* English corrections (Douce Paresse → Languages): the passages listed there are marked "notranslate"
	   before GTranslate runs, so its machine translation never touches them, and while the visitor reads
	   in English they show the corrected English instead of the French. Matched on the French text. */
	var FIX_SKIP = /^(SCRIPT|STYLE|NOSCRIPT|TEXTAREA|TEMPLATE|SVG|IFRAME|TITLE)$/;
	var fixState = { total: 0, found: [], spots: [] };

	function fold(raw, withMap) {
		var out = '';
		var map = [];
		var space = false;
		for (var i = 0; i < raw.length; i++) {
			var c = raw.charAt(i);
			if (/[\s   ]/.test(c)) {
				if (out && !space) { out += ' '; map.push(i); }
				space = true;
				continue;
			}
			space = false;
			if (/[’‘ʼ`]/.test(c)) { c = "'"; } else if (/[“”«»]/.test(c)) { c = '"'; } else if (/[–—]/.test(c)) { c = '-'; } else if (c === '…') { c = '.'; }
			var low = c.toLowerCase();
			out += low.length === 1 ? low : c;
			map.push(i);
		}
		if (out.charAt(out.length - 1) === ' ') { out = out.slice(0, -1); map.pop(); }
		return withMap ? { text: out, map: map } : out;
	}

	function fixSkipped(el) {
		for (; el && el !== document.body; el = el.parentElement) {
			if (FIX_SKIP.test(el.tagName.toUpperCase()) || el.id === 'wpadminbar' || el.hasAttribute('data-dpk-fix') || el.hasAttribute('data-dpk-lang-switch') || el.hasAttribute('data-dpk-debug')) { return true; }
		}
		return false;
	}

	// Text of an element, line breaks and block edges as spaces ("Ligne 1<br>Ligne 2" → "Ligne 1 Ligne 2").
	// With nodes: the text nodes and where each starts (passages already corrected are left out).
	function plainText(el, nodes) {
		var out = '';
		var w = document.createTreeWalker(el, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
		for (var n = w.nextNode(); n; n = w.nextNode()) {
			if (n.nodeType === 3) {
				if (nodes) {
					if (fixSkipped(n.parentElement)) { out += ' '; continue; }
					nodes.push({ node: n, at: out.length });
				}
				out += n.nodeValue;
			} else if (/^(BR|P|DIV|LI|H[1-6]|OPTION)$/.test(n.tagName)) {
				out += ' ';
			}
		}
		return out;
	}

	function markFix(el, index) {
		el.setAttribute('data-dpk-fix', index);
		el.classList.add('notranslate');
		el.setAttribute('translate', 'no');
		fixState.spots.push({ el: el, index: index, original: null });
		if (fixState.found.indexOf(index) < 0) { fixState.found.push(index); }
	}

	// A passage inside a longer text, never in the middle of a word or phrase ("Nom" is not corrected inside
	// "Nombre de chambres", nor "Le Premier Geste" inside "Le premier geste de Douce Paresse"). It may start
	// after a sentence end or another element ("<strong>Les tasses</strong> ornées de …") and end before
	// punctuation, a symbol or the next sentence. Short passages (1–2 words) only next to symbols ("Nom *").
	function partEdges(t, at, key, tagBefore, tagAfter) {
		var before = t.slice(0, at);
		var after = t.slice(at + key.length);
		var word = /[\p{L}\p{N}']/u;
		if (word.test(before.slice(-1)) || word.test(after.charAt(0))) { return false; }
		var prev = before.replace(/ $/, '').slice(-1);
		var next = after.replace(/^ /, '').charAt(0);
		if (key.split(' ').length >= 3) {
			return (!prev || tagBefore || !word.test(prev)) && (!next || tagAfter || /[.!?:\u2026]$/.test(key) || !word.test(next));
		}
		return !/[\p{L}\p{N}]/u.test(before + after);
	}

	// The passage starts / ends right at an element edge (nothing but spaces between).
	function atTag(node, off, end) {
		var v = node.nodeValue;
		if ((end ? v.slice(off) : v.slice(0, off)).trim()) { return false; }
		var sib = end ? node.nextSibling : node.previousSibling;
		return !!sib && sib.nodeType === 1 && !sib.hasAttribute('data-dpk-brand');
	}

	function wrapPart(el, key, index) {
		var nodes = [];
		var raw = plainText(el, nodes);
		var f = fold(raw, true);
		var at = f.text.indexOf(key);
		if (at < 0) { return false; }
		var locate = function (pos) {
			for (var i = nodes.length - 1; i >= 0; i--) {
				if (pos >= nodes[i].at) { return pos - nodes[i].at <= nodes[i].node.nodeValue.length ? { node: nodes[i].node, off: pos - nodes[i].at } : null; }
			}
			return null;
		};
		var a = locate(f.map[at]);
		var b = locate(f.map[at + key.length - 1] + 1);
		if (!a || !b || !partEdges(f.text, at, key, atTag(a.node, a.off, false), atTag(b.node, b.off, true))) { return false; }
		var range = document.createRange();
		range.setStart(a.node, a.off);
		range.setEnd(b.node, b.off);
		var span = document.createElement('span');
		span.appendChild(range.extractContents());
		range.insertNode(span);
		markFix(span, index);
		return true;
	}

	function prepareFixes() {
		var list = cfg.fixes || [];
		if (!list.length || !document.body || inEditor()) { return; }
		fixState.total = list.length;
		var keys = list.map(function (f) { return fold(f.fr || ''); });
		var longest = Math.max.apply(null, keys.map(function (k) { return k.length; }));
		var cands = [];
		var all = document.body.getElementsByTagName('*');
		for (var i = 0; i < all.length; i++) {
			var raw = all[i].textContent;
			if (raw && raw.length <= Math.max(longest * 2 + 40, 3000) && !/^(SELECT|HTML|BODY)$/.test(all[i].tagName) && !fixSkipped(all[i])) {
				cands.push({ el: all[i], text: fold(plainText(all[i])) });
			}
		}
		// Longest passages first: a paragraph listed as a whole wins over one of its sentences listed too.
		var order = keys.map(function (k, j) { return j; }).sort(function (x, y) { return keys[y].length - keys[x].length; });
		order.forEach(function (j) {
			var key = keys[j];
			if (!key) { return; }
			var hits = cands.filter(function (c) { return c.text.indexOf(key) > -1 && !fixSkipped(c.el); });
			hits.forEach(function (h) {
				if (hits.some(function (o) { return o !== h && h.el.contains(o.el); })) { return; } // Innermost element only.
				if (h.text === key && !h.el.querySelector('[data-dpk-fix]')) {
					markFix(h.el, j); // The whole title / paragraph / button / option: replaced as a block.
				} else if (h.el.tagName !== 'OPTION') {
					while (wrapPart(h.el, key, j)) { /* every occurrence */ }
				}
			});
		});
		window.__dpkKit = window.__dpkKit || {};
		window.__dpkKit.fixes = fixState;
		syncFixes();
	}

	function syncFixes() {
		if (!fixState.spots.length) { return; }
		var src = sourceLang();
		var cur = currentLang();
		var english = !sameLang(cur, src) && sameLang(cur, 'en');
		fixState.spots.forEach(function (s) {
			if (english && !s.original) {
				s.original = document.createDocumentFragment();
				while (s.el.firstChild) { s.original.appendChild(s.el.firstChild); }
				if (s.el.tagName === 'OPTION') {
					var tmp = document.createElement('div');
					tmp.innerHTML = cfg.fixes[s.index].en;
					s.el.textContent = tmp.textContent;
				} else {
					s.el.innerHTML = cfg.fixes[s.index].en;
				}
			} else if (!english && s.original) {
				s.el.textContent = '';
				s.el.appendChild(s.original);
				s.original = null;
			}
		});
	}

	function gtWidgetOnPage() {
		return typeof window.doGTranslate === 'function' || !!window.gtranslateSettings;
	}

	// Same as GTranslate's own load_tlib().
	function gtLib(cb) {
		if (window.__GT && window.__GT.translator) {
			if (cb) { cb(); }
			return;
		}
		if (!window.gt_translate_script) {
			var sc = document.createElement('script');
			sc.src = GT_LIB;
			window.gt_translate_script = sc;
			document.body.appendChild(sc);
		}
		if (cb) { window.gt_translate_script.addEventListener('load', cb, { once: true }); }
	}

	function gtReady() {
		return !!(window.__GT && window.__GT.translator);
	}

	function gtTranslate(src, code) {
		var run = function () { window.__GT.translator.translate(src, code); };
		if (gtReady()) { run(); return; }
		if (!window.gt_translate_script) {
			// Nothing loading yet: let GTranslate's widget load its library, or load it the same way.
			if (typeof window.doGTranslate === 'function') { window.doGTranslate(src + '|' + code); } else { gtLib(run); }
			return;
		}
		// The library is already on its way (GTranslate restoring a stored language): wait until it is ready.
		// (Calling doGTranslate now would run before window.__GT exists – a race in GTranslate itself.)
		var tries = 0;
		(function wait() {
			if (gtReady()) { run(); return; }
			if (++tries < 80) { setTimeout(wait, 125); }
		})();
	}

	/* Paid GTranslate plans without their own links on the page: /en/page/ or en.example.com. */
	function translatedUrl(src, code) {
		var loc = window.location;
		var cur = currentLang();
		var mode = gtConfig().mode;
		if (mode === 'sub_directory') {
			var path = loc.pathname;
			if (cur && cur !== src && path.indexOf('/' + cur + '/') === 0) { path = path.slice(cur.length + 1); }
			return loc.protocol + '//' + loc.host + (code === src ? '' : '/' + code) + path + loc.search + loc.hash;
		}
		if (mode === 'sub_domain') {
			var host = loc.host;
			if (cur && cur !== src && host.indexOf(cur + '.') === 0) { host = host.slice(cur.length + 1); }
			var base = host.replace(/^www\./, '');
			return loc.protocol + '//' + (code === src ? host : code + '.' + base) + loc.pathname + loc.search + loc.hash;
		}
		return '';
	}

	function switchLang(sw, code) {
		var src = sourceLang(sw);
		if (sameLang(currentLang(sw), code)) { return; }
		if (paidMode()) {
			var a = document.querySelector('a[data-gt-lang="' + code + '"]');
			var url = a && a.href && !/#$/.test(a.href) ? a.href : translatedUrl(src, code);
			if (url) { window.location.href = url; }
			return;
		}
		pendingLang = { code: code, at: Date.now() };
		markLang();
		gtTranslate(src, code); // Also for the original language: GTranslate restores it in place.
		[800, 2000, 5000, 15500].forEach(function (ms) { setTimeout(markLang, ms); });
	}

	document.addEventListener('click', function (e) {
		var btn = closest(e.target, '[data-dpk-lang]');
		if (!btn) { return; }
		e.preventDefault();
		if (document.body.classList.contains('elementor-editor-active') || /[?&]elementor-preview=/.test(window.location.search)) { return; }
		var sw = closest(btn, '[data-dpk-lang-switch]');
		var header = closest(btn, '[data-dpk-header]');
		if (header && header.classList.contains('is-open')) { setMenu(header, false); }
		switchLang(sw, btn.getAttribute('data-dpk-lang'));
	});

	// Earlier kit versions drove Google's translator (googtrans cookie, dpk_lang, html lock): clean up.
	function cleanLegacy() {
		var host = window.location.hostname;
		var base = host.replace(/^www\./, '');
		var domains = ['', '; domain=' + host, '; domain=.' + host, '; domain=' + base, '; domain=.' + base];
		var paths = ['/'];
		var acc = '';
		window.location.pathname.split('/').forEach(function (part) {
			if (part) { acc += '/' + part; paths.push(acc, acc + '/'); }
		});
		var exp = '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		if (/(?:^|;)\s*googtrans=/.test(document.cookie)) {
			paths.forEach(function (pth) { domains.forEach(function (d) { document.cookie = 'googtrans=; path=' + pth + d + exp; }); });
		}
		if (/(?:^|;)\s*dpk_lang=/.test(document.cookie)) { document.cookie = 'dpk_lang=; path=/' + exp; }
	}

	// Visitor chose another language on an earlier page and GTranslate's widget is not on this page:
	// load GTranslate's library, which translates the page from its stored language (as its widget does).
	function followStoredLang() {
		var sw = document.querySelector('[data-dpk-lang-switch]');
		if (!sw || paidMode() || gtWidgetOnPage()) { markLang(); return; }
		var stored = storedLang();
		if (stored && !sameLang(stored, sourceLang(sw))) { gtLib(); }
		markLang();
	}

	// The brand name stays French in the tab title too (<title> cannot carry translate="no").
	function keepBrandInTitle() {
		var brand = (cfg.brand || []).filter(function (t) { return /^douce\s+paresse$/i.test(t); })[0];
		var titleEl = document.querySelector('title');
		if (!brand || !titleEl || !window.MutationObserver || document.title.toLowerCase().indexOf('douce paresse') < 0) { return; }
		new MutationObserver(function () {
			if (/douce\s+paresse/i.test(document.title)) { return; }
			var fixed = document.title.replace(/\b(sweet|gentle|soft|mild|pleasant|lovely|delightful)\s+(laziness|idleness|sloth|indolence)\b/i, 'Douce Paresse');
			if (fixed !== document.title) { document.title = fixed; }
		}).observe(titleEl, { childList: true, characterData: true, subtree: true });
	}

	window.addEventListener('storage', function (e) { if (e.key === GT_STORE) { markLang(); } }); // Other tabs.
	cleanLegacy();
	prepareFixes(); // Now, before GTranslate's library (loaded later, async) translates the page.

	/* WordPress admin bar (logged-in users): fixed above 600px, but it scrolls away on smaller screens.
	   Publish the height it really covers so the fixed header (and the mobile menu) sit right under it. */
	var adminBarTicking = false;
	function adminBarOffset() {
		adminBarTicking = false;
		var bar = document.getElementById('wpadminbar');
		if (!bar || !document.body.classList.contains('admin-bar')) { return; }
		var h = Math.max(0, Math.round(bar.getBoundingClientRect().bottom));
		var v = h + 'px';
		if (document.documentElement.style.getPropertyValue('--dpk-adminbar') !== v) {
			document.documentElement.style.setProperty('--dpk-adminbar', v);
		}
	}
	function queueAdminBar() {
		if (!adminBarTicking) { adminBarTicking = true; window.requestAnimationFrame(adminBarOffset); }
	}
	window.addEventListener('scroll', queueAdminBar, { passive: true });
	window.addEventListener('resize', queueAdminBar);
	window.addEventListener('load', adminBarOffset);
	window.addEventListener('load', followStoredLang); // After GTranslate's deferred widget script.

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { prepareFixes(); keepHeaderFixed(); adminBarOffset(); markActive(); markLang(); keepBrandInTitle(); initForms(); });
	} else {
		keepHeaderFixed();
		adminBarOffset();
		markActive();
		markLang();
		keepBrandInTitle();
		initForms();
	}
	window.addEventListener('load', keepHeaderFixed); // editor: after entrance animations have been applied

	window.addEventListener('elementor/frontend/init', function () {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) { return; }
		window.elementorFrontend.hooks.addAction('frontend/element_ready/dpk-header.default', function ($scope) {
			markActive($scope && $scope[0]);
		});
		['dpk-pro-form', 'dpk-contact'].forEach(function (name) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/' + name + '.default', function ($scope) {
				initForms($scope && $scope[0]);
			});
		});
	});
})();
