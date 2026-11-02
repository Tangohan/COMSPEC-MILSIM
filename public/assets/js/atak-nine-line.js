/*
 * COMSPEC Athena — page /9-line (QR code et bouton de l'app JTAC de l'ATAK natif, ou seconde fenêtre du PC).
 * - Destinataires : appareils remontés par le jeu (GET /9-line/appareils, toutes les 5 s : pilote, équipage, position,
 *   fraîcheur ; « Joueurs uniquement » masque les appareils sans joueur à bord), indicatif affiché modifiable
 *   (POST /9-line/appareils/indicatif), indicatifs saisis à la main.
 * - Formulaire ligne par ligne : 9-LINE (CAS), 5-LINE (ATP 3-09.32) ou MEDEVAC, saisie libre (aucune ligne obligatoire,
 *   avertissements seulement), aides et contrôle par ligne, brouillon gardé dans ce navigateur, aide au calcul des
 *   lignes 2 et 3, carte de transmission tenue à jour. Indicatif JTAC du membre (POST /9-line/indicatif).
 * - Historique (GET /9-line/feed, toutes les 5 s) : statut de chaque destinataire (envoyé, reçu, lu, WILCO, UNABLE),
 *   reprise dans le formulaire, suppression (auteur ou responsable), vidage (responsable, mot de confirmation).
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

  var DEFS = {
    cas: {
      tag: '9-LINE',
      rail: 'CAS',
      title: '9-LINE · Appui aérien rapproché (CAS)',
      lines: [
        ['IP / BP', 'Point initial ou d’attente, d’où l’appareil commence son attaque.', 'ex. IP DELTA ou 0123 4567'],
        ['Cap', 'Cap magnétique de l’IP vers la cible, avec la déviation (gauche / droite).', 'ex. 245° M, déviation droite'],
        ['Distance', 'De l’IP à la cible, en milles nautiques (NM) ou en mètres.', 'ex. 4.2 NM'],
        ['Altitude cible', 'Altitude de la cible en pieds au-dessus du niveau de la mer (MSL).', 'ex. 450 ft MSL'],
        ['Description', 'Nature de la cible : type, nombre, attitude.', 'ex. 2 BTR à l’arrêt, infanterie retranchée'],
        ['Position cible', 'Grille de 4 à 10 chiffres (MGRS : zone facultative).', 'ex. 0234 5678'],
        ['Marquage', 'Comment la cible est désignée : laser (code), fumigène, IR, aucun.', 'ex. Laser 1688'],
        ['Amis', 'Direction et distance des troupes amies les plus proches de la cible.', 'ex. Sud 600 m'],
        ['Dégagement', 'Sortie après l’attaque : direction, altitude, point de regroupement.', 'ex. Sortie nord-est vers IP DELTA']
      ]
    },
    fiveline: {
      tag: '5-LINE',
      rail: '5-LINE',
      title: '5-LINE · Brief CAS court (hélicoptère, appareil déjà sur zone)',
      lines: [
        ['Observateur / alerte', 'Indicatif de l’observateur (JTAC) et ordre d’alerte : « 5-LINE », type de contrôle.', 'ex. HAMMER 11, 5-LINE, type 2'],
        ['Position amie / marquage', 'Position des amis et leur marquage (panneau, IR, fumigène).', 'ex. 0231 5642, panneau VS-17'],
        ['Position cible', 'Grille, ou direction et distance depuis le marquage ami.', 'ex. Nord 400 m de mon marquage'],
        ['Description / marquage cible', 'Nature de la cible et son marquage (laser, IR, fumigène).', 'ex. Mitrailleuse dans le bâtiment, fumigène rouge'],
        ['Remarques / restrictions', 'Axe d’attaque, armement, menaces, DANGER CLOSE, restrictions de tir.', 'ex. FAH 270 ± 20, roquettes seulement, menace SA-7']
      ]
    },
    medevac: {
      tag: 'MEDEVAC',
      rail: 'MEDEVAC',
      title: 'MEDEVAC · Évacuation sanitaire',
      lines: [
        ['Point de prise en charge', 'Grille de la zone de poser (LZ).', 'ex. 0234 5678'],
        ['Fréquence et indicatif', 'Fréquence radio et indicatif à contacter sur place.', 'ex. 45.500 / TANGO 2'],
        ['Patients par urgence', 'A : urgent, B : urgent chirurgical, C : différé.', 'ex. A 1 / B 0 / C 2'],
        ['Matériel spécial', 'Treuil, ventilation, aucun.', 'ex. Aucun'],
        ['Patients couchés / valides', 'L : sur brancard, A : capables de marcher.', 'ex. L 1 / A 2'],
        ['Sécurité de la zone', 'N : aucun ennemi, P : ennemi possible, E : ennemi présent, X : escorte armée.', 'ex. P'],
        ['Marquage de la zone', 'Fumigène (couleur), panneau, IR, aucun.', 'ex. Fumigène vert'],
        ['Nationalité des patients', 'Militaires amis, civils, prisonniers.', 'ex. Militaires amis'],
        ['Contamination / terrain', 'NBC, obstacles et description de la zone.', 'ex. Aucune ; LZ en pente douce']
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
  var live = $('nl-live');
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

  // ---------- Champs ligne par ligne (9 champs, les 4 derniers masqués en 5-LINE) ----------
  (function build() {
    var html = '';
    for (var n = 1; n <= MAX; n++) {
      var input = '<textarea class="effk-sel nl-input" id="nl-line' + n + '" name="line' + n + '" rows="1" maxlength="1000" aria-describedby="nl-help' + n + ' nl-err' + n + '"></textarea>';
      html += '<div class="nl-line" data-line="' + n + '">'
        + '<label class="nl-line__lbl" for="nl-line' + n + '"><span class="nl-num" aria-hidden="true">' + n + '</span>'
        + '<span class="nl-label" id="nl-label' + n + '"></span><span class="nl-req" aria-hidden="true">*</span></label>'
        + input
        + '<span class="nl-help" id="nl-help' + n + '"></span>'
        + '<span class="nl-err" id="nl-err' + n + '" role="alert"></span></div>';
    }
    $('nl-lines').innerHTML = html;
  })();

  function applyKind(next, keepValues) {
    kind = DEFS[next] ? next : 'cas';
    kindInput.value = kind;
    var def = DEFS[kind];
    var count = lineCount();
    for (var n = 1; n <= MAX; n++) {
      var row = field(n).closest('.nl-line');
      var on = n <= count;
      row.hidden = !on;
      if (!on) continue;
      var d = def.lines[n - 1];
      $('nl-label' + n).textContent = d[0];
      $('nl-help' + n).textContent = d[1];
      field(n).setAttribute('placeholder', d[2]);
      field(n).setAttribute('maxlength', n === count ? '1000' : '255');
      row.classList.toggle('is-required', isRequired(n) && !isFree());
      row.classList.toggle('is-wide', kind === 'fiveline' && n === 5);
      setError(n, '');
    }
    $('nl-lines').classList.toggle('is-five', kind === 'fiveline');
    $('nl-remarks-row').hidden = kind === 'fiveline';
    if (kind === 'fiveline' && !value(1)) field(1).value = myCallsign() + ', 5-LINE';
    $('nl-kind-title').textContent = def.title;
    $('nl-top-kind').textContent = def.tag;
    $('nl-rail-kind').textContent = def.rail;
    $('nl-req-note').textContent = isFree() ? 'saisie libre : aucune ligne obligatoire' : 'lignes obligatoires marquées *';
    Array.prototype.forEach.call(document.querySelectorAll('[data-kind]'), function (b) {
      var on2 = b.getAttribute('data-kind') === kind;
      b.classList.toggle('is-on', on2);
      b.setAttribute('aria-pressed', on2 ? 'true' : 'false');
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-only]'), function (el) {
      el.hidden = el.getAttribute('data-only') !== kind;
    });
    if (!keepValues) saveDraft();
    refresh();
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
    el.classList.toggle('is-warn', !!warn);
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
  function saveDraft() {
    var d = { kind: kind, free: isFree(), lines: {}, remarks: $('nl-remarks').value };
    for (var n = 1; n <= MAX; n++) d.lines[n] = field(n).value;
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(d)); } catch (e) {}
  }
  function loadDraft() {
    var d = null;
    try { d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { d = null; }
    if (!d || typeof d !== 'object') return 'cas';
    for (var n = 1; n <= MAX; n++) field(n).value = d.lines && d.lines[n] ? String(d.lines[n]) : '';
    $('nl-remarks').value = d.remarks ? String(d.remarks) : '';
    freeBox.checked = !!d.free;
    return DEFS[d.kind] ? d.kind : 'cas';
  }
  function resetForm() {
    for (var n = 1; n <= MAX; n++) { field(n).value = ''; setError(n, ''); }
    $('nl-remarks').value = '';
    try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    $('nl-calc-out').textContent = '';
    message('', '');
    if (kind === 'fiveline') field(1).value = myCallsign() + ', 5-LINE';
    refresh();
  }

  // ---------- Carte de transmission ----------
  function acks(list) {
    if (!list || !list.length) return '';
    return '<div class="nl-acks">' + list.map(function (r) {
      var st = ACK[r.status] ? r.status : 'sent';
      var tip = r.ack_by ? fmt('par %1', r.ack_by) : '';
      return '<span class="nl-ack is-' + st + '"' + (tip ? ' title="' + esc(tip) + '"' : '') + '>' + raw(r.label || r.callsign) + ' <b>' + esc(ACK[st]) + '</b></span>';
    }).join('') + '</div>';
  }
  function card(it, opts) {
    opts = opts || {};
    var k = kindOf(it);
    var count = lineCount(k);
    var rows = '';
    for (var n = 1; n <= count; n++) {
      var v = it['line' + n] || '';
      rows += '<li class="' + (v ? '' : 'is-empty') + '"><i>' + n + '</i><em>' + esc(DEFS[k].lines[n - 1][0]) + '</em>'
        + '<b>' + (v ? raw(v) : '<span class="nl-none">néant</span>') + '</b></li>';
    }
    if (k !== 'fiveline' && it.remarks) {
      rows += '<li><i>+</i><em>Remarques</em><b>' + raw(it.remarks) + '</b></li>';
    }
    var dest = it.assigned_aircraft ? raw(it.assigned_aircraft) : '<span class="nl-none">tous les appareils à l’écoute</span>';
    return '<article class="nl-card' + (opts.draft ? ' is-draft' : '') + '">'
      + '<header class="nl-card__head"><span class="nl-card__kind" translate="no">' + esc(DEFS[k].tag) + (k === 'cas' ? ' · CAS' : '') + '</span>'
      + '<span class="nl-card__stamp">' + esc(opts.stamp || '') + '</span></header>'
      + '<p class="nl-card__to"><span>Destinataires</span> ' + dest + '</p>'
      + '<ol class="nl-card__lines">' + rows + '</ol>'
      + (opts.acks ? acks(it.recipients) : '')
      + (opts.foot ? '<footer class="nl-card__foot">' + opts.foot + '</footer>' : '')
      + '</article>';
  }
  function destList() { return Object.keys(selected); }
  function destLabel() {
    return destList().map(function (cs) { return selected[cs].label || cs; }).join(', ');
  }
  function refresh() {
    var filled = 0;
    var count = lineCount();
    var req = isFree() ? [] : (REQUIRED[kind] || []);
    var reqOk = 0;
    var draft = { mission_kind: kind, assigned_aircraft: destLabel(), remarks: $('nl-remarks').value.trim() };
    for (var n = 1; n <= count; n++) {
      var v = value(n);
      draft['line' + n] = v;
      if (v) filled++;
      if (v && req.indexOf(n) !== -1) reqOk++;
    }
    $('nl-rail-lines').textContent = filled + ' / ' + count;
    $('nl-rail-dest').textContent = String(destList().length);
    $('nl-progress-text').textContent = req.length ? reqOk + ' / ' + req.length : '—';
    $('nl-draft-state').textContent = filled ? fmt('%1 ligne(s) renseignée(s)', filled) : 'Brouillon vide';
    $('nl-preview').innerHTML = card(draft, { draft: true, stamp: 'Brouillon' });
    renderDest();
  }

  // ---------- Destinataires ----------
  function person(p, crewKnown) {
    if (!p) return '<span class="nl-none">inconnu</span>';
    var out = '<b>' + raw(p.label || p.game_name || '') + '</b>';
    if (p.name) out += ' <span class="nl-person__name">' + raw(p.name) + '</span>';
    if (!p.player && crewKnown) out += ' <span class="nl-tag is-ia">IA</span>';
    else if (!p.linked) out += ' <span class="nl-tag" title="Nom reçu du jeu, sans compte Athena reconnu">jeu</span>';
    return out;
  }
  function visibleAircraft() {
    return playersBox.checked ? aircraft.filter(function (a) { return a.has_player || selected[a.callsign]; }) : aircraft;
  }
  function renderAircraft() {
    var box = $('nl-aircraft');
    var list = visibleAircraft();
    var hidden = aircraft.length - list.length;
    $('nl-ai-hidden').textContent = hidden > 0 ? fmt('%1 appareil(s) sans joueur masqué(s)', hidden) : '';
    if (!aircraft.length) {
      $('nl-aircraft-count').textContent = 'Aucun appareil';
      $('nl-aircraft-fresh').textContent = '—';
      box.innerHTML = '<div class="nl-empty"><b>Aucun appareil remonté par le jeu</b>Le mod doit être connecté à Athena (COMSPEC Link) et un aéronef doit avoir quelqu’un à bord. Vous pouvez quand même saisir un indicatif à la main ci-dessous.</div>';
      return;
    }
    var liveN = aircraft.filter(function (a) { return a.live; }).length;
    $('nl-aircraft-count').textContent = fmt('%1 appareil(s), %2 en direct', aircraft.length, liveN);
    $('nl-aircraft-fresh').textContent = fmt('Dernière remontée %1', ago(Math.min.apply(null, aircraft.map(function (a) { return a.age_s; }))));
    if (!list.length) {
      box.innerHTML = '<div class="nl-empty"><b>Aucun appareil avec un joueur à bord</b>Décochez « Joueurs uniquement » pour voir les appareils de l’IA.</div>';
      return;
    }
    box.innerHTML = list.map(function (a) {
      var on = !!selected[a.callsign];
      var crew = (a.crew || []).filter(function (c) { return c.seat !== 'driver'; });
      var facts = [];
      if (a.grid) facts.push('<span><em>Grille</em> ' + raw(a.grid) + '</span>');
      if (a.alt_m !== null && a.alt_m !== undefined) facts.push('<span><em>Alt</em> ' + raw(a.alt_ft + ' ft') + '</span>');
      if (a.heading !== null && a.heading !== undefined) facts.push('<span><em>Cap</em> ' + raw(('00' + a.heading).slice(-3) + '°') + '</span>');
      if (a.fuel_pct !== null && a.fuel_pct !== undefined) facts.push('<span><em>Carb.</em> ' + raw(a.fuel_pct + ' %') + '</span>');
      if (a.freq) facts.push('<span><em>Fréq.</em> ' + raw(a.freq) + '</span>');
      if (a.laser) facts.push('<span><em>Laser</em> ' + raw(a.laser) + '</span>');
      var state = !a.live ? '<span class="nl-state is-lost">Signal perdu</span>'
        : (String(a.status).toUpperCase() === 'IN-FLIGHT' ? '<span class="nl-state is-air">En vol</span>' : '<span class="nl-state is-ground">Au sol</span>');
      var label = a.label || a.callsign;
      var edit = renaming === a.callsign
        ? '<span class="nl-ac__rename"><input class="effk-sel nl-input nl-mono" data-rename-input="' + esc(a.callsign) + '" maxlength="32" list="nl-air-suggest" value="' + esc(label) + '">'
          + '<button type="button" class="effk-btn is-sm" data-rename-save="' + esc(a.callsign) + '">OK</button>'
          + '<button type="button" class="effk-btn is-sm" data-rename-cancel="1">Annuler</button></span>'
        : '';
      return '<label class="nl-ac' + (on ? ' is-selected' : '') + (a.live ? '' : ' is-stale') + '">'
        + '<input type="checkbox" class="nl-ac__check" value="' + esc(a.callsign) + '" data-map="' + a.map_id + '"' + (on ? ' checked' : '') + '>'
        + '<span class="nl-ac__body">'
        + '<span class="nl-ac__top"><b class="nl-ac__cs">' + raw(label) + '</b>'
        + (label !== a.callsign ? '<span class="nl-ac__orig" title="Indicatif remonté par le jeu">' + raw(a.callsign) + '</span>' : '')
        + state
        + '<button type="button" class="effk-btn is-sm nl-ac__edit" data-rename="' + esc(a.callsign) + '" title="' + esc(RENAME_TIP) + '">' + esc('Indicatif') + '</button></span>'
        + edit
        + '<span class="nl-ac__model">' + raw(a.model || '') + (TYPES[a.aircraft_type] ? ' · ' + esc(TYPES[a.aircraft_type]) : '')
        + (a.map_name ? ' · ' + raw(a.map_name) : '') + ' · ' + esc(fmt('mis à jour %1', ago(a.age_s))) + '</span>'
        + '<span class="nl-ac__pilot"><em>Pilote</em> ' + person(a.pilot, a.crew_count > 0) + '</span>'
        + (crew.length ? '<span class="nl-ac__crew"><em>Équipage</em> ' + crew.map(function (c) {
          return person(c, true) + ' <span class="nl-seat">' + esc(SEATS[c.seat] || SEATS.cargo) + '</span>';
        }).join(' · ') + '</span>' : '')
        + '<span class="nl-ac__facts">' + facts.join('') + '</span>'
        + '</span></label>';
    }).join('');
  }
  /** Destinataires choisis (appareils de la liste et indicatifs saisis) : retirables d'un clic. */
  function renderDest() {
    var cs = destList();
    $('nl-dest').innerHTML = cs.map(function (c) {
      var s = selected[c];
      var known = aircraft.some(function (a) { return a.callsign === c; });
      return '<button type="button" class="nl-chip" data-unselect="' + esc(c) + '" title="' + esc(known ? 'Retirer ce destinataire' : 'Saisi à la main ou plus remonté par le jeu : retirer') + '">' + raw(s.label || c) + ' ×</button>';
    }).join('');
  }
  function addManual() {
    var input = $('nl-manual-cs');
    var cs = cleanCs(input.value);
    if (!cs) { input.focus(); return; }
    selected[cs] = { label: cs, manual: true, map: Number(mapInput.value) || 1 };
    input.value = '';
    renderAircraft();
    refresh();
  }
  $('nl-manual-add').addEventListener('click', addManual);
  $('nl-manual-cs').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addManual(); } });
  $('nl-aircraft').addEventListener('change', function (e) {
    var cb = e.target.closest('.nl-ac__check');
    if (!cb) return;
    var a = aircraft.filter(function (x) { return x.callsign === cb.value; })[0] || {};
    if (cb.checked) selected[cb.value] = { label: a.label || cb.value, map: Number(cb.getAttribute('data-map')) || 1 };
    else delete selected[cb.value];
    var maps = destList().map(function (k) { return selected[k].map; }).filter(Boolean);
    if (maps.length) mapInput.value = String(maps[0]);
    renderAircraft();
    refresh();
  });
  $('nl-aircraft').addEventListener('click', function (e) {
    var t = e.target;
    var r = t.closest('[data-rename]');
    var save = t.closest('[data-rename-save]');
    var cancel = t.closest('[data-rename-cancel]');
    if (t.closest('.nl-ac__rename input')) return;
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
      if (!r.ok) { message('err', r.error || 'Indicatif non enregistré.'); return; }
      renaming = '';
      aircraft.forEach(function (a) { if (a.callsign === cs) a.label = r.label || cs; });
      if (selected[cs]) selected[cs].label = r.label || cs;
      message('ok', fmt('Indicatif affiché : %1 (remonté par le jeu : %2).', r.label || cs, cs));
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
  });

  // ---------- Indicatif JTAC ----------
  function saveJtac() {
    var inp = $('nl-jtac-cs');
    var cs = cleanCs(inp.value);
    var fd = new FormData();
    fd.append('callsign', cs);
    post('/9-line/indicatif', fd).then(function (r) {
      if (!r.ok) { message('err', r.error || 'Indicatif non enregistré.'); return; }
      jtac = r.callsign || '';
      inp.value = jtac;
      message('ok', jtac ? fmt('Indicatif JTAC : %1.', jtac) : fmt('Indicatif JTAC : celui d’Athena (%1).', r.author || myCallsign()));
      if (kind === 'fiveline' && /, 5-LINE$/.test(value(1))) { field(1).value = myCallsign() + ', 5-LINE'; refresh(); }
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
  function renderHistory() {
    var list = $('nl-history');
    $('nl-history-count').textContent = items.length ? fmt('%1 demande(s)', items.length) : 'Aucune demande';
    $('nl-clear').hidden = !canClear || !items.length;
    if (!items.length) {
      $('nl-selected').innerHTML = '<p class="nl-hint">Aucune demande pour l’instant. Rédigez-en une ici, ou dans l’app JTAC du jeu : elle apparaît en quelques secondes, avec les accusés des pilotes.</p>';
      list.innerHTML = '';
      return;
    }
    var cur = items.filter(function (it) { return it.id === pinned; })[0] || items[0];
    var foot = '<span>' + esc('par') + ' ' + raw(cur.author || 'JTAC') + '</span>'
      + (cur.map_name ? '<span>' + raw(cur.map_name) + '</span>' : '')
      + '<span>' + raw(when(cur.updated_at || cur.created_at)) + '</span>'
      + (cur.status ? '<span class="nl-tag" translate="no">' + esc(cur.status) + '</span>' : '');
    $('nl-selected').innerHTML = card(cur, { stamp: cur.id === items[0].id ? 'Dernier reçu' : 'Sélectionné', foot: foot, acks: true });
    list.innerHTML = items.map(function (it) {
      var ak = ackShort(it.recipients);
      return '<li class="nl-hist' + (it.id === cur.id ? ' is-on' : '') + '">'
        + '<button type="button" class="nl-hist__main" data-show="' + it.id + '">'
        + '<span class="nl-hist__kind" translate="no">' + esc(DEFS[kindOf(it)].tag) + '</span>'
        + '<span class="nl-hist__what">' + raw(summary(it)) + '</span>'
        + '<small>' + raw(it.author || 'JTAC') + ' · ' + raw(when(it.updated_at || it.created_at))
        + (it.assigned_aircraft ? ' · ' + raw(it.assigned_aircraft) : '') + (ak ? ' · ' + esc(ak) : '') + '</small></button>'
        + '<span class="nl-hist__acts">'
        + '<button type="button" class="effk-btn is-sm" data-reuse="' + it.id + '">Reprendre</button>'
        + (it.can_delete ? '<button type="button" class="effk-btn is-sm nl-danger" data-delete="' + it.id + '">Supprimer</button>' : '')
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
      saveDraft();
      refresh();
      message('info', 'Lignes reprises dans le formulaire : vérifiez-les avant de transmettre.');
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else if (del) {
      confirmBox('Supprimer cette demande ?', fmt('%1, par %2. La suppression est définitive, pour tous les membres et dans le jeu.', summary(it), it.author || 'JTAC'), false, function () {
        post('/9-line/' + id + '/supprimer', new FormData()).then(function (r) {
          if (!r.ok) { message('err', r.error || 'Suppression impossible.'); return; }
          if (pinned === id) pinned = null;
          message('ok', 'Demande supprimée.');
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
        if (!r.ok) { message('err', r.error || 'Suppression impossible.'); return; }
        pinned = null;
        message('ok', fmt('%1 demande(s) supprimée(s).', r.deleted || 0));
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
    live.textContent = ok ? fmt('à jour · %1', new Date().toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', second: '2-digit' })) : 'hors ligne';
    live.classList.toggle('is-off', !ok);
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
      if (err && err.message === 'login') $('nl-selected').innerHTML = '<p class="nl-hint">Connectez-vous à Athena pour voir les 9-LINE.</p>';
    });
  }
  function pollAircraft() {
    return getJson('/9-line/appareils').then(function (j) {
      aircraft = (j && j.items) || [];
      if (!renaming || !document.querySelector('[data-rename-input]:focus')) renderAircraft();
      renderDest();
    }).catch(function () {});
  }

  // ---------- Envoi ----------
  function message(type, text) {
    var el = $('nl-form-msg');
    el.hidden = !text;
    el.className = 'effk-note nl-msg ' + (type === 'ok' ? 'is-ok' : (type === 'err' ? 'is-warn' : 'is-info'));
    el.textContent = text || '';
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var bad = validateAll();
    if (bad) {
      message('err', 'Vérifiez les lignes signalées.');
      field(bad).focus();
      return;
    }
    var fd = new FormData(form);
    for (var n = lineCount() + 1; n <= MAX; n++) fd.delete('line' + n);
    if (!isFree()) fd.delete('free');
    destList().forEach(function (cs) { fd.append('aircraft[]', cs); });
    var btn = $('nl-submit');
    btn.disabled = true;
    var to = destLabel();
    post('/9-line', fd).then(function (r) {
      btn.disabled = false;
      if (!r.ok) {
        if (r.errors) Object.keys(r.errors).forEach(function (k) { setError(Number(k), r.errors[k]); });
        message('err', r.error || 'Envoi impossible.');
        return;
      }
      resetForm();
      pinned = null;
      message('ok', to ? fmt('%1 transmis à %2 : le pilote le reçoit dans BRIEFS AIR, son accusé s’affiche dans l’historique.', DEFS[kind].tag, to) : fmt('%1 transmis.', DEFS[kind].tag));
      pollFeed().then(function () { $('nl-sec-history').scrollIntoView({ behavior: 'smooth', block: 'nearest' }); });
    });
  });
  $('nl-reset').addEventListener('click', function () {
    var any = false;
    for (var n = 1; n <= lineCount(); n++) if (value(n)) any = true;
    if (any && !window.confirm('Effacer toutes les lignes du formulaire ?')) return;
    resetForm();
    field(1).focus();
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-kind]'), function (b) {
    b.addEventListener('click', function () { applyKind(b.getAttribute('data-kind'), false); });
  });
  freeBox.addEventListener('change', function () { applyKind(kind, false); validateAll(); });
  form.addEventListener('input', function (e) {
    var row = e.target.closest('.nl-line');
    if (row) {
      var n = Number(row.getAttribute('data-line'));
      if ($('nl-err' + n).textContent) setError(n, check(n));
    }
    saveDraft();
    refresh();
  });
  form.addEventListener('focusout', function (e) {
    var row = e.target.closest && e.target.closest('.nl-line');
    if (row && e.target.value.trim()) {
      var n = Number(row.getAttribute('data-line'));
      setError(n, check(n));
    }
  });

  // ---------- Aide au calcul : grille → mètres (case centrée), même lecture que la carte en jeu ----------
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
  $('nl-calc-btn').addEventListener('click', function () {
    var out = $('nl-calc-out');
    var a = grid(value(1));
    var b = grid(value(6));
    if (!a || !b) { out.textContent = 'Renseignez une grille à la ligne 1 (IP) et à la ligne 6 (cible), de 4 à 10 chiffres.'; return; }
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var tru = (Math.atan2(dx, dy) * 180 / Math.PI + 360) % 360;
    var decl = parseFloat(String($('nl-decl').value).replace(',', '.')) || 0;
    var mag = ((tru - decl) % 360 + 360) % 360;
    var d = Math.sqrt(dx * dx + dy * dy);
    field(2).value = pad3(mag) + '° M';
    field(3).value = (d / 1852).toFixed(1) + ' NM';
    out.textContent = fmt('Cap %1° M (vrai %2°, retour %3° M), distance %4 NM (%5 m) : lignes 2 et 3 remplies.', pad3(mag), pad3(tru), pad3(mag + 180), (d / 1852).toFixed(1), Math.round(d));
    setError(2, check(2));
    setError(3, check(3));
    saveDraft();
    refresh();
  });

  // ---------- Démarrage ----------
  try { playersBox.checked = localStorage.getItem(PLAYERS_KEY) !== '0'; } catch (e) {}
  applyKind(loadDraft(), true);
  renderAircraft();
  pollFeed();
  pollAircraft();
  setInterval(function () { if (!document.hidden) pollFeed(); }, 5000);
  setInterval(function () { if (!document.hidden) pollAircraft(); }, 5000);
})();
