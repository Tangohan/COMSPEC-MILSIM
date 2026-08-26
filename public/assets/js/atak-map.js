/* COMSPEC ATAK - Carte Leaflet + marqueurs temps réel */
window.ATAKMap = (function () {
  var map;
  var layerGroups = {};
  var markersById = {};
  var intelLayer = null;
  var intelMarkersById = {};
  var designatorLayer = null;
  var designatorMarkersById = {};
  var sigintLayer = null;
  var sigintCirclesById = {};
  var pingTempLayer = null;
  var pingTempMarkersById = {};
  var pingLayer = null;
  var pingMarkersById = {};
  var explosiveLayer = null;
  var explosiveMarkersById = {};
  var gpsVehicleLayer = null;
  var gpsVehicleMarkersById = {};
  var airAssetsLayer = null;
  var airAssetsById = {};
  var unitsLayer = null;
  var unitsById = {};
  var config;
  var baseTileLayer = null;
  var tileFailCount = 0;
  var invalidateTimer = null;
  var mapResizeObserver = null;
  var lastMapSizeKey = '';
  var UNIT_MARKER_PRIORITY_KEY = 'atak_unit_marker_priority';
  var UNIT_MARKER_PRIORITY_DEFAULT = 'nato';
  var DISPLAY_PREFS_KEY = 'atak_map_display_prefs';
  var DISPLAY_PREFS_DEFAULT = {
    styleMode: 'nato',
    iconSize: 17,
    labelSize: 7,
    showFtFrame: true,
    markerDepth: true,
    markerMotion: true,
    showIntelPhotoMarkers: true,
    autoCenterSelf: false,
    showDelayedUnits: true,
    positionDelayEnabled: false,
    positionDelayMs: 2000,
    packetLossEnabled: false,
    packetLossPercent: 25,
    showSseOverlay: true,
    showMissionOverlay: true,
    showSseLayer_cases: true,
    showSseLayer_pir: true,
    showSseLayer_taskings: true,
    showSseLayer_photos: true,
    showSseLayer_intel: true,
    showSseTracks: true,
    showSseGhostTracks: false,
    showSseHistory: false,
    showUnitTrails: true,
    showUnitGhostTrails: true,
    showMotionArrows: true,
    showMotionProjection: true,
    showAssignmentLines: true,
    showMotionTrail: true,
    showEtaLabels: false,
    terrainLayer: 'hillshade',
    terrainHillshade: true,
    terrainContours10: true,
    terrainContours50: false,
    terrainAltitudes: false,
    terrainSlope: false,
    terrainOpacity: 0.32,
    terrainSunAzimuth: 315
  };
  var displayPrefsCache = null;
  var lastUnitsListForMap = null;
  var lastAirListForMap = null;
  var unitPosQueues = {};
  var unitPosDisplayed = {};
  var unitPosLiveSeen = {};
  var posSimFlushTimer = null;
  var selfAutoCentered = false;

  function normalizeUnitMarkerPriority(v) {
    return v === 'avatar' ? 'avatar' : UNIT_MARKER_PRIORITY_DEFAULT;
  }

  function getUnitMarkerPriority() {
    try {
      return normalizeUnitMarkerPriority(localStorage.getItem(UNIT_MARKER_PRIORITY_KEY));
    } catch (e) {
      return UNIT_MARKER_PRIORITY_DEFAULT;
    }
  }

  function setUnitMarkerPriority(v) {
    var next = normalizeUnitMarkerPriority(v);
    try {
      localStorage.setItem(UNIT_MARKER_PRIORITY_KEY, next);
    } catch (e) {}
    refreshUnitMarkerIcons();
    return next;
  }

  function clampNum(n, min, max, fallback) {
    var v = Number(n);
    if (isNaN(v)) return fallback;
    if (v < min) return min;
    if (v > max) return max;
    return v;
  }

  function normalizeStyleMode(v) {
    if (v === 'dot' || v === 'team_dot' || v === 'intel_dot') return v;
    return 'nato';
  }

  function normalizeDisplayPrefs(raw) {
    var src = raw && typeof raw === 'object' ? raw : {};
    return {
      styleMode: normalizeStyleMode(src.styleMode),
      iconSize: clampNum(src.iconSize, (window.ATAKMarkerSizes && window.ATAKMarkerSizes.PREF_MIN) || 10, (window.ATAKMarkerSizes && window.ATAKMarkerSizes.PREF_MAX) || 22, DISPLAY_PREFS_DEFAULT.iconSize),
      labelSize: clampNum(src.labelSize, 6, 16, DISPLAY_PREFS_DEFAULT.labelSize),
      showFtFrame: src.showFtFrame !== false,
      markerDepth: src.markerDepth !== false,
      markerMotion: src.markerMotion !== false,
      showIntelPhotoMarkers: src.showIntelPhotoMarkers !== false,
      autoCenterSelf: !!src.autoCenterSelf,
      showDelayedUnits: src.showDelayedUnits !== false,
      positionDelayEnabled: !!src.positionDelayEnabled,
      positionDelayMs: Math.round(clampNum(src.positionDelayMs, 500, 10000, DISPLAY_PREFS_DEFAULT.positionDelayMs)),
      packetLossEnabled: !!src.packetLossEnabled,
      packetLossPercent: Math.round(clampNum(src.packetLossPercent, 5, 80, DISPLAY_PREFS_DEFAULT.packetLossPercent)),
      showSseOverlay: src.showSseOverlay !== false,
      showMissionOverlay: src.showMissionOverlay !== false,
      showSseLayer_cases: src.showSseLayer_cases !== false,
      showSseLayer_pir: src.showSseLayer_pir !== false,
      showSseLayer_taskings: src.showSseLayer_taskings !== false,
      showSseLayer_photos: src.showSseLayer_photos !== false,
      showSseLayer_intel: src.showSseLayer_intel !== false,
      showSseTracks: src.showSseTracks !== false,
      showSseGhostTracks: !!src.showSseGhostTracks,
      showSseHistory: !!src.showSseHistory,
      showUnitTrails: src.showUnitTrails !== false,
      showUnitGhostTrails: src.showUnitGhostTrails !== false,
      showMotionArrows: src.showMotionArrows !== false,
      showMotionProjection: src.showMotionProjection !== false,
      showAssignmentLines: src.showAssignmentLines !== false,
      showMotionTrail: src.showMotionTrail !== false,
      showEtaLabels: !!src.showEtaLabels,
      terrainLayer: ['off', 'hillshade', 'hypsometry', 'slope', 'contours', 'ridges', 'depressions'].indexOf(src.terrainLayer) >= 0 ? src.terrainLayer : DISPLAY_PREFS_DEFAULT.terrainLayer,
      terrainHillshade: src.terrainHillshade != null ? !!src.terrainHillshade : (src.terrainLayer == null || src.terrainLayer === 'hillshade' || src.terrainLayer === 'hypsometry'),
      terrainContours10: src.terrainContours10 != null ? !!src.terrainContours10 : (src.terrainLayer == null || src.terrainLayer === 'contours' || src.terrainLayer === 'hillshade'),
      terrainContours50: !!src.terrainContours50,
      terrainAltitudes: !!src.terrainAltitudes,
      terrainSlope: src.terrainSlope != null ? !!src.terrainSlope : src.terrainLayer === 'slope',
      terrainOpacity: clampNum(src.terrainOpacity, 0.05, 1, DISPLAY_PREFS_DEFAULT.terrainOpacity),
      terrainSunAzimuth: clampNum(src.terrainSunAzimuth, 0, 360, DISPLAY_PREFS_DEFAULT.terrainSunAzimuth)
    };
  }

  function getDisplayPrefs() {
    if (displayPrefsCache) return displayPrefsCache;
    try {
      var raw = localStorage.getItem(DISPLAY_PREFS_KEY);
      displayPrefsCache = normalizeDisplayPrefs(raw ? JSON.parse(raw) : null);
    } catch (e) {
      displayPrefsCache = normalizeDisplayPrefs(null);
    }
    return displayPrefsCache;
  }

  function saveDisplayPrefs(prefs) {
    displayPrefsCache = normalizeDisplayPrefs(prefs);
    try {
      localStorage.setItem(DISPLAY_PREFS_KEY, JSON.stringify(displayPrefsCache));
    } catch (e) {}
    return displayPrefsCache;
  }

  function patchDisplayPrefs(patch) {
    var prev = getDisplayPrefs();
    var next = saveDisplayPrefs(Object.assign({}, prev, patch || {}));
    applyDisplayPrefsToMapDom();
    ensurePosSimFlushTimer();
    if (patch && (patch.positionDelayEnabled === false || patch.packetLossEnabled === false
        || patch.positionDelayMs != null || patch.packetLossPercent != null)) {
      if (!next.positionDelayEnabled && !next.packetLossEnabled) {
        clearPosSimState();
      }
    }
    if (patch && patch.showIntelPhotoMarkers === false) {
      clearIntelMarkers();
    } else if (patch && patch.showIntelPhotoMarkers === true && prev.showIntelPhotoMarkers === false) {
      if (window.ATAKCams && typeof window.ATAKCams.refresh === 'function') {
        window.ATAKCams.refresh();
      }
    }
    refreshUnitMarkerIcons();
    try {
      window.dispatchEvent(new CustomEvent('atak:display-prefs-changed', { detail: next }));
    } catch (e) { /* ignore */ }
    return next;
  }

  function inclinedView() {
    try {
      return !!(window.ATAKTerrain3D && window.ATAKTerrain3D.getState && window.ATAKTerrain3D.getState().enabled);
    } catch (e) {
      return false;
    }
  }

  function unitBillboardLabel(callSign) {
    var cs = String(callSign || '').trim();
    if (!cs || !inclinedView()) return '';
    return '<span class="atak-unit-dot-label">' + cs.replace(/</g, '&lt;') + '</span>';
  }

  function applyDisplayPrefsToMapDom() {
    var p = getDisplayPrefs();
    var mapEl = document.getElementById('atak-map');
    if (!mapEl) return;
    mapEl.style.setProperty('--atak-unit-label-size', p.labelSize + 'px');
    mapEl.style.setProperty('--atak-unit-icon-size', p.iconSize + 'px');
    var frame = Math.max(12, Math.round(p.iconSize * 1.2));
    mapEl.style.setProperty('--atak-ft-frame-size', frame + 'px');
    if (p.showFtFrame) mapEl.classList.remove('atak-map--hide-ft-frames');
    else mapEl.classList.add('atak-map--hide-ft-frames');
    mapEl.classList.toggle('atak-map--marker-depth', !!p.markerDepth);
    mapEl.classList.toggle('atak-map--marker-motion', !!p.markerMotion);
  }

  function clearPosSimState() {
    unitPosQueues = {};
    unitPosDisplayed = {};
    unitPosLiveSeen = {};
  }

  function ensurePosSimFlushTimer() {
    var p = getDisplayPrefs();
    var need = p.positionDelayEnabled && p.positionDelayMs > 0;
    if (need && !posSimFlushTimer) {
      posSimFlushTimer = setInterval(function () {
        if (lastUnitsListForMap) setUnitsMarkers(lastUnitsListForMap);
      }, 200);
    } else if (!need && posSimFlushTimer) {
      clearInterval(posSimFlushTimer);
      posSimFlushTimer = null;
    }
  }

  function resolveSimulatedLatLng(id, liveLatlng, existingMarker) {
    var p = getDisplayPrefs();
    var delayOn = p.positionDelayEnabled && p.positionDelayMs > 0;
    var lossOn = p.packetLossEnabled && p.packetLossPercent > 0;
    var liveKey = Math.round(liveLatlng.lat * 10) / 10 + ',' + Math.round(liveLatlng.lng * 10) / 10;
    var liveChanged = unitPosLiveSeen[id] !== liveKey;
    if (!delayOn && !lossOn) {
      if (unitPosQueues[id]) delete unitPosQueues[id];
      unitPosLiveSeen[id] = liveKey;
      unitPosDisplayed[id] = { lat: liveLatlng.lat, lng: liveLatlng.lng };
      return liveLatlng;
    }
    var isFirst = !unitPosDisplayed[id] && !existingMarker;
    if (lossOn && liveChanged && !isFirst && Math.random() * 100 < p.packetLossPercent) {
      // Mise à jour « perdue » : on mémorise la position live pour ne pas rejouer le tirage,
      // mais on conserve l’affichage précédent.
      unitPosLiveSeen[id] = liveKey;
      if (unitPosDisplayed[id]) {
        return L.latLng(unitPosDisplayed[id].lat, unitPosDisplayed[id].lng);
      }
      if (existingMarker) return existingMarker.getLatLng();
      return liveLatlng;
    }
    if (liveChanged) unitPosLiveSeen[id] = liveKey;
    if (!delayOn) {
      if (liveChanged || !unitPosDisplayed[id]) {
        unitPosDisplayed[id] = { lat: liveLatlng.lat, lng: liveLatlng.lng };
      }
      return L.latLng(unitPosDisplayed[id].lat, unitPosDisplayed[id].lng);
    }
    if (!unitPosQueues[id]) unitPosQueues[id] = [];
    var q = unitPosQueues[id];
    if (liveChanged) {
      q.push({ t: Date.now(), lat: liveLatlng.lat, lng: liveLatlng.lng });
      if (q.length > 48) q.splice(0, q.length - 48);
    }
    if (isFirst) {
      unitPosDisplayed[id] = { lat: liveLatlng.lat, lng: liveLatlng.lng };
      return liveLatlng;
    }
    var cutoff = Date.now() - p.positionDelayMs;
    var best = null;
    var bestIdx = -1;
    for (var i = 0; i < q.length; i++) {
      if (q[i].t <= cutoff) {
        best = q[i];
        bestIdx = i;
      }
    }
    if (bestIdx > 0) q.splice(0, bestIdx);
    if (best) {
      unitPosDisplayed[id] = { lat: best.lat, lng: best.lng };
      return L.latLng(best.lat, best.lng);
    }
    if (unitPosDisplayed[id]) {
      return L.latLng(unitPosDisplayed[id].lat, unitPosDisplayed[id].lng);
    }
    if (existingMarker) return existingMarker.getLatLng();
    unitPosDisplayed[id] = { lat: liveLatlng.lat, lng: liveLatlng.lng };
    return liveLatlng;
  }

  function buildIntelDotIcon(callSign, size, labelPx) {
    var S = window.ATAKMarkerSizes;
    var d = S ? S.px('micro') : 10;
    var html = '<span class="atak-intel-marker-dot" style="width:' + d + 'px;height:' + d + 'px;"></span>' + unitBillboardLabel(callSign);
    if (S && S.divIcon) {
      return S.divIcon(L, html, 'micro', { className: 'atak-unit-intel-dot-marker atak-compact-marker' });
    }
    return L.divIcon({
      className: 'atak-unit-intel-dot-marker atak-compact-marker',
      html: html,
      iconSize: [d, d],
      iconAnchor: [d / 2, d / 2]
    });
  }

  function buildDotIcon(callSign, color, size, labelPx) {
    var S = window.ATAKMarkerSizes;
    var d = S ? S.clampPref(size || S.px('small')) : Math.max(10, Math.round(size || 14));
    var safeColor = color && /^#[0-9A-Fa-f]{6}$/.test(color) ? color : '#22c55e';
    var html = '<span class="atak-unit-dot" style="width:' + d + 'px;height:' + d + 'px;background:' + safeColor + ';"></span>' + unitBillboardLabel(callSign);
    if (S && S.divIcon) {
      return S.divIcon(L, html, d, { className: 'atak-unit-dot-marker atak-compact-marker' });
    }
    return L.divIcon({
      className: 'atak-unit-dot-marker atak-compact-marker',
      html: html,
      iconSize: [d, d],
      iconAnchor: [d / 2, d / 2]
    });
  }

  function refreshInclinedMarkers() {
    refreshUnitMarkerIcons();
    if (!lastAirListForMap) return;
    Object.keys(airAssetsById).forEach(function (k) {
      if (airAssetsById[k]) airAssetsById[k]._atakIconSig = '';
    });
    setAirAssets(lastAirListForMap);
  }

  function refreshUnitMarkerIcons() {
    Object.keys(unitsById).forEach(function (k) {
      if (unitsById[k]) unitsById[k]._atakIconSig = '';
    });
    if (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function') {
      setUnitsMarkers(window.ATAKUnits.getUnits());
    } else if (lastUnitsListForMap) {
      setUnitsMarkers(lastUnitsListForMap);
    }
  }

  function syncRangePair(inputId, valId, value) {
    var el = document.getElementById(inputId);
    var val = document.getElementById(valId);
    if (el) {
      el.value = String(value);
      el.setAttribute('aria-valuenow', String(value));
    }
    if (val) val.textContent = String(value);
  }

  function syncDisplayPrefsUi() {
    var p = getDisplayPrefs();
    var styleEl = document.getElementById('atak-unit-style-mode');
    if (styleEl) styleEl.value = p.styleMode;
    var mapStyleEl = document.getElementById('atak-map-look-style');
    if (mapStyleEl) mapStyleEl.value = p.styleMode;
    var settingsStyleEl = document.getElementById('atak-settings-look-style');
    if (settingsStyleEl) settingsStyleEl.value = p.styleMode;
    syncRangePair('atak-unit-icon-size', 'atak-unit-icon-size-val', p.iconSize);
    syncRangePair('atak-map-look-icon-size', 'atak-map-look-icon-size-val', p.iconSize);
    syncRangePair('atak-settings-look-icon-size', 'atak-settings-look-icon-size-val', p.iconSize);
    syncRangePair('atak-unit-label-size', 'atak-unit-label-size-val', p.labelSize);
    syncRangePair('atak-map-look-label-size', 'atak-map-look-label-size-val', p.labelSize);
    syncRangePair('atak-settings-look-label-size', 'atak-settings-look-label-size-val', p.labelSize);
    var ftEl = document.getElementById('atak-unit-ft-frame');
    if (ftEl) ftEl.checked = !!p.showFtFrame;
    var mapFtEl = document.getElementById('atak-map-look-ft-frame');
    if (mapFtEl) mapFtEl.checked = !!p.showFtFrame;
    var settingsFtEl = document.getElementById('atak-settings-look-ft-frame');
    if (settingsFtEl) settingsFtEl.checked = !!p.showFtFrame;
    var depthEl = document.getElementById('atak-unit-marker-depth');
    if (depthEl) depthEl.checked = !!p.markerDepth;
    var mapDepthEl = document.getElementById('atak-map-look-depth');
    if (mapDepthEl) mapDepthEl.checked = !!p.markerDepth;
    var settingsDepthEl = document.getElementById('atak-settings-look-depth');
    if (settingsDepthEl) settingsDepthEl.checked = !!p.markerDepth;
    var motionEl = document.getElementById('atak-unit-marker-motion');
    if (motionEl) motionEl.checked = !!p.markerMotion;
    var mapMotionEl = document.getElementById('atak-map-look-motion');
    if (mapMotionEl) mapMotionEl.checked = !!p.markerMotion;
    var settingsMotionEl = document.getElementById('atak-settings-look-motion');
    if (settingsMotionEl) settingsMotionEl.checked = !!p.markerMotion;
    var intelPhotosEl = document.getElementById('atak-show-intel-photo-markers');
    if (intelPhotosEl) intelPhotosEl.checked = !!p.showIntelPhotoMarkers;
    var autoCenterSelf = document.getElementById('atak-auto-center-self');
    if (autoCenterSelf) autoCenterSelf.checked = !!p.autoCenterSelf;
    var showDelayedUnits = document.getElementById('atak-show-delayed-units');
    if (showDelayedUnits) showDelayedUnits.checked = !!p.showDelayedUnits;
    var sseOverlay = document.getElementById('atak-show-sse-overlay');
    if (sseOverlay) sseOverlay.checked = !!p.showSseOverlay;
    var missionOverlay = document.getElementById('atak-show-mission-overlay');
    if (missionOverlay) missionOverlay.checked = !!p.showMissionOverlay;
    var sseCases = document.getElementById('atak-sse-layer-cases');
    if (sseCases) sseCases.checked = !!p.showSseLayer_cases;
    var ssePir = document.getElementById('atak-sse-layer-pir');
    if (ssePir) ssePir.checked = !!p.showSseLayer_pir;
    var sseTask = document.getElementById('atak-sse-layer-taskings');
    if (sseTask) sseTask.checked = !!p.showSseLayer_taskings;
    var ssePhotos = document.getElementById('atak-sse-layer-photos');
    if (ssePhotos) ssePhotos.checked = !!p.showSseLayer_photos;
    var sseIntel = document.getElementById('atak-sse-layer-intel');
    if (sseIntel) sseIntel.checked = !!p.showSseLayer_intel;
    var sseTracks = document.getElementById('atak-sse-layer-tracks');
    if (sseTracks) sseTracks.checked = !!p.showSseTracks;
    var sseGhost = document.getElementById('atak-sse-layer-ghost');
    if (sseGhost) sseGhost.checked = !!p.showSseGhostTracks;
    var sseHist = document.getElementById('atak-sse-layer-history');
    if (sseHist) sseHist.checked = !!p.showSseHistory;
    var unitTrails = document.getElementById('atak-show-unit-trails');
    if (unitTrails) unitTrails.checked = !!p.showUnitTrails;
    var unitGhost = document.getElementById('atak-show-unit-ghost-trails');
    if (unitGhost) unitGhost.checked = !!p.showUnitGhostTrails;
    var lookArrows = document.getElementById('atak-map-look-motion-arrows');
    if (lookArrows) lookArrows.checked = !!p.showMotionArrows;
    var lookLines = document.getElementById('atak-map-look-assignment-lines');
    if (lookLines) lookLines.checked = !!p.showAssignmentLines;
    var motionArrows = document.getElementById('atak-show-motion-arrows');
    if (motionArrows) motionArrows.checked = !!p.showMotionArrows;
    var motionProj = document.getElementById('atak-show-motion-projection');
    if (motionProj) motionProj.checked = !!p.showMotionProjection;
    var assignLines = document.getElementById('atak-show-assignment-lines');
    if (assignLines) assignLines.checked = !!p.showAssignmentLines;
    var motionTrail = document.getElementById('atak-show-motion-trail');
    if (motionTrail) motionTrail.checked = !!p.showMotionTrail;
    var etaLabels = document.getElementById('atak-show-eta-labels');
    if (etaLabels) etaLabels.checked = !!p.showEtaLabels;
    var delayEn = document.getElementById('atak-pos-delay-enabled');
    var delaySec = document.getElementById('atak-pos-delay-sec');
    var delayVal = document.getElementById('atak-pos-delay-sec-val');
    if (delayEn) delayEn.checked = !!p.positionDelayEnabled;
    if (delaySec) {
      delaySec.value = String(p.positionDelayMs / 1000);
      delaySec.disabled = !p.positionDelayEnabled;
      delaySec.setAttribute('aria-valuenow', String(p.positionDelayMs / 1000));
    }
    if (delayVal) delayVal.textContent = (p.positionDelayMs / 1000) + ' s';
    var lossEn = document.getElementById('atak-pos-loss-enabled');
    var lossPct = document.getElementById('atak-pos-loss-pct');
    var lossVal = document.getElementById('atak-pos-loss-pct-val');
    if (lossEn) lossEn.checked = !!p.packetLossEnabled;
    if (lossPct) {
      lossPct.value = String(p.packetLossPercent);
      lossPct.disabled = !p.packetLossEnabled;
      lossPct.setAttribute('aria-valuenow', String(p.packetLossPercent));
    }
    if (lossVal) lossVal.textContent = p.packetLossPercent + ' %';
    var pri = document.getElementById('atak-unit-marker-priority');
    if (pri) {
      pri.value = getUnitMarkerPriority();
      pri.disabled = p.styleMode !== 'nato';
    }
  }

  function bindDisplayPrefsUi() {
    if (bindDisplayPrefsUi._bound) return;
    bindDisplayPrefsUi._bound = true;
    syncDisplayPrefsUi();
    applyDisplayPrefsToMapDom();
    ensurePosSimFlushTimer();

    var select = document.getElementById('atak-unit-marker-priority');
    if (select && !select._atakBound) {
      select._atakBound = true;
      select.addEventListener('change', function () {
        setUnitMarkerPriority(select.value);
      });
    }

    function bindStyleSelect(id) {
      var el = document.getElementById(id);
      if (!el || el._atakBound) return;
      el._atakBound = true;
      el.addEventListener('change', function () {
        patchDisplayPrefs({ styleMode: el.value });
        syncDisplayPrefsUi();
      });
    }
    bindStyleSelect('atak-unit-style-mode');
    bindStyleSelect('atak-map-look-style');
    bindStyleSelect('atak-settings-look-style');

    function bindIconSize(id) {
      var el = document.getElementById(id);
      if (!el || el._atakBound) return;
      el._atakBound = true;
      function onIconSize() {
        patchDisplayPrefs({ iconSize: parseInt(el.value, 10) });
        syncDisplayPrefsUi();
      }
      el.addEventListener('input', onIconSize);
      el.addEventListener('change', onIconSize);
    }
    bindIconSize('atak-unit-icon-size');
    bindIconSize('atak-map-look-icon-size');
    bindIconSize('atak-settings-look-icon-size');

    function bindLabelSize(id) {
      var el = document.getElementById(id);
      if (!el || el._atakBound) return;
      el._atakBound = true;
      function onLabelSize() {
        patchDisplayPrefs({ labelSize: parseInt(el.value, 10) });
        syncDisplayPrefsUi();
      }
      el.addEventListener('input', onLabelSize);
      el.addEventListener('change', onLabelSize);
    }
    bindLabelSize('atak-unit-label-size');
    bindLabelSize('atak-map-look-label-size');
    bindLabelSize('atak-settings-look-label-size');

    function bindCheck(id, key) {
      var el = document.getElementById(id);
      if (!el || el._atakBound) return;
      el._atakBound = true;
      el.addEventListener('change', function () {
        var patch = {};
        patch[key] = !!el.checked;
        patchDisplayPrefs(patch);
        syncDisplayPrefsUi();
      });
    }
    bindCheck('atak-unit-ft-frame', 'showFtFrame');
    bindCheck('atak-map-look-ft-frame', 'showFtFrame');
    bindCheck('atak-settings-look-ft-frame', 'showFtFrame');
    bindCheck('atak-unit-marker-depth', 'markerDepth');
    bindCheck('atak-map-look-depth', 'markerDepth');
    bindCheck('atak-settings-look-depth', 'markerDepth');
    bindCheck('atak-unit-marker-motion', 'markerMotion');
    bindCheck('atak-map-look-motion', 'markerMotion');
    bindCheck('atak-settings-look-motion', 'markerMotion');
    bindCheck('atak-show-intel-photo-markers', 'showIntelPhotoMarkers');

    function bindSseToggle(id, key) {
      bindCheck(id, key);
    }
    bindSseToggle('atak-show-sse-overlay', 'showSseOverlay');
    bindSseToggle('atak-show-mission-overlay', 'showMissionOverlay');
    bindSseToggle('atak-sse-layer-cases', 'showSseLayer_cases');
    bindSseToggle('atak-sse-layer-pir', 'showSseLayer_pir');
    bindSseToggle('atak-sse-layer-taskings', 'showSseLayer_taskings');
    bindSseToggle('atak-sse-layer-photos', 'showSseLayer_photos');
    bindSseToggle('atak-sse-layer-intel', 'showSseLayer_intel');
    bindSseToggle('atak-sse-layer-tracks', 'showSseTracks');
    bindSseToggle('atak-sse-layer-ghost', 'showSseGhostTracks');
    bindSseToggle('atak-sse-layer-history', 'showSseHistory');
    bindSseToggle('atak-show-unit-trails', 'showUnitTrails');
    bindSseToggle('atak-show-unit-ghost-trails', 'showUnitGhostTrails');
    bindSseToggle('atak-show-motion-arrows', 'showMotionArrows');
    bindSseToggle('atak-show-motion-projection', 'showMotionProjection');
    bindSseToggle('atak-show-assignment-lines', 'showAssignmentLines');
    bindSseToggle('atak-show-motion-trail', 'showMotionTrail');
    bindSseToggle('atak-show-eta-labels', 'showEtaLabels');
    bindSseToggle('atak-map-look-motion-arrows', 'showMotionArrows');
    bindSseToggle('atak-map-look-assignment-lines', 'showAssignmentLines');

    var autoCenterSelf = document.getElementById('atak-auto-center-self');
    if (autoCenterSelf && !autoCenterSelf._atakBound) {
      autoCenterSelf._atakBound = true;
      autoCenterSelf.addEventListener('change', function () {
        selfAutoCentered = false;
        patchDisplayPrefs({ autoCenterSelf: !!autoCenterSelf.checked });
      });
    }

    var showDelayedUnits = document.getElementById('atak-show-delayed-units');
    if (showDelayedUnits && !showDelayedUnits._atakBound) {
      showDelayedUnits._atakBound = true;
      showDelayedUnits.addEventListener('change', function () {
        patchDisplayPrefs({ showDelayedUnits: !!showDelayedUnits.checked });
        syncDisplayPrefsUi();
      });
    }

    var delayEn = document.getElementById('atak-pos-delay-enabled');
    if (delayEn && !delayEn._atakBound) {
      delayEn._atakBound = true;
      delayEn.addEventListener('change', function () {
        patchDisplayPrefs({ positionDelayEnabled: !!delayEn.checked });
        syncDisplayPrefsUi();
      });
    }
    function onDelaySec() {
      var el = document.getElementById('atak-pos-delay-sec');
      if (!el) return;
      var sec = parseFloat(el.value);
      patchDisplayPrefs({ positionDelayMs: Math.round(sec * 1000) });
      syncDisplayPrefsUi();
    }
    var delaySec = document.getElementById('atak-pos-delay-sec');
    if (delaySec && !delaySec._atakBound) {
      delaySec._atakBound = true;
      delaySec.addEventListener('input', onDelaySec);
      delaySec.addEventListener('change', onDelaySec);
    }

    var lossEn = document.getElementById('atak-pos-loss-enabled');
    if (lossEn && !lossEn._atakBound) {
      lossEn._atakBound = true;
      lossEn.addEventListener('change', function () {
        patchDisplayPrefs({ packetLossEnabled: !!lossEn.checked });
        syncDisplayPrefsUi();
      });
    }
    function onLossPct() {
      var el = document.getElementById('atak-pos-loss-pct');
      if (!el) return;
      patchDisplayPrefs({ packetLossPercent: parseInt(el.value, 10) });
      syncDisplayPrefsUi();
    }
    var lossPct = document.getElementById('atak-pos-loss-pct');
    if (lossPct && !lossPct._atakBound) {
      lossPct._atakBound = true;
      lossPct.addEventListener('input', onLossPct);
      lossPct.addEventListener('change', onLossPct);
    }
  }

  function buildConfigFromAtakMapConfig(raw) {
    if (!raw || !raw.tilePattern) return null;
    var crsOpt = raw.crs || {};
    var factorx = crsOpt.factorx != null ? crsOpt.factorx : 0.006839;
    var factory = crsOpt.factory != null ? crsOpt.factory : 0.006836;
    var tileWidth = crsOpt.tileWidth != null ? crsOpt.tileWidth : 212;
    var CRS = typeof window.MGRS_CRS === 'function' ? window.MGRS_CRS(factorx, factory, tileWidth) : L.CRS.Simple;
    return {
      CRS: CRS,
      tilePattern: raw.tilePattern,
      minZoom: raw.minZoom != null ? raw.minZoom : 0,
      maxZoom: raw.maxZoom != null ? raw.maxZoom : 6,
      defaultZoom: raw.defaultZoom != null ? raw.defaultZoom : 3,
      attribution: raw.attribution || '&copy; Bohemia Interactive',
      tileSize: raw.tileSize != null ? raw.tileSize : 212,
      center: Array.isArray(raw.center) ? raw.center : [15000, 15000],
      offsetX: raw.offsetX != null ? parseFloat(raw.offsetX) : 0,
      offsetY: raw.offsetY != null ? parseFloat(raw.offsetY) : 0,
      worldSize: raw.worldSize != null ? parseFloat(raw.worldSize) : 30720
    };
  }

  function scheduleInvalidateSize() {
    if (!map) return;
    clearTimeout(invalidateTimer);
    invalidateTimer = setTimeout(function () {
      if (!map) return;
      try { map.invalidateSize({ animate: false }); } catch (err) {}
    }, 140);
  }

  function destroy() {
    if (mapResizeObserver) {
      try { mapResizeObserver.disconnect(); } catch (e) {}
      mapResizeObserver = null;
    }
    clearTimeout(invalidateTimer);
    invalidateTimer = null;
    lastMapSizeKey = '';
    if (window._atakMapResizeHandler) {
      window.removeEventListener('resize', window._atakMapResizeHandler);
      window._atakMapResizeHandler = null;
    }
    if (!map) return;
    map.remove();
    map = null;
    config = null;
    layerGroups = {};
    markersById = {};
    intelLayer = null;
    intelMarkersById = {};
    designatorLayer = null;
    designatorMarkersById = {};
    sigintLayer = null;
    sigintCirclesById = {};
    pingTempLayer = null;
    pingTempMarkersById = {};
    pingLayer = null;
    pingMarkersById = {};
    explosiveLayer = null;
    explosiveMarkersById = {};
    gpsVehicleLayer = null;
    gpsVehicleMarkersById = {};
    airAssetsLayer = null;
    airAssetsById = {};
    unitsLayer = null;
    unitsById = {};
    baseTileLayer = null;
    tileFailCount = 0;
    if (posSimFlushTimer) {
      clearInterval(posSimFlushTimer);
      posSimFlushTimer = null;
    }
    clearPosSimState();
    lastUnitsListForMap = null;
    lastAirListForMap = null;
  }

  function init(mapId) {
    destroy();
    config = null;
    if (window.ATAK_MAP_CONFIG) {
      config = buildConfigFromAtakMapConfig(window.ATAK_MAP_CONFIG);
    }
    if (!config && window.Arma3Map && window.Arma3Map.Maps && window.Arma3Map.Maps.altis) {
      config = window.Arma3Map.Maps.altis;
    }
    if (!config) {
      console.error('ATAKMap: no map config (set window.ATAK_MAP_CONFIG or load a map script)');
      return null;
    }
    var el = document.getElementById('atak-map');
    if (!el) return null;

    map = L.map('atak-map', {
      minZoom: config.minZoom,
      maxZoom: config.maxZoom,
      crs: config.CRS
    });
    if (window.ATAKMarkerSizes && typeof window.ATAKMarkerSizes.bindZoom === 'function') {
      window.ATAKMarkerSizes.bindZoom(map);
    }

    var tileLayer = L.tileLayer(config.tilePattern, {
      attribution: config.attribution,
      tileSize: config.tileSize,
      errorTileUrl: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
    });
    tileFailCount = 0;
    tileLayer.on('tileerror', function () {
      tileFailCount += 1;
      if (tileFailCount === 8 && !window._atakTileErrorShown) {
        window._atakTileErrorShown = true;
        if (window.ATAKShowError) {
          window.ATAKShowError('Fond de carte indisponible (tuiles). Vérifiez le CDN ou basculez de théâtre.');
        }
      }
    });
    tileLayer.on('load', function () {
      tileFailCount = 0;
    });
    tileLayer.addTo(map);
    baseTileLayer = tileLayer;

    intelLayer = L.layerGroup().addTo(map);
    intelMarkersById = {};
    designatorLayer = L.layerGroup().addTo(map);
    designatorMarkersById = {};
    unitsLayer = L.layerGroup().addTo(map);
    unitsById = {};
    airAssetsLayer = L.layerGroup().addTo(map);
    airAssetsById = {};

    map.setView(config.center, config.defaultZoom);
    L.control.scale({ maxWidth: 160, imperial: false, metric: true, position: 'bottomleft' }).addTo(map);

    var gridEl = document.getElementById('atak-map-hud');
    if (!gridEl) {
      gridEl = L.DomUtil.create('div', 'atak-map-hud');
      gridEl.id = 'atak-map-hud';
      gridEl.innerHTML =
        '<div class="atak-map-hud__row"><span class="atak-map-hud__k">Grille</span> <span class="atak-map-hud__v" data-hud-grid>0 0</span></div>'
        + '<div class="atak-map-hud__row"><span class="atak-map-hud__k">Échelle</span> <span class="atak-map-hud__v" data-hud-zoom>Z' + map.getZoom() + '</span></div>'
        + '<div class="atak-map-hud__row" data-hud-measure-row hidden><span class="atak-map-hud__k">Mesure</span> <span class="atak-map-hud__v atak-map-hud__measure" data-hud-measure>—</span></div>'
        + '<div class="atak-map-hud__row"><span class="atak-map-hud__k">Contacts</span> <span class="atak-map-hud__v" data-hud-contacts>—</span></div>'
        + '<div class="atak-map-hud__row"><span class="atak-map-hud__k">Réseau</span> <span class="atak-map-hud__v atak-map-hud__ok" data-hud-net>En liaison</span></div>';
    }
    var zoomHud = gridEl.querySelector('[data-hud-zoom]');
    if (zoomHud) zoomHud.textContent = 'Z' + map.getZoom();
    var brStack = document.getElementById('atak-map-br-stack');
    if (brStack && gridEl.parentNode !== brStack) {
      brStack.appendChild(gridEl);
    } else if (!brStack && gridEl.parentNode !== map.getContainer()) {
      map.getContainer().appendChild(gridEl);
    }
    map.on('mousemove', function (e) {
      var lat = Math.round(e.latlng.lat);
      var lng = Math.round(e.latlng.lng);
      var v = gridEl.querySelector('[data-hud-grid]');
      if (v) v.textContent = lng + ' ' + lat;
    });
    map.on('zoomend', function () {
      var z = gridEl.querySelector('[data-hud-zoom]');
      if (z) z.textContent = 'Z' + map.getZoom();
    });
    // Recalcule la taille Leaflet après layout flex (carte + tiroir effectifs).
    // Debounce + seuil de taille : évite une boucle reflow / tremblement plein écran.
    setTimeout(scheduleInvalidateSize, 0);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        if (!map) return;
        try { map.invalidateSize(true); } catch (err) {}
      });
    });
    window._atakMapResizeHandler = function () {
      if (!map) return;
      try { map.invalidateSize(false); } catch (err) {}
    };
    window.addEventListener('resize', window._atakMapResizeHandler);
    if (typeof ResizeObserver !== 'undefined') {
      mapResizeObserver = new ResizeObserver(function (entries) {
        var cr = entries && entries[0] && entries[0].contentRect;
        if (!cr) return;
        var w = Math.round(cr.width);
        var h = Math.round(cr.height);
        if (w < 2 || h < 2) return;
        var key = w + 'x' + h;
        if (key === lastMapSizeKey) return;
        lastMapSizeKey = key;
        scheduleInvalidateSize();
      });
      try { mapResizeObserver.observe(el); } catch (e) {}
    }

    window.ATAKMap._map = map;
    applyDisplayPrefsToMapDom();
    ensurePosSimFlushTimer();
    try {
      window.dispatchEvent(new CustomEvent('atak:mapready', { detail: { map: map, mapId: mapId } }));
    } catch (e) {}
    return map;
  }

  function getMap() { return map; }

  function getConfig() { return config; }

  function applyOffset(lat, lng) {
    if (!config) return [lat, lng];
    var ox = config.offsetX != null ? config.offsetX : 0;
    var oy = config.offsetY != null ? config.offsetY : 0;
    return [lat + oy, lng + ox];
  }

  function latLngFromWorld(x, y) {
    var a = applyOffset(y, x);
    return L.latLng(a[0], a[1]);
  }

  function worldFromLatLng(ll) {
    if (!ll) return { x: 0, y: 0 };
    var ox = config && config.offsetX != null ? config.offsetX : 0;
    var oy = config && config.offsetY != null ? config.offsetY : 0;
    return { x: ll.lng - ox, y: ll.lat - oy };
  }

  function ensureLayer(layerId) {
    if (!layerGroups[layerId]) {
      layerGroups[layerId] = L.layerGroup().addTo(map);
    }
    return layerGroups[layerId];
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function markerSizePx(size) {
    var S = window.ATAKMarkerSizes;
    if (S) {
      if (size === 'sm' || size === 'small') return S.px('micro');
      if (size === 'lg' || size === 'large') return S.px('important');
      if (typeof size === 'number' && size > 0) return S.clampPref(size);
      return S.px('small');
    }
    if (size === 'sm' || size === 'small') return 10;
    if (size === 'lg' || size === 'large') return 18;
    if (typeof size === 'number' && size > 0) return size;
    return 14;
  }

  function bindMarkerChrome(marker, title, lines) {
    var S = window.ATAKMarkerSizes;
    if (!S) return marker;
    if (title) S.bindHoverTip(marker, S.hoverTipHtml(title, lines || []));
    S.bindSelectVisual(marker);
    return marker;
  }

  function buildManualMarkerIcon(data) {
    data = data || {};
    var nato = window.NatoSidcIcons;
    var useMil = data.sidc || data.icon === 'milsymbol' || data.symbolMode === 'tactical';
    if (useMil && nato && nato.leafletDivIcon) {
      var px = markerSizePx(data.size);
      return nato.leafletDivIcon(L, {
        sidc: data.sidc,
        affiliation: data.affiliation || 'friend',
        functionid: data.functionid,
        scheme: data.scheme,
        battledimension: data.battledimension,
        callSign: '',
        showLabel: false,
        size: Math.max((window.ATAKMarkerSizes ? window.ATAKMarkerSizes.px('tactical') : 19), Math.min(px, (window.ATAKMarkerSizes ? window.ATAKMarkerSizes.px('important') : 22))),
      });
    }
    var color = data.color || '#34d399';
    var kind = data.icon || data.symbol || 'dot';
    var px2 = markerSizePx(data.size);
    var html;
    if (kind === 'pin') {
      html = '<span class="atak-micon atak-micon--pin" style="--m-color:' + color + ';--m-size:' + px2 + 'px"></span>';
    } else if (kind === 'flag') {
      html = '<span class="atak-micon atak-micon--flag" style="--m-color:' + color + ';--m-size:' + px2 + 'px"></span>';
    } else if (kind === 'warning') {
      html = '<span class="atak-micon atak-micon--warning" style="--m-color:' + color + ';--m-size:' + px2 + 'px">!</span>';
    } else if (kind === 'target') {
      html = '<span class="atak-micon atak-micon--target" style="--m-color:' + color + ';--m-size:' + px2 + 'px"></span>';
    } else {
      html = '<span class="atak-micon atak-micon--dot" style="--m-color:' + color + ';--m-size:' + px2 + 'px"></span>';
    }
    var box = px2;
    var htmlWrap = window.ATAKMarkerSizes && window.ATAKMarkerSizes.wrapGlyph ? window.ATAKMarkerSizes.wrapGlyph(html) : html;
    return L.divIcon({
      className: 'atak-marker-icon atak-compact-marker',
      html: htmlWrap,
      iconSize: [box, box],
      iconAnchor: [box / 2, box / 2],
      popupAnchor: [0, -box / 2]
    });
  }

  function markerPopupHtml(data, lng, lat, markerId) {
    var arma = window.ArmaMapMarkers;
    var label = (arma && arma.displayLabelOf)
      ? arma.displayLabelOf(data)
      : (data.label || data.text || data.message || data.name || data.symbolName || 'Repère');
    var author = data.author || data.createdBy || '';
    var desc = data.description || data.desc || '';
    var gx = Math.round(Number(lng));
    var gy = Math.round(Number(lat));
    var html = '<div class="atak-marker-popup"' + (markerId != null ? ' data-atak-marker-id="' + escapeHtml(String(markerId)) + '"' : '') + '>';
    html += '<div class="atak-marker-popup__kind">Repère carte</div>';
    html += '<strong>' + escapeHtml(label) + '</strong>';
    var typeFr = (arma && arma.typeLabelFr)
      ? arma.typeLabelFr(data)
      : '';
    if (data.symbolName || data.affiliation || typeFr) {
      var affFr = (window.MilstdCatalog && window.MilstdCatalog.affiliationLabelFr)
        ? window.MilstdCatalog.affiliationLabelFr(data.affiliation)
        : '';
      var symLine = [];
      if (data.symbolName) symLine.push(escapeHtml(data.symbolName));
      else if (typeFr && typeFr !== label) symLine.push(escapeHtml(typeFr));
      if (affFr) symLine.push(escapeHtml(affFr));
      if (symLine.length) html += '<div class="atak-marker-popup__symbol">' + symLine.join(' · ') + '</div>';
    }
    html += '<div class="atak-marker-popup__coords">Grille ' + gx + ' / ' + gy + '</div>';
    if (desc) html += '<p class="atak-marker-popup__desc">' + escapeHtml(desc) + '</p>';
    if (author) html += '<span class="atak-marker-popup__author">' + escapeHtml(author) + '</span>';
    html += '<p class="atak-marker-popup__hint">Ce point n’est pas un effectif en liaison — c’est un repère posé sur la carte.</p>';
    html += '<div class="atak-marker-popup__arrivals" data-atak-arrivals></div>';
    html += '</div>';
    return html;
  }

  function liveUnitsForMarkerDedupe() {
    if (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function') {
      return window.ATAKUnits.getUnits() || [];
    }
    return Array.isArray(lastUnitsListForMap) ? lastUnitsListForMap : [];
  }

  function shouldHideMarkerVsUnits(data) {
    var arma = window.ArmaMapMarkers;
    if (!arma || typeof arma.isLiveUnitDuplicate !== 'function') return false;
    return !!arma.isLiveUnitDuplicate(data, liveUnitsForMarkerDedupe());
  }

  function emitFeatureContextMenu(detail, e) {
    if (!e || !e.originalEvent) return;
    L.DomEvent.preventDefault(e);
    L.DomEvent.stopPropagation(e);
    try { e.originalEvent.stopImmediatePropagation(); } catch (err) {}
    var oe = e.originalEvent;
    window.dispatchEvent(new CustomEvent('atak:feature-contextmenu', {
      detail: Object.assign({}, detail, {
        latlng: e.latlng || null,
        clientX: oe.clientX,
        clientY: oe.clientY
      })
    }));
  }

  function bindMarkerContextMenu(marker, id) {
    if (!marker || marker._atakCtxBound) return;
    marker._atakCtxBound = true;
    marker.on('contextmenu', function (e) {
      var data = marker._atakData || {};
      emitFeatureContextMenu({
        featureType: 'marker',
        id: id,
        layerId: marker._atakLayerId,
        data: data,
        label: data.label || data.text || data.name || 'Marqueur'
      }, e);
    });
  }

  function removeExistingMarkerLayer(id) {
    var prev = markersById[id];
    if (!prev) return;
    var lg = layerGroups[prev._atakLayerId];
    if (lg) {
      try { lg.removeLayer(prev); } catch (e) {}
    } else {
      try { if (map) map.removeLayer(prev); } catch (e2) {}
    }
    delete markersById[id];
  }

  function extractMarkerPos(data) {
    if (!data || typeof data !== 'object') return null;
    var pos = data.pos;
    if (Array.isArray(pos) && pos.length >= 2) {
      if (Array.isArray(pos[0]) && pos[0].length >= 2) {
        return [Number(pos[0][0]), Number(pos[0][1])];
      }
      return [Number(pos[0]), Number(pos[1])];
    }
    if (data.pos_x != null && data.pos_y != null) {
      return [Number(data.pos_x), Number(data.pos_y)];
    }
    return null;
  }

  function addOrUpdateMarker(payload) {
    var id = payload.id;
    var layerId = payload.layerId;
    var data = payload.data || {};
    if (shouldHideMarkerVsUnits(data)) {
      removeExistingMarkerLayer(id);
      return;
    }
    var xy = extractMarkerPos(data);
    if (!xy || isNaN(xy[0]) || isNaN(xy[1])) return;
    var lng = xy[0];
    var lat = xy[1];
    var applied = applyOffset(lat, lng);
    var latlng = L.latLng(applied[0], applied[1]);
    var popupHtml = markerPopupHtml(data, lng, lat, id);
    var armaHelper = window.ArmaMapMarkers;
    var isArma = armaHelper && typeof armaHelper.isArmaStyleMarker === 'function' && armaHelper.isArmaStyleMarker(data);
    var isArea = armaHelper && typeof armaHelper.isAreaShape === 'function' && armaHelper.isAreaShape(data);
    var isManual = !isArma && ((data.type === 'manual') || data.color || data.icon || data.size || data.description);
    var layer = ensureLayer(layerId);
    var existing = markersById[id];

    // Formes Arma (rectangle / ellipse / polyline) — couche géométrique, pas une icône point.
    if (isArea && armaHelper && armaHelper.leafletShapeLayer) {
      if (existing) removeExistingMarkerLayer(id);
      var shapeLayer = armaHelper.leafletShapeLayer(L, data, latlng);
      if (!shapeLayer) return;
      shapeLayer._atakId = id;
      shapeLayer._atakLayerId = layerId;
      shapeLayer._atakData = data;
      shapeLayer._atakGrid = { lng: lng, lat: lat };
      shapeLayer._atakIsArea = true;
      if (shapeLayer.bindPopup) shapeLayer.bindPopup(popupHtml);
      bindMarkerContextMenu(shapeLayer, id);
      shapeLayer.addTo(layer);
      markersById[id] = shapeLayer;
      return;
    }

    if (existing && existing._atakIsArea) {
      removeExistingMarkerLayer(id);
      existing = null;
    }

    if (existing) {
      if (existing.setLatLng) existing.setLatLng(latlng);
      existing._atakData = data;
      existing._atakGrid = { lng: lng, lat: lat };
      try {
        if (isArma && armaHelper.leafletDivIcon && existing.setIcon) {
          existing.setIcon(armaHelper.leafletDivIcon(L, data));
        } else if (isManual && existing.setIcon) {
          existing.setIcon(buildManualMarkerIcon(data));
        }
      } catch (e) {}
      if (existing.getPopup && existing.getPopup()) {
        existing.setPopupContent(popupHtml);
      } else if (existing.bindPopup) {
        existing.bindPopup(popupHtml);
      }
      bindMarkerContextMenu(existing, id);
      return;
    }

    var icon;
    if (isArma && armaHelper && armaHelper.leafletDivIcon) {
      icon = armaHelper.leafletDivIcon(L, data);
    } else if (isManual) {
      icon = buildManualMarkerIcon(data);
    } else {
      var nato = window.NatoSidcIcons;
      var label = data.label || data.text || data.message || data.name || '';
      if (nato && nato.leafletDivIcon) {
        icon = nato.leafletDivIcon(L, {
          affiliation: 'friend',
          role: 'point',
          roleKey: 'recon',
          callSign: '',
          showLabel: false,
          size: window.ATAKMarkerSizes ? window.ATAKMarkerSizes.px('normal') : 17,
        });
      } else {
        icon = buildManualMarkerIcon(data);
      }
    }
    var marker = L.marker(latlng, { icon: icon });
    marker._atakId = id;
    marker._atakLayerId = layerId;
    marker._atakData = data;
    marker._atakGrid = { lng: lng, lat: lat };
    marker.bindPopup(popupHtml);
    var tipLabel = (armaHelper && armaHelper.displayLabelOf) ? armaHelper.displayLabelOf(data) : (data.label || data.text || data.name || 'Repère');
    var tipType = (armaHelper && armaHelper.typeLabelFr) ? armaHelper.typeLabelFr(data) : '';
    bindMarkerChrome(marker, tipLabel, [
      tipType,
      'Grille ' + Math.round(lng) + ' / ' + Math.round(lat)
    ]);
    bindMarkerContextMenu(marker, id);
    marker.addTo(layer);
    markersById[id] = marker;
  }

  function getMarkerById(id) {
    var m = markersById[id];
    if (!m) return null;
    var data = m._atakData || {};
    var grid = m._atakGrid || {};
    var ll = m.getLatLng ? m.getLatLng() : null;
    if (!ll && m.getBounds) {
      try {
        var b = m.getBounds();
        if (b && b.isValid && b.isValid()) ll = b.getCenter();
      } catch (e) {}
    }
    return {
      id: id,
      layerId: m._atakLayerId,
      data: data,
      gridLng: grid.lng != null ? grid.lng : (ll ? ll.lng : null),
      gridLat: grid.lat != null ? grid.lat : (ll ? ll.lat : null)
    };
  }

  function updateMarkerById(id, markerData, layerId) {
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    var existing = getMarkerById(id);
    var data = Object.assign({}, (existing && existing.data) || {}, markerData || {});
    var lid = layerId != null ? layerId : (existing ? existing.layerId : 1);
    addOrUpdateMarker({ id: id, layerId: lid, data: data });
    if (window.ATAKMarkers && window.ATAKMarkers.refresh) window.ATAKMarkers.refresh();
    if (!base || String(id).indexOf('local_') === 0) {
      return Promise.resolve({ id: id, layerId: lid, markerData: data });
    }
    return fetch(base + '/api/markers/' + encodeURIComponent(id), {
      method: 'PATCH',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ markerData: data, layerId: lid })
    }).then(function (r) {
      if (!r.ok) throw new Error('update');
      return r.json();
    }).then(function (row) {
      if (!row) return null;
      var parsed = typeof row.markerData === 'string'
        ? (function () { try { return JSON.parse(row.markerData); } catch (e) { return data; } })()
        : (row.markerData || data);
      addOrUpdateMarker({
        id: row.id,
        layerId: row.layerId != null ? row.layerId : lid,
        data: parsed
      });
      if (window.ATAKMarkers && window.ATAKMarkers.refresh) window.ATAKMarkers.refresh();
      return row;
    });
  }

  function listMarkers() {
    return Object.keys(markersById).map(function (k) {
      var m = markersById[k];
      var data = m._atakData || {};
      var grid = m._atakGrid || {};
      var ll = m.getLatLng ? m.getLatLng() : null;
      if (!ll && m.getBounds) {
        try {
          var b = m.getBounds();
          if (b && b.isValid && b.isValid()) ll = b.getCenter();
        } catch (e) {}
      }
      return {
        id: k,
        layerId: m._atakLayerId,
        data: data,
        gridLng: grid.lng != null ? grid.lng : (ll ? ll.lng : null),
        gridLat: grid.lat != null ? grid.lat : (ll ? ll.lat : null)
      };
    });
  }

  function focusMarker(id) {
    var m = markersById[id];
    if (!m || !map) return;
    var ll = null;
    if (m.getLatLng) {
      ll = m.getLatLng();
    } else if (m.getBounds) {
      try {
        var b = m.getBounds();
        if (b && b.isValid && b.isValid()) ll = b.getCenter();
      } catch (e) {}
    }
    if (ll) {
      map.setView(ll, Math.max(map.getZoom(), 4));
      if (m.openPopup) m.openPopup();
    }
  }

  function deleteMarkerById(id) {
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    if (!base || String(id).indexOf('local_') === 0) {
      removeMarker({ id: id });
      return Promise.resolve(true);
    }
    return fetch(base + '/api/markers/' + encodeURIComponent(id), {
      method: 'DELETE',
      credentials: 'include'
    }).then(function (r) {
      if (!r.ok) throw new Error('delete');
      removeMarker({ id: id });
      return true;
    });
  }

  function removeMarker(payload) {
    var id = payload.id;
    var m = markersById[id];
    if (m) {
      var lg = layerGroups[m._atakLayerId];
      if (lg) lg.removeLayer(m);
      delete markersById[id];
    }
  }

  function removeLayer(payload) {
    var id = payload.id;
    var lg = layerGroups[id];
    if (lg) {
      map.removeLayer(lg);
      delete layerGroups[id];
    }
    Object.keys(markersById).forEach(function (k) {
      if (markersById[k]._atakLayerId === id) delete markersById[k];
    });
  }

  function addOrUpdateLayer(payload) {
    ensureLayer(payload.id);
  }

  function pointMap(userId, pos) {
    if (!map || !pos || !pos.length) return;
    var lat = pos[0];
    var lng = pos.length > 1 ? pos[1] : pos[0];
    var applied = applyOffset(lat, lng);
    map.setView(L.latLng(applied[0], applied[1]), map.getZoom());
  }

  function endPointMap(userId) {}

  function centerOn(lat, lng) {
    if (!map) return;
    var applied = applyOffset(lat, lng);
    map.setView(L.latLng(applied[0], applied[1]), map.getZoom());
  }

  function ensureIntelLayer() {
    if (!map) return null;
    if (!intelLayer) intelLayer = L.layerGroup().addTo(map);
    return intelLayer;
  }

  function addIntelPhotoMarker(id, posY, posX, photoUrl) {
    if (!getDisplayPrefs().showIntelPhotoMarkers) return;
    if (posY == null || posX == null || !photoUrl) return;
    var applied = applyOffset(posY, posX);
    var latlng = L.latLng(applied[0], applied[1]);
    var layer = ensureIntelLayer();
    if (!layer) return;
    if (intelMarkersById[id]) {
      intelMarkersById[id].setLatLng(latlng);
      return;
    }
    var fullUrl = photoUrl.indexOf('http') === 0 || photoUrl.indexOf('//') === 0 ? photoUrl : (window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '') + (photoUrl.charAt(0) === '/' ? photoUrl : '/' + photoUrl);
    var S = window.ATAKMarkerSizes;
    var intelHtml = '<span class="atak-intel-marker-dot" title="Photo terrain"></span>';
    var icon = S && S.divIcon
      ? S.divIcon(L, intelHtml, 'micro', { className: 'atak-intel-marker atak-compact-marker' })
      : L.divIcon({
          className: 'atak-intel-marker atak-compact-marker',
          html: intelHtml,
          iconSize: [10, 10],
          iconAnchor: [5, 5]
        });
    var marker = L.marker(latlng, { icon: icon });
    marker.bindPopup('<img src="' + fullUrl + '" alt="Intel" style="max-width:280px;max-height:200px;display:block;" />');
    bindMarkerChrome(marker, 'Photo terrain', []);
    marker._atakIntelId = id;
    marker.addTo(layer);
    intelMarkersById[id] = marker;
  }

  function removeIntelPhotoMarker(id) {
    var m = intelMarkersById[id];
    if (m && intelLayer) {
      intelLayer.removeLayer(m);
      delete intelMarkersById[id];
    }
  }

  function clearIntelMarkers() {
    if (!intelLayer) return;
    Object.keys(intelMarkersById).forEach(function (k) {
      intelLayer.removeLayer(intelMarkersById[k]);
    });
    intelMarkersById = {};
  }

  function addOrUpdateDesignator(row) {
    if (!map || !row) return;
    var id = 'designator_' + (row.call_sign || row.id || '');
    var posX = row.pos_x != null ? row.pos_x : 0;
    var posY = row.pos_y != null ? row.pos_y : 0;
    var applied = applyOffset(posY, posX);
    var latlng = L.latLng(applied[0], applied[1]);
    if (!designatorLayer) designatorLayer = L.layerGroup().addTo(map);
    if (designatorMarkersById[id]) {
      designatorMarkersById[id].setLatLng(latlng);
      return;
    }
    var S = window.ATAKMarkerSizes;
    var desHtml = '<span style="font-size:14px;color:#ef4444;line-height:1;">&#10010;</span>';
    var icon = S && S.divIcon
      ? S.divIcon(L, desHtml, 'tactical', { className: 'atak-designator-marker atak-compact-marker' })
      : L.divIcon({
          className: 'atak-designator-marker atak-compact-marker',
          html: desHtml,
          iconSize: [16, 16],
          iconAnchor: [8, 8]
        });
    var marker = L.marker(latlng, { icon: icon });
    marker.bindPopup('<strong>JTAC</strong> ' + (row.call_sign || '') + '<br/>Cible designee');
    bindMarkerChrome(marker, 'Désignateur JTAC', [row.call_sign || '']);
    marker._atakDesignatorId = id;
    marker.addTo(designatorLayer);
    designatorMarkersById[id] = marker;
  }

  function pingKindFromMessage(msg) {
    var rawMsg = String(msg || '');
    var m = rawMsg.match(/^\s*\[([^\]]+)\]\s*/);
    if (m) {
      var raw = m[1].toLowerCase();
      var rest = rawMsg.slice(m[0].length);
      if (raw.indexOf('drone') >= 0 || raw.indexOf('isr') >= 0) {
        var dLabel = 'Contact ISR';
        var dColor = '#f97316';
        if (raw.indexOf('eny') >= 0 || raw.indexOf('hostile') >= 0) {
          dLabel = 'ISR adversaire';
          dColor = '#ef4444';
        } else if (raw.indexOf('civ') >= 0) {
          dLabel = 'ISR civil';
          dColor = '#22c55e';
        } else if (raw.indexOf('unk') >= 0) {
          dLabel = 'ISR inconnu';
          dColor = '#eab308';
        }
        return { kind: 'drone', label: dLabel, color: dColor, rest: rest };
      }
      if (raw.indexOf('hostile') >= 0 || raw.indexOf('ennemi') >= 0) return { kind: 'hostile', label: 'Hostile', color: '#ef4444', rest: rest };
      if (raw.indexOf('jackpot') >= 0 || raw.indexOf('hvt') >= 0) return { kind: 'jackpot', label: 'JACKPOT', color: '#f59e0b', rest: rest };
      if (raw.indexOf('medical') >= 0 || raw.indexOf('médical') >= 0) return { kind: 'medical', label: 'Médical', color: '#f8fafc', rest: rest };
      if (raw.indexOf('ralli') >= 0 || raw.indexOf('rally') >= 0) return { kind: 'rally', label: 'Ralliement', color: '#22c55e', rest: rest };
      if (raw.indexOf('tir') >= 0 || raw.indexOf('feu') >= 0) return { kind: 'fire', label: 'Tir', color: '#f97316', rest: rest };
      if (raw.indexOf('impact') >= 0) return { kind: 'hit', label: 'Impact', color: '#ef4444', rest: rest };
      if (raw.indexOf('missile') >= 0) return { kind: 'missile', label: 'Missile', color: '#a855f7', rest: rest };
      if (raw.indexOf('échange') >= 0 || raw.indexOf('echange') >= 0) return { kind: 'exchange', label: 'Échange', color: '#f59e0b', rest: rest };
      if (raw.indexOf('contact') >= 0) return { kind: 'contact', label: 'Contact', color: '#f97316', rest: rest };
      if (raw.indexOf('objectif') >= 0) return { kind: 'objective', label: 'Objectif', color: '#eab308', rest: rest };
      if (raw.indexOf('alerte') >= 0) return { kind: 'warning', label: 'Alerte', color: '#f97316', rest: rest };
      if (raw.indexOf('rep') >= 0 || raw.indexOf('marqueur') >= 0 || raw.indexOf('intérêt') >= 0 || raw.indexOf('interet') >= 0) {
        return { kind: 'marker', label: m[1], color: '#ef4444', rest: rest };
      }
      return { kind: 'info', label: m[1], color: '#38bdf8', rest: rest };
    }
    var low = rawMsg.toLowerCase();
    if (low.indexOf('point d') >= 0 && low.indexOf('inter') >= 0) {
      return { kind: 'info', label: 'Intérêt', color: '#38bdf8', rest: rawMsg };
    }
    if (low.indexOf('marqueur') >= 0) {
      return { kind: 'marker', label: 'Repère', color: '#ef4444', rest: rawMsg };
    }
    return { kind: 'info', label: 'Ping', color: '#ec4899', rest: rawMsg };
  }

  function setPingsOnMap(rows) {
    if (!map) return;
    if (!pingLayer) pingLayer = L.layerGroup().addTo(map);
    var list = Array.isArray(rows) ? rows : [];
    var seen = {};
    list.forEach(function (p) {
      if (!p) return;
      var id = p.id != null ? String(p.id) : ('p_' + p.pos_x + '_' + p.pos_y + '_' + (p.created_at || ''));
      seen[id] = true;
      var x = parseFloat(p.pos_x);
      var y = parseFloat(p.pos_y);
      if (isNaN(x) || isNaN(y)) return;
      var applied = applyOffset(y, x);
      var latlng = L.latLng(applied[0], applied[1]);
      var kind = pingKindFromMessage(p.message);
      var author = String(p.author || '').trim();
      // Ne jamais afficher l’indicatif seul sous le point (confusion avec un effectif).
      var pinLabel = kind.label || 'Ping';
      var S = window.ATAKMarkerSizes;
      var pingHtml = '<span style="width:12px;height:12px;border-radius:50%;background:' + kind.color + ';border:1px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.35);display:block;"></span>';
      var icon = S && S.divIcon
        ? S.divIcon(L, pingHtml, 'small', { className: 'atak-ping-map-icon atak-compact-marker' })
        : L.divIcon({
            className: 'atak-ping-map-icon atak-compact-marker',
            html: pingHtml,
            iconSize: [14, 14],
            iconAnchor: [7, 7]
          });
      var popup = '<div class="atak-ping-popup"><div class="atak-marker-popup__kind">Ping</div><b>' +
        String(kind.label).replace(/</g, '&lt;') +
        (author ? ' — ' + String(author).replace(/</g, '&lt;') : '') +
        '</b><br/>' + String(kind.rest || p.message || '').replace(/</g, '&lt;') +
        '<p class="atak-marker-popup__hint">Signal ponctuel — ce n’est pas la position d’un effectif.</p></div>';
      if (pingMarkersById[id]) {
        pingMarkersById[id].setLatLng(latlng);
        pingMarkersById[id].setIcon(icon);
        pingMarkersById[id].setPopupContent(popup);
        return;
      }
      var marker = L.marker(latlng, { icon: icon, zIndexOffset: 350 });
      marker.bindPopup(popup);
      bindMarkerChrome(marker, pinLabel, [
        author ? 'De ' + author : '',
        String(kind.rest || p.message || '').slice(0, 80)
      ]);
      marker._atakPingId = id;
      if (!marker._atakCtxBound) {
        marker._atakCtxBound = true;
        marker.on('contextmenu', function (e) {
          emitFeatureContextMenu({
            featureType: 'ping',
            id: id,
            label: kind.label,
            data: { author: author, message: p.message || '' }
          }, e);
        });
      }
      marker.addTo(pingLayer);
      pingMarkersById[id] = marker;
    });
    Object.keys(pingMarkersById).forEach(function (k) {
      if (!seen[k]) {
        try { pingLayer.removeLayer(pingMarkersById[k]); } catch (e) {}
        delete pingMarkersById[k];
      }
    });
  }

  function formatChargeRemain(sec) {
    sec = Math.max(0, Math.floor(Number(sec) || 0));
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    if (m > 0) return m + ':' + (s < 10 ? '0' : '') + s;
    return String(s) + 's';
  }

  function setExplosiveTimersOnMap(rows) {
    if (!map) return;
    if (!explosiveLayer) explosiveLayer = L.layerGroup().addTo(map);
    var list = Array.isArray(rows) ? rows : [];
    var seen = {};
    list.forEach(function (item) {
      if (!item || item.status !== 'armed') return;
      var id = item.id != null ? String(item.id) : String(item.charge_id || '');
      if (!id) return;
      var x = parseFloat(item.pos_x);
      var y = parseFloat(item.pos_y);
      if (isNaN(x) || isNaN(y)) return;
      seen[id] = true;
      var applied = applyOffset(y, x);
      var latlng = L.latLng(applied[0], applied[1]);
      var remaining = Number(item.remaining_seconds);
      if (isNaN(remaining)) remaining = 0;
      var countdown = item.has_countdown !== false && item.has_countdown !== 0 && item.remaining_seconds != null;
      var pending = !!item.detonate_pending;
      var urgent = countdown && remaining <= 15;
      var color = pending ? '#facc15' : (urgent ? '#ef4444' : '#f97316');
      var grid = String(item.grid_ref || '').trim();
      var pinLabel = countdown ? formatChargeRemain(remaining) : (pending ? 'TOC' : 'ARM');
      var S = window.ATAKMarkerSizes;
      var chargeHtml = '<span style="width:12px;height:12px;border-radius:2px;background:' + color + ';border:1px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.4);display:block;"></span>';
      var icon = S && S.divIcon
        ? S.divIcon(L, chargeHtml, 'tactical', { className: 'atak-charge-map-icon atak-compact-marker' })
        : L.divIcon({
            className: 'atak-charge-map-icon atak-compact-marker',
            html: chargeHtml,
            iconSize: [16, 16],
            iconAnchor: [8, 8]
          });
      var kindTitle = countdown ? 'Charge à retardement' : 'Charge';
      var delayLine = countdown
        ? '<br/>Délai programmé : ' + formatChargeRemain(item.fuse_seconds) +
          '<br/>Temps restant : ' + formatChargeRemain(remaining)
        : '<br/>Déclenchement : ' + (pending ? 'ordre envoyé' : 'à la demande');
      var popup = '<div class="atak-charge-popup"><div class="atak-marker-popup__kind">' + kindTitle + '</div><b>' +
        String(item.magazine_label || 'Charge').replace(/</g, '&lt;') +
        '</b><br/>Coordonnées : ' + String(grid || (Math.round(x) + ' / ' + Math.round(y))).replace(/</g, '&lt;') +
        delayLine + '</div>';
      if (explosiveMarkersById[id]) {
        explosiveMarkersById[id].setLatLng(latlng);
        explosiveMarkersById[id].setIcon(icon);
        explosiveMarkersById[id].setPopupContent(popup);
        return;
      }
      var marker = L.marker(latlng, { icon: icon, zIndexOffset: 420 });
      marker.bindPopup(popup);
      bindMarkerChrome(marker, item.magazine_label || 'Charge', [
        pinLabel,
        grid ? 'Grille ' + grid : ''
      ]);
      marker.addTo(explosiveLayer);
      explosiveMarkersById[id] = marker;
    });
    Object.keys(explosiveMarkersById).forEach(function (k) {
      if (!seen[k]) {
        try { explosiveLayer.removeLayer(explosiveMarkersById[k]); } catch (e) {}
        delete explosiveMarkersById[k];
      }
    });
  }

  function vehicleClassLabel(cls) {
    var k = String(cls || '').toUpperCase();
    if (k === 'TANK') return 'Char';
    if (k === 'APC') return 'VCI';
    if (k === 'IFV') return 'VCI';
    if (k === 'TRUCK') return 'Camion';
    if (k === 'HELICOPTER') return 'Hélicoptère';
    if (k === 'FIXED_WING') return 'Avion';
    if (k === 'UAV') return 'Drone';
    if (k === 'BOAT') return 'Embarcation';
    if (k === 'ARTILLERY') return 'Artillerie';
    return 'Véhicule';
  }

  function setGpsVehiclesOnMap(rows) {
    if (!map) return;
    if (!gpsVehicleLayer) gpsVehicleLayer = L.layerGroup().addTo(map);
    var list = Array.isArray(rows) ? rows : [];
    var seen = {};
    list.forEach(function (item) {
      if (!item) return;
      var id = item.id != null ? String(item.id) : String(item.vehicle_callsign || '');
      if (!id) return;
      var x = parseFloat(item.pos_x);
      var y = parseFloat(item.pos_y);
      if (isNaN(x) || isNaN(y)) return;
      if (Math.abs(x) < 1 && Math.abs(y) < 1) return;
      seen[id] = true;
      var applied = applyOffset(y, x);
      var latlng = L.latLng(applied[0], applied[1]);
      var pretty = String(item.vehicle_name || item.vehicle_callsign || 'Véhicule');
      var gps = String(item.mission_type || '').toUpperCase() === 'GPS_BEACON';
      var color = gps ? '#38bdf8' : '#f59e0b';
      var kind = gps ? 'Balise GPS' : 'Véhicule suivi';
      var S = window.ATAKMarkerSizes;
      var gpsHtml = '<span style="width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;border-bottom:11px solid ' + color + ';filter:drop-shadow(0 0 1px #000);display:block;"></span>';
      var icon = S && S.divIcon
        ? S.divIcon(L, gpsHtml, 'tactical', { className: 'atak-gps-map-icon atak-compact-marker', pin: true })
        : L.divIcon({
            className: 'atak-gps-map-icon atak-compact-marker',
            html: gpsHtml,
            iconSize: [16, 16],
            iconAnchor: [8, 16]
          });
      var popup = '<div class="atak-gps-popup"><div class="atak-marker-popup__kind">' + kind + '</div><b>' +
        pretty.replace(/</g, '&lt;') +
        '</b><br/>' + vehicleClassLabel(item.vehicle_class) +
        (item.crew_count != null ? '<br/>À bord : ' + String(item.crew_count) : '') +
        (function () {
          var occ = item.passengers_json;
          if (typeof occ === 'string') {
            try { occ = JSON.parse(occ); } catch (e) { occ = []; }
          }
          if (!Array.isArray(occ) || !occ.length) return '';
          return '<div class="atak-occ atak-occ--popup">' + occ.map(function (o) {
            var n = String((o && (o.name || o.callsign)) || '').replace(/</g, '&lt;');
            return n ? ('<div>' + n + '</div>') : '';
          }).join('') + '</div>';
        }()) +
        '</div>';
      if (gpsVehicleMarkersById[id]) {
        gpsVehicleMarkersById[id].setLatLng(latlng);
        gpsVehicleMarkersById[id].setIcon(icon);
        gpsVehicleMarkersById[id].setPopupContent(popup);
        return;
      }
      var marker = L.marker(latlng, { icon: icon, zIndexOffset: 380 });
      marker.bindPopup(popup);
      bindMarkerChrome(marker, pretty, [kind, vehicleClassLabel(item.vehicle_class)]);
      marker.addTo(gpsVehicleLayer);
      gpsVehicleMarkersById[id] = marker;
    });
    Object.keys(gpsVehicleMarkersById).forEach(function (k) {
      if (!seen[k]) {
        try { gpsVehicleLayer.removeLayer(gpsVehicleMarkersById[k]); } catch (e) {}
        delete gpsVehicleMarkersById[k];
      }
    });
  }

  function addTemporaryPingMarker(posX, posY, author, message, pingId) {
    if (!map) return;
    if (!pingLayer) pingLayer = L.layerGroup().addTo(map);
    var applied = applyOffset(parseFloat(posY), parseFloat(posX));
    var latlng = L.latLng(applied[0], applied[1]);
    var kind = pingKindFromMessage(message);
    var pinLabel = String(kind.label || 'Ping').slice(0, 14);
    var id = pingId != null && String(pingId) !== '' ? String(pingId) : ('live_' + Date.now());
    var S = window.ATAKMarkerSizes;
    var pingHtml = '<span style="width:12px;height:12px;border-radius:50%;background:' + kind.color + ';border:1px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.35);display:block;"></span>';
    var icon = S && S.divIcon
      ? S.divIcon(L, pingHtml, 'small', { className: 'atak-ping-temp-icon atak-compact-marker' })
      : L.divIcon({
          className: 'atak-ping-temp-icon atak-compact-marker',
          html: pingHtml,
          iconSize: [14, 14],
          iconAnchor: [7, 7]
        });
    var marker = L.marker(latlng, { icon: icon, zIndexOffset: 400 });
    marker.bindPopup(
      '<div class="atak-ping-popup"><div class="atak-marker-popup__kind">Ping</div><b>' +
      kind.label + (author ? ' — ' + String(author).replace(/</g, '&lt;') : '') +
      '</b><br/>' + String(message || '').replace(/</g, '&lt;') +
      '<p class="atak-marker-popup__hint">Signal ponctuel — ce n’est pas la position d’un effectif.</p></div>'
    ).openPopup();
    marker._atakPingId = id;
    if (!marker._atakCtxBound) {
      marker._atakCtxBound = true;
      marker.on('contextmenu', function (e) {
        emitFeatureContextMenu({
          featureType: 'ping',
          id: marker._atakPingId || id,
          label: kind.label,
          data: { author: author, message: message || '' }
        }, e);
      });
    }
    marker.addTo(pingLayer);
    pingMarkersById[id] = marker;
    // Les pings API restent via setPingsOnMap ; l’éphémère live disparaît après sync.
    if (String(id).indexOf('live_') === 0) {
      setTimeout(function () {
        if (pingMarkersById[id] && String(id).indexOf('live_') === 0) {
          try { pingLayer.removeLayer(pingMarkersById[id]); } catch (e) {}
          delete pingMarkersById[id];
        }
      }, 120000);
    }
  }

  function removeTemporaryPingMarker(id) {
    if (!id) return false;
    if (pingMarkersById[id]) {
      if (pingLayer) {
        try { pingLayer.removeLayer(pingMarkersById[id]); } catch (e) {}
      }
      delete pingMarkersById[id];
      return true;
    }
    if (pingTempMarkersById[id]) {
      if (pingTempLayer) {
        try { pingTempLayer.removeLayer(pingTempMarkersById[id]); } catch (e) {}
      }
      delete pingTempMarkersById[id];
      return true;
    }
    return false;
  }

  function clearTemporaryPings() {
    Object.keys(pingMarkersById).forEach(function (id) {
      var key = String(id);
      if (key.indexOf('combat_') === 0 || key.indexOf('live_') === 0) {
        removeTemporaryPingMarker(id);
      }
    });
    Object.keys(pingTempMarkersById).forEach(function (id) {
      removeTemporaryPingMarker(id);
    });
  }

  function pollMarkers() {
    if (!map) return;
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    var mapId = window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
    var url = (base || '') + '/api/atak/markers?mapId=' + mapId;
    fetch(url, { credentials: 'include' }).then(function (r) {
      if (!r.ok) {
        throw new Error('markers_http_' + r.status);
      }
      return r.json();
    }).then(function (list) {
      if (!map) return;
      // Réponse d’erreur JSON ou format inattendu : ne pas vider la carte.
      if (!Array.isArray(list)) return;
      var seen = {};
      list.forEach(function (m) {
        var id = m.id;
        if (id == null) return;
        seen[id] = true;
        var data = (typeof m.markerData === 'string' ? (function () { try { return JSON.parse(m.markerData); } catch (e) { return {}; } })() : (m.markerData || {}));
        addOrUpdateMarker({ id: id, layerId: m.layerId != null ? m.layerId : 0, data: data });
      });
      Object.keys(markersById).forEach(function (k) {
        if (!seen[k] && String(k).indexOf('local_') !== 0) removeMarker({ id: k });
      });
      if (window.ATAKMarkers && window.ATAKMarkers.renderFromMap) {
        window.ATAKMarkers.renderFromMap();
      }
    }).catch(function () { /* conserver les marqueurs déjà affichés */ });
  }

  function refreshSigintZones() {
    if (!map) return;
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    var mapId = window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
    var url = (base || '') + '/api/atak/sigint/zones?mapId=' + mapId;
    fetch(url).then(function (r) { return r.json(); }).then(function (zones) {
      if (!map) return;
      if (!sigintLayer) sigintLayer = L.layerGroup().addTo(map);
      var seen = {};
      zones.forEach(function (z, i) {
        var id = 'sigint_' + i;
        seen[id] = true;
        var posX = z.pos_x != null ? z.pos_x : 0;
        var posY = z.pos_y != null ? z.pos_y : 0;
        var radius = z.radius != null ? z.radius : 200;
        var applied = applyOffset(posY, posX);
        var latlng = L.latLng(applied[0], applied[1]);
        if (sigintCirclesById[id]) {
          sigintCirclesById[id].setLatLng(latlng);
          sigintCirclesById[id].setRadius(radius);
        } else {
          var circle = L.circle(latlng, { radius: radius, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 0.15, weight: 2 });
          circle._atakSigintId = id;
          circle.addTo(sigintLayer);
          circle.bindPopup('SIGINT: zone d\'incertitude (' + (z.reports || 0) + ' rapports)');
          sigintCirclesById[id] = circle;
        }
      });
      Object.keys(sigintCirclesById).forEach(function (k) {
        if (!seen[k]) {
          sigintLayer.removeLayer(sigintCirclesById[k]);
          delete sigintCirclesById[k];
        }
      });
    }).catch(function () {});
  }

  function bindUnitMarkerContextMenu(marker) {
    if (!marker || marker._atakUnitCtxBound) return;
    marker._atakUnitCtxBound = true;
    marker.on('contextmenu', function (e) {
      if (!e || !e.originalEvent) return;
      L.DomEvent.preventDefault(e);
      L.DomEvent.stopPropagation(e);
      try { e.originalEvent.stopImmediatePropagation(); } catch (err) {}
      var unit = marker._atakUnit || {};
      var oe = e.originalEvent;
      window.dispatchEvent(new CustomEvent('atak:unit-contextmenu', {
        detail: {
          unit: unit,
          clientX: oe.clientX,
          clientY: oe.clientY,
          latlng: e.latlng || null
        }
      }));
    });
  }

  var replayActive = false;

  function setReplayActive(v) {
    replayActive = !!v;
  }

  function isReplayActive() {
    return replayActive;
  }

  function setUnitsMarkers(list, opts) {
    opts = opts || {};
    if (replayActive && !opts.fromReplay) return;
    if (!map) return;
    if (!unitsLayer) unitsLayer = L.layerGroup().addTo(map);
    lastUnitsListForMap = Array.isArray(list) ? list : [];
    applyDisplayPrefsToMapDom();
    var prefs = getDisplayPrefs();
    var nato = window.NatoSidcIcons;
    var seen = {};
    var ORIGIN_EPS = 0.5;
    function isValidPos(x, y) {
      if (isNaN(x) || isNaN(y)) return false;
      if (Math.abs(x) < ORIGIN_EPS && Math.abs(y) < ORIGIN_EPS) return false;
      return true;
    }
    function unitLive(u) {
      if (window.ATAKUnits && window.ATAKUnits.resolveLiveStatus) {
        return window.ATAKUnits.resolveLiveStatus(u);
      }
      return String((u && u.status) || '').toLowerCase();
    }
    function currentUserCallsignKey() {
      if (!window.ATAK_USER) return '';
      var raw = String(window.ATAK_USER.callsign || window.ATAK_USER.armaCallsign || '').trim();
      return raw.toUpperCase();
    }
    var ownCallsignKey = currentUserCallsignKey();
    (Array.isArray(list) ? list : []).forEach(function (u) {
      var live = unitLive(u);
      if (live === 'offline') return;
      if (!prefs.showDelayedUnits && live === 'delayed') return;
      var id = 'unit_' + (u.id != null ? u.id : (u.call_sign || Math.random()));
      var x = u.pos_x != null ? parseFloat(u.pos_x) : NaN;
      var y = u.pos_y != null ? parseFloat(u.pos_y) : NaN;
      if (!isValidPos(x, y)) {
        var gridRef = String(u.grid_ref || '').trim().split(/\s+/);
        x = parseFloat(gridRef[0]);
        y = parseFloat(gridRef[1]);
      }
      if (!isValidPos(x, y)) return;
      seen[id] = true;
      var applied = applyOffset(y, x);
      var liveLatlng = L.latLng(applied[0], applied[1]);
      var existing = unitsById[id];
      var latlng = resolveSimulatedLatLng(id, liveLatlng, existing);
      var unitCallsignKey = String(u.call_sign || '').toUpperCase().trim();
      if (!selfAutoCentered && prefs.autoCenterSelf && ownCallsignKey !== '' && unitCallsignKey === ownCallsignKey) {
        centerOn(y, x);
        selfAutoCentered = true;
      }
      var extra = {};
      try {
        if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
        else if (u.extra && typeof u.extra === 'object') extra = u.extra;
      } catch (e) {}
      if (window.ATAKUnits && window.ATAKUnits.shouldHideEnemyAi && window.ATAKUnits.shouldHideEnemyAi(u, list)) {
        return;
      }
      var P = window.ATAKUnitPopup;
      var isPhone = P && P.isPhoneGeoloc ? P.isPhoneGeoloc(extra) : !!(extra.phone_geoloc);
      var rev = (isPhone && P && P.phoneReveal) ? P.phoneReveal(extra) : null;
      var labelCs = (P && P.unitDisplayName) ? P.unitDisplayName(u, extra) : ((isPhone && P && P.phoneDisplayName) ? P.phoneDisplayName(u, extra) : (u.call_sign || ''));
      var aff = extra.affiliation || extra.affil || u.affiliation || 'friend';
      if (isPhone && (!rev || !rev.affiliation)) aff = 'unknown';
      var health = String(extra.health || u.health || '').toLowerCase();
      if (isPhone) health = '';
      var healthClass = '';
      if (health === 'wounded' || health === 'injured') healthClass = 'nato-sidc--wounded';
      if (health === 'unconscious' || health === 'cardiac_arrest' || health === 'cardiac-arrest' || health === 'dead' || health === 'kia') {
        healthClass = 'nato-sidc--critical';
      }
      var emitting = (window.ATAKRadio && window.ATAKRadio.isEmitting)
        ? window.ATAKRadio.isEmitting(extra)
        : (extra.radio_tx === true || extra.radio_tx === 1 || extra.radio_tx === 'true' ||
          extra.radio_speaking === true || extra.radio_speaking === 1 || extra.radio_speaking === 'true');
      var radioCh = extra.radio_channel != null ? String(extra.radio_channel) : '';
      var onMonNet = window.ATAKRadio && window.ATAKRadio.isMonitoredChannel
        ? window.ATAKRadio.isMonitoredChannel(radioCh)
        : false;
      if (emitting) {
        healthClass = (healthClass ? healthClass + ' ' : '') + 'nato-sidc--emitting';
      }
      if (onMonNet) {
        healthClass = (healthClass ? healthClass + ' ' : '') + 'nato-sidc--radio-listen';
      }
      var roleText = isPhone ? 'Téléphone' : String(u.role || extra.role || '').trim();
      var callsignKey = String(u.call_sign || '').toUpperCase().trim();
      var profile = (window.ATAK_CALLSIGN_TO_USER && callsignKey)
        ? window.ATAK_CALLSIGN_TO_USER[callsignKey]
        : null;
      if (isPhone && (!rev || !rev.identity)) profile = null;
      var headingRounded = u.heading != null && u.heading !== '' ? Math.round(Number(u.heading)) : '';
      if (isPhone && (!rev || !rev.heading)) headingRounded = '';
      var markerPriority = getUnitMarkerPriority();
      var preferAvatar = prefs.styleMode === 'nato' && markerPriority === 'avatar' && profile && profile.avatarUrl;
      var ftColor = String(u.fire_team_color || '').trim();
      var safeFt = ftColor.replace(/[^#A-Fa-f0-9]/g, '');
      if (!/^#[0-9A-Fa-f]{6}$/.test(safeFt)) safeFt = '';
      var plat = extra.platform || extra.vehicle_class || u.vehicle_class || '';
      var vehName = extra.vehicle || extra.vehicle_type || extra.vehicle_name || extra.model || '';
      var inVehFlag = extra.in_vehicle === true || extra.in_vehicle === 1 || extra.in_vehicle === '1' || extra.in_vehicle === 'true';
      var iconSig = [
        prefs.styleMode,
        prefs.iconSize,
        prefs.labelSize,
        prefs.showFtFrame ? '1' : '0',
        markerPriority,
        aff, roleText, health, healthClass, labelCs, headingRounded,
        preferAvatar ? profile.avatarUrl : '',
        extra.sidc || u.sidc || '',
        plat,
        vehName,
        inVehFlag ? '1' : '0',
        emitting ? '1' : '0',
        radioCh,
        onMonNet ? '1' : '0',
        u.fire_team_id || '',
        safeFt,
        isPhone ? JSON.stringify(rev || {}) : '',
        inclinedView() ? '3d' : '2d'
      ].join('|');
      var posSig = Math.round(latlng.lat * 10) / 10 + ',' + Math.round(latlng.lng * 10) / 10;
      if (existing && existing._atakIconSig === iconSig && existing._atakPosSig === posSig) {
        existing._atakUnit = u;
        return;
      }
      var icon = null;
      if (!existing || existing._atakIconSig !== iconSig) {
        if (prefs.styleMode === 'intel_dot') {
          icon = buildIntelDotIcon(labelCs, prefs.iconSize, prefs.labelSize);
        } else if (prefs.styleMode === 'dot' || prefs.styleMode === 'team_dot') {
          var dotColor = prefs.styleMode === 'team_dot' && safeFt ? safeFt : '#22c55e';
          icon = buildDotIcon(labelCs, dotColor, prefs.iconSize, prefs.labelSize);
        } else if (preferAvatar) {
          var av = Math.max(12, Math.round(prefs.iconSize));
          var avHtml = '<img src="' + String(profile.avatarUrl).replace(/"/g, '&quot;') + '" alt="" style="width:' + av + 'px;height:' + av + 'px;"/>' +
            unitBillboardLabel(labelCs) +
            (emitting ? '<span class="atak-unit-emit-badge">Émet</span>' : '') +
            (onMonNet && !emitting ? '<span class="atak-unit-listen-badge">Réseau</span>' : '');
          icon = window.ATAKMarkerSizes && window.ATAKMarkerSizes.divIcon
            ? window.ATAKMarkerSizes.divIcon(L, avHtml, av, {
                className: 'atak-unit-avatar-marker atak-compact-marker' +
                  (emitting ? ' atak-unit-avatar-marker--emit' : '') +
                  (onMonNet ? ' atak-unit-avatar-marker--listen' : '')
              })
            : L.divIcon({
                className: 'atak-unit-avatar-marker atak-compact-marker' +
                  (emitting ? ' atak-unit-avatar-marker--emit' : '') +
                  (onMonNet ? ' atak-unit-avatar-marker--listen' : ''),
                html: avHtml,
                iconSize: [av, av],
                iconAnchor: [av / 2, av / 2]
              });
        } else {
          var iconOpts = {
            affiliation: aff,
            role: roleText,
            sidc: extra.sidc || u.sidc || '',
            platform: plat,
            vehicle: vehName,
            vehicle_class: extra.vehicle_class || '',
            in_vehicle: extra.in_vehicle,
            aircraftType: extra.aircraft_type || u.aircraft_type || '',
            callSign: labelCs,
            heading: headingRounded,
            showLabel: inclinedView(),
            size: prefs.iconSize,
            health: health,
            className: healthClass,
            emitting: emitting,
            listening: onMonNet,
          };
          icon = nato && nato.leafletDivIcon
            ? nato.leafletDivIcon(L, iconOpts)
            : (window.ATAKMarkerSizes && window.ATAKMarkerSizes.divIcon
              ? window.ATAKMarkerSizes.divIcon(L, '<span style="display:block;width:8px;height:8px;border-radius:50%;background:#3b82f6;"></span>' + unitBillboardLabel(labelCs), 'small', { className: 'atak-unit-fallback atak-compact-marker ' + healthClass })
              : L.divIcon({
                  className: 'atak-unit-fallback atak-compact-marker ' + healthClass,
                  html: '<span style="display:block;width:8px;height:8px;border-radius:50%;background:#3b82f6;"></span>',
                  iconSize: [14, 14],
                  iconAnchor: [7, 7]
                }));
        }
        if (prefs.showFtFrame && safeFt && prefs.styleMode === 'nato' && icon && icon.options) {
          icon.options.className = (icon.options.className || '') + ' atak-ft-marker';
          icon.options.html = '<div class="atak-ft-marker-wrap" style="--ft-color:' + safeFt + '">' + (icon.options.html || '') + '</div>';
        }
      }
      if (!existing) {
        var marker = L.marker(latlng, { icon: icon, zIndexOffset: 400 });
        marker._atakUnit = u;
        marker._atakIconSig = iconSig;
        marker._atakPosSig = posSig;
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindUnit) {
          window.ATAKUnitPopup.bindUnit(marker, u);
        } else {
          marker.bindPopup('<strong>' + (labelCs || '—') + '</strong><br/>' + (isPhone ? 'Signal téléphone' : (u.role || '')) + '<br/>' + ((isPhone && (!rev || !rev.grid)) ? '' : (u.grid_ref || '')));
        }
        if (window.ATAKMarkerSizes) window.ATAKMarkerSizes.bindSelectVisual(marker);
        bindUnitMarkerContextMenu(marker);
        marker.addTo(unitsLayer);
        unitsById[id] = marker;
      } else {
        existing._atakUnit = u;
        if (existing._atakPosSig !== posSig) {
          existing.setLatLng(latlng);
          existing._atakPosSig = posSig;
        }
        if (icon && existing._atakIconSig !== iconSig) {
          existing.setIcon(icon);
          existing._atakIconSig = iconSig;
        }
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindUnit) {
          window.ATAKUnitPopup.bindUnit(existing, u);
        }
      }
    });
    Object.keys(unitsById).forEach(function (k) {
      if (!seen[k]) {
        unitsLayer.removeLayer(unitsById[k]);
        delete unitsById[k];
        delete unitPosQueues[k];
        delete unitPosDisplayed[k];
        delete unitPosLiveSeen[k];
      }
    });
    // Unités mises à jour → retirer les repères carte qui doublonnent le BFT.
    refreshMarkersAgainstUnits();
    try {
      window.dispatchEvent(new CustomEvent('atak:units-markers-updated', { detail: { units: lastUnitsListForMap } }));
    } catch (e) {}
  }

  function refreshMarkersAgainstUnits() {
    Object.keys(markersById).forEach(function (id) {
      var m = markersById[id];
      if (!m || !m._atakData) return;
      if (shouldHideMarkerVsUnits(m._atakData)) {
        removeExistingMarkerLayer(id);
      }
    });
  }

  function setAirAssets(assets) {
    if (!map || !Array.isArray(assets)) return;
    lastAirListForMap = assets;
    if (!airAssetsLayer) airAssetsLayer = L.layerGroup().addTo(map);
    var nato = window.NatoSidcIcons;
    var seen = {};
    assets.forEach(function (a) {
      var id = 'air_' + (a.callsign || '').replace(/\s/g, '_');
      seen[id] = true;
      var posX = a.pos_x != null ? a.pos_x : 0;
      var posY = a.pos_y != null ? a.pos_y : 0;
      var applied = applyOffset(posY, posX);
      var latlng = L.latLng(applied[0], applied[1]);
      var side = (a.side || 'WEST').toUpperCase();
      var status = (a.status || 'IN-FLIGHT').toUpperCase();
      var aff = 'friend';
      if (side === 'EAST') aff = 'hostile';
      else if (side === 'GUER' || side === 'CIV' || status === 'SUSPECT') aff = 'unknown';
      if (aff === 'hostile' && window.ATAKUnits && window.ATAKUnits.showEnemyAiEnabled && !window.ATAKUnits.showEnemyAiEnabled()) {
        return;
      }
      var icon = nato && nato.leafletDivIcon
        ? nato.leafletDivIcon(L, {
            affiliation: aff,
            aircraftType: a.aircraft_type || 'plane',
            role: a.model || a.aircraft_type || '',
            callSign: a.callsign || '',
            showLabel: inclinedView(),
            size: window.ATAKMarkerSizes ? window.ATAKMarkerSizes.px('important') : 22,
          })
        : L.divIcon({
            className: 'atak-air-asset-marker atak-compact-marker',
            html: window.ATAKMarkerSizes && window.ATAKMarkerSizes.wrapGlyph
              ? window.ATAKMarkerSizes.wrapGlyph('<span style="color:#3b82f6;font-size:12px;font-weight:bold;">▲</span>' + unitBillboardLabel(a.callsign || ''))
              : '<span style="color:#3b82f6;font-size:12px;font-weight:bold;">▲</span>',
            iconSize: [16, 16],
            iconAnchor: [8, 8],
          });
      var iconSig = [aff, a.aircraft_type || '', a.model || '', a.callsign || '', status, inclinedView() ? '3d' : '2d'].join('|');
      var posSig = Math.round(latlng.lat * 10) / 10 + ',' + Math.round(latlng.lng * 10) / 10;
      if (!airAssetsById[id]) {
        var marker = L.marker(latlng, { icon: icon, zIndexOffset: 500 });
        marker._atakAirId = id;
        marker._atakIconSig = iconSig;
        marker._atakPosSig = posSig;
        marker.addTo(airAssetsLayer);
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindAir) {
          window.ATAKUnitPopup.bindAir(marker, a);
        } else {
          marker.bindPopup('<strong>' + (a.callsign || '—') + '</strong><br/>' + (a.model || '') + '<br/>' + status);
        }
        if (window.ATAKMarkerSizes) {
          window.ATAKMarkerSizes.bindHoverTip(marker, window.ATAKMarkerSizes.hoverTipHtml(a.callsign || 'Aérien', [a.model || '', status]));
          window.ATAKMarkerSizes.bindSelectVisual(marker);
        }
        airAssetsById[id] = marker;
      } else {
        var existingAir = airAssetsById[id];
        if (existingAir._atakPosSig !== posSig) {
          existingAir.setLatLng(latlng);
          existingAir._atakPosSig = posSig;
        }
        if (existingAir._atakIconSig !== iconSig) {
          existingAir.setIcon(icon);
          existingAir._atakIconSig = iconSig;
        }
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindAir) {
          window.ATAKUnitPopup.bindAir(existingAir, a);
        }
      }
    });
    Object.keys(airAssetsById).forEach(function (k) {
      if (!seen[k]) {
        airAssetsLayer.removeLayer(airAssetsById[k]);
        delete airAssetsById[k];
      }
    });
    try {
      window.dispatchEvent(new CustomEvent('atak:air-markers-updated', { detail: { assets: assets } }));
    } catch (e) {}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindDisplayPrefsUi);
  } else {
    bindDisplayPrefsUi();
  }
  window.addEventListener('atak:terrain3dchange', refreshInclinedMarkers);

  return {
    init: init,
    destroy: destroy,
    getMap: getMap,
    invalidateSize: function (animate) {
      if (!map) return;
      try {
        map.invalidateSize(animate === true ? true : false);
      } catch (err) {}
    },
    getConfig: getConfig,
    getBaseTileLayer: function () { return baseTileLayer; },
    applyOffset: applyOffset,
    latLngFromWorld: latLngFromWorld,
    worldFromLatLng: worldFromLatLng,
    addIntelPhotoMarker: addIntelPhotoMarker,
    removeIntelPhotoMarker: removeIntelPhotoMarker,
    clearIntelMarkers: clearIntelMarkers,
    addOrUpdateMarker: addOrUpdateMarker,
    addOrUpdateDesignator: addOrUpdateDesignator,
    refreshSigintZones: refreshSigintZones,
    pollMarkers: pollMarkers,
    listMarkers: listMarkers,
    getMarkerById: getMarkerById,
    focusMarker: focusMarker,
    deleteMarkerById: deleteMarkerById,
    updateMarkerById: updateMarkerById,
    setAirAssets: setAirAssets,
    setUnitsMarkers: setUnitsMarkers,
    setReplayActive: setReplayActive,
    isReplayActive: isReplayActive,
    getUnitMarkerPriority: getUnitMarkerPriority,
    setUnitMarkerPriority: setUnitMarkerPriority,
    getDisplayPrefs: getDisplayPrefs,
    patchDisplayPrefs: patchDisplayPrefs,
    syncDisplayPrefsUi: syncDisplayPrefsUi,
    refreshUnitMarkerIcons: refreshUnitMarkerIcons,
    removeMarker: removeMarker,
    addOrUpdateLayer: addOrUpdateLayer,
    removeLayer: removeLayer,
    pointMap: pointMap,
    endPointMap: endPointMap,
    centerOn: centerOn,
    addTemporaryPingMarker: addTemporaryPingMarker,
    removeTemporaryPingMarker: removeTemporaryPingMarker,
    clearTemporaryPings: clearTemporaryPings,
    setPingsOnMap: setPingsOnMap,
    setExplosiveTimersOnMap: setExplosiveTimersOnMap,
    setGpsVehiclesOnMap: setGpsVehiclesOnMap
  };
})();
