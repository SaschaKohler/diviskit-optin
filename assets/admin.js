/**
 * Diviskit Optin — admin UI.
 * - Provider field toggle on the settings tab
 * - Interests repeater (+/- rows)
 * - Live preview for the mail template editor (placeholder values come
 *   from DKOPT_ADMIN.vars — the same map the mailer uses)
 */
(function () {
  'use strict';

  /* --- provider field toggle ------------------------------------- */
  var sel = document.getElementById('dkopt-provider');
  if (sel) {
    var toggle = function () {
      document.querySelectorAll('.dkopt-pfield').forEach(function (row) {
        row.style.display = row.dataset.provider === sel.value ? '' : 'none';
      });
    };
    sel.addEventListener('change', toggle);
    toggle();
  }

  /* --- interests repeater ----------------------------------------- */
  var intWrap = document.getElementById('dkopt-interests');
  var intAdd  = document.getElementById('dkopt-int-add');
  if (intWrap && intAdd) {
    var optName = intWrap.dataset.option;
    var idx     = intWrap.querySelectorAll('.dkopt-int-row').length;

    function bindDel(row) {
      row.querySelector('.dkopt-int-del').addEventListener('click', function () {
        row.remove();
      });
    }
    intWrap.querySelectorAll('.dkopt-int-row').forEach(bindDel);

    intAdd.addEventListener('click', function () {
      var row = document.createElement('div');
      row.className = 'dkopt-int-row';
      row.innerHTML =
        '<input type="hidden" name="' + optName + '[interests][' + idx + '][slug]" value="">' +
        '<input type="text" name="' + optName + '[interests][' + idx + '][label]" value="" placeholder="Label" class="regular-text">' +
        ' <input type="text" name="' + optName + '[interests][' + idx + '][group]" value="" placeholder="ML-Group-ID (optional)" class="regular-text">' +
        ' <button type="button" class="button dkopt-int-del" title="Entfernen">−</button>';
      bindDel(row);
      intWrap.appendChild(row);
      idx++;
      row.querySelector('input[type="text"]').focus();
    });
  }

  /* --- template editor live preview ------------------------------- */
  var ta    = document.getElementById('dkopt-tpl-html');
  var frame = document.getElementById('dkopt-tpl-preview');
  if (!ta || !frame) return;

  var vars = (window.DKOPT_ADMIN && DKOPT_ADMIN.vars) || {};

  function render() {
    var html = ta.value;
    Object.keys(vars).forEach(function (key) {
      html = html.split(key).join(vars[key]);
    });
    frame.srcdoc = html;
  }

  var t;
  ta.addEventListener('input', function () {
    clearTimeout(t);
    t = setTimeout(render, 300);
  });
  render();
})();
