/**
 * Effectifs › Ajouter un membre (views/admin/effectifs_workspace/new_member.php).
 * Étapes une par une, vérifications en direct (indicatif, e-mail, Steam), emplois (recherche, plafond,
 * emploi principal), aperçu de la fiche. Sans ce script, le formulaire reste complet et utilisable.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-eff-new-member]');
  if (!root) {
    return;
  }
  var form = root.querySelector('#eff-nm-form');
  var steps = Array.prototype.slice.call(root.querySelectorAll('[data-nm-step]'));
  var rail = root.querySelector('[data-nm-rail]');
  var pager = root.querySelector('[data-nm-pager]');
  var prev = root.querySelector('[data-nm-prev]');
  var next = root.querySelector('[data-nm-next]');
  var checkUrl = root.getAttribute('data-check-url') || '';
  var jobMax = parseInt(root.getAttribute('data-job-max') || '5', 10) || 5;
  var csrf = (form.querySelector('input[name="_csrf_token"]') || {}).value || '';
  var current = 0;
  var stepped = false;
  var checkState = {};

  function q(sel) { return form.querySelector(sel); }
  function val(name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el && !el.disabled ? (el.value || '').trim() : '';
  }
  function checked(sel) { return Array.prototype.slice.call(form.querySelectorAll(sel + ':checked')); }

  /* ── Type de fiche, e-mail inconnu, invitation ─────────────────── */

  function noAccount() {
    var r = form.querySelector('input[name="roster_only"]:checked');
    return !!r && r.value === '1';
  }

  function syncVisibility() {
    var account = root.querySelector('[data-nm-account]');
    var inviteStep = root.querySelector('[data-nm-invite-step]');
    if (account) { account.hidden = noAccount(); }
    if (inviteStep && !stepped) { inviteStep.hidden = noAccount(); }
    var unknown = q('[data-nm-email-unknown]');
    var email = q('[data-nm-email]');
    var sendEmail = q('[data-nm-send-email]');
    if (email && unknown) {
      email.disabled = unknown.checked;
      if (unknown.checked) { setMsg('email', '', ''); }
    }
    if (sendEmail) {
      var noMail = noAccount() || (unknown && unknown.checked);
      sendEmail.disabled = !!noMail;
      if (noMail) { sendEmail.checked = false; }
      var row = root.querySelector('[data-nm-send-email-row]');
      if (row) { row.classList.toggle('is-off', !!noMail); }
    }
    var invite = q('[data-nm-invite]');
    var opts = root.querySelector('[data-nm-invite-opts]');
    if (invite && opts) { opts.hidden = !invite.checked; }
    var discord = q('[data-nm-send-discord]');
    var dest = root.querySelector('[data-nm-destination]');
    if (discord && dest) { dest.hidden = !discord.checked; }
    if (rail) {
      var inviteRail = rail.querySelector('[data-nm-goto="invitation"]');
      if (inviteRail) { inviteRail.parentElement.hidden = noAccount(); }
    }
  }

  /* ── Étapes ────────────────────────────────────────────────────── */

  function visibleSteps() {
    return steps.filter(function (s) { return !(s.hasAttribute('data-nm-invite-step') && noAccount()); });
  }

  function show(index, focus) {
    var list = visibleSteps();
    index = Math.max(0, Math.min(list.length - 1, index));
    var target = list[index];
    steps.forEach(function (s) {
      s.classList.toggle('is-current', s === target);
      s.hidden = s !== target;
    });
    current = steps.indexOf(target);
    if (rail) {
      rail.querySelectorAll('[data-nm-goto]').forEach(function (b) {
        var sid = b.getAttribute('data-nm-goto');
        var pos = list.findIndex(function (s) { return s.getAttribute('data-nm-step') === sid; });
        b.classList.toggle('is-current', pos === index);
        b.classList.toggle('is-done', pos > -1 && pos < index);
        if (pos === index) { b.setAttribute('aria-current', 'step'); } else { b.removeAttribute('aria-current'); }
      });
    }
    if (prev) { prev.hidden = index === 0; }
    if (next) { next.hidden = index === list.length - 1; }
    if (target.getAttribute('data-nm-step') === 'apercu') { renderSummary(); }
    if (focus) {
      var h = target.querySelector('h2');
      if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
      target.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }
  }

  function indexOfCurrent() {
    return Math.max(0, visibleSteps().indexOf(steps[current]));
  }

  /** Erreurs bloquantes de l'étape courante (texte affiché sous le champ fautif). */
  function stepProblem(stepId) {
    if (stepId === 'identite') {
      if (val('callsign') === '' && val('first_name') === '' && val('last_name') === '') {
        return ['callsign', 'Indiquez au moins l’indicatif ou le nom du personnage.'];
      }
      var unknown = q('[data-nm-email-unknown]');
      if (!noAccount() && q('[data-nm-email]') && !(unknown && unknown.checked)) {
        var email = val('email');
        if (email === '') { return ['email', 'Indiquez son e-mail, ou cochez « Je ne connais pas son e-mail ».']; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { return ['email', 'Adresse e-mail incomplète.']; }
      }
      for (var f in checkState) {
        if (Object.prototype.hasOwnProperty.call(checkState, f) && (checkState[f].state === 'taken' || checkState[f].state === 'invalid')) {
          var el = q('[name="' + f + '"]');
          if (el && !el.disabled && el.value.trim() !== '') { return [f, checkState[f].message]; }
        }
      }
    }
    return null;
  }

  function guard(stepId) {
    var p = stepProblem(stepId);
    if (p === null) { return true; }
    setMsg(p[0], 'invalid', p[1]);
    var el = q('[name="' + p[0] + '"]');
    if (el) { el.focus(); }
    return false;
  }

  if (rail && pager) {
    root.classList.add('is-stepped');
    stepped = true;
    rail.hidden = false;
    pager.hidden = false;
    rail.querySelectorAll('[data-nm-goto]').forEach(function (b) {
      b.addEventListener('click', function () {
        var list = visibleSteps();
        var pos = list.findIndex(function (s) { return s.getAttribute('data-nm-step') === b.getAttribute('data-nm-goto'); });
        if (pos > indexOfCurrent() && !guard(steps[current].getAttribute('data-nm-step'))) { return; }
        show(pos, true);
      });
    });
    next.addEventListener('click', function () {
      if (guard(steps[current].getAttribute('data-nm-step'))) { show(indexOfCurrent() + 1, true); }
    });
    prev.addEventListener('click', function () { show(indexOfCurrent() - 1, true); });
    show(0, false);
  }

  form.addEventListener('submit', function (e) {
    if (!guard('identite')) {
      e.preventDefault();
      if (rail) { show(0, true); guard('identite'); }
      return;
    }
    var btn = form.querySelector('[data-nm-submit]');
    if (btn) { btn.disabled = true; btn.textContent = 'Création…'; }
  });

  /* ── Vérifications en direct ───────────────────────────────────── */

  function setMsg(field, state, text) {
    var msg = form.querySelector('[data-nm-msg="' + field + '"]');
    var input = form.querySelector('[name="' + field + '"]');
    if (input) {
      input.classList.toggle('is-bad', state === 'taken' || state === 'invalid');
      input.classList.toggle('is-good', state === 'ok');
      if (state === 'taken' || state === 'invalid') { input.setAttribute('aria-invalid', 'true'); } else { input.removeAttribute('aria-invalid'); }
    }
    if (!msg) { return; }
    if (!msg.hasAttribute('data-default')) { msg.setAttribute('data-default', msg.textContent); }
    msg.className = 'eff-nm-msg' + (state ? ' is-' + state : '');
    msg.textContent = text !== '' ? text : msg.getAttribute('data-default');
  }

  var timer = null;
  function runChecks() {
    if (!checkUrl || !window.fetch) { return; }
    var fd = new FormData();
    fd.append('_csrf_token', csrf);
    var any = false;
    form.querySelectorAll('[data-nm-check]').forEach(function (el) {
      if (!el.disabled && el.value.trim() !== '') { fd.append(el.name, el.value.trim()); any = true; }
      else { delete checkState[el.name]; setMsg(el.name, '', ''); }
    });
    if (!any) { return; }
    fetch(checkUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var fields = (data && data.fields) || {};
        Object.keys(fields).forEach(function (k) {
          checkState[k] = fields[k];
          setMsg(k, fields[k].state, fields[k].message);
        });
      })
      .catch(function () { /* hors ligne : le serveur revérifie à l'enregistrement */ });
  }
  form.querySelectorAll('[data-nm-check]').forEach(function (el) {
    el.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(runChecks, 450);
    });
  });

  /* ── Emplois ───────────────────────────────────────────────────── */

  var jobBox = root.querySelector('[data-nm-jobs]');
  var jobOrder = [];
  if (jobBox) {
    var primary = jobBox.querySelector('[data-nm-primary-job]');
    var jobMsg = jobBox.querySelector('[data-nm-job-msg]');
    var boxes = Array.prototype.slice.call(jobBox.querySelectorAll('input[name="job_role_ids[]"]'));
    jobOrder = boxes.filter(function (b) { return b.checked; });
    var syncJobs = function () {
      jobOrder = jobOrder.filter(function (b) { return b.checked; });
      primary.value = jobOrder.length ? jobOrder[0].value : '';
      boxes.forEach(function (b) {
        b.disabled = !b.checked && jobOrder.length >= jobMax;
        var lab = b.closest('[data-nm-job]');
        if (lab) { lab.classList.toggle('is-primary', jobOrder.length > 0 && b === jobOrder[0]); }
      });
      jobMsg.textContent = jobOrder.length >= jobMax ? 'Plafond atteint (' + jobMax + ' emplois).' : '';
    };
    boxes.forEach(function (b) {
      b.addEventListener('change', function () {
        if (b.checked) { jobOrder.push(b); }
        syncJobs();
        preview();
      });
    });
    var search = jobBox.querySelector('[data-nm-job-search]');
    if (search) {
      search.addEventListener('input', function () {
        var term = search.value.trim().toLowerCase();
        jobBox.querySelectorAll('[data-nm-job-group]').forEach(function (g) {
          var shown = 0;
          g.querySelectorAll('[data-nm-job]').forEach(function (j) {
            var ok = term === '' || (j.getAttribute('data-search') || '').indexOf(term) !== -1;
            j.hidden = !ok;
            if (ok) { shown++; }
          });
          g.hidden = shown === 0;
          if (term !== '' && shown > 0) { g.open = true; }
        });
      });
    }
    syncJobs();
  }

  /* ── Aperçu ────────────────────────────────────────────────────── */

  function pv(key, text) {
    var el = root.querySelector('[data-pv="' + key + '"]');
    if (el) { el.textContent = text; }
  }
  function selectedText(sel) {
    var s = form.querySelector(sel);
    if (!s || s.disabled || !s.value) { return ''; }
    var o = s.options[s.selectedIndex];
    return o ? (o.getAttribute('data-name') || o.textContent || '').trim() : '';
  }
  function langs() {
    var main = form.querySelector('input[name="primary_lang"]:checked');
    var out = main ? [main.getAttribute('data-flag') + ' ' + main.parentElement.textContent.replace(main.getAttribute('data-flag'), '').trim()] : [];
    checked('input[name="other_langs[]"]').forEach(function (c) {
      if (!main || c.value !== main.value) { out.push(c.getAttribute('data-flag')); }
    });
    return out.join(' ');
  }
  function inviteLine() {
    var invite = q('[data-nm-invite]');
    if (noAccount()) { return 'Fiche sans compte : aucun lien.'; }
    if (!invite || !invite.checked) { return 'Pas de lien pour l’instant.'; }
    var d = form.querySelector('input[name="duration_hours"]:checked');
    var parts = ['Lien valable ' + (d ? d.value : '48') + ' h'];
    if ((q('input[name="single_use"]') || {}).checked) { parts.push('usage unique'); }
    if ((q('[data-nm-send-discord]') || {}).checked) { parts.push('publié sur Discord'); }
    if ((q('[data-nm-send-email]') || {}).checked) { parts.push('envoyé par e-mail'); }
    if (parts.length === 1 || (parts.length === 2 && parts[1] === 'usage unique')) { parts.push('à copier depuis la fiche'); }
    return parts.join(', ') + '.';
  }

  function preview() {
    var call = val('callsign');
    var name = (val('first_name') + ' ' + val('last_name')).trim();
    var nick = val('nickname');
    pv('callsign', call !== '' ? call : 'Indicatif');
    pv('name', (name !== '' ? name : 'Nom du personnage') + (nick !== '' ? ' « ' + nick + ' »' : ''));
    pv('grade', selectedText('[data-nm-grade]') || 'Sans grade');
    var unitSel = form.querySelector('[data-nm-unit]');
    var opt = unitSel && unitSel.value ? unitSel.options[unitSel.selectedIndex] : null;
    pv('unit', opt ? opt.getAttribute('data-name') : 'Sans unité');
    pv('unitcode', opt && opt.getAttribute('data-code') ? opt.getAttribute('data-code') : '');
    var codeHint = root.querySelector('[data-nm-unitcode]');
    if (codeHint) {
      codeHint.textContent = opt && opt.getAttribute('data-code')
        ? 'Code unité ' + opt.getAttribute('data-code') + (opt.getAttribute('data-admin') ? ' · identifiant ' + opt.getAttribute('data-admin') : '')
        : '';
    }
    pv('unit_role', val('unit_role') || 'Membre');
    pv('jobs', jobOrder.length ? jobOrder.map(function (b) { return b.getAttribute('data-label'); }).join(', ') : 'Aucun');
    var acc = form.querySelector('input[name="access_role_id"]:checked');
    pv('access', acc ? acc.getAttribute('data-label') : 'Membre');
    pv('langs', langs() || '—');
    pv('discord', val('discord_handle') !== '' ? val('discord_handle') : 'À relier par la personne');
    pv('steam', val('steam_id') !== '' ? 'Renseigné' : 'À relier par la personne');
    pv('invite', inviteLine());
  }

  function renderSummary() {
    preview();
    var dl = root.querySelector('[data-nm-summary]');
    if (!dl) { return; }
    var rows = [
      ['Indicatif', val('callsign') || '—'],
      ['Personnage', ((val('first_name') + ' ' + val('last_name')).trim() || '—') + (val('nickname') ? ' « ' + val('nickname') + ' »' : '')],
      ['Connexion', noAccount() ? 'Fiche sans compte' : ((q('[data-nm-email-unknown]') || {}).checked ? 'E-mail saisi par la personne' : (val('email') || '—'))],
      ['Grade', selectedText('[data-nm-grade]') || 'Sans grade'],
      ['Unité', (root.querySelector('[data-pv="unit"]') || {}).textContent + (val('unit_role') ? ' — ' + val('unit_role') : '')],
      ['Emplois', (root.querySelector('[data-pv="jobs"]') || {}).textContent],
      ['Accès', (root.querySelector('[data-pv="access"]') || {}).textContent],
      ['Engagement', val('enlistment_date') ? val('enlistment_date').split('-').reverse().join('/') : '—'],
      ['Langues', langs() || '—'],
      ['Invitation', inviteLine()]
    ];
    dl.textContent = '';
    rows.forEach(function (r) {
      var wrap = document.createElement('div');
      var dt = document.createElement('dt');
      var dd = document.createElement('dd');
      dt.textContent = r[0];
      dd.textContent = r[1];
      wrap.appendChild(dt);
      wrap.appendChild(dd);
      dl.appendChild(wrap);
    });
  }

  form.addEventListener('input', preview);
  form.addEventListener('change', function () { syncVisibility(); preview(); });
  syncVisibility();
  preview();
  if (form.querySelector('[data-nm-check]')) { runChecks(); }
})();
