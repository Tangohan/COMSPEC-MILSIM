/**
 * Combobox accessible (motif ARIA 1.2 « combobox » + listbox), amélioration progressive d’un <select>.
 *
 * <select data-coop-combobox
 *         data-source="remote|local"          remote : interroge data-endpoint ; local : filtre les <option>
 *         data-endpoint="/back-office/cooperation/api/tenants/search"
 *         data-mission-id="12"                ajouté à la requête si présent
 *         data-multiple-name="partner_tenant_ids[]"   sélection multiple (champs cachés générés)
 *         data-placeholder="Rechercher une unité…">
 *
 * Sans JavaScript (ou si la recherche échoue), le <select> d’origine reste affiché et fonctionnel.
 * Clavier : ↓/↑ parcourent, Entrée choisit, Échap ferme, Retour arrière retire la dernière puce.
 */
(function () {
  'use strict';

  var DEBOUNCE_MS = 250;
  var uid = 0;

  function norm(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function el(tag, attrs, text) {
    var e = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); });
    if (text != null) e.textContent = text;
    return e;
  }

  function enhance(select) {
    if (select.getAttribute('data-coop-enhanced') === '1') return;
    select.setAttribute('data-coop-enhanced', '1');
    uid++;
    var id = 'coop-cbx-' + uid;
    var source = select.getAttribute('data-source') || 'local';
    var endpoint = select.getAttribute('data-endpoint') || '';
    var missionId = select.getAttribute('data-mission-id') || '';
    var multiName = select.getAttribute('data-multiple-name') || '';
    var multiple = multiName !== '';
    var label = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;
    var form = select.form;

    var wrap = el('div', { class: 'coop-cbx' + (multiple ? ' coop-cbx--multi' : '') });
    var chips = el('ul', { class: 'coop-cbx__chips', 'aria-label': 'Sélection' });
    var input = el('input', {
      type: 'text',
      id: id + '-input',
      class: 'coop-cbx__input',
      role: 'combobox',
      'aria-autocomplete': 'list',
      'aria-expanded': 'false',
      'aria-controls': id + '-list',
      autocomplete: 'off',
      spellcheck: 'false',
      placeholder: select.getAttribute('data-placeholder') || 'Rechercher…'
    });
    if (select.required && !multiple) input.setAttribute('aria-required', 'true');
    var list = el('ul', { id: id + '-list', class: 'coop-cbx__list', role: 'listbox', hidden: '' });
    if (multiple) list.setAttribute('aria-multiselectable', 'true');
    var status = el('p', { class: 'sr-only', 'aria-live': 'polite', id: id + '-status' });
    var help = el('p', { class: 'coop-cbx__help', id: id + '-help' });
    input.setAttribute('aria-describedby', id + '-help');

    if (label) {
      label.setAttribute('for', id + '-input');
      label.id = label.id || id + '-label';
      list.setAttribute('aria-labelledby', label.id);
    }

    wrap.appendChild(chips);
    wrap.appendChild(input);
    wrap.appendChild(list);
    wrap.appendChild(help);
    wrap.appendChild(status);
    select.parentNode.insertBefore(wrap, select.nextSibling);
    select.hidden = true;
    select.setAttribute('aria-hidden', 'true');
    select.tabIndex = -1;

    // Sélection multiple : le nom du <select> est remplacé par des champs cachés.
    var hiddenBox = null;
    if (multiple) {
      select.removeAttribute('name');
      select.required = false;
      hiddenBox = el('div', { hidden: '' });
      wrap.appendChild(hiddenBox);
    }

    var selected = [];   // [{id, name}]
    var results = [];    // résultats courants
    var active = -1;
    var timer = null;
    var seq = 0;

    function localOptions() {
      return Array.prototype.slice.call(select.options)
        .filter(function (o) { return o.value !== ''; })
        .map(function (o) {
          return { id: o.value, name: o.textContent.trim(), selectable: !o.disabled, state: '' };
        });
    }

    function setHelp(text) { help.textContent = text || ''; }

    function close() {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    }

    function isSelected(idv) {
      return selected.some(function (s) { return String(s.id) === String(idv); });
    }

    function render() {
      list.innerHTML = '';
      if (!results.length) {
        list.appendChild(el('li', { class: 'coop-cbx__empty', role: 'presentation' }, 'Aucun résultat'));
      }
      results.forEach(function (r, i) {
        var li = el('li', {
          id: id + '-opt-' + i,
          role: 'option',
          class: 'coop-cbx__opt' + (i === active ? ' is-active' : ''),
          'aria-selected': isSelected(r.id) ? 'true' : 'false'
        });
        if (!r.selectable) li.setAttribute('aria-disabled', 'true');
        li.appendChild(el('span', { class: 'coop-cbx__name' }, r.name));
        var meta = [r.type || '', r.state || r.detail || ''].filter(Boolean).join(' · ');
        if (meta) li.appendChild(el('span', { class: 'coop-cbx__meta' }, meta));
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(i); });
        list.appendChild(li);
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      if (active >= 0) input.setAttribute('aria-activedescendant', id + '-opt-' + active);
      else input.removeAttribute('aria-activedescendant');
      status.textContent = results.length ? results.length + ' résultat' + (results.length > 1 ? 's' : '') + ' disponible' + (results.length > 1 ? 's' : '') : 'Aucun résultat';
    }

    function renderLoading() {
      list.innerHTML = '';
      var li = el('li', { class: 'coop-cbx__loading', role: 'presentation', 'aria-busy': 'true' });
      var tpl = document.getElementById('coop-skeleton-template');
      if (tpl && tpl.content) {
        li.appendChild(tpl.content.cloneNode(true));
      } else {
        li.textContent = 'Recherche…';
      }
      list.appendChild(li);
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      wrap.setAttribute('aria-busy', 'true');
    }

    function syncValue() {
      if (multiple) {
        hiddenBox.innerHTML = '';
        selected.forEach(function (s) {
          hiddenBox.appendChild(el('input', { type: 'hidden', name: multiName, value: String(s.id) }));
        });
      } else {
        var s = selected[0];
        if (s && !Array.prototype.some.call(select.options, function (o) { return o.value === String(s.id); })) {
          select.appendChild(el('option', { value: String(s.id) }, s.name));
        }
        select.value = s ? String(s.id) : '';
      }
      chips.innerHTML = '';
      selected.forEach(function (s, idx) {
        var li = el('li', { class: 'coop-cbx__chip' });
        li.appendChild(el('span', null, s.name));
        var btn = el('button', { type: 'button', class: 'coop-cbx__chip-x', 'aria-label': 'Retirer ' + s.name }, '×');
        btn.addEventListener('click', function () {
          selected.splice(idx, 1);
          syncValue();
          input.focus();
          status.textContent = s.name + ' retirée de la sélection.';
        });
        li.appendChild(btn);
        chips.appendChild(li);
      });
      if (!multiple) {
        input.value = selected[0] ? selected[0].name : '';
      }
      wrap.classList.toggle('has-value', selected.length > 0);
    }

    function choose(i) {
      var r = results[i];
      if (!r || !r.selectable) {
        if (r) status.textContent = r.name + ' : ' + (r.state || 'non sélectionnable');
        return;
      }
      if (multiple) {
        if (isSelected(r.id)) {
          selected = selected.filter(function (s) { return String(s.id) !== String(r.id); });
        } else {
          selected.push({ id: r.id, name: r.name });
        }
        input.value = '';
        syncValue();
        status.textContent = r.name + (isSelected(r.id) ? ' ajoutée.' : ' retirée.');
        close();
      } else {
        selected = [{ id: r.id, name: r.name }];
        syncValue();
        close();
        status.textContent = r.name + ' sélectionnée.';
      }
    }

    function search(q) {
      if (source === 'local') {
        var nq = norm(q);
        results = localOptions().filter(function (o) { return !nq || norm(o.name).indexOf(nq) !== -1; }).slice(0, 30);
        active = results.length ? 0 : -1;
        render();
        return;
      }
      if (q.trim().length < 2) {
        results = [];
        close();
        setHelp('Saisissez au moins 2 caractères.');
        return;
      }
      setHelp('');
      renderLoading();
      var mySeq = ++seq;
      var url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q) + (missionId ? '&mission_id=' + encodeURIComponent(missionId) : '');
      fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.json(); })
        .then(function (data) {
          if (mySeq !== seq) return;
          wrap.removeAttribute('aria-busy');
          results = (data && data.results ? data.results : []).map(function (r) {
            return { id: r.id, name: r.name, type: r.type || '', state: r.state || '', detail: r.detail || '', selectable: r.selectable !== false };
          });
          active = -1;
          for (var k = 0; k < results.length; k++) { if (results[k].selectable) { active = k; break; } }
          render();
        })
        .catch(function () {
          if (mySeq !== seq) return;
          wrap.removeAttribute('aria-busy');
          close();
          setHelp('La recherche est momentanément indisponible : utilisez la liste ci-dessous.');
          select.hidden = false;
          select.removeAttribute('aria-hidden');
          select.tabIndex = 0;
          if (multiple) {
            select.setAttribute('name', select.getAttribute('data-fallback-name') || 'partner_tenant_id');
          }
        });
    }

    input.addEventListener('input', function () {
      if (!multiple) selected = [];
      clearTimeout(timer);
      var q = input.value;
      timer = setTimeout(function () { search(q); }, source === 'local' ? 0 : DEBOUNCE_MS);
    });
    input.addEventListener('focus', function () { if (source === 'local') search(input.value); });
    input.addEventListener('blur', function () { setTimeout(close, 120); if (!multiple) syncValue(); });
    input.addEventListener('keydown', function (e) {
      var open = !list.hidden;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!open) { search(input.value); return; }
        active = Math.min(results.length - 1, active + 1);
        render();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active = Math.max(0, active - 1);
        render();
      } else if (e.key === 'Enter') {
        if (open && active >= 0) { e.preventDefault(); choose(active); }
      } else if (e.key === 'Escape') {
        if (open) { e.preventDefault(); close(); }
      } else if (e.key === 'Backspace' && multiple && input.value === '' && selected.length) {
        var removed = selected.pop();
        syncValue();
        status.textContent = removed.name + ' retirée de la sélection.';
      }
    });

    if (form) {
      form.addEventListener('submit', function (e) {
        var need = select.getAttribute('data-required') === '1' || (select.required && !multiple);
        if (need && selected.length === 0) {
          e.preventDefault();
          e.stopImmediatePropagation();
          setHelp(multiple ? 'Choisissez au moins une unité.' : 'Choisissez un élément dans la liste.');
          help.classList.add('fr-message--error');
          input.classList.add('fr-input--error');
          input.setAttribute('aria-invalid', 'true');
          input.focus();
        }
      }, true);
    }

    // Valeur initiale éventuelle (sélection unique).
    if (!multiple && select.value) {
      var opt = select.options[select.selectedIndex];
      selected = [{ id: select.value, name: opt ? opt.textContent.trim() : select.value }];
      syncValue();
    }
  }

  function init() {
    document.querySelectorAll('select[data-coop-combobox]').forEach(enhance);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
