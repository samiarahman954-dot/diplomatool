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

	/* ---------- Languages: FR | EN switcher driving GTranslate ----------
	 * Free GTranslate (Google Translate, cookie "googtrans"): doGTranslate('fr|en'). It only loads
	 * Google's library when its floating button is hovered, so the hidden button is "woken up" first;
	 * if nothing happened after a few seconds the cookie is set and the page reloaded (same result).
	 * Paid GTranslate (/en/ sub-directory or sub-domain): go to the URL of GTranslate's own link. */
	function gtSettings() {
		var g = window.gtranslateSettings;
		if (!g || typeof g !== 'object') { return {}; }
		if (g.default_language) { return g; }
		for (var k in g) { if (g[k] && g[k].default_language) { return g[k]; } }
		return {};
	}

	// Target language of the googtrans cookie ("/fr/en" → "en"). Several cookies can coexist (Google
	// writes it for the domain, the kit for the host): the first one naming a language wins.
	function langCookie() {
		var re = /(?:^|;)\s*googtrans=([^;]*)/g;
		var m;
		while ((m = re.exec(document.cookie))) {
			var t = decodeURIComponent(m[1]).split('/')[2];
			if (t) { return t; }
		}
		return null;
	}

	function switchCodes() {
		return Array.prototype.map.call(document.querySelectorAll('[data-dpk-lang]'), function (b) { return b.getAttribute('data-dpk-lang'); })
			.filter(function (c, i, all) { return all.indexOf(c) === i; });
	}

	function sourceLang(sw) {
		return gtSettings().default_language || (sw && sw.getAttribute('data-source')) || 'fr';
	}

	function translatedPage() {
		return /\btranslated-(ltr|rtl)\b/.test(document.documentElement.className);
	}

	function currentLang(sw) {
		var g = gtSettings();
		var src = sourceLang(sw);
		if (g.url_structure && g.url_structure !== 'none') {
			// Paid plans: the language is in the URL (/en/… or en.example.com). Not <html lang>: it follows
			// the WordPress site language, which can be English on a French site.
			var codes = (g.languages || []).concat(Array.prototype.map.call(document.querySelectorAll('[data-dpk-lang]'), function (b) { return b.getAttribute('data-dpk-lang'); }));
			var first = g.url_structure === 'sub_domain' ? window.location.hostname.split('.')[0] : (window.location.pathname.split('/')[1] || '');
			return first && first !== src && codes.indexOf(first) > -1 ? first : src;
		}
		var c = langCookie();
		if (translatedPage()) {
			// What the page shows wins: Google does not always write the cookie the kit can read.
			if (c && !sameLang(c, src)) { return c; }
			var hl = document.documentElement.getAttribute('lang') || '';
			if (hl && !sameLang(hl, src) && switchCodes().some(function (x) { return sameLang(hl, x); })) { return hl.split('-')[0]; }
			var others = switchCodes().filter(function (x) { return !sameLang(x, src); });
			if (others.length === 1) { return others[0]; }
		}
		var choice = choiceLang();
		if (choice) { return choice; }
		return c || src;
	}

	function sameLang(a, b) {
		return String(a).toLowerCase() === String(b).toLowerCase() || String(a).toLowerCase().split('-')[0] === String(b).toLowerCase();
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
	}

	// Google writes googtrans without a path, so the browser binds it to the folder of the page where the
	// language was chosen (/la-maison/ → path=/la-maison): it must be removed on every parent path and
	// for every domain form, or it comes back after the reload.
	function cookieVariants() {
		var host = window.location.hostname;
		var base = host.replace(/^www\./, '');
		var domains = ['', '; domain=' + host, '; domain=.' + host, '; domain=' + base, '; domain=.' + base];
		var paths = ['/'];
		var acc = '';
		window.location.pathname.split('/').forEach(function (part) {
			if (!part) { return; }
			acc += '/' + part;
			paths.push(acc, acc + '/');
		});
		var out = [];
		paths.forEach(function (pth) {
			domains.forEach(function (d) {
				var v = '; path=' + pth + d;
				if (out.indexOf(v) < 0) { out.push(v); }
			});
		});
		return out;
	}

	function setLangCookie(value) {
		var exp = '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		cookieVariants().forEach(function (v) { document.cookie = 'googtrans=' + v + exp; });
		if (value) {
			var host = window.location.hostname.replace(/^www\./, '');
			document.cookie = 'googtrans=' + value + '; path=/';
			document.cookie = 'googtrans=' + value + '; path=/; domain=.' + host;
		}
	}

	// The visitor's own choice, kept by the kit (Google's cookie alone is not reliable).
	function choiceLang() {
		var m = document.cookie.match(/(?:^|;)\s*dpk_lang=([A-Za-z-]+)/);
		return m ? m[1] : null;
	}

	function setChoice(code) {
		document.cookie = 'dpk_lang=' + code + '; path=/; max-age=31536000; SameSite=Lax';
	}

	function reloadIn(code, src) {
		setChoice(code);
		setLangCookie(sameLang(code, src) ? '' : '/' + src + '/' + code);
		window.location.reload();
	}

	/* Google's website translator, loaded by the kit when GTranslate's own widget is not on the page. */
	function gtOnPage() {
		return typeof window.doGTranslate === 'function' || !!window.gtranslateSettings || !!document.querySelector('script[src*="gtranslate"]');
	}

	function loadGoogle(src) {
		if (window.dpkGoogleLoading) { return; }
		window.dpkGoogleLoading = true;
		var el = document.createElement('div');
		el.id = 'dpk-gt-element';
		el.className = 'skiptranslate';
		el.setAttribute('aria-hidden', 'true');
		document.body.appendChild(el);
		window.dpkGoogleTranslateInit = function () {
			new window.google.translate.TranslateElement({ pageLanguage: src, autoDisplay: false }, 'dpk-gt-element');
		};
		if (window.google && window.google.translate && window.google.translate.TranslateElement) {
			window.dpkGoogleTranslateInit();
			return;
		}
		var s = document.createElement('script');
		s.src = 'https://translate.google.com/translate_a/element.js?cb=dpkGoogleTranslateInit';
		document.body.appendChild(s);
	}

	function googleSwitch(code, tries) {
		var sel = document.querySelector('select.goog-te-combo');
		if (!sel || sel.options.length < 2) {
			if (tries < 60) { setTimeout(function () { googleSwitch(code, tries + 1); }, 250); }
			return;
		}
		sel.value = code;
		for (var i = 0; i < 2; i++) { // GTranslate fires it twice too (first change only loads the language).
			try { sel.dispatchEvent(new Event('change')); } catch (e) { /* old browser */ }
		}
	}

	/* Paid GTranslate plans without their own links on the page: /en/page/ or en.example.com. */
	function translatedUrl(g, src, code) {
		var loc = window.location;
		var cur = currentLang();
		if (g.url_structure === 'sub_directory') {
			var path = loc.pathname;
			if (cur && cur !== src && path.indexOf('/' + cur + '/') === 0) { path = path.slice(cur.length + 1); }
			return loc.protocol + '//' + loc.host + (code === src ? '' : '/' + code) + path + loc.search + loc.hash;
		}
		if (g.url_structure === 'sub_domain') {
			var host = loc.host;
			if (cur && cur !== src && host.indexOf(cur + '.') === 0) { host = host.slice(cur.length + 1); }
			var base = host.replace(/^www\./, '');
			return loc.protocol + '//' + (code === src ? host : code + '.' + base) + loc.pathname + loc.search + loc.hash;
		}
		return '';
	}

	function switchLang(sw, code) {
		var g = gtSettings();
		var src = sourceLang(sw);
		if (sameLang(currentLang(sw), code)) { return; }
		if (g.url_structure && g.url_structure !== 'none') {
			var a = document.querySelector('a[data-gt-lang="' + code + '"]');
			var url = a && a.href && !/#$/.test(a.href) ? a.href : translatedUrl(g, src, code);
			if (url) { window.location.href = url; }
			return;
		}
		setChoice(code);
		var libLoaded = function () { return !!(window.google && window.google.translate); };
		if (sameLang(code, src)) {
			// Back to the original: clearing the cookie and reloading is the only reliable way (Google's own
			// "show original" depends on its library and does not always clear its cookie).
			reloadIn(src, src);
			return;
		}
		if (typeof window.doGTranslate === 'function') {
			var wake = document.querySelectorAll('.gtranslate_wrapper, [class*="gt_container-"]');
			for (var i = 0; i < wake.length; i++) {
				try { wake[i].dispatchEvent(new Event('pointerenter')); } catch (e) { /* old browser */ }
			}
			window.doGTranslate(src + '|' + code);
		} else {
			// GTranslate prints nothing on the page (floating selector switched off, no widget placed):
			// use Google's website translator directly, exactly as GTranslate's free widget does.
			loadGoogle(src);
			googleSwitch(code, 0);
		}
		// Optimistic state, then check that Google really switched.
		var btns = document.querySelectorAll('[data-dpk-lang]');
		for (var j = 0; j < btns.length; j++) { btns[j].setAttribute('aria-pressed', sameLang(code, btns[j].getAttribute('data-dpk-lang')) ? 'true' : 'false'); }
		// Google's library can be slow on a weak connection: give it time before reloading.
		var tries = 0;
		setTimeout(function verify() {
			if (translatedPage()) {
				setLangCookie('/' + src + '/' + code); // Next pages open in this language too.
				markLang();
				return;
			}
			if (++tries < (libLoaded() ? 20 : 10)) { setTimeout(verify, 800); return; }
			reloadIn(code, src);
		}, 800);
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

	// Google translated / restored the page: update the switcher and remember the language for the next
	// pages right away (Google itself does not always write a cookie the kit can read).
	function langChanged() {
		markLang();
		var sw = document.querySelector('[data-dpk-lang-switch]');
		var g = gtSettings();
		if (!sw || (g.url_structure && g.url_structure !== 'none') || !translatedPage()) { return; }
		var src = sourceLang(sw);
		var cur = currentLang(sw);
		var choice = choiceLang();
		if (choice && sameLang(choice, src)) { return; }
		if (!sameLang(cur, src) && !sameLang(langCookie() || src, cur)) { setLangCookie('/' + src + '/' + cur); }
	}

	if (window.MutationObserver) {
		new MutationObserver(langChanged).observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'lang'] });
	}

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
	window.addEventListener('load', markLang); // GTranslate settings may be printed after the kit script
	// Runs as soon as the kit script runs (before GTranslate's deferred script reads the cookie): the
	// visitor's choice decides. Original language chosen → any leftover googtrans cookie is removed.
	(function followChoice() {
		var sw = document.querySelector('[data-dpk-lang-switch]');
		var g = gtSettings();
		if (!sw || (g.url_structure && g.url_structure !== 'none')) { return; }
		var src = sourceLang(sw);
		var choice = choiceLang();
		if (!choice && langCookie()) { choice = langCookie(); setChoice(choice); } // Chosen with an older version.
		if (choice && sameLang(choice, src) && langCookie()) { setLangCookie(''); }
	})();

	window.addEventListener('load', function () {
		// Visitor chose another language on a previous page and GTranslate is not on the page: Google's
		// translator applies the googtrans cookie by itself once loaded.
		var sw = document.querySelector('[data-dpk-lang-switch]');
		var g = gtSettings();
		if (!sw || (g.url_structure && g.url_structure !== 'none')) { return; }
		var src = sourceLang(sw);
		var choice = choiceLang();
		if (!choice || sameLang(choice, src)) {
			if (langCookie()) { setLangCookie(''); }
			return;
		}
		if (langCookie() !== choice) { setLangCookie('/' + src + '/' + choice); }
		if (!gtOnPage()) { loadGoogle(src); }
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { keepHeaderFixed(); adminBarOffset(); markActive(); markLang(); keepBrandInTitle(); initForms(); });
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
