/**
 * Skit Optin — admin UI.
 * - Provider field toggle on the settings tab
 * - Interests repeater (+/- rows)
 * - Live preview for the mail template editor (placeholder values come
 *   from SKIT_ADMIN.vars — the same map the mailer uses)
 */
(function () {
  'use strict';

  /* --- provider field toggle ------------------------------------- */
  var sel = document.getElementById('skit-provider');
  if (sel) {
    var toggle = function () {
      document.querySelectorAll('.skit-pfield').forEach(function (row) {
        row.style.display = row.dataset.provider === sel.value ? '' : 'none';
      });
    };
    sel.addEventListener('change', toggle);
    toggle();
  }

  /* --- interests repeater ----------------------------------------- */
  var intWrap = document.getElementById('skit-interests');
  var intAdd  = document.getElementById('skit-int-add');
  if (intWrap && intAdd) {
    var optName = intWrap.dataset.option;
    var idx     = intWrap.querySelectorAll('.skit-int-row').length;

    function bindDel(row) {
      row.querySelector('.skit-int-del').addEventListener('click', function () {
        row.remove();
      });
    }
    intWrap.querySelectorAll('.skit-int-row').forEach(bindDel);

    intAdd.addEventListener('click', function () {
      var row = document.createElement('div');
      row.className = 'skit-int-row';
      row.innerHTML =
        '<input type="hidden" name="' + optName + '[interests][' + idx + '][slug]" value="">' +
        '<input type="text" name="' + optName + '[interests][' + idx + '][label]" value="" placeholder="Label" class="regular-text">' +
        ' <input type="text" name="' + optName + '[interests][' + idx + '][group]" value="" placeholder="ML-Group-ID (optional)" class="regular-text">' +
        ' <button type="button" class="button skit-int-del" title="Entfernen">−</button>';
      bindDel(row);
      intWrap.appendChild(row);
      idx++;
      row.querySelector('input[type="text"]').focus();
    });
  }

  /* --- template editor live preview ------------------------------- */
  var ta    = document.getElementById('skit-tpl-html');
  var frame = document.getElementById('skit-tpl-preview');
  if (!ta || !frame) return;

  var vars = (window.SKIT_ADMIN && SKIT_ADMIN.vars) || {};

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
