/* Just Move DFW – Elementor Template Kit front-end behaviour. */
(function () {
  'use strict';

  var cfg = window.jmkKit || { ajaxUrl: '', action: 'jmk_submit_lead', i18n: {} };
  var t = function (k, d) { return (cfg.i18n && cfg.i18n[k]) || d; };

  // Sticky header: make the Elementor container/section that holds the header sticky,
  // since the header itself is only as tall as its own wrapper.
  function initHeader(root) {
    var headers = root.querySelectorAll('[data-jmk-sticky]');
    Array.prototype.forEach.call(headers, function (h) {
      var host = h.closest('.elementor-top-section, .elementor > .e-con, .elementor-section-wrap > .e-con') ||
                 h.closest('.e-con, .elementor-section');
      if (host) { host.classList.add('jmk-sticky-host'); }
    });
  }

  function initMobileBar(root) {
    if (root.querySelector('[data-jmk-mbar]')) { document.body.classList.add('jmk-has-mbar'); }
  }

  function initForms(root) {
    var forms = root.querySelectorAll('form[data-jmk-form]');
    Array.prototype.forEach.call(forms, function (form) {
      if (form.dataset.jmkReady) { return; }
      form.dataset.jmkReady = '1';

      var err = form.querySelector('.err');
      var btn = form.querySelector('button[type="submit"]');
      var showErr = function (msg) {
        if (!err) { window.alert(msg); return; }
        err.textContent = msg;
        err.classList.add('on');
      };

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (err) { err.classList.remove('on'); }

        var data = new FormData(form);
        var name = String(data.get('name') || '').trim();
        var phone = String(data.get('phone') || '').trim();
        if (!name || !phone) { showErr(t('required', 'Please add your name and phone so we can send your quote.')); return; }

        // Inside the Elementor editor: just preview the success state.
        if (document.body.classList.contains('elementor-editor-active') || !cfg.ajaxUrl) {
          form.classList.add('sent');
          return;
        }

        data.append('action', cfg.action);
        data.append('source', form.dataset.source || 'home-hero-form');
        data.append('page_url', window.location.href);

        var label = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.textContent = t('sending', 'Sending…'); }

        fetch(cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (!res || !res.success) {
              throw new Error((res && res.data && res.data.message) || t('error', 'Something went wrong.'));
            }
            if (form.dataset.redirect) { window.location.href = form.dataset.redirect; return; }
            form.classList.add('sent');
          })
          .catch(function (ex) { showErr(ex.message || t('error', 'Something went wrong.')); })
          .then(function () { if (btn) { btn.disabled = false; btn.innerHTML = label; } });
      });
    });
  }

  function init(root) {
    root = root || document;
    initHeader(root);
    initMobileBar(root);
    initForms(root);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }

  // Re-run when Elementor (editor preview) re-renders one of our widgets.
  var hooked = false;
  function hookElementor() {
    if (hooked || !window.elementorFrontend || !window.elementorFrontend.hooks) { return; }
    hooked = true;
    window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
      var el = $scope && $scope[0];
      if (el && el.querySelector && el.querySelector('.jmk')) { init(el); }
    });
  }
  hookElementor();
  if (window.jQuery) { window.jQuery(window).on('elementor/frontend/init', hookElementor); }
  window.addEventListener('elementor/frontend/init', hookElementor);
})();
