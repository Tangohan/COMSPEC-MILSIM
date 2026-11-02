/*
 * « Sur mon téléphone » (/m/…) : comptes rendus, CR fin d'op, carte et briefs air de l'ATAK sur le vrai téléphone.
 * Écritures en POST (formulaire) avec l'en-tête X-CSRF-Token ; lectures GET. Ce qui est envoyé part dans l'ATAK
 * du joueur en jeu (avis Athena) ou dans les marqueurs du site, qui redescendent en jeu.
 */
(function () {
  'use strict';
  var body = document.body;
  var BASE = (body.getAttribute('data-base') || '').replace(/\/$/, '');
  var VIEW = body.getAttribute('data-view') || '';
  var meta = document.querySelector('meta[name="csrf-token"]');
  var CSRF = meta ? meta.getAttribute('content') : '';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function toast(msg) {
    var t = document.getElementById('mm-toast');
    if (!t) return;
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { t.hidden = true; }, 3200);
  }
  function getJSON(path) {
    return fetch(BASE + path, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }
  function post(path, params) {
    var data = params instanceof URLSearchParams ? params : new URLSearchParams(params || {});
    data.set('_csrf_token', CSRF);
    return fetch(BASE + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' },
      body: data
    }).then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }
  function failText(j) {
    if (!j) return 'Envoi impossible : vérifiez la connexion du téléphone.';
    if (j.error === 'session' || j._status === 401) return 'Session terminée : régénérez le QR code dans le jeu.';
    if (j.error === 'csrf') return 'Page expirée : rechargez-la.';
    if (j.error === 'rate_limited') return 'Trop d’envois d’un coup : patientez une minute.';
    if (j.error === 'invalid') return 'Complétez les lignes en rouge.';
    return 'Envoi impossible, réessayez.';
  }
  function pad4(n) { n = Math.max(0, Math.floor(n)) % 10000; return ('0000' + n).slice(-4); }
  function grid(x, y) { return pad4(x / 10) + ' ' + pad4(y / 10); }

  // Choix en boutons (gros boutons, segments) : un seul allumé, valeur dans le champ caché du groupe.
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.mm-choice');
    if (!b) return;
    var group = b.closest('.mm-choices');
    if (!group) return;
    var on = !b.classList.contains('is-on');
    $all('.mm-choice', group).forEach(function (x) { x.classList.remove('is-on'); });
    var input = $('input[type="hidden"]', group);
    if (on || !input) b.classList.add('is-on');
    if (input) input.value = on ? b.getAttribute('data-choice') : '';
    var f = b.closest('.mm-field');
    if (f) f.classList.remove('is-bad');
  });

  var logout = $('[data-mm-logout]');
  if (logout) {
    logout.addEventListener('click', function () {
      if (!window.confirm('Déconnecter ce téléphone ? Il faudra scanner un nouveau QR code dans le jeu.')) return;
      post('/m/quitter').then(function () { window.location.reload(); });
    });
  }

  // Suivi d'un envoi confié à l'ATAK du joueur.
  function follow(id, el) {
    var tries = 0;
    var labels = {
      pending: 'En attente de votre ATAK…',
      delivered: 'Reçu par votre ATAK…',
      acked: 'Envoyé par votre ATAK ✓',
      unknown: 'Envoi introuvable.'
    };
    (function tick() {
      getJSON('/m/api/suivi/' + id).then(function (j) {
        var st = (j && j.state) || 'pending';
        el.textContent = labels[st] || labels.pending;
        el.setAttribute('data-st', st);
        if (st === 'acked' || st === 'unknown') return;
        if (++tries > 90) { el.textContent = 'Votre ATAK ne l’a pas encore reçu : allumez le téléphone en jeu et vérifiez la liaison Athena.'; return; }
        setTimeout(tick, 3000);
      }).catch(function () { setTimeout(tick, 5000); });
    })();
  }

  // Carte : même repère que la carte ATAK du site (latitude = y, longitude = x en mètres du monde).
  function makeMap(el) {
    var cfg = window.MM_MAP;
    if (!el || typeof L === 'undefined' || !cfg) {
      if (el) el.innerHTML = '<p class="mm-muted mm-pad">Carte indisponible pour cette partie.</p>';
      return null;
    }
    var crs = cfg.crs || {};
    var CRS = typeof window.MGRS_CRS === 'function'
      ? window.MGRS_CRS(Number(crs.factorx) || 0.006839, Number(crs.factory) || 0.006836, Number(crs.tileWidth || cfg.tileSize) || 212)
      : L.CRS.Simple;
    var c = cfg.center || [15000, 15000];
    var maxNative = Number(cfg.maxZoom) || 6;
    var map = L.map(el, {
      crs: CRS,
      center: [Number(c[1]) || 15000, Number(c[0]) || 15000],
      zoom: Number(cfg.defaultZoom) || 3,
      minZoom: Number(cfg.minZoom) || 0,
      maxZoom: maxNative + 2,
      zoomControl: false,
      attributionControl: false
    });
    if (cfg.tilePattern) {
      var ws = Number(cfg.worldSize) || 30720;
      L.tileLayer(cfg.tilePattern, {
        tileSize: Number(cfg.tileSize) || 212,
        maxZoom: maxNative + 2,
        maxNativeZoom: maxNative,
        noWrap: true,
        crossOrigin: true,
        bounds: L.latLngBounds([0, 0], [ws, ws])
      }).addTo(map);
    }
    setTimeout(function () { map.invalidateSize(); }, 120);
    return map;
  }
  var COLORS = { ColorRed: '#e5483a', ColorOrange: '#f08a3c', ColorYellow: '#e8c547', ColorBlue: '#4aa3df', ColorGreen: '#5cc76b', ColorBlack: '#e3ebe7', ColorWhite: '#ffffff', ColorOPFOR: '#e5483a', ColorBLUFOR: '#4aa3df' };

  // ── Comptes rendus ───────────────────────────────────────────────────────
  if (VIEW === 'cr') {
    var pick = $('[data-cr-pick]');
    var sent = $('[data-cr-sent]');
    var current = null;
    var gridTarget = null;
    var pickMap = null;
    var show = function (code) {
      pick.hidden = !!code;
      sent.hidden = true;
      $all('[data-cr-form]').forEach(function (f) { f.hidden = f.getAttribute('data-cr-form') !== code; });
      current = code;
      window.scrollTo(0, 0);
    };
    $all('[data-cr-type]').forEach(function (b) {
      b.addEventListener('click', function () { show(b.getAttribute('data-cr-type')); });
    });
    $all('[data-cr-back]').forEach(function (b) { b.addEventListener('click', function () { show(null); }); });
    $('[data-cr-again]').addEventListener('click', function () { show(null); });
    $all('[data-grid-here]').forEach(function (b) {
      b.addEventListener('click', function () {
        var input = $('input', b.closest('.mm-field'));
        getJSON('/m/api/carte').then(function (j) {
          if (j && j.me && j.me.grid) { input.value = j.me.grid; toast('Position du jeu : ' + j.me.grid); } else { toast('Position inconnue : votre ATAK n’a pas encore envoyé de position.'); }
        });
      });
    });
    var sheet = $('[data-map-sheet]');
    $all('[data-grid-map]').forEach(function (b) {
      b.addEventListener('click', function () {
        gridTarget = $('input', b.closest('.mm-field'));
        sheet.hidden = false;
        if (!pickMap) {
          pickMap = makeMap(document.getElementById('mm-pick-map'));
          if (pickMap) {
            pickMap.on('click', function (e) {
              if (gridTarget) gridTarget.value = grid(e.latlng.lng, e.latlng.lat);
              sheet.hidden = true;
              toast('Grille ' + gridTarget.value);
            });
            getJSON('/m/api/carte').then(function (j) {
              if (j && j.me) {
                pickMap.setView([j.me.y, j.me.x], Math.max(pickMap.getZoom(), 4));
                L.circleMarker([j.me.y, j.me.x], { radius: 8, color: '#f08a3c', weight: 3 }).addTo(pickMap);
              }
            });
          }
        } else {
          setTimeout(function () { pickMap.invalidateSize(); }, 80);
        }
      });
    });
    $('[data-map-close]').addEventListener('click', function () { sheet.hidden = true; });
    $all('[data-cr-form]').forEach(function (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var data = new URLSearchParams(new FormData(form));
        data.set('type', current || '');
        var btn = $('.mm-btn--send', form);
        btn.disabled = true;
        $all('.mm-field', form).forEach(function (f) { f.classList.remove('is-bad'); });
        post('/m/cr', data).then(function (j) {
          btn.disabled = false;
          if (!j.ok) {
            (j.fields || []).forEach(function (k) { var f = $('[data-field="' + k + '"]', form); if (f) f.classList.add('is-bad'); });
            toast(failText(j));
            return;
          }
          form.reset();
          $all('.mm-choice', form).forEach(function (x) { x.classList.remove('is-on'); });
          form.hidden = true;
          sent.hidden = false;
          follow(j.notice, $('[data-state]', sent));
        }).catch(function () { btn.disabled = false; toast(failText(null)); });
      });
    });
  }

  // ── CR fin d'op ──────────────────────────────────────────────────────────
  if (VIEW === 'compte_rendu') {
    var dform = $('[data-dbf-form]');
    if (dform) {
      dform.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var btn = $('.mm-btn--send', dform);
        btn.disabled = true;
        $all('.mm-field', dform).forEach(function (f) { f.classList.remove('is-bad'); });
        post('/m/compte-rendu', new URLSearchParams(new FormData(dform))).then(function (j) {
          btn.disabled = false;
          if (!j.ok) {
            (j.fields || []).forEach(function (k) { var f = $('[data-field="' + k + '"]', dform); if (f) f.classList.add('is-bad'); });
            toast(j.error === 'invalid' ? 'Répondez aux questions marquées d’une étoile.' : failText(j));
            return;
          }
          dform.hidden = true;
          var s = $('[data-dbf-sent]');
          s.hidden = false;
          follow(j.notice, $('[data-state]', s));
        }).catch(function () { btn.disabled = false; toast(failText(null)); });
      });
    }
  }

  // ── Carte ────────────────────────────────────────────────────────────────
  if (VIEW === 'carte') {
    var map = makeMap(document.getElementById('mm-map'));
    var layer = map ? L.layerGroup().addTo(map) : null;
    var me = null;
    var centered = false;
    var mode = '';
    var editing = null;
    var pending = null;
    var msheet = $('[data-marker-sheet]');
    var hint = $('[data-map-hint]');
    var chip = $('[data-my-grid]');
    var setKind = function (k) {
      $all('[data-marker-kinds] .mm-choice').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-choice') === k); });
    };
    var kindOf = function () { var on = $('[data-marker-kinds] .mm-choice.is-on'); return on ? on.getAttribute('data-choice') : 'point'; };
    var openSheet = function (m, x, y) {
      editing = m;
      pending = { x: x, y: y };
      $('[data-marker-title]').textContent = m ? 'Mon marqueur' : 'Nouveau marqueur';
      $('[data-marker-text]').value = m ? (m.text || '') : '';
      setKind(m ? (m.kind || 'point') : 'point');
      $('[data-marker-grid]').textContent = 'Grille ' + grid(x, y);
      $('[data-marker-save]').textContent = m ? 'ENREGISTRER' : 'POSER';
      $('[data-marker-move]').hidden = !m;
      $('[data-marker-delete]').hidden = !m;
      msheet.hidden = false;
    };
    var closeSheet = function () { msheet.hidden = true; editing = null; };
    var refresh = function () {
      if (!map) return;
      getJSON('/m/api/carte').then(function (j) {
        if (!j || !j.ok) { if (j && j._status === 401) toast(failText(j)); return; }
        layer.clearLayers();
        me = j.me;
        chip.textContent = me ? ('Position : ' + me.grid) : 'Position : en attente du jeu';
        (j.units || []).forEach(function (u) {
          if (u.me) return;
          L.circleMarker([u.y, u.x], { radius: 7, color: '#35d6a1', weight: 2, fillOpacity: 0.5 })
            .bindTooltip(esc(u.callsign), { permanent: false, direction: 'top' }).addTo(layer);
        });
        (j.markers || []).forEach(function (m) {
          var col = COLORS[m.color] || '#e8c547';
          var mk = L.circleMarker([m.y, m.x], { radius: m.mine ? 9 : 7, color: col, weight: m.mine ? 4 : 2, fillOpacity: 0.35 })
            .bindTooltip(esc(m.text || ''), { direction: 'top' }).addTo(layer);
          if (m.mine) {
            mk.on('click', function (e) {
              L.DomEvent.stopPropagation(e);
              openSheet({ id: m.id, text: m.text, kind: guessKind(m) }, m.x, m.y);
            });
          }
        });
        if (me) {
          L.circleMarker([me.y, me.x], { radius: 10, color: '#f08a3c', weight: 4, fillOpacity: 0.6 }).bindTooltip('Moi').addTo(layer);
          if (!centered) { map.setView([me.y, me.x], Math.max(map.getZoom(), 4)); centered = true; }
        }
      }).catch(function () {});
    };
    var TYPE_KIND = { hd_dot: 'point', hd_destroy: 'ennemi', hd_warning: 'danger', hd_objective: 'objectif', hd_flag: 'ami', hd_pickup: 'lz' };
    var guessKind = function (m) { return TYPE_KIND[m.type] || 'point'; };
    if (map) {
      map.on('click', function (e) {
        var x = e.latlng.lng;
        var y = e.latlng.lat;
        if (mode === 'add') { mode = ''; hint.hidden = true; openSheet(null, x, y); return; }
        if (!mode && !msheet.hidden) { closeSheet(); return; }
        if (mode === 'move' && editing) {
          mode = '';
          hint.hidden = true;
          post('/m/api/marqueurs/' + editing.id, { kind: editing.kind || 'point', text: editing.text || '', x: x, y: y }).then(function (j) {
            toast(j.ok ? 'Marqueur déplacé.' : failText(j));
            closeSheet();
            refresh();
          });
        }
      });
      refresh();
      setInterval(function () { if (!document.hidden) refresh(); }, 5000);
    }
    $('[data-map-me]').addEventListener('click', function () {
      if (me && map) map.setView([me.y, me.x], Math.max(map.getZoom(), 5)); else toast('Position inconnue : votre ATAK n’a pas encore envoyé de position.');
    });
    $('[data-map-add]').addEventListener('click', function () { mode = 'add'; hint.hidden = false; closeSheet(); });
    $('[data-map-cancel]').addEventListener('click', function () { mode = ''; hint.hidden = true; });
    $('[data-marker-close]').addEventListener('click', closeSheet);
    // Fermer aussi par Échap et en glissant vers le bas.
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !msheet.hidden) closeSheet(); });
    var y0 = null;
    msheet.addEventListener('touchstart', function (e) { y0 = msheet.scrollTop <= 0 ? e.touches[0].clientY : null; }, { passive: true });
    msheet.addEventListener('touchend', function (e) { if (y0 !== null && e.changedTouches[0].clientY - y0 > 80) closeSheet(); y0 = null; });
    // Carte en grand : Fullscreen API, repli CSS (iOS), choix gardé pendant la session.
    var wrap = $('.mm-mapwrap');
    var fbtn = $('[data-map-full]');
    var setFull = function (on) {
      wrap.classList.toggle('is-full', on);
      fbtn.textContent = on ? '✕ Quitter' : 'Plein écran';
      fbtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      try { sessionStorage.setItem('mm-map-full', on ? '1' : ''); } catch (e) {}
      if (map) setTimeout(function () { map.invalidateSize(); }, 60);
    };
    fbtn.addEventListener('click', function () {
      var on = !wrap.classList.contains('is-full');
      if (on && wrap.requestFullscreen) wrap.requestFullscreen().catch(function () {});
      if (!on && document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {});
      setFull(on);
    });
    document.addEventListener('fullscreenchange', function () { if (!document.fullscreenElement && wrap.classList.contains('is-full')) setFull(false); });
    window.addEventListener('resize', function () { if (map) map.invalidateSize(); });
    try { if (sessionStorage.getItem('mm-map-full')) setFull(true); } catch (e) {}
    $('[data-marker-move]').addEventListener('click', function () {
      if (!editing) return;
      editing.kind = kindOf();
      editing.text = $('[data-marker-text]').value;
      mode = 'move';
      msheet.hidden = true;
      hint.hidden = false;
    });
    $('[data-marker-save]').addEventListener('click', function () {
      if (!pending) return;
      var params = { kind: kindOf(), text: $('[data-marker-text]').value, x: pending.x, y: pending.y };
      var path = editing ? ('/m/api/marqueurs/' + editing.id) : '/m/api/marqueurs';
      post(path, params).then(function (j) {
        toast(j.ok ? (editing ? 'Marqueur enregistré.' : 'Marqueur posé : il arrive dans les ATAK de la partie.') : failText(j));
        if (j.ok) { closeSheet(); refresh(); }
      }).catch(function () { toast(failText(null)); });
    });
    $('[data-marker-delete]').addEventListener('click', function () {
      if (!editing || !window.confirm('Retirer ce marqueur de la carte de la partie ?')) return;
      post('/m/api/marqueurs/' + editing.id + '/supprimer').then(function (j) {
        toast(j.ok ? 'Marqueur retiré.' : failText(j));
        closeSheet();
        refresh();
      });
    });
  }

  // ── Briefs air (même boîte que l'espace BRIEFS AIR du téléphone en jeu) ──
  if (VIEW === 'briefs') {
    var LABELS = {
      '9line': ['IP / BP', 'Cap depuis l’IP', 'Distance', 'Altitude de la cible', 'Description de la cible', 'Position de la cible', 'Marquage', 'Amis', 'Dégagement'],
      '5line': ['Indicatif et ordre d’alerte', 'Position des amis', 'Position de la cible', 'Description et marquage de la cible', 'Remarques et restrictions'],
      medevac: ['Position de la LZ', 'Fréquence et indicatif', 'Patients par urgence', 'Équipement spécial', 'Patients (couchés / assis)', 'Sécurité de la LZ', 'Marquage de la LZ', 'Nationalité', 'NRBC et terrain']
    };
    var TITLES = { '9line': 'CAS 9-LINE', '5line': '5-LINE', medevac: 'MEDEVAC 9-LINE' };
    var STATES = { sent: 'ENVOYÉ', received: 'REÇU', read: 'LU', wilco: 'WILCO', unable: 'UNABLE' };
    var known = null;
    var briefs = [];
    var openId = 0;
    var list = $('[data-brief-list]');
    var bsheet = $('[data-brief-sheet]');
    var banner = $('[data-brief-banner]');
    var baseTitle = document.title;
    var unread = function (b) { return b.status === 'sent' || b.status === 'received'; };
    var render = function () {
      if (!briefs.length) { list.innerHTML = '<p class="mm-muted">Aucun brief reçu pour l’instant.</p>'; return; }
      var sorted = briefs.slice().sort(function (a, b) { return (unread(a) ? 0 : 1) - (unread(b) ? 0 : 1) || b.id - a.id; });
      var nUnread = sorted.filter(unread).length;
      var row = function (b) {
        var u = unread(b);
        var st = STATES[b.status] ? b.status : 'sent';
        return '<button type="button" class="mm-brief' + (u ? ' is-new' : '') + '" data-brief="' + b.id + '">' +
          '<strong>' + esc(TITLES[b.format] || 'BRIEF') + (u ? ' <em>NOUVEAU</em>' : '') + '</strong>' +
          '<span translate="no">' + esc(b.from) + ' → ' + esc(b.to) + '</span>' +
          '<small>' + esc(b.time) + '</small><b class="mm-ack ' + st + '">' + esc(STATES[st]) + '</b></button>';
      };
      list.innerHTML = (nUnread ? '<p class="mm-unread-h">' + esc('Non lus') + ' · ' + nUnread + '</p>' : '') +
        sorted.slice(0, nUnread).map(row).join('') +
        (nUnread && sorted.length > nUnread ? '<p class="mm-unread-h">' + esc('Déjà lus') + '</p>' : '') +
        sorted.slice(nUnread).map(row).join('');
    };
    var openBrief = function (id) {
      var b = briefs.filter(function (x) { return x.id === id; })[0];
      if (!b) return;
      openId = id;
      var labels = LABELS[b.format] || LABELS['9line'];
      $('[data-brief-title]').textContent = (TITLES[b.format] || 'BRIEF') + ' · ' + b.from;
      $('[data-brief-lines]').innerHTML = labels.map(function (l, i) {
        return '<li><span>' + esc(l) + '</span><b translate="no">' + esc(b.lines[i] || '—') + '</b></li>';
      }).join('') + (b.remarks ? '<li class="mm-lines__rem"><span>Remarques</span><b translate="no">' + esc(b.remarks) + '</b></li>' : '');
      var bst = unread(b) ? 'read' : (STATES[b.status] ? b.status : 'sent');
      $('[data-brief-state]').innerHTML = '<b class="mm-ack ' + bst + '">' + esc(STATES[bst]) + '</b>';
      bsheet.hidden = false;
      // Ouvert sur le téléphone : « lu » pour le JTAC (une seule fois).
      if (unread(b)) {
        b.status = 'read';
        post('/m/api/briefs/' + id + '/reponse', { answer: 'read' });
      }
      render();
    };
    list.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-brief]');
      if (b) openBrief(Number(b.getAttribute('data-brief')));
    });
    $('[data-brief-close]').addEventListener('click', function () { bsheet.hidden = true; openId = 0; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !bsheet.hidden) { bsheet.hidden = true; openId = 0; } });
    var poll = function () {
      getJSON('/m/api/briefs').then(function (j) {
        if (!j || !j.ok) {
          if (j && j.error === 'not_aircrew') list.innerHTML = '<p class="mm-muted">Vous n’êtes plus à bord d’un appareil.</p>';
          return;
        }
        briefs = j.briefs || [];
        var ids = briefs.map(function (b) { return b.id; });
        if (known !== null) {
          var fresh = ids.filter(function (id) { return known.indexOf(id) < 0; });
          if (fresh.length) {
            banner.hidden = false;
            document.title = '(' + fresh.length + ') ' + baseTitle;
            if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
            setTimeout(function () { banner.hidden = true; document.title = baseTitle; }, 8000);
          }
        }
        known = ids;
        render();
      }).catch(function () {});
    };
    $all('[data-brief-answer]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!openId) return;
        var answer = btn.getAttribute('data-brief-answer');
        post('/m/api/briefs/' + openId + '/reponse', { answer: answer.toLowerCase() }).then(function (j) {
          $('[data-brief-state]').textContent = j.ok ? (answer + ' envoyé au JTAC ✓') : failText(j);
          if (j.ok) poll();
        });
      });
    });
    banner.addEventListener('click', function () { banner.hidden = true; });
    poll();
    setInterval(function () { if (!document.hidden) poll(); }, 5000);
  }
})();
