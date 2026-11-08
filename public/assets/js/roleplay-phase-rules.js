/*
 * Parcours RH (views/admin/organization/roleplay_phase_rules.php) : montre les champs utiles au type de chaque condition,
 * écrit la phrase qui résume la règle, ajoute et retire des conditions, déplace les étapes.
 * Sans ce script, tous les champs restent visibles et le formulaire reste complet.
 * Textes en français : traduits à l'affichage par i18n_dom.js (lang/en/site_auto.php, js-bundle).
 */
(function () {
  'use strict';

  // Étapes : un déplacement enregistre tout de suite le nouvel ordre.
  var order = document.querySelector('[data-rpp-order]');
  if (order) {
    order.addEventListener('click', function (e) {
      var up = e.target.closest('[data-rpp-up]');
      var down = e.target.closest('[data-rpp-down]');
      if (!up && !down) return;
      var li = e.target.closest('li');
      var other = up ? li.previousElementSibling : li.nextElementSibling;
      if (!other) return;
      if (up) li.parentNode.insertBefore(li, other);
      else li.parentNode.insertBefore(other, li);
      order.submit();
    });
  }

  var form = document.querySelector('[data-rpp-edit]');
  if (!form) return;
  var FIELDS = {};
  try { FIELDS = JSON.parse(form.getAttribute('data-fields') || '{}'); } catch (e) { FIELDS = {}; }
  var list = form.querySelector('[data-rpp-conds]');
  var empty = form.querySelector('[data-rpp-empty]');
  var tpl = form.querySelector('[data-rpp-template]');

  /** Phrase et libellé du seuil par type, mêmes textes que PhaseRuleEngine::previewSentence. */
  function fill(text, n, w) { return text.replace('%1', String(n)).replace('%2', String(w)); }
  function sentence(type, n, w, valid) {
    switch (type) {
      case 'qualification_held': return valid ? 'Le membre doit détenir la qualification choisie, encore valable.' : 'Le membre doit détenir la qualification choisie.';
      case 'lms_completed': return 'Le membre doit avoir validé le module de formation choisi.';
      case 'playtime_raw_hours': return fill('Le temps de jeu brut doit atteindre au moins %1 h.', n, w);
      case 'playtime_validated_hours': return fill('Le temps validé pour le suivi doit atteindre au moins %1 h.', n, w);
      case 'playtime_category_hours': return fill('Le temps validé dans cette nature de session doit atteindre au moins %1 h.', n, w);
      case 'sessions_validated_count': return fill(w > 0 ? 'Au moins %1 session(s) validée(s) sur %2 jours.' : 'Au moins %1 session(s) validée(s).', n, w);
      case 'sessions_kind_count': return fill(w > 0 ? 'Au moins %1 session(s) de ce type sur %2 jours.' : 'Au moins %1 session(s) de ce type.', n, w);
      case 'attendance_checked_in_count': return fill(w > 0 ? 'Au moins %1 présence(s) réellement pointée(s) sur %2 jours. Une simple inscription ne compte pas.' : 'Au moins %1 présence(s) réellement pointée(s). Une simple inscription ne compte pas.', n, w);
      case 'attendance_rate': return fill(w > 0 ? 'Le taux de présence pointée doit atteindre %1 % sur %2 jours.' : 'Le taux de présence pointée doit atteindre %1 %.', n, w);
      case 'seniority_days': return fill('L’ancienneté doit atteindre au moins %1 jours.', n, w);
      case 'dossier_completeness': return fill('La fiche doit être complète à au moins %1 %.', n, w);
      case 'tutor_assigned': return 'Un tuteur doit être désigné sur le dossier.';
      case 'no_active_sanction': return 'Aucune sanction en cours.';
      default: return '';
    }
  }
  var UNIT = {
    playtime_raw_hours: 'Heures', playtime_validated_hours: 'Heures', playtime_category_hours: 'Heures',
    sessions_validated_count: 'Nombre de sessions', sessions_kind_count: 'Nombre de sessions', attendance_checked_in_count: 'Nombre de présences',
    attendance_rate: 'Pourcentage', seniority_days: 'Jours', dossier_completeness: 'Pourcentage'
  };

  function sync(cond) {
    var type = cond.querySelector('[data-rpp-type]').value;
    var wanted = FIELDS[type] || [];
    Array.prototype.forEach.call(cond.querySelectorAll('[data-rpp-field]'), function (el) {
      el.hidden = wanted.indexOf(el.getAttribute('data-rpp-field')) === -1;
    });
    var label = cond.querySelector('[data-rpp-threshold-label]');
    if (label) label.textContent = UNIT[type] || 'Seuil';
    var th = cond.querySelector('[data-rpp-threshold]');
    var win = cond.querySelector('[data-rpp-window]');
    var val = cond.querySelector('[data-rpp-validity]');
    var n = th ? (Number(th.value) || 0) : 0;
    cond.querySelector('[data-rpp-says]').textContent = sentence(type, n, win ? (Number(win.value) || 0) : 0, !!(val && val.checked));
  }
  function refreshEmpty() {
    if (empty) empty.hidden = !!list.querySelector('[data-rpp-cond]');
  }

  Array.prototype.forEach.call(list.querySelectorAll('[data-rpp-cond]'), sync);
  list.addEventListener('input', function (e) {
    var cond = e.target.closest('[data-rpp-cond]');
    if (cond) sync(cond);
  });
  list.addEventListener('change', function (e) {
    var cond = e.target.closest('[data-rpp-cond]');
    if (cond) sync(cond);
  });
  list.addEventListener('click', function (e) {
    if (!e.target.closest('[data-rpp-del]')) return;
    e.target.closest('[data-rpp-cond]').remove();
    refreshEmpty();
  });

  var next = 1000;
  var addBtn = form.querySelector('[data-rpp-add]');
  if (addBtn && tpl) {
    addBtn.addEventListener('click', function () {
      var holder = document.createElement('div');
      holder.innerHTML = tpl.innerHTML.replace(/__IDX__/g, String(next++));
      var cond = holder.querySelector('[data-rpp-cond]');
      if (!cond) return;
      list.appendChild(cond);
      sync(cond);
      refreshEmpty();
      cond.querySelector('[data-rpp-type]').focus();
    });
  }
})();
