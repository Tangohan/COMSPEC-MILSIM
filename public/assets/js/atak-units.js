/* COMSPEC ATAK - Panneau droit Effectifs / contacts */
window.ATAKUnits = (function () {
  var units = [];
  var filterLive = true;
  var filterText = '';
  /** Empreinte du dernier rendu liste/table — évite rebuild DOM à chaque poll. */
  var lastRenderFp = '';
  /** Aligné sur AtakDataRepository::UNIT_LIVE_TTL_SECONDS (sec). */
  var LIVE_TTL_MS = 120 * 1000;
  var ORIGIN_EPS = 0.5;

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
    var hasRole = roleText !== '' && roleText !== '—';
    var hasSidc = !!(ex.sidc || u.sidc || ex.functionid || u.functionid);
    if ((hasRole || hasSidc) && window.NatoSidcIcons && window.NatoSidcIcons.listBadgeHtml) {
      return window.NatoSidcIcons.listBadgeHtml({
        affiliation: ex.affiliation || ex.affil || u.affiliation || 'friend',
        role: roleText,
        sidc: ex.sidc || u.sidc || '',
        functionid: ex.functionid || u.functionid || '',
        size: 20,
      });
    }
    return '<span class="atak-unit-no-symbol" title="Sans symbole">Sans symbole</span>';
  }

  function fetchUnits() {
    if (!isNodeConfigured()) return;
    var url = getApiBase() + '/api/units?mapId=' + getMapId();
    fetch(url, { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (data) {
      units = Array.isArray(data) ? data : (data.units || []);
      render();
      if (window.ATAKMap && window.ATAKMap.setUnitsMarkers) {
        window.ATAKMap.setUnitsMarkers(units);
      }
      if (window.ATAKRadio && window.ATAKRadio.onUnitsUpdated) {
        window.ATAKRadio.onUnitsUpdated();
      }
      try {
        window.dispatchEvent(new CustomEvent('atak:units-updated', { detail: { count: units.length } }));
      } catch (e) {}
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible de charger les unités.');
      render();
    });
  }

  function setUnits(list) {
    units = Array.isArray(list) ? list : [];
    render();
    if (window.ATAKMap && window.ATAKMap.setUnitsMarkers) {
      window.ATAKMap.setUnitsMarkers(units);
    }
    if (window.ATAKRadio && window.ATAKRadio.onUnitsUpdated) {
      window.ATAKRadio.onUnitsUpdated();
    }
    try {
      window.dispatchEvent(new CustomEvent('atak:units-updated', { detail: { count: units.length } }));
    } catch (e) {}
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
      var roleRaw = u.role || ex.role || '';
      var roleText = String(roleRaw || '').trim();
      if (!roleText || roleText === '—' || /^[A-Za-z0-9]+_[A-Za-z0-9_]+_F$/i.test(roleText)) {
        roleText = String(ex.group || u.group || '').trim() || 'Opérateur';
        roleRaw = roleText !== 'Opérateur' ? roleText : '';
      }
      var statusClass = resolveLiveStatus(u);
      var statusLabel = (window.ATAKUnitPopup && window.ATAKUnitPopup.statusLabelFr)
        ? window.ATAKUnitPopup.statusLabelFr(statusClass)
        : statusClass;
      var hasHeading = u.heading != null && u.heading !== '';
      var heading = hasHeading ? (Math.round(u.heading) + '°') : '—';
      var gridRaw = formatGrid(u);
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
        '<td class="atak-drawer-cs"><span class="atak-drawer-cs-text">' + esc(u.call_sign || '—') + '</span></td>' +
        '<td' + (roleRaw ? '' : ' class="atak-drawer-muted"') + '>' + esc(roleText) + '</td>' +
        '<td>' + ftCell + '</td>' +
        '<td><span class="atak-unit-status ' + statusClass + '">' + esc(statusLabel) + '</span></td>' +
        '<td class="atak-drawer-hdg' + (hasHeading ? '' : ' atak-drawer-muted') + '">' + esc(heading) + '</td>' +
        '<td class="atak-drawer-grid' + (gridRaw ? '' : ' atak-drawer-muted') + '">' + esc(grid) + '</td>' +
        '<td class="atak-drawer-notes">' + notesCellHtml(ex) + '</td>' +
        '<td class="atak-drawer-actions">' +
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
        if (ev.target.closest('a, button, [data-unit-more]')) return;
        focusUnit();
      });
      row.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          focusUnit();
        }
      });
    });
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
      return [
        u.id || '',
        u.call_sign || '',
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
      if (filterLive && !isInLiaison(u)) return false;
      if (filterText) {
        var t = filterText.toLowerCase();
        var ex = parseExtra(u);
        var role = (u.role || ex.role || '').toLowerCase();
        var toc = tocNotesFromExtra(ex);
        var hay = [
          u.call_sign || '',
          role,
          u.fire_team_label || '',
          toc.radio,
          toc.vehicle,
          toc.note,
          displayRadio(ex)
        ].join(' ').toLowerCase();
        return hay.indexOf(t) >= 0;
      }
      return true;
    });
    var fp = filterLive + '|' + filterText + '\n' + displayFingerprint(filtered);
    if (fp === lastRenderFp) return;
    lastRenderFp = fp;
    renderTable(filtered);
    if (filtered.length === 0) {
      listEl.innerHTML = emptyStateHtml;
      return;
    }
    listEl.innerHTML = filtered.map(function (u) {
      var ex = parseExtra(u);
      var health = ex.health || u.health || 'ok';
      var statusClass = resolveLiveStatus(u);
      var statusLabel = (window.ATAKUnitPopup && window.ATAKUnitPopup.statusLabelFr)
        ? window.ATAKUnitPopup.statusLabelFr(statusClass)
        : statusClass;
      var cardClass = 'atak-unit-card ' + (statusClass === 'delayed' ? 'delayed' : (statusClass === 'offline' ? 'delayed' : 'linked'));
      var healthNorm = String(health || '').toLowerCase();
      if (healthNorm === 'wounded' || healthNorm === 'injured') cardClass += ' atak-unit-bft-wounded';
      if (healthNorm === 'unconscious' || healthNorm === 'cardiac_arrest' || healthNorm === 'cardiac-arrest' || healthNorm === 'dead' || healthNorm === 'kia') {
        cardClass += ' atak-unit-bft-critical';
      }
      var gridRaw = formatGrid(u);
      var grid = gridRaw || '—';
      var heading = u.heading != null ? (Math.round(u.heading) + '°') : '—';
      var roleText = String(u.role || ex.role || '').trim();
      if (!roleText || roleText === '—' || /^[A-Za-z0-9]+_[A-Za-z0-9_]+_F$/i.test(roleText)) {
        roleText = String(ex.group || u.group || '').trim() || 'Opérateur';
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
      var userLink = (window.ATAK_CALLSIGN_TO_USER && callsignKey && window.ATAK_CALLSIGN_TO_USER[callsignKey])
        ? '<a href="' + (window.ATAK_CALLSIGN_TO_USER[callsignKey].url || '') + '" class="atak-unit-fiche-link" onclick="event.stopPropagation();" title="Ouvrir la fiche personnel">Fiche</a>'
        : '';
      var natoBadge = unitBadgeHtml(u, ex);
      var c = parseCoords(u);
      var posOk = hasValidPosition(u);
      var ftLabel = String(u.fire_team_label || '').trim();
      var ftColor = String(u.fire_team_color || '').trim();
      if (ftColor) {
        cardClass += ' atak-unit-card--ft';
      }
      var ftBadge = ftLabel
        ? ('<span class="atak-ft-chip"' + (ftColor ? ' style="--ft-color:' + esc(ftColor) + ';border-color:' + esc(ftColor) + ';color:' + esc(ftColor) + '"' : '') + '>'
          + (ftColor ? '<span class="atak-ft-chip-dot" aria-hidden="true"></span>' : '')
          + esc(ftLabel) + '</span>')
        : '';
      return '<div class="' + cardClass + '" data-unit-id="' + esc(u.id || '') + '" data-callsign="' + esc(u.call_sign || '') + '" data-grid="' + esc(gridRaw) + '" data-x="' + esc(posOk ? c.x : '') + '" data-y="' + esc(posOk ? c.y : '') + '"'
        + (ftColor ? ' style="--ft-color:' + esc(ftColor) + '"' : '')
        + ' title="' + esc(tooltip) + '">' +
        '<div class="atak-unit-callsign-wrap">' +
        '<div class="atak-unit-callsign">' + natoBadge + esc(u.call_sign || '—') + '</div>' +
        '<span class="atak-unit-status ' + statusClass + '">' + esc(statusLabel) + '</span>' +
        (userLink ? userLink : '') +
        '<button type="button" class="atak-unit-more" data-unit-more aria-label="Actions sur ce contact" title="Actions">⋯</button>' +
        '</div>' +
        '<div class="atak-unit-role">' + esc(roleText) + ftBadge + '</div>' +
        '<div class="atak-unit-vitals">' + vitals.join('') + '</div>' +
        '<div class="atak-unit-meta-row">' +
        '<div class="atak-unit-grid">Coord. ' + esc(grid) + '</div>' +
        '<div class="atak-unit-heading">Cap ' + esc(heading) + '</div>' +
        '</div>' +
        '</div>';
    }).join('');

    listEl.querySelectorAll('.atak-unit-card').forEach(function (card) {
      card.addEventListener('click', function (ev) {
        if (ev.target.closest('a, button, [data-unit-more]')) return;
        var x = this.getAttribute('data-x');
        var y = this.getAttribute('data-y');
        if (x && y && window.ATAKMap && window.ATAKMap.centerOn) {
          window.ATAKMap.centerOn(parseFloat(y), parseFloat(x));
        }
      });
    });
    if (window.ATAKUnitMenu && window.ATAKUnitMenu.bindListInteractions) {
      window.ATAKUnitMenu.bindListInteractions(listEl);
    }
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
    var next = units.filter(function (u) { return String(u.id) !== sid; });
    if (next.length === units.length) return;
    units = next;
    lastRenderFp = '';
    render();
    if (window.ATAKMap && window.ATAKMap.setUnitsMarkers) {
      window.ATAKMap.setUnitsMarkers(units);
    }
    if (window.ATAKRadio && window.ATAKRadio.onUnitsUpdated) {
      window.ATAKRadio.onUnitsUpdated();
    }
    try {
      window.dispatchEvent(new CustomEvent('atak:units-updated', { detail: { count: units.length } }));
    } catch (e) {}
  }

  /** Passe un contact en hors liaison côté UI (après Couper la liaison / TTL). */
  function setUnitOfflineLocal(id) {
    if (id == null || id === '') return;
    var sid = String(id);
    var changed = false;
    for (var i = 0; i < units.length; i++) {
      if (String(units[i].id) === sid) {
        units[i] = Object.assign({}, units[i], { status: 'offline', db_status: 'offline' });
        changed = true;
        break;
      }
    }
    if (!changed) return;
    lastRenderFp = '';
    render();
    if (window.ATAKMap && window.ATAKMap.setUnitsMarkers) {
      window.ATAKMap.setUnitsMarkers(units);
    }
    if (window.ATAKRadio && window.ATAKRadio.onUnitsUpdated) {
      window.ATAKRadio.onUnitsUpdated();
    }
    try {
      window.dispatchEvent(new CustomEvent('atak:units-updated', { detail: { count: units.length } }));
    } catch (e) {}
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
  };
})();
