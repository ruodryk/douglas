/* Painel: confirmações, pré-visualização e arrastar-e-soltar de fotos */
(function () {
  'use strict';

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.dataset.confirm)) { ev.preventDefault(); }
    });
  });

  var preview = function (input) {
    var root = input.closest('form') || document;
    var out = root.querySelector('[data-preview-out]');
    if (!out) { return; }
    out.innerHTML = '';
    Array.prototype.slice.call(input.files || []).slice(0, 40).forEach(function (file) {
      if (!/^image\//.test(file.type)) { return; }
      var img = document.createElement('img');
      img.alt = file.name;
      img.src = URL.createObjectURL(file);
      img.onload = function () { URL.revokeObjectURL(img.src); };
      out.appendChild(img);
    });
  };

  // Contador de caracteres para campos de SEO
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var out = document.createElement('p');
    out.className = 'help';
    el.insertAdjacentElement('afterend', out);
    var upd = function () { out.textContent = el.value.length + ' caracteres'; };
    el.addEventListener('input', upd);
    upd();
  });

  document.querySelectorAll('input[data-preview]').forEach(function (input) {
    input.addEventListener('change', function () { preview(input); });
  });

  document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
    var input = zone.querySelector('input[type=file]');
    if (!input) { return; }
    ['dragenter', 'dragover'].forEach(function (t) {
      zone.addEventListener(t, function (ev) { ev.preventDefault(); zone.classList.add('drag'); });
    });
    ['dragleave', 'drop'].forEach(function (t) {
      zone.addEventListener(t, function (ev) { ev.preventDefault(); zone.classList.remove('drag'); });
    });
    zone.addEventListener('drop', function (ev) {
      if (ev.dataTransfer && ev.dataTransfer.files.length) {
        input.files = ev.dataTransfer.files;
        preview(input);
      }
    });
  });
})();
