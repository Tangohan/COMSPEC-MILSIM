/* COMSPEC ATAK — Menu contextuel opérateurs (cartes / tableau / marqueurs) */
window.ATAKUnitMenu = (function () {
  var menuEl = null;
  var selectEl = null;
  var notesEl = null;
  var activeUnit = null;
  var selectResolve = null;
  var notesResolve = null;
  var longPressTimer = null;
  var longPressTarget = null;

  function caps() {
    return window.ATAK_CAPS || {};
  }

  function getApiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function parseExtra(u) {
    try {
      return typeof u.extra === 'string' ? JSON.parse(u.extra) : (u.extra || {});
    } catch (e) {
      return {};
    }
  }

  function tocFromUnit(unit) {
    var ex = parseExtra(unit || {});
    return {
      radio: String(ex.toc_radio || '').trim(),
      vehicle: String(ex.toc_vehicle || '').trim(),
      note: String(ex.toc_note || '').trim()
    };
  }

  function gridLabel(unit) {
    if (!unit) return '—';
    if (unit.grid_ref) return String(unit.grid_ref).trim();
    var x = unit.pos_x != null ? Math.round(Number(unit.pos_x)) : null;
    var y = unit.pos_y != null ? Math.round(Number(unit.pos_y)) : null;
    if (x != null && y != null && !isNaN(x) && !isNaN(y)) return x + ' ' + y;
    return '—';
  }

  function hasValidPos(unit) {
    if (!unit) return false;
    if (window.ATAKUnits && window.ATAKUnits.hasValidPosition) {
      return window.ATAKUnits.hasValidPosition(unit);
    }
    var x = unit.pos_x != null ? Number(unit.pos_x) : NaN;
    var y = unit.pos_y != null ? Number(unit.pos_y) : NaN;
    if (isNaN(x) || isNaN(y)) {
      var parts = String(unit.grid_ref || '').trim().split(/\s+/);
      if (parts.length < 2) return false;
      x = parseFloat(parts[0]);
      y = parseFloat(parts[1]);
    }
    if (isNaN(x) || isNaN(y)) return false;
    if (Math.abs(x) < 0.5 && Math.abs(y) < 0.5) return false;
    return true;
  }

  function linkedFiche(unit) {
    if (!unit) return null;
    var key = String(unit.call_sign || '').toUpperCase().trim();
    if (key && window.ATAK_CALLSIGN_TO_USER && window.ATAK_CALLSIGN_TO_USER[key]) {
      return window.ATAK_CALLSIGN_TO_USER[key];
    }
    return null;
  }

  function menuItem(action, label, opts) {
    opts = opts || {};
    var disabled = !!opts.disabled;
    var cls = 'atak-ctx-menu__item';
    if (opts.muted) cls += ' atak-ctx-menu__item--muted';
    if (opts.danger) cls += ' atak-ctx-menu__item--danger';
    if (disabled) cls += ' atak-ctx-menu__item--disabled';
    var title = opts.title ? ' title="' + escapeHtml(opts.title) + '"' : '';
    return '<button type="button" class="' + cls + '" data-action="' + escapeHtml(action) + '" role="menuitem"' +
      (disabled ? ' disabled aria-disabled="true"' : '') + title + '>' + escapeHtml(label) + '</button>';
  }

  function ensureMenu() {
    if (menuEl) return menuEl;
    menuEl = document.createElement('div');
    menuEl.id = 'atak-unit-ctx-menu';
    menuEl.className = 'atak-ctx-menu atak-unit-ctx-menu';
    menuEl.setAttribute('role', 'menu');
    menuEl.hidden = true;
    menuEl.innerHTML = '<div class="atak-ctx-menu__coords" id="atak-unit-ctx-coords"></div><div id="atak-unit-ctx-items"></div>';
    document.body.appendChild(menuEl);
    menuEl.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-action]');
      if (!btn || btn.disabled) return;
      e.preventDefault();
      e.stopPropagation();
      var action = btn.getAttribute('data-action');
      hideMenu();
      runAction(action);
    });
    return menuEl;
  }

  function hideMenu() {
    if (menuEl) menuEl.hidden = true;
  }

  function positionMenu(clientX, clientY) {
    ensureMenu();
    menuEl.hidden = false;
    var pad = 8;
    var mw = menuEl.offsetWidth || 240;
    var mh = menuEl.offsetHeight || 280;
    var x = clientX;
    var y = clientY;
    if (x + mw + pad > window.innerWidth) x = window.innerWidth - mw - pad;
    if (y + mh + pad > window.innerHeight) y = window.innerHeight - mh - pad;
    if (x < pad) x = pad;
    if (y < pad) y = pad;
    menuEl.style.left = x + 'px';
    menuEl.style.top = y + 'px';
  }

  function renderItems(unit) {
    var c = caps();
    var fiche = linkedFiche(unit);
    var canCenter = hasValidPos(unit);
    var canPing = c.canPing !== false && canCenter;
    var canRename = !!c.canRenameUnit;
    var canNotes = !!c.canEditUnitNotes || !!c.canRenameUnit || !!c.loggedIn;
    var canView = !!c.canViewPersonnel;
    var canLink = !!c.canLinkPersonnel;
    var canDelete = canDeleteUnit(unit);
    var liveStatus = unitLiveStatus(unit);
    var inLiaison = liveStatus === 'linked' || liveStatus === 'delayed';
    var canDisconnect = canDeleteUnit(unit) && inLiaison;
    var html = '';

    html += menuItem('center', 'Centrer sur la carte', {
      disabled: !canCenter,
      title: canCenter ? '' : 'Position indisponible pour ce contact'
    });
    html += menuItem('copy-grid', 'Copier la grille', {
      muted: true,
      disabled: gridLabel(unit) === '—'
    });
    html += '<div class="atak-ctx-menu__sep" role="separator"></div>';

    html += menuItem('notes', 'Notes du contact…', {
      disabled: !canNotes,
      title: canNotes
        ? 'Fréquence radio, véhicule et note visibles par tous les opérateurs'
        : 'Connexion requise pour modifier les notes'
    });
    html += menuItem('rename', 'Renommer l’indicatif', {
      disabled: !canRename,
      title: canRename ? '' : 'Connexion requise pour renommer'
    });
    html += menuItem('ping', 'Envoyer un ping ici', {
      disabled: !canPing,
      title: canPing ? '' : (canCenter ? 'Action indisponible' : 'Position indisponible')
    });
    html += menuItem('vibrate', 'Faire vibrer le terminal', {
      disabled: !inLiaison,
      title: !inLiaison
        ? 'Hors liaison — vibration impossible'
        : 'Fait vibrer le téléphone ATAK du joueur en jeu'
    });
    html += menuItem('notify', 'Envoyer une notification…', {
      disabled: !inLiaison,
      title: !inLiaison
        ? 'Hors liaison — notification impossible'
        : 'Affiche une notification cliquable sur le terminal Athena'
    });
    html += '<div class="atak-ctx-menu__sep" role="separator"></div>';
    html += menuItem('helmet-snap', 'Demander une photo casque', {
      disabled: !inLiaison,
      title: !inLiaison
        ? 'Hors liaison — demande impossible'
        : 'Le terminal capture et envoie un cliché vers le poste de commandement'
    });
    html += menuItem('helmet-snap-hd', 'Demander une photo casque HD', {
      disabled: !inLiaison,
      title: !inLiaison
        ? 'Hors liaison — demande impossible'
        : 'Capture haute définition — peut prendre quelques secondes de plus'
    });
    html += menuItem('helmet-stream', 'Vue casque en temps réel', {
      disabled: true,
      title: 'Pas encore au point — utilisez une photo depuis le terminal ATAK, ou l’onglet Photos'
    });
    html += menuItem('chat', 'Ouvrir la messagerie', { muted: !inLiaison, disabled: !inLiaison, title: inLiaison ? '' : 'Disponible lorsque le contact est en liaison' });

    html += '<div class="atak-ctx-menu__sep" role="separator"></div>';
    html += menuItem('assign-dest', 'Assigner une destination', {
      disabled: !canCenter,
      title: canCenter ? 'Pointer un repère, une unité ou un point sur la carte' : 'Position indisponible'
    });
    var asg = unit && (unit.navigation || unit.assignment);
    if (asg && asg.id && asg.status !== 'detached') {
      html += menuItem('change-dest', 'Changer la destination', { disabled: !canCenter });
      html += menuItem('detach-dest', 'Détacher la destination');
    }

    html += '<div class="atak-ctx-menu__sep" role="separator"></div>';

    if (fiche && fiche.url) {
      html += menuItem('open-fiche', 'Ouvrir la fiche personnel', {
        disabled: !canView,
        title: canView ? '' : 'Consultation des fiches non autorisée pour votre rôle'
      });
      html += menuItem('link-fiche', 'Changer la fiche liée…', {
        disabled: !canLink,
        title: canLink ? '' : 'Réservé aux responsables RH / administration'
      });
    } else {
      html += menuItem('link-fiche', 'Lier à une fiche de perso…', {
        disabled: !canLink,
        title: canLink ? '' : 'Réservé aux responsables RH / administration'
      });
      if (!canView && !canLink) {
        html += menuItem('noop', 'Fiches personnel — accès restreint', {
          disabled: true,
          muted: true,
          title: 'Votre rôle ne permet pas de consulter ou lier les fiches'
        });
      }
    }

    html += '<div class="atak-ctx-menu__sep" role="separator"></div>';
    html += menuItem('disconnect', 'Couper la liaison', {
      danger: true,
      disabled: !canDisconnect,
      title: canDisconnect
        ? 'Marquer ce contact hors liaison (carte et effectifs)'
        : (!inLiaison
          ? 'Hors liaison - vibration impossible'
          : 'Réservé à l’état-major, à l’administration, ou à l’opérateur concerné')
    });
    html += menuItem('delete', 'Supprimer', {
      danger: true,
      disabled: !canDelete,
      title: canDelete
        ? 'Retirer ce contact de la carte et des effectifs'
        : 'Réservé à l’état-major, à l’administration, ou à l’opérateur concerné'
    });

    return html;
  }

  function unitLiveStatus(unit) {
    if (window.ATAKUnits && window.ATAKUnits.resolveLiveStatus) {
      return window.ATAKUnits.resolveLiveStatus(unit);
    }
    return String((unit && unit.status) || '').toLowerCase();
  }

  function ownCallsigns() {
    var u = window.ATAK_USER || {};
    var out = [];
    [u.callsign, u.armaCallsign].forEach(function (cs) {
      var t = String(cs || '').trim();
      if (t && out.indexOf(t.toUpperCase()) < 0) out.push(t.toUpperCase());
    });
    return out;
  }

  function canDeleteUnit(unit) {
    var c = caps();
    if (c.canDeleteUnitStaff) return true;
    if (!c.canDeleteOwnUnit && !c.loggedIn) return false;
    if (!c.canDeleteOwnUnit) return false;
    var unitCs = String((unit && unit.call_sign) || '').trim().toUpperCase();
    if (!unitCs) return false;
    return ownCallsigns().indexOf(unitCs) >= 0;
  }

  function showMenu(unit, clientX, clientY) {
    if (!unit) return;
    if (window.ATAKContextMenu && window.ATAKContextMenu.hide) {
      window.ATAKContextMenu.hide();
    }
    activeUnit = unit;
    ensureMenu();
    var coords = document.getElementById('atak-unit-ctx-coords');
    if (coords) {
      coords.textContent = (unit.call_sign || 'Contact') + ' — grille ' + gridLabel(unit);
    }
    var items = document.getElementById('atak-unit-ctx-items');
    if (items) items.innerHTML = renderItems(unit);
    positionMenu(clientX || 0, clientY || 0);
  }

  function openPrompt(title, hint, placeholder, defaultValue) {
    if (window.ATAKContextMenu && window.ATAKContextMenu.openPrompt) {
      return window.ATAKContextMenu.openPrompt(title, hint, placeholder, defaultValue);
    }
    var v = window.prompt(title + (hint ? '\n' + hint : ''), defaultValue || '');
    return Promise.resolve(v);
  }

  function ensureNotesModal() {
    if (notesEl) return notesEl;
    notesEl = document.createElement('div');
    notesEl.id = 'atak-unit-notes-modal';
    notesEl.className = 'atak-input-modal';
    notesEl.hidden = true;
    notesEl.setAttribute('aria-hidden', 'true');
    notesEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-atak-notes-cancel></div>' +
      '<div class="atak-input-modal__box atak-unit-notes-modal" role="dialog" aria-modal="true" aria-labelledby="atak-unit-notes-title">' +
      '<h3 class="atak-input-modal__title" id="atak-unit-notes-title">Notes du contact</h3>' +
      '<p class="atak-input-modal__hint" id="atak-unit-notes-hint">Visible par tous les opérateurs de la session carte.</p>' +
      '<label class="atak-unit-notes-field">' +
      '<span>Fréquence radio</span>' +
      '<input type="text" id="atak-unit-notes-radio" class="atak-input-modal__field" maxlength="80" autocomplete="off" placeholder="Ex. 78,5 ou NET ALPHA" />' +
      '</label>' +
      '<label class="atak-unit-notes-field">' +
      '<span>Véhicule</span>' +
      '<input type="text" id="atak-unit-notes-vehicle" class="atak-input-modal__field" maxlength="80" autocomplete="off" placeholder="Ex. Hunter, HEMTT, UH-80" />' +
      '</label>' +
      '<label class="atak-unit-notes-field">' +
      '<span>Note</span>' +
      '<textarea id="atak-unit-notes-note" class="atak-input-modal__field atak-input-modal__field--area" maxlength="500" rows="3" placeholder="Consignes, matériel, remarque…"></textarea>' +
      '</label>' +
      '<div class="atak-input-modal__actions">' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-atak-notes-cancel>Annuler</button>' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--primary" id="atak-unit-notes-save">Enregistrer</button>' +
      '</div></div>';
    document.body.appendChild(notesEl);
    notesEl.querySelectorAll('[data-atak-notes-cancel]').forEach(function (el) {
      el.addEventListener('click', function () { closeNotes(null); });
    });
    document.getElementById('atak-unit-notes-save').addEventListener('click', function () {
      closeNotes({
        toc_radio: (document.getElementById('atak-unit-notes-radio') || {}).value || '',
        toc_vehicle: (document.getElementById('atak-unit-notes-vehicle') || {}).value || '',
        toc_note: (document.getElementById('atak-unit-notes-note') || {}).value || ''
      });
    });
    notesEl.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        closeNotes(null);
      }
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        document.getElementById('atak-unit-notes-save').click();
      }
    });
    return notesEl;
  }

  function openNotesModal(unit) {
    ensureNotesModal();
    var toc = tocFromUnit(unit);
    var hint = document.getElementById('atak-unit-notes-hint');
    if (hint) {
      hint.textContent = (unit && unit.call_sign ? unit.call_sign + ' — ' : '') +
        'Fréquence, véhicule et note visibles par tous les opérateurs de la session.';
    }
    var radio = document.getElementById('atak-unit-notes-radio');
    var vehicle = document.getElementById('atak-unit-notes-vehicle');
    var note = document.getElementById('atak-unit-notes-note');
    if (radio) radio.value = toc.radio;
    if (vehicle) vehicle.value = toc.vehicle;
    if (note) note.value = toc.note;
    notesEl.hidden = false;
    notesEl.setAttribute('aria-hidden', 'false');
    setTimeout(function () { if (radio) radio.focus(); }, 30);
    return new Promise(function (resolve) {
      notesResolve = resolve;
    });
  }

  function closeNotes(value) {
    if (!notesEl) return;
    notesEl.hidden = true;
    notesEl.setAttribute('aria-hidden', 'true');
    var cb = notesResolve;
    notesResolve = null;
    if (cb) cb(value);
  }

  function ensureSelectModal() {
    if (selectEl) return selectEl;
    selectEl = document.createElement('div');
    selectEl.id = 'atak-personnel-modal';
    selectEl.className = 'atak-input-modal';
    selectEl.hidden = true;
    selectEl.setAttribute('aria-hidden', 'true');
    selectEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-atak-personnel-cancel></div>' +
      '<div class="atak-input-modal__box atak-personnel-picker" role="dialog" aria-modal="true" aria-labelledby="atak-personnel-title">' +
      '<h3 class="atak-input-modal__title" id="atak-personnel-title">Lier à une fiche</h3>' +
      '<p class="atak-input-modal__hint">Choisissez la fiche personnel Athena à associer à ce contact.</p>' +
      '<input type="search" class="atak-input-modal__field" id="atak-personnel-search" placeholder="Rechercher un nom ou un indicatif…" autocomplete="off" />' +
      '<div class="atak-personnel-picker__list" id="atak-personnel-list" role="listbox"></div>' +
      '<label class="atak-personnel-picker__opt">' +
      '<input type="checkbox" id="atak-personnel-apply-cs" checked />' +
      '<span>Remplacer l’indicatif du contact par celui de la fiche (si renseigné)</span>' +
      '</label>' +
      '<div class="atak-input-modal__actions">' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-atak-personnel-cancel>Annuler</button>' +
      '</div></div>';
    document.body.appendChild(selectEl);
    selectEl.querySelectorAll('[data-atak-personnel-cancel]').forEach(function (el) {
      el.addEventListener('click', function () { closeSelect(null); });
    });
    document.getElementById('atak-personnel-search').addEventListener('input', function () {
      filterSelectList(this.value);
    });
    document.getElementById('atak-personnel-search').addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        closeSelect(null);
      }
    });
    return selectEl;
  }

  var selectItemsCache = [];

  function filterSelectList(q) {
    var list = document.getElementById('atak-personnel-list');
    if (!list) return;
    q = String(q || '').toLowerCase().trim();
    var filtered = !q ? selectItemsCache : selectItemsCache.filter(function (it) {
      var hay = ((it.label || '') + ' ' + (it.callsign || '') + ' ' + (it.characterName || '')).toLowerCase();
      return hay.indexOf(q) >= 0;
    });
    if (!filtered.length) {
      list.innerHTML = '<p class="atak-personnel-picker__empty">Aucune fiche ne correspond.</p>';
      return;
    }
    list.innerHTML = filtered.map(function (it) {
      var sub = [];
      if (it.characterName) sub.push(it.characterName);
      if (it.callsign) sub.push(it.callsign);
      return '<button type="button" class="atak-personnel-picker__item" role="option" data-user-id="' + escapeHtml(it.id) + '">' +
        '<span class="atak-personnel-picker__name">' + escapeHtml(it.label) + '</span>' +
        (sub.length ? '<span class="atak-personnel-picker__sub">' + escapeHtml(sub.join(' · ')) + '</span>' : '') +
        '</button>';
    }).join('');
    list.querySelectorAll('[data-user-id]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var applyCs = document.getElementById('atak-personnel-apply-cs');
        closeSelect({
          userId: parseInt(btn.getAttribute('data-user-id'), 10),
          applyProfileCallsign: applyCs ? applyCs.checked : true
        });
      });
    });
  }

  function openPersonnelSelect() {
    ensureSelectModal();
    var list = document.getElementById('atak-personnel-list');
    var search = document.getElementById('atak-personnel-search');
    if (list) list.innerHTML = '<p class="atak-personnel-picker__empty">Chargement des fiches…</p>';
    if (search) search.value = '';
    selectEl.hidden = false;
    selectEl.setAttribute('aria-hidden', 'false');
    setTimeout(function () { if (search) search.focus(); }, 30);

    var base = getApiBase();
    fetch(base + '/api/atak/personnel', { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('load');
        return r.json();
      })
      .then(function (data) {
        if (data && data.canLink === false) {
          closeSelect(null);
          if (window.ATAKShowError) {
            window.ATAKShowError(data.message || 'Vous n’avez pas l’autorisation de lier une fiche.');
          }
          return;
        }
        selectItemsCache = Array.isArray(data && data.items) ? data.items : [];
        filterSelectList('');
      })
      .catch(function () {
        if (list) list.innerHTML = '<p class="atak-personnel-picker__empty">Impossible de charger les fiches.</p>';
        if (window.ATAKShowError) window.ATAKShowError('Impossible de charger les fiches personnel.');
      });

    return new Promise(function (resolve) {
      selectResolve = resolve;
    });
  }

  function closeSelect(value) {
    if (!selectEl) return;
    selectEl.hidden = true;
    selectEl.setAttribute('aria-hidden', 'true');
    var cb = selectResolve;
    selectResolve = null;
    if (cb) cb(value);
  }

  function patchUnit(unitId, payload) {
    var base = getApiBase();
    return fetch(base + '/api/units/' + encodeURIComponent(unitId), {
      method: 'PATCH',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      return r.json().then(function (body) {
        if (!r.ok) {
          var err = new Error((body && body.message) || 'update_failed');
          err.body = body;
          throw err;
        }
        return body;
      });
    });
  }

  function deleteUnit(unitId) {
    var base = getApiBase();
    return fetch(base + '/api/units/' + encodeURIComponent(unitId), {
      method: 'DELETE',
      credentials: 'include'
    }).then(function (r) {
      if (r.status === 204 || r.ok) return true;
      return r.json().then(function (body) {
        var err = new Error((body && body.message) || 'delete_failed');
        err.body = body;
        throw err;
      }).catch(function (e) {
        if (e && e.body) throw e;
        var err = new Error('delete_failed');
        throw err;
      });
    });
  }

  function disconnectUnit(unitId) {
    var base = getApiBase();
    return fetch(base + '/api/units/' + encodeURIComponent(unitId) + '/disconnect', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: '{}'
    }).then(function (r) {
      return r.json().then(function (body) {
        if (!r.ok) {
          var err = new Error((body && body.message) || 'disconnect_failed');
          err.body = body;
          throw err;
        }
        return body;
      });
    });
  }

  function vibrateUnit(unitId) {
    var base = getApiBase();
    return fetch(base + '/api/units/' + encodeURIComponent(unitId) + '/vibrate', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: '{}'
    }).then(function (r) {
      return r.json().then(function (body) {
        if (!r.ok) {
          var err = new Error((body && body.message) || 'vibrate_failed');
          err.body = body;
          throw err;
        }
        return body;
      });
    });
  }

  function requestHelmetMedia(unitId, mode) {
    var base = getApiBase();
    return fetch(base + '/api/units/' + encodeURIComponent(unitId) + '/request-helmet-media', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ mode: mode || 'snap' })
    }).then(function (r) {
      return r.json().then(function (body) {
        if (!r.ok) {
          var err = new Error((body && body.message) || 'helmet_media_failed');
          err.body = body;
          throw err;
        }
        return body;
      });
    });
  }

  function notifyUnit(unitId, message) {
    var base = getApiBase();
    return fetch(base + '/api/units/' + encodeURIComponent(unitId) + '/notify', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: String(message || '').trim() })
    }).then(function (r) {
      return r.json().then(function (body) {
        if (!r.ok) {
          var err = new Error((body && body.message) || 'notify_failed');
          err.body = body;
          throw err;
        }
        return body;
      });
    });
  }

  function rememberLink(callsign, linked) {
    if (!linked || !linked.userId) return;
    if (!window.ATAK_CALLSIGN_TO_USER) window.ATAK_CALLSIGN_TO_USER = {};
    var key = String(callsign || linked.callsign || '').toUpperCase().trim();
    if (!key) return;
    window.ATAK_CALLSIGN_TO_USER[key] = {
      userId: linked.userId,
      url: linked.url || ''
    };
  }

  function refreshUnits() {
    if (window.ATAKUnits && window.ATAKUnits.fetchUnits) {
      window.ATAKUnits.fetchUnits();
    }
  }

  function openChatTab(unit) {
    var btn = document.querySelector('.atak-tab[data-tab="chat"]');
    if (btn) btn.click();
    var input = document.getElementById('atak-chat-input') || document.querySelector('#tab-chat textarea, #tab-chat input[type="text"]');
    if (input && unit && unit.call_sign) {
      var prefix = '@' + unit.call_sign + ' ';
      if (!input.value || input.value.indexOf(prefix) !== 0) {
        input.value = prefix + (input.value || '');
      }
      try { input.focus(); } catch (e) {}
    }
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('Messagerie ouverte' + (unit && unit.call_sign ? ' — ' + unit.call_sign : ''));
    }
  }

  function runAction(action) {
    var unit = activeUnit;
    if (!unit) return;

    if (action === 'center') {
      if (!hasValidPos(unit)) return;
      var x = unit.pos_x != null ? parseFloat(unit.pos_x) : NaN;
      var y = unit.pos_y != null ? parseFloat(unit.pos_y) : NaN;
      if (isNaN(x) || isNaN(y)) {
        var parts = String(unit.grid_ref || '').trim().split(/\s+/);
        x = parseFloat(parts[0]);
        y = parseFloat(parts[1]);
      }
      if (!isNaN(x) && !isNaN(y) && window.ATAKMap && window.ATAKMap.centerOn) {
        window.ATAKMap.centerOn(y, x);
      }
      return;
    }

    if (action === 'copy-grid') {
      var text = gridLabel(unit);
      if (text === '—') return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification('Grille copiée : ' + text);
        }).catch(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification(text);
        });
      } else if (window.ATAKShowNotification) {
        window.ATAKShowNotification(text);
      }
      return;
    }

    if (action === 'chat') {
      openChatTab(unit);
      return;
    }

    if (action === 'assign-dest' || action === 'change-dest') {
      if (window.ATAKAssignments && window.ATAKAssignments.beginPick) {
        window.ATAKAssignments.beginPick(unit);
      }
      return;
    }
    if (action === 'detach-dest') {
      var asg = unit.navigation || unit.assignment;
      if (asg && asg.id && window.ATAKAssignments && window.ATAKAssignments.detach) {
        window.ATAKAssignments.detach(asg.id).then(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification('Destination détachée');
          if (window.ATAKUnits && window.ATAKUnits.fetchUnits) window.ATAKUnits.fetchUnits();
        });
      }
      return;
    }

    if (action === 'open-fiche') {
      var fiche = linkedFiche(unit);
      if (fiche && fiche.url) {
        window.open(fiche.url, '_blank', 'noopener');
      }
      return;
    }

    if (action === 'notes') {
      var cNotes = caps();
      if (!(cNotes.canEditUnitNotes || cNotes.canRenameUnit || cNotes.loggedIn)) return;
      if (!unit.id) {
        if (window.ATAKShowError) window.ATAKShowError('Contact non enregistré — impossible d’ajouter des notes.');
        return;
      }
      openNotesModal(unit).then(function (vals) {
        if (!vals) return;
        patchUnit(unit.id, {
          toc_radio: String(vals.toc_radio || '').trim(),
          toc_vehicle: String(vals.toc_vehicle || '').trim(),
          toc_note: String(vals.toc_note || '').trim()
        }).then(function () {
          if (window.ATAKShowNotification) {
            window.ATAKShowNotification('Notes enregistrées' + (unit.call_sign ? ' — ' + unit.call_sign : ''));
          }
          refreshUnits();
        }).catch(function (err) {
          if (window.ATAKShowError) {
            window.ATAKShowError((err && err.body && err.body.message) || 'Impossible d’enregistrer les notes.');
          }
        });
      });
      return;
    }

    if (action === 'rename') {
      if (!caps().canRenameUnit) return;
      openPrompt(
        'Renommer l’indicatif',
        'Nouvel indicatif affiché sur la carte et dans les effectifs.',
        'Ex. VIPER-1',
        unit.call_sign || ''
      ).then(function (val) {
        if (val === null) return;
        var next = String(val || '').trim();
        if (!next) {
          if (window.ATAKShowError) window.ATAKShowError('Saisissez un indicatif.');
          return;
        }
        if (!unit.id) {
          if (window.ATAKShowError) window.ATAKShowError('Contact non enregistré — impossible de renommer.');
          return;
        }
        patchUnit(unit.id, { call_sign: next }).then(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification('Indicatif mis à jour : ' + next);
          refreshUnits();
        }).catch(function (err) {
          if (window.ATAKShowError) {
            window.ATAKShowError((err && err.body && err.body.message) || 'Impossible de renommer ce contact.');
          }
        });
      });
      return;
    }

    if (action === 'ping') {
      if (!hasValidPos(unit)) return;
      var px = unit.pos_x != null ? parseFloat(unit.pos_x) : NaN;
      var py = unit.pos_y != null ? parseFloat(unit.pos_y) : NaN;
      if (isNaN(px) || isNaN(py)) {
        var gp = String(unit.grid_ref || '').trim().split(/\s+/);
        px = parseFloat(gp[0]);
        py = parseFloat(gp[1]);
      }
      var sendPing = function (msg, kind) {
        if (window.ATAKPings && window.ATAKPings.createPingAt) {
          window.ATAKPings.createPingAt(px, py, msg || '', kind || 'contact');
        }
      };
      if (window.ATAKContextMenu && typeof window.ATAKContextMenu.openPingForm === 'function') {
        window.ATAKContextMenu.openPingForm().then(function (vals) {
          if (!vals) return;
          sendPing(vals.message || '', vals.kind || 'contact');
        });
      } else {
        openPrompt('Message du ping', 'Optionnel — visible par les opérateurs connectés.', 'Ex. contact hostile', '').then(function (msg) {
          if (msg === null) return;
          sendPing(msg || '', 'contact');
        });
      }
      return;
    }

    if (action === 'vibrate') {
      if (!unit || !unit.id) {
        if (window.ATAKShowError) window.ATAKShowError('Contact non enregistré — impossible de faire vibrer le terminal.');
        return;
      }
      var liveV = unitLiveStatus(unit);
      if (liveV !== 'linked' && liveV !== 'delayed') {
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Ce contact n’est plus en liaison.');
        }
        return;
      }
      var vibLabel = unit.call_sign ? String(unit.call_sign) : 'cet opérateur';
      vibrateUnit(unit.id).then(function () {
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Le terminal de ' + vibLabel + ' vibre en jeu');
        }
      }).catch(function (err) {
        if (window.ATAKShowError) {
          window.ATAKShowError((err && err.body && err.body.message) || 'Impossible de faire vibrer ce terminal.');
        }
      });
      return;
    }

    if (action === 'notify') {
      if (!unit || !unit.id) {
        if (window.ATAKShowError) window.ATAKShowError('Contact non enregistré — impossible d’envoyer une notification.');
        return;
      }
      var liveN = unitLiveStatus(unit);
      if (liveN !== 'linked' && liveN !== 'delayed') {
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Ce contact n’est plus en liaison.');
        }
        return;
      }
      var ntfLabel = unit.call_sign ? String(unit.call_sign) : 'cet opérateur';
      openPrompt(
        'Notification pour ' + ntfLabel,
        'Le texte apparaîtra sur le terminal Athena — le joueur pourra cliquer pour le lire.',
        'Ex. Rejoignez le brief à 15:00',
        ''
      ).then(function (msg) {
        if (msg === null) return;
        var text = String(msg || '').trim();
        if (!text) {
          if (window.ATAKShowError) window.ATAKShowError('Saisissez le texte de la notification.');
          return;
        }
        notifyUnit(unit.id, text).then(function () {
          if (window.ATAKShowNotification) {
            window.ATAKShowNotification('Notification envoyée — ' + ntfLabel);
          }
        }).catch(function (err) {
          if (window.ATAKShowError) {
            window.ATAKShowError((err && err.body && err.body.message) || 'Impossible d’envoyer la notification.');
          }
        });
      });
      return;
    }

    if (action === 'helmet-snap' || action === 'helmet-snap-hd' || action === 'helmet-stream') {
      if (action === 'helmet-stream') {
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('La vue casque en temps réel n’est pas encore au point. Demandez une photo, ou utilisez l’onglet Photos.');
        }
        return;
      }
      if (!unit || !unit.id) {
        if (window.ATAKShowError) window.ATAKShowError('Contact non enregistré — demande impossible.');
        return;
      }
      var liveH = unitLiveStatus(unit);
      if (liveH !== 'linked' && liveH !== 'delayed') {
        if (window.ATAKShowNotification) window.ATAKShowNotification('Ce contact n’est plus en liaison.');
        return;
      }
      var mode = action === 'helmet-snap-hd' ? 'snap_hd' : (action === 'helmet-stream' ? 'stream' : 'snap');
      var hLabel = unit.call_sign ? String(unit.call_sign) : 'cet opérateur';
      var confirmMsg = mode === 'stream'
        ? 'Demander un flux casque à ' + hLabel + ' ?\n\nAperçus toutes les ~5 secondes pendant ~3 minutes. Ce n’est pas une vidéo en direct type RTMP.'
        : (mode === 'snap_hd'
          ? 'Demander une photo casque HD à ' + hLabel + ' ?'
          : 'Demander une photo casque à ' + hLabel + ' ?');
      if (!window.confirm(confirmMsg)) return;
      requestHelmetMedia(unit.id, mode).then(function (res) {
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification((res && res.message) || ('Demande envoyée — ' + hLabel));
        }
        if (mode === 'stream' && window.ATAKCams && typeof window.ATAKCams.refresh === 'function') {
          setTimeout(function () { window.ATAKCams.refresh(); }, 2500);
        }
      }).catch(function (err) {
        if (window.ATAKShowError) {
          window.ATAKShowError((err && err.body && err.body.message) || 'Impossible d’envoyer la demande caméra.');
        }
      });
      return;
    }

    if (action === 'link-fiche') {
      if (!caps().canLinkPersonnel) return;
      if (!unit.id) {
        if (window.ATAKShowError) window.ATAKShowError('Contact non enregistré — impossible de lier une fiche.');
        return;
      }
      openPersonnelSelect().then(function (choice) {
        if (!choice || !choice.userId) return;
        patchUnit(unit.id, {
          link_user_id: choice.userId,
          apply_profile_callsign: !!choice.applyProfileCallsign
        }).then(function (row) {
          var linked = row && row.linked_personnel;
          var cs = (row && row.call_sign) || unit.call_sign;
          if (linked) rememberLink(cs, linked);
          if (window.ATAKShowNotification) {
            window.ATAKShowNotification('Fiche liée' + (linked && linked.label ? ' — ' + linked.label : ''));
          }
          refreshUnits();
        }).catch(function (err) {
          if (window.ATAKShowError) {
            window.ATAKShowError((err && err.body && err.body.message) || 'Impossible de lier la fiche.');
          }
        });
      });
      return;
    }

    if (action === 'disconnect') {
      if (!canDeleteUnit(unit)) return;
      var live = unitLiveStatus(unit);
      if (live !== 'linked' && live !== 'delayed') {
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Ce contact n’est déjà plus en liaison.');
        }
        return;
      }
      if (!unit.id) {
        if (window.ATAKShowError) window.ATAKShowError('Contact non enregistré — impossible de couper la liaison.');
        return;
      }
      var discLabel = unit.call_sign ? String(unit.call_sign) : 'cet opérateur';
      if (!window.confirm('Couper la liaison ATAK ?\n\n' + discLabel + ' passera hors liaison sur la carte et dans les effectifs (le contact reste dans la liste « Tous »).')) {
        return;
      }
      disconnectUnit(unit.id).then(function () {
        if (window.ATAKUnits && window.ATAKUnits.setUnitOfflineLocal) {
          window.ATAKUnits.setUnitOfflineLocal(unit.id);
        } else {
          refreshUnits();
        }
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Liaison coupée' + (unit.call_sign ? ' — ' + unit.call_sign : ''));
        }
        if (window.ATAKSounds && typeof window.ATAKSounds.playEvent === 'function') {
          try { window.ATAKSounds.playEvent('disconnect'); } catch (e) {}
        }
      }).catch(function (err) {
        if (window.ATAKShowError) {
          window.ATAKShowError((err && err.body && err.body.message) || 'Impossible de couper la liaison.');
        }
      });
      return;
    }

    if (action === 'delete') {
      if (!canDeleteUnit(unit)) return;
      if (!unit.id) {
        if (window.ATAKShowError) window.ATAKShowError('Contact non enregistré — impossible de le retirer.');
        return;
      }
      var label = unit.call_sign ? String(unit.call_sign) : 'cet opérateur';
      if (!window.confirm('Retirer cet opérateur de la carte ?\n\n' + label + ' disparaîtra des effectifs et de la carte.')) {
        return;
      }
      deleteUnit(unit.id).then(function () {
        if (window.ATAKUnits && window.ATAKUnits.removeUnitLocal) {
          window.ATAKUnits.removeUnitLocal(unit.id);
        } else {
          refreshUnits();
        }
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Opérateur retiré' + (unit.call_sign ? ' — ' + unit.call_sign : ''));
        }
      }).catch(function (err) {
        if (window.ATAKShowError) {
          window.ATAKShowError((err && err.body && err.body.message) || 'Impossible de retirer cet opérateur.');
        }
      });
    }
  }

  function unitFromElement(el) {
    if (!el) return null;
    var id = el.getAttribute('data-unit-id');
    if (id && window.ATAKUnits && window.ATAKUnits.getUnitById) {
      var found = window.ATAKUnits.getUnitById(id);
      if (found) return found;
    }
    return {
      id: id || null,
      call_sign: el.getAttribute('data-callsign') || '',
      pos_x: el.getAttribute('data-x'),
      pos_y: el.getAttribute('data-y'),
      grid_ref: el.getAttribute('data-grid') || ''
    };
  }

  function onUnitContextEvent(ev) {
    if (!ev || !ev.detail) return;
    var d = ev.detail;
    showMenu(d.unit, d.clientX, d.clientY);
  }

  function bindListInteractions(root) {
    if (!root || root._atakUnitMenuBound) return;
    root._atakUnitMenuBound = true;
    root.addEventListener('contextmenu', function (e) {
      var card = e.target.closest('.atak-unit-card, .atak-drawer-row');
      if (!card || !root.contains(card)) return;
      e.preventDefault();
      e.stopPropagation();
      showMenu(unitFromElement(card), e.clientX, e.clientY);
    });
    root.addEventListener('click', function (e) {
      var more = e.target.closest('[data-unit-more]');
      if (!more || !root.contains(more)) return;
      e.preventDefault();
      e.stopPropagation();
      var host = more.closest('.atak-unit-card, .atak-drawer-row') || more;
      var rect = more.getBoundingClientRect();
      showMenu(unitFromElement(host), rect.left, rect.bottom + 4);
    });
    root.addEventListener('touchstart', function (e) {
      var card = e.target.closest('.atak-unit-card, .atak-drawer-row');
      if (!card || !root.contains(card)) return;
      if (e.target.closest('a, button, input')) return;
      var touch = e.touches && e.touches[0];
      if (!touch) return;
      longPressTarget = card;
      clearTimeout(longPressTimer);
      longPressTimer = setTimeout(function () {
        longPressTimer = null;
        if (!longPressTarget) return;
        showMenu(unitFromElement(longPressTarget), touch.clientX, touch.clientY);
        longPressTarget = null;
      }, 550);
    }, { passive: true });
    root.addEventListener('touchend', function () {
      clearTimeout(longPressTimer);
      longPressTimer = null;
      longPressTarget = null;
    });
    root.addEventListener('touchmove', function () {
      clearTimeout(longPressTimer);
      longPressTimer = null;
      longPressTarget = null;
    }, { passive: true });
  }

  function onDocClick(e) {
    if (!menuEl || menuEl.hidden) return;
    if (menuEl.contains(e.target)) return;
    hideMenu();
  }

  function onKeyDown(e) {
    if (e.key !== 'Escape') return;
    if (notesEl && !notesEl.hidden) {
      closeNotes(null);
      return;
    }
    if (selectEl && !selectEl.hidden) {
      closeSelect(null);
      return;
    }
    hideMenu();
  }

  function init() {
    ensureMenu();
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKeyDown);
    window.addEventListener('atak:unit-contextmenu', onUnitContextEvent);
    bindListInteractions(document.getElementById('atak-units-list'));
    bindListInteractions(document.getElementById('atak-units-table-body'));
    var drawer = document.getElementById('atak-effectifs-drawer') || document.querySelector('.atak-drawer');
    if (drawer) bindListInteractions(drawer);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    show: showMenu,
    hide: hideMenu,
    bindListInteractions: bindListInteractions
  };
})();
