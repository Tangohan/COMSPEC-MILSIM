/*
 * Page d'accueil publique (views/admin/organization/community_presentation.php) : les menus marqués data-bcs-visual
 * deviennent des cartes qui montrent le rendu de chaque choix. Le menu d'origine reste dans le formulaire : envoi,
 * récapitulatif des modifications et absence de script fonctionnent comme avant.
 * Textes en français : traduits à l'affichage par i18n_dom.js (lang/en/site_auto.php, js-bundle).
 */
(function () {
  'use strict';

  function art(kind, value) {
    var el = document.createElement('span');
    if (kind === 'layout') {
      el.className = 'bcs-vpick__art bcs-art-layout-' + value;
      el.innerHTML = '<i></i><i></i><i></i>';
    } else if (kind === 'submenu') {
      el.className = 'bcs-vpick__art bcs-art-submenu-' + value;
      el.innerHTML = '<i></i><i></i><i></i><i></i>';
    } else if (kind === 'accent') {
      el.className = 'bcs-vpick__swatch bcs-swatch-' + value;
    } else if (kind === 'alert') {
      el.className = 'bcs-vpick__art bcs-art-alert-' + value;
      el.style.height = '26px';
    } else {
      return null;
    }
    el.setAttribute('aria-hidden', 'true');
    return el;
  }

  function enhance(select) {
    var kind = select.getAttribute('data-bcs-visual');
    var labelEl = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;
    var group = document.createElement('div');
    group.className = 'bcs-vpick bcs-vpick--' + kind;
    group.setAttribute('role', 'radiogroup');
    if (labelEl) group.setAttribute('aria-label', labelEl.textContent.trim());
    var buttons = [];

    function update() {
      buttons.forEach(function (b) {
        var on = b.getAttribute('data-value') === select.value;
        b.setAttribute('aria-checked', on ? 'true' : 'false');
        b.tabIndex = on ? 0 : -1;
      });
    }
    function choose(value, focus) {
      if (select.value !== value) {
        select.value = value;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      update();
      if (focus) {
        buttons.forEach(function (b) { if (b.getAttribute('data-value') === value) b.focus(); });
      }
    }

    Array.prototype.forEach.call(select.options, function (o) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bcs-vpick__opt';
      b.setAttribute('role', 'radio');
      b.setAttribute('data-value', o.value);
      var picture = art(kind, o.value);
      if (picture) b.appendChild(picture);
      var name = document.createElement('span');
      name.className = 'bcs-vpick__name';
      name.textContent = o.textContent.trim();
      b.appendChild(name);
      var hintText = o.getAttribute('data-hint');
      if (hintText) {
        var hint = document.createElement('span');
        hint.className = 'bcs-vpick__hint';
        hint.textContent = hintText;
        b.appendChild(hint);
      }
      b.addEventListener('click', function () { choose(o.value, false); });
      buttons.push(b);
      group.appendChild(b);
    });

    group.addEventListener('keydown', function (e) {
      var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
      if (!step) return;
      e.preventDefault();
      var i = Array.prototype.findIndex.call(select.options, function (o) { return o.value === select.value; });
      var next = select.options[(i + step + select.options.length) % select.options.length];
      choose(next.value, true);
    });

    select.classList.add('bcs-vpick__native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.parentNode.insertBefore(group, select.nextSibling);
    select.addEventListener('change', update);
    update();
  }

  /** Alerte de la vitrine : rendu avec le titre et le texte saisis, dans la couleur choisie. */
  function alertRender() {
    var style = document.getElementById('f-hl-style');
    var title = document.getElementById('f-hl-title');
    var body = document.getElementById('f-hl-body');
    if (!style || !title || !body) return;
    var box = document.createElement('div');
    var label = document.createElement('span');
    label.className = 'bcs-render__label';
    label.textContent = 'Aperçu sur la page publique';
    var strong = document.createElement('strong');
    var text = document.createElement('p');
    box.appendChild(label);
    box.appendChild(strong);
    box.appendChild(text);
    var field = body.closest('.bcs-field');
    if (!field) return;
    field.parentNode.insertBefore(box, field.nextSibling);
    function sync() {
      var filled = title.value.trim() !== '' || body.value.trim() !== '';
      box.hidden = !filled;
      box.className = 'bcs-render bcs-art-alert-' + style.value;
      strong.textContent = title.value.trim();
      text.textContent = body.value.trim();
    }
    [style, title, body].forEach(function (el) {
      el.addEventListener('input', sync);
      el.addEventListener('change', sync);
    });
    sync();
  }

  Array.prototype.forEach.call(document.querySelectorAll('select[data-bcs-visual]'), enhance);
  alertRender();
})();
