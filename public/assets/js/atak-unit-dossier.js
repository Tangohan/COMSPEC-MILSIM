/* Fiche latérale d’unité — SOURCE ARMA vs ANALYSE ATHENA. */
window.ATAKUnitDossier = (function () {
  'use strict';

  var openRef = null;
  var openKind = 'ground';
  var root = null;
  var TABS = [
    { id: 'sit', label: 'Situation' },
    { id: 'nav', label: 'Navigation' },
    { id: 'pers', label: 'Personnel' },
    { id: 'cbt', label: 'Combat' },
    { id: 'rad', label: 'Radio' },
    { id: 'msn', label: 'Mission' }
  ];
  var tab = 'sit';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function extra(u) {
    if (!u) return {};
    try {
      if (typeof u.extra === 'string') return JSON.parse(u.extra || '{}') || {};
      if (u.extra && typeof u.extra === 'object') return u.extra;
    } catch (e) {}
    return {};
  }
  function num(v) {
    if (v == null || v === '') return null;
    var n = Number(v);
    return isFinite(n) ? n : null;
  }
  function dash(v) {
    return v == null || v === '' ? '—' : String(v);
  }
  function ago(iso) {
    if (window.ATAKUnitPopup && window.ATAKUnitPopup.formatTimeAgo) return window.ATAKUnitPopup.formatTimeAgo(iso) || '—';
    if (!iso) return '—';
    var t = new Date(iso).getTime();
    if (isNaN(t)) return '—';
    var sec = Math.max(0, Math.floor((Date.now() - t) / 1000));
    if (sec < 15) return 'À l’instant';
    if (sec < 60) return sec + ' s';
    return Math.floor(sec / 60) + ' min';
  }
  function findUnit(kind, ref) {
    ref = String(ref || '').trim();
    if (!ref) return null;
    if (kind === 'air' && window.ATAKAirAssets && window.ATAKAirAssets.getAssets) {
      var air = window.ATAKAirAssets.getAssets() || [];
      for (var i = 0; i < air.length; i++) {
        if (String(air[i].callsign || air[i].call_sign || '') === ref) return air[i];
      }
    }
    var units = (window.ATAKUnits && window.ATAKUnits.getUnits) ? (window.ATAKUnits.getUnits() || []) : [];
    for (var j = 0; j < units.length; j++) {
      if (String(units[j].call_sign || units[j].callsign || '') === ref) return units[j];
    }
    return null;
  }
  function kindOf(u) {
    if (!u) return 'ground';
    if (window.ATAKMotion && window.ATAKMotion.isAir && window.ATAKMotion.isAir(u)) return 'air';
    if (u.callsign && !u.call_sign && u.model != null) return 'air';
    return 'ground';
  }
  function typeLabel(u, ex) {
    var cat = String((u.motion && u.motion.category) || ex.platform || '').toUpperCase();
    if (cat === 'INFANTRY') return 'Infanterie';
    if (cat === 'GROUND_VEHICLE') return 'Véhicule';
    if (cat === 'HELICOPTER') return 'Hélicoptère';
    if (cat === 'FIXED_WING') return 'Aérien';
    if (cat === 'UAV') return 'Drone';
    var aff = window.ATAKUnitPopup && window.ATAKUnitPopup.affiliationLabelFr
      ? window.ATAKUnitPopup.affiliationLabelFr(ex.affiliation || u.affiliation)
      : '';
    return [u.role || '', aff].filter(Boolean).join(' · ') || 'Unité';
  }
  function row(k, v) {
    return '<div class="atak-dossier__row"><span class="atak-dossier__k">' + esc(k) + '</span><span class="atak-dossier__v">' + esc(dash(v)) + '</span></div>';
  }
  function block(title, html) {
    if (!html) return '';
    return '<div class="atak-dossier__block"><div class="atak-dossier__block-title">' + esc(title) + '</div>' + html + '</div>';
  }
  function etaFmt(sec, arrived) {
    if (window.ATAKMotion && window.ATAKMotion.formatEta) return window.ATAKMotion.formatEta(sec, arrived);
    if (arrived) return 'Arrivé';
    if (sec == null) return '';
    var n = Math.max(0, Math.round(sec));
    var m = Math.floor(n / 60);
    var s = n % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function sitHtml(u, ex, arma, ath) {
    var M = window.ATAKMotion;
    var x = num(u.pos_x);
    var y = num(u.pos_y);
    var pos = (x != null && y != null) ? (Math.round(x) + ' / ' + Math.round(y)) : (u.grid_ref || '');
    var alt = arma.altitude_m != null ? (Math.round(arma.altitude_m) + ' m') : (M ? M.formatAlt(u) : '');
    var cap = M ? (M.formatHeading(u.movement_heading) || M.formatHeading(u.heading_object || u.heading)) : '';
    var spd = M ? M.formatSpeed(u) : '';
    var st = M ? M.statusLabel((u.motion && u.motion.status) || '') : '';
    var html = row('Position', pos) + row('Altitude', alt) + row('Cap', cap) + row('Vitesse', spd)
      + row('État', st) + row('Dernière MAJ', ago(u.updated_at || u.last_update));
    html += block('Source Arma', row('Vitesse', arma.speed_ms != null ? (arma.speed_ms * 3.6).toFixed(1) + ' km/h' : '') + row('Orientation', arma.heading_deg != null ? Math.round(arma.heading_deg) + '°' : ''));
    html += block('Analyse Athena', row('Statut', M ? M.statusLabel(ath.motion_status) : ath.motion_status)
      + row('Confiance', ath.confidence != null ? Math.round(ath.confidence * 100) + ' %' : '')
      + row('Tendance', M ? M.trendLabel(ath.trend) : ath.trend));
    return html;
  }
  function navHtml(u, ex, arma, ath) {
    var M = window.ATAKMotion;
    var asg = M ? M.assignmentOf(u) : (u.navigation || u.assignment);
    if (!asg) return '<p class="atak-dossier__empty">Aucune destination assignée.</p>';
    var terr = asg.terrain || {};
    var html = row('Destination', asg.destination_label)
      + row('Distance', asg.distance_m != null ? (asg.distance_m / 1000).toFixed(2) + ' km' : '')
      + row('Route', M ? M.courseLabel(asg.course_status) : asg.course_status)
      + row('ETA cinématique', etaFmt(asg.eta && asg.eta.seconds, asg.eta && asg.eta.arrived));
    if (terr && terr.eta_terrain_s != null) {
      html += row('ETA terrain', etaFmt(terr.eta_terrain_s, false))
        + row('Dénivelé positif', terr.climb_m != null ? '+' + Math.round(terr.climb_m) + ' m' : '')
        + row('Pente moyenne', terr.mean_slope_pct != null ? terr.mean_slope_pct + ' %' : '')
        + row('Pente maximale', terr.max_slope_pct != null ? terr.max_slope_pct + ' %' : '')
        + row('Confiance terrain', terr.confidence != null ? Math.round(terr.confidence * 100) + ' %' : '');
    }
    return html;
  }
  function persHtml(u, ex) {
    var n = num(ex.group_count || ex.crew_count);
    var html = row('Effectif', n != null ? String(n) : (u.fire_team_label || ''))
      + row('Chef', ex.leader || ex.group_leader || u.group_name || u.group || '')
      + row('Équipe', u.fire_team_label || '')
      + row('Groupe', ex.group_name || u.group || '');
    return html;
  }
  function cbtHtml(u, ex, arma, ath, op) {
    var P = window.ATAKUnitPopup;
    var health = P ? P.healthLabelFr(ex.health || u.health) : (ex.health || u.health);
    var contact = op && op.combat && op.combat.contact ? 'Contact' : 'Calme';
    var html = row('État combat', contact)
      + row('Munitions', arma.ammo)
      + row('Carburant', arma.fuel_pct != null ? Math.round(arma.fuel_pct) + ' %' : '')
      + row('Santé', health)
      + row('Blessés', op && op.medical && op.medical.unconscious ? 'Oui' : '')
      + row('Mode', ex.combat_mode)
      + row('Comportement', ex.behaviour);
    return html;
  }
  function radHtml(u, ex, arma, op) {
    return row('SR', arma.radio_freq)
      + row('LR', arma.radio_lr)
      + row('Réseau', arma.radio_net || ex.radio_net)
      + row('Émission', op && op.radio && op.radio.speaking ? 'En cours' : '');
  }
  function msnHtml(u, ex, ath) {
    var asg = (window.ATAKMotion && window.ATAKMotion.assignmentOf) ? window.ATAKMotion.assignmentOf(u) : null;
    return row('Phase', ex.mission_phase || ex.phase)
      + row('Tâche', ex.mission_task || ex.task)
      + row('Objectif', asg && asg.destination_label ? asg.destination_label : ath.destination);
  }

  function ensure() {
    if (root) return root;
    root = document.getElementById('atak-unit-dossier');
    return root;
  }

  function render() {
    var el = ensure();
    if (!el || !openRef) return;
    var u = findUnit(openKind, openRef);
    if (!u) {
      el.hidden = false;
      el.innerHTML = '<div class="atak-dossier__head"><strong>' + esc(openRef) + '</strong><button type="button" class="atak-dossier__close" data-dossier-close>Fermer</button></div><p class="atak-dossier__empty">Plus en liaison.</p>';
      return;
    }
    var ex = extra(u);
    var arma = u.source_arma || {};
    var ath = u.analysis_athena || {};
    var op = u.operational || {};
    var cs = u.call_sign || u.callsign || openRef;
    var sub = typeLabel(u, ex);
    var body = '';
    if (tab === 'nav') body = navHtml(u, ex, arma, ath);
    else if (tab === 'pers') body = persHtml(u, ex);
    else if (tab === 'cbt') body = cbtHtml(u, ex, arma, ath, op);
    else if (tab === 'rad') body = radHtml(u, ex, arma, op);
    else if (tab === 'msn') body = msnHtml(u, ex, ath);
    else body = sitHtml(u, ex, arma, ath);
    var tabs = TABS.map(function (t) {
      return '<button type="button" class="atak-dossier__tab' + (t.id === tab ? ' is-active' : '') + '" data-dossier-tab="' + t.id + '">' + esc(t.label) + '</button>';
    }).join('');
    el.hidden = false;
    el.innerHTML =
      '<div class="atak-dossier__head">' +
        '<div><div class="atak-dossier__cs">' + esc(cs) + '</div><div class="atak-dossier__sub">' + esc(sub) + '</div></div>' +
        '<button type="button" class="atak-dossier__close" data-dossier-close title="Fermer la fiche">Fermer</button>' +
      '</div>' +
      '<div class="atak-dossier__tabs">' + tabs + '</div>' +
      '<div class="atak-dossier__body">' + body + '</div>';
  }

  function open(u) {
    if (!u) return;
    openRef = String(u.call_sign || u.callsign || '').trim();
    openKind = kindOf(u);
    tab = 'sit';
    var el = ensure();
    if (el) el.hidden = false;
    render();
  }

  function close() {
    openRef = null;
    var el = ensure();
    if (el) el.hidden = true;
  }

  function onClick(ev) {
    var t = ev.target;
    if (!t || !t.closest) return;
    if (t.closest('[data-dossier-close]')) {
      close();
      return;
    }
    var tb = t.closest('[data-dossier-tab]');
    if (tb) {
      tab = tb.getAttribute('data-dossier-tab') || 'sit';
      render();
    }
  }

  document.addEventListener('click', onClick);
  window.addEventListener('atak:units-updated', function () { if (openRef) render(); });
  window.addEventListener('atak:units-markers-updated', function () { if (openRef) render(); });
  window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && openRef) close();
  });

  return { open: open, close: close, render: render, isOpen: function () { return !!openRef; } };
})();
