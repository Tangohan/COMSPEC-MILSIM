/**
 * Fiche popup unité / aérien — carte tactique Athena (ATAK, Overwatch, TACMAP).
 * N’affiche que les champs présents dans le payload (pas de métriques inventées).
 */
window.ATAKUnitPopup = (function () {
  'use strict';

  var POPUP_OPTS = {
    className: 'atak-map-popup',
    maxWidth: 300,
    minWidth: 210,
    closeButton: true,
    autoPan: true,
  };

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function parseExtra(u) {
    if (!u) return {};
    try {
      if (typeof u.extra === 'string') return JSON.parse(u.extra || '{}') || {};
      if (u.extra && typeof u.extra === 'object') return u.extra;
    } catch (e) {}
    return {};
  }

    function statusLabelFr(status) {
    var s = String(status || '').toLowerCase().trim();
    if (s === 'linked') return 'En liaison';
    if (s === 'delayed') return 'Signal différé';
    if (s === 'offline') return 'Hors liaison';
    if (s === 'in-flight' || s === 'in_flight') return 'En vol';
    if (s === 'suspect') return 'À vérifier';
    if (!s) return '';
    return String(status);
  }

  function affiliationLabelFr(a) {
    var x = String(a || '').toLowerCase().trim();
    if (x === 'friend' || x === 'friendly' || x === 'west') return 'Allié';
    if (x === 'hostile' || x === 'enemy' || x === 'east') return 'Hostile';
    if (x === 'neutral' || x === 'guer' || x === 'civ') return 'Neutre';
    if (x === 'unknown') return 'Inconnu';
    if (x === 'suspect') return 'Suspect';
    return a ? String(a) : '';
  }

  function healthLabelFr(h) {
    var x = String(h || '').toLowerCase().trim();
    if (x === 'ok' || x === 'stable' || x === 'healthy') return 'Opérationnel';
    if (x === 'wounded' || x === 'injured') return 'Blessé';
    if (x === 'unconscious') return 'Inconscient';
    if (x === 'cardiac_arrest' || x === 'cardiac-arrest') return 'Arrêt cardiaque';
    if (x === 'dead' || x === 'kia') return 'Hors combat';
    if (x === 'critical' || x === 'incapacitated' || x === 'down') return 'État critique';
    return h ? String(h) : '';
  }

  function healthTone(h) {
    var x = String(h || '').toLowerCase().trim();
    if (x === 'wounded' || x === 'injured') return 'warn';
    if (x === 'unconscious' || x === 'dead' || x === 'kia' || x === 'cardiac_arrest' || x === 'cardiac-arrest' || x === 'critical' || x === 'incapacitated' || x === 'down') return 'danger';
    if (x === 'ok' || x === 'stable' || x === 'healthy') return 'ok';
    return '';
  }

  function statusTone(status) {
    var s = String(status || '').toLowerCase().trim();
    if (s === 'linked' || s === 'in-flight' || s === 'in_flight') return 'ok';
    if (s === 'delayed' || s === 'suspect') return 'warn';
    if (s === 'offline') return 'danger';
    return '';
  }

  function formatTimeAgo(iso) {
    if (!iso) return '';
    var t = new Date(iso).getTime();
    if (isNaN(t)) return '';
    var sec = Math.max(0, Math.floor((Date.now() - t) / 1000));
    if (sec < 15) return 'À l’instant';
    if (sec < 60) return 'Il y a ' + sec + ' s';
    if (sec < 3600) return 'Il y a ' + Math.floor(sec / 60) + ' min';
    if (sec < 86400) return 'Il y a ' + Math.floor(sec / 3600) + ' h';
    try {
      return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return String(iso);
    }
  }

  function formatHeading(h) {
    if (h == null || h === '') return '';
    var n = parseFloat(h);
    if (isNaN(n)) return '';
    return Math.round(n) + '°';
  }

  function formatGrid(u, extra) {
    var x = u && u.pos_x != null ? parseFloat(u.pos_x) : NaN;
    var y = u && u.pos_y != null ? parseFloat(u.pos_y) : NaN;
    if (isNaN(x) || isNaN(y)) {
      var g = (u && u.grid_ref) || (extra && extra.grid_ref) || '';
      var parts = String(g).trim().split(/\s+/);
      if (parts.length >= 2) {
        x = parseFloat(parts[0]);
        y = parseFloat(parts[1]);
      }
    }
    if (isNaN(x) || isNaN(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return '';
    var g2 = (u && u.grid_ref) || (extra && extra.grid_ref) || '';
    g2 = String(g2).trim();
    if (g2 && g2 !== '0 0') return g2;
    return Math.round(x) + ' ' + Math.round(y);
  }

  function row(label, value, tone) {
    if (value == null || value === '') return '';
    var toneClass = tone ? ' atak-unit-popup__val--' + tone : '';
    return (
      '<div class="atak-unit-popup__row">' +
      '<span class="atak-unit-popup__k">' + escapeHtml(label) + '</span>' +
      '<span class="atak-unit-popup__v' + toneClass + '">' + escapeHtml(value) + '</span>' +
      '</div>'
    );
  }

  function motionSectionHtml(u, extra, airMode) {
    var M = window.ATAKMotion;
    if (!M) return '';
    var m = M.motionOf(u);
    var moveH = M.formatHeading(u.movement_heading);
    var objH = M.formatHeading(u.heading_object != null ? u.heading_object : u.heading);
    var spd = M.formatSpeed(u);
    var status = M.statusLabel(m.status);
    var trend = M.trendLabel(m.trend);
    var conf = M.confidenceLabel(m.confidence);
    var rows = '';
    if (airMode || M.isAir(u)) {
      rows += row('Cap', moveH || objH);
      rows += row('Vitesse sol', spd);
      rows += row('Altitude', M.formatAlt(u));
      rows += row('Vitesse verticale', M.formatVs(u));
      rows += row('Tendance', trend);
    } else {
      rows += row('Cap de déplacement', moveH);
      if (objH && objH !== moveH) rows += row('Orientation', objH);
      rows += row('Vitesse', spd);
      rows += row('Tendance', trend);
      rows += row('Déplacement', status || conf);
    }
    if (!rows) return '';
    return '<div class="atak-unit-popup__section"><div class="atak-unit-popup__section-title">Mouvement</div>' + rows + '</div>';
  }

  function navigationSectionHtml(u, airMode) {
    var M = window.ATAKMotion;
    var A = window.ATAKAssignments;
    var asg = M ? M.assignmentOf(u) : null;
    var cs = u.call_sign || u.callsign || '';
    var kind = airMode ? 'air' : 'ground';
    var actions = '<div class="atak-unit-popup__actions">';
    if (!asg || asg.status === 'arrived' || asg.status === 'detached') {
      actions += '<button type="button" class="atak-unit-popup__btn" data-atak-assign="pick" data-unit-ref="' +
        escapeHtml(cs) + '" data-unit-kind="' + kind + '">Assigner une destination</button>';
    } else {
      actions += '<button type="button" class="atak-unit-popup__btn" data-atak-assign="pick" data-unit-ref="' +
        escapeHtml(cs) + '" data-unit-kind="' + kind + '">Changer</button>';
      if (asg.id) {
        actions += '<button type="button" class="atak-unit-popup__btn" data-atak-assign="detach" data-assign-id="' +
          escapeHtml(String(asg.id)) + '">Détacher</button>';
      }
    }
    actions += '</div>';
    var body = '';
    if (asg && M) {
      var course = M.courseLabel(asg.course_status);
      var courseTone = '';
      var st = String(asg.course_status || '').toUpperCase();
      if (st === 'DIVERGING') courseTone = 'course-diverging';
      if (st === 'ARRIVED') courseTone = 'course-arrived';
      body += row('Destination', M.destLabel(asg));
      body += row('Distance', M.formatDistance(asg.distance_m));
      body += row('Arrivée estimée', M.formatEta(asg.eta && asg.eta.seconds, asg.eta && asg.eta.arrived));
      body += row('Statut', course, courseTone);
    }
    return '<div class="atak-unit-popup__section"><div class="atak-unit-popup__section-title">Navigation</div>' +
      body + actions + '</div>';
  }

  function badgeHtml(aff, role) {
    if (!window.NatoSidcIcons || !window.NatoSidcIcons.listBadgeHtml) return '';
    return window.NatoSidcIcons.listBadgeHtml({
      affiliation: aff || 'friend',
      role: role || '',
      size: 22,
    });
  }

  /**
   * @param {object} u unit row from /api/units
   * @returns {string} HTML
   */
  function buildUnitHtml(u) {
    u = u || {};
    var extra = parseExtra(u);
    var callSign = u.call_sign || u.callsign || '—';
    var role = u.role || extra.role || '';
    var aff = extra.affiliation || extra.affil || u.affiliation || '';
    var affLabel = affiliationLabelFr(aff);
    var statusRaw = u.status || '';
    var status = statusLabelFr(statusRaw);
    var healthRaw = extra.health != null ? extra.health : u.health;
    var health = healthLabelFr(healthRaw);
    var heading = formatHeading(u.heading != null ? u.heading : extra.heading);
    var grid = formatGrid(u, extra);
    var updated = formatTimeAgo(u.updated_at || extra.updated_at || u.last_update);
    var parent = extra.parent || extra.parent_callsign || extra.group || extra.groupe || '';
    var radio = '';
    if (extra.toc_radio != null && String(extra.toc_radio).trim() !== '') {
      radio = String(extra.toc_radio).trim();
    } else if (extra.radio_freq != null && extra.radio_freq !== '') {
      radio = String(extra.radio_freq);
    } else if (extra.radio != null && extra.radio !== '') {
      radio = String(extra.radio);
    }
    var fuel = extra.fuel !== undefined && extra.fuel !== null && extra.fuel !== ''
      ? String(extra.fuel) + (String(extra.fuel).indexOf('%') >= 0 ? '' : ' %')
      : '';
    var ammo = extra.ammo != null && extra.ammo !== '' && String(extra.ammo).toLowerCase() !== 'n/a'
      ? String(extra.ammo)
      : '';
    var notes = extra.toc_note || extra.notes || extra.note || '';
    var vehicle = extra.toc_vehicle != null ? String(extra.toc_vehicle).trim() : '';
    var side = extra.side || u.side || '';
    var bftId = String(u.military_id || u.bft_id || extra.military_id || extra.bft_id || '').trim();

    var tone = statusTone(statusRaw) || healthTone(healthRaw);
    var headClass = 'atak-unit-popup__head';
    if (tone) headClass += ' atak-unit-popup__head--' + tone;

    var rows =
      row('Liaison', status, statusTone(statusRaw)) +
      row('État', health, healthTone(healthRaw)) +
      (bftId ? row('Identifiant de suivi', bftId) : '') +
      (function () {
        var ip = extra.client_ip || extra.ip || extra.public_ip || extra.network || '';
        return ip ? row('Adresse réseau', String(ip)) : '';
      }()) +
      row('Affiliation', affLabel) +
      (side && !affLabel ? row('Camp', String(side)) : '') +
      row('Groupe', parent) +
      row('Coordonnées', grid) +
      row('Cap', heading) +
      row('Fréquence radio', radio) +
      (vehicle ? row('Véhicule', vehicle) : '') +
      (extra.radio_channel != null && String(extra.radio_channel) !== ''
        ? row('Canal', String(extra.radio_channel))
        : '') +
      ((extra.radio_tx === true || extra.radio_tx === 1 || extra.radio_tx === 'true' ||
        extra.radio_speaking === true || extra.radio_speaking === 1 || extra.radio_speaking === 'true')
        ? row('Émission', 'Émet', 'warn')
        : '') +
      row('Carburant', fuel) +
      row('Munitions', ammo) +
      row('Dernière MAJ', updated);

    var motionHtml = motionSectionHtml(u, extra, false);
    var navHtml = navigationSectionHtml(u, false);

    return (
      '<div class="atak-unit-popup">' +
      '<div class="' + headClass + '">' +
      badgeHtml(aff || 'friend', role) +
      '<div class="atak-unit-popup__title-wrap">' +
      '<div class="atak-unit-popup__callsign">' + escapeHtml(callSign) + '</div>' +
      (role ? '<div class="atak-unit-popup__subtitle">' + escapeHtml(role) + '</div>' : '') +
      (extra.phone_geoloc ? '<div class="atak-unit-popup__subtitle">Géolocalisation téléphone</div>' : '') +
      (extra.ally_ai ? '<div class="atak-unit-popup__subtitle">Unité alliée</div>' : '') +
      '</div></div>' +
      (rows ? '<div class="atak-unit-popup__body">' + rows + '</div>' : '') +
      motionHtml +
      navHtml +
      (notes
        ? '<div class="atak-unit-popup__notes">' + escapeHtml(String(notes)) + '</div>'
        : '') +
      '</div>'
    );
  }

  /**
   * @param {object} a air asset
   * @returns {string} HTML
   */
  function buildAirHtml(a) {
    a = a || {};
    var callsign = a.callsign || a.call_sign || '—';
    var model = a.model || a.aircraft_type || '';
    var statusRaw = a.status || '';
    var status = statusLabelFr(statusRaw) || String(statusRaw || '');
    var side = a.side || '';
    var aff = 'friend';
    var sideU = String(side).toUpperCase();
    if (sideU === 'EAST') aff = 'hostile';
    else if (sideU === 'GUER' || sideU === 'CIV' || String(statusRaw).toUpperCase() === 'SUSPECT') aff = 'unknown';
    var freq = a.freq != null && a.freq !== '' ? String(a.freq) : '';
    var laser = a.laser != null && a.laser !== '' ? String(a.laser) : '';
    var grid = formatGrid(a, {});
    var updated = formatTimeAgo(a.updated_at || a.last_update);

    var rows =
      row('Statut', status, statusTone(statusRaw)) +
      row('Affiliation', affiliationLabelFr(aff)) +
      row('Fréquence', freq) +
      row('Code laser', laser) +
      row('Coordonnées', grid) +
      row('Dernière MAJ', updated);

    var airMotion = motionSectionHtml(a, {}, true);
    var airNav = navigationSectionHtml(a, true);

    return (
      '<div class="atak-unit-popup atak-unit-popup--air">' +
      '<div class="atak-unit-popup__head">' +
      badgeHtml(aff, model) +
      '<div class="atak-unit-popup__title-wrap">' +
      '<div class="atak-unit-popup__callsign">' + escapeHtml(callsign) + '</div>' +
      (model ? '<div class="atak-unit-popup__subtitle">' + escapeHtml(model) + '</div>' : '') +
      '</div></div>' +
      (rows ? '<div class="atak-unit-popup__body">' + rows + '</div>' : '') +
      airMotion +
      airNav +
      '</div>'
    );
  }

  function popupOptions(extra) {
    var o = {};
    for (var k in POPUP_OPTS) {
      if (Object.prototype.hasOwnProperty.call(POPUP_OPTS, k)) o[k] = POPUP_OPTS[k];
    }
    if (extra && typeof extra === 'object') {
      for (var j in extra) {
        if (Object.prototype.hasOwnProperty.call(extra, j)) o[j] = extra[j];
      }
    }
    return o;
  }

  function bindUnit(marker, u) {
    if (!marker) return marker;
    marker._atakUnit = u;
    if (marker.unbindPopup) {
      try { marker.unbindPopup(); } catch (e) {}
    }
    if (!marker._atakDossierBound) {
      marker._atakDossierBound = true;
      marker.on('click', function (e) {
        if (window.L && e) {
          try { window.L.DomEvent.stopPropagation(e); } catch (err) {}
        }
        if (window.ATAKUnitDossier) window.ATAKUnitDossier.open(marker._atakUnit || u);
      });
    }
    if (window.ATAKMarkerSizes && window.ATAKMarkerSizes.bindHoverTip) {
      var extra = parseExtra(u);
      var callSign = u.call_sign || u.callsign || '—';
      var grid = formatGrid(u, extra);
      var updated = formatTimeAgo(u.updated_at || extra.updated_at || u.last_update);
      window.ATAKMarkerSizes.bindHoverTip(marker, window.ATAKMarkerSizes.hoverTipHtml(callSign, [
        grid ? 'Grille ' + grid : '',
        (function () {
          var M = window.ATAKMotion;
          if (!M || !M.isMoving(u)) return '';
          var bits = [];
          var st = M.statusLabel((u.motion && u.motion.status) || '');
          var spd = M.formatSpeed(u);
          var cap = M.formatHeading(u.movement_heading);
          if (st) bits.push(st);
          if (spd) bits.push(spd);
          if (cap) bits.push('Cap ' + cap);
          var asg = M.assignmentOf(u);
          if (asg && asg.eta && asg.eta.seconds != null) bits.push('ETA ' + M.formatEta(asg.eta.seconds, asg.eta.arrived));
          return bits.join(' · ');
        }()),
        updated ? 'Dernière liaison : ' + updated : ''
      ]));
    }
    return marker;
  }

  function bindAir(marker, a) {
    if (!marker) return marker;
    marker._atakAir = a;
    if (marker.unbindPopup) {
      try { marker.unbindPopup(); } catch (e) {}
    }
    if (!marker._atakDossierBound) {
      marker._atakDossierBound = true;
      marker.on('click', function (e) {
        if (window.L && e) {
          try { window.L.DomEvent.stopPropagation(e); } catch (err) {}
        }
        if (window.ATAKUnitDossier) window.ATAKUnitDossier.open(marker._atakAir || a);
      });
    }
    if (window.ATAKMarkerSizes && window.ATAKMarkerSizes.bindHoverTip) {
      var callsign = a.callsign || a.call_sign || 'Aérien';
      var bits = [a.model || '', statusLabelFr(a.status || '')];
      var M = window.ATAKMotion;
      if (M) {
        var spd = M.formatSpeed(a);
        var cap = M.formatHeading(a.movement_heading || a.heading);
        var alt = M.formatAlt(a);
        var asg = M.assignmentOf(a);
        if (spd) bits.push(spd);
        if (cap) bits.push(cap);
        if (alt) bits.push(alt);
        if (asg && asg.eta) bits.push('ETA ' + M.formatEta(asg.eta.seconds, asg.eta.arrived));
      }
      window.ATAKMarkerSizes.bindHoverTip(marker, window.ATAKMarkerSizes.hoverTipHtml(callsign, bits));
    }
    return marker;
  }

  return {
    popupOptions: popupOptions,
    buildUnitHtml: buildUnitHtml,
    buildAirHtml: buildAirHtml,
    bindUnit: bindUnit,
    bindAir: bindAir,
    statusLabelFr: statusLabelFr,
    affiliationLabelFr: affiliationLabelFr,
    healthLabelFr: healthLabelFr,
    formatTimeAgo: formatTimeAgo,
  };
})();
