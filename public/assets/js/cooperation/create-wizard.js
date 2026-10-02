/**
 * Assistant de création d’une coopération : affiche une étape à la fois, valide l’intitulé
 * avant d’avancer (message sous le champ) et alimente le récapitulatif.
 * Sans JavaScript, le formulaire complet reste utilisable.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-coop-wizard]');
  if (!form) return;
  var steps = Array.prototype.slice.call(form.querySelectorAll('[data-wizard-step]'));
  var prev = form.querySelector('[data-wizard-prev]');
  var next = form.querySelector('[data-wizard-next-btn]');
  var send = form.querySelector('[data-wizard-send]');
  var heading = form.querySelector('[data-wizard-heading]');
  var nextLabel = form.querySelector('[data-wizard-next]');
  var stepperItems = Array.prototype.slice.call(form.querySelectorAll('.ds-stepper__item'));
  var labels = ['Intitulé & cadrage', 'Unités à inviter', 'Récapitulatif & envoi'];
  var title = form.querySelector('#itm-title');
  var titleMsg = form.querySelector('#itm-title-msg');
  var current = 0;

  form.removeAttribute('novalidate');
  form.classList.add('is-enhanced');

  function validTitle(show) {
    var v = title ? title.value.trim() : '';
    var ok = v.length >= 3 && v.length <= 255;
    if (show && title) {
      title.classList.toggle('fr-input--error', !ok);
      title.setAttribute('aria-invalid', ok ? 'false' : 'true');
      if (titleMsg) {
        titleMsg.classList.toggle('fr-message--error', !ok);
        titleMsg.textContent = ok
          ? 'Entre 3 et 255 caractères. Un intitulé explicite aide les unités invitées à décider.'
          : 'Indiquez un intitulé d’au moins 3 caractères.';
      }
    }
    return ok;
  }

  function selectedText(select) {
    if (!select || select.selectedIndex < 0) return '';
    return select.options[select.selectedIndex].text;
  }

  function recap() {
    var set = function (key, val) {
      var el = form.querySelector('[data-recap="' + key + '"]');
      if (el) el.textContent = val;
    };
    set('title', title && title.value.trim() !== '' ? title.value.trim() : '—');
    var typo = form.querySelector('#itm-typology');
    if (typo) set('typology', typo.value ? selectedText(typo) : 'Non précisée');
    var prio = form.querySelector('#itm-priority');
    if (prio) set('priority', selectedText(prio));
    var dl = form.querySelector('#itm-deadline');
    if (dl) set('deadline', dl.value ? new Date(dl.value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : 'Pas de date limite');
    var units = Array.prototype.slice.call(form.querySelectorAll('input[name="partner_tenant_ids[]"]:checked'))
      .map(function (c) { return c.getAttribute('data-unit-label'); });
    set('units', units.length ? units.join(', ') : 'Aucune (brouillon)');
    if (send) {
      send.textContent = units.length ? 'Créer et envoyer ' + (units.length > 1 ? 'les ' + units.length + ' invitations' : 'l’invitation') : 'Créer le brouillon';
      send.value = units.length ? '1' : '';
    }
  }

  function show(i) {
    current = Math.max(0, Math.min(steps.length - 1, i));
    steps.forEach(function (s, idx) { s.hidden = idx !== current; });
    if (prev) prev.hidden = current === 0;
    if (next) next.hidden = current === steps.length - 1;
    if (send) send.hidden = current !== steps.length - 1;
    if (heading) heading.textContent = 'Étape ' + (current + 1) + ' sur 3 — ' + labels[current];
    if (nextLabel) nextLabel.textContent = current < 2 ? 'Étape suivante : ' + labels[current + 1] : 'Dernière étape';
    stepperItems.forEach(function (li, idx) {
      li.classList.toggle('ds-stepper__item--done', idx < current);
      li.classList.toggle('ds-stepper__item--active', idx === current);
      if (idx === current) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
    });
    if (current === 2) recap();
    var focusTarget = steps[current].querySelector('input, select, textarea, button');
    if (focusTarget && i !== 0) focusTarget.focus();
  }

  if (next) {
    next.addEventListener('click', function () {
      if (current === 0 && !validTitle(true)) {
        title.focus();
        return;
      }
      show(current + 1);
    });
  }
  if (prev) prev.addEventListener('click', function () { show(current - 1); });

  form.addEventListener('submit', function (e) {
    if (!validTitle(true)) {
      e.preventDefault();
      show(0);
      title.focus();
    }
  });
  if (title) title.addEventListener('input', function () { if (title.getAttribute('aria-invalid') === 'true') validTitle(true); });

  var filter = form.querySelector('[data-wizard-unit-filter]');
  if (filter) {
    filter.addEventListener('input', function () {
      var q = filter.value.trim().toLowerCase();
      form.querySelectorAll('[data-wizard-units] li').forEach(function (li) {
        li.hidden = q !== '' && (li.getAttribute('data-unit-name') || '').indexOf(q) === -1;
      });
    });
  }
  form.addEventListener('change', recap);

  show(0);
})();
