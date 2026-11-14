/**
 * Emplois & fonctions : détection à la création d'un emploi. Pendant la saisie du nom (et de l'abrégé, du code, de
 * l'unité), le site cherche l'emploi dans le référentiel (JobCatalogController::detect) et propose de l'importer, avec ce
 * qu'il apporte en plus (qualifications, postes types, sources). Le bouton ouvre la fenêtre du catalogue sur l'emploi
 * (job-catalog.js, data-job-catalog-show), où « Copier tout » remplit la fiche. Les fiches arrivent par fragments :
 * tout passe par délégation sur document.
 */
(function () {
  'use strict';

  var FIELDS = 'input[name="name"], input[name="abbreviation"], input[name="code"], select[name="linked_unit_id"]';
  var timer = 0;
  var last = '';
  var dismissed = {};
  var pending = null;

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function value(form, name) {
    var field = form.querySelector('[name="' + name + '"]');
    if (!field) return '';
    if (field.tagName === 'SELECT') {
      var option = field.options[field.selectedIndex];
      return option && field.value !== '0' ? option.textContent.trim() : '';
    }
    return field.value.trim();
  }

  function render(box, hints) {
    box.textContent = '';
    if (!hints.length) {
      box.hidden = true;
      return;
    }
    var gains = hints[0].gains ? ' Tu auras en plus : ' + hints[0].gains + '.' : '';
    var lead = el('p', 'eff-jobs-detect__lead');
    lead.appendChild(el('strong', '', 'Hop hop !'));
    lead.appendChild(document.createTextNode(' Ça existe déjà dans notre référentiel, prêt à l’emploi. Tu ne préfères pas l’importer ?' + gains));
    box.appendChild(lead);
    var list = el('ul', 'eff-jobs-detect__list');
    hints.forEach(function (hint) {
      var item = el('li', 'eff-jobs-detect__item');
      var text = el('span');
      text.appendChild(el('b', '', hint.name));
      var meta = [hint.country_label, hint.unit, hint.abbreviation, hint.code].filter(Boolean).join(' · ');
      if (meta) text.appendChild(el('span', 'eff-jobs-detect__meta', meta));
      if (hint.gains) text.appendChild(el('span', 'eff-jobs-detect__gains', 'Avec ' + hint.gains));
      var open = el('button', 'eff-btn', 'Voir et importer');
      open.type = 'button';
      open.setAttribute('data-job-catalog-open', '');
      open.setAttribute('data-job-catalog-show', hint.id);
      open.setAttribute('aria-haspopup', 'dialog');
      open.setAttribute('aria-controls', 'job-catalog-dialog');
      item.append(text, open);
      list.appendChild(item);
    });
    box.appendChild(list);
    var actions = el('div', 'eff-jobs-detect__actions');
    var no = el('button', 'eff-jobs-detect__dismiss', 'Non merci, je crée le mien');
    no.type = 'button';
    no.setAttribute('data-job-detect-dismiss', '');
    actions.appendChild(no);
    box.appendChild(actions);
    box.hidden = false;
  }

  function check(form) {
    var box = form.querySelector('[data-job-detect]');
    if (!box || !box.dataset.url) return;
    var name = value(form, 'name');
    var abbr = value(form, 'abbreviation');
    var code = value(form, 'code');
    if (name.length < 3 && abbr.length < 2 && code.length < 2) {
      render(box, []);
      last = '';
      return;
    }
    var params = new URLSearchParams({nom: name, abrege: abbr, code: code, unite: value(form, 'linked_unit_id')});
    var key = params.toString();
    if (key === last) return;
    last = key;
    if (pending) pending.abort();
    pending = typeof AbortController === 'function' ? new AbortController() : null;
    fetch(box.dataset.url + (box.dataset.url.indexOf('?') >= 0 ? '&' : '?') + key, {
      credentials: 'same-origin',
      headers: {'Accept': 'application/json'},
      signal: pending ? pending.signal : undefined
    })
      .then(function (response) { return response.ok ? response.json() : {hints: []}; })
      .then(function (json) {
        var hints = (json && Array.isArray(json.hints) ? json.hints : []).filter(function (h) { return !dismissed[h.id]; });
        render(box, hints);
      })
      .catch(function () { /* requête remplacée ou réseau : la saisie continue sans suggestion */ });
  }

  function schedule(event) {
    var form = event.target.closest('[data-job-catalog-form]');
    if (!form || !event.target.matches(FIELDS) || !form.querySelector('[data-job-detect]')) return;
    window.clearTimeout(timer);
    timer = window.setTimeout(function () { check(form); }, 350);
  }

  document.addEventListener('input', schedule);
  document.addEventListener('change', schedule);
  document.addEventListener('click', function (event) {
    var no = event.target.closest('[data-job-detect-dismiss]');
    if (!no) return;
    var box = no.closest('[data-job-detect]');
    box.querySelectorAll('[data-job-catalog-show]').forEach(function (b) { dismissed[b.getAttribute('data-job-catalog-show')] = true; });
    render(box, []);
  });
})();
