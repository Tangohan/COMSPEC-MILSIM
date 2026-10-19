/**
 * Back-office — Intégrations (views/admin/organization/integrations.php).
 * Sommaire actif au défilement, choix du salon par événement (Ne pas publier / Salon commun / Autre salon),
 * recherche et filtres d'événements, réglage groupé, barre d'enregistrement, vérification des liens webhook,
 * préréglages des fournisseurs d'envoi d'e-mails.
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
    if (/^https:\/\/(?:(?:www|ptb|canary)\.)?discord(?:app)?\.com\/api\/webhooks\/\d{5,}\/[\w-]{20,}\/?$/i.test(v)) {
      kind = 'ok';
    } else if (/discord(?:app)?\.com\/channels\//i.test(v)) {
      kind = 'channel';
    } else if (/^\d{15,}$/.test(v)) {
      kind = 'id';
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

  /* ── Événements Discord ───────────────────────────────────────── */

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
    var initial = new FormData(form);
    var initialStr = serialize(initial);

    function serialize(fd) {
      var out = [];
      fd.forEach(function (v, k) {
        if (k !== '_csrf_token') {
          out.push(k + '=' + v);
        }
      });
      return out.join('&');
    }

    function modeOf(card) {
      var checked = card.querySelector('.js-discord-mode:checked');
      return checked ? checked.value : 'off';
    }

    function syncCard(card) {
      var mode = modeOf(card);
      card.setAttribute('data-mode', mode);
      var custom = card.querySelector('.js-discord-custom');
      if (custom) {
        custom.hidden = mode !== 'custom';
      }
    }

    function refresh() {
      var q = search ? search.value.trim().toLowerCase() : '';
      var total = 0;
      var visible = 0;
      events.forEach(function (card) {
        var mode = modeOf(card);
        if (mode !== 'off') {
          total++;
        }
        var matchQ = q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
        var matchF = filter === 'all' || (filter === 'on' ? mode !== 'off' : mode === 'off');
        card.hidden = !(matchQ && matchF);
        if (!card.hidden) {
          visible++;
        }
      });
      groups.forEach(function (g) {
        var cards = g.querySelectorAll('[data-discord-event]');
        var on = 0;
        var shown = 0;
        cards.forEach(function (c) {
          if (modeOf(c) !== 'off') { on++; }
          if (!c.hidden) { shown++; }
        });
        var label = g.querySelector('[data-group-on]');
        if (label) { label.textContent = String(on); }
        g.hidden = shown === 0;
        if ((q !== '' || filter !== 'all') && shown > 0) {
          g.open = true;
        }
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

    events.forEach(function (card) {
      syncCard(card);
      card.querySelectorAll('.js-discord-mode').forEach(function (radio) {
        radio.addEventListener('change', function () {
          syncCard(card);
          refresh();
          if (radio.value === 'custom' && radio.checked) {
            var input = card.querySelector('.js-discord-custom input');
            if (input && input.value === '') { input.focus(); }
          }
        });
      });
    });

    form.addEventListener('input', refresh);

    groups.forEach(function (g) {
      g.querySelectorAll('[data-group-set]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          g.open = true;
          var mode = btn.getAttribute('data-group-set');
          g.querySelectorAll('[data-discord-event]').forEach(function (card) {
            if (card.hidden) { return; }
            var radio = card.querySelector('.js-discord-mode[value="' + mode + '"]');
            if (radio) { radio.checked = true; }
            syncCard(card);
          });
          refresh();
        });
      });
    });

    if (search) {
      search.addEventListener('input', refresh);
    }
    filters.forEach(function (btn) {
      btn.addEventListener('click', function () {
        filter = btn.getAttribute('data-discord-filter') || 'all';
        filters.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
        refresh();
      });
    });

    form.addEventListener('reset', function () {
      window.setTimeout(function () {
        events.forEach(syncCard);
        form.querySelectorAll('[data-webhook-input]').forEach(checkWebhook);
        refresh();
      }, 0);
    });

    form.addEventListener('submit', function () {
      initialStr = serialize(new FormData(form));
    });

    // Essai d'un événement : il part avec le réglage enregistré, on prévient si la page a changé.
    root.querySelectorAll('[data-discord-test]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        if (serialize(new FormData(form)) !== initialStr
          && !window.confirm('Vos changements ne sont pas encore enregistrés : l’essai part dans le salon enregistré. Continuer ?')) {
          e.preventDefault();
        }
      });
    });

    window.addEventListener('beforeunload', function (e) {
      if (serialize(new FormData(form)) !== initialStr) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    refresh();
  }

  /* ── Comptes d'envoi d'e-mails ────────────────────────────────── */

  root.querySelectorAll('[data-mail-form]').forEach(function (mailForm) {
    var presets = {};
    var tips = {};
    try { presets = JSON.parse(mailForm.getAttribute('data-presets') || '{}'); } catch (e) { presets = {}; }
    try { tips = JSON.parse(mailForm.getAttribute('data-tips') || '{}'); } catch (e) { tips = {}; }
    var provider = mailForm.querySelector('[data-mail-provider]');
    var host = mailForm.querySelector('[data-mail-host]');
    var port = mailForm.querySelector('[data-mail-port]');
    var enc = mailForm.querySelector('[data-mail-encryption]');
    var tip = mailForm.querySelector('[data-mail-tip]');
    if (!provider) {
      return;
    }
    function apply(changed) {
      var p = presets[provider.value] || {};
      if (host) {
        host.placeholder = p.host ? p.host + ' (automatique)' : 'smtp.votre-hebergeur.fr';
      }
      if (changed) {
        if (port && p.port) { port.value = String(p.port); }
        if (enc && p.encryption) { enc.value = p.encryption; }
        if (host && p.host) { host.value = ''; }
      }
      if (tip && tips[provider.value]) {
        tip.textContent = tips[provider.value];
      }
    }
    provider.addEventListener('change', function () { apply(true); });
    if (port && enc) {
      port.addEventListener('change', function () {
        if (port.value === '465') { enc.value = 'ssl'; } else if (enc.value === 'ssl') { enc.value = 'tls'; }
      });
    }
    apply(false);
  });

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
