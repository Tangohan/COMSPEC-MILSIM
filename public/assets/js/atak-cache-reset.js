/* COMSPEC ATAK — Réinitialisation carte / vidages de cache locaux */
window.ATAKCacheReset = (function () {
  'use strict';

  function notify(msg) {
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification(msg);
      return;
    }
    try { console.info('[ATAK]', msg); } catch (e) { /* ignore */ }
  }

  function resetMapView() {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (map && typeof map.invalidateSize === 'function') {
      try { map.invalidateSize({ animate: false }); } catch (e) { /* ignore */ }
    }
    if (window.ATAKMapTools && typeof window.ATAKMapTools.centerOnSelf === 'function') {
      try { window.ATAKMapTools.centerOnSelf(); } catch (e2) { /* ignore */ }
    }
    notify('Vue carte réinitialisée.');
  }

  function clearDrawings() {
    if (window.ATAKMapTools && typeof window.ATAKMapTools.clearDrawings === 'function') {
      window.ATAKMapTools.clearDrawings();
      notify('Tracés et zones effacés.');
      return;
    }
    notify('Aucun tracé à effacer pour le moment.');
  }

  function clearMedicalDismissals() {
    if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.clearDismissed === 'function') {
      window.ATAKMedicalAlerts.clearDismissed();
    }
    if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.fetchAlerts === 'function') {
      window.ATAKMedicalAlerts.fetchAlerts();
    }
    notify('Alertes santé remises à l’affichage.');
  }

  function clearHiddenPhotos() {
    try {
      var keys = [];
      for (var i = 0; i < sessionStorage.length; i++) {
        var k = sessionStorage.key(i);
        if (k && k.indexOf('atak_hidden_recon_photos_') === 0) keys.push(k);
      }
      keys.forEach(function (k) { sessionStorage.removeItem(k); });
    } catch (e) { /* ignore */ }
    if (window.ATAKCams && typeof window.ATAKCams.refresh === 'function') {
      try { window.ATAKCams.refresh(); } catch (e2) { /* ignore */ }
    }
    notify('Photos masquées localement réaffichées.');
  }

  function clearMapLayerCache() {
    if (window.ATAKMap && typeof window.ATAKMap.clearIntelMarkers === 'function') {
      try { window.ATAKMap.clearIntelMarkers(); } catch (e) { /* ignore */ }
    }
    if (window.ATAKUnits && typeof window.ATAKUnits.fetchUnits === 'function') {
      try { window.ATAKUnits.fetchUnits(); } catch (e2) { /* ignore */ }
    } else if (window.ATAKSocket && typeof window.ATAKSocket.requestUnits === 'function') {
      try { window.ATAKSocket.requestUnits(); } catch (e3) { /* ignore */ }
    }
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (map && typeof map.invalidateSize === 'function') {
      try { map.invalidateSize({ animate: false }); } catch (e4) { /* ignore */ }
    }
    notify('Couches carte rechargées.');
  }

  function clearLocalAtakStorage(reloadPage) {
    var prefixes = [
      'atak_medical_dismissed_',
      'atak_hidden_recon_photos_',
      'atak_map_nvg',
      'atak_map_tools_',
      'atak_map_display_prefs',
      'atak_unit_marker_priority',
      'atak_map_slug',
      'atak_sidebar_collapse_',
      'atak_panel_',
      'atak_chat_clear_',
      'atak_mention_seen_'
    ];
    function wipeStore(store) {
      if (!store) return;
      var keys = [];
      try {
        for (var i = 0; i < store.length; i++) {
          var k = store.key(i);
          if (!k) continue;
          for (var p = 0; p < prefixes.length; p++) {
            if (k.indexOf(prefixes[p]) === 0 || k === prefixes[p]) {
              keys.push(k);
              break;
            }
          }
        }
        keys.forEach(function (k) { store.removeItem(k); });
      } catch (e) { /* ignore */ }
    }
    wipeStore(window.localStorage);
    wipeStore(window.sessionStorage);
    if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.clearDismissed === 'function') {
      window.ATAKMedicalAlerts.clearDismissed();
    }
    if (reloadPage) {
      notify('Cache local vidé — rechargement…');
      setTimeout(function () { window.location.reload(); }, 400);
      return;
    }
    clearDrawings();
    clearMapLayerCache();
    notify('Cache local ATAK vidé.');
  }

  function run(action) {
    switch (String(action || '')) {
      case 'view':
        resetMapView();
        break;
      case 'drawings':
        clearDrawings();
        break;
      case 'medical':
        clearMedicalDismissals();
        break;
      case 'photos':
        clearHiddenPhotos();
        break;
      case 'layers':
        clearMapLayerCache();
        break;
      case 'all':
        if (!window.confirm('Vider le cache local ATAK sur cet appareil (tracés, alertes masquées, préférences d’affichage carte) puis recharger la page ?')) {
          return;
        }
        clearLocalAtakStorage(true);
        break;
      default:
        notify('Action de nettoyage inconnue.');
    }
  }

  function bindUi() {
    document.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest
        ? ev.target.closest('[data-atak-cache-reset]')
        : null;
      if (!btn) return;
      ev.preventDefault();
      run(btn.getAttribute('data-atak-cache-reset'));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindUi);
  } else {
    bindUi();
  }

  return {
    run: run,
    resetMapView: resetMapView,
    clearDrawings: clearDrawings,
    clearMedicalDismissals: clearMedicalDismissals,
    clearHiddenPhotos: clearHiddenPhotos,
    clearMapLayerCache: clearMapLayerCache,
    clearLocalAtakStorage: clearLocalAtakStorage
  };
})();
