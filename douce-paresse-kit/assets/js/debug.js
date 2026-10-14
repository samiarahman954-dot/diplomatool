/* Douce Paresse Kit — browser diagnostics (administrators only, page opened with ?dpk-debug=1).
 * Checks the live page in the real browser and shows a panel with a "Copy report" button. */
(function () {
	'use strict';

	var D = window.DPKDebug || {};
	var probe = window.__dpkDebug || { errors: [] };
	var results = [];
	var marked = [];
	var panel;

	var BREAKERS = ['transform', 'filter', 'backdropFilter', 'perspective', 'willChange', 'contain', 'containerType'];
	var STACKING = ['zIndex', 'opacity', 'isolation', 'mixBlendMode', 'clipPath'];

	function add(state, group, label, msg, details) {
		results.push({ state: state, group: group, label: label, msg: msg, details: details || '' });
	}

	function desc(el) {
		if (!el || !el.tagName) { return String(el); }
		if (el === document.documentElement) { return 'html'; }
		if (el === document.body) { return 'body'; }
		var s = el.tagName.toLowerCase();
		if (el.id) { s += '#' + el.id; }
		var cls = typeof el.className === 'string' ? el.className.trim().split(/\s+/).filter(Boolean) : [];
		if (cls.length) { s += '.' + cls.slice(0, 4).join('.'); }
		var id = el.getAttribute('data-id') || el.getAttribute('data-elementor-id');
		if (id) { s += '[' + (el.hasAttribute('data-elementor-id') ? 'doc ' : 'id ') + id + ']'; }
		return s;
	}

	function kebab(p) { return p.replace(/[A-Z]/g, function (c) { return '-' + c.toLowerCase(); }); }

	/** Properties of an element that make it the containing block of position:fixed descendants. */
	function breaksFixed(el) {
		var cs = getComputedStyle(el);
		var out = [];
		if (cs.transform && cs.transform !== 'none') { out.push('transform: ' + cs.transform); }
		if (cs.filter && cs.filter !== 'none') { out.push('filter: ' + cs.filter); }
		if (cs.backdropFilter && cs.backdropFilter !== 'none') { out.push('backdrop-filter: ' + cs.backdropFilter); }
		if (cs.perspective && cs.perspective !== 'none') { out.push('perspective: ' + cs.perspective); }
		if (/transform|filter|perspective/.test(cs.willChange)) { out.push('will-change: ' + cs.willChange); }
		if (cs.contain && /paint|layout|strict|content/.test(cs.contain)) { out.push('contain: ' + cs.contain); }
		if (cs.containerType && cs.containerType !== 'normal') { out.push('container-type: ' + cs.containerType); }
		if (el.getAnimations) {
			el.getAnimations().forEach(function (a) {
				var name = a.animationName || (a.effect && a.effect.getKeyframes && JSON.stringify(a.effect.getKeyframes()).slice(0, 80)) || 'animation';
				out.push('running animation: ' + name);
			});
		}
		return out;
	}

	function stacking(el) {
		var cs = getComputedStyle(el);
		var out = [];
		if (cs.position !== 'static' && cs.zIndex !== 'auto') { out.push('z-index: ' + cs.zIndex + ' (' + cs.position + ')'); }
		if (parseFloat(cs.opacity) < 1) { out.push('opacity: ' + cs.opacity); }
		if (cs.isolation === 'isolate') { out.push('isolation: isolate'); }
		if (cs.mixBlendMode && cs.mixBlendMode !== 'normal') { out.push('mix-blend-mode: ' + cs.mixBlendMode); }
		if (cs.clipPath && cs.clipPath !== 'none') { out.push('clip-path: ' + cs.clipPath); }
		return out;
	}

	/** CSS rules (all same-origin stylesheets) that match an element and set one of the given properties. */
	function rulesFor(el, props) {
		var found = [];
		var skipped = 0;
		function walk(rules, sheet) {
			for (var i = 0; i < rules.length; i++) {
				var r = rules[i];
				if (r.cssRules && (r.conditionText !== undefined || r.media)) {
					var cond = r.conditionText || (r.media && r.media.mediaText) || '';
					var active = true;
					if (r.media && cond) { active = window.matchMedia(cond).matches; }
					if (active) { walk(r.cssRules, sheet); }
					continue;
				}
				if (r.cssRules && !r.selectorText) { walk(r.cssRules, sheet); continue; } // @layer, @supports…
				if (!r.selectorText || !r.style) { continue; }
				var match = false;
				try { match = el.matches(r.selectorText); } catch (e) { match = false; }
				if (!match) { continue; }
				props.forEach(function (p) {
					var v = r.style.getPropertyValue(kebab(p));
					if (v) {
						var imp = r.style.getPropertyPriority(kebab(p)) ? ' !important' : '';
						found.push(kebab(p) + ': ' + v + imp + '   ← ' + r.selectorText.slice(0, 160) + '   [' + sheet + ']');
					}
				});
			}
		}
		for (var i = 0; i < document.styleSheets.length; i++) {
			var s = document.styleSheets[i];
			var name = s.href ? s.href.replace(/^.*\/wp-content\//, '').split('?')[0] : (s.ownerNode && s.ownerNode.id ? '<style #' + s.ownerNode.id + '>' : '<style>');
			try { walk(s.cssRules, name); } catch (e) { skipped++; }
		}
		var inline = el.getAttribute('style');
		if (inline) {
			props.forEach(function (p) {
				var v = el.style.getPropertyValue(kebab(p));
				if (v) { found.push(kebab(p) + ': ' + v + (el.style.getPropertyPriority(kebab(p)) ? ' !important' : '') + '   ← style="" attribute'); }
			});
		}
		if (skipped) { found.push('(' + skipped + ' stylesheet(s) from other domains could not be read)'); }
		return found;
	}

	function mark(el) {
		if (el && el.style) { marked.push(el); }
	}

	function nextFrame() {
		return new Promise(function (r) { requestAnimationFrame(function () { requestAnimationFrame(function () { setTimeout(r, 120); }); }); });
	}

	/* ------------------------------------------------------------------ */

	function checkAssets() {
		var G = 'Kit files';
		var kit = window.__dpkKit;
		var js = document.querySelector('script[src*="douce-paresse-kit/assets/js/frontend.js"]');
		var delayed = document.querySelector('script[data-rocket-src*="douce-paresse-kit"], script[data-litespeed-src*="douce-paresse-kit"], script[data-src*="douce-paresse-kit"], script[data-lazy-src*="douce-paresse-kit"]');
		var css = document.querySelector('link[href*="douce-paresse-kit/assets/css/frontend.css"]');
		var ver = function (u) { var m = /[?&]ver=([^&]+)/.exec(u || ''); return m ? m[1] : '?'; };

		if (kit) {
			add('ok', G, 'Kit script', 'Ran ' + kit.runs + ' time(s)' + (js ? ', version ' + ver(js.src) : ' (combined into another file)') + '.');
			if (js && ver(js.src) !== D.version) { add('error', G, 'Kit script version', 'Page loads frontend.js ' + ver(js.src) + ' but the plugin is ' + D.version + ': an old cached copy. Purge all caches.'); }
		} else if (js) {
			add('error', G, 'Kit script', 'frontend.js is in the page but did NOT run' + (js.type && !/javascript|module/.test(js.type) ? ' (type="' + js.type + '": delayed by an optimisation plugin)' : '') + '. Without it the header cannot be fixed by the kit.', js.outerHTML.slice(0, 300));
		} else if (delayed) {
			add('error', G, 'Kit script', 'frontend.js is DELAYED by an optimisation plugin (runs only after the visitor moves/scrolls). Exclude “douce-paresse-kit” from “Delay JavaScript”.', delayed.outerHTML.slice(0, 300));
		} else {
			add('error', G, 'Kit script', 'frontend.js is not in the page (removed, or delayed/combined by an optimisation plugin).');
		}
		if (js && js.type && !/^(text\/javascript|module|application\/javascript)$/.test(js.type)) {
			add('warn', G, 'Kit script type', 'type="' + js.type + '": another tool (e.g. Cloudflare Rocket Loader) rewrites the script and runs it late.');
		}

		var test = document.createElement('div');
		test.className = 'dpk';
		test.style.display = 'none';
		document.body.appendChild(test);
		var cssOk = !!getComputedStyle(test).getPropertyValue('--dpk-navy').trim();
		test.remove();
		if (!cssOk) {
			add('error', G, 'Kit CSS', 'frontend.css is not applied on this page' + (css ? ' (link present: ' + css.href + ')' : ' (no link found; removed or “unused CSS” removal)') + '.');
		} else {
			add('ok', G, 'Kit CSS', 'Applied' + (css ? ', version ' + ver(css.href) : ' (combined into another file)') + '.');
			if (css && ver(css.href) !== D.version) { add('error', G, 'Kit CSS version', 'Page loads frontend.css ' + ver(css.href) + ' but the plugin is ' + D.version + ': old cached copy. Purge all caches.'); }
		}
		if (!window.DPK) { add('warn', G, 'Kit settings', 'window.DPK is missing (forms cannot send).'); }
	}

	function otherHeaders(root) {
		var list = [];
		var all = document.querySelectorAll('header, .site-header, #masthead, .elementor-location-header, [data-elementor-type="header"], .elementor-sticky, .e-con, .elementor-section');
		for (var i = 0; i < all.length; i++) {
			var el = all[i];
			if (root && (root.contains(el) || el.contains(root))) { continue; }
			var r = el.getBoundingClientRect();
			var cs = getComputedStyle(el);
			if (r.height > 0 && r.top < 140 + (window.scrollY || 0) * 0 && r.bottom > 0 && cs.visibility !== 'hidden' && cs.display !== 'none' && (el.matches('header, .site-header, #masthead, [data-elementor-type="header"]') || cs.position === 'fixed' || cs.position === 'sticky')) {
				list.push(desc(el) + ' (' + cs.position + ', ' + Math.round(r.height) + 'px high)');
			}
		}
		return list;
	}

	function checkHeaderStatic() {
		var G = 'Header';
		var roots = document.querySelectorAll('[data-dpk-header]');
		var others = otherHeaders(roots[0]);
		add('info', G, 'Theme Builder header', 'Kit header template: #' + (D.header && D.header.template_id) + ' (' + (D.header && D.header.status) + '), conditions: ' + ((D.header && D.header.conditions || []).join(', ') || 'none'));
		if (!roots.length) {
			add('error', G, 'DP Header widget', 'Not on this page. The header you see is another one' + (others.length ? ': ' + others.join(' | ') : '') + '. Check Templates → Theme Builder → Header: the kit header must be published with “Entire Site”, other headers unpublished.');
			return null;
		}
		if (roots.length > 1) { add('warn', G, 'DP Header widget', roots.length + ' DP Header widgets on this page (only one expected).'); }
		var root = roots[0];
		var bar = root.querySelector('.dpk-header__bar');
		if (!root.classList.contains('dpk-header--fixed')) {
			add('error', G, 'Fixed on scroll', 'OFF in the widget. Edit the header → DP Header → Content → “Fixed on scroll” → on → Update.');
		} else {
			add('ok', G, 'Fixed on scroll', 'On in the widget.');
		}
		if (others.length) { add('warn', G, 'Other headers', 'Other header-like elements at the top of the page (may hide or duplicate the kit header).', others.join('\n')); }

		var portal = root.closest('.dpk-header-portal');
		var state = window.__dpkKit || {};
		if (portal) {
			add('ok', G, 'Lifted under <body>', 'Yes (' + desc(portal) + ').');
		} else if (state.editor) {
			add('info', G, 'Lifted under <body>', 'No: editor/preview mode detected (the widget stays in place there).');
		} else if (root.classList.contains('dpk-header--fixed')) {
			add('warn', G, 'Lifted under <body>', 'No. The kit script ' + (window.__dpkKit ? 'ran but did not move it' : 'did not run') + '.', 'parent chain: ' + chain(root).join(' > '));
		}

		// Snapshot taken before the kit script changed anything.
		if (probe.snapshot) {
			var bad = [];
			probe.snapshot.chain.forEach(function (o, i) {
				if (!i) { return; }
				var why = [];
				['transform', 'filter', 'backdropFilter', 'perspective'].forEach(function (p) { if (o[p] && o[p] !== 'none') { why.push(kebab(p) + ': ' + o[p]); } });
				if (/transform|filter|perspective/.test(o.willChange)) { why.push('will-change: ' + o.willChange); }
				if (o.contain && /paint|layout|strict|content/.test(o.contain)) { why.push('contain: ' + o.contain); }
				if (o.containerType && o.containerType !== 'normal') { why.push('container-type: ' + o.containerType); }
				if (o.animationName && o.animationName !== 'none') { why.push('animation: ' + o.animationName); }
				if (o.position !== 'static' && o.zIndex !== 'auto') { why.push('z-index: ' + o.zIndex); }
				if (parseFloat(o.opacity) < 1) { why.push('opacity: ' + o.opacity); }
				if (/"(_?animation|motion_fx|sticky)/.test(o.ds)) { why.push('Elementor settings: ' + o.ds.slice(0, 120)); }
				if (why.length) { bad.push(o.el + ' → ' + why.join('; ')); }
			});
			// Computed values above are already neutralised by the kit CSS: also list the site's own rules.
			for (var a = root.parentElement; a; a = a.parentElement) {
				var own = rulesFor(a, BREAKERS.concat(STACKING, ['animationName', 'animation'])).filter(function (l) { return !/dpk-header--fixed|^\(/.test(l) && !/style="" attribute/.test(l) && !/: var\(--[a-z-]+\)\s+← [^\n]*\[plugins\/elementor(-pro)?\//.test(l); });
				if (own.length) { bad.push(desc(a) + ' (site CSS):\n      ' + own.join('\n      ')); }
			}
			add(bad.length ? 'warn' : 'ok', G, 'Wrappers around the header (site settings)', bad.length ? bad.length + ' wrapper(s) carry CSS/settings that break a fixed header on their own. The kit neutralises them; remove them if anything still looks wrong.' : 'Nothing in the wrappers breaks position:fixed.', bad.join('\n'));
		}

		if (!bar) { add('error', G, 'Header bar', 'The .dpk-header__bar element is missing.'); return null; }
		var cs = getComputedStyle(bar);
		var detail = rulesFor(bar, ['position', 'top', 'transform', 'zIndex', 'display', 'visibility', 'opacity']);
		add(cs.position === 'fixed' ? 'ok' : 'error', G, 'Bar CSS position', 'position: ' + cs.position + ', top: ' + cs.top + ', z-index: ' + cs.zIndex + (cs.position === 'fixed' ? '' : ' → a stylesheet overrides the kit (see details).'), detail.join('\n'));

		var current = [];
		for (var el = bar.parentElement; el; el = el.parentElement) {
			var b = breaksFixed(el);
			if (b.length) { current.push(desc(el) + ' → ' + b.join('; ') + '\n      rules: ' + rulesFor(el, BREAKERS).join('\n      ')); mark(el); }
		}
		add(current.length ? 'error' : 'ok', G, 'Wrappers around the header (now)', current.length ? 'These still make position:fixed scroll with the page:' : 'None: nothing above the header can break position:fixed.', current.join('\n'));
		return { root: root, bar: bar };
	}

	function chain(el) {
		var out = [];
		for (; el && out.length < 12; el = el.parentElement) { out.push(desc(el)); }
		return out;
	}

	function findScroller() {
		var best = null;
		var all = document.querySelectorAll('body *');
		for (var i = 0; i < all.length; i++) {
			var el = all[i];
			if (el.scrollHeight <= el.clientHeight + 50 || el.clientHeight < window.innerHeight * 0.6) { continue; }
			var oy = getComputedStyle(el).overflowY;
			if (oy === 'auto' || oy === 'scroll' || oy === 'overlay') { best = el; break; }
		}
		return best;
	}

	function scrollTest(h) {
		var G = 'Header';
		if (!h) { return Promise.resolve(); }
		var bar = h.bar;
		var html = document.documentElement;
		var se = document.scrollingElement || html;
		var startY = window.scrollY;
		var prevBehavior = html.style.scrollBehavior;
		html.style.scrollBehavior = 'auto';
		if (panel) { panel.style.visibility = 'hidden'; }
		var maxY = se.scrollHeight - window.innerHeight;
		var scroller = null;
		var target = Math.min(900, Math.max(0, maxY));

		function restore() {
			if (scroller) { scroller.scrollTop = 0; }
			window.scrollTo(0, startY);
			html.style.scrollBehavior = prevBehavior;
			if (panel) { panel.style.visibility = ''; }
		}

		if (maxY < 150) {
			scroller = findScroller();
			if (!scroller) {
				add('info', G, 'Scroll test', 'Page too short to scroll; nothing to test.');
				restore();
				return Promise.resolve();
			}
			add('warn', G, 'Page scroll container', 'The window does not scroll: the page scrolls inside ' + desc(scroller) + ' (theme/plugin CSS). Fixed elements still work, but anchors, scroll effects and some scripts may not.', rulesFor(scroller, ['overflow', 'overflowY', 'height']).join('\n'));
			target = Math.min(900, scroller.scrollHeight - scroller.clientHeight);
		}
		var before = bar.getBoundingClientRect().top;
		if (scroller) { scroller.scrollTop = target; } else { window.scrollTo(0, target); }

		return nextFrame().then(function () {
			var moved = scroller ? scroller.scrollTop : window.scrollY;
			var r = bar.getBoundingClientRect();
			var cs = getComputedStyle(bar);
			var expected = parseFloat(cs.top) || 0;
			var stuck = Math.abs(r.top - expected) <= 2 && cs.display !== 'none';
			var visibleNav = Array.prototype.filter.call(bar.querySelectorAll('nav'), function (n) { return getComputedStyle(n).display !== 'none'; }).map(desc).join(', ');
			if (stuck) {
				add('ok', G, 'Scroll test', 'Scrolled ' + Math.round(moved) + 'px: the header stays at the top (top ' + Math.round(r.top) + 'px). Visible bar: ' + visibleNav + '.');
			} else {
				var cb = [];
				for (var el = bar.parentElement; el; el = el.parentElement) {
					var b = breaksFixed(el);
					if (b.length) { cb.push(desc(el) + ' → ' + b.join('; ')); }
				}
				add('error', G, 'Scroll test', 'Scrolled ' + Math.round(moved) + 'px: the header MOVED (top went from ' + Math.round(before) + 'px to ' + Math.round(r.top) + 'px). It is not fixed to the screen.' + (cb.length ? ' Cause: ' + cb[0] : ''), cb.join('\n'));
			}
			// Is it on top of the content?
			var y = Math.round(Math.max(1, r.top + r.height / 2));
			var covers = [];
			[0.08, 0.5, 0.92].forEach(function (fx) {
				var x = Math.round(r.left + r.width * fx);
				var hit = document.elementFromPoint(x, y);
				if (hit && !bar.contains(hit) && covers.indexOf(hit) < 0) { covers.push(hit); }
			});
			if (stuck && covers.length) {
				var info = covers.map(function (c) {
					var why = [];
					for (var el = c; el && el !== document.body; el = el.parentElement) {
						var s = stacking(el);
						if (s.length) { why.push(desc(el) + ' (' + s.join(', ') + ')'); }
					}
					return desc(c) + (why.length ? '\n      stacking: ' + why.join(' > ') : '');
				});
				var hz = [];
				for (var e2 = bar; e2 && e2 !== document.body; e2 = e2.parentElement) {
					var s2 = stacking(e2);
					if (s2.length) { hz.push(desc(e2) + ' (' + s2.join(', ') + ')'); }
				}
				add('error', G, 'Header covered', 'The header stays in place but page content is drawn OVER it (it looks like it scrolled away).', 'covering: ' + info.join('\n') + '\nheader stacking: ' + (hz.join(' > ') || 'z-index ' + getComputedStyle(bar).zIndex));
				covers.forEach(mark);
			} else if (stuck) {
				add('ok', G, 'Header on top', 'Nothing covers the header while scrolling.');
			}
			restore();
		});
	}

	function checkPage() {
		var G = 'Page';
		var widgets = document.querySelectorAll('.dpk');
		add('info', G, 'Kit widgets', widgets.length + ' on this page. Viewport ' + window.innerWidth + '×' + window.innerHeight + ' @' + (window.devicePixelRatio || 1) + 'x.');

		// Fonts
		var fams = {};
		if (document.fonts && document.fonts.forEach) {
			document.fonts.forEach(function (f) {
				var fam = f.family.replace(/["']/g, '');
				if (/Jost|Cormorant/i.test(fam)) { fams[fam + ' ' + f.weight + (f.style === 'italic' ? 'i' : '')] = f.status; }
			});
		}
		var failed = Object.keys(fams).filter(function (k) { return fams[k] === 'error'; });
		var loaded = Object.keys(fams).filter(function (k) { return fams[k] === 'loaded'; });
		add(failed.length ? 'error' : (loaded.length ? 'ok' : 'warn'), G, 'Fonts', failed.length ? 'Failed to load: ' + failed.join(', ') : (loaded.length ? 'Loaded: ' + loaded.join(', ') : 'Kit fonts not loaded yet / not declared.'));
		var unstyled = [];
		for (var i = 0; i < widgets.length && unstyled.length < 6; i++) {
			if (!/Jost|Cormorant/i.test(getComputedStyle(widgets[i]).fontFamily)) { unstyled.push(desc(widgets[i]) + ' font-family: ' + getComputedStyle(widgets[i]).fontFamily); }
		}
		if (unstyled.length) { add('warn', G, 'Typography overridden', 'Some kit sections do not use the kit fonts (theme or Elementor Site Settings CSS wins).', unstyled.join('\n')); }

		// Images
		var broken = [];
		var imgs = document.querySelectorAll('.dpk img');
		for (var j = 0; j < imgs.length; j++) {
			if (imgs[j].complete && imgs[j].naturalWidth === 0 && imgs[j].getAttribute('loading') !== 'lazy') { broken.push(imgs[j].currentSrc || imgs[j].src); }
		}
		add(broken.length ? 'error' : 'ok', G, 'Images', broken.length ? broken.length + ' broken image(s).' : imgs.length + ' image(s), none broken.', broken.join('\n'));

		// Horizontal overflow
		var vw = document.documentElement.clientWidth;
		if (document.documentElement.scrollWidth > vw + 1) {
			var wide = [];
			var all = document.querySelectorAll('body *');
			for (var k = 0; k < all.length && wide.length < 8; k++) {
				var r = all[k].getBoundingClientRect();
				if (r.right > vw + 1 && r.width > 0 && !(all[k].parentElement && all[k].parentElement.getBoundingClientRect().right > vw + 1) && getComputedStyle(all[k]).position !== 'fixed') { wide.push(desc(all[k]) + ' right edge ' + Math.round(r.right) + 'px'); }
			}
			add('warn', G, 'Horizontal scroll', 'The page is ' + document.documentElement.scrollWidth + 'px wide for a ' + vw + 'px screen.', wide.join('\n'));
		} else {
			add('ok', G, 'Horizontal scroll', 'None.');
		}

		// Links
		var plain = document.querySelectorAll('.dpk a[href*="page_id="], .dpk a[href*="?p="]');
		var empty = document.querySelectorAll('.dpk-header a[href="#"], .dpk-footer a[href="#"], .dpk-header a:not([href]), .dpk-footer a:not([href])');
		if (plain.length) { add('warn', G, 'Plain links', plain.length + ' link(s) with ?page_id= (old cache or typed by hand).'); }
		if (empty.length) { add('warn', G, 'Empty links', empty.length + ' header/footer link(s) point to “#”.', Array.prototype.map.call(empty, function (a) { return a.textContent.trim(); }).join(', ')); }
		var menus = document.querySelectorAll('.dpk-header__desktop');
		for (var m = 0; m < menus.length; m++) {
			var cur = menus[m].querySelectorAll('a[aria-current="page"]');
			if (cur.length > 1) { add('error', G, 'Active link', cur.length + ' menu links marked as current page (all underlined).'); }
		}
		var under = [];
		document.querySelectorAll('.dpk-header__menu a').forEach(function (a) {
			var s = getComputedStyle(a);
			if (s.textDecorationLine !== 'none') { under.push(a.textContent.trim() + ' (text-decoration ' + s.textDecorationLine + ')'); }
		});
		if (under.length) { add('error', G, 'Menu underline', 'Menu links are underlined by another stylesheet.', under.join('\n') + '\n' + rulesFor(document.querySelector('.dpk-header__menu a'), ['textDecoration', 'textDecorationLine']).join('\n')); }

		// Brand protected from automatic translation
		var terms = (window.DPK && window.DPK.brand) || [];
		if (terms.length) {
			var bare = [];
			var re = new RegExp('(^|[^\\p{L}\\p{N}])(' + terms.map(function (t) { return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s+'); }).join('|') + ')(?![\\p{L}\\p{N}])', 'iu');
			var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
			var node;
			while ((node = walker.nextNode()) && bare.length < 10) {
				var pe = node.parentElement;
				if (!pe || !re.test(node.nodeValue) || (function (el) { for (; el && el !== document.documentElement; el = el.parentElement) { if (el.matches('script,style,noscript,textarea,.notranslate,[translate="no"],.dpkdbg,#wpadminbar')) { return true; } } return false; })(pe)) { continue; }
				bare.push(desc(pe) + ': “' + node.nodeValue.trim().slice(0, 60) + '”');
			}
			add(bare.length ? 'warn' : 'ok', G, 'Brand protection', bare.length ? bare.length + ' occurrence(s) of ' + terms.join(', ') + ' can still be translated (page cached before the update, or text added by a script).' : terms.join(', ') + ' is protected from automatic translation everywhere on this page.', bare.join('\n'));
		}
		var gtFloat = document.getElementById('gt_float_wrapper');
		var kitLang = document.querySelector('.dpk-lang');
		if (kitLang) {
			var gtMode = typeof window.doGTranslate === 'function' ? 'GTranslate widget on the page (free plan)'
				: (window.gtranslateSettings ? 'GTranslate settings found (' + (((Object.values ? Object.values(window.gtranslateSettings) : []).filter(function (v) { return v && v.url_structure; })[0] || window.gtranslateSettings).url_structure || 'none') + ')'
				: 'GTranslate prints nothing on this page (floating selector off / no widget): the kit loads Google Translate itself');
			var gtc = (document.cookie.match(/(?:^|;)\s*googtrans=[^;]*/g) || []).map(function (c) { return c.replace(/^[;\s]+/, ''); });
			var choice = (document.cookie.match(/(?:^|;)\s*dpk_lang=([^;]*)/) || [])[1] || 'none';
			var pressed = (document.querySelector('.dpk-lang [aria-pressed="true"]') || {}).textContent || '?';
			var shown = /translated-(ltr|rtl)/.test(document.documentElement.className) ? 'translated (html lang=' + document.documentElement.getAttribute('lang') + ')' : 'original';
			add('info', G, 'Language state', 'switcher shows ' + pressed + ' · page ' + shown + ' · visitor choice ' + choice + ' · Google cookies on this page: ' + (gtc.length ? gtc.join(', ') : 'none'), 'URL path: ' + location.pathname + '\nGoogle Translate script: ' + (document.querySelector('script[src*="translate.google"]') ? 'loaded' : 'not loaded') + '\nGTranslate on page: ' + (window.gtranslateSettings ? 'yes' : 'no'));
			add('ok', G, 'Language switcher', 'FR | EN switcher present · ' + gtMode + (window.google && window.google.translate ? ' · Google Translate loaded' : '') + (gtFloat ? (getComputedStyle(gtFloat).display === 'none' ? ' · floating button hidden.' : ' · floating button still visible.') : '.'));
		}

		// Duplicate ids
		var ids = {};
		var dups = [];
		document.querySelectorAll('[id]').forEach(function (el) { if (ids[el.id] && dups.indexOf(el.id) < 0) { dups.push(el.id); } ids[el.id] = 1; });
		if (dups.length) { add('warn', G, 'Duplicate ids', dups.length + ' id(s) used twice.', dups.slice(0, 15).join(', ')); }

		// Elementor
		var cfg = window.elementorFrontendConfig;
		add(window.elementorFrontend ? 'ok' : 'warn', G, 'Elementor frontend', window.elementorFrontend ? 'Loaded' + (cfg && cfg.version ? ' (' + cfg.version + ')' : '') + '.' : 'Not loaded on this page.');

		// JS errors
		var errs = (probe.errors || []).map(function (e) { return e.type + ': ' + e.msg + (e.src ? ' @ ' + e.src : ''); });
		add(errs.length ? 'error' : 'ok', G, 'JavaScript errors', errs.length ? errs.length + ' error(s) while loading the page.' : 'None.', errs.join('\n'));
	}

	function checkForms() {
		var G = 'Forms';
		var forms = document.querySelectorAll('.dpk-form');
		if (!forms.length) { return Promise.resolve(); }
		var ts = window.DPK && window.DPK.turnstile;
		var boxes = document.querySelectorAll('[data-dpk-turnstile]');
		if (ts) {
			add(boxes.length ? 'ok' : 'error', G, 'Turnstile', boxes.length ? 'Enabled, ' + boxes.length + ' box(es)' + (window.turnstile ? ', Cloudflare script loaded.' : ', Cloudflare script NOT loaded yet (blocked?).') : 'Enabled but no Turnstile box in the form.');
		}
		if (!window.DPK || !window.DPK.ajax) { add('error', G, 'Form endpoint', 'window.DPK.ajax missing: forms cannot send.'); return Promise.resolve(); }
		var body = new FormData();
		body.append('action', 'dpk_form_token');
		return fetch(window.DPK.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.text().then(function (t) { return { status: r.status, text: t }; }); })
			.then(function (res) {
				var json = null;
				try { json = JSON.parse(res.text); } catch (e) { json = null; }
				var ok = json && json.success && json.data && json.data.token;
				add(ok ? 'ok' : 'error', G, 'Anti-spam token', ok ? forms.length + ' form(s); token endpoint works.' : 'admin-ajax.php answered ' + res.status + ' without a token: forms will fail (security plugin/firewall blocking admin-ajax?).', ok ? '' : res.text.slice(0, 300));
			})
			.catch(function (e) { add('error', G, 'Anti-spam token', 'Request failed: ' + e.message); });
	}

	/* ------------------------------------------------------------------ */

	function report() {
		var lines = ['DOUCE PARESSE KIT — BROWSER REPORT', location.href, new Date().toISOString(), navigator.userAgent, ''];
		lines.push('SERVER');
		(D.server || []).forEach(function (l) { lines.push('  ' + l); });
		lines.push('');
		var group = '';
		results.forEach(function (r) {
			if (r.group !== group) { group = r.group; lines.push(group.toUpperCase()); }
			lines.push('  [' + r.state.toUpperCase() + '] ' + r.label + ': ' + r.msg);
			if (r.details) { lines.push('      ' + r.details.replace(/\n/g, '\n      ')); }
		});
		return lines.join('\n');
	}

	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) { e.className = cls; }
		if (text !== undefined) { e.textContent = text; }
		return e;
	}

	function render() {
		if (panel) { panel.remove(); }
		panel = el('div', 'dpkdbg');
		panel.setAttribute('role', 'dialog');
		panel.setAttribute('aria-label', 'Douce Paresse debug');
		var counts = { error: 0, warn: 0 };
		results.forEach(function (r) { if (counts[r.state] !== undefined) { counts[r.state]++; } });

		var head = el('div', 'dpkdbg__head');
		head.appendChild(el('strong', '', 'DP Debug ' + (D.version || '')));
		head.appendChild(el('span', 'dpkdbg__sum' + (counts.error ? ' is-bad' : ''), counts.error + ' problem(s) · ' + counts.warn + ' warning(s)'));
		var tools = el('div', 'dpkdbg__tools');
		function btn(label, fn) { var b = el('button', 'dpkdbg__btn', label); b.type = 'button'; b.addEventListener('click', fn); tools.appendChild(b); return b; }
		var copy = btn('Copy report', function () {
			var text = report();
			var done = function () { copy.textContent = 'Copied ✓'; setTimeout(function () { copy.textContent = 'Copy report'; }, 2000); };
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(done, function () { fallback(text); done(); });
			} else { fallback(text); done(); }
		});
		btn('Re-run', run);
		btn(marked.length ? 'Highlight (' + marked.length + ')' : 'Highlight', function () {
			marked.forEach(function (m) { m.classList.toggle('dpkdbg-mark'); });
		});
		btn('–', function () { panel.classList.toggle('is-min'); });
		btn('×', function () { panel.remove(); marked.forEach(function (m) { m.classList.remove('dpkdbg-mark'); }); });
		head.appendChild(tools);
		panel.appendChild(head);

		var list = el('div', 'dpkdbg__list');
		var group = '';
		var order = { error: 0, warn: 1, ok: 2, info: 3 };
		results.slice().sort(function (a, b) { return a.group === b.group ? order[a.state] - order[b.state] : 0; }).forEach(function (r) {
			if (r.group !== group) { group = r.group; list.appendChild(el('div', 'dpkdbg__group', group)); }
			var row = el('div', 'dpkdbg__row is-' + r.state);
			row.appendChild(el('span', 'dpkdbg__icon', { ok: '✓', warn: '!', error: '✗', info: 'i' }[r.state]));
			var txt = el('div', 'dpkdbg__txt');
			txt.appendChild(el('b', '', r.label + ' '));
			txt.appendChild(document.createTextNode(r.msg));
			if (r.details) {
				var det = el('details');
				det.appendChild(el('summary', '', 'details'));
				det.appendChild(el('pre', '', r.details));
				txt.appendChild(det);
			}
			row.appendChild(txt);
			list.appendChild(row);
		});
		panel.appendChild(list);
		document.body.appendChild(panel);

		if (window.console) {
			console.groupCollapsed('%cDP Debug: ' + counts.error + ' problem(s), ' + counts.warn + ' warning(s)', 'color:#16233F;font-weight:bold');
			console.log(report());
			console.groupEnd();
		}
	}

	function fallback(text) {
		var t = document.createElement('textarea');
		t.value = text;
		t.style.cssText = 'position:fixed;left:-9999px;top:0';
		document.body.appendChild(t);
		t.select();
		try { document.execCommand('copy'); } catch (e) { /* ignore */ }
		t.remove();
	}

	function run() {
		results = [];
		marked.forEach(function (m) { m.classList.remove('dpkdbg-mark'); });
		marked = [];
		var h;
		try { checkAssets(); } catch (e) { add('error', 'Debug', 'Asset check crashed', e.message); }
		try { h = checkHeaderStatic(); } catch (e) { add('error', 'Debug', 'Header check crashed', e.message); }
		return scrollTest(h)
			.catch(function (e) { add('error', 'Debug', 'Scroll test crashed', e.message); })
			.then(function () { try { checkPage(); } catch (e) { add('error', 'Debug', 'Page check crashed', e.message); } })
			.then(checkForms)
			.then(render, render);
	}

	function start() {
		var go = function () { setTimeout(run, 1500); }; // after entrance animations and fonts
		if (document.readyState === 'complete') { go(); } else { window.addEventListener('load', go); }
	}
	start();
})();
