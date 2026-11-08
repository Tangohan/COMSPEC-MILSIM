/*
 * COMSPEC Athena — page /9-line (QR code et bouton de l'app JTAC de l'ATAK natif, ou seconde fenêtre du PC).
 * Mise en page de la maquette « 9-Line - Athena » (views/atak/nine_line_view.php, atak-nine-line.css).
 * - Destinataires : appareils remontés par le jeu (GET /9-line/appareils, toutes les 5 s : pilote, équipage, position,
 *   fraîcheur ; « Joueurs uniquement » masque les appareils sans joueur à bord), indicatif affiché modifiable
 *   (POST /9-line/appareils/indicatif), indicatifs saisis à la main.
 * - Rédaction ligne par ligne : 9-LINE (CAS), 5-LINE ou MEDEVAC, saisie libre (aucune ligne obligatoire,
 *   avertissements seulement), contrôle par ligne, brouillon gardé dans ce navigateur, aide au calcul des lignes 2 et 3,
 *   carte de transmission tenue à jour (prête, transmise), texte radio à copier. Indicatif JTAC (POST /9-line/indicatif).
 * - Historique (GET /9-line/feed, toutes les 5 s) : statut de chaque destinataire (envoyé, reçu, lu, WILCO, UNABLE),
 *   reprise dans le formulaire, suppression (auteur ou responsable), vidage (responsable, mot de confirmation).
 * - Spécialités (GET /9-line/equipages, toutes les 60 s ; champs spec_pilot et spec_jtac des appareils) : qualification ou
 *   fonction de pilote et de JTAC lue dans les dossiers, signalée sur l'équipage, l'auteur et l'indicatif JTAC. Une
 *   détection informe : elle ne bloque jamais un envoi.
 * Textes en français : traduits à l'affichage par i18n_dom.js (lang/en/site_auto.php, js-bundle).
 */
