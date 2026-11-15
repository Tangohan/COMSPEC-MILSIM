/* Poste TOC web : onglet « Téléphones » (rubrique Situation). Liste des téléphones ATAK connus du poste (joueurs et PNJ),
 * fiche d'un téléphone dans le tiroir de la console avec quatre actions : notifier, faire sonner ou vibrer, localiser,
 * trianguler (et le journal). Positions et relèvements sont des positions du jeu (mètres Arma) : jamais un appareil réel.
 * La localisation est la réponse du téléphone (jamais inventée : sans réponse, « position inconnue ») ; la triangulation
 * est une estimation et le dit. Les écritures passent par /atak/toc/api/phones/… (droit atak.toc.phone, CSRF, journal).
 * Dépend de toc-console.js (window.TocHost), toc-console-ui.js (tiroir), toc-geo.js et toc-tracks.js. */
window.TocPhones = (function () {
  'use strict';
  var G = window.TocGeo;
  var T = window.TocTracks;
  var UI = window.TocUi;
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var host = function () { return window.TocHost || null; };

  var P = {
    data: null, events: [], error: '', loading: false, sel: null, tab: 'notify', q: '', state: 'all', kind: 'all',
    structSig: '', timer: null, tm: null, layer: null, mapKey: '', pinned: {}, active: false, drawerOpen: false, level: 'info', mode: 'ring', seconds: 10, sent: 0
  };

  var ERRORS = {
    csrf: 'Session expirée : rechargez la page.', forbidden: 'Ce poste demande le droit « tenir le poste TOC web ».',
    forbidden_phone: 'Agir sur les téléphones demande le droit « Agir sur les téléphones en jeu ».',
    disabled: 'Les actions sur les téléphones sont désactivées pour votre communauté.',
    not_ready: 'Cette fonction n’est pas encore installée sur le site (étape 4690 à lancer).',
    schema_not_ready: 'Cette fonction n’est pas encore installée sur le site (étape 4690 à lancer).',
    target_unknown: 'Ce téléphone n’est plus connu du poste.',
    view_degraded: 'Les positions sont brouillées : le poste n’agit pas sur des positions qu’il ne lit pas clairement.',
    confirm_required: 'Confirmez l’action pour l’envoyer.',
    rate_limited: 'Trop de demandes d’affilée : attendez avant de recommencer.',
    too_many_pending: 'Ce joueur a déjà trois messages en attente.',
    too_many_triangulations: 'Trop de triangulations en cours : attendez qu’une se termine.',
    message_required: 'Écrivez le message.', message_too_long: 'Le message dépasse 160 caractères.',
    message_too_long_encoded: 'Le message est trop long une fois ses caractères spéciaux codés : raccourcissez-le.',
    level_invalid: 'Niveau inconnu.', duration_invalid: 'La durée va de 3 à 30 secondes.', mode_invalid: 'Mode inconnu.',
    target_not_player: 'Ce contact n’a pas de téléphone de joueur allié : pas de message, de sonnerie ni de localisation par le téléphone.',
    no_position: 'Aucune position connue pour ce téléphone.',
    stale_position: 'La dernière position est trop ancienne pour trianguler.',
    send_failed: 'Le site n’a pas pu mettre la demande en file : réessayez.', request_invalid: 'Demande inconnue.'
  };
  function errText(j) { return ERRORS[j && j.error] || 'Action refusée.'; }

  var STATE_LABEL = { online: 'En ligne', no_coverage: 'Hors couverture', offline: 'Hors ligne' };
  var STATE_TONE = { online: 'accent', no_coverage: 'amber', offline: 'grey' };
  var ACTION_LABEL = { notify: 'Message', ring: 'Sonnerie', ring_stop: 'Arrêt de la sonnerie', locate: 'Localisation', triangulate: 'Triangulation' };
  var LEVEL_LABEL = { info: 'Info', alert: 'Alerte', urgent: 'Urgent' };
  var ACC_LABEL = { bonne: 'Précision bonne', moyenne: 'Précision moyenne', faible: 'Précision faible' };

  function pill(text, tone) { return '<span class="toc-pill ' + (tone ? 'is-' + tone : '') + '">' + esc(text) + '</span>'; }
  function txt(s) { return '<span>' + esc(s) + '</span>'; }
  function two(n) { return (n < 10 ? '0' : '') + n; }
  function hhmm(ts) { var d = new Date(ts * 1000); return two(d.getUTCHours()) + ':' + two(d.getUTCMinutes()) + ':' + two(d.getUTCSeconds()); }
  function nowS() { return P.data && P.data.now ? P.data.now + Math.max(0, (Date.now() - (P.data._at || Date.now())) / 1000) : Date.now() / 1000; }
  function grid(p) { return p && G ? G.grid(p[0], p[1], 6) : '—'; }
  function phoneBy(ref) { var l = (P.data && P.data.phones) || []; for (var i = 0; i < l.length; i++) if (l[i].ref === ref) return l[i]; return null; }
  function ageText(sec) { return sec == null ? '' : T.ageLabel(sec); }
  function eventsFor(ref) { return P.events.filter(function (e) { return e.target === ref; }); }
  function can() { return !!(P.data && P.data.ready && P.data.enabled && P.data.canPhone); }

  /* ---------- Réseau ---------- */
  function q(path) {
    var s = host().state();
    return path + (path.indexOf('?') >= 0 ? '&' : '?') + 'mapId=' + s.mapId + '&side=' + s.side;
  }
  function load() {
    var h = host();
    if (!h || P.loading) return Promise.resolve();
    P.loading = true;
    return Promise.all([h.api(q('/atak/toc/api/phones')), h.api('/atak/toc/api/phones/events')]).then(function (r) {
      P.loading = false;
      var a = r[0], b = r[1];
      if (!a || a.ok === false) { P.error = a && a.error === 'forbidden' ? 'forbidden' : 'unavailable'; P.data = P.data || null; render(); return; }
      P.error = '';
      a._at = Date.now();
      P.data = a;
      if (b && b.ok && b.events) P.events = b.events;
      render();
      drawMap();
      if (P.drawerOpen) refreshSheet();
    }).catch(function () { P.loading = false; P.error = 'unavailable'; render(); });
  }
  function post(path, body) {
    return host().api(q(path), { method: 'POST', body: body || {} });
  }
  function schedule() {
    clearTimeout(P.timer);
    if (!P.active && !P.drawerOpen) return;
    var busy = P.events.some(function (e) { return e.status === 'queued' || e.status === 'running' || e.status === 'delivered'; });
    P.timer = setTimeout(function () { if (document.hidden) { schedule(); return; } load().then(schedule, schedule); }, busy ? 2000 : 6000);
  }

  /* ---------- Liste ---------- */
  function filtered() {
    var l = (P.data && P.data.phones) || [];
    var n = P.q.trim().toUpperCase();
    return l.filter(function (p) {
      if (P.state !== 'all' && p.state !== P.state) return false;
      if (P.kind !== 'all' && p.kind !== P.kind) return false;
      return !n || (p.callsign + ' ' + p.group).toUpperCase().indexOf(n) >= 0;
    });
  }
  function banner() {
    var d = P.data;
    if (P.error === 'forbidden') return '<div class="toc-ph-banner is-warn"><p>' + esc(ERRORS.forbidden) + '</p></div>';
    if (!d) return P.error ? '<div class="toc-ph-banner is-warn"><p>' + esc('Téléphones indisponibles pour l’instant : nouvelle tentative dans quelques secondes.') + '</p></div>' : '';
    var h = '';
    if (!d.ready) h += '<div class="toc-ph-banner is-warn"><p>' + esc(ERRORS.not_ready) + '</p></div>';
    else if (!d.enabled) {
      h += '<div class="toc-ph-banner"><p>' + esc(ERRORS.disabled) + '</p>' + (d.canManage ? '<button type="button" class="toc-btn is-small" data-ph="settings">Régler la fonction</button>' : '<p class="toc-muted">' + esc('Demandez à la gestion du poste de l’activer.') + '</p>') + '</div>';
    } else if (!d.canPhone) h += '<div class="toc-ph-banner"><p>' + esc(ERRORS.forbidden_phone) + '</p></div>';
    if (d.warning === 'view_degraded') h += '<div class="toc-ph-banner is-warn"><p>' + esc(ERRORS.view_degraded) + '</p></div>';
    return h;
  }
  function rowHtml(p) {
    var tone = STATE_TONE[p.state] || 'grey';
    var fix = p.fix;
    var posTxt = p.pos ? '<span class="mono">' + esc(grid(p.pos)) + '</span>' : '<span class="toc-muted">' + esc('position inconnue') + '</span>';
    var kind = p.kind === 'npc' ? pill(p.hostile ? 'PNJ hostile' : 'PNJ', p.hostile ? 'red' : 'blue') : (p.hostile ? pill('Hostile', 'red') : '');
    return '<li><button type="button" class="toc-ph-row' + (P.sel === p.ref ? ' is-sel' : '') + (p.state === 'offline' ? ' is-off' : '') + '" data-ph="open" data-ref="' + esc(p.ref) + '">' +
      '<span class="toc-ph-dot is-' + tone + '" aria-hidden="true"></span>' +
      '<span class="toc-ph-main"><b>' + esc(p.callsign) + '</b><small>' + esc(p.group || p.role || '') + '</small></span>' +
      '<span class="toc-ph-state">' + pill(STATE_LABEL[p.state], tone) + kind + '</span>' +
      '<span class="toc-ph-pos">' + posTxt + '<small>' + (p.age != null ? esc(ageText(p.age)) : '') + '</small></span>' +
      '<span class="toc-ph-meta">' + (p.battery != null ? '<span class="mono toc-ph-bat' + (p.battery <= 20 ? ' is-low' : '') + '">' + p.battery + ' %</span>' : '') +
      (p.pending ? '<span class="toc-badge">' + p.pending + '</span>' : '') + (fix ? '<span class="toc-ph-fixmark" title="' + esc('Dernière position obtenue') + '">◎</span>' : '') + '</span>' +
      '</button></li>';
  }
  function listHtml() {
    var rows = filtered();
    if (!P.data) return '<p class="toc-note">' + esc(P.error ? '' : 'Chargement des téléphones…') + '</p>';
    if (!(P.data.phones || []).length) return '<div class="toc-empty"><p>' + esc('Aucun téléphone connu sur ce terrain pour ce camp.') + '</p><p class="toc-note">' + esc('Les téléphones apparaissent quand les joueurs ont un ATAK relié à Athena.') + '</p></div>';
    if (!rows.length) return '<p class="toc-note">' + esc('Aucun téléphone ne correspond à la recherche.') + '</p>';
    return '<ul class="toc-ph-list" aria-label="' + esc('Téléphones') + '">' + rows.map(rowHtml).join('') + '</ul>';
  }
  function chips() {
    var defs = [['state', 'all', 'Tous'], ['state', 'online', 'En ligne'], ['state', 'no_coverage', 'Hors couverture'], ['state', 'offline', 'Hors ligne'], ['kind', 'player', 'Joueurs'], ['kind', 'npc', 'PNJ']];
    return '<div class="toc-ph-chips" role="group" aria-label="' + esc('Filtres') + '">' + defs.map(function (c) {
      var on = c[0] === 'state' ? P.state === c[1] : (P.kind === c[1]);
      return '<button type="button" class="toc-ph-chip' + (on ? ' is-on' : '') + '" data-ph="chip" data-k="' + c[0] + '" data-v="' + c[1] + '" aria-pressed="' + on + '">' + esc(c[2]) + '</button>';
    }).join('') + '</div>';
  }
  function panel() {
    var l = (P.data && P.data.phones) || [];
    var on = l.filter(function (p) { return p.state === 'online'; }).length;
    var head = '<div class="toc-sec"><h2>' + esc('Téléphones') + '</h2><span class="toc-dots"><span class="toc-n"><b>' + l.length + '</b> <span>' + esc('téléphone(s)') + '</span></span><span class="toc-n"><b>' + on + '</b> <span>' + esc('en ligne') + '</span></span></span></div>' +
      '<p class="toc-ph-desc">' + esc('Les téléphones ATAK des joueurs et des PNJ connus du poste : notifier, faire sonner, localiser ou trianguler.') + '</p>';
    var tools = '<div class="toc-ph-tools"><label class="toc-sr" for="toc-ph-q">' + esc('Chercher un téléphone') + '</label>' +
      '<input id="toc-ph-q" class="toc-input toc-ph-q" type="search" data-ph-q placeholder="' + esc('Indicatif ou groupe…') + '" autocomplete="off" value="' + esc(P.q) + '">' +
      (P.data && P.data.canManage ? '<button type="button" class="toc-btn" data-ph="settings">' + esc('Réglages') + '</button>' : '') + '</div>';
    return head + banner() + tools + chips() + '<div id="toc-ph-listbox">' + listHtml() + '</div>' +
      '<p class="toc-note">' + esc('Un clic ouvre la fiche du téléphone. Une position obtenue s’affiche sur la carte avec sa précision et son âge.') + '</p>';
  }
  function render() {
    var h = host();
    if (!h) return;
    var st = h.state();
    if (st.tab !== 'telephones') return;
    var box = $('#toc-ph-listbox');
    // Les droits, l'activation ou une erreur changent la barre d'outils et les bandeaux : alors tout le panneau est redessiné.
    var d = P.data;
    var sig = d ? [d.ready, d.enabled, d.canPhone, d.canManage, d.warning || ''].join('|') : 'none|' + P.error;
    if (box && sig === P.structSig) { box.innerHTML = listHtml(); var s = $('#toc-ph-q'); if (s && document.activeElement !== s) s.value = P.q; syncHead(); return; }
    P.structSig = sig;
    h.renderPanel();
  }
  function syncHead() {
    var l = (P.data && P.data.phones) || [];
    var on = l.filter(function (p) { return p.state === 'online'; }).length;
    var dots = $('#toc-panel .toc-sec .toc-dots');
    if (dots) dots.innerHTML = '<span class="toc-n"><b>' + l.length + '</b> <span>' + esc('téléphone(s)') + '</span></span><span class="toc-n"><b>' + on + '</b> <span>' + esc('en ligne') + '</span></span>';
    var b = $('#toc-panel .toc-ph-banner');
    var holder = $('#toc-panel');
    if (holder) {
      Array.prototype.slice.call(holder.querySelectorAll('.toc-ph-banner')).forEach(function (n) { n.remove(); });
      var d = $('#toc-panel .toc-ph-desc');
      if (d) d.insertAdjacentHTML('afterend', banner());
    }
    return b;
  }

  /* ---------- Fenêtre de confirmation ---------- */
  function confirmDialog(cfg) {
    return new Promise(function (resolve) {
      var back = document.activeElement;
      var veil = document.createElement('div');
      veil.className = 'toc-ph-modal-veil';
      veil.innerHTML = '<div class="toc-ph-modal" role="alertdialog" aria-modal="true" aria-labelledby="toc-ph-modal-t" aria-describedby="toc-ph-modal-d"><h2 id="toc-ph-modal-t">' + esc(cfg.title) + '</h2><p class="toc-ph-modal-who"><b>' + esc(cfg.who) + '</b>' + (cfg.extra ? '<span class="mono">' + esc(cfg.extra) + '</span>' : '') + '</p><p id="toc-ph-modal-d">' + esc(cfg.text) + '</p>' +
        '<div class="toc-ph-modal-btns"><button type="button" class="toc-btn" data-m="no">' + esc('Annuler') + '</button><button type="button" class="toc-btn ' + (cfg.danger ? 'is-danger' : 'is-primary') + '" data-m="yes">' + esc(cfg.confirm) + '</button></div></div>';
      document.body.appendChild(veil);
      var btns = veil.querySelectorAll('button');
      function done(v) { window.removeEventListener('keydown', onKey, true); veil.remove(); if (back && back.focus && document.contains(back)) back.focus(); resolve(v); }
      function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); done(false); }
        else if (e.key === 'Tab') {
          e.preventDefault(); e.stopPropagation();
          var i = Array.prototype.indexOf.call(btns, document.activeElement);
          btns[(i + (e.shiftKey ? -1 : 1) + btns.length) % btns.length].focus();
        }
      }
      window.addEventListener('keydown', onKey, true);
      veil.addEventListener('click', function (e) { var b = e.target.closest('[data-m]'); if (b) done(b.getAttribute('data-m') === 'yes'); else if (e.target === veil) done(false); });
      btns[0].focus();
    });
  }

  /* ---------- Fiche (tiroir) ---------- */
  var TABS = [['notify', 'Notifier'], ['ring', 'Sonner'], ['locate', 'Localiser'], ['tri', 'Trianguler'], ['journal', 'Journal']];
  function reason(p) {
    var d = P.data;
    if (!d.ready) return ERRORS.not_ready;
    if (!d.enabled) return ERRORS.disabled;
    if (!d.canPhone) return ERRORS.forbidden_phone;
    return '';
  }
  function disabledNote(p, kind) {
    var r = reason(p);
    if (r) return r;
    if (kind === 'msg' && !p.can.notify) return ERRORS.target_not_player;
    if (kind === 'locate' && !p.can.locate) return 'Un PNJ n’a pas de téléphone qui réponde : utilisez la triangulation, qui estime sa position.';
    if (kind === 'tri' && !p.can.triangulate) return ERRORS.no_position;
    return '';
  }
  function pane(p) {
    var tab = P.tab;
    if (tab === 'notify') {
      var off = disabledNote(p, 'msg');
      return '<h3>' + esc('Envoyer un message') + '</h3><p class="toc-ph-desc">' + esc('Le message arrive dans l’app Messages et dans le journal de l’app Alertes du téléphone, sans bandeau ni fenêtre.') + '</p>' +
        (off ? '<p class="toc-ph-off" role="note">' + esc(off) + '</p>' : '') + (off ? '' :
        '<form class="toc-form toc-ph-form" data-ph-form="notify"><label for="toc-ph-msg">' + esc('Message') + '</label>' +
        '<textarea id="toc-ph-msg" class="toc-textarea" name="message" rows="4" maxlength="160" required></textarea>' +
        '<p class="toc-ph-count"><b class="mono" id="toc-ph-count">0</b><span> / 160 </span><span>' + esc('caractères') + '</span></p>' +
        '<fieldset class="toc-ph-levels"><legend>' + esc('Niveau') + '</legend>' + ['info', 'alert', 'urgent'].map(function (lv) {
          var desc = { info: 'Arrive sans bruit.', alert: 'Le téléphone vibre.', urgent: 'Trois vibrations, confirmation demandée.' }[lv];
          return '<label class="toc-ph-level is-' + lv + '"><input type="radio" name="level" value="' + lv + '"' + (P.level === lv ? ' checked' : '') + '><span><b>' + esc(LEVEL_LABEL[lv]) + '</b><small>' + esc(desc) + '</small></span></label>';
        }).join('') + '</fieldset>' +
        '<label class="toc-ph-check"><input type="checkbox" name="ack"><span><b>' + esc('Demander un accusé de réception') + '</b><small>' + esc('Le journal montre « reçu » puis « lu ».') + '</small></span></label>' +
        '<button type="submit" class="toc-btn is-primary toc-ph-go">' + esc('Envoyer le message') + '</button></form>') +
        '<div class="toc-ph-live" data-live="notify"></div>';
    }
    if (tab === 'ring') {
      var offr = disabledNote(p, 'msg');
      return '<h3>' + esc('Faire sonner ou vibrer') + '</h3><p class="toc-ph-desc">' + esc('Le téléphone sonne ou vibre avec les sons déjà présents du mod, de 3 à 30 secondes.') + '</p>' +
        (offr ? '<p class="toc-ph-off" role="note">' + esc(offr) + '</p>' : '') + (offr ? '' :
        '<form class="toc-form toc-ph-form" data-ph-form="ring"><fieldset class="toc-ph-levels"><legend>' + esc('Signal') + '</legend>' +
        [['ring', 'Sonnerie', 'Son de notification d’ordre et vibration.'], ['vib', 'Vibration', 'Vibration du téléphone seule.']].map(function (m) {
          return '<label class="toc-ph-level"><input type="radio" name="mode" value="' + m[0] + '"' + (P.mode === m[0] ? ' checked' : '') + '><span><b>' + esc(m[1]) + '</b><small>' + esc(m[2]) + '</small></span></label>';
        }).join('') + '</fieldset>' +
        '<label for="toc-ph-sec">' + esc('Durée') + '</label><div class="toc-ph-range"><input id="toc-ph-sec" type="range" name="seconds" min="3" max="30" step="1" value="' + P.seconds + '"><output class="mono" id="toc-ph-sec-out">' + P.seconds + ' s</output></div>' +
        '<div class="toc-buttons" style="--cols:2"><button type="submit" class="toc-btn is-danger toc-ph-go">' + esc('Faire sonner') + '</button><button type="button" class="toc-btn toc-ph-go" data-ph="stop">' + esc('Arrêter') + '</button></div></form>') +
        '<div class="toc-ph-live" data-live="ring"></div>';
    }
    if (tab === 'locate') {
      var offl = disabledNote(p, 'locate');
      return '<h3>' + esc('Demander la position') + '</h3><p class="toc-ph-desc">' + esc('Le téléphone répond avec sa position dans le jeu et sa précision. Sans réponse : position inconnue.') + '</p>' +
        (offl ? '<p class="toc-ph-off" role="note">' + esc(offl) + '</p>' : '') +
        (offl ? '' : '<button type="button" class="toc-btn is-primary toc-ph-go" data-ph="locate">' + esc('Localiser') + '</button>' +
        '<p class="toc-note">' + esc('Une demande par téléphone toutes les 15 secondes. Le joueur voit « Localisé par le TOC » dans son téléphone.') + '</p>') +
        '<div class="toc-ph-live" data-live="locate"></div>';
    }
    if (tab === 'tri') {
      var offt = disabledNote(p, 'tri');
      return '<h3>' + esc('Trianguler') + '</h3><p class="toc-ph-desc">' + esc('Estimation de position à partir des relais et des téléphones alliés qui entendent ce téléphone : toujours une estimation, avec sa zone d’incertitude.') + '</p>' +
        (offt ? '<p class="toc-ph-off" role="note">' + esc(offt) + '</p>' : '') +
        (offt ? '' : '<button type="button" class="toc-btn is-primary toc-ph-go" data-ph="tri">' + esc('Trianguler') + '</button>' +
        '<p class="toc-note">' + esc('Les sources écoutent de 6 à 10 secondes. Le résultat est le même pendant 30 secondes pour un même téléphone.') + '</p>') +
        '<div class="toc-ph-live" data-live="tri"></div>';
    }
    return '<h3>' + esc('Journal de ce téléphone') + '</h3><p class="toc-ph-desc">' + esc('Chaque demande du poste : qui, quoi, quand et le résultat.') + '</p><div class="toc-ph-live" data-live="journal"></div>';
  }
  function sheetBody(p) {
    var tone = STATE_TONE[p.state] || 'grey';
    var sum = '<div class="toc-ph-summary">' + pill(STATE_LABEL[p.state], tone) + (p.kind === 'npc' ? pill(p.hostile ? 'PNJ hostile' : 'PNJ', p.hostile ? 'red' : 'blue') : (p.hostile ? pill('Hostile', 'red') : pill('Joueur', 'accent'))) +
      '<span class="mono">' + esc(grid(p.pos)) + '</span><span class="toc-muted">' + esc(p.age != null ? ageText(p.age) : 'position inconnue') + '</span>' + (p.battery != null ? '<span class="mono">' + p.battery + ' %</span>' : '') +
      (p.pos ? '<button type="button" class="toc-btn is-small" data-ph="center">' + esc('Centrer') + '</button>' : '') + '</div>';
    var tabs = '<div class="toc-ph-tabs" role="tablist" aria-label="' + esc('Actions') + '">' + TABS.map(function (t) {
      return '<button type="button" role="tab" class="toc-ph-tab" id="ph-tab-' + t[0] + '" aria-selected="' + (P.tab === t[0]) + '" data-ph="tab" data-t="' + t[0] + '">' + esc(t[1]) + '</button>';
    }).join('') + '</div>';
    return sum + tabs + '<div class="toc-ph-pane" role="tabpanel" aria-labelledby="ph-tab-' + P.tab + '" id="toc-ph-pane">' + pane(p) + '</div><p class="toc-drawer-msg" role="status"></p>';
  }
  function openSheet(ref) {
    var p = phoneBy(ref);
    if (!p) return;
    P.sel = ref;
    P.drawerOpen = true;
    UI.drawer.open({
      id: 'phone', title: p.callsign, sub: 'Notifier, faire sonner, localiser ou trianguler ce téléphone.',
      body: sheetBody(p),
      onOpen: function () { bindSheet(); refreshSheet(); },
      onClose: function () { P.drawerOpen = false; P.sel = null; render(); schedule(); }
    });
    render();
    schedule();
    load();
  }
  function msg(text, isErr) { UI.drawer.message(text, isErr); }
  function bindSheet() {
    var body = UI.drawer.body();
    if (!body) return;
    var ta = $('#toc-ph-msg', body);
    if (ta) ta.addEventListener('input', function () { $('#toc-ph-count', body).textContent = String(ta.value.length); });
    var rg = $('#toc-ph-sec', body);
    if (rg) rg.addEventListener('input', function () { P.seconds = Number(rg.value); $('#toc-ph-sec-out', body).textContent = rg.value + ' s'; });
    Array.prototype.slice.call(body.querySelectorAll('input[name="level"]')).forEach(function (r) { r.addEventListener('change', function () { P.level = r.value; }); });
    Array.prototype.slice.call(body.querySelectorAll('input[name="mode"]')).forEach(function (r) { r.addEventListener('change', function () { P.mode = r.value; }); });
  }
  function switchTab(t) {
    var p = phoneBy(P.sel);
    var body = UI.drawer.body();
    if (!p || !body) return;
    P.tab = t;
    var paneEl = $('#toc-ph-pane', body);
    paneEl.innerHTML = pane(p);
    paneEl.setAttribute('aria-labelledby', 'ph-tab-' + t);
    Array.prototype.slice.call(body.querySelectorAll('.toc-ph-tab')).forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-t') === t)); });
    msg('');
    bindSheet();
    refreshSheet();
  }

  /* Zones vivantes de la fiche : états des demandes, position obtenue, triangulation, journal. */
  function evLabel(e) {
    var st = e.status;
    if (e.action === 'notify') {
      if (st === 'done') return e.ack_requested ? ['Lu', 'accent'] : ['Reçu', 'accent'];
      if (st === 'delivered') return ['Reçu, pas encore lu', 'amber'];
    }
    if (e.action === 'locate' && (st === 'expired' || (st === 'failed' && e.result === 'position_unknown'))) return ['Position inconnue', 'red'];
    var m = { queued: ['En file, en attente du jeu', 'amber'], delivered: ['Reçu par le téléphone', 'amber'], done: ['Terminé', 'accent'], failed: ['Échec', 'red'], expired: ['Sans réponse du téléphone', 'red'], cancelled: ['Annulé', 'grey'], refused: ['Refusé', 'red'], running: ['Écoute en cours', 'amber'] };
    return m[st] || [st, 'grey'];
  }
  function fixHtml(e) {
    var f = e.fix;
    if (!f) return '';
    var age = Math.max(0, nowS() - f.at);
    return '<div class="toc-ph-fix"><div class="toc-ph-fixhead">' + pill(f.source === 'phone' ? 'Réponse du téléphone' : 'Estimation', f.source === 'phone' ? 'accent' : 'amber') + '</div>' +
      '<p><span class="mono toc-ph-big">' + esc(G ? G.grid(f.x, f.y, 8) : '') + '</span></p>' +
      '<p><span>' + esc('Précision') + '</span> <b class="mono">' + (f.accuracy_m != null ? '± ' + Math.round(f.accuracy_m) + ' m' : '—') + '</b> <span class="toc-muted">' + esc(ageText(age)) + '</span></p>' +
      '<button type="button" class="toc-btn is-small" data-ph="show" data-id="' + e.id + '">' + esc('Voir sur la carte') + '</button></div>';
  }
  function triHtml(e) {
    var t = e.triangulation;
    if (e.status === 'running') {
      var total = 10;
      return '<div class="toc-ph-run" role="status"><p><b class="mono">' + Math.max(0, e.ready_in || 0) + ' s</b> <span>' + esc('Écoute en cours : les sources mesurent le signal.') + '</span></p><div class="toc-ph-bar"><i style="width:' + Math.round(100 * (1 - Math.min(1, (e.ready_in || 0) / total))) + '%"></i></div></div>';
    }
    if (!t) return '<p class="toc-note">' + esc(evLabel(e)[0]) + '</p>';
    var head = pill('Estimation', 'amber') + (t.accuracy && t.accuracy !== 'none' ? pill(ACC_LABEL[t.accuracy] || t.accuracy, t.accuracy === 'bonne' ? 'accent' : (t.accuracy === 'moyenne' ? 'amber' : 'red')) : '');
    var line;
    if (t.status === 'none') line = esc('Aucune source n’entend ce téléphone : pas d’estimation.');
    else if (t.status === 'circle') line = esc('Une seule source l’entend : cercle de ± %1 m, sa position n’est pas connue.'.replace('%1', Math.round(t.radius_m)));
    else if (t.status === 'ambiguous') line = esc('Deux sources : deux positions possibles, zone élargie à ± %1 m.'.replace('%1', Math.round(t.radius_m)));
    else line = '<span class="mono toc-ph-big">' + esc(G.grid(t.position[0], t.position[1], 8)) + '</span> <b class="mono">± ' + Math.round(t.radius_m) + ' m</b>';
    var rows = (t.readings || []).map(function (r) {
      return '<tr><td>' + esc(r.label) + '</td><td class="num mono">' + Math.round(r.distance_m) + ' m <small>± ' + Math.round(r.error_pct) + ' %</small></td><td class="num mono">' + Math.round(r.bearing_deg) + '°</td><td class="num mono">' + r.signal_dbm + '</td></tr>';
    }).join('');
    return '<div class="toc-ph-fix"><div class="toc-ph-fixhead">' + head + '</div><p>' + line + '</p>' +
      '<p class="toc-muted"><b class="mono">' + t.heard + '</b> <span>' + esc('source(s) entendue(s) sur') + '</span> <b class="mono">' + (t.sources_available != null ? t.sources_available : t.sources) + '</b></p>' +
      (rows ? '<div class="toc-ph-tablewrap"><table class="toc-table"><thead><tr><th>' + esc('Source') + '</th><th class="num">' + esc('Distance') + '</th><th class="num">' + esc('Azimut') + '</th><th class="num">dBm</th></tr></thead><tbody>' + rows + '</tbody></table></div>' : '') +
      (t.status !== 'none' ? '<button type="button" class="toc-btn is-small" data-ph="show" data-id="' + e.id + '">' + esc('Voir sur la carte') + '</button>' : '') + '</div>';
  }
  function journalHtml(list) {
    if (!list.length) return '<p class="toc-note">' + esc('Aucune demande pour ce téléphone.') + '</p>';
    return '<ul class="toc-ph-journal">' + list.slice(0, 30).map(function (e) {
      var l = evLabel(e);
      var what = ACTION_LABEL[e.action] || e.action;
      var lvl = e.action === 'notify' && LEVEL_LABEL[e.level] ? '<span class="toc-ph-jlevel">' + esc(LEVEL_LABEL[e.level]) + '</span>' : '';
      return '<li><span class="mono">' + hhmm(e.at) + 'Z</span><span class="toc-ph-jmain"><span class="toc-ph-jhead"><b>' + esc(what) + '</b>' + lvl + '</span><small>' + esc(e.operator) + (e.message && e.action === 'notify' ? ' · ' + esc(e.message) : '') + '</small></span>' + pill(l[0], l[1]) + '</li>';
    }).join('') + '</ul>';
  }
  function refreshSheet() {
    var body = UI.drawer.body();
    if (!body || !P.sel) return;
    var list = eventsFor(P.sel);
    var live = body.querySelector('.toc-ph-live');
    if (!live) return;
    var kind = live.getAttribute('data-live');
    var html = '';
    if (kind === 'journal') html = journalHtml(list);
    else {
      var act = { notify: 'notify', ring: 'ring', locate: 'locate', tri: 'triangulate' }[kind];
      var rel = list.filter(function (e) { return e.action === act || (kind === 'ring' && e.action === 'ring_stop'); });
      var last = rel[0];
      if (last) {
        var l = evLabel(last);
        html = '<div class="toc-ph-last"><span class="mono">' + hhmm(last.at) + 'Z</span> <b>' + esc(ACTION_LABEL[last.action] || last.action) + '</b> ' + pill(l[0], l[1]) + '</div>';
        if (kind === 'locate') {
          if (last.fix) html += fixHtml(last);
          else if (last.status === 'expired' || last.status === 'failed') html += '<div class="toc-ph-unknown"><p><b>' + esc('Position inconnue') + '</b></p><p class="toc-muted">' + esc('Le téléphone n’a pas répondu avec une position : il est peut-être éteint, hors couverture ou sans fix GPS.') + '</p></div>';
          var old = rel.filter(function (e) { return e.fix && e !== last; }).slice(0, 4);
          if (old.length) html += '<h4>' + esc('Positions précédentes') + '</h4><ul class="toc-ph-journal">' + old.map(function (e) { return '<li><span class="mono">' + hhmm(e.at) + 'Z</span><span class="toc-ph-jmain"><b class="mono">' + esc(G.grid(e.fix.x, e.fix.y, 6)) + '</b><small>± ' + Math.round(e.fix.accuracy_m || 0) + ' m</small></span></li>'; }).join('') + '</ul>';
        } else if (kind === 'tri') html += triHtml(last);
        else if (last.status === 'failed' || last.status === 'refused') html += '<p class="toc-muted">' + esc(last.result || '') + '</p>';
      }
    }
    if (live.getAttribute('data-sig') !== html) { live.innerHTML = html; live.setAttribute('data-sig', html); }
    var p = phoneBy(P.sel);
    var sum = $('.toc-ph-summary', body);
    if (p && sum) {
      var head = sheetBody(p);
      var tmp = document.createElement('div');
      tmp.innerHTML = head;
      var ns = $('.toc-ph-summary', tmp);
      if (ns && ns.innerHTML !== sum.innerHTML) sum.innerHTML = ns.innerHTML;
    }
  }

  /* ---------- Actions ---------- */
  function afterAction(j, okText) {
    if (!j || !j.ok) {
      var t = errText(j);
      if (j && j.error === 'rate_limited' && j.retry_after) t = 'Trop de demandes : réessayez dans %1 s.'.replace('%1', Math.round(j.retry_after));
      msg(t, true);
      return Promise.resolve(false);
    }
    msg(okText, false);
    return load().then(function () { schedule(); return true; });
  }
  function doNotify(form) {
    var p = phoneBy(P.sel);
    var text = form.elements.message.value.trim();
    var level = (form.querySelector('input[name="level"]:checked') || {}).value || 'info';
    if (!text) { msg(ERRORS.message_required, true); return; }
    var go = function (confirmed) {
      return post('/atak/toc/api/phones/' + P.sel + '/notify', { message: text, level: level, ack: !!form.elements.ack.checked, confirm: confirmed }).then(function (j) {
        return afterAction(j, 'Message envoyé : il part au téléphone dès que le jeu le reprend.').then(function (ok) {
          if (!ok) return;
          form.elements.message.value = '';
          var c = $('#toc-ph-count');
          if (c) c.textContent = '0';
        });
      });
    };
    if (level === 'urgent') {
      confirmDialog({ title: 'Envoyer un message urgent ?', who: p.callsign, text: 'Le téléphone vibrera trois fois. N’utilisez « Urgent » que pour une alerte qui ne peut pas attendre.', confirm: 'Envoyer en urgent', danger: true }).then(function (ok) { if (ok) go(true); });
    } else go(false);
  }
  function doRing(form) {
    var p = phoneBy(P.sel);
    var mode = (form.querySelector('input[name="mode"]:checked') || {}).value || 'ring';
    var sec = Number(form.elements.seconds.value);
    confirmDialog({ title: mode === 'vib' ? 'Faire vibrer ce téléphone ?' : 'Faire sonner ce téléphone ?', who: p.callsign, extra: sec + ' s', text: 'Le joueur peut être en train de se cacher : confirmez seulement si c’est voulu.', confirm: mode === 'vib' ? 'Faire vibrer' : 'Faire sonner', danger: true }).then(function (ok) {
      if (!ok) return;
      post('/atak/toc/api/phones/' + P.sel + '/ring', { seconds: sec, mode: mode, confirm: true }).then(function (j) { afterAction(j, 'Demande envoyée : le téléphone sonne dès que le jeu la reprend.'); });
    });
  }
  function simple(path, okText) { return post('/atak/toc/api/phones/' + P.sel + '/' + path).then(function (j) { return afterAction(j, okText); }); }

  /* ---------- Carte ---------- */
  function ll(p) { return P.tm.toLL(p); }
  function mapKeyNow() { var s = host().state(); return s.mapId + ':' + s.side; }
  function drawMap() {
    if (!P.tm || !window.L) return;
    // Panneau à part, au-dessus des plaques des pistes : une position obtenue ne doit jamais être cachée par un symbole.
    if (!P.tm.map.getPane('tocPhones')) { P.tm.map.createPane('tocPhones'); P.tm.map.getPane('tocPhones').style.zIndex = 640; }
    if (P.layer) P.layer.clearLayers(); else P.layer = L.layerGroup().addTo(P.tm.map);
    var seenLoc = {}, seenTri = {};
    var now = nowS();
    P.events.forEach(function (e) {
      if (!e.fix || now - e.fix.at > 900 && !P.pinned[e.id]) return;
      var phone = phoneBy(e.target);
      var name = phone ? phone.callsign : e.label;
      if (e.action === 'locate' && !seenLoc[e.target]) { seenLoc[e.target] = true; drawFix(e, name); }
      if (e.action === 'triangulate' && e.triangulation && !seenTri[e.target]) { seenTri[e.target] = true; drawTri(e, name); }
    });
  }
  function labelIcon(cls, html) { return L.divIcon({ className: 'toc-ph-mk ' + cls, html: html, iconSize: null, iconAnchor: [9, 9] }); }
  function drawFix(e, name) {
    var f = e.fix;
    var pos = [f.x, f.y];
    var age = Math.max(0, nowS() - f.at);
    if (f.accuracy_m) P.layer.addLayer(L.circle(ll(pos), { radius: f.accuracy_m, color: '#34d399', weight: 1.5, opacity: .9, fillColor: '#34d399', fillOpacity: .12, interactive: false, pane: 'tocPhones' }));
    var mk = L.marker(ll(pos), { icon: labelIcon('is-phone', '<i></i><span><b>' + esc(name) + '</b> ± ' + Math.round(f.accuracy_m || 0) + ' m · ' + esc(ageText(age)) + '</span>'), keyboard: false, pane: 'tocPhones' });
    mk.on('click', function () { openSheet(e.target); });
    P.layer.addLayer(mk);
  }
  function drawTri(e, name) {
    var t = e.triangulation;
    // Les six sources les mieux entendues sont dessinées (cercle de distance, relèvement) ; le tableau de la fiche les donne toutes.
    (t.readings || []).slice().sort(function (a, b) { return b.quality - a.quality; }).slice(0, 6).forEach(function (r) {
      P.layer.addLayer(L.circle(ll([r.x, r.y]), { radius: r.distance_m, color: '#62b0f5', weight: 1, opacity: .75, dashArray: '2 6', fill: false, interactive: false, pane: 'tocPhones' }));
      var a = r.bearing_deg * Math.PI / 180;
      var end = [r.x + Math.sin(a) * r.distance_m, r.y + Math.cos(a) * r.distance_m];
      P.layer.addLayer(L.polyline([ll([r.x, r.y]), ll(end)], { color: '#62b0f5', weight: 1.5, opacity: .9, interactive: false, pane: 'tocPhones' }));
      P.layer.addLayer(L.marker(ll([r.x, r.y]), { icon: labelIcon('is-src', '<i></i><span>' + esc(r.label) + '</span>'), keyboard: false, interactive: false, pane: 'tocPhones' }));
    });
    if (t.status === 'estimate' || t.status === 'ambiguous') {
      var cands = t.status === 'ambiguous' ? t.candidates : [t.position];
      cands.forEach(function (c) {
        P.layer.addLayer(L.circle(ll(c), { radius: t.radius_m, color: '#f5b841', weight: 2, dashArray: '6 5', fillColor: '#f5b841', fillOpacity: .12, interactive: false, pane: 'tocPhones' }));
        var mk = L.marker(ll(c), { icon: labelIcon('is-tri', '<i></i><span><b>' + esc(name) + '</b> · ' + esc('estimation') + ' ± ' + Math.round(t.radius_m) + ' m</span>'), keyboard: false, pane: 'tocPhones' });
        mk.on('click', function () { openSheet(e.target); });
        P.layer.addLayer(mk);
      });
    }
  }
  function showOnMap(id) {
    var e = P.events.filter(function (x) { return String(x.id) === String(id); })[0];
    if (!e || !e.fix || !P.tm) return;
    P.pinned[e.id] = true;
    drawMap();
    var r = e.triangulation ? Math.max(e.triangulation.radius_m || 100, 300) : Math.max((e.fix.accuracy_m || 30) * 4, 200);
    P.tm.map.fitBounds(L.latLngBounds(ll([e.fix.x - r, e.fix.y - r]), ll([e.fix.x + r, e.fix.y + r])), { maxZoom: 7 });
    UI.drawer.close(true);
    P.drawerOpen = false;
  }

  /* ---------- Réglages de la communauté ---------- */
  function openSettings() {
    var d = P.data || {};
    UI.drawer.open({
      id: 'phone-settings', title: 'Actions sur les téléphones', sub: 'Réglage de la communauté pour le poste TOC web.',
      body: '<form class="toc-form toc-ph-form" data-ph-form="settings">' +
        '<label class="toc-ph-check"><input type="checkbox" name="enabled"' + (d.enabled ? ' checked' : '') + '><span><b>' + esc('Activer les actions sur les téléphones en jeu') + '</b><small>' + esc('Désactivé tant que la communauté ne l’a pas activé. Il faut aussi le droit « Agir sur les téléphones en jeu ».') + '</small></span></label>' +
        '<label class="toc-ph-check"><input type="checkbox" name="hide"' + (d.hide_hostile_journal ? ' checked' : '') + '><span><b>' + esc('Masquer l’entrée « Localisé par le TOC » sur les téléphones hostiles') + '</b><small>' + esc('Jamais masquée pour un joueur allié : il voit toujours quand il est localisé ou triangulé.') + '</small></span></label>' +
        '<ul class="toc-ph-rules"><li>' + esc('Un PNJ ou un hostile ne reçoit ni message ni sonnerie.') + '</li><li>' + esc('Une localisation par téléphone toutes les 15 secondes, dix messages par minute et par opérateur, trois triangulations en même temps.') + '</li><li>' + esc('Chaque demande est inscrite au journal : qui, quoi, quel téléphone, quand, résultat.') + '</li></ul>' +
        '<button type="submit" class="toc-btn is-primary toc-ph-go">' + esc('Enregistrer') + '</button></form><p class="toc-drawer-msg" role="status"></p>',
      onSubmit: function (form) {
        host().api('/atak/toc/api/phones/settings', { method: 'POST', body: { enabled: !!form.elements.enabled.checked, hide_hostile_journal: !!form.elements.hide.checked } }).then(function (j) {
          if (!j || !j.ok) { msg(errText(j), true); return; }
          UI.drawer.close();
          host().say(j.enabled ? 'Actions sur les téléphones activées pour la communauté.' : 'Actions sur les téléphones désactivées pour la communauté.');
          load();
        });
      }
    });
  }

  /* ---------- Événements ---------- */
  function onClick(e) {
    var b = e.target.closest('[data-ph]');
    if (!b) return;
    var a = b.getAttribute('data-ph');
    if (a === 'open') openSheet(b.getAttribute('data-ref'));
    else if (a === 'settings') openSettings();
    else if (a === 'chip') { P[b.getAttribute('data-k')] = b.getAttribute('data-v'); host().renderPanel(); }
  }
  function onDrawerClick(e) {
    var body = UI.drawer.body();
    if (!body || !body.contains(e.target) || UI.drawer.current() !== 'phone') return;
    var b = e.target.closest('[data-ph]');
    if (!b) return;
    var a = b.getAttribute('data-ph');
    if (a === 'tab') switchTab(b.getAttribute('data-t'));
    else if (a === 'locate') simple('locate', 'Demande de position envoyée : la réponse du téléphone s’affiche ici.');
    else if (a === 'tri') simple('triangulate', 'Triangulation lancée : les sources écoutent.');
    else if (a === 'stop') simple('ring-stop', 'Arrêt demandé au téléphone.');
    else if (a === 'show') showOnMap(b.getAttribute('data-id'));
    else if (a === 'center') { var p = phoneBy(P.sel); if (p && p.pos && P.tm) { P.tm.centerOn(p.pos, Math.max(P.tm.map.getZoom(), P.tm.map.getMaxZoom() - 3)); UI.drawer.close(); } }
  }
  function onDrawerSubmit(e) {
    var f = e.target;
    if (!f.getAttribute || UI.drawer.current() !== 'phone') return;
    var k = f.getAttribute('data-ph-form');
    if (k === 'notify') { e.preventDefault(); e.stopImmediatePropagation(); doNotify(f); }
    else if (k === 'ring') { e.preventDefault(); e.stopImmediatePropagation(); doRing(f); }
  }

  var wired = false;
  function wire() {
    if (wired) return;
    wired = true;
    document.addEventListener('click', function (e) { var pnl = $('#toc-panel'); if (pnl && pnl.contains(e.target)) onClick(e); else onDrawerClick(e); });
    // Le tiroir de la console reprend « submit » en phase de capture : on passe avant pour les formulaires de la fiche.
    document.addEventListener('submit', onDrawerSubmit, true);
    document.addEventListener('input', function (e) {
      if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-ph-q')) { P.q = e.target.value; var box = $('#toc-ph-listbox'); if (box) box.innerHTML = listHtml(); }
    });
    setInterval(function () { if (P.layer && P.events.some(function (x) { return x.fix; })) drawMap(); }, 15000);
  }

  /* ---------- Points d'entrée pour la console ---------- */
  return {
    panel: function () { wire(); return panel(); },
    attached: function () { wire(); P.active = true; if (mapKeyNow() !== P.mapKey) { P.mapKey = mapKeyNow(); P.data = null; P.events = []; P.sel = null; } if (!P.data) load().then(schedule, schedule); else schedule(); },
    left: function () { P.active = false; schedule(); },
    mapReady: function (tm) { P.tm = tm; P.layer = null; P.mapKey = ''; P.data = null; P.events = []; wire(); load(); },
    reset: function () { P.data = null; P.events = []; P.mapKey = ''; if (P.layer) P.layer.clearLayers(); },
    open: openSheet,
    openByCallsign: function (cs) {
      var l = (P.data && P.data.phones) || [];
      for (var i = 0; i < l.length; i++) if (l[i].callsign.toUpperCase() === String(cs).toUpperCase()) { openSheet(l[i].ref); return; }
      host().say('Ce téléphone n’est pas (encore) dans la liste des téléphones : ouvrez l’onglet Téléphones.', true);
    },
    state: function () { return P; }
  };
})();
