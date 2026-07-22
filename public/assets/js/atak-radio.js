/* COMSPEC — Radio proximité (métadonnées BFT, pas d’audio navigateur) */
window.ATAKRadio = (function () {
  'use strict';

  var LS_FOCUS = 'atak_radio_focus_cs_v1';
  var LS_MONITOR = 'atak_radio_monitor_v1';
  var LS_HIDE_NO_MODULE = 'atak_radio_hide_no_module_v1';
  var LS_RADIUS = 'atak_radio_radius_v1';
  var LS_TX_ONLY = 'atak_radio_tx_only_v1';
  var bound = false;
  var lastFp = '';
  var focusCs = '';
  var monitorState = null;
  var defaultRadius = 75;
  /** @type {Object.<string, boolean>} */
  var prevTxByKey = {};
  var monitorPrimed = false;

  function esc(s) {
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

  function truthyFlag(v) {
    return v === true || v === 1 || v === '1' || v === 'true' || v === 'TRUE';
  }

  function isEmitting(ex) {
    if (!ex) return false;
    return truthyFlag(ex.radio_tx) || truthyFlag(ex.radio_speaking);
  }

  function normChannel(ch) {
    return String(ch == null ? '' : ch).trim();
  }

  function channelsMatch(a, b) {
    var ca = normChannel(a);
    var cb = normChannel(b);
    if (!ca || !cb) return false;
    return ca.toLowerCase() === cb.toLowerCase();
  }

  function unitKey(u) {
    if (u && u.id != null && u.id !== '') return 'id:' + String(u.id);
    return 'cs:' + String((u && u.call_sign) || '').toLowerCase();
  }

  function modulePresent(list) {
    var arr = Array.isArray(list) ? list : [];
    for (var i = 0; i < arr.length; i++) {
      var ex = parseExtra(arr[i]);
      if (truthyFlag(ex.radio_module)) return true;
      var net = String(ex.radio_net || '').toUpperCase();
      if (net === 'ACRE' || net === 'TFAR') return true;
      if (ex.radio_freq && String(ex.radio_freq) !== '' && String(ex.radio_freq) !== 'N/A') return true;
      if (normChannel(ex.radio_channel) !== '') return true;
    }
    return false;
  }

  function unitPos(u) {
    var x = u && u.pos_x != null ? parseFloat(u.pos_x) : NaN;
    var y = u && u.pos_y != null ? parseFloat(u.pos_y) : NaN;
    if (isNaN(x) || isNaN(y)) {
      var g = String((u && u.grid_ref) || '').trim().split(/\s+/);
      x = parseFloat(g[0]);
      y = parseFloat(g[1]);
    }
    if (isNaN(x) || isNaN(y)) return null;
    if (Math.abs(x) < 0.5 && Math.abs(y) < 0.5) return null;
    return [x, y];
  }

  function distM(a, b) {
    var dx = a[0] - b[0];
    var dy = a[1] - b[1];
    return Math.sqrt(dx * dx + dy * dy);
  }

  function readFocus() {
    try { return localStorage.getItem(LS_FOCUS) || ''; } catch (e) { return ''; }
  }

  function writeFocus(cs) {
    focusCs = cs || '';
    try {
      if (focusCs) localStorage.setItem(LS_FOCUS, focusCs);
      else localStorage.removeItem(LS_FOCUS);
    } catch (e) {}
  }

  function readMonitor() {
    try {
      var raw = localStorage.getItem(LS_MONITOR);
      return raw ? JSON.parse(raw) : null;
    } catch (e) { return null; }
  }

  function writeMonitor(state) {
    monitorState = state && state.channel ? state : null;
    try {
      if (monitorState) localStorage.setItem(LS_MONITOR, JSON.stringify(monitorState));
      else localStorage.removeItem(LS_MONITOR);
    } catch (e) {}
    refreshMapAndUnits();
    try {
      window.dispatchEvent(new CustomEvent('atak-radio-monitor', { detail: monitorState }));
    } catch (e2) {}
  }

  function refreshMapAndUnits() {
    try {
      var list = getUnits();
      if (window.ATAKMap && typeof window.ATAKMap.setUnitsMarkers === 'function') {
        window.ATAKMap.setUnitsMarkers(list);
      }
      if (window.ATAKUnits && typeof window.ATAKUnits.forceRender === 'function') {
        window.ATAKUnits.forceRender();
      }
    } catch (e) {}
  }

  function hideWhenNoModule() {
    try { return localStorage.getItem(LS_HIDE_NO_MODULE) === '1'; } catch (e) { return false; }
  }

  function setHideWhenNoModule(on) {
    try {
      if (on) localStorage.setItem(LS_HIDE_NO_MODULE, '1');
      else localStorage.removeItem(LS_HIDE_NO_MODULE);
    } catch (e) {}
  }

  function readTxOnly() {
    try { return localStorage.getItem(LS_TX_ONLY) === '1'; } catch (e) { return false; }
  }

  function setTxOnly(on) {
    try {
      if (on) localStorage.setItem(LS_TX_ONLY, '1');
      else localStorage.removeItem(LS_TX_ONLY);
    } catch (e) {}
  }

  function readSavedRadius() {
    try {
      var n = parseInt(localStorage.getItem(LS_RADIUS) || '', 10);
      return isNaN(n) ? null : n;
    } catch (e) { return null; }
  }

  function writeSavedRadius(n) {
    try { localStorage.setItem(LS_RADIUS, String(n)); } catch (e) {}
  }

  function getUnits() {
    if (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function') {
      return window.ATAKUnits.getUnits() || [];
    }
    return [];
  }

  function findFocusUnit(list) {
    var cs = (focusCs || '').toLowerCase();
    if (!cs) return null;
    for (var i = 0; i < list.length; i++) {
      if (String(list[i].call_sign || '').toLowerCase() === cs) return list[i];
    }
    return null;
  }

  function isMonitoredChannel(ch) {
    return !!(monitorState && monitorState.channel && channelsMatch(monitorState.channel, ch));
  }

  function getMonitorState() {
    return monitorState;
  }

  function buildProximity(list, radius) {
    var focus = findFocusUnit(list);
    var origin = focus ? unitPos(focus) : null;
    if (!origin) {
      for (var i = 0; i < list.length; i++) {
        var st = window.ATAKUnits && window.ATAKUnits.resolveLiveStatus
          ? window.ATAKUnits.resolveLiveStatus(list[i])
          : list[i].status;
        if (String(st).toLowerCase() === 'offline') continue;
        origin = unitPos(list[i]);
        if (origin) {
          if (!focusCs) focus = list[i];
          break;
        }
      }
    }
    if (!origin) return { focus: focus, items: [] };

    var items = [];
    for (var j = 0; j < list.length; j++) {
      var u = list[j];
      var p = unitPos(u);
      if (!p) continue;
      var d = distM(origin, p);
      if (d > radius) continue;
      var ex = parseExtra(u);
      items.push({
        unit: u,
        dist: Math.round(d),
        emitting: isEmitting(ex),
        channel: normChannel(ex.radio_channel),
        net: ex.radio_net != null ? String(ex.radio_net) : '',
        freq: ex.radio_freq != null ? String(ex.radio_freq) : '',
        extra: ex
      });
    }
    items.sort(function (a, b) {
      if (a.emitting !== b.emitting) return a.emitting ? -1 : 1;
      return a.dist - b.dist;
    });
    return { focus: focus, items: items };
  }

  function updateTabVisibility(hasModule) {
    var tab = document.querySelector('.atak-tab[data-tab="radio"]');
    if (!tab) return;
    if (!hasModule && hideWhenNoModule()) {
      tab.hidden = true;
      var panel = document.getElementById('tab-radio');
      if (panel && panel.classList.contains('active')) {
        var cams = document.querySelector('.atak-tab[data-tab="cams"]');
        if (cams) cams.click();
      }
    } else {
      tab.hidden = false;
    }
  }

  function notifyTxStart(cs, channel) {
    var msg = (cs || 'Contact') + ' émet' + (channel ? (' — canal ' + channel) : '');
    if (window.ATAKShowNotification) {
      try { window.ATAKShowNotification(msg); } catch (e) {}
      return;
    }
    if (window.ATAKSounds && typeof window.ATAKSounds.play === 'function') {
      try { window.ATAKSounds.play(); } catch (e2) {}
    }
  }

  /**
   * Surveille les démarrages d’émission sur le canal suivi (tous effectifs, pas seulement le rayon).
   */
  function detectMonitoredTx(units) {
    var nextMap = {};
    var monCh = monitorState && monitorState.channel ? normChannel(monitorState.channel) : '';
    var arr = Array.isArray(units) ? units : [];

    for (var i = 0; i < arr.length; i++) {
      var u = arr[i];
      var key = unitKey(u);
      var ex = parseExtra(u);
      var emitting = isEmitting(ex);
      nextMap[key] = emitting;
      if (!monitorPrimed) continue;
      if (!monCh || !emitting) continue;
      if (!channelsMatch(monCh, ex.radio_channel)) continue;
      if (prevTxByKey[key]) continue;
      notifyTxStart(u.call_sign || '', normChannel(ex.radio_channel));
    }

    prevTxByKey = nextMap;
    monitorPrimed = true;
  }

  function startMonitor(cs, channel) {
    var ch = normChannel(channel);
    if (!ch) {
      if (window.ATAKShowNotification) {
        window.ATAKShowNotification('Canal inconnu — impossible de surveiller ce réseau pour le moment.', { silent: true });
      }
      return;
    }
    if (monitorState && channelsMatch(monitorState.channel, ch)) {
      writeMonitor(null);
      if (window.ATAKShowNotification) {
        window.ATAKShowNotification('Surveillance du réseau arrêtée.', { silent: true });
      }
      return;
    }
    writeMonitor({ callsign: cs || '', channel: ch, at: Date.now() });
    // Évite un faux positif immédiat sur les émissions déjà en cours.
    monitorPrimed = false;
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification(
        'À l’écoute du canal ' + ch + '. L’écoute audio se fait en jeu ; ici vous suivez qui émet.',
        { silent: true }
      );
    }
  }

  function stopMonitor() {
    if (!monitorState) return;
    writeMonitor(null);
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('Surveillance du réseau arrêtée.', { silent: true });
    }
  }

  function render() {
    var listEl = document.getElementById('atak-radio-list');
    var banner = document.getElementById('atak-radio-banner');
    var focusSel = document.getElementById('atak-radio-focus');
    var badge = document.getElementById('atak-radio-tab-badge');
    var listenBar = document.getElementById('atak-radio-listen-bar');
    if (!listEl) return;

    var units = getUnits().filter(function (u) {
      var st = window.ATAKUnits && window.ATAKUnits.resolveLiveStatus
        ? window.ATAKUnits.resolveLiveStatus(u)
        : u.status;
      return String(st).toLowerCase() !== 'offline';
    });

    detectMonitoredTx(units);

    var hasModule = modulePresent(units);
    updateTabVisibility(hasModule);

    if (focusSel) {
      var opts = '<option value="">Opérateur de référence (auto)</option>';
      units.forEach(function (u) {
        var cs = u.call_sign || '';
        if (!cs) return;
        opts += '<option value="' + esc(cs) + '"' +
          (focusCs && focusCs.toLowerCase() === cs.toLowerCase() ? ' selected' : '') +
          '>' + esc(cs) + '</option>';
      });
      if (focusSel.innerHTML !== opts) focusSel.innerHTML = opts;
    }

    var radiusEl = document.getElementById('atak-radio-radius');
    var radius = radiusEl ? parseInt(radiusEl.value, 10) : defaultRadius;
    if (isNaN(radius)) radius = defaultRadius;

    var txOnlyEl = document.getElementById('atak-radio-tx-only');
    var txOnly = txOnlyEl ? !!txOnlyEl.checked : readTxOnly();

    var prox = buildProximity(units, radius);
    var displayItems = txOnly ? prox.items.filter(function (it) { return it.emitting; }) : prox.items;
    var txCount = prox.items.filter(function (it) { return it.emitting; }).length;
    var monTxCount = 0;
    if (monitorState && monitorState.channel) {
      units.forEach(function (u) {
        var ex = parseExtra(u);
        if (isEmitting(ex) && channelsMatch(monitorState.channel, ex.radio_channel)) monTxCount++;
      });
    }

    if (badge) {
      var badgeN = monitorState ? monTxCount : txCount;
      if (badgeN > 0) {
        badge.hidden = false;
        badge.textContent = String(badgeN > 9 ? '9+' : badgeN);
      } else if (monitorState) {
        badge.hidden = false;
        badge.textContent = '♪';
        badge.title = 'À l’écoute';
      } else {
        badge.hidden = true;
        badge.title = '';
      }
    }

    if (listenBar) {
      if (monitorState && monitorState.channel) {
        listenBar.hidden = false;
        listenBar.innerHTML =
          '<span class="atak-radio-pill atak-radio-pill--listen">À l’écoute</span>' +
          '<span class="atak-radio-listen-bar__text">Canal ' + esc(monitorState.channel) +
          (monTxCount > 0 ? (' · ' + monTxCount + ' émission' + (monTxCount > 1 ? 's' : '')) : '') +
          '</span>' +
          '<button type="button" class="atak-radio-btn" id="atak-radio-stop-monitor">Arrêter</button>';
      } else {
        listenBar.hidden = true;
        listenBar.innerHTML = '';
      }
    }

    if (banner) {
      if (!hasModule) {
        banner.hidden = false;
        banner.className = 'atak-radio-banner atak-radio-banner--warn';
        banner.textContent = 'Module radio non détecté sur le théâtre. Les pastilles d’émission restent inactives jusqu’à ce qu’un module radio soit chargé côté jeu.';
      } else if (monitorState && monitorState.channel) {
        banner.hidden = false;
        banner.className = 'atak-radio-banner atak-radio-banner--ok';
        banner.textContent = 'À l’écoute du canal ' + monitorState.channel +
          '. L’écoute audio se fait en jeu ; ici vous suivez qui émet (alerte visuelle et bip si les sons ne sont pas coupés).';
      } else {
        banner.hidden = false;
        banner.className = 'atak-radio-banner';
        banner.textContent = 'Contacts dans le rayon autour de l’opérateur de référence. Aucune lecture audio dans le navigateur — utilisez « Surveiller ce réseau » pour suivre les émissions d’un canal.';
      }
    }

    var fp = [
      hasModule ? 1 : 0,
      focusCs,
      radius,
      txOnly ? 1 : 0,
      monitorState ? JSON.stringify(monitorState) : '',
      monTxCount,
      displayItems.map(function (it) {
        return (it.unit.call_sign || '') + '|' + it.dist + '|' + (it.emitting ? 1 : 0) + '|' + it.channel + '|' + it.freq;
      }).join(';')
    ].join('\n');
    if (fp === lastFp) return;
    lastFp = fp;

    if (!displayItems.length) {
      listEl.innerHTML =
        '<div class="atak-empty-state">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">◎</div>' +
        '<p class="atak-empty-state-title">' +
        (txOnly ? 'Aucune émission dans le rayon' : 'Aucun contact à proximité') +
        '</p>' +
        '<p class="atak-empty-state-text">' +
        (txOnly
          ? 'Personne n’émet près de l’opérateur de référence. Désactivez le filtre ou élargissez le rayon.'
          : 'Choisissez un opérateur en liaison et un rayon, ou attendez les positions BFT.') +
        '</p>' +
        '</div>';
      return;
    }

    listEl.innerHTML = displayItems.map(function (it) {
      var cs = it.unit.call_sign || '—';
      var netLabel = (it.net === 'ACRE' || it.net === 'TFAR') ? 'Réseau radio' : (it.net && it.net !== 'none' ? it.net : '—');
      var ch = it.channel ? ('Canal ' + it.channel) : 'Canal —';
      var freq = it.freq && it.freq !== 'N/A' ? it.freq : '';
      var onMon = isMonitoredChannel(it.channel);
      var monitoringThis = onMon;
      var btnLabel = monitoringThis ? 'Arrêter l’écoute' : 'Surveiller ce réseau';
      var cardCls = 'atak-radio-card' +
        (it.emitting ? ' atak-radio-card--tx' : '') +
        (onMon ? ' atak-radio-card--listen' : '');
      return (
        '<article class="' + cardCls + '">' +
        '<div class="atak-radio-card__head">' +
        '<strong>' + esc(cs) + '</strong>' +
        (it.emitting ? '<span class="atak-radio-pill atak-radio-pill--tx">Émet</span>' : '') +
        (onMon ? '<span class="atak-radio-pill atak-radio-pill--listen">À l’écoute</span>' : '') +
        '</div>' +
        '<div class="atak-radio-card__meta">' +
        esc(String(it.dist)) + ' m · ' + esc(netLabel) + ' · ' + esc(ch) +
        (freq ? (' · ' + esc(freq)) : '') +
        '</div>' +
        '<div class="atak-radio-card__actions">' +
        '<button type="button" class="atak-radio-btn" data-radio-focus="' + esc(cs) + '">Référence</button>' +
        '<button type="button" class="atak-radio-btn' + (monitoringThis ? '' : ' atak-radio-btn--primary') +
        '" data-radio-monitor="' + esc(cs) +
        '" data-channel="' + esc(it.channel) + '">' + btnLabel + '</button>' +
        '</div>' +
        '</article>'
      );
    }).join('');
  }

  function bindUi() {
    if (bound) return;
    bound = true;
    focusCs = readFocus();
    monitorState = readMonitor();
    if (monitorState && !monitorState.channel) monitorState = null;

    var focusSel = document.getElementById('atak-radio-focus');
    if (focusSel) {
      focusSel.addEventListener('change', function () {
        writeFocus(this.value || '');
        lastFp = '';
        render();
      });
    }

    var radiusEl = document.getElementById('atak-radio-radius');
    if (radiusEl) {
      var savedR = readSavedRadius();
      if (savedR != null) {
        var opt = radiusEl.querySelector('option[value="' + savedR + '"]');
        if (opt) radiusEl.value = String(savedR);
      }
      radiusEl.addEventListener('change', function () {
        var n = parseInt(this.value, 10);
        if (!isNaN(n)) writeSavedRadius(n);
        lastFp = '';
        render();
      });
    }

    var hideCb = document.getElementById('atak-radio-hide-nomodule');
    if (hideCb) {
      hideCb.checked = hideWhenNoModule();
      hideCb.addEventListener('change', function () {
        setHideWhenNoModule(!!this.checked);
        lastFp = '';
        render();
      });
    }

    var txOnlyCb = document.getElementById('atak-radio-tx-only');
    if (txOnlyCb) {
      txOnlyCb.checked = readTxOnly();
      txOnlyCb.addEventListener('change', function () {
        setTxOnly(!!this.checked);
        lastFp = '';
        render();
      });
    }

    var listEl = document.getElementById('atak-radio-list');
    if (listEl) {
      listEl.addEventListener('click', function (ev) {
        var focusBtn = ev.target.closest('[data-radio-focus]');
        if (focusBtn) {
          writeFocus(focusBtn.getAttribute('data-radio-focus') || '');
          if (focusSel) focusSel.value = focusCs;
          lastFp = '';
          render();
          return;
        }
        var monBtn = ev.target.closest('[data-radio-monitor]');
        if (monBtn) {
          var cs = monBtn.getAttribute('data-radio-monitor') || '';
          var ch = monBtn.getAttribute('data-channel') || '';
          startMonitor(cs, ch);
          lastFp = '';
          render();
        }
      });
    }

    var head = document.getElementById('atak-radio-head') || document.querySelector('.atak-radio-head');
    if (head) {
      head.addEventListener('click', function (ev) {
        if (ev.target && ev.target.id === 'atak-radio-stop-monitor') {
          stopMonitor();
          lastFp = '';
          render();
        }
      });
    }
  }

  function onUnitsUpdated() {
    bindUi();
    render();
  }

  function init() {
    bindUi();
    render();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    onUnitsUpdated: onUnitsUpdated,
    isEmitting: isEmitting,
    parseExtra: parseExtra,
    isMonitoredChannel: isMonitoredChannel,
    getMonitorState: getMonitorState,
    channelsMatch: channelsMatch,
    render: render,
    startMonitor: startMonitor,
    stopMonitor: stopMonitor
  };
})();
