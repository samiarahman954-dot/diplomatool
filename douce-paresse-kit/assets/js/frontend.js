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

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { keepHeaderFixed(); markActive(); initForms(); });
	} else {
		keepHeaderFixed();
		markActive();
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
