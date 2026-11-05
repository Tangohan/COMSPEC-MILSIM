/**
 * Back-office — Intégrations (views/admin/organization/integrations.php).
 * Sommaire actif au défilement, usages Discord (activé / coupé, salon à part en mode avancé), bouton « Tester »,
 * aperçu du message des liens d’invitation,
 * recherche et filtres d'événements, réglage groupé, barre d'enregistrement, vérification des liens webhook,
 * rédaction d’un e-mail à la communauté.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-bo-int]');
  if (!root) {
    return;
  }

  var hints = {
    ok: 'Lien webhook valide.',
    channel: 'Ceci est le lien du salon, pas un webhook : Modifier le salon › Intégrations › Webhooks › Copier l’URL du webhook.',
    id: 'Ceci est l’identifiant du salon, pas un webhook : il faut le lien complet du webhook.',
    invite: 'Ceci est une invitation au serveur, pas un webhook : suivez le tutoriel ci-dessus (Intégrations › Webhooks).',
    incomplete: 'Le lien est coupé : il manque la partie secrète à la fin. Recopiez-le avec « Copier l’URL du webhook ».',
    foreign: 'Seuls les liens de Discord sont acceptés (discord.com ou discordapp.com).',
    bad: 'Lien non reconnu. Il doit ressembler à https://discord.com/api/webhooks/1234…/AbCd…'
  };

  /* ── Vérification des liens webhook ───────────────────────────── */

  function checkWebhook(input) {
    var msg = input.parentElement.querySelector('[data-webhook-msg]');
    var v = (input.value || '').trim();
    input.classList.remove('is-valid', 'is-invalid');
    if (!msg) {
      return;
    }
    msg.className = 'bo-int-check-msg';
    if (v === '') {
      msg.textContent = '';
      return;
    }
    var kind;
    if (/^https:\/\/(?:(?:www|ptb|canary)\.)?discord(?:app)?\.com\/api(?:\/v\d+)?\/webhooks\/\d{5,}\/[\w-]{20,}\/?(?:\?thread_id=\d+)?$/i.test(v)) {
      kind = 'ok';
    } else if (/discord(?:app)?\.com\/channels\//i.test(v)) {
      kind = 'channel';
    } else if (/^#?\d{15,}$/.test(v)) {
      kind = 'id';
    } else if (/discord\.gg\/|discord(?:app)?\.com\/invite\//i.test(v)) {
      kind = 'invite';
    } else if (/\/webhooks\/\d+\/?$/i.test(v)) {
      kind = 'incomplete';
    } else if (/^https?:\/\//i.test(v) && !/^https?:\/\/(?:(?:www|ptb|canary)\.)?discord(?:app)?\.com\//i.test(v)) {
      kind = 'foreign';
    } else {
      kind = 'bad';
    }
    msg.textContent = hints[kind];
    msg.classList.add(kind === 'ok' ? 'is-ok' : 'is-bad');
    input.classList.add(kind === 'ok' ? 'is-valid' : 'is-invalid');
  }

  root.querySelectorAll('[data-webhook-input]').forEach(function (input) {
    if (input.value.trim() !== '') {
      checkWebhook(input);
    }
    input.addEventListener('input', function () { checkWebhook(input); });
  });

  /* ── Usages Discord ───────────────────────────────────────────── */

  var form = root.querySelector('[data-discord-form]');
  if (form) {
    var events = Array.prototype.slice.call(form.querySelectorAll('[data-discord-event]'));
    var groups = Array.prototype.slice.call(form.querySelectorAll('[data-discord-group]'));
    var search = form.querySelector('[data-discord-search]');
    var filters = form.querySelectorAll('[data-discord-filter]');
    var countOn = form.querySelector('[data-discord-count-on]');
    var empty = form.querySelector('[data-discord-empty]');
    var savebar = form.querySelector('[data-savebar]');
    var saveState = form.querySelector('[data-savebar-state]');
    var resetBtn = form.querySelector('[data-savebar-reset]');
    var filter = 'all';
    var initialStr = serialize(new FormData(form));

    function serialize(fd) {
      var out = [];
      fd.forEach(function (v, k) {
        if (k !== '_csrf_token') {
          out.push(k + '=' + v);
        }
      });
      return out.join('&');
    }

    function isOn(card) {
      var box = card.querySelector('[data-usage-on]');
      return !!(box && box.checked);
    }

    function refresh() {
      var q = search ? search.value.trim().toLowerCase() : '';
      var total = 0;
      var visible = 0;
      events.forEach(function (card) {
        var on = isOn(card);
        card.setAttribute('data-mode', on ? 'on' : 'off');
        if (on) { total++; }
        var matchQ = q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
        var matchF = filter === 'all' || (filter === 'on' ? on : !on);
        card.hidden = !(matchQ && matchF);
        if (!card.hidden) { visible++; }
      });
      groups.forEach(function (g) {
        var on = 0;
        var shown = 0;
        g.querySelectorAll('[data-discord-event]').forEach(function (c) {
          if (isOn(c)) { on++; }
          if (!c.hidden) { shown++; }
        });
        var label = g.querySelector('[data-group-on]');
        if (label) { label.textContent = String(on); }
        g.hidden = shown === 0;
        if ((q !== '' || filter !== 'all') && shown > 0) { g.open = true; }
      });
      if (countOn) { countOn.textContent = String(total); }
      if (empty) { empty.hidden = visible > 0; }
      var dirty = serialize(new FormData(form)) !== initialStr;
      if (savebar) {
        savebar.classList.toggle('is-dirty', dirty);
        saveState.textContent = dirty ? 'Modifications non enregistrées.' : 'Tout est enregistré.';
        resetBtn.disabled = !dirty;
      }
    }

    function setAll(scope, on) {
      scope.querySelectorAll('[data-discord-event]').forEach(function (card) {
        if (card.hidden) { return; }
        var box = card.querySelector('[data-usage-on]');
        if (box) { box.checked = on; }
      });
      refresh();
    }

    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    groups.forEach(function (g) {
      g.querySelectorAll('[data-group-set]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          g.open = true;
          setAll(g, btn.getAttribute('data-group-set') === 'on');
        });
      });
    });
    form.querySelectorAll('[data-usage-all]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setAll(form, btn.getAttribute('data-usage-all') === 'on');
        groups.forEach(function (g) { g.open = btn.getAttribute('data-usage-all') === 'on'; });
      });
    });
    if (search) { search.addEventListener('input', refresh); }
    filters.forEach(function (btn) {
      btn.addEventListener('click', function () {
        filter = btn.getAttribute('data-discord-filter') || 'all';
        filters.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
        refresh();
      });
    });
    form.addEventListener('reset', function () { window.setTimeout(refresh, 0); });
    form.addEventListener('submit', function () { initialStr = serialize(new FormData(form)); });
    window.addEventListener('beforeunload', function (e) {
      if (serialize(new FormData(form)) !== initialStr) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
    refresh();
  }

  /* ── Bouton « Tester » d'un salon ─────────────────────────────── */

  var testForm = root.querySelector('[data-wh-test-form]');
  root.querySelectorAll('[data-wh-test]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!testForm || !window.fetch) { return; }
      var box = btn.closest('[data-wh-form]') || btn.closest('.bo-int-wh');
      var out = box ? box.querySelector('[data-wh-result]') : null;
      var typed = box ? box.querySelector('input[name="url"]') : null;
      var fd = new FormData(testForm);
      fd.append('id', btn.getAttribute('data-wh-test') || 'organisation');
      if (typed && typed.value.trim() !== '') {
        fd.append('url', typed.value.trim());
      } else if (btn.getAttribute('data-wh-test') === 'nouveau') {
        if (out) { out.className = 'bo-int-wh__result is-bad'; out.textContent = 'Collez d’abord le lien du webhook.'; }
        if (typed) { typed.focus(); }
        return;
      }
      btn.disabled = true;
      if (out) { out.className = 'bo-int-wh__result'; out.textContent = 'Envoi d’un message d’essai…'; }
      fetch(testForm.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (out) {
            out.className = 'bo-int-wh__result ' + (data && data.ok ? 'is-ok' : 'is-bad');
            out.textContent = (data && data.message) || 'Réponse inattendue.';
          }
        })
        .catch(function () {
          if (out) { out.className = 'bo-int-wh__result is-bad'; out.textContent = 'Connexion perdue. Réessayez.'; }
        })
        .then(function () { btn.disabled = false; });
    });
  });

  /* ── Message des liens d'invitation : variables et aperçu ─────── */

  var tpl = root.querySelector('[data-tpl-form]');
  if (tpl) {
    var area = tpl.querySelector('[data-tpl-input]');
    var preview = tpl.querySelector('[data-tpl-preview]');
    var sample = {
      indicatif: 'VIPER 2-1',
      lien: 'https://athena-comspec.eu/public/invitation/profil/…',
      expire: '03/11/2026 à 21:00',
      communaute: tpl.getAttribute('data-community') || 'Votre communauté',
      unite: '1re section · SOAR-B-1',
      grade: 'Caporal'
    };
    var render = function () {
      var text = (area.value || '').replace(/\{(\w+)\}/g, function (m, k) { return Object.prototype.hasOwnProperty.call(sample, k) ? sample[k] : m; });
      preview.textContent = '@VIPER 2-1 ' + text;
    };
    tpl.querySelectorAll('[data-tpl-var]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        var token = '{' + chip.getAttribute('data-tpl-var') + '}';
        var start = area.selectionStart || area.value.length;
        var end = area.selectionEnd || start;
        area.value = area.value.slice(0, start) + token + area.value.slice(end);
        area.focus();
        area.setSelectionRange(start + token.length, start + token.length);
        render();
      });
    });
    area.addEventListener('input', render);
    render();
  }

  /* ── Rédaction d’un e-mail à la communauté ──────────────────── */

  var mailDialog = root.querySelector('[data-mail-dialog]');
  var mailOpen = root.querySelector('[data-mail-open]');
  var mailForm = root.querySelector('[data-community-mail-form]');
  var sending = false;
  if (mailDialog && mailOpen) {
    mailOpen.addEventListener('click', function () { mailDialog.showModal(); });
    root.querySelectorAll('[data-mail-close]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (!sending) { mailDialog.close(); }
      });
    });
    mailDialog.addEventListener('cancel', function (event) {
      if (sending) { event.preventDefault(); }
    });
  }
  if (mailForm) {
    mailForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (sending) { return; }
      var result = mailForm.querySelector('[data-mail-result]');
      var submit = mailForm.querySelector('[type="submit"]');
      var data = new FormData(mailForm);
      sending = true;
      submit.disabled = true;
      result.textContent = 'Envoi en cours…';
      fetch(mailForm.action, {
        method: 'POST', body: data, credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          result.textContent = data.message || 'Réponse inattendue.';
          if (data.ok) { mailForm.reset(); }
        })
        .catch(function () {
          result.textContent = 'Impossible de confirmer l’envoi. Consultez l’historique des communications avant de réessayer.';
        })
        .then(function () { sending = false; submit.disabled = false; });
    });
  }

  /* ── Sommaire actif ───────────────────────────────────────────── */

  var links = root.querySelectorAll('[data-int-nav]');
  var sections = root.querySelectorAll('[data-int-section]');
  if ('IntersectionObserver' in window && links.length) {
    var byId = {};
    links.forEach(function (a) { byId[(a.getAttribute('href') || '').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && byId[entry.target.id]) {
          links.forEach(function (a) { a.classList.remove('is-active'); });
          byId[entry.target.id].classList.add('is-active');
        }
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    sections.forEach(function (s) { io.observe(s); });
  }
})();
