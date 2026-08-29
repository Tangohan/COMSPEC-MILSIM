/**
 * Pont opt-in vers la refonte C2 — active avec window.ATAK_MAP_C2_V2 = true
 * avant le chargement de ce script.
 */
(function () {
  'use strict';
  if (!window.ATAK_MAP_C2_V2) return;

  document.documentElement.classList.add('atak-map-c2-v2');

  var link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = '/public/assets/css/atak-map-c2-v2.css';
  document.head.appendChild(link);

  window.addEventListener('atak:mapready', function () {
    if (window.__atakC2BridgeReady) return;
    window.__atakC2BridgeReady = true;

    import('/public/assets/js/map/MarkerManager.js').then(function (mod) {
      var map = window.ATAKMap && window.ATAKMap.getMap && window.ATAKMap.getMap();
      if (!map) return;

      var manager = new mod.MarkerManager({ map: map, clustering: true });
      window.ATAKMarkerManagerC2 = manager;

      /* Intercepte setUnitsMarkers si exposé */
      if (window.ATAKMap._setUnitsMarkersOrig == null && window.ATAKMap.setUnitsMarkers) {
        window.ATAKMap._setUnitsMarkersOrig = window.ATAKMap.setUnitsMarkers;
        window.ATAKMap.setUnitsMarkers = function (units) {
          var entities = (units || []).map(normalizeUnit);
          manager.setEntities(entities);
          try { window.ATAKMap._setUnitsMarkersOrig(units); } catch (e) { /* overlay legacy */ }
        };
      }
    }).catch(function (err) {
      console.warn('[ATAK C2 bridge]', err);
    });
  });

  function normalizeUnit(u) {
    u = u || {};
    return {
      id: u.id || u.user_id || u.steam_uid,
      callsign: u.call_sign || u.callsign,
      role: u.role || u.roleDescription,
      affiliation: u.affiliation || u.side,
      type: u.platform || u.unitType || 'INFANTRY',
      status: u.link_state || u.linkState || 'ONLINE',
      heading: u.heading || u.movement_heading,
      speed: u.speed,
      altitude: u.asl_z || u.altitude,
      x: u.pos_x,
      y: u.pos_y,
      pos: u.pos,
    };
  }
})();
