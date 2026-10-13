(function () {
	'use strict';

	var cfg = window.DPKAdmin;
	if (!cfg) { return; }
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

	var runBtn = $('#dpk-run');
	var logEl = $('.dpk-log');
	var bar = $('.dpk-progress');
	var fill = $('.dpk-progress__bar');

	function post(data) {
		var body = new FormData();
		body.append('nonce', cfg.nonce);
		Object.keys(data).forEach(function (k) {
			if (Array.isArray(data[k])) {
				data[k].forEach(function (v) { body.append(k + '[]', v); });
			} else {
				body.append(k, data[k]);
			}
		});
		return fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) {
					throw new Error((res && res.data && res.data.message) || 'Request failed');
				}
				return res.data;
			});
	}

	function log(lines) {
		(lines || []).forEach(function (line) {
			var li = document.createElement('li');
			li.textContent = line;
			logEl.appendChild(li);
		});
		logEl.scrollTop = logEl.scrollHeight;
	}

	function progress(done, total) {
		fill.style.width = Math.round((done / total) * 100) + '%';
	}

	var all = $('#dpk-all');
	if (all) {
		all.addEventListener('change', function () {
			$$('.dpk-item').forEach(function (c) { c.checked = all.checked; });
		});
	}

	if (runBtn) {
		runBtn.addEventListener('click', function () {
			var keys = $$('.dpk-item:checked').map(function (c) { return c.value; });
			var mode = ($('input[name="dpk-mode"]:checked') || {}).value || 'sync';
			var front = $('#dpk-front') && $('#dpk-front').checked ? '1' : '';
			if (!keys.length) { window.alert(cfg.i18n.nothing); return; }
			if (mode === 'reset' && !window.confirm(cfg.i18n.confirmReset)) { return; }

			runBtn.disabled = true;
			logEl.hidden = false;
			bar.hidden = false;
			logEl.innerHTML = '';
			var total = Math.ceil(cfg.images / 4) + keys.length + 2;
			var done = 0;
			var step = function () { done++; progress(done, total); };

			log([cfg.i18n.media]);
			var chain = (function media(offset) {
				return post({ action: 'dpk_import_step', step: 'media', offset: offset }).then(function (d) {
					log(d.log); step();
					return d.done ? null : media(d.next);
				});
			})(0);

			chain = chain.then(function () {
				return post({ action: 'dpk_import_step', step: 'prepare', keys: keys, front: front }).then(function (d) { log(d.log); step(); });
			});
			keys.forEach(function (key) {
				chain = chain.then(function () {
					return post({ action: 'dpk_import_step', step: 'item', key: key, mode: mode }).then(function (d) { log(d.log); step(); });
				});
			});
			chain.then(function () {
				return post({ action: 'dpk_import_step', step: 'finalize', mode: mode });
			}).then(function (d) {
				log(d.log); progress(1, 1);
				log([cfg.i18n.done]);
				setTimeout(function () { window.location.reload(); }, 2500);
			}).catch(function (err) {
				log([cfg.i18n.failed + ' ' + err.message]);
				runBtn.disabled = false;
			});
		});
	}

	$$('.dpk-restore').forEach(function (a) {
		a.addEventListener('click', function (e) {
			e.preventDefault();
			if (!window.confirm(cfg.i18n.restore)) { return; }
			post({ action: 'dpk_restore', key: a.getAttribute('data-key') })
				.then(function () { window.location.reload(); })
				.catch(function (err) { window.alert(err.message); });
		});
	});
})();
