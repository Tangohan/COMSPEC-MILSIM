/**
 * Sons ATAK (web) — alignés sur le réglage jeu (CBA).
 * Préférence : Mode silence (vibration) / Stalker / Alerte santé / Mode silence sans vibration.
 * Volume 0–100 % + modes silence (barre latérale / panneau compte).
 * Événements dédiés : démarrage, déconnexion, inconscient, mort.
 */
window.ATAKSounds = (function () {
  var STORAGE_KEY = 'atak_notif_sound';
  var STORAGE_KEY_VOLUME = 'atak_alert_volume';
  var STORAGE_KEY_AUDIBLE = 'atak_notif_sound_audible';
  var PREFS = {
    silent_vib: { label: 'Mode silence (avec vibration)', file: null, vibrate: true },
    stalker: { label: 'Stalker', file: 'sound_1_stalker.ogg', vibrate: false },
    health: { label: 'Alerte santé', file: 'atak_no_activyt_health.ogg', vibrate: false },
    mute: { label: 'Mode silence sans vibration', file: null, vibrate: false }
  };
  /** Sons liés à un événement précis (indépendants du choix Stalker/Santé, sauf modes silence). */
  var EVENTS = {
    start: { file: 'atak_start.ogg', cooldown: 2500 },
    disconnect: { file: 'atak_disconnect.ogg', cooldown: 2500 },
    unconscious: { file: 'atak_alert_2.ogg', cooldown: 4000 },
    death: { file: 'atak_death.ogg', cooldown: 4000 }
  };
  var DEFAULT_PREF = 'stalker';
  var DEFAULT_AUDIBLE = 'stalker';
  var DEFAULT_VOLUME = 70;
  var COOLDOWN_MS = 450;
  var VIBRATE_PATTERN = [35, 45, 35];
  var pref = DEFAULT_PREF;
  var volume = DEFAULT_VOLUME;
  var lastAudiblePref = DEFAULT_AUDIBLE;
  var unlocked = false;
  var lastPlayAt = 0;
  var lastEventAt = {};
  var audioCache = {};
  var baseUrl = '';
  var syncingUi = false;

  function resolveBase() {
    var raw = (window.ATAK_API_BASE || window.ATAK_SOUNDS_BASE || '').replace(/\/$/, '');
    return raw;
  }
  function normalizePref(value) {
    var v = String(value || '').toLowerCase();
    return PREFS[v] ? v : DEFAULT_PREF;
  }
  function normalizeAudible(value) {
    var v = normalizePref(value);
    if (v === 'mute' || v === 'silent_vib') return DEFAULT_AUDIBLE;
    return v;
  }
  function normalizeVolume(value) {
    var n = parseInt(value, 10);
    if (isNaN(n)) return DEFAULT_VOLUME;
    if (n < 0) return 0;
    if (n > 100) return 100;
    return n;
  }
  function normalizeEvent(name) {
    var n = String(name || '').toLowerCase();
    if (n === 'crash') return 'disconnect';
    if (n === 'boot' || n === 'connect' || n === 'client_init') return 'start';
    if (n === 'cardiac_arrest' || n === 'kia' || n === 'dead' || n === 'killed') return 'death';
    if (EVENTS[n]) return n;
    return '';
  }
  function loadPref() {
    try {
      pref = normalizePref(localStorage.getItem(STORAGE_KEY));
    } catch (e) {
      pref = DEFAULT_PREF;
    }
    try {
      lastAudiblePref = normalizeAudible(localStorage.getItem(STORAGE_KEY_AUDIBLE) || lastAudiblePref);
    } catch (e2) {
      lastAudiblePref = DEFAULT_AUDIBLE;
    }
    if (pref !== 'mute' && pref !== 'silent_vib') {
      lastAudiblePref = pref;
    }
    return pref;
  }
  function loadVolume() {
    try {
      volume = normalizeVolume(localStorage.getItem(STORAGE_KEY_VOLUME));
    } catch (e) {
      volume = DEFAULT_VOLUME;
    }
    return volume;
  }
  function saveAudibleMemory(value) {
    var a = normalizeAudible(value);
    lastAudiblePref = a;
    try {
      localStorage.setItem(STORAGE_KEY_AUDIBLE, a);
    } catch (e) { /* stockage indisponible */ }
  }
  function savePref(value) {
    pref = normalizePref(value);
    if (pref !== 'mute' && pref !== 'silent_vib') {
      saveAudibleMemory(pref);
    }
    try {
      localStorage.setItem(STORAGE_KEY, pref);
    } catch (e) { /* stockage indisponible */ }
    syncUi();
    return pref;
  }
  function saveVolume(value) {
    volume = normalizeVolume(value);
    try {
      localStorage.setItem(STORAGE_KEY_VOLUME, String(volume));
    } catch (e) { /* stockage indisponible */ }
    applyVolumeToCache();
    syncUi();
    return volume;
  }
  function soundUrl(file) {
    if (!baseUrl) baseUrl = resolveBase();
    return baseUrl + '/assets/sounds/' + file;
  }
  function cacheKey(kind, id) {
    return kind + ':' + id;
  }
  function gain() {
    return Math.max(0, Math.min(1, volume / 100));
  }
  function applyVolumeToCache() {
    var g = gain();
    Object.keys(audioCache).forEach(function (k) {
      var a = audioCache[k];
      if (a) a.volume = g;
    });
  }
  function getAudioByFile(file, id) {
    if (!file) return null;
    if (audioCache[id]) {
      audioCache[id].volume = gain();
      return audioCache[id];
    }
    var a = new Audio(soundUrl(file));
    a.preload = 'auto';
    a.volume = gain();
    audioCache[id] = a;
    return a;
  }
  function getAudio(prefKey) {
    var meta = PREFS[prefKey];
    if (!meta || !meta.file) return null;
    return getAudioByFile(meta.file, cacheKey('pref', prefKey));
  }
  function tryVibrate() {
    if (pref === 'mute') return false;
    if (pref !== 'silent_vib' && !(PREFS[pref] && PREFS[pref].vibrate)) return false;
    if (typeof navigator === 'undefined' || typeof navigator.vibrate !== 'function') return false;
    try {
      return !!navigator.vibrate(VIBRATE_PATTERN);
    } catch (e) {
      return false;
    }
  }
  function isSilentMode() {
    return pref === 'mute' || pref === 'silent_vib';
  }
  function muteReason() {
    var silent = isSilentMode();
    if (silent && volume <= 0) {
      return 'Sons coupés : mode silence et volume à 0 %. Les bandeaux d’alerte restent visibles.';
    }
    if (silent) {
      return pref === 'mute'
        ? 'Sons et vibrations coupés (mode silence sans vibration). Les bandeaux d’alerte restent visibles.'
        : 'Sons coupés (mode silence). Les bandeaux d’alerte restent visibles.';
    }
    if (volume <= 0) {
      return 'Volume des alertes à 0 %. Montez le volume pour entendre les sons. Les bandeaux restent visibles.';
    }
    return '';
  }
  function refreshMuteHint() {
    var hint = document.getElementById('atak-alert-mute-hint');
    if (!hint) return;
    var reason = muteReason();
    if (!reason) {
      hint.hidden = true;
      hint.textContent = '';
      return;
    }
    hint.hidden = false;
    hint.textContent = reason;
  }
  function unlock() {
    if (unlocked) return;
    unlocked = true;
    Object.keys(PREFS).forEach(function (key) {
      var a = getAudio(key);
      if (!a) return;
      try {
        a.muted = true;
        var p = a.play();
        if (p && typeof p.then === 'function') {
          p.then(function () {
            a.pause();
            a.currentTime = 0;
            a.muted = false;
          }).catch(function () {
            a.muted = false;
          });
        } else {
          a.pause();
          a.currentTime = 0;
          a.muted = false;
        }
      } catch (e) {
        a.muted = false;
      }
    });
    Object.keys(EVENTS).forEach(function (key) {
      var meta = EVENTS[key];
      if (!meta || !meta.file) return;
      var a = getAudioByFile(meta.file, cacheKey('event', key));
      if (!a) return;
      try {
        a.muted = true;
        var p = a.play();
        if (p && typeof p.then === 'function') {
          p.then(function () {
            a.pause();
            a.currentTime = 0;
            a.muted = false;
          }).catch(function () {
            a.muted = false;
          });
        } else {
          a.pause();
          a.currentTime = 0;
          a.muted = false;
        }
      } catch (e) {
        a.muted = false;
      }
    });
  }
  function bindUnlock() {
    var once = function () {
      unlock();
      document.removeEventListener('pointerdown', once, true);
      document.removeEventListener('keydown', once, true);
      document.removeEventListener('touchstart', once, true);
    };
    document.addEventListener('pointerdown', once, true);
    document.addEventListener('keydown', once, true);
    document.addEventListener('touchstart', once, { capture: true, passive: true });
  }
  function playAudio(a) {
    if (!a) return false;
    if (volume <= 0) return false;
    try {
      a.pause();
      a.currentTime = 0;
      a.muted = false;
      a.volume = gain();
      var p = a.play();
      if (p && typeof p.catch === 'function') {
        p.catch(function () {
          /* autoplay bloqué tant qu’aucun geste — silencieux */
        });
      }
      return true;
    } catch (e) {
      return false;
    }
  }
  /**
   * Alerte générique (bip radio / activité).
   * Modes silence : pas de son ; vibration uniquement si « silence avec vibration ».
   */
  function play(opts) {
    opts = opts || {};
    if (pref === 'mute') return false;
    if (pref === 'silent_vib') {
      var nowSilent = Date.now();
      if (!opts.force && nowSilent - lastPlayAt < COOLDOWN_MS) return false;
      lastPlayAt = nowSilent;
      return tryVibrate();
    }
    var meta = PREFS[pref];
    if (!meta || !meta.file) return false;
    var now = Date.now();
    if (!opts.force && now - lastPlayAt < COOLDOWN_MS) return false;
    lastPlayAt = now;
    return playAudio(getAudio(pref));
  }
  /**
   * Son d’événement (démarrage, déconnexion, inconscient, mort).
   * Modes silence : pas de son (vibration éventuelle en silent_vib).
   */
  function playEvent(name, opts) {
    opts = opts || {};
    if (pref === 'mute') return false;
    var eventKey = normalizeEvent(name);
    var meta = eventKey ? EVENTS[eventKey] : null;
    if (!meta || !meta.file) return false;
    var now = Date.now();
    var cool = typeof meta.cooldown === 'number' ? meta.cooldown : 2000;
    if (!opts.force && now - (lastEventAt[eventKey] || 0) < cool) return false;
    lastEventAt[eventKey] = now;
    lastPlayAt = now;
    if (pref === 'silent_vib') {
      return tryVibrate();
    }
    return playAudio(getAudioByFile(meta.file, cacheKey('event', eventKey)));
  }
  /** Types d’activité de liaison qui méritent un son (pas le bruit de fond position). */
  function shouldPlayForActivity(type) {
    switch (String(type || '')) {
      case 'client_init':
      case 'disconnect':
      case 'callsign_change':
      case 'auth':
      case 'phone':
      case 'chat':
      case 'ping':
      case 'marker':
      case 'intel':
      case 'nine_line':
      case 'designator':
      case 'laser':
      case 'flight':
      case 'sigint':
      case 'order':
        return true;
      default:
        return false;
    }
  }
  /** Joue le son adapté à un type d’activité (événement dédié si connu). */
  function playForActivity(type, opts) {
    var t = String(type || '');
    if (t === 'client_init') return playEvent('start', opts);
    if (t === 'disconnect') return playEvent('disconnect', opts);
    return play(opts);
  }
  function setVolumeInputs(val) {
    var rail = document.getElementById('atak-alert-volume');
    var account = document.getElementById('atak-alert-volume-account');
    var label = document.getElementById('atak-alert-volume-value');
    if (rail && String(rail.value) !== String(val)) rail.value = String(val);
    if (rail) rail.setAttribute('aria-valuenow', String(val));
    if (account && String(account.value) !== String(val)) account.value = String(val);
    if (account) account.setAttribute('aria-valuenow', String(val));
    if (label) label.textContent = String(val);
  }
  function setSilenceChecks() {
    var silence = document.getElementById('atak-alert-silence');
    var novib = document.getElementById('atak-alert-silence-novib');
    if (!silence && !novib) return;
    var isMute = pref === 'mute';
    var isSilentVib = pref === 'silent_vib';
    if (silence) silence.checked = isMute || isSilentVib;
    if (novib) novib.checked = isMute;
  }
  function syncUi() {
    if (syncingUi) return;
    syncingUi = true;
    try {
      var select = document.getElementById('atak-notif-sound');
      if (select && select.value !== pref) select.value = pref;
      var label = document.getElementById('atak-notif-sound-label');
      if (label && PREFS[pref]) label.textContent = PREFS[pref].label;
      setVolumeInputs(volume);
      setSilenceChecks();
      refreshMuteHint();
    } finally {
      syncingUi = false;
    }
  }
  function onSilenceChange() {
    if (syncingUi) return;
    unlock();
    var silence = document.getElementById('atak-alert-silence');
    var novib = document.getElementById('atak-alert-silence-novib');
    var silenceOn = !!(silence && silence.checked);
    var novibOn = !!(novib && novib.checked);
    if (novibOn && !silenceOn && silence) {
      silence.checked = true;
      silenceOn = true;
    }
    if (!silenceOn && novib) {
      novib.checked = false;
      novibOn = false;
    }
    if (novibOn) {
      savePref('mute');
      return;
    }
    if (silenceOn) {
      savePref('silent_vib');
      return;
    }
    savePref(lastAudiblePref || DEFAULT_AUDIBLE);
  }
  function bindVolumeInput(el) {
    if (!el) return;
    var onInput = function () {
      if (syncingUi) return;
      unlock();
      saveVolume(el.value);
    };
    el.addEventListener('input', onInput);
    el.addEventListener('change', onInput);
  }
  function bindUi() {
    var select = document.getElementById('atak-notif-sound');
    if (select) {
      select.value = pref;
      select.addEventListener('change', function () {
        if (syncingUi) return;
        unlock();
        savePref(select.value);
        if (!isSilentMode()) play({ force: true });
        else if (pref === 'silent_vib') tryVibrate();
      });
    }
    var previewBtn = document.getElementById('atak-notif-sound-preview');
    if (previewBtn) {
      previewBtn.addEventListener('click', function () {
        unlock();
        if (pref === 'mute') return;
        if (pref === 'silent_vib') {
          tryVibrate();
          return;
        }
        play({ force: true });
      });
    }
    bindVolumeInput(document.getElementById('atak-alert-volume'));
    bindVolumeInput(document.getElementById('atak-alert-volume-account'));
    var silence = document.getElementById('atak-alert-silence');
    var novib = document.getElementById('atak-alert-silence-novib');
    if (silence) silence.addEventListener('change', onSilenceChange);
    if (novib) novib.addEventListener('change', onSilenceChange);
  }
  function init() {
    baseUrl = resolveBase();
    loadPref();
    loadVolume();
    bindUnlock();
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        bindUi();
        syncUi();
      });
    } else {
      bindUi();
      syncUi();
    }
  }
  init();
  return {
    PREFS: PREFS,
    EVENTS: EVENTS,
    getPref: function () { return pref; },
    setPref: savePref,
    getVolume: function () { return volume; },
    setVolume: saveVolume,
    isSilentMode: isSilentMode,
    muteReason: muteReason,
    refreshMuteHint: refreshMuteHint,
    play: play,
    playEvent: playEvent,
    playForActivity: playForActivity,
    unlock: unlock,
    shouldPlayForActivity: shouldPlayForActivity
  };
})();

