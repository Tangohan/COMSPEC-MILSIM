/* COMSPEC ATAK - Panneau droit Effectifs / contacts */
window.ATAKUnits = (function () {
  var units = [];
  var filterLive = true;
  var filterText = '';
  /** Filtre équipe de feu : '' = toutes, '__none__' = sans équipe, sinon id. */
  var filterFireTeamId = '';
  /** Empreinte du dernier rendu liste/table — évite rebuild DOM à chaque poll. */
  var lastRenderFp = '';
  /** Aligné sur AtakDataRepository::UNIT_LIVE_TTL_SECONDS (sec). */
  var LIVE_TTL_MS = 120 * 1000;
  var ORIGIN_EPS = 0.5;
  /** Poll carte ~3 s : garder un terminal encore en liaison s’il manque à 1–3 lectures. */
  var ROSTER_GRACE_MS = 12000;
  /** Première absence constatée par clé d’unité (id / indicatif). */
  var rosterMissingSince = {};

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }
  function isNodeConfigured() {
    var b = getApiBase();
    return b && b.trim() !== '';
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function parseExtra(u) {
    try {
      return typeof u.extra === 'string' ? JSON.parse(u.extra) : (u.extra || {});
    } catch (e) {
      return {};
    }
  }

  function flagOn(v) {
    return v === true || v === 1 || v === '1' || v === 'true';
  }

  function isHostileAffiliation(ex, u) {
    var a = String((ex && (ex.affiliation || ex.affil)) || (u && u.affiliation) || '').toLowerCase();
    var side = String((ex && ex.side) || (u && u.side) || '').toUpperCase();
    return a === 'hostile' || a === 'enemy' || a === 'east' || side === 'EAST';
  }

  function isAiContact(ex, u) {
    if (!ex) ex = {};
    if (flagOn(ex.phone_geoloc) || flagOn(ex.gps_beacon)) return false;
    if (flagOn(ex.enemy_ai) || flagOn(ex.ally_ai) || flagOn(ex.is_ai)) return true;
    var src = String(ex.source || '').toLowerCase();
    if (src === 'ally' || src === 'enemy') return true;
    var cs = String((u && (u.call_sign || u.callsign)) || '').toUpperCase();
    return cs.indexOf('ALLY-') === 0 || cs.indexOf('ENY-') === 0;
  }

  function isEnemyAi(u) {
    var ex = parseExtra(u || {});
    return isAiContact(ex, u) && isHostileAffiliation(ex, u);
  }

  function showEnemyAiEnabled(list) {
    var arr = list || units || [];
    var playerOn = null;
    var enemyOn = false;
    for (var i = 0; i < arr.length; i++) {
      var u = arr[i] || {};
      var ex = parseExtra(u);
      var ai = isAiContact(ex, u);
      if (flagOn(ex.show_enemy_ai)) {
        if (ai) enemyOn = true;
        else playerOn = true;
      } else if (ex.show_enemy_ai === false || ex.show_enemy_ai === 0 || ex.show_enemy_ai === '0' || ex.show_enemy_ai === 'false') {
        if (!ai) playerOn = false;
      }
    }
    var on = playerOn === true || (playerOn === null && enemyOn);
    window.ATAK_SHOW_ENEMY_AI = on;
    return on;
  }

  function shouldHideEnemyAi(u, list) {
    if (!isEnemyAi(u)) return false;
    return !showEnemyAiEnabled(list);
  }

  function parseCoords(u) {
    var x = u && u.pos_x != null && u.pos_x !== '' ? parseFloat(u.pos_x) : NaN;
    var y = u && u.pos_y != null && u.pos_y !== '' ? parseFloat(u.pos_y) : NaN;
    if (isNaN(x) || isNaN(y)) {
      var parts = String((u && u.grid_ref) || '').trim().split(/\s+/);
      if (parts.length >= 2) {
        x = parseFloat(parts[0]);
        y = parseFloat(parts[1]);
      }
    }
    return { x: x, y: y };
  }

  function hasValidPosition(u) {
    var c = parseCoords(u);
    if (isNaN(c.x) || isNaN(c.y)) return false;
    if (Math.abs(c.x) < ORIGIN_EPS && Math.abs(c.y) < ORIGIN_EPS) return false;
    return true;
  }

  function resolveLiveStatus(u) {
    var raw = String((u && u.status) || '').toLowerCase().trim();
    // /api/units a déjà appliqué le TTL MySQL (TIMESTAMPDIFF) : ne pas recalculer
    // avec Date(updated_at) — DATETIME sans TZ → faux « hors liaison » selon le fuseau navigateur.
    // Aligné sur Tacmap (comspec-operational-map) qui affiche u.status tel quel.
    if (raw === 'linked' || raw === 'delayed') {
      return hasValidPosition(u) ? raw : 'offline';
    }
    if (raw === 'offline') return 'offline';
    // Payload legacy sans status résolu : dernier recours (âge navigateur).
    var updated = u && u.updated_at ? new Date(String(u.updated_at).replace(' ', 'T')).getTime() : NaN;
    if (!isNaN(updated)) {
      var age = Date.now() - updated;
      if (age < 0) age = 0;
      if (age > LIVE_TTL_MS) return 'offline';
      if (age > LIVE_TTL_MS * 0.6) return 'delayed';
      return hasValidPosition(u) ? 'linked' : 'offline';
    }
    return raw || 'offline';
  }

  function isInLiaison(u) {
    var s = resolveLiveStatus(u);
    if (s !== 'linked' && s !== 'delayed') return false;
    // Contact à l’origine (0,0) = pas de vraie position reçue → hors filtre « En liaison ».
    return hasValidPosition(u);
  }

  function formatGrid(u) {
    if (!hasValidPosition(u)) return '';
    var raw = String((u && u.grid_ref) || '').trim();
    if (raw && raw !== '0 0') return raw;
    var c = parseCoords(u);
    return Math.round(c.x) + ' ' + Math.round(c.y);
  }

  function tocNotesFromExtra(ex) {
    ex = ex || {};
    return {
      radio: String(ex.toc_radio || '').trim(),
      vehicle: String(ex.toc_vehicle || '').trim(),
      note: String(ex.toc_note || '').trim()
    };
  }

  function displayRadio(ex) {
    var toc = tocNotesFromExtra(ex);
    if (toc.radio) return toc.radio;
    if (ex.radio_freq !== undefined && ex.radio_freq !== '') return String(ex.radio_freq);
    if (ex.radio !== undefined && ex.radio !== '') return String(ex.radio);
    return '';
  }

  function notesCellHtml(ex) {
    var toc = tocNotesFromExtra(ex);
    var chips = [];
    var radio = displayRadio(ex);
    if (radio) {
      chips.push('<span class="atak-note-chip atak-note-chip--radio" title="Fréquence radio">' + esc(radio) + '</span>');
    }
    if (toc.vehicle) {
      chips.push('<span class="atak-note-chip atak-note-chip--vehicle" title="Véhicule">' + esc(toc.vehicle) + '</span>');
    }
    if (toc.note) {
      chips.push('<span class="atak-note-chip atak-note-chip--note" title="Note">' + esc(toc.note) + '</span>');
    }
    if (!chips.length) {
      return '<span class="atak-drawer-muted">—</span>';
    }
    return '<div class="atak-note-chips">' + chips.join('') + '</div>';
  }

  function unitBadgeHtml(u, ex) {
    var callsignKey = (u.call_sign || '').toUpperCase().trim();
    var profile = (window.ATAK_CALLSIGN_TO_USER && callsignKey)
      ? window.ATAK_CALLSIGN_TO_USER[callsignKey]
      : null;
    if (profile && profile.avatarUrl) {
      return '<span class="atak-unit-avatar-wrap" title="Photo du profil">' +
        '<img class="atak-unit-avatar" src="' + esc(profile.avatarUrl) + '" alt="" width="20" height="20" loading="lazy"/>' +
        '</span>';
    }
    var roleText = String(u.role || ex.role || '').trim();
    var fields = (window.NatoSidcIcons && window.NatoSidcIcons.symbolFieldsFromUnit)
      ? window.NatoSidcIcons.symbolFieldsFromUnit(u, ex)
      : null;
    if (window.NatoSidcIcons && window.NatoSidcIcons.listBadgeHtml) {
      return window.NatoSidcIcons.listBadgeHtml(fields ? Object.assign({}, fields, {
        affiliation: fields.affiliation || 'friend',
        role: roleText,
        size: 20,
      }) : {
        affiliation: ex.affiliation || ex.affil || u.affiliation || 'friend',
        role: roleText,
        sidc: ex.sidc || u.sidc || '',
        functionid: ex.functionid || u.functionid || '',
        platform: ex.platform || ex.vehicle_class || '',
        vehicle: ex.vehicle || ex.vehicle_name || '',
        in_vehicle: ex.in_vehicle,
        size: 20,
      });
    }
    return '<span class="atak-unit-no-symbol" title="Sans symbole">Sans symbole</span>';
  }

  function matchesFireTeamFilter(u) {
    if (!filterFireTeamId) return true;
    var tid = String(u.fire_team_id || '');
    if (filterFireTeamId === '__none__') {
      return !tid && !String(u.fire_team_label || '').trim();
    }
    return tid === String(filterFireTeamId);
  }

  function unitsForMap() {
    var src = filterFireTeamId ? units.filter(matchesFireTeamFilter) : units;
    if (showEnemyAiEnabled(units)) return src;
    return src.filter(function (u) { return !isEnemyAi(u); });
  }

  function pushMarkers() {
    if (window.ATAKMap && window.ATAKMap.setUnitsMarkers) {
      window.ATAKMap.setUnitsMarkers(unitsForMap());
    }
  }

  function unitRosterKey(u) {
    if (!u) return '';
    if (u.id != null && String(u.id) !== '') return 'id:' + String(u.id);
    var cs = String(u.call_sign || u.callsign || '').trim().toUpperCase();
    return cs ? 'cs:' + cs : '';
  }

  function unitByRosterKey(key) {
    if (!key) return null;
    for (var i = 0; i < units.length; i++) {
      if (unitRosterKey(units[i]) === key) return units[i];
    }
    return null;
  }

  function isRosterKeepPayload(data) {
    if (data == null) return true;
    if (data.paused === true) return true;
    if (data.ok === false) return true;
    if (data.unavailable === true) return true;
    if (data.error && !Array.isArray(data) && !Array.isArray(data.units)) return true;
    return false;
  }

  function extractRosterList(data) {
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.units)) return data.units;
    return null;
  }

  function notifyDeferredFromRoster() {
    var deferred = false;
    units.forEach(function (u) {
      if (!isInLiaison(u) && resolveLiveStatus(u) !== 'delayed') return;
      var ex = parseExtra(u);
      if (flagOn(ex.deferred) || Number(ex.send_interval_s) >= 45) deferred = true;
    });
    if (window.ATAKSocket && typeof window.ATAKSocket.noteRemoteDeferred === 'function') {
      window.ATAKSocket.noteRemoteDeferred(deferred);
    }
  }

  function publishRoster() {
    render();
    pushMarkers();
    notifyDeferredFromRoster();
    if (window.ATAKRadio && window.ATAKRadio.onUnitsUpdated) {
      window.ATAKRadio.onUnitsUpdated();
    }
    try {
      window.dispatchEvent(new CustomEvent('atak:units-updated', { detail: { count: units.length } }));
    } catch (e) {}
  }

  /**
   * Fusionne une lecture d’effectifs sans faire disparaître un terminal encore en liaison
   * (pause, refus, liste vide d’erreur, absence d’un poll). Keep last good roster.
   */
  function commitRoster(next, opts) {
    opts = opts || {};
    var incoming = Array.isArray(next) ? next : [];
    var now = Date.now();
    var merged = [];
    var kept = {};

    incoming.forEach(function (u) {
      var k = unitRosterKey(u);
      var prev = k ? unitByRosterKey(k) : null;
      var live = resolveLiveStatus(u);
      if (
        !opts.force &&
        prev &&
        isInLiaison(prev) &&
        live === 'offline'
      ) {
        if (!rosterMissingSince[k]) rosterMissingSince[k] = now;
        if (now - rosterMissingSince[k] < ROSTER_GRACE_MS) {
          merged.push(prev);
          kept[k] = true;
          return;
        }
      }
      if (k) delete rosterMissingSince[k];
      merged.push(u);
      if (k) kept[k] = true;
    });

    if (!opts.force) {
      units.forEach(function (prev) {
        var k = unitRosterKey(prev);
        if (!k || kept[k]) return;
        if (!isInLiaison(prev) && resolveLiveStatus(prev) !== 'delayed') return;
        if (!rosterMissingSince[k]) rosterMissingSince[k] = now;
        if (now - rosterMissingSince[k] < ROSTER_GRACE_MS) {
          merged.push(prev);
          kept[k] = true;
        }
      });
    }

    Object.keys(rosterMissingSince).forEach(function (k) {
      if (!kept[k]) delete rosterMissingSince[k];
    });

    units = merged;
    publishRoster();
  }

  function fetchUnits() {
    if (!isNodeConfigured()) return;
    var url = getApiBase() + '/api/units?mapId=' + getMapId() + '&include_gateway=1';
    fetch(url, { credentials: 'include' }).then(function (r) {
      if (!r.ok) return { _keep: true };
      return r.json().then(function (data) {
        return { data: data };
      });
    }).then(function (wrap) {
      if (!wrap || wrap._keep) return;
      var data = wrap.data;
      if (isRosterKeepPayload(data)) return;
      var next = extractRosterList(data);
      if (next == null) return;
      commitRoster(next);
    }).catch(function () {
      render();
    });
  }

  function setUnits(list) {
    if (!Array.isArray(list)) {
      if (units.length) return;
      units = [];
      publishRoster();
      return;
    }
    commitRoster(list);
  }

  function setFireTeamFilter(id) {
    filterFireTeamId = id == null ? '' : String(id);
    lastRenderFp = '';
    render();
    pushMarkers();
  }

  function vitalTone(kind, value) {
    if (kind === 'health') {
      var h = String(value || '').toLowerCase();
      if (h === 'ok' || h === 'stable' || h === 'healthy') return 'ok';
      if (h === 'wounded' || h === 'injured') return 'warn';
      return 'crit';
    }
    if (kind === 'fuel' || kind === 'battery') {
      var n = Number(value);
      if (isNaN(n)) return '';
      if (n <= 15) return 'crit';
      if (n <= 35) return 'warn';
      return 'ok';
    }
    return '';
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  var emptyStateHtml = '<div class="atak-units-empty" id="atak-units-empty">' +
    '<div class="atak-units-empty-icon" aria-hidden="true">' +
    '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>' +
    '</div>' +
    '<p class="atak-units-empty-title">Aucun contact en liaison</p>' +
    '<p class="atak-units-empty-text">La liaison du compte Athena ne suffit pas : le jeu doit envoyer une position valide. En mission, déplacez-vous un peu ou utilisez le hub → Transmettre. Vérifiez aussi le journal Liaison côté Athena.</p>' +
    '</div>';

  function updateSummary() {
    var linked = 0;
    units.forEach(function (u) {
      if (shouldHideEnemyAi(u, units)) return;
      if (resolveLiveStatus(u) === 'linked') linked++;
    });
    var chipEl = document.getElementById('atak-chip-contacts-value');
    if (chipEl) chipEl.textContent = String(linked);
  }

  function updateTableCount(n) {
    var countEl = document.getElementById('atak-effectifs-count');
    if (!countEl) return;
    if (!n) {
      countEl.hidden = true;
      countEl.textContent = '';
      return;
    }
    countEl.hidden = false;
    countEl.textContent = n === 1 ? '1 contact' : (n + ' contacts');
  }

  function renderTable(list) {
    var body = document.getElementById('atak-units-table-body');
    if (!body) return;
    updateTableCount(list.length);
    if (!list.length) {
      body.innerHTML = '<tr><td colspan="8" class="atak-drawer-empty">Aucun contact en liaison pour le moment.</td></tr>';
      return;
    }
    body.innerHTML = list.map(function (u) {
      var ex = parseExtra(u);
      var P = window.ATAKUnitPopup;
      var isPhone = P && P.isPhoneGeoloc ? P.isPhoneGeoloc(ex) : !!(ex.phone_geoloc);
      var rev = (isPhone && P && P.phoneReveal) ? P.phoneReveal(ex) : null;
      var displayName = (P && P.phoneDisplayName) ? P.phoneDisplayName(u, ex) : (u.call_sign || '—');
      var roleRaw = isPhone ? 'Téléphone' : (u.role || ex.role || '');
      var roleText = String(roleRaw || '').trim();
      if (!isPhone && (!roleText || roleText === '—' || /^[A-Za-z0-9]+_[A-Za-z0-9_]+_F$/i.test(roleText))) {
        roleText = String(ex.group || u.group || '').trim() || 'Opérateur';
        roleRaw = roleText !== 'Opérateur' ? roleText : '';
      }
      var statusClass = resolveLiveStatus(u);
      var statusLabel = (window.ATAKUnitPopup && window.ATAKUnitPopup.statusLabelFr)
        ? window.ATAKUnitPopup.statusLabelFr(statusClass)
        : statusClass;
      var hasHeading = (!isPhone || (rev && rev.heading)) && u.heading != null && u.heading !== '';
      var heading = hasHeading ? (Math.round(u.heading) + '°') : '—';
      var gridRaw = (!isPhone || (rev && rev.grid)) ? formatGrid(u) : '';
      var grid = gridRaw || '—';
      var c = parseCoords(u);
      var posOk = hasValidPosition(u);
      var ftLabel = String(u.fire_team_label || '').trim();
      var ftColor = String(u.fire_team_color || '').trim();
      var ftCell = ftLabel
        ? ('<span class="atak-ft-chip atak-ft-chip--sm"' + (ftColor ? ' style="--ft-color:' + esc(ftColor) + ';border-color:' + esc(ftColor) + ';color:' + esc(ftColor) + '"' : '') + '>'
          + (ftColor ? '<span class="atak-ft-chip-dot" aria-hidden="true"></span>' : '')
          + esc(ftLabel) + '</span>')
        : '<span class="atak-drawer-muted">—</span>';
      var toc = tocNotesFromExtra(ex);
      return '<tr class="atak-drawer-row' + (ftColor ? ' atak-drawer-row--ft' : '') + (toc.radio || toc.vehicle || toc.note ? ' atak-drawer-row--notes' : '') + '" tabindex="0" role="button" title="' + (posOk ? 'Centrer la carte sur ce contact' : 'Position non disponible') + '"' +
        ' data-unit-id="' + esc(u.id || '') + '"' +
        ' data-callsign="' + esc(u.call_sign || '') + '"' +
        ' data-grid="' + esc(gridRaw) + '"' +
        ' data-x="' + esc(posOk ? c.x : '') + '"' +
        ' data-y="' + esc(posOk ? c.y : '') + '"' +
        (ftColor ? ' style="--ft-color:' + esc(ftColor) + '"' : '') + '>' +
        '<td class="atak-drawer-cs"><span class="atak-drawer-cs-text">' + esc(displayName) + '</span></td>' +
        '<td' + (roleRaw ? '' : ' class="atak-drawer-muted"') + '>' + esc(roleText) + '</td>' +
        '<td>' + ftCell + '</td>' +
        '<td><span class="atak-unit-status ' + statusClass + '">' + esc(statusLabel) + '</span></td>' +
        '<td class="atak-drawer-hdg' + (hasHeading ? '' : ' atak-drawer-muted') + '">' + esc(heading) + '</td>' +
        '<td class="atak-drawer-grid' + (gridRaw ? '' : ' atak-drawer-muted') + '">' + esc(grid) + '</td>' +
        '<td class="atak-drawer-notes">' + (isPhone ? '<span class="atak-drawer-muted">—</span>' : notesCellHtml(ex)) + '</td>' +
        '<td class="atak-drawer-actions">' +
        ((!isPhone && (statusClass === 'linked' || statusClass === 'delayed'))
          ? '<button type="button" class="atak-unit-vibrate atak-unit-vibrate--table" data-unit-vibrate aria-label="Faire vibrer le terminal" title="Faire vibrer le terminal">Vibrer</button>'
          : '') +
        '<button type="button" class="atak-unit-more" data-unit-more aria-label="Actions sur ce contact" title="Actions">⋯</button>' +
        '</td>' +
        '</tr>';
    }).join('');

    body.querySelectorAll('.atak-drawer-row').forEach(function (row) {
      function focusUnit() {
        var x = row.getAttribute('data-x');
        var y = row.getAttribute('data-y');
        if (x && y && window.ATAKMap && window.ATAKMap.centerOn) {
          window.ATAKMap.centerOn(parseFloat(y), parseFloat(x));
        }
      }
      row.addEventListener('click', function (ev) {
        if (ev.target.closest('a, button, [data-unit-more], [data-unit-vibrate]')) return;
        focusUnit();
      });
      row.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          focusUnit();
        }
      });
    });
    bindVibrateButtons(body);
    if (window.ATAKUnitMenu && window.ATAKUnitMenu.bindListInteractions) {
      window.ATAKUnitMenu.bindListInteractions(body);
    }
  }

  function displayFingerprint(list) {
    return list.map(function (u) {
      var ex = parseExtra(u);
      var health = ex.health || u.health || 'ok';
      var battery = ex.battery != null ? ex.battery : (u.battery != null ? u.battery : '');
      var fuel = ex.fuel !== undefined && ex.fuel !== '' ? ex.fuel : '';
      var ammo = ex.ammo !== undefined && ex.ammo !== '' && ex.ammo !== 'n/a' ? ex.ammo : '';
      var radio = displayRadio(ex);
      var radioTx = (ex.radio_tx === true || ex.radio_tx === 1 || ex.radio_tx === 'true' || ex.radio_speaking === true || ex.radio_speaking === 1 || ex.radio_speaking === 'true') ? '1' : '0';
      var radioCh = ex.radio_channel != null ? ex.radio_channel : '';
      var monCh = (window.ATAKRadio && window.ATAKRadio.getMonitorState)
        ? ((window.ATAKRadio.getMonitorState() || {}).channel || '')
        : '';
      var heading = u.heading != null && u.heading !== '' ? Math.round(Number(u.heading)) : '';
      var toc = tocNotesFromExtra(ex);
      var P = window.ATAKUnitPopup;
      var displayName = (P && P.phoneDisplayName) ? P.phoneDisplayName(u, ex) : (u.call_sign || '');
      return [
        u.id || '',
        displayName,
        JSON.stringify(ex.reveal || {}),
        resolveLiveStatus(u),
        formatGrid(u),
        heading,
        u.role || ex.role || '',
        ex.group || u.group || '',
        health,
        battery,
        fuel,
        ammo,
        radio,
        radioTx,
        radioCh,
        monCh,
        u.fire_team_id || '',
        u.fire_team_label || '',
        u.fire_team_color || '',
        toc.radio,
        toc.vehicle,
        toc.note
      ].join('\t');
    }).join('\n');
  }

  function render() {
    var listEl = document.getElementById('atak-units-list');
    if (!listEl) return;
    updateSummary();
    var filtered = units.filter(function (u) {
      if (shouldHideEnemyAi(u, units)) return false;
      if (filterLive && !isInLiaison(u)) return false;
      if (!matchesFireTeamFilter(u)) return false;
      if (filterText) {
        var t = filterText.toLowerCase();
        var ex = parseExtra(u);
        var P = window.ATAKUnitPopup;
        var isPhone = P && P.isPhoneGeoloc ? P.isPhoneGeoloc(ex) : !!(ex.phone_geoloc);
        var shown = (P && P.phoneDisplayName) ? P.phoneDisplayName(u, ex) : (u.call_sign || '');
        var role = isPhone ? 'téléphone' : (u.role || ex.role || '').toLowerCase();
        var toc = tocNotesFromExtra(ex);
        var hay = [
          shown,
          isPhone ? '' : (u.call_sign || ''),
          isPhone ? '' : (u.military_id || ''),
          isPhone ? '' : (u.bft_id || ''),
          role,
          isPhone ? '' : (u.fire_team_label || ''),
          isPhone ? '' : toc.radio,
          isPhone ? '' : toc.vehicle,
          isPhone ? '' : toc.note,
          isPhone ? '' : displayRadio(ex)
        ].join(' ').toLowerCase();
        return hay.indexOf(t) >= 0;
      }
      return true;
    });
    var fp = filterLive + '|' + filterText + '|' + filterFireTeamId + '\n' + displayFingerprint(filtered);
    if (fp === lastRenderFp) return;
    lastRenderFp = fp;
    renderTable(filtered);
    if (filtered.length === 0) {
      listEl.innerHTML = emptyStateHtml;
      return;
    }
    listEl.innerHTML = filtered.map(function (u) {
      var ex = parseExtra(u);
      var P = window.ATAKUnitPopup;
      var isPhone = P && P.isPhoneGeoloc ? P.isPhoneGeoloc(ex) : !!(ex.phone_geoloc);
      var rev = (isPhone && P && P.phoneReveal) ? P.phoneReveal(ex) : null;
      var displayName = (P && P.phoneDisplayName) ? P.phoneDisplayName(u, ex) : (u.call_sign || '—');
      var health = isPhone ? '' : (ex.health || u.health || 'ok');
      var statusClass = resolveLiveStatus(u);
      var statusLabel = (window.ATAKUnitPopup && window.ATAKUnitPopup.statusLabelFr)
        ? window.ATAKUnitPopup.statusLabelFr(statusClass)
        : statusClass;
      var cardClass = 'atak-unit-card ' + (statusClass === 'delayed' ? 'delayed' : (statusClass === 'offline' ? 'delayed' : 'linked'));
      if (isPhone) cardClass += ' atak-unit-card--phone';
      var healthNorm = String(health || '').toLowerCase();
      if (healthNorm === 'wounded' || healthNorm === 'injured') cardClass += ' atak-unit-bft-wounded';
      if (healthNorm === 'unconscious' || healthNorm === 'cardiac_arrest' || healthNorm === 'cardiac-arrest' || healthNorm === 'dead' || healthNorm === 'kia') {
        cardClass += ' atak-unit-bft-critical';
      }
      var gridRaw = (!isPhone || (rev && rev.grid)) ? formatGrid(u) : '';
      var grid = gridRaw || (isPhone ? '—' : '—');
      var heading = (!isPhone || (rev && rev.heading)) && u.heading != null && u.heading !== ''
        ? (Math.round(u.heading) + '°')
        : '—';
      var roleText = isPhone ? 'Téléphone' : String(u.role || ex.role || '').trim();
      if (!isPhone && ex.ally_ai && (!roleText || roleText === '—' || roleText.toLowerCase() === 'operator')) {
        roleText = 'Unité alliée';
      }
      if (!isPhone && (!roleText || roleText === '—' || /^[A-Za-z0-9]+_[A-Za-z0-9_]+_F$/i.test(roleText))) {
        roleText = String(ex.group || u.group || '').trim() || (ex.ally_ai ? 'Unité alliée' : 'Opérateur');
      }
      var healthLabel = (window.ATAKUnitPopup && window.ATAKUnitPopup.healthLabelFr)
        ? window.ATAKUnitPopup.healthLabelFr(health)
        : health;
      var battery = ex.battery != null ? ex.battery : (u.battery != null ? u.battery : null);
      var fuel = ex.fuel !== undefined && ex.fuel !== '' ? ex.fuel : null;
      var ammo = ex.ammo !== undefined && ex.ammo !== '' && ex.ammo !== 'n/a' ? ex.ammo : null;
      var radio = displayRadio(ex) || null;
      var toc = tocNotesFromExtra(ex);

      var vitals = [];
      if (isPhone) {
        vitals.push('<span class="atak-unit-vital">Signal</span>');
        if (rev && rev.vehicle) {
          var inVeh = ex.in_vehicle === true || ex.in_vehicle === 1 || ex.in_vehicle === 'true';
          vitals.push('<span class="atak-unit-vital">' + (inVeh ? 'À bord' : 'À pied') + '</span>');
        }
      } else {
        var hTone = vitalTone('health', health);
        if (healthNorm !== 'ok' && healthNorm !== 'stable' && healthNorm !== 'healthy') {
          vitals.push('<span class="atak-unit-vital atak-unit-vital--' + hTone + '">État ' + esc(healthLabel) + '</span>');
        } else {
          vitals.push('<span class="atak-unit-vital atak-unit-vital--ok">État stable</span>');
        }
        if (battery != null && battery !== '') {
          var bTone = vitalTone('battery', battery);
          vitals.push('<span class="atak-unit-vital' + (bTone ? ' atak-unit-vital--' + bTone : '') + '">Batt. ' + esc(battery) + '%</span>');
        }
        if (fuel != null) {
          var fTone = vitalTone('fuel', fuel);
          vitals.push('<span class="atak-unit-vital' + (fTone ? ' atak-unit-vital--' + fTone : '') + '">Carb. ' + esc(fuel) + '%</span>');
        }
        if (ammo != null) {
          vitals.push('<span class="atak-unit-vital">Mun. ' + esc(ammo) + '</span>');
        }
        if (radio != null) {
          vitals.push('<span class="atak-unit-vital">Radio ' + esc(radio) + '</span>');
        }
        if (toc.vehicle) {
          vitals.push('<span class="atak-unit-vital">Véhicule ' + esc(toc.vehicle) + '</span>');
        }
        if (toc.note) {
          vitals.push('<span class="atak-unit-vital atak-unit-vital--note" title="' + esc(toc.note) + '">Note</span>');
        }
      }
      if (u.gateway_partner) {
        vitals.push('<span class="atak-unit-vital atak-unit-vital--gateway" title="Contact via passerelle inter-équipes">'
          + esc(u.gateway_peer_label || 'Allié') + '</span>');
        cardClass += ' atak-unit-card--gateway';
      }
      var emitting = (window.ATAKRadio && window.ATAKRadio.isEmitting)
        ? window.ATAKRadio.isEmitting(ex)
        : (ex.radio_tx === true || ex.radio_tx === 1 || ex.radio_tx === 'true' ||
          ex.radio_speaking === true || ex.radio_speaking === 1 || ex.radio_speaking === 'true');
      var radioCh = ex.radio_channel != null ? String(ex.radio_channel) : '';
      var onMonNet = window.ATAKRadio && window.ATAKRadio.isMonitoredChannel
        ? window.ATAKRadio.isMonitoredChannel(radioCh)
        : false;
      if (emitting) {
        vitals.push('<span class="atak-unit-vital atak-unit-vital--emit">Émet</span>');
        cardClass += ' atak-unit-bft-emitting';
      } else if (radioCh !== '') {
        vitals.push('<span class="atak-unit-vital">Canal ' + esc(radioCh) + '</span>');
      }
      if (onMonNet) {
        vitals.push('<span class="atak-unit-vital atak-unit-vital--listen">À l’écoute</span>');
        cardClass += ' atak-unit-bft-radio-listen';
      }

      var tooltipParts = [];
      if (healthNorm !== 'ok' && healthNorm !== 'stable') tooltipParts.push('État : ' + healthLabel);
      if (fuel != null) tooltipParts.push('Carburant ' + fuel + '%');
      if (ammo != null) tooltipParts.push(String(ammo));
      if (radio != null) tooltipParts.push('Radio ' + radio);
      if (emitting) tooltipParts.push('Émet');
      if (onMonNet) tooltipParts.push('Réseau surveillé');
      var tooltip = tooltipParts.join(' · ');

      var callsignKey = (u.call_sign || '').toUpperCase().trim();
      var userLink = (!isPhone && window.ATAK_CALLSIGN_TO_USER && callsignKey && window.ATAK_CALLSIGN_TO_USER[callsignKey])
        ? '<a href="' + (window.ATAK_CALLSIGN_TO_USER[callsignKey].url || '') + '" class="atak-unit-fiche-link" onclick="event.stopPropagation();" title="Ouvrir la fiche personnel">Fiche</a>'
        : '';
      var badgeUnit = u;
      var badgeEx = ex;
      if (isPhone && (!rev || !rev.affiliation)) {
        badgeEx = Object.assign({}, ex, { affiliation: 'unknown' });
      }
      var natoBadge = unitBadgeHtml(badgeUnit, badgeEx);
      var bftId = isPhone ? '' : String(u.military_id || u.bft_id || ex.military_id || ex.bft_id || '').trim();
      var bftLine = bftId
        ? ('<div class="atak-unit-mid" title="Identifiant de suivi lié à cet indicatif">Suivi ' + esc(bftId) + '</div>')
        : '';
      var c = parseCoords(u);
      var posOk = hasValidPosition(u);
      var ftLabel = isPhone ? '' : String(u.fire_team_label || '').trim();
      var ftColor = isPhone ? '' : String(u.fire_team_color || '').trim();
      if (ftColor) {
        cardClass += ' atak-unit-card--ft';
      }
      var ftBadge = ftLabel
        ? ('<span class="atak-ft-chip"' + (ftColor ? ' style="--ft-color:' + esc(ftColor) + ';border-color:' + esc(ftColor) + ';color:' + esc(ftColor) + '"' : '') + '>'
          + (ftColor ? '<span class="atak-ft-chip-dot" aria-hidden="true"></span>' : '')
          + esc(ftLabel) + '</span>')
        : '';
      var metaRow = '';
      if (!isPhone || (rev && (rev.grid || rev.heading))) {
        metaRow = '<div class="atak-unit-meta-row">' +
          '<div class="atak-unit-grid">' + (isPhone && !(rev && rev.grid) ? '' : ('Coord. ' + esc(grid))) + '</div>' +
          '<div class="atak-unit-heading">' + (isPhone && !(rev && rev.heading) ? '' : ('Cap ' + esc(heading))) + '</div>' +
          '</div>';
      }
      return '<div class="' + cardClass + '" data-unit-id="' + esc(u.id || '') + '" data-callsign="' + esc(u.call_sign || '') + '" data-bft-id="' + esc(bftId) + '" data-grid="' + esc(gridRaw) + '" data-x="' + esc(posOk ? c.x : '') + '" data-y="' + esc(posOk ? c.y : '') + '"'
        + (ftColor ? ' style="--ft-color:' + esc(ftColor) + '"' : '')
        + ' title="' + esc(isPhone ? displayName : tooltip) + '">' +
        '<div class="atak-unit-callsign-wrap">' +
        '<div class="atak-unit-callsign">' + natoBadge + esc(displayName) + '</div>' +
        '<span class="atak-unit-status ' + statusClass + '">' + esc(statusLabel) + '</span>' +
        (userLink ? userLink : '') +
        ((!isPhone && (statusClass === 'linked' || statusClass === 'delayed'))
          ? '<button type="button" class="atak-unit-vibrate" data-unit-vibrate aria-label="Faire vibrer le terminal" title="Faire vibrer le terminal">Vibrer</button>'
          : '') +
        '<button type="button" class="atak-unit-more" data-unit-more aria-label="Actions sur ce contact" title="Actions">⋯</button>' +
        '</div>' +
        bftLine +
        '<div class="atak-unit-role">' + esc(roleText) + ftBadge + '</div>' +
        '<div class="atak-unit-vitals">' + vitals.join('') + '</div>' +
        metaRow +
        '</div>';
    }).join('');

    listEl.querySelectorAll('.atak-unit-card').forEach(function (card) {
      card.addEventListener('click', function (ev) {
        if (ev.target.closest('a, button, [data-unit-more], [data-unit-vibrate]')) return;
        var x = this.getAttribute('data-x');
        var y = this.getAttribute('data-y');
        if (x && y && window.ATAKMap && window.ATAKMap.centerOn) {
          window.ATAKMap.centerOn(parseFloat(y), parseFloat(x));
        }
      });
    });
    bindVibrateButtons(listEl);
    if (window.ATAKUnitMenu && window.ATAKUnitMenu.bindListInteractions) {
      window.ATAKUnitMenu.bindListInteractions(listEl);
    }
  }

  function vibrateUnitFromList(unitId, callsign) {
    var label = callsign ? String(callsign) : 'cet opérateur';
    if (!window.confirm('Faire vibrer le terminal de ' + label + ' ?\n\nLe joueur recevra une vibration sur son téléphone ATAK en jeu.')) {
      return;
    }
    var base = getApiBase();
    if (!base || !unitId) {
      if (window.ATAKShowError) window.ATAKShowError('Impossible de faire vibrer ce terminal.');
      return;
    }
    fetch(base + '/api/units/' + encodeURIComponent(unitId) + '/vibrate', {
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
    }).then(function () {
      if (window.ATAKShowNotification) {
        window.ATAKShowNotification('Le terminal de ' + label + ' vibre en jeu');
      }
    }).catch(function (err) {
      if (window.ATAKShowError) {
        window.ATAKShowError((err && err.body && err.body.message) || 'Impossible de faire vibrer ce terminal.');
      }
    });
  }

  function bindVibrateButtons(root) {
    if (!root) return;
    root.querySelectorAll('[data-unit-vibrate]').forEach(function (btn) {
      if (btn._atakVibBound) return;
      btn._atakVibBound = true;
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        var row = btn.closest('[data-unit-id]');
        if (!row) return;
        vibrateUnitFromList(row.getAttribute('data-unit-id'), row.getAttribute('data-callsign'));
      });
    });
  }

  function getUnitById(id) {
    if (id == null || id === '') return null;
    var sid = String(id);
    for (var i = 0; i < units.length; i++) {
      if (String(units[i].id) === sid) return units[i];
    }
    return null;
  }

  function removeUnitLocal(id) {
    if (id == null || id === '') return;
    var sid = String(id);
    var dropped = null;
    var next = units.filter(function (u) {
      if (String(u.id) !== sid) return true;
      dropped = u;
      return false;
    });
    if (next.length === units.length) return;
    var k = unitRosterKey(dropped);
    if (k) delete rosterMissingSince[k];
    units = next;
    lastRenderFp = '';
    publishRoster();
  }

  /** Passe un contact en hors liaison côté UI (après Couper la liaison / TTL). */
  function setUnitOfflineLocal(id) {
    if (id == null || id === '') return;
    var sid = String(id);
    var changed = false;
    var updated = null;
    for (var i = 0; i < units.length; i++) {
      if (String(units[i].id) === sid) {
        units[i] = Object.assign({}, units[i], { status: 'offline', db_status: 'offline' });
        updated = units[i];
        changed = true;
        break;
      }
    }
    if (!changed) return;
    var k = unitRosterKey(updated);
    if (k) delete rosterMissingSince[k];
    lastRenderFp = '';
    publishRoster();
  }

  function getUnits() {
    return units.slice();
  }

  function forceRender() {
    lastRenderFp = '';
    render();
  }

  function initFilters() {
    var filterEl = document.getElementById('atak-units-filter');
    var btnLive = document.getElementById('atak-filter-live');
    var btnAll = document.getElementById('atak-filter-all');
    if (filterEl) filterEl.addEventListener('input', function () { filterText = this.value; render(); });
    if (btnLive) btnLive.addEventListener('click', function () { filterLive = true; btnLive.classList.add('active'); if (btnAll) btnAll.classList.remove('active'); render(); });
    if (btnAll) btnAll.addEventListener('click', function () { filterLive = false; btnAll.classList.add('active'); if (btnLive) btnLive.classList.remove('active'); render(); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFilters);
  } else {
    initFilters();
  }

  function isAllyAi(u) {
    var ex = parseExtra(u || {});
    if (isHostileAffiliation(ex, u)) return false;
    if (ex.ally_ai === true || ex.ally_ai === 1 || ex.ally_ai === '1' || ex.ally_ai === 'true') return true;
    if (ex.is_ai === true || ex.is_ai === 1 || ex.is_ai === 'true') return true;
    if (String(ex.source || '').toLowerCase() === 'ally') return true;
    var cs = String((u && (u.call_sign || u.callsign)) || '').toUpperCase();
    return cs.indexOf('ALLY-') === 0;
  }

  function allyIdOf(u) {
    var ex = parseExtra(u || {});
    var id = String(ex.ally_id || '').trim();
    if (id) return id;
    var cs = String((u && (u.call_sign || u.callsign)) || '').trim();
    var m = cs.match(/^(ALLY-[^\s·]+)/i);
    return m ? m[1] : cs;
  }

  function listAllyUnits() {
    return (units || []).filter(function (u) {
      if (!isAllyAi(u)) return false;
      var st = String((u && u.status) || '').toLowerCase();
      if (st === 'offline') return false;
      return true;
    });
  }

  return {
    setUnits: setUnits,
    fetchUnits: fetchUnits,
    getUnitById: getUnitById,
    getUnits: getUnits,
    removeUnitLocal: removeUnitLocal,
    setUnitOfflineLocal: setUnitOfflineLocal,
    forceRender: forceRender,
    hasValidPosition: hasValidPosition,
    resolveLiveStatus: resolveLiveStatus,
    setFireTeamFilter: setFireTeamFilter,
    getFireTeamFilter: function () { return filterFireTeamId; },
    isAllyAi: isAllyAi,
    isEnemyAi: isEnemyAi,
    shouldHideEnemyAi: shouldHideEnemyAi,
    showEnemyAiEnabled: showEnemyAiEnabled,
    allyIdOf: allyIdOf,
    listAllyUnits: listAllyUnits
  };
})();
