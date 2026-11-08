/**
 * Effectifs — Chaîne de commandement, sans rechargement de page.
 *  - « Remonter la chaîne » d’un membre (surbrillance et fil) ; menu d’un membre.
 *  - Fenêtre unique pour désigner un chef, appliquer une proposition, enregistrer le tableau, affecter un membre.
 *  - Écriture : fetch vers les routes habituelles (réponse JSON), puis la zone [data-chain-live] est remplacée
 *    par celle du serveur. Une erreur reste affichée dans la fenêtre : rien n’est montré comme réussi sans réponse positive.
 * Sans ce script, les formulaires de la page s’envoient normalement (repli).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-eff-chain]');
  if (!root) return;

  // Lien direct vers une section repliée (#eff-chain-setup…) : la section s’ouvre.
  function openFromHash() {
    var id = window.location.hash.slice(1);
    if (!id) return;
    var el = document.getElementById(id);
    if (el && el.tagName === 'DETAILS' && root.contains(el)) {
      el.open = true;
      el.scrollIntoView({ block: 'start' });
    }
  }
  openFromHash();
  window.addEventListener('hashchange', openFromHash);

  if (!window.EffKit || typeof window.fetch !== 'function') return;
  var K = window.EffKit;
  var canManage = root.hasAttribute('data-can-manage');
  var live = root.querySelector('[data-chain-live]');
  var path = root.querySelector('[data-chain-path]');
  var q = root.querySelector('[data-chain-q]');
  var menu = root.querySelector('[data-chain-menu]');
  var pageUrl = root.getAttribute('data-chain-url') || '';
  var assignUrl = root.getAttribute('data-chain-assign-url') || '';
  var csrf = root.getAttribute('data-chain-csrf') || '';

  var data = {};
  var units = {};
  var members = {};
  var followed = 0;

  function loadData() {
    var el = document.getElementById('eff-chain-data');
    try {
      data = el ? JSON.parse(el.textContent || '{}') : {};
    } catch (e) {
      data = {};
    }
    units = data.units || {};
    members = data.members || {};
  }
  loadData();

  /** Texte à valeurs : le modèle entier reste traduisible (i18n_dom.js reconnaît %1, %2). */
  function fill(template, a, b) {
    return String(template).replace('%1', a).replace('%2', b === undefined ? '' : b);
  }
  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }
  function toast(variant, message) {
    if (typeof window.athToast === 'function') window.athToast(variant, message);
  }
  function memberAvatar(id, size) {
    var m = members[id] || {};
    return K.avatar(m.initials, m.color, size);
  }
  function memberLabel(id) {
    return (members[id] || {}).label || 'Membre';
  }
  function memberSub(id) {
    var m = members[id] || {};
    if (m.commands && m.commands.length) return 'Chef de ' + m.commands[0];
    return m.unitName || 'Sans unité';
  }
  function chart() {
    return root.querySelector('[data-chain-chart]');
  }

  // ——— Remonter la chaîne ———
  function follow(id) {
    var ch = chart();
    if (!ch || !path) return;
    followed = id && members[id] ? id : 0;
    id = followed;
    var ids = id ? [id].concat((members[id] || {}).chain || []) : [];
    ch.classList.toggle('is-focus', ids.length > 0);
    ch.querySelectorAll('[data-chain-member]').forEach(function (el) {
      var mid = parseInt(el.getAttribute('data-chain-member'), 10);
      el.classList.toggle('is-hl', ids.indexOf(mid) !== -1);
      el.classList.toggle('is-me', mid === id);
    });
    if (!id) {
      path.hidden = true;
      path.innerHTML = '';
      return;
    }
    var steps = ids.slice().reverse();
    var html = steps.map(function (sid, i) {
      var m = members[sid] || {};
      return (i ? '<span class="effk-path__ar" aria-hidden="true">→</span>' : '')
        + '<span class="effk-path__st' + (sid === id ? ' is-me' : '') + '">' + memberAvatar(sid, 'sm')
        + '<b>' + K.esc(m.label || '') + '</b><small>' + K.esc(memberSub(sid)) + '</small></span>';
    }).join('');
    if (steps.length === 1) {
      html += '<span class="effk-path__none">Aucun chef au-dessus : chef non désigné ou sommet de l’organigramme.</span>';
    }
    path.innerHTML = '<span class="sr-only">Chaîne de ' + K.esc((members[id] || {}).label || '') + ', du sommet au membre :</span>'
      + html + '<button type="button" data-chain-clear>Effacer</button>';
    path.hidden = false;
  }
  function findMember(value) {
    var v = norm(value).trim();
    if (!v) return 0;
    var ids = Object.keys(members);
    var exact = ids.filter(function (id) { return norm(members[id].label) === v; });
    if (exact.length) return parseInt(exact[0], 10);
    if (v.length < 2) return 0;
    var part = ids.filter(function (id) { return norm(members[id].label).indexOf(v) !== -1; });
    return part.length ? parseInt(part[0], 10) : 0;
  }
  if (q) {
    q.addEventListener('input', function () { follow(findMember(q.value)); });
  }

  // ——— Échanges avec le serveur ———
  function failure(message, extra) {
    var err = new Error(message);
    err.reload = !!(extra && extra.reload);
    err.conflict = !!(extra && extra.conflict);
    return err;
  }
  function request(url, options) {
    var opts = options || {};
    opts.credentials = 'same-origin';
    opts.headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    return fetch(url, opts).catch(function () {
      throw failure('Le serveur ne répond pas : rien n’a été modifié. Vérifiez votre connexion, puis réessayez.');
    }).then(function (res) {
      var type = res.headers.get('Content-Type') || '';
      if (type.indexOf('application/json') === -1) {
        // Page de connexion ou d’erreur à la place du JSON.
        throw failure('Votre session a peut-être expiré. Rechargez la page, puis réessayez.', { reload: true });
      }
      return res.json().catch(function () {
        throw failure('Réponse illisible du serveur. Rechargez la page pour vérifier ce qui a été enregistré.', { reload: true });
      }).then(function (json) {
        if (!res.ok || !json || json.ok !== true) {
          throw failure((json && json.message) || 'Le serveur a refusé l’enregistrement. Réessayez dans un instant.', json);
        }
        return json;
      });
    });
  }

  function refreshUrl() {
    var u = new URL(pageUrl, window.location.href);
    u.searchParams.set('fragment', '1');
    if (new URL(window.location.href).searchParams.get('ecarts') === '1') u.searchParams.set('ecarts', '1');
    return u.toString();
  }

  function updateTabBadge(count) {
    var tab = document.querySelector('.bo-eff-tab[aria-current="page"]');
    if (!tab || typeof count !== 'number') return;
    var badge = tab.querySelector('.bo-eff-badge');
    if (count < 1) {
      if (badge) badge.remove();
      return;
    }
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'bo-eff-badge';
      tab.appendChild(badge);
    }
    badge.textContent = count > 99 ? '99+' : String(count);
    badge.title = fill('%1 à voir', count);
  }

  /** Remplace la zone vivante en gardant les sections ouvertes et, sauf demande, les choix non enregistrés du tableau. */
  function swap(json, keepChoices) {
    var openState = {};
    live.querySelectorAll('details[id]').forEach(function (d) { openState[d.id] = d.open; });
    var choices = {};
    if (keepChoices) {
      live.querySelectorAll('select[data-chain-initial]').forEach(function (sel) {
        if (sel.value !== sel.getAttribute('data-chain-initial')) choices[sel.id] = sel.value;
      });
    }
    live.innerHTML = json.html;
    live.querySelectorAll('details[id]').forEach(function (d) {
      if (Object.prototype.hasOwnProperty.call(openState, d.id)) d.open = openState[d.id];
    });
    Object.keys(choices).forEach(function (id) {
      var sel = document.getElementById(id);
      if (sel && sel.querySelector('option[value="' + choices[id] + '"]')) sel.value = choices[id];
    });
    loadData();
    follow(followed);
    updateBulk();
    updateTabBadge(json.missing);
  }
  function refresh(keepChoices) {
    return request(refreshUrl()).then(function (json) { swap(json, keepChoices !== false); });
  }

  function highlight(unitId) {
    if (!unitId) return;
    var el = live.querySelector('.effk-slot[data-chain-slot="' + unitId + '"]');
    if (!el) return;
    el.classList.add('is-saved');
    setTimeout(function () { el.classList.remove('is-saved'); }, 2400);
    el.focus({ preventScroll: true });
  }

  // ——— Fenêtre ———
  var dlg = root.querySelector('[data-chain-dialog]');
  var dlgBody = dlg && dlg.querySelector('[data-chain-dlg-body]');
  var dlgError = dlg && dlg.querySelector('[data-chain-dlg-error]');
  var dlgConfirm = dlg && dlg.querySelector('[data-chain-dlg-confirm]');
  var dlgBack = dlg && dlg.querySelector('[data-chain-dlg-back]');
  var onConfirm = null;
  var onBack = null;
  var busy = false;
  var opener = null;

  function setError(message, reload) {
    if (!dlgError) return;
    dlgError.hidden = !message;
    dlgError.textContent = message || '';
    if (message && reload) {
      var link = document.createElement('a');
      link.href = window.location.href;
      link.textContent = 'Recharger la page';
      dlgError.appendChild(document.createTextNode(' '));
      dlgError.appendChild(link);
    }
  }
  function setBusy(on, label) {
    busy = on;
    dlg.setAttribute('aria-busy', on ? 'true' : 'false');
    dlg.querySelectorAll('button').forEach(function (b) { b.disabled = on; });
    if (on) {
      dlgConfirm.setAttribute('data-label', dlgConfirm.textContent);
      dlgConfirm.textContent = label || 'Enregistrement…';
    } else if (dlgConfirm.hasAttribute('data-label')) {
      dlgConfirm.textContent = dlgConfirm.getAttribute('data-label');
      dlgConfirm.removeAttribute('data-label');
    }
  }
  /** o : kicker, title, body (HTML déjà échappé), confirm (libellé, absent = pas de bouton), danger, onConfirm, onBack. */
  function showDialog(o) {
    if (!dlg) return false;
    K.closePop(false);
    if (!dlg.open) opener = document.activeElement;
    dlg.querySelector('[data-chain-dlg-kicker]').textContent = o.kicker || '';
    dlg.querySelector('[data-chain-dlg-title]').textContent = o.title || '';
    dlgBody.innerHTML = o.body || '';
    setError('');
    dlgConfirm.hidden = !o.confirm;
    dlgConfirm.textContent = o.confirm || '';
    dlgConfirm.classList.toggle('is-danger', !!o.danger);
    dlgConfirm.classList.toggle('is-dark', !o.danger);
    onConfirm = o.onConfirm || null;
    onBack = o.onBack || null;
    dlgBack.hidden = !onBack;
    if (!dlg.open) {
      try {
        dlg.showModal();
      } catch (e) {
        return false;
      }
    }
    return true;
  }
  function closeDialog() {
    if (dlg && dlg.open) dlg.close();
  }
  if (dlg) {
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg && !busy) {
        closeDialog();
        return;
      }
      if (e.target.closest('[data-chain-dlg-close]')) {
        if (!busy) closeDialog();
      } else if (e.target.closest('[data-chain-dlg-back]')) {
        if (!busy && onBack) onBack();
      } else if (e.target.closest('[data-chain-dlg-confirm]')) {
        if (!busy && onConfirm) onConfirm();
      }
    });
    dlg.addEventListener('cancel', function (e) {
      if (busy) e.preventDefault();
    });
    dlg.addEventListener('close', function () {
      if (opener && document.contains(opener)) opener.focus({ preventScroll: true });
      opener = null;
    });
  }

  /** Enregistre, réactualise la page, puis ferme : la fenêtre reste ouverte avec le motif tant que le serveur refuse. */
  function commit(url, body, focusUnit, keepChoices) {
    setError('');
    setBusy(true);
    if (!body.get('_csrf_token')) body.set('_csrf_token', csrf);
    request(url, { method: 'POST', body: body }).then(function (saved) {
      return refresh(keepChoices).then(function () { return { saved: saved, fresh: true }; }, function () { return { saved: saved, fresh: false }; });
    }).then(function (r) {
      setBusy(false);
      opener = null;
      closeDialog();
      toast(r.saved.updated === 0 ? 'info' : 'success', r.saved.message);
      if (r.fresh) {
        if (focusUnit) {
          highlight(focusUnit);
        } else {
          live.setAttribute('tabindex', '-1');
          live.focus({ preventScroll: true });
        }
      } else {
        toast('warning', 'Enregistré, mais l’affichage n’a pas pu être actualisé. Rechargez la page pour voir le résultat.');
      }
    }).catch(function (err) {
      if (err.conflict) {
        // Un autre responsable est passé avant : on montre l’état réel, rien n’a été écrit.
        var done = function () {
          setBusy(false);
          closeDialog();
          toast('warning', err.message);
        };
        refresh(true).then(done, done);
        return;
      }
      setBusy(false);
      setError(err.message, err.reload);
    });
  }

  // ——— Désigner un chef : choix, puis confirmation ———
  function pickerItems(unitId, filter) {
    var u = units[unitId] || {};
    var inUnit = {};
    (u.members || []).forEach(function (id) { inUnit[id] = true; });
    var proposed = {};
    (u.suggested || []).forEach(function (id) { proposed[id] = true; });
    var f = norm(filter).trim();
    var sug = [];
    var same = [];
    var other = [];
    Object.keys(members).forEach(function (key) {
      var id = parseInt(key, 10);
      var m = members[key];
      if (m.off || (f && norm(m.label).indexOf(f) === -1)) return;
      (proposed[id] ? sug : inUnit[id] ? same : other).push(id);
    });
    var sort = function (a, b) { return String(members[a].label).localeCompare(String(members[b].label)); };
    same.sort(sort);
    other.sort(sort);
    sug.sort(function (a, b) { return u.suggested.indexOf(a) - u.suggested.indexOf(b); });
    var item = function (id) {
      var cur = id === u.chef;
      return '<button type="button" class="effk-pi" data-pick="' + id + '"' + (cur ? ' aria-current="true"' : '') + '>'
        + memberAvatar(id, 'sm') + '<span>' + K.esc(members[id].label) + '</span><small>' + K.esc(cur ? 'Chef actuel' : memberSub(id)) + '</small></button>';
    };
    var html = '';
    if (sug.length) html += '<p class="effk-pop__g">Proposés : grade attendu pour ce poste</p>' + sug.map(item).join('');
    if (same.length) html += '<p class="effk-pop__g">Membres de l’unité</p>' + same.map(item).join('');
    if (other.length) html += '<p class="effk-pop__g">Autres membres</p>' + other.slice(0, 200).map(item).join('');
    if (!sug.length && !same.length && !other.length) {
      html += '<p class="effk-pop__none">' + (f ? 'Aucun membre ne correspond à cette recherche.' : 'Aucun membre actif à désigner : affectez d’abord des membres.') + '</p>';
    }
    return html;
  }
  function openPicker(unitId) {
    var u = units[unitId];
    if (!canManage || !u) return;
    var ok = showDialog({
      kicker: u.chef ? 'Changer le chef' : 'Désigner un chef',
      title: u.name,
      body: '<label class="effk-pop__q effk-md__q"><span class="sr-only">Filtrer les membres</span><input type="search" placeholder="Filtrer les membres…" autocomplete="off" data-chain-pick-q></label>'
        + '<div class="effk-md__list" data-chain-pick-list>' + pickerItems(unitId, '') + '</div>'
        + (u.chef ? '<button type="button" class="effk-md__btn is-danger-ghost" data-pick="0">Libérer le poste</button>' : '')
    });
    if (!ok) return;
    var input = dlgBody.querySelector('[data-chain-pick-q]');
    var list = dlgBody.querySelector('[data-chain-pick-list]');
    input.addEventListener('input', function () { list.innerHTML = pickerItems(unitId, input.value); });
    dlgBody.onclick = function (e) {
      var b = e.target.closest('[data-pick]');
      if (!b) return;
      var id = parseInt(b.getAttribute('data-pick'), 10);
      if (id === u.chef) {
        closeDialog();
        return;
      }
      confirmChief(unitId, id, function () { openPicker(unitId); });
    };
    input.focus();
  }
  function confirmChief(unitId, memberId, back) {
    var u = units[unitId];
    if (!u) return;
    dlgBody.onclick = null;
    var notes = [];
    if (memberId) {
      var m = members[memberId] || {};
      if ((u.members || []).indexOf(memberId) === -1) {
        notes.push(m.unit ? fill('%1 reste affecté à %2 : il apparaîtra comme détaché.', m.label, m.unitName) : fill('%1 n’a pas d’unité : il apparaîtra comme détaché.', m.label));
      }
      if (m.commands && m.commands.length) {
        notes.push(fill('%1 commande déjà %2 et cumulera les deux postes.', m.label, m.commands.join(', ')));
      }
    } else {
      notes.push('Le poste sera vacant : ses membres remonteront au premier chef au-dessus de l’unité.');
    }
    if (u.chef && memberId) {
      notes.push(fill('%1 garde son affectation et n’est plus chef de cette unité.', memberLabel(u.chef)));
    }
    var flow = '<div class="effk-flow">'
      + '<div class="effk-flow__u"><small>Chef actuel</small><b>' + K.esc(u.chef ? memberLabel(u.chef) : 'Aucun') + '</b></div>'
      + '<span aria-hidden="true">→</span>'
      + '<div class="effk-flow__u"><small>Nouveau chef</small><b>' + K.esc(memberId ? memberLabel(memberId) : 'Aucun') + '</b></div></div>';
    var noteHtml = notes.map(function (n) { return '<p class="effk-note ' + (memberId ? 'is-info' : 'is-warn') + '"><span>' + K.esc(n) + '</span></p>'; }).join('');
    showDialog({
      kicker: 'Confirmation',
      title: memberId ? fill('Nommer %1 chef de %2', memberLabel(memberId), u.name) : fill('Libérer le poste de chef de %1', u.name),
      body: flow + noteHtml,
      confirm: memberId ? 'Confirmer la nomination' : 'Libérer le poste',
      danger: !memberId,
      onBack: back || null,
      onConfirm: function () {
        var fd = new FormData();
        fd.set('commanders[' + unitId + ']', String(memberId));
        fd.set('expected_commanders[' + unitId + ']', String(u.chef || 0));
        commit(pageUrl, fd, unitId, true);
      }
    });
    dlgConfirm.focus();
  }

  // ——— Proposition ———
  function confirmProposal(form) {
    var titleEl = form.parentNode.querySelector('[data-chain-confirm-title]');
    var bodyEl = form.querySelector('[data-chain-confirm-body]');
    var submit = form.querySelector('button[type="submit"]');
    var unit = parseInt(form.getAttribute('data-unit'), 10);
    var isOk = showDialog({
      kicker: 'Appliquer la proposition',
      title: titleEl ? titleEl.textContent : '',
      body: '<p class="effk-md__txt">' + K.esc(bodyEl ? bodyEl.textContent : '') + '</p>',
      confirm: submit ? submit.textContent : 'Confirmer',
      onConfirm: function () { commit(form.action, new FormData(form), unit, true); }
    });
    if (isOk) dlgConfirm.focus();
    else form.submit();
  }

  // ——— Affecter ou muter un membre ———
  function openAssign(memberId) {
    var m = members[memberId];
    if (!m || !canManage) return;
    var options = '<option value="">Choisir une unité…</option>';
    (data.order || []).forEach(function (id) {
      var u = units[id];
      if (!u || id === m.unit) return;
      options += '<option value="' + id + '">' + new Array((u.depth || 0) + 1).join('  ') + K.esc(u.name) + '</option>';
    });
    var ok = showDialog({
      kicker: m.unit ? 'Mutation' : 'Affectation',
      title: m.label,
      body: '<div class="effk-md__who">' + memberAvatar(memberId, 'lg') + '<div><b>' + K.esc(m.label) + '</b><small>' + K.esc(m.unitName ? 'Actuellement : ' + m.unitName : 'Sans unité pour le moment') + '</small></div></div>'
        + '<div class="effk-md__fl"><label for="eff-chain-assign-unit">Unité</label><select id="eff-chain-assign-unit" class="effk-sel" data-chain-assign-unit>' + options + '</select></div>'
        + '<div class="effk-md__fl"><label for="eff-chain-assign-reason">Motif</label><textarea id="eff-chain-assign-reason" maxlength="255" data-chain-assign-reason placeholder="Renfort, fin de probation, demande du membre…"></textarea>'
        + '<small>Facultatif. Le changement prend effet aujourd’hui et rejoint l’historique d’affectation du dossier.</small></div>',
      confirm: m.unit ? 'Confirmer la mutation' : 'Confirmer l’affectation',
      onConfirm: function () {
        var unitId = dlgBody.querySelector('[data-chain-assign-unit]').value;
        if (!unitId) {
          setError('Choisissez l’unité de destination.');
          return;
        }
        var fd = new FormData();
        fd.set('unit_id', unitId);
        fd.set('reason', dlgBody.querySelector('[data-chain-assign-reason]').value);
        fd.set('return_url', pageUrl);
        commit(assignUrl.replace('__ID__', String(memberId)), fd, 0, true);
      }
    });
    if (ok) dlgBody.querySelector('[data-chain-assign-unit]').focus();
  }

  // ——— Tableau des chefs ———
  function bulkForm() {
    return live.querySelector('[data-chain-bulk]');
  }
  function bulkChanges() {
    var form = bulkForm();
    if (!form) return [];
    return Array.prototype.filter.call(form.querySelectorAll('select[data-chain-initial]'), function (sel) {
      return sel.value !== sel.getAttribute('data-chain-initial');
    });
  }
  function updateBulk() {
    var form = bulkForm();
    if (!form) return;
    var n = bulkChanges().length;
    var count = form.querySelector('[data-chain-bulk-count]');
    var save = form.querySelector('[data-chain-bulk-save]');
    var reset = form.querySelector('[data-chain-bulk-reset]');
    if (count) count.textContent = n ? (n > 1 ? fill('%1 modifications à enregistrer', n) : 'Une modification à enregistrer') : 'Aucune modification en attente.';
    if (save) save.disabled = n === 0;
    if (reset) reset.hidden = n === 0;
  }
  updateBulk();
  function optionLabel(sel, value) {
    var opt = sel.querySelector('option[value="' + value + '"]');
    return opt ? opt.textContent.trim() : '—';
  }
  function confirmBulk(form) {
    var changes = bulkChanges();
    if (!changes.length) {
      toast('info', 'Aucune modification à enregistrer.');
      return;
    }
    var rows = changes.map(function (sel) {
      var unit = units[sel.getAttribute('data-unit')] || {};
      return '<li><b>' + K.esc(unit.name || '') + '</b><span>' + K.esc(optionLabel(sel, sel.getAttribute('data-chain-initial'))) + ' → ' + K.esc(optionLabel(sel, sel.value)) + '</span></li>';
    }).join('');
    var isOk = showDialog({
      kicker: 'Confirmation',
      title: changes.length > 1 ? fill('Enregistrer %1 changements de chef', changes.length) : 'Enregistrer un changement de chef',
      body: '<ul class="effk-md__changes">' + rows + '</ul>',
      confirm: 'Enregistrer les chefs',
      onConfirm: function () { commit(form.action, new FormData(form), 0, false); }
    });
    if (isOk) dlgConfirm.focus();
    else form.submit();
  }

  // ——— Filtre « personnes avec un écart » ———
  function setGapsFilter(on) {
    var u = new URL(window.location.href);
    var previous = u.toString();
    if (on) u.searchParams.set('ecarts', '1'); else u.searchParams.delete('ecarts');
    u.hash = 'eff-chain-people-panel';
    window.history.replaceState(null, '', u.toString());
    live.setAttribute('aria-busy', 'true');
    refresh(true).then(function () {
      var panel = document.getElementById('eff-chain-people-panel');
      if (panel) {
        panel.open = true;
        panel.scrollIntoView({ block: 'start' });
      }
    }).catch(function (err) {
      window.history.replaceState(null, '', previous);
      toast('error', err.message);
    }).then(function () {
      live.removeAttribute('aria-busy');
    });
  }

  // ——— Menu d’un membre ———
  function openMenu(anchor, memberId) {
    var m = members[memberId];
    if (!menu || !m) return;
    menu.querySelector('[data-chain-menu-title]').textContent = m.label;
    var list = menu.querySelector('[data-chain-menu-list]');
    var html = '<button type="button" class="effk-pi" data-act="follow">Remonter sa chaîne</button>'
      + '<a class="effk-pi" href="' + K.esc(m.url) + '">Ouvrir sa fiche</a>';
    if (canManage && m.unit && units[m.unit] && units[m.unit].chef !== memberId) {
      html += '<button type="button" class="effk-pi" data-act="chef">' + K.esc(fill('Nommer chef de %1', units[m.unit].name)) + '</button>';
    }
    if (canManage) {
      html += '<button type="button" class="effk-pi" data-act="move">Muter vers une autre unité</button>';
    }
    list.innerHTML = html;
    list.onclick = function (e) {
      var b = e.target.closest('[data-act]');
      if (!b) return;
      var act = b.getAttribute('data-act');
      if (act === 'follow') {
        K.closePop(false);
        if (q) q.value = m.label;
        follow(memberId);
        if (path) path.scrollIntoView({ block: 'nearest' });
      } else if (act === 'chef') {
        K.closePop(false);
        confirmChief(m.unit, memberId);
      } else if (act === 'move') {
        K.closePop(false);
        openAssign(memberId);
      }
    };
    K.pop(menu, anchor);
  }

  // ——— Délégation : la zone vivante est remplacée, les écouteurs restent sur la racine ———
  function plainClick(e) {
    return e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey;
  }
  root.addEventListener('click', function (e) {
    if (dlg && dlg.contains(e.target)) return;
    if (e.target.closest('[data-chain-clear]')) {
      if (q) q.value = '';
      follow(0);
      if (q) q.focus();
      return;
    }
    if (!plainClick(e)) return;
    var slot = e.target.closest('[data-chain-slot]');
    if (slot && canManage) {
      e.preventDefault();
      openPicker(parseInt(slot.getAttribute('data-chain-slot'), 10));
      return;
    }
    var assign = e.target.closest('[data-chain-assign]');
    if (assign && canManage) {
      var assignId = parseInt(assign.getAttribute('data-chain-assign'), 10);
      if (members[assignId]) {
        e.preventDefault();
        openAssign(assignId);
      }
      return;
    }
    var filter = e.target.closest('[data-chain-filter]');
    if (filter) {
      e.preventDefault();
      setGapsFilter(filter.getAttribute('data-chain-filter') === '1');
      return;
    }
    var act = e.target.closest('.adq-act[href*="#eff-chain-setup"]');
    if (act && canManage) {
      var issue = act.closest('[data-adq-unit]');
      var issueUnit = issue ? parseInt(issue.getAttribute('data-adq-unit'), 10) : 0;
      if (units[issueUnit]) {
        e.preventDefault();
        openPicker(issueUnit);
      }
      return;
    }
    if (e.target.closest('[data-chain-bulk-reset]')) {
      bulkChanges().forEach(function (sel) { sel.value = sel.getAttribute('data-chain-initial'); });
      updateBulk();
      return;
    }
    var chip = e.target.closest('[data-chain-chip]');
    if (chip) {
      e.preventDefault();
      chip.setAttribute('aria-haspopup', 'dialog');
      openMenu(chip, parseInt(chip.getAttribute('data-chain-chip'), 10));
    }
  });
  root.addEventListener('submit', function (e) {
    var form = e.target;
    if (!dlg || !form.matches) return;
    if (form.matches('[data-chain-proposal]')) {
      e.preventDefault();
      confirmProposal(form);
    } else if (form.matches('[data-chain-bulk]')) {
      e.preventDefault();
      confirmBulk(form);
    }
  });
  root.addEventListener('change', function (e) {
    if (e.target.matches && e.target.matches('select[data-chain-initial]')) updateBulk();
  });
})();
