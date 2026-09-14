/* Overwatch Beta V3 - Quick Ping, Context Menu, Alerts, Weather, Replay */

(function () {
  'use strict';

  // ============================================================================
  // QUICK PING AMÉLIORÉ ET ANIMÉ
  // ============================================================================

  var activePings = [];
  var PING_DURATION = 5000; // 5 secondes
  var PING_COLORS = {
    default: '#00d69a',
    alert: '#e05b63',
    info: '#5ad0ff',
    warning: '#e7b14d'
  };

  function createQuickPing(latlng, options) {
    options = options || {};
    var type = options.type || 'default';
    var color = PING_COLORS[type] || PING_COLORS.default;
    var label = options.label || '';
    var author = options.author || 'Poste';

    var pingId = 'ping-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    
    var html = '<div class="ow-quick-ping" style="--ping-color:' + color + '">' +
      '<div class="ow-ping-core"></div>' +
      '<div class="ow-ping-ring ow-ping-ring-1"></div>' +
      '<div class="ow-ping-ring ow-ping-ring-2"></div>' +
      '<div class="ow-ping-ring ow-ping-ring-3"></div>' +
      (label ? '<div class="ow-ping-label">' + escapeHtml(label) + '<br><small>' + escapeHtml(author) + '</small></div>' : '') +
      '</div>';

    var marker = L.marker(latlng, {
      icon: L.divIcon({
        className: 'ow-ping-marker',
        html: html,
        iconSize: [100, 100],
        iconAnchor: [50, 50]
      }),
      interactive: false
    });

    if (window.ATAKMap && window.ATAKMap.getMap) {
      marker.addTo(window.ATAKMap.getMap());
    }

    var pingData = {
      id: pingId,
      marker: marker,
      created: Date.now(),
      latlng: latlng,
      type: type,
      label: label
    };

    activePings.push(pingData);

    // Son
    if (options.sound !== false) {
      playPingSound(type);
    }

    // Auto-remove après durée
    setTimeout(function () {
      removePing(pingId);
    }, PING_DURATION);

    return pingId;
  }

  function removePing(pingId) {
    var index = activePings.findIndex(function (p) { return p.id === pingId; });
    if (index === -1) return;

    var ping = activePings[index];
    if (ping.marker && window.ATAKMap && window.ATAKMap.getMap) {
      window.ATAKMap.getMap().removeLayer(ping.marker);
    }

    activePings.splice(index, 1);
  }

  function clearAllPings() {
    activePings.forEach(function (ping) {
      if (ping.marker && window.ATAKMap && window.ATAKMap.getMap) {
        window.ATAKMap.getMap().removeLayer(ping.marker);
      }
    });
    activePings = [];
  }

  // ============================================================================
  // MENU CLIC DROIT AMÉLIORÉ
  // ============================================================================

  var contextMenuData = null;

  function showContextMenu(event, latlng, options) {
    hideContextMenu();

    options = options || {};
    var items = options.items || getDefaultContextItems(latlng);

    var html = '<div class="ow-context-menu-new" style="left:' + event.clientX + 'px;top:' + event.clientY + 'px">';
    
    if (options.title) {
      html += '<div class="ow-context-header">' +
        '<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><circle cx="7" cy="7" r="2" fill="currentColor"/></svg>' +
        '<span>' + escapeHtml(options.title) + '</span>' +
        '</div>';
    }

    items.forEach(function (item) {
      if (item.separator) {
        html += '<div class="ow-context-separator"></div>';
      } else {
        var icon = item.icon || '';
        var danger = item.danger ? ' ow-context-danger' : '';
        var disabled = item.disabled ? ' disabled' : '';
        html += '<button type="button" class="ow-context-item' + danger + disabled + '" data-action="' + escapeHtml(item.action) + '">' +
          (icon ? '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">' + icon + '</svg>' : '') +
          '<span>' + escapeHtml(item.label) + '</span>' +
          (item.shortcut ? '<kbd>' + escapeHtml(item.shortcut) + '</kbd>' : '') +
          '</button>';
      }
    });

    html += '</div>';

    var container = document.createElement('div');
    container.innerHTML = html;
    document.body.appendChild(container.firstChild);

    contextMenuData = {
      latlng: latlng,
      items: items,
      options: options
    };

    // Bind clicks
    document.querySelectorAll('.ow-context-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var action = btn.getAttribute('data-action');
        handleContextAction(action, latlng, options);
        hideContextMenu();
      });
    });
  }

  function hideContextMenu() {
    var menu = document.querySelector('.ow-context-menu-new');
    if (menu) menu.remove();
    contextMenuData = null;
  }

  function getDefaultContextItems(latlng) {
    return [
      { icon: '<circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" fill="none"/>', label: 'Quick Ping', action: 'ping' },
      { icon: '<path d="M8 2C5 2 3 4 3 7C3 10 8 14 8 14C8 14 13 10 13 7C13 4 11 2 8 2Z" stroke="currentColor" stroke-width="2" fill="none"/>', label: 'Point à atteindre', action: 'po' },
      { icon: '<circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="2" fill="none"/><path d="M8 2L8 5M8 11L8 14M2 8L5 8M11 8L14 8" stroke="currentColor" stroke-width="2"/>', label: 'Ralliement', action: 'rally' },
      { separator: true },
      { icon: '<path d="M2 2L14 8L2 14L5 8L2 2Z" stroke="currentColor" stroke-width="2" fill="none"/>', label: 'Marqueur', action: 'marker' },
      { icon: '<path d="M3 13L13 3" stroke="currentColor" stroke-width="2"/>', label: 'Mesurer distance', action: 'measure', shortcut: 'M' },
      { icon: '<circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" fill="none"/>', label: 'Tracer cercle', action: 'circle' },
      { separator: true },
      { icon: '<rect x="3" y="2" width="10" height="10" stroke="currentColor" stroke-width="2" fill="none"/>', label: 'Copier coordonnées', action: 'copy' }
    ];
  }

  function handleContextAction(action, latlng, options) {
    switch (action) {
      case 'ping':
        createQuickPing(latlng, { type: 'default', label: 'Position signalée' });
        break;
      case 'po':
        console.log('Créer PO à', latlng);
        break;
      case 'rally':
        console.log('Créer ralliement à', latlng);
        break;
      case 'marker':
        console.log('Créer marqueur à', latlng);
        break;
      case 'measure':
        console.log('Mesurer depuis', latlng);
        break;
      case 'circle':
        console.log('Tracer cercle à', latlng);
        break;
      case 'copy':
        console.log('Copier coords', latlng);
        break;
    }
  }

  // ============================================================================
  // ALERTES D'INCONSCIENCE
  // ============================================================================

  var unconsciousAlerts = [];

  function addUnconsciousAlert(unit, data) {
    var alert = {
      id: 'unconscious-' + unit.id + '-' + Date.now(),
      unitId: unit.id,
      callsign: unit.callsign || unit.call_sign || 'CONTACT',
      timestamp: Date.now(),
      position: data.position || null,
      severity: data.severity || 'critical',
      status: 'active'
    };

    unconsciousAlerts.push(alert);

    // Afficher bannière
    showAlertBanner({
      type: 'unconscious',
      severity: 'critical',
      title: '🚨 OPÉRATEUR INCONSCIENT',
      message: alert.callsign + ' est inconscient et nécessite assistance médicale immédiate',
      duration: 10000
    });

    // Son d'alerte
    playAlertSound('unconscious');

    // Notification
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('🚨 ' + alert.callsign + ' INCONSCIENT', { priority: true });
    }

    return alert.id;
  }

  function removeUnconsciousAlert(alertId) {
    var index = unconsciousAlerts.findIndex(function (a) { return a.id === alertId; });
    if (index !== -1) {
      unconsciousAlerts.splice(index, 1);
    }
  }

  // ============================================================================
  // BANDEAUX DE MESSAGES
  // ============================================================================

  var activeBanners = [];

  function showAlertBanner(options) {
    var banner = {
      id: 'banner-' + Date.now(),
      type: options.type || 'info',
      severity: options.severity || 'normal',
      title: options.title || '',
      message: options.message || '',
      duration: options.duration || 5000,
      closable: options.closable !== false
    };

    var severityClass = 'ow-banner-' + banner.severity;
    var html = '<div class="ow-alert-banner ' + severityClass + '" data-banner-id="' + banner.id + '">' +
      '<div class="ow-banner-icon">' +
      (banner.severity === 'critical' ? '🚨' : banner.severity === 'warning' ? '⚠️' : 'ℹ️') +
      '</div>' +
      '<div class="ow-banner-content">' +
      '<div class="ow-banner-title">' + escapeHtml(banner.title) + '</div>' +
      '<div class="ow-banner-message">' + escapeHtml(banner.message) + '</div>' +
      '</div>' +
      (banner.closable ? '<button class="ow-banner-close" data-close-banner="' + banner.id + '">×</button>' : '') +
      '</div>';

    var container = document.getElementById('ow-banner-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'ow-banner-container';
      container.className = 'ow-banner-container';
      document.body.appendChild(container);
    }

    container.insertAdjacentHTML('beforeend', html);
    activeBanners.push(banner);

    // Bind close
    var closeBtn = document.querySelector('[data-close-banner="' + banner.id + '"]');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        closeAlertBanner(banner.id);
      });
    }

    // Auto-remove
    if (banner.duration > 0) {
      setTimeout(function () {
        closeAlertBanner(banner.id);
      }, banner.duration);
    }

    return banner.id;
  }

  function closeAlertBanner(bannerId) {
    var el = document.querySelector('[data-banner-id="' + bannerId + '"]');
    if (el) {
      el.classList.add('ow-banner-closing');
      setTimeout(function () { el.remove(); }, 300);
    }

    var index = activeBanners.findIndex(function (b) { return b.id === bannerId; });
    if (index !== -1) {
      activeBanners.splice(index, 1);
    }
  }

  // ============================================================================
  // AFFICHAGE MAINTENANCE
  // ============================================================================

  function showMaintenanceOverlay(options) {
    options = options || {};
    var html = '<div class="ow-maintenance-overlay">' +
      '<div class="ow-maintenance-content">' +
      '<svg class="ow-maintenance-icon" width="64" height="64" viewBox="0 0 64 64" fill="currentColor">' +
      '<circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="4" fill="none"/>' +
      '<path d="M32 16L32 32L40 40" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round"/>' +
      '</svg>' +
      '<h2>' + escapeHtml(options.title || 'Maintenance en cours') + '</h2>' +
      '<p>' + escapeHtml(options.message || 'Le système sera de retour dans quelques instants.') + '</p>' +
      (options.eta ? '<div class="ow-maintenance-eta">Retour estimé : ' + escapeHtml(options.eta) + '</div>' : '') +
      '</div>' +
      '</div>';

    var container = document.createElement('div');
    container.innerHTML = html;
    document.body.appendChild(container.firstChild);
  }

  function hideMaintenanceOverlay() {
    var overlay = document.querySelector('.ow-maintenance-overlay');
    if (overlay) overlay.remove();
  }

  // ============================================================================
  // MÉTÉO ET JOURNAL MÉTÉO
  // ============================================================================

  var weatherHistory = [];
  var currentWeather = null;

  function updateWeather(data) {
    currentWeather = {
      timestamp: Date.now(),
      condition: data.condition || 'clear',
      temperature: data.temperature || 20,
      wind_speed: data.wind_speed || 0,
      wind_direction: data.wind_direction || 0,
      visibility: data.visibility || 10000,
      pressure: data.pressure || 1013
    };

    weatherHistory.push(currentWeather);
    if (weatherHistory.length > 100) weatherHistory.shift();

    // Afficher dans le chip
    var chip = document.getElementById('ow-weather-chip');
    if (chip) {
      chip.textContent = '🌤️ ' + currentWeather.temperature + '°C · ' +
        'Vent ' + currentWeather.wind_speed + ' km/h · ' +
        currentWeather.condition;
      chip.hidden = false;
    }
  }

  function showWeatherJournal() {
    var html = '<div class="ow-weather-journal">' +
      '<h3>📊 JOURNAL MÉTÉO</h3>' +
      '<div class="ow-weather-current">' +
      '<div class="ow-weather-icon">🌤️</div>' +
      '<div class="ow-weather-temp">' + (currentWeather ? currentWeather.temperature : '--') + '°C</div>' +
      '<div class="ow-weather-condition">' + (currentWeather ? currentWeather.condition : 'N/A') + '</div>' +
      '</div>' +
      '<div class="ow-weather-details">' +
      '<div class="ow-weather-detail">' +
      '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2L12 8L10 14L8 8Z" stroke="currentColor" stroke-width="2" fill="none"/></svg>' +
      '<span>Vent</span>' +
      '<strong>' + (currentWeather ? currentWeather.wind_speed : '--') + ' km/h</strong>' +
      '</div>' +
      '<div class="ow-weather-detail">' +
      '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none"/></svg>' +
      '<span>Visibilité</span>' +
      '<strong>' + (currentWeather ? (currentWeather.visibility / 1000).toFixed(1) : '--') + ' km</strong>' +
      '</div>' +
      '<div class="ow-weather-detail">' +
      '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><rect x="4" y="4" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"/></svg>' +
      '<span>Pression</span>' +
      '<strong>' + (currentWeather ? currentWeather.pressure : '--') + ' hPa</strong>' +
      '</div>' +
      '</div>' +
      '<h4>HISTORIQUE (dernières 24h)</h4>' +
      '<div class="ow-weather-history">' +
      weatherHistory.slice(-24).reverse().map(function (w) {
        var time = new Date(w.timestamp).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        return '<div class="ow-weather-history-item">' +
          '<span class="ow-weather-time">' + time + '</span>' +
          '<span class="ow-weather-temp">' + w.temperature + '°C</span>' +
          '<span class="ow-weather-wind">' + w.wind_speed + ' km/h</span>' +
          '<span class="ow-weather-condition">' + w.condition + '</span>' +
          '</div>';
      }).join('') +
      '</div>' +
      '</div>';

    // Afficher dans un drawer ou modal
    if (document.getElementById('ow-drawer')) {
      document.getElementById('ow-drawer-kicker').textContent = 'MÉTÉOROLOGIE';
      document.getElementById('ow-drawer-title').textContent = 'MÉTÉO';
      document.getElementById('ow-drawer-body').innerHTML = html;
      document.getElementById('ow-drawer').hidden = false;
    }
  }

  // ============================================================================
  // SYSTÈME DE REPLAY
  // ============================================================================

  var replayData = {
    active: false,
    paused: false,
    currentTime: 0,
    startTime: 0,
    endTime: 0,
    speed: 1,
    snapshots: []
  };

  function startReplay(options) {
    options = options || {};
    replayData.active = true;
    replayData.paused = false;
    replayData.startTime = options.startTime || (Date.now() - 3600000); // 1h ago
    replayData.endTime = options.endTime || Date.now();
    replayData.currentTime = replayData.startTime;
    replayData.speed = options.speed || 1;

    // Afficher controls
    var timeline = document.getElementById('ow-timeline');
    if (timeline) {
      timeline.hidden = false;
      updateReplayUI();
    }

    // Charger les données
    loadReplayData();
  }

  function stopReplay() {
    replayData.active = false;
    var timeline = document.getElementById('ow-timeline');
    if (timeline) timeline.hidden = true;
  }

  function toggleReplayPause() {
    replayData.paused = !replayData.paused;
    updateReplayUI();
  }

  function setReplaySpeed(speed) {
    replayData.speed = speed;
    updateReplayUI();
  }

  function setReplayTime(time) {
    replayData.currentTime = Math.max(replayData.startTime, Math.min(replayData.endTime, time));
    applyReplaySnapshot(replayData.currentTime);
    updateReplayUI();
  }

  function updateReplayUI() {
    var slider = document.getElementById('ow-timeline-slider');
    var timeLabel = document.getElementById('ow-timeline-time');
    var playBtn = document.getElementById('ow-timeline-play');
    var speedLabel = document.getElementById('ow-timeline-speed');

    if (slider) {
      var progress = ((replayData.currentTime - replayData.startTime) / (replayData.endTime - replayData.startTime)) * 100;
      slider.value = progress;
    }

    if (timeLabel) {
      var date = new Date(replayData.currentTime);
      timeLabel.textContent = date.toLocaleTimeString('fr-FR');
    }

    if (playBtn) {
      playBtn.textContent = replayData.paused ? '▶' : '⏸';
    }

    if (speedLabel) {
      speedLabel.textContent = '×' + replayData.speed;
    }
  }

  function loadReplayData() {
    // TODO: Charger les snapshots depuis l'API
    console.log('Loading replay data from', new Date(replayData.startTime), 'to', new Date(replayData.endTime));
  }

  function applyReplaySnapshot(time) {
    // TODO: Appliquer le snapshot à ce timestamp
    console.log('Apply snapshot at', new Date(time));
  }

  // ============================================================================
  // SONS
  // ============================================================================

  var audioContext = null;
  var audioEnabled = true;

  function initAudio() {
    try {
      audioContext = new (window.AudioContext || window.webkitAudioContext)();
    } catch (e) {
      console.warn('Audio non disponible');
    }
  }

  function playPingSound(type) {
    if (!audioEnabled || !audioContext) return;

    var freq = type === 'alert' ? 800 : type === 'warning' ? 600 : 1000;
    var osc = audioContext.createOscillator();
    var gain = audioContext.createGain();

    osc.type = 'sine';
    osc.frequency.value = freq;
    osc.connect(gain);
    gain.connect(audioContext.destination);

    gain.gain.setValueAtTime(0.3, audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

    osc.start();
    osc.stop(audioContext.currentTime + 0.3);
  }

  function playAlertSound(severity) {
    if (!audioEnabled || !audioContext) return;

    // Double beep pour alerte critique
    [0, 0.2].forEach(function (delay) {
      setTimeout(function () {
        var osc = audioContext.createOscillator();
        var gain = audioContext.createGain();

        osc.type = 'square';
        osc.frequency.value = 1200;
        osc.connect(gain);
        gain.connect(audioContext.destination);

        gain.gain.setValueAtTime(0.4, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.15);

        osc.start();
        osc.stop(audioContext.currentTime + 0.15);
      }, delay * 1000);
    });
  }

  // ============================================================================
  // UTILITAIRES
  // ============================================================================

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ============================================================================
  // EXPORT
  // ============================================================================

  if (typeof window !== 'undefined') {
    window.OverwatchV3 = {
      // Quick Ping
      createQuickPing: createQuickPing,
      removePing: removePing,
      clearAllPings: clearAllPings,

      // Context Menu
      showContextMenu: showContextMenu,
      hideContextMenu: hideContextMenu,

      // Alerts
      addUnconsciousAlert: addUnconsciousAlert,
      removeUnconsciousAlert: removeUnconsciousAlert,
      showAlertBanner: showAlertBanner,
      closeAlertBanner: closeAlertBanner,

      // Maintenance
      showMaintenanceOverlay: showMaintenanceOverlay,
      hideMaintenanceOverlay: hideMaintenanceOverlay,

      // Weather
      updateWeather: updateWeather,
      showWeatherJournal: showWeatherJournal,
      getWeatherHistory: function () { return weatherHistory; },

      // Replay
      startReplay: startReplay,
      stopReplay: stopReplay,
      toggleReplayPause: toggleReplayPause,
      setReplaySpeed: setReplaySpeed,
      setReplayTime: setReplayTime,

      // Audio
      initAudio: initAudio,
      playPingSound: playPingSound,
      playAlertSound: playAlertSound
    };

    // Auto-init
    document.addEventListener('DOMContentLoaded', function () {
      initAudio();

      // Bind global click pour fermer context menu
      document.addEventListener('click', function (e) {
        if (!e.target.closest('.ow-context-menu-new')) {
          hideContextMenu();
        }
      });

      // Bind ESC pour fermer
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          hideContextMenu();
        }
      });
    });
  }
})();
