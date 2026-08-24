/* ATAK — vue relief inclinée, inspirée des commandes de globe cartographique. */
window.ATAKTerrain3D = (function () {
  'use strict';

  var KEY = 'atak_terrain_3d_view';
  var state = { enabled: false, pitch: 48, bearing: 0 };
  var stage;
  var button;
  var nav;
  var settings;
  var pitchInput;
  var dragging = false;
  var dragged = false;
  var startX = 0;
  var startBearing = 0;

  function clamp(value, min, max) { return Math.max(min, Math.min(max, Number(value) || min)); }
  function normalizeBearing(value) { return ((Number(value) || 0) % 360 + 360) % 360; }

  function restore() {
    try {
      var saved = JSON.parse(localStorage.getItem(KEY) || '{}');
      state.enabled = !!saved.enabled;
      state.pitch = clamp(saved.pitch == null ? 48 : saved.pitch, 25, 65);
      state.bearing = normalizeBearing(saved.bearing);
    } catch (e) {}
  }

  function save() {
    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
  }

  function render() {
    if (!stage) return;
    stage.classList.toggle('atak-map-stage--3d', state.enabled);
    stage.style.setProperty('--atak-map-pitch', state.pitch + 'deg');
    stage.style.setProperty('--atak-map-bearing', state.bearing + 'deg');
    stage.style.setProperty('--atak-map-bearing-number', String(state.bearing));
    if (button) {
      button.classList.toggle('is-active', state.enabled);
      button.setAttribute('aria-pressed', state.enabled ? 'true' : 'false');
      button.textContent = state.enabled ? '3D actif' : '3D';
    }
    if (nav) nav.hidden = !state.enabled;
    if (settings) settings.hidden = !state.enabled;
    if (pitchInput) pitchInput.value = String(state.pitch);
    var pitchValue = document.getElementById('atak-terrain-pitch-val');
    if (pitchValue) pitchValue.textContent = state.pitch + '°';
  }

  function setEnabled(enabled) {
    state.enabled = !!enabled;
    if (state.enabled && window.ATAKMap && window.ATAKMap.patchDisplayPrefs) {
      window.ATAKMap.patchDisplayPrefs({ terrainHillshade: true });
    }
    render();
    save();
    window.setTimeout(function () {
      var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
      if (map && map.invalidateSize) map.invalidateSize(false);
    }, 450);
  }

  function bindCompass(compass) {
    if (!compass) return;
    compass.addEventListener('pointerdown', function (event) {
      dragging = true;
      dragged = false;
      startX = event.clientX;
      startBearing = state.bearing;
      compass.setPointerCapture(event.pointerId);
    });
    compass.addEventListener('pointermove', function (event) {
      if (!dragging) return;
      var delta = event.clientX - startX;
      if (Math.abs(delta) > 2) dragged = true;
      state.bearing = Math.round(normalizeBearing(startBearing + delta * 1.5));
      render();
    });
    compass.addEventListener('pointerup', function () { dragging = false; save(); });
    compass.addEventListener('click', function () {
      if (dragged) return;
      state.bearing = 0;
      render();
      save();
    });
  }

  function init() {
    stage = document.querySelector('.atak-map-stage');
    button = document.getElementById('atak-view-3d');
    nav = document.getElementById('atak-map-3d-nav');
    settings = document.getElementById('atak-terrain-3d-settings');
    pitchInput = document.getElementById('atak-terrain-pitch');
    if (!stage || !button) return;
    restore();
    button.addEventListener('click', function () { setEnabled(!state.enabled); });
    var flat = document.getElementById('atak-map-3d-flat');
    if (flat) flat.addEventListener('click', function () { setEnabled(false); });
    if (pitchInput) pitchInput.addEventListener('input', function () {
      state.pitch = clamp(pitchInput.value, 25, 65);
      render();
      save();
    });
    bindCompass(document.getElementById('atak-map-3d-compass'));
    render();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  return { setEnabled: setEnabled, getState: function () { return Object.assign({}, state); } };
})();
