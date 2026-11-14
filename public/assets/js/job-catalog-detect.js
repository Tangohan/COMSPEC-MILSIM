/**
 * Emplois & fonctions : le référentiel à portée de main.
 *  - Création d'un emploi : pendant la saisie du nom (et de l'abrégé, du code, de l'unité), le site cherche l'emploi
 *    dans le référentiel (JobCatalogController::detect) et propose de l'importer, avec ce qu'il apporte en plus.
 *    « Voir dans : » change de pays (un nom tapé en français propose d'abord les postes français ; un joueur d'une
 *    nation US choisit « États-Unis »). Le pays choisi est retenu sur l'appareil comme nation jouée.
 *  - Emploi déjà créé (note orange) : « Le même poste dans un autre pays » liste le meilleur équivalent de chaque pays.
 * Les boutons ouvrent la fenêtre du catalogue sur l'emploi (job-catalog.js, data-job-catalog-show), où « Copier tout »
 * remplit la fiche. Les fiches arrivent par fragments : tout passe par délégation sur document.
 */
(function () {
  'use strict';

  var FIELDS = 'input[name="name"], input[name="abbreviation"], input[name="code"], select[name="linked_unit_id"]';
  var NATION_KEY = 'jobcat-nation';
  var timer = 0;
  var last = '';
  var dismissed = {};
  var pending = null;
  var pays = '';

  function stored() {
    try { return window.localStorage.getItem(NATION_KEY) || ''; } catch (e) { return ''; }
  }

  function store(v) {
    try {
      if (v) window.localStorage.setItem(NATION_KEY, v); else window.localStorage.removeItem(NATION_KEY);
    } catch (e) { /* stockage indisponible : le choix vaut pour la page */ }
  }

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

  function openButton(id, label) {
    var b = el('button', 'eff-btn', label);
    b.type = 'button';
    b.setAttribute('data-job-catalog-open', '');
    b.setAttribute('data-job-catalog-show', id);
    b.setAttribute('aria-haspopup', 'dialog');
    b.setAttribute('aria-controls', 'job-catalog-dialog');
    return b;
  }

  function hintItem(hint) {
    var item = el('li', 'eff-jobs-detect__item');
    var text = el('span');
    text.appendChild(el('b', '', hint.name));
    var meta = [hint.country_label, hint.unit, hint.abbreviation, hint.code].filter(Boolean).join(' · ');
    if (meta) text.appendChild(el('span', 'eff-jobs-detect__meta', meta));
    if (hint.gains) text.appendChild(el('span', 'eff-jobs-detect__gains', 'Avec ' + hint.gains));
    item.append(text, openButton(hint.id, 'Voir et importer'));
    return item;
  }

  /** Sélecteur de pays : « Tous » puis chaque pays où un poste proche existe. */
  function countryBar(countries, active) {
    var bar = el('div', 'eff-jobs-detect__countries');
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Voir dans un pays');
    bar.appendChild(el('span', 'eff-jobs-detect__countries-label', 'Voir dans :'));
    var all = [{country: '', country_label: 'Tous les pays'}].concat(countries);
    all.forEach(function (c) {
      var chip = el('button', 'eff-jobs-detect__country' + (c.country === active ? ' is-on' : ''), c.country_label);
      chip.type = 'button';
      chip.setAttribute('data-job-detect-country', c.country);
      chip.setAttribute('aria-pressed', c.country === active ? 'true' : 'false');
      bar.appendChild(chip);
    });
    return bar;
  }

  function render(box, json) {
    box.textContent = '';
    var hints = (json && Array.isArray(json.hints) ? json.hints : []).filter(function (h) { return !dismissed[h.id]; });
    var countries = json && Array.isArray(json.countries) ? json.countries : [];
    if (!hints.length && !countries.length) {
      box.hidden = true;
      return;
    }
    var lead = el('p', 'eff-jobs-detect__lead');
    lead.appendChild(el('strong', '', 'Hop hop !'));
    if (hints.length) {
      var gains = hints[0].gains ? ' Tu auras en plus : ' + hints[0].gains + '.' : '';
      lead.appendChild(document.createTextNode(' Ça existe déjà dans notre référentiel, prêt à l’emploi. Tu ne préfères pas l’importer ?' + gains));
    } else {
      lead.appendChild(document.createTextNode(' Rien de proche dans ce pays. Essaie un autre pays :'));
    }
    box.appendChild(lead);
    if (countries.length > 1 || pays) box.appendChild(countryBar(countries, pays));
    if (hints.length) {
      var list = el('ul', 'eff-jobs-detect__list');
      hints.forEach(function (h) { list.appendChild(hintItem(h)); });
      box.appendChild(list);
    }
    var actions = el('div', 'eff-jobs-detect__actions');
    var no = el('button', 'eff-jobs-detect__dismiss', 'Non merci, je crée le mien');
    no.type = 'button';
    no.setAttribute('data-job-detect-dismiss', '');
    actions.appendChild(no);
    box.appendChild(actions);
    box.hidden = false;
  }

  function fetchJson(url, params) {
    if (pending) pending.abort();
    pending = typeof AbortController === 'function' ? new AbortController() : null;
    return fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + params.toString(), {
      credentials: 'same-origin',
      headers: {'Accept': 'application/json'},
      signal: pending ? pending.signal : undefined
    }).then(function (response) { return response.ok ? response.json() : {hints: [], countries: []}; });
  }

  function check(form, force) {
    var box = form.querySelector('[data-job-detect]');
    if (!box || !box.dataset.url) return;
    var name = value(form, 'name');
    var abbr = value(form, 'abbreviation');
    var code = value(form, 'code');
    if (name.length < 3 && abbr.length < 2 && code.length < 2) {
      box.textContent = '';
      box.hidden = true;
      last = '';
      return;
    }
    var params = new URLSearchParams({nom: name, abrege: abbr, code: code, unite: value(form, 'linked_unit_id'), nation: stored(), pays: pays});
    var key = params.toString();
    if (key === last && !force) return;
    last = key;
    fetchJson(box.dataset.url, params)
      .then(function (json) { render(box, json); })
      .catch(function () { /* requête remplacée ou réseau : la saisie continue sans suggestion */ });
  }

  function schedule(event) {
    var form = event.target.closest('[data-job-catalog-form]');
    if (!form || !event.target.matches(FIELDS) || !form.querySelector('[data-job-detect]')) return;
    window.clearTimeout(timer);
    timer = window.setTimeout(function () { check(form, false); }, 350);
  }

  /** Emploi déjà créé : le meilleur équivalent dans chaque autre pays, chargé à l'ouverture du volet. */
  function loadOthers(panel) {
    if (panel.dataset.loaded) return;
    panel.dataset.loaded = '1';
    var list = panel.querySelector('[data-job-catalog-other-list]');
    list.textContent = 'Recherche…';
    var params = new URLSearchParams({nom: panel.dataset.name || '', abrege: panel.dataset.abbr || '', code: panel.dataset.code || ''});
    fetch(panel.dataset.url + (panel.dataset.url.indexOf('?') >= 0 ? '&' : '?') + params.toString(), {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
      .then(function (r) { return r.ok ? r.json() : {countries: []}; })
      .then(function (json) {
        list.textContent = '';
        var current = panel.dataset.current || '';
        var rows = (json.countries || []).filter(function (c) { return c.id !== current; });
        if (!rows.length) {
          list.appendChild(el('li', 'eff-jobs-detect__meta', 'Aucun équivalent dans un autre pays.'));
          return;
        }
        rows.forEach(function (c) {
          var item = el('li', 'eff-jobs-detect__item');
          var text = el('span');
          text.appendChild(el('b', '', c.country_label));
          text.appendChild(el('span', 'eff-jobs-detect__meta', c.name));
          item.append(text, openButton(c.id, 'Voir dans le référentiel'));
          list.appendChild(item);
        });
      })
      .catch(function () { list.textContent = 'La recherche n’a pas abouti. Réessayez.'; delete panel.dataset.loaded; });
  }

  document.addEventListener('input', schedule);
  document.addEventListener('change', schedule);
  document.addEventListener('toggle', function (event) {
    var panel = event.target;
    if (panel.matches && panel.matches('[data-job-catalog-other]') && panel.open) loadOthers(panel);
  }, true);
  document.addEventListener('click', function (event) {
    var chip = event.target.closest('[data-job-detect-country]');
    if (chip) {
      pays = chip.getAttribute('data-job-detect-country');
      // Le pays choisi devient la nation jouée de cet appareil (« Tous les pays » l'oublie).
      store(pays);
      var form = chip.closest('[data-job-catalog-form]');
      if (form) check(form, true);
      return;
    }
    var no = event.target.closest('[data-job-detect-dismiss]');
    if (!no) return;
    var box = no.closest('[data-job-detect]');
    box.querySelectorAll('[data-job-catalog-show]').forEach(function (b) { dismissed[b.getAttribute('data-job-catalog-show')] = true; });
    box.textContent = '';
    box.hidden = true;
  });

  pays = stored();
})();
