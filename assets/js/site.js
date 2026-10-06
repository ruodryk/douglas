/* Douglas Souza Arquitetura — interações do site */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Banner ---------- */
  var slider = document.querySelector('[data-slider]');
  if (slider) {
    var slides = slider.querySelectorAll('[data-slide]');
    var tabs = slider.querySelectorAll('[data-slider-tab]');
    var pos = slider.querySelector('[data-slider-pos]');
    var tag = slider.querySelector('[data-slider-tag]');
    var title = slider.querySelector('[data-slider-title]');
    var idx = 0, timer = null;
    var show = function (i) {
      idx = (i + slides.length) % slides.length;
      slides.forEach(function (s, k) { s.classList.toggle('is-active', k === idx); });
      tabs.forEach(function (t, k) { t.setAttribute('aria-selected', k === idx ? 'true' : 'false'); });
      pos.textContent = String(idx + 1).padStart(2, '0');
      tag.textContent = slides[idx].dataset.tag;
      title.textContent = slides[idx].dataset.title;
    };
    var stop = function () { if (timer) { clearInterval(timer); timer = null; } };
    slider.querySelector('[data-slider-prev]').addEventListener('click', function () { stop(); show(idx - 1); });
    slider.querySelector('[data-slider-next]').addEventListener('click', function () { stop(); show(idx + 1); });
    tabs.forEach(function (t) { t.addEventListener('click', function () { stop(); show(+t.dataset.sliderTab); }); });
    if (slides.length > 1 && !reduce) { timer = setInterval(function () { show(idx + 1); }, 5000); }
  }

  /* ---------- Abas de categorias ---------- */
  var tablist = document.querySelector('[data-tabs]');
  if (tablist) {
    var catTabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));
    var select = function (tab, focus) {
      catTabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) { panel.hidden = !on; }
      });
      if (focus) { tab.focus(); }
    };
    // Abre a categoria indicada no endereço (ex.: index.php#cat-residencial — links do rodapé e do mapa do site)
    var fromHash = function () {
      var h = decodeURIComponent(location.hash || '').slice(1);
      if (h.indexOf('cat-') !== 0) { return; }
      var tab = catTabs.filter(function (t) { return t.getAttribute('aria-controls') === h; })[0];
      if (tab) {
        select(tab);
        var sec = document.getElementById('projetos');
        if (sec) { sec.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' }); }
      }
    };
    window.addEventListener('hashchange', fromHash);
    fromHash();
    catTabs.forEach(function (t, i) {
      t.tabIndex = t.getAttribute('aria-selected') === 'true' ? 0 : -1;
      t.addEventListener('click', function () { select(t); });
      t.addEventListener('keydown', function (ev) {
        var d = ev.key === 'ArrowRight' ? 1 : ev.key === 'ArrowLeft' ? -1 : 0;
        if (d) { ev.preventDefault(); select(catTabs[(i + d + catTabs.length) % catTabs.length], true); }
      });
    });
  }

  /* ---------- Visualizador (lightbox) ---------- */
  var lb = document.querySelector('[data-lightbox]');
  if (lb) {
    var img = lb.querySelector('[data-lb-img]');
    var cap = lb.querySelector('[data-lb-cap]');
    var lpos = lb.querySelector('[data-lb-pos]');
    var list = [], cur = 0, opener = null;
    var render = function () {
      var it = list[cur];
      img.src = it.dataset.src;
      img.alt = it.dataset.caption || '';
      cap.textContent = it.dataset.caption || '';
      lpos.textContent = list.length > 1 ? (cur + 1) + ' / ' + list.length : '';
    };
    var open = function (btn) {
      list = Array.prototype.slice.call(document.querySelectorAll('[data-lb="' + btn.dataset.lb + '"]'));
      cur = list.indexOf(btn);
      opener = btn;
      render();
      lb.hidden = false;
      document.body.classList.add('no-scroll');
      lb.querySelector('[data-lb-close]').focus();
    };
    var close = function () {
      lb.hidden = true;
      document.body.classList.remove('no-scroll');
      img.src = '';
      if (opener) { opener.focus(); }
    };
    var step = function (d) { cur = (cur + d + list.length) % list.length; render(); };
    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-lb]');
      if (btn) { open(btn); }
    });
    lb.querySelector('[data-lb-prev]').addEventListener('click', function () { step(-1); });
    lb.querySelector('[data-lb-next]').addEventListener('click', function () { step(1); });
    lb.querySelector('[data-lb-close]').addEventListener('click', close);
    lb.addEventListener('click', function (ev) { if (ev.target === lb) { close(); } });
    document.addEventListener('keydown', function (ev) {
      if (lb.hidden) { return; }
      if (ev.key === 'Escape') { close(); }
      else if (ev.key === 'ArrowRight') { step(1); }
      else if (ev.key === 'ArrowLeft') { step(-1); }
    });
  }
})();