(function () {
  'use strict';

  var cfg = window.NINE_LINE || {};
  var base = String(cfg.base || '').replace(/\/$/, '');
  var locale = cfg.lang === 'fr' ? 'fr-FR' : 'en-GB';
  var REQUIRED = cfg.required || { cas: [4, 5, 6], medevac: [1, 2, 3], fiveline: [2, 3, 4] };
  var DRAFT_KEY = 'athena.nl.draft';
  var PLAYERS_KEY = 'athena.nl.playersOnly';
  var MAX = 9;

  // [libellé, exemple, aide] par ligne (textes de la maquette).
  var DEFS = {
    cas: {
      tag: '9-LINE', type: 'CAS', kick: 'Appui aérien rapproché', title: '9-LINE · Appui aérien rapproché',
      lede: 'Rédigez la demande ligne par ligne, vérifiez la carte de transmission, puis envoyez-la aux pilotes à l’écoute.',
      lines: [
        ['IP / BP', 'ex. IP DELTA ou 0123 4567', 'Point initial ou d’attente, d’où l’appareil commence son attaque.'],
        ['Cap', 'ex. 245° M, déviation droite', 'Cap magnétique de l’IP vers la cible, avec la déviation (gauche / droite).'],
        ['Distance', 'ex. 4.2 NM', 'De l’IP à la cible, en milles nautiques (NM) ou en mètres.'],
        ['Altitude cible', 'ex. 450 ft MSL', 'Altitude de la cible en pieds au-dessus du niveau de la mer (MSL).'],
        ['Description', 'ex. 2 BTR à l’arrêt, infanterie retranchée', 'Nature de la cible : type, nombre, attitude.'],
        ['Position cible', 'ex. 0234 5678', 'Grille de 4 à 10 chiffres (MGRS : zone facultative).'],
        ['Marquage', 'ex. Laser 1688', 'Comment la cible est désignée : laser (code), fumigène, IR, aucun.'],
        ['Amis', 'ex. Sud 600 m', 'Direction et distance des troupes amies les plus proches de la cible.'],
        ['Dégagement', 'ex. Sortie nord-est vers IP DELTA', 'Sortie après l’attaque : direction, altitude, point de regroupement.']
      ]
    },
    fiveline: {
      tag: '5-LINE', type: 'CCA', kick: 'Appui hélicoptère', title: '5-LINE · Appui hélicoptère (CCA)',
      lede: 'Format court pour l’appui des hélicoptères d’attaque : positions, cible et consignes.',
      lines: [
        ['Observateur / ordre', 'ex. TA1, 5-LINE, tir immédiat', 'Indicatif de l’observateur et type de mission.'],
        ['Position amie', 'ex. 0198 5602, fumigène vert', 'Position des amis et leur marquage.'],
        ['Position cible', 'ex. 0234 5678', 'Grille ou direction / distance depuis les amis.'],
        ['Description cible', 'ex. Mitrailleuse dans le bâtiment est', 'Nature de la cible et son marquage.'],
        ['Remarques', 'ex. Tir d’est en ouest, danger close', 'Axe d’attaque, menaces, restrictions.']
      ]
    },
    medevac: {
      tag: 'MEDEVAC', type: 'MEDEVAC', kick: 'Évacuation sanitaire', title: '9-LINE · Évacuation sanitaire',
      lede: 'Demande d’évacuation des blessés : zone de poser, effectifs et sécurité.',
      lines: [
        ['Zone de poser', 'ex. 0156 4420', 'Grille de la zone d’atterrissage.'],
        ['Fréquence / indicatif', 'ex. 41.500 · TA1', 'Fréquence radio et indicatif sur zone.'],
        ['Blessés par urgence', 'ex. A2 · B1', 'A urgent, B prioritaire, C de routine.'],
        ['Équipement spécial', 'ex. Treuil', 'Aucun, treuil, extraction, ventilateur.'],
        ['Couchés / assis', 'ex. L2 · A1', 'Nombre de blessés allongés (L) et assis (A).'],
        ['Sécurité zone', 'ex. E · ennemi possible', 'N pas d’ennemi, P possible, E ennemi, X escorte requise.'],
        ['Marquage', 'ex. Fumigène violet', 'Panneau, pyrotechnie, fumigène, aucun.'],
        ['Nationalité / statut', 'ex. A · militaire allié', 'A allié, B civil allié, C non allié, D civil, E prisonnier.'],
        ['NBC / terrain', 'ex. Pente nord, câbles', 'Contamination NBC ou obstacles de terrain.']
      ]
    }
  };
  var ERR = {
    required: 'Ligne obligatoire.',
    too_long: 'Texte trop long.',
    heading: 'Cap entre 000 et 360.',
    grid: 'Grille de 4 à 10 chiffres (ex. 0234 5678), zone MGRS facultative.'
  };
  var WARN = {
    required: 'Ligne vide : à préciser à la radio si besoin.',
    heading: 'Cap inhabituel (au-delà de 360).',
    grid: 'Grille peu lisible : vérifiez-la.'
  };
  var ACK = { sent: 'ENVOYÉ', received: 'REÇU', read: 'LU', wilco: 'WILCO', unable: 'UNABLE' };
  var SEATS = { driver: 'Pilote', gunner: 'Tireur', commander: 'Chef de bord', cargo: 'Passager' };
  var TYPES = { helicopter: 'Hélicoptère', plane: 'Avion', uav: 'Drone' };
  var RENAME_TIP = 'Changer l’indicatif affiché de cet appareil';

  var $ = function (id) { return document.getElementById(id); };
  var form = $('nl-form');
  var kindInput = $('nl-kind');
  var mapInput = $('nl-map');
  var freeBox = $('nl-free');
  var playersBox = $('nl-players-only');
  var kind = 'cas';
  var selected = {};
  var aircraft = [];
  var items = [];
  var canClear = false;
  var pinned = null;
  var historyMap = 0;
  var renaming = '';
  var sent = false;
  var calcVal = null;
  var csrf = String(cfg.csrf || '');
  var jtac = String(cfg.jtac || '');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  /** Texte de l'utilisateur ou du jeu : jamais passé au traducteur. */
  function raw(s) { return '<span translate="no">' + esc(s) + '</span>'; }
  function fmt(tpl) {
    var args = arguments;
    return String(tpl).replace(/%(\d)/g, function (_m, d) { return String(args[Number(d)]); });
  }
  function when(s) {
    var d = new Date(String(s || '').replace(' ', 'T'));
    return isNaN(d) ? '' : d.toLocaleString(locale, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }
  function ago(sec) {
    sec = Math.max(0, Math.round(sec || 0));
    if (sec < 60) return fmt('il y a %1 s', sec);
    return fmt('il y a %1 min', Math.floor(sec / 60));
  }
  function cleanCs(v) { return String(v || '').replace(/\s+/g, ' ').trim().toUpperCase().slice(0, 32); }
  function lineCount(k) { return (k || kind) === 'fiveline' ? 5 : 9; }
  function field(n) { return $('nl-line' + n); }
  function value(n) { var el = field(n); return el && n <= lineCount() ? el.value.trim() : ''; }
  function isRequired(n) { return (REQUIRED[kind] || []).indexOf(n) !== -1; }
  function isFree() { return !!freeBox.checked; }
  function myCallsign() { return jtac || String(cfg.jtacDefault || 'JTAC'); }
  function kindOf(it) { return it.mission_kind === 'medevac' ? 'medevac' : (it.mission_kind === 'fiveline' ? 'fiveline' : 'cas'); }
  function fit(a) { a.style.height = 'auto'; a.style.height = a.scrollHeight + 2 + 'px'; }

  // ---------- Lignes (9 champs, les 4 derniers masqués en 5-LINE) et remarques ----------
  (function build() {
    var html = '';
    for (var n = 1; n <= MAX; n++) {
      html += '<div class="ln" data-line="' + n + '" id="nl-ln' + n + '">'
        + '<div class="lh"><span class="n">' + n + '</span><b id="nl-label' + n + '"></b><span class="req" id="nl-req' + n + '"></span></div>'
        + '<textarea rows="1" id="nl-line' + n + '" name="line' + n + '" maxlength="255" aria-labelledby="nl-label' + n + '" aria-describedby="nl-help' + n + ' nl-err' + n + '"></textarea>'
        + '<p id="nl-help' + n + '"></p><span class="err" id="nl-err' + n + '" role="alert"></span></div>';
    }
    html += '<div class="ln full" id="nl-remarks-row"><div class="lh"><span class="n">+</span><b id="nl-remarks-label">Remarques et restrictions</b></div>'
      + '<textarea rows="2" id="nl-remarks" name="remarks" maxlength="1000" aria-labelledby="nl-remarks-label" placeholder="ex. FAH 270 ± 20, menace SA-7 au sud, DANGER CLOSE"></textarea></div>';
    $('nl-lines').innerHTML = html;
  })();

  function applyKind(next, keepValues) {
    kind = DEFS[next] ? next : 'cas';
    kindInput.value = kind;
    var def = DEFS[kind];
    var count = lineCount();
    for (var n = 1; n <= MAX; n++) {
      var row = $('nl-ln' + n);
      var on = n <= count;
      row.hidden = !on;
      if (!on) continue;
      var d = def.lines[n - 1];
      $('nl-label' + n).textContent = d[0];
      $('nl-help' + n).textContent = d[2];
      field(n).setAttribute('placeholder', d[1]);
      field(n).setAttribute('maxlength', n === count ? '1000' : '255');
      row.classList.toggle('full', n === count && count % 2 === 1);
      setError(n, '');
    }
    $('nl-remarks-row').hidden = kind === 'fiveline';
    if (kind === 'fiveline' && !value(1)) field(1).value = myCallsign() + ', 5-LINE';
    $('nl-kind-title').textContent = def.title;
    $('nl-top-kind').textContent = def.tag;
    $('nl-kick').textContent = def.kick;
    $('nl-lede').textContent = def.lede;
    Array.prototype.forEach.call(document.querySelectorAll('[data-kind]'), function (b) {
      var on2 = b.getAttribute('data-kind') === kind;
      b.classList.toggle('on', on2);
      b.setAttribute('aria-pressed', on2 ? 'true' : 'false');
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-only]'), function (el) {
      el.hidden = el.getAttribute('data-only') !== kind;
    });
    sent = false;
    if (!keepValues) saveDraft();
    refresh();
    fitAll();
  }
  function fitAll() {
    for (var n = 1; n <= lineCount(); n++) fit(field(n));
    fit($('nl-remarks'));
  }

  // ---------- Contrôle par ligne (saisie libre : avertissements, jamais bloquant) ----------
  function check(n) {
    var v = value(n);
    var count = lineCount();
    if (!v) return isRequired(n) ? 'required' : '';
    if (v.length > (n === count ? 1000 : 255)) return 'too_long';
    if (kind === 'cas' && n === 2) {
      var m = v.match(/^(\d{1,3})/);
      if (m && Number(m[1]) > 360) return 'heading';
    }
    if (kind === 'cas' && n === 6 && !grid(v)) return 'grid';
    return '';
  }
  function blocking(code) { return code === 'too_long' || (code && !isFree()); }
  function setError(n, code) {
    var el = $('nl-err' + n);
    if (!el) return;
    var warn = code && !blocking(code);
    el.textContent = code ? (warn ? (WARN[code] || ERR[code] || code) : (ERR[code] || code)) : '';
    el.classList.toggle('warn', !!warn);
    var f = field(n);
    f.classList.toggle('is-invalid', !!code && !warn);
    f.classList.toggle('is-warn', !!warn);
    f.setAttribute('aria-invalid', code && !warn ? 'true' : 'false');
  }
  function validateAll() {
    var first = 0;
    for (var n = 1; n <= lineCount(); n++) {
      var code = check(n);
      setError(n, code);
      if (blocking(code) && !first) first = n;
    }
    return first;
  }

  // ---------- Brouillon (ce navigateur seulement) ----------
  var saveTimer = null;
  function snapshot() {
    var d = { kind: kind, free: isFree(), lines: {}, remarks: $('nl-remarks').value };
    for (var n = 1; n <= MAX; n++) d.lines[n] = field(n).value;
    return d;
  }
  function restore(d) {
    for (var n = 1; n <= MAX; n++) field(n).value = d.lines && d.lines[n] ? String(d.lines[n]) : '';
    $('nl-remarks').value = d.remarks ? String(d.remarks) : '';
    freeBox.checked = !!d.free;
    return DEFS[d.kind] ? d.kind : 'cas';
  }
  function saveDraft() {
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(snapshot())); } catch (e) {}
    $('nl-tx-save').textContent = 'Enregistrement…';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () { $('nl-tx-save').textContent = 'Brouillon enregistré'; }, 400);
  }
  function loadDraft() {
    var d = null;
    try { d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { d = null; }
    return d && typeof d === 'object' ? restore(d) : 'cas';
  }
  function clearLines() {
    for (var n = 1; n <= MAX; n++) { field(n).value = ''; setError(n, ''); }
    $('nl-remarks').value = '';
    if (kind === 'fiveline') field(1).value = myCallsign() + ', 5-LINE';
  }

  // ---------- Carte de transmission ----------
  function txRows(it, opts) {
    var k = kindOf(it);
    var count = lineCount(k);
    var req = opts.draft && !isFree() ? (REQUIRED[k] || []) : [];
    var b = '';
    if (it.author) b += '<div class="tr"><span class="n">›</span><div><span class="k">De</span><span class="v">' + raw(it.author) + (opts.draft ? '' : authorCheck(it)) + '</span></div></div>';
    for (var n = 1; n <= count; n++) {
      var v = String(it['line' + n] || '').trim();
      var must = req.indexOf(n) !== -1;
      b += '<div class="tr"><span class="n">' + n + '</span><div><span class="k">' + esc(DEFS[k].lines[n - 1][0]) + '</span>'
        + '<span class="v' + (v ? '' : (must ? ' miss' : ' e')) + '">' + (v ? raw(v) : esc(must ? 'À saisir' : '—')) + '</span></div></div>';
    }
    if (k !== 'fiveline' && it.remarks) {
      b += '<div class="tr"><span class="n">+</span><div><span class="k">Remarques</span><span class="v">' + raw(it.remarks) + '</span></div></div>';
    }
    return b;
  }
  function acks(list) {
    if (!list || !list.length) return '';
    return '<div class="acks">' + list.map(function (r) {
      var st = ACK[r.status] ? r.status : 'sent';
      var tip = r.ack_by ? fmt('par %1', r.ack_by) : '';
      return '<span class="ack ' + st + '"' + (tip ? ' title="' + esc(tip) + '"' : '') + '>' + raw(r.label || r.callsign) + ' <b>' + esc(ACK[st]) + '</b></span>';
    }).join('') + '</div>';
  }
  function destList() { return Object.keys(selected); }
  function destLabel() { return destList().map(function (cs) { return selected[cs].label || cs; }).join(', '); }
  function counts() {
    var count = lineCount();
    var req = REQUIRED[kind] || [];
    var filled = 0;
    var reqOk = 0;
    var miss = [];
    for (var n = 1; n <= count; n++) {
      var v = value(n);
      if (v) filled++;
      if (req.indexOf(n) !== -1) { if (v) reqOk++; else miss.push(n); }
    }
    return { filled: filled, count: count, req: req.length, reqOk: reqOk, miss: miss };
  }
  function refresh() {
    var def = DEFS[kind];
    var c = counts();
    var free = isFree();
    var ready = free ? c.filled > 0 : c.reqOk === c.req;
    $('nl-rail-kind').textContent = def.type;
    $('nl-rail-dest').textContent = String(destList().length);
    $('nl-rail-lines').innerHTML = c.filled + '<small> / ' + c.count + '</small>';
    $('nl-progress-text').innerHTML = free ? '—' : c.reqOk + '<small> / ' + c.req + '</small>';
    $('nl-req-stat').classList.toggle('ok', !free && c.reqOk === c.req);
    for (var n = 1; n <= c.count; n++) {
      var tag = $('nl-req' + n);
      var on = isRequired(n) && !free;
      tag.hidden = !on;
      if (on) {
        var ok = !!value(n);
        tag.classList.toggle('done', ok);
        tag.textContent = ok ? 'Saisi' : 'Obligatoire';
      }
    }
    var missing = c.req - c.reqOk;
    $('nl-draft-state').textContent = !c.filled ? (free ? 'Brouillon vide · saisie libre, aucune ligne obligatoire' : 'Brouillon vide · lignes obligatoires marquées')
      : (free || !missing ? fmt('%1 ligne(s) saisie(s) · prête à transmettre', c.filled) : fmt('%1 ligne(s) saisie(s) · %2 obligatoire(s) manquante(s)', c.filled, missing));
    var draft = { mission_kind: kind, author: myCallsign(), remarks: $('nl-remarks').value.trim() };
    for (var i = 1; i <= c.count; i++) draft['line' + i] = value(i);
    $('nl-tx-title').textContent = def.tag + ' · ' + def.type;
    var pill = $('nl-tx-state');
    pill.className = 'pill ' + (sent ? 'snt' : (ready ? 'rdy' : 'drf'));
    pill.innerHTML = '<i></i>' + esc(sent ? 'Transmis' : (ready ? 'Prêt' : 'Brouillon'));
    $('nl-preview').innerHTML = txRows(draft, { draft: true });
    $('nl-tx-to').innerHTML = destList().length ? esc('À') + ' ' + raw(destLabel()) : esc('Tous les appareils à l’écoute');
    $('nl-qc').innerHTML = !free && c.miss.length
      ? '<span class="lbl">' + esc(fmt('%1 à corriger', c.miss.length)) + '</span>' + c.miss.map(function (n2) {
        return '<button type="button" data-go="' + n2 + '"><svg class="i"><use href="#nl-s-alert"></use></svg>' + esc(fmt('Ligne %1', n2)) + ' · ' + esc(def.lines[n2 - 1][0]) + '</button>';
      }).join('') : '';
    $('nl-submit').disabled = !ready || sent;
    renderDest();
    calc();
  }
  $('nl-qc').addEventListener('click', function (e) {
    var b = e.target.closest('[data-go]');
    if (b) field(Number(b.getAttribute('data-go'))).focus();
  });

  // ---------- Destinataires ----------
  var SPEC_KIND = { pilot: 'Pilote', jtac: 'JTAC' };
  var SPEC_STATUS = { qualified: 'qualifié', role: 'fonction', training: 'en formation', expired: 'qualif expirée' };
  /** Pastille d'une détection : l'infobulle dit d'où elle vient. */
  function specTag(s) {
    if (!s) return '';
    var tip = esc(s.source === 'fonction' ? 'Fonction au dossier' : 'Qualification au dossier') + ' : ' + esc(s.label || '');
    if (s.expires_at) tip += ' · ' + esc(fmt('valable jusqu’au %1', String(s.expires_at).split('-').reverse().join('/')));
    if (s.hours) tip += ' · ' + esc(fmt('%1 h de vol', s.hours));
    return '<span class="tag sp ' + esc(s.status) + '" title="' + tip + '">' + esc(SPEC_KIND[s.kind] || '') + ' · ' + esc(SPEC_STATUS[s.status] || '') + '</span>';
  }
  /** Sur un siège : la spécialité attendue, ou le constat qu'un membre connu n'a rien au dossier. */
  function seatCheck(p, seat) {
    if (!p || (!p.player && !p.linked)) return '';
    if (!p.linked) return '<span class="tag sp none" title="' + esc('Ce nom ne correspond à aucun membre de la communauté') + '">' + esc('Non reconnu') + '</span>';
    if (seat === 'driver') {
      return p.spec_pilot ? specTag(p.spec_pilot) : '<span class="tag sp none" title="' + esc('Ni qualification ni fonction de pilote au dossier') + '">' + esc('Pilote non qualifié') + '</span>';
    }
    return (p.spec_pilot ? specTag(p.spec_pilot) : '') + (p.spec_jtac ? specTag(p.spec_jtac) : '');
  }
  function person(p, crewKnown, seat) {
    if (!p) return '';
    var out = raw(p.label || p.game_name || '');
    if (p.name) out += ' ' + raw(p.name);
    if (!p.player && crewKnown) out += ' <span class="tag ia">IA</span>';
    var check = seatCheck(p, seat);
    return check ? out + ' ' + check : out;
  }
  function visibleAircraft() {
    return playersBox.checked ? aircraft.filter(function (a) { return a.has_player || selected[a.callsign]; }) : aircraft;
  }
  function renderAircraft() {
    var box = $('nl-aircraft');
    var list = visibleAircraft();
    var hidden = aircraft.length - list.length;
    $('nl-ai-hidden').textContent = hidden > 0 ? fmt('%1 appareil(s) sans joueur masqué(s)', hidden) : (playersBox.checked ? '' : 'Affichage des appareils IA');
    if (!aircraft.length) {
      $('nl-aircraft-count').textContent = 'Aucun appareil';
      $('nl-aircraft-fresh').textContent = '—';
      box.innerHTML = '<div class="empty"><b>Aucun appareil remonté par le jeu</b><span>Le mod doit être connecté à Athena (COMSPEC Link) et un aéronef doit avoir quelqu’un à bord. Vous pouvez quand même saisir un indicatif à la main ci-dessous.</span></div>';
      return;
    }
    var liveN = aircraft.filter(function (a) { return a.live; }).length;
    $('nl-aircraft-count').textContent = fmt('%1 appareil(s), %2 en direct', aircraft.length, liveN);
    $('nl-aircraft-fresh').textContent = fmt('Dernière remontée %1', ago(Math.min.apply(null, aircraft.map(function (a) { return a.age_s; }))));
    if (!list.length) {
      box.innerHTML = '<div class="empty"><b>Aucun appareil avec un joueur à bord</b><span>Décochez « Joueurs uniquement » pour voir les appareils de l’IA.</span></div>';
      return;
    }
    box.innerHTML = '<div class="ac">' + list.map(function (a) {
      var on = !!selected[a.callsign];
      var label = a.label || a.callsign;
      var crew = (a.crew || []).filter(function (c) { return c.seat !== 'driver'; });
      var facts = [];
      if (a.grid) facts.push(a.grid);
      if (a.alt_ft !== null && a.alt_ft !== undefined) facts.push(a.alt_ft + ' ft');
      if (a.heading !== null && a.heading !== undefined) facts.push(('00' + a.heading).slice(-3) + '°');
      if (a.fuel_pct !== null && a.fuel_pct !== undefined) facts.push(a.fuel_pct + ' %');
      if (a.laser) facts.push('LSR ' + a.laser);
      var state = !a.live ? '<span class="st lost">Signal perdu</span>'
        : (String(a.status).toUpperCase() === 'IN-FLIGHT' ? '<span class="st air">En vol</span>' : '<span class="st">Au sol</span>');
      var who = [];
      if (a.pilot) who.push(esc('Pilote') + ' ' + person(a.pilot, a.crew_count > 0, 'driver'));
      crew.forEach(function (c) { who.push(esc(SEATS[c.seat] || SEATS.cargo) + ' ' + person(c, true, c.seat)); });
      var rename = renaming === a.callsign
        ? '<span class="rn"><input class="inp" data-rename-input="' + esc(a.callsign) + '" maxlength="32" list="nl-air-suggest" value="' + esc(label) + '">'
          + '<button type="button" class="btn2" data-rename-save="' + esc(a.callsign) + '">OK</button>'
          + '<button type="button" class="btn2" data-rename-cancel="1" aria-label="Annuler"><svg class="i"><use href="#nl-s-x"></use></svg></button></span>'
        : '';
      return '<label class="acb' + (on ? ' on' : '') + (a.live ? '' : ' stale') + '">'
        + '<input type="checkbox" class="nl-ac-check" value="' + esc(a.callsign) + '" data-map="' + a.map_id + '"' + (on ? ' checked' : '') + '>'
        + '<span class="d' + (a.has_player ? ' pl' : '') + '"></span>'
        + '<span class="bd"><span class="top"><span class="cs">' + raw(label) + '</span>'
        + (label !== a.callsign ? '<span class="orig" title="Indicatif remonté par le jeu">' + raw(a.callsign) + '</span>' : '')
        + state + '<button type="button" class="ren" data-rename="' + esc(a.callsign) + '" title="' + esc(RENAME_TIP) + '">' + esc('Indicatif') + '</button></span>'
        + '<small>' + raw(a.model || '') + (TYPES[a.aircraft_type] ? ' · ' + esc(TYPES[a.aircraft_type]) : '') + (a.map_name ? ' · ' + raw(a.map_name) : '') + '</small>'
        + (who.length ? '<small>' + who.join(' · ') + '</small>' : '')
        + (facts.length ? '<span class="fx">' + raw(facts.join(' · ')) + ' · ' + esc(ago(a.age_s)) + '</span>' : '')
        + rename + '</span></label>';
    }).join('') + '</div>';
  }
  /** Indicatifs saisis à la main (ou appareils plus remontés) : pastilles retirables ; ceux de la liste sont cochés. */
  function renderDest() {
    var known = {};
    aircraft.forEach(function (a) { known[a.callsign] = true; });
    $('nl-dest').innerHTML = destList().filter(function (c) { return !known[c] || !visibleAircraft().some(function (a) { return a.callsign === c; }); }).map(function (c) {
      return '<span class="chip">' + raw(selected[c].label || c) + '<button type="button" data-unselect="' + esc(c) + '" aria-label="' + esc('Retirer ce destinataire') + '"><svg class="i"><use href="#nl-s-x"></use></svg></button></span>';
    }).join('');
  }
  function addManual() {
    var input = $('nl-manual-cs');
    var cs = cleanCs(input.value);
    if (!cs) { input.focus(); return; }
    selected[cs] = { label: cs, manual: true, map: Number(mapInput.value) || 1 };
    input.value = '';
    sent = false;
    renderAircraft();
    refresh();
  }
  $('nl-manual-add').addEventListener('click', addManual);
  $('nl-manual-cs').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addManual(); } });
  $('nl-aircraft').addEventListener('change', function (e) {
    var cb = e.target.closest('.nl-ac-check');
    if (!cb) return;
    var a = aircraft.filter(function (x) { return x.callsign === cb.value; })[0] || {};
    if (cb.checked) selected[cb.value] = { label: a.label || cb.value, map: Number(cb.getAttribute('data-map')) || 1 };
    else delete selected[cb.value];
    var maps = destList().map(function (k) { return selected[k].map; }).filter(Boolean);
    if (maps.length) mapInput.value = String(maps[0]);
    sent = false;
    renderAircraft();
    refresh();
  });
  $('nl-aircraft').addEventListener('click', function (e) {
    var t = e.target;
    if (t.closest('.rn input')) { e.preventDefault(); return; }
    var r = t.closest('[data-rename]');
    var save = t.closest('[data-rename-save]');
    var cancel = t.closest('[data-rename-cancel]');
    if (!r && !save && !cancel) return;
    e.preventDefault();
    if (cancel) { renaming = ''; renderAircraft(); return; }
    if (r) {
      renaming = r.getAttribute('data-rename');
      renderAircraft();
      var inp = document.querySelector('[data-rename-input]');
      if (inp) { inp.focus(); inp.select(); }
      return;
    }
    saveRename(save.getAttribute('data-rename-save'));
  });
  $('nl-aircraft').addEventListener('keydown', function (e) {
    var inp = e.target.closest('[data-rename-input]');
    if (!inp) return;
    if (e.key === 'Enter') { e.preventDefault(); saveRename(inp.getAttribute('data-rename-input')); }
    if (e.key === 'Escape') { renaming = ''; renderAircraft(); }
  });
  function saveRename(cs) {
    var inp = document.querySelector('[data-rename-input]');
    var fd = new FormData();
    fd.append('callsign', cs);
    fd.append('label', cleanCs(inp ? inp.value : ''));
    post('/9-line/appareils/indicatif', fd).then(function (r) {
      if (!r.ok) { toast(r.error || 'Indicatif non enregistré.', null, true); return; }
      renaming = '';
      aircraft.forEach(function (a) { if (a.callsign === cs) a.label = r.label || cs; });
      if (selected[cs]) selected[cs].label = r.label || cs;
      toast(fmt('Indicatif affiché : %1 (remonté par le jeu : %2).', r.label || cs, cs));
      renderAircraft();
      refresh();
    });
  }
  $('nl-dest').addEventListener('click', function (e) {
    var b = e.target.closest('[data-unselect]');
    if (!b) return;
    delete selected[b.getAttribute('data-unselect')];
    renderAircraft();
    refresh();
  });
  playersBox.addEventListener('change', function () {
    try { localStorage.setItem(PLAYERS_KEY, playersBox.checked ? '1' : '0'); } catch (e) {}
    renderAircraft();
    renderDest();
  });

  // ---------- Pilotes et JTAC d'après les dossiers ----------
  function renderMe(specs) {
    var box = $('nl-me-spec');
    if (!box) return;
    var jt = specs && specs.jtac;
    box.innerHTML = jt
      ? esc('Votre dossier') + ' ' + specTag(jt)
      : '<span class="tag sp none">' + esc('Aucune qualification JTAC au dossier') + '</span>';
  }
  function renderRoster(list) {
    var box = $('nl-roster');
    var pilots = list.filter(function (m) { return m.specs.some(function (s) { return s.kind === 'pilot'; }); }).length;
    var jtacs = list.filter(function (m) { return m.specs.some(function (s) { return s.kind === 'jtac'; }); }).length;
    $('nl-roster-count').textContent = fmt('%1 pilote(s), %2 JTAC', pilots, jtacs);
    if (!list.length) {
      box.innerHTML = '<div class="empty"><b>Personne de détecté</b><span>Attribuez une qualification ou une fonction de pilote ou de JTAC sur les fiches des membres : elle apparaîtra ici.</span></div>';
      return;
    }
    box.innerHTML = '<ul class="rlist">' + list.map(function (m) {
      var cs = cleanCs(m.callsign);
      var isPilot = m.specs.some(function (s) { return s.kind === 'pilot'; });
      var add = cs && isPilot ? '<button type="button" class="btn2 sm" data-roster-add="' + esc(cs) + '">' + esc(selected[cs] ? 'Ajouté' : 'Destinataire') + '</button>' : '';
      return '<li><span class="rn2">' + raw(m.label) + (m.name ? ' <small>' + raw(m.name) + '</small>' : '') + '</span>'
        + '<span class="rs">' + m.specs.map(specTag).join(' ') + '</span>' + add + '</li>';
    }).join('') + '</ul>';
  }
  $('nl-roster').addEventListener('click', function (e) {
    var b = e.target.closest('[data-roster-add]');
    if (!b) return;
    var cs = b.getAttribute('data-roster-add');
    if (selected[cs]) delete selected[cs];
    else selected[cs] = { label: cs, manual: true, map: Number(mapInput.value) || 1 };
    b.textContent = selected[cs] ? 'Ajouté' : 'Destinataire';
    sent = false;
    renderAircraft();
    refresh();
  });
  function pollRoster() {
    return getJson('/9-line/equipages').then(function (j) {
      renderRoster((j && j.items) || []);
      renderMe((j && j.me) || {});
    }).catch(function () {});
  }

  // ---------- Indicatif JTAC ----------
  function saveJtac() {
    var inp = $('nl-jtac-cs');
    var fd = new FormData();
    fd.append('callsign', cleanCs(inp.value));
    post('/9-line/indicatif', fd).then(function (r) {
      if (!r.ok) { toast(r.error || 'Indicatif non enregistré.', null, true); return; }
      jtac = r.callsign || '';
      inp.value = jtac;
      toast(jtac ? fmt('Indicatif JTAC : %1.', jtac) : fmt('Indicatif JTAC : celui d’Athena (%1).', r.author || myCallsign()));
      if (kind === 'fiveline' && /, 5-LINE$/.test(value(1))) field(1).value = myCallsign() + ', 5-LINE';
      refresh();
    });
  }
  $('nl-jtac-save').addEventListener('click', saveJtac);
  $('nl-jtac-cs').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); saveJtac(); } });

  // ---------- Historique ----------
  function summary(it) {
    var k = kindOf(it);
    if (k === 'fiveline') return (it.line3 || '?') + ' · ' + (it.line4 || '');
    if (k === 'medevac') return (it.line1 || '?') + ' · ' + (it.line3 || '');
    return (it.line1 || 'IP') + ' → ' + (it.line6 || '?');
  }
  function ackShort(list) {
    if (!list || !list.length) return '';
    var n = { wilco: 0, unable: 0, read: 0, received: 0, sent: 0 };
    list.forEach(function (r) { n[r.status] = (n[r.status] || 0) + 1; });
    var parts = [];
    if (n.wilco) parts.push(n.wilco + ' WILCO');
    if (n.unable) parts.push(n.unable + ' UNABLE');
    if (n.read) parts.push(fmt('%1 lu(s)', n.read));
    if (n.received) parts.push(fmt('%1 reçu(s)', n.received));
    if (n.sent) parts.push(fmt('%1 en attente', n.sent));
    return parts.join(' · ');
  }
  function authorCheck(it) {
    var c = it.author_check;
    if (!c || !c.linked) return '';
    return ' ' + (c.spec ? specTag(c.spec) : '<span class="tag sp none" title="' + esc('Ni qualification ni fonction de JTAC au dossier') + '">' + esc('Sans qualif JTAC') + '</span>');
  }
  function renderHistory() {
    var list = $('nl-history');
    $('nl-history-count').textContent = items.length ? fmt('%1 demande(s)', items.length) : 'Aucune demande';
    $('nl-clear').hidden = !canClear || !items.length;
    if (!items.length) {
      $('nl-selected').innerHTML = '<div class="empty"><b>Aucune demande pour l’instant</b><span>Rédigez-en une ici, ou dans l’app JTAC du jeu : elle apparaît en quelques secondes, avec les accusés des pilotes.</span></div>';
      list.innerHTML = '';
      return;
    }
    var cur = items.filter(function (it) { return it.id === pinned; })[0] || items[0];
    var k = kindOf(cur);
    $('nl-selected').innerHTML = '<div class="tx"><div class="txh"><h3 translate="no">' + esc(DEFS[k].tag + ' · ' + DEFS[k].type) + '</h3>'
      + '<span class="pill ' + (cur.id === items[0].id ? 'rdy' : '') + '"><i></i>' + esc(cur.id === items[0].id ? 'Dernier reçu' : 'Sélectionné') + '</span></div>'
      + '<div class="txb">' + txRows(cur, {}) + '</div>' + acks(cur.recipients)
      + '<div class="txf"><span>' + (cur.assigned_aircraft ? esc('À') + ' ' + raw(cur.assigned_aircraft) : esc('Tous les appareils à l’écoute')) + '</span>'
      + '<span>' + (cur.map_name ? raw(cur.map_name) + ' · ' : '') + raw(when(cur.updated_at || cur.created_at)) + '</span></div></div>';
    list.innerHTML = items.map(function (it) {
      var ak = ackShort(it.recipients);
      return '<li class="' + (it.id === cur.id ? 'on' : '') + '">'
        + '<button type="button" class="hm" data-show="' + it.id + '">'
        + '<span class="hk" translate="no">' + esc(DEFS[kindOf(it)].tag) + '</span>'
        + '<span class="hw">' + raw(summary(it)) + '</span>'
        + '<small>' + raw(it.author || 'JTAC') + authorCheck(it) + ' · ' + raw(when(it.updated_at || it.created_at))
        + (it.assigned_aircraft ? ' · ' + raw(it.assigned_aircraft) : '') + (ak ? ' · ' + esc(ak) : '') + '</small></button>'
        + '<span class="ha"><button type="button" class="btn2 sm" data-reuse="' + it.id + '">Reprendre</button>'
        + (it.can_delete ? '<button type="button" class="btn2 sm dng" data-delete="' + it.id + '">Supprimer</button>' : '')
        + '</span></li>';
    }).join('');
  }
  $('nl-history').addEventListener('click', function (e) {
    var show = e.target.closest('[data-show]');
    var reuse = e.target.closest('[data-reuse]');
    var del = e.target.closest('[data-delete]');
    var btn = show || reuse || del;
    if (!btn) return;
    var id = Number(btn.getAttribute(show ? 'data-show' : (reuse ? 'data-reuse' : 'data-delete')));
    var it = items.filter(function (x) { return x.id === id; })[0];
    if (!it) return;
    if (show) {
      pinned = (items[0] && items[0].id === id) ? null : id;
      renderHistory();
      $('nl-selected').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else if (reuse) {
      applyKind(kindOf(it), true);
      for (var n = 1; n <= MAX; n++) field(n).value = n <= lineCount() ? (it['line' + n] || '') : '';
      $('nl-remarks').value = it.remarks || '';
      sent = false;
      saveDraft();
      refresh();
      fitAll();
      toast('Lignes reprises dans le formulaire : vérifiez-les avant de transmettre.');
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else if (del) {
      confirmBox('Supprimer cette demande ?', fmt('%1, par %2. La suppression est définitive, pour tous les membres et dans le jeu.', summary(it), it.author || 'JTAC'), false, function () {
        post('/9-line/' + id + '/supprimer', new FormData()).then(function (r) {
          if (!r.ok) { toast(r.error || 'Suppression impossible.', null, true); return; }
          if (pinned === id) pinned = null;
          toast('Demande supprimée.');
          pollFeed();
        });
      });
    }
  });
  $('nl-history-map').addEventListener('change', function () {
    historyMap = Number(this.value) || 0;
    pinned = null;
    pollFeed();
  });
  $('nl-clear').addEventListener('click', function () {
    var sel = $('nl-history-map');
    var scope = historyMap ? fmt('de la carte %1', sel.options[sel.selectedIndex].text) : 'de toutes les cartes';
    confirmBox('Vider l’historique ?', fmt('Toutes les demandes %1 seront supprimées définitivement, pour tous les membres et dans le jeu.', scope), true, function (word) {
      var fd = new FormData();
      fd.append('confirm', word);
      fd.append('map_id', String(historyMap));
      post('/9-line/vider', fd).then(function (r) {
        if (!r.ok) { toast(r.error || 'Suppression impossible.', null, true); return; }
        pinned = null;
        toast(fmt('%1 demande(s) supprimée(s).', r.deleted || 0));
        pollFeed();
      });
    });
  });

  // ---------- Confirmation ----------
  var dialog = $('nl-dialog');
  var pendingConfirm = null;
  function confirmBox(title, text, needWord, onOk) {
    $('nl-dialog-title').textContent = title;
    $('nl-dialog-text').textContent = text;
    $('nl-dialog-word').hidden = !needWord;
    $('nl-dialog-input').value = '';
    $('nl-dialog-ok').textContent = needWord ? 'Tout supprimer' : 'Supprimer';
    pendingConfirm = { needWord: needWord, onOk: onOk };
    if (dialog && typeof dialog.showModal === 'function') {
      dialog.showModal();
      if (needWord) $('nl-dialog-input').focus();
    } else if (window.confirm(title + '\n' + text)) {
      onOk(needWord ? (window.prompt('Tapez SUPPRIMER pour confirmer') || '') : '');
    }
  }
  if (dialog) {
    dialog.addEventListener('close', function () {
      var p = pendingConfirm;
      pendingConfirm = null;
      if (!p || dialog.returnValue !== 'ok') return;
      p.onOk($('nl-dialog-input').value.trim());
    });
    $('nl-dialog-form').addEventListener('submit', function (e) {
      var sub = e.submitter;
      if (sub && sub.value === 'ok' && pendingConfirm && pendingConfirm.needWord) {
        var w = $('nl-dialog-input').value.trim().toUpperCase();
        if (w !== 'SUPPRIMER' && w !== 'DELETE') {
          e.preventDefault();
          $('nl-dialog-input').classList.add('is-invalid');
          $('nl-dialog-input').focus();
        }
      }
    });
  }

  // ---------- Notification (en bas, avec Annuler) ----------
  var toastTimer = null;
  var undo = null;
  function toast(text, onUndo, isErr) {
    $('nl-toast-t').textContent = text;
    undo = onUndo || null;
    $('nl-toast-u').hidden = !undo;
    $('nl-toast').classList.toggle('err', !!isErr);
    $('nl-toast').classList.add('on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { $('nl-toast').classList.remove('on'); }, 5000);
  }
  $('nl-toast-u').addEventListener('click', function () {
    if (undo) { undo(); undo = null; }
    $('nl-toast').classList.remove('on');
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') $('nl-toast').classList.remove('on'); });

  // ---------- Réseau ----------
  function post(path, fd) {
    fd.append('_csrf_token', csrf);
    return fetch(base + path, {
      method: 'POST', credentials: 'include', body: fd,
      headers: { Accept: 'application/json', 'X-CSRF-Token': csrf }
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        j = j || {};
        j.ok = r.ok && j.ok !== false;
        j.status = r.status;
        return j;
      });
    }).catch(function () { return { ok: false, error: 'Connexion au site impossible. Réessayez dans un instant.' }; });
  }
  function getJson(path) {
    return fetch(base + path, { credentials: 'include', headers: { Accept: 'application/json' } }).then(function (r) {
      if (r.status === 401 || r.redirected) throw new Error('login');
      return r.json();
    });
  }
  function online(ok) {
    $('nl-live').textContent = ok ? fmt('À jour · %1', new Date().toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', second: '2-digit' })) : 'hors ligne';
    $('nl-live').parentNode.classList.toggle('off', !ok);
  }
  function pollFeed() {
    return getJson('/9-line/feed' + (historyMap ? '?carte=' + historyMap : '')).then(function (j) {
      items = (j && j.items) || [];
      items.forEach(function (it) { it.id = Number(it.id); });
      canClear = !!(j && j.can_clear);
      var sel = $('nl-history-map');
      var maps = (j && j.maps) || [];
      var opts = '<option value="0">Toutes les cartes</option>' + maps.map(function (m) {
        return '<option value="' + m.id + '"' + (m.id === historyMap ? ' selected' : '') + ' translate="no">' + esc(m.label) + ' (' + m.count + ')</option>';
      }).join('');
      if (sel.getAttribute('data-sig') !== opts) { sel.innerHTML = opts; sel.setAttribute('data-sig', opts); }
      renderHistory();
      online(true);
    }).catch(function (err) {
      online(false);
      if (err && err.message === 'login') $('nl-selected').innerHTML = '<p class="hint">Connectez-vous à Athena pour voir les 9-LINE.</p>';
    });
  }
  function pollAircraft() {
    return getJson('/9-line/appareils').then(function (j) {
      aircraft = (j && j.items) || [];
      if (!renaming) renderAircraft();
      renderDest();
    }).catch(function () {});
  }

  // ---------- Envoi, texte radio, réinitialisation ----------
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var bad = validateAll();
    if (bad) {
      toast('Vérifiez les lignes signalées.', null, true);
      field(bad).focus();
      return;
    }
    var fd = new FormData(form);
    for (var n = lineCount() + 1; n <= MAX; n++) fd.delete('line' + n);
    if (!isFree()) fd.delete('free');
    if (kind === 'fiveline') fd.delete('remarks');
    destList().forEach(function (cs) { fd.append('aircraft[]', cs); });
    var btn = $('nl-submit');
    btn.disabled = true;
    var to = destList().length;
    post('/9-line', fd).then(function (r) {
      if (!r.ok) {
        btn.disabled = false;
        if (r.errors) Object.keys(r.errors).forEach(function (k) { setError(Number(k), r.errors[k]); });
        toast(r.error || 'Envoi impossible.', null, true);
        return;
      }
      sent = true;
      pinned = null;
      refresh();
      toast(to ? fmt('%1 transmis à %2 appareil(s) : le pilote le reçoit dans BRIEFS AIR, son accusé s’affiche dans l’historique.', DEFS[kind].tag, to) : fmt('%1 transmis à tous les appareils à l’écoute.', DEFS[kind].tag));
      pollFeed();
    });
  });
  function radioText() {
    var def = DEFS[kind];
    var out = [def.tag + ' ' + def.type + ' · DE ' + myCallsign()];
    for (var n = 1; n <= lineCount(); n++) out.push(n + '. ' + def.lines[n - 1][0].toUpperCase() + ' : ' + (value(n) || '—'));
    var rem = kind !== 'fiveline' ? $('nl-remarks').value.trim() : '';
    if (rem) out.push('REMARQUES : ' + rem);
    return out.join('\n');
  }
  $('nl-copy').addEventListener('click', function () {
    var txt = radioText();
    var done = function () { toast('Texte radio copié'); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(txt).then(done, function () { toast('Copie impossible dans ce navigateur.', null, true); });
    else toast('Copie impossible dans ce navigateur.', null, true);
  });
  $('nl-reset').addEventListener('click', function () {
    var prev = snapshot();
    clearLines();
    sent = false;
    saveDraft();
    refresh();
    fitAll();
    toast(fmt('%1 réinitialisé', DEFS[kind].tag), function () {
      applyKind(restore(prev), true);
      saveDraft();
    });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-kind]'), function (b) {
    b.addEventListener('click', function () { applyKind(b.getAttribute('data-kind'), false); });
  });
  freeBox.addEventListener('change', function () { applyKind(kind, false); validateAll(); });
  form.addEventListener('input', function (e) {
    if (e.target.tagName === 'TEXTAREA') fit(e.target);
    var row = e.target.closest('.ln[data-line]');
    if (row) {
      var n = Number(row.getAttribute('data-line'));
      if ($('nl-err' + n).textContent) setError(n, check(n));
    }
    sent = false;
    saveDraft();
    refresh();
  });
  form.addEventListener('focusout', function (e) {
    var row = e.target.closest && e.target.closest('.ln[data-line]');
    if (row && e.target.value.trim()) {
      var n = Number(row.getAttribute('data-line'));
      setError(n, check(n));
    }
  });

  // ---------- Aide au calcul : grille → mètres, même lecture que la carte en jeu ----------
  function grid(txt) {
    var t = String(txt || '').toUpperCase().trim();
    var m = t.match(/[A-Z](?!.*[A-Z])/);
    if (m) t = t.slice(t.lastIndexOf(m[0]) + 1);
    var g = t.match(/[0-9]+/g) || [];
    var s;
    if (g.length === 2) {
      if (g[0].length !== g[1].length) return null;
      s = g[0] + g[1];
    } else {
      s = g.join('');
    }
    if (s.length < 4 || s.length > 10 || s.length % 2) return null;
    var n = s.length / 2;
    var cell = Math.pow(10, 5 - n);
    return { x: Number(s.slice(0, n)) * cell + cell / 2, y: Number(s.slice(n)) * cell + cell / 2 };
  }
  function pad3(v) { return ('00' + Math.round(v) % 360).slice(-3); }
  function calc() {
    var a = kind === 'cas' ? grid(value(1)) : null;
    var b = kind === 'cas' ? grid(value(6)) : null;
    calcVal = null;
    if (a && b) {
      var dx = b.x - a.x;
      var dy = b.y - a.y;
      var d = Math.sqrt(dx * dx + dy * dy);
      calcVal = { brg: pad3((Math.atan2(dx, dy) * 180 / Math.PI + 360) % 360), nm: (d / 1852).toFixed(1), m: Math.round(d) };
    }
    $('nl-c-brg').textContent = calcVal ? calcVal.brg + '°' : '—';
    $('nl-c-nm').textContent = calcVal ? calcVal.nm + ' NM' : '—';
    $('nl-c-m').textContent = calcVal ? calcVal.m.toLocaleString(locale) + ' m' : '—';
    ['nl-c-brg', 'nl-c-nm', 'nl-c-m'].forEach(function (id) { $(id).classList.toggle('na', !calcVal); });
    $('nl-calc-btn').disabled = !calcVal;
  }
  $('nl-calc-btn').addEventListener('click', function () {
    if (!calcVal) return;
    field(2).value = calcVal.brg + '° M';
    field(3).value = calcVal.nm + ' NM';
    setError(2, check(2));
    setError(3, check(3));
    sent = false;
    saveDraft();
    refresh();
    fitAll();
    toast('Lignes 2 et 3 renseignées');
  });

  // ---------- Démarrage ----------
  try { playersBox.checked = localStorage.getItem(PLAYERS_KEY) !== '0'; } catch (e) {}
  applyKind(loadDraft(), true);
  $('nl-tx-save').textContent = '—';
  renderAircraft();
  pollFeed();
  pollAircraft();
  renderMe(cfg.mySpecs || {});
  pollRoster();
  setInterval(function () { if (!document.hidden) pollRoster(); }, 60000);
  setInterval(function () { if (!document.hidden) pollFeed(); }, 5000);
  setInterval(function () { if (!document.hidden) pollAircraft(); }, 5000);
})();
