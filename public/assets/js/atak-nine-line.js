/*
 * COMSPEC Athena — page /9-line (QR code et bouton de l'app JTAC de l'ATAK natif, ou seconde fenêtre du PC).
 * - Appareils remontés par le jeu (GET /9-line/appareils, toutes les 5 s) : pilote, équipage, position, fraîcheur ;
 *   sélection des destinataires du 9-LINE.
 * - Formulaire ligne par ligne (CAS ou MEDEVAC), aides et contrôle par ligne, brouillon gardé dans ce navigateur,
 *   aide au calcul des lignes 2 et 3, carte de transmission tenue à jour.
 * - Historique (GET /9-line/feed, toutes les 5 s) : affichage, reprise dans le formulaire, suppression (auteur ou
 *   responsable), vidage de l'historique (responsable, mot de confirmation).
 * Textes en français : traduits à l'affichage par i18n_dom.js (lang/en/site_auto.php, js-bundle).
 */
(function () {
  'use strict';

  var cfg = window.NINE_LINE || {};
  var base = String(cfg.base || '').replace(/\/$/, '');
  var locale = cfg.lang === 'fr' ? 'fr-FR' : 'en-GB';
  var REQUIRED = cfg.required || { cas: [4, 5, 6], medevac: [1, 2, 3] };
  var DRAFT_KEY = 'athena.nl.draft';

  var DEFS = {
    cas: {
      title: 'Appui aérien rapproché (CAS)',
      lines: [
        ['IP / BP', 'Point initial ou d’attente, d’où l’appareil commence son attaque.', 'ex. IP DELTA ou 0123 4567'],
        ['Cap', 'Cap magnétique de l’IP vers la cible, avec la déviation (gauche / droite).', 'ex. 245° M, déviation droite'],
        ['Distance', 'De l’IP à la cible, en milles nautiques (NM) ou en mètres.', 'ex. 4.2 NM'],
        ['Altitude cible', 'Altitude de la cible en pieds au-dessus du niveau de la mer (MSL).', 'ex. 450 ft MSL'],
        ['Description', 'Nature de la cible : type, nombre, attitude.', 'ex. 2 BTR à l’arrêt, infanterie retranchée'],
        ['Position cible', 'Grille de 4 à 10 chiffres (MGRS : zone facultative).', 'ex. 0234 5678'],
        ['Marquage', 'Comment la cible est désignée : laser (code), fumigène, IR, aucun.', 'ex. Laser 1688'],
        ['Amis', 'Direction et distance des troupes amies les plus proches de la cible.', 'ex. Sud 600 m'],
        ['Dégagement', 'Sortie après l’attaque, puis remarques (menaces, restrictions, contrôle).', 'ex. Sortie nord-est vers IP DELTA. Menace SA-7 au sud.']
      ]
    },
    medevac: {
      title: 'Évacuation sanitaire (MEDEVAC)',
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
  var SEATS = { driver: 'Pilote', gunner: 'Tireur', commander: 'Chef de bord', cargo: 'Passager' };
  var TYPES = { helicopter: 'Hélicoptère', plane: 'Avion', uav: 'Drone' };

  var $ = function (id) { return document.getElementById(id); };
  var form = $('nl-form');
  var kindInput = $('nl-kind');
  var mapInput = $('nl-map');
  var live = $('nl-live');
  var kind = 'cas';
  var selected = {};
  var aircraft = [];
  var items = [];
  var canClear = false;
  var pinned = null;
  var historyMap = 0;
  var csrf = String(cfg.csrf || '');

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
  function field(n) { return $('nl-line' + n); }
  function value(n) { var el = field(n); return el ? el.value.trim() : ''; }
  function isRequired(n) { return (REQUIRED[kind] || []).indexOf(n) !== -1; }

  // ---------- Champs ligne par ligne ----------
  Array.prototype.forEach.call(document.querySelectorAll('.nl-lines[data-lines]'), function (box) {
    box.innerHTML = box.getAttribute('data-lines').split(',').map(function (v) {
      var n = Number(v);
      var input = n === 9
        ? '<textarea class="input-field nl-input" id="nl-line9" name="line9" rows="2" maxlength="1000" aria-describedby="nl-help9 nl-err9"></textarea>'
        : '<input class="input-field nl-input" id="nl-line' + n + '" name="line' + n + '" type="text" maxlength="255" aria-describedby="nl-help' + n + ' nl-err' + n + '">';
      return '<div class="ce-field nl-line" data-line="' + n + '">'
        + '<label class="ce-label" for="nl-line' + n + '"><span class="nl-num" aria-hidden="true">' + n + '</span>'
        + '<span class="nl-label" id="nl-label' + n + '"></span><span class="nl-req" aria-hidden="true">*</span></label>'
        + input
        + '<span class="ce-label-hint" id="nl-help' + n + '"></span>'
        + '<span class="nl-err" id="nl-err' + n + '" role="alert"></span></div>';
    }).join('');
  });

  function applyKind(next, keepValues) {
    kind = next === 'medevac' ? 'medevac' : 'cas';
    kindInput.value = kind;
    var def = DEFS[kind];
    for (var n = 1; n <= 9; n++) {
      var d = def.lines[n - 1];
      $('nl-label' + n).textContent = d[0];
      $('nl-help' + n).textContent = d[1];
      field(n).setAttribute('placeholder', d[2]);
      var row = field(n).closest('.nl-line');
      row.classList.toggle('is-required', isRequired(n));
      field(n).toggleAttribute('required', isRequired(n));
      setError(n, '');
    }
    $('nl-kind-title').textContent = def.title;
    $('nl-rail-kind').textContent = kind === 'medevac' ? 'MEDEVAC' : 'CAS';
    Array.prototype.forEach.call(document.querySelectorAll('[data-kind]'), function (b) {
      var on = b.getAttribute('data-kind') === kind;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-only]'), function (el) {
      el.hidden = el.getAttribute('data-only') !== kind;
    });
    if (!keepValues) saveDraft();
    refresh();
  }

  // ---------- Contrôle par ligne ----------
  function gridOk(txt) { return grid(txt) !== null; }
  function check(n) {
    var v = value(n);
    if (!v) return isRequired(n) ? 'required' : '';
    if (v.length > (n === 9 ? 1000 : 255)) return 'too_long';
    if (kind === 'cas' && n === 2) {
      var m = v.match(/^(\d{1,3})/);
      if (m && Number(m[1]) > 360) return 'heading';
    }
    if ((kind === 'cas' && n === 6) || (kind === 'medevac' && n === 1)) {
      if (!gridOk(v)) return kind === 'cas' ? 'grid' : '';
    }
    return '';
  }
  function setError(n, code) {
    var el = $('nl-err' + n);
    if (!el) return;
    el.textContent = code ? (ERR[code] || code) : '';
    var f = field(n);
    f.classList.toggle('is-invalid', !!code);
    f.setAttribute('aria-invalid', code ? 'true' : 'false');
  }
  function validateAll() {
    var first = 0;
    for (var n = 1; n <= 9; n++) {
      var code = check(n);
      setError(n, code);
      if (code && !first) first = n;
    }
    return first;
  }

  // ---------- Brouillon (ce navigateur seulement) ----------
  function saveDraft() {
    var d = { kind: kind, lines: {} };
    for (var n = 1; n <= 9; n++) d.lines[n] = field(n).value;
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(d)); } catch (e) {}
  }
  function loadDraft() {
    var d = null;
    try { d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { d = null; }
    if (!d || typeof d !== 'object') return 'cas';
    for (var n = 1; n <= 9; n++) field(n).value = d.lines && d.lines[n] ? String(d.lines[n]) : '';
    return d.kind === 'medevac' ? 'medevac' : 'cas';
  }
  function resetForm() {
    for (var n = 1; n <= 9; n++) { field(n).value = ''; setError(n, ''); }
    try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    $('nl-calc-out').textContent = '';
    message('', '');
    refresh();
  }

  // ---------- Avancement, carte de transmission ----------
  function card(it, opts) {
    opts = opts || {};
    var k = it.mission_kind === 'medevac' ? 'medevac' : 'cas';
    var rows = '';
    for (var n = 1; n <= 9; n++) {
      var v = it['line' + n] || '';
      rows += '<li class="' + (v ? '' : 'is-empty') + '"><i>' + n + '</i><em>' + esc(DEFS[k].lines[n - 1][0]) + '</em>'
        + '<b>' + (v ? raw(v) : '<span class="nl-none">néant</span>') + '</b></li>';
    }
    var dest = it.assigned_aircraft ? raw(it.assigned_aircraft) : '<span class="nl-none">tous les appareils à l’écoute</span>';
    return '<article class="nl-card' + (opts.draft ? ' is-draft' : '') + '">'
      + '<header class="nl-card__head"><span class="nl-card__kind">' + (k === 'medevac' ? 'MEDEVAC' : 'CAS') + ' · 9-LINE</span>'
      + '<span class="nl-card__stamp">' + esc(opts.stamp || '') + '</span></header>'
      + '<p class="nl-card__to"><span>Destinataires</span> ' + dest + '</p>'
      + '<ol class="nl-card__lines">' + rows + '</ol>'
      + (opts.foot ? '<footer class="nl-card__foot">' + opts.foot + '</footer>' : '')
      + '</article>';
  }
  function destLabel() {
    return Object.keys(selected).join(', ');
  }
  function refresh() {
    var filled = 0;
    var req = REQUIRED[kind] || [];
    var reqOk = 0;
    var draft = { mission_kind: kind, assigned_aircraft: destLabel() };
    for (var n = 1; n <= 9; n++) {
      var v = value(n);
      draft['line' + n] = v;
      if (v) filled++;
      if (v && req.indexOf(n) !== -1) reqOk++;
    }
    $('nl-rail-lines').textContent = filled + ' / 9';
    $('nl-rail-dest').textContent = String(Object.keys(selected).length);
    $('nl-progress').style.width = Math.round(filled / 9 * 100) + '%';
    $('nl-progress-text').textContent = fmt('Lignes obligatoires : %1 / %2', reqOk, req.length);
    $('nl-draft-state').textContent = filled ? fmt('%1 ligne(s) renseignée(s)', filled) : 'Brouillon vide';
    $('nl-preview').innerHTML = card(draft, { draft: true, stamp: 'Brouillon' });
  }

  // ---------- Appareils remontés par le jeu ----------
  function person(p) {
    if (!p) return '<span class="nl-none">inconnu</span>';
    var out = '<b>' + raw(p.label || p.game_name || '') + '</b>';
    if (p.name) out += ' <span class="nl-person__name">' + raw(p.name) + '</span>';
    if (!p.linked) out += ' <span class="nl-tag" title="Nom reçu du jeu, sans compte Athena reconnu">jeu</span>';
    return out;
  }
  function renderAircraft() {
    var box = $('nl-aircraft');
    var count = $('nl-aircraft-count');
    var fresh = $('nl-aircraft-fresh');
    if (!aircraft.length) {
      count.textContent = 'Aucun appareil';
      fresh.textContent = '—';
      box.innerHTML = '<div class="ce-notice ce-notice--info nl-empty"><p class="ce-notice__kicker">Aucune donnée</p>'
        + '<p class="ce-notice__title">Aucun appareil remonté par le jeu</p>'
        + '<p class="ce-notice__lead">Le mod doit être connecté à Athena (COMSPEC Link) et un aéronef doit avoir quelqu’un à bord. Vous pouvez quand même rédiger et transmettre le 9-LINE sans destinataire.</p></div>'
        + selectedOrphans();
      return;
    }
    var liveN = aircraft.filter(function (a) { return a.live; }).length;
    count.textContent = fmt('%1 appareil(s), %2 en direct', aircraft.length, liveN);
    fresh.textContent = fmt('Dernière remontée %1', ago(Math.min.apply(null, aircraft.map(function (a) { return a.age_s; }))));
    box.innerHTML = aircraft.map(function (a) {
      var on = !!selected[a.callsign];
      var crew = (a.crew || []).filter(function (c) { return c.seat !== 'driver'; });
      var facts = [];
      if (a.grid) facts.push('<span><em>Grille</em> ' + raw(a.grid) + '</span>');
      if (a.alt_m !== null && a.alt_m !== undefined) facts.push('<span><em>Altitude</em> ' + raw(a.alt_ft + ' ft · ' + a.alt_m + ' m') + '</span>');
      if (a.heading !== null && a.heading !== undefined) facts.push('<span><em>Cap</em> ' + raw(('00' + a.heading).slice(-3) + '°') + '</span>');
      if (a.fuel_pct !== null && a.fuel_pct !== undefined) facts.push('<span><em>Carburant</em> ' + raw(a.fuel_pct + ' %') + '</span>');
      if (a.freq) facts.push('<span><em>Fréquence</em> ' + raw(a.freq) + '</span>');
      if (a.laser) facts.push('<span><em>Laser</em> ' + raw(a.laser) + '</span>');
      var state = !a.live ? '<span class="nl-state is-lost">Signal perdu</span>'
        : (String(a.status).toUpperCase() === 'IN-FLIGHT' ? '<span class="nl-state is-air">En vol</span>' : '<span class="nl-state is-ground">Au sol</span>');
      return '<label class="nl-ac' + (on ? ' is-selected' : '') + (a.live ? '' : ' is-stale') + '">'
        + '<input type="checkbox" class="nl-ac__check" value="' + esc(a.callsign) + '" data-map="' + a.map_id + '"' + (on ? ' checked' : '') + '>'
        + '<span class="nl-ac__body">'
        + '<span class="nl-ac__top"><b class="nl-ac__cs">' + raw(a.callsign) + '</b>' + state + '</span>'
        + '<span class="nl-ac__model">' + raw(a.model || '') + (TYPES[a.aircraft_type] ? ' · ' + esc(TYPES[a.aircraft_type]) : '')
        + (a.map_name ? ' · ' + raw(a.map_name) : '') + '</span>'
        + '<span class="nl-ac__pilot"><em>Pilote</em> ' + person(a.pilot) + '</span>'
        + (crew.length ? '<span class="nl-ac__crew"><em>Équipage</em> ' + crew.map(function (c) {
          return person(c) + ' <span class="nl-seat">' + esc(SEATS[c.seat] || SEATS.cargo) + '</span>';
        }).join(' · ') + '</span>' : '')
        + '<span class="nl-ac__facts">' + facts.join('') + '</span>'
        + '<span class="nl-ac__age">' + esc(fmt('Mis à jour %1', ago(a.age_s))) + '</span>'
        + '</span></label>';
    }).join('') + selectedOrphans();
  }
  /** Destinataires choisis dont l'appareil n'est plus remonté : gardés, et retirables. */
  function selectedOrphans() {
    var known = {};
    aircraft.forEach(function (a) { known[a.callsign] = true; });
    var orphans = Object.keys(selected).filter(function (cs) { return !known[cs]; });
    if (!orphans.length) return '';
    return '<p class="nl-orphans">Toujours destinataires, plus remontés par le jeu : ' + orphans.map(function (cs) {
      return '<button type="button" class="nl-chip" data-unselect="' + esc(cs) + '">' + raw(cs) + ' ×</button>';
    }).join(' ') + '</p>';
  }
  $('nl-aircraft').addEventListener('change', function (e) {
    var cb = e.target.closest('.nl-ac__check');
    if (!cb) return;
    if (cb.checked) selected[cb.value] = Number(cb.getAttribute('data-map')) || 1;
    else delete selected[cb.value];
    var maps = Object.keys(selected).map(function (k) { return selected[k]; });
    if (maps.length) mapInput.value = String(maps[0]);
    renderAircraft();
    refresh();
  });
  $('nl-aircraft').addEventListener('click', function (e) {
    var b = e.target.closest('[data-unselect]');
    if (!b) return;
    delete selected[b.getAttribute('data-unselect')];
    renderAircraft();
    refresh();
  });

  // ---------- Historique ----------
  function renderHistory() {
    var list = $('nl-history');
    $('nl-history-count').textContent = items.length ? fmt('%1 9-LINE', items.length) : 'Aucun 9-LINE';
    $('nl-clear').hidden = !canClear || !items.length;
    if (!items.length) {
      $('nl-selected').innerHTML = '<div class="ce-notice ce-notice--muted"><p class="ce-notice__lead">Aucun 9-LINE pour l’instant. Rédigez-en un ci-dessus, ou dans l’app JTAC du jeu (TRANSMETTRE LE 9-LINE) : il apparaît ici en quelques secondes.</p></div>';
      list.innerHTML = '';
      return;
    }
    var cur = items.filter(function (it) { return it.id === pinned; })[0] || items[0];
    var foot = '<span>' + esc('par') + ' ' + raw(cur.author || 'JTAC') + '</span>'
      + (cur.map_name ? '<span>' + raw(cur.map_name) + '</span>' : '')
      + '<span>' + raw(when(cur.updated_at || cur.created_at)) + '</span>'
      + (cur.status ? '<span class="nl-tag" translate="no">' + esc(cur.status) + '</span>' : '');
    $('nl-selected').innerHTML = card(cur, { stamp: cur.id === items[0].id ? 'Dernier reçu' : 'Sélectionné', foot: foot });
    list.innerHTML = items.map(function (it) {
      var k = it.mission_kind === 'medevac' ? 'MEDEVAC' : 'CAS';
      return '<li class="nl-hist' + (it.id === cur.id ? ' is-on' : '') + '">'
        + '<button type="button" class="nl-hist__main" data-show="' + it.id + '">'
        + '<span class="nl-hist__kind" translate="no">' + k + '</span>'
        + '<span class="nl-hist__what">' + raw((it.line1 || 'IP') + ' → ' + (it.line6 || '?')) + '</span>'
        + '<small>' + raw(it.author || 'JTAC') + ' · ' + raw(when(it.updated_at || it.created_at))
        + (it.assigned_aircraft ? ' · ' + raw(it.assigned_aircraft) : '') + '</small></button>'
        + '<span class="nl-hist__acts">'
        + '<button type="button" class="ce-btn ce-btn--soft nl-mini" data-reuse="' + it.id + '">Reprendre</button>'
        + (it.can_delete ? '<button type="button" class="ce-btn ce-btn--soft nl-mini nl-danger" data-delete="' + it.id + '">Supprimer</button>' : '')
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
      applyKind(it.mission_kind, true);
      for (var n = 1; n <= 9; n++) field(n).value = it['line' + n] || '';
      saveDraft();
      refresh();
      message('info', 'Lignes reprises dans le formulaire : vérifiez-les avant de transmettre.');
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else if (del) {
      confirmBox('Supprimer ce 9-LINE ?', fmt('%1 → %2, par %3. La suppression est définitive, pour tous les membres et dans le jeu.', it.line1 || 'IP', it.line6 || '?', it.author || 'JTAC'), false, function () {
        post('/9-line/' + id + '/supprimer', new FormData()).then(function (r) {
          if (!r.ok) { message('err', r.error || 'Suppression impossible.'); return; }
          if (pinned === id) pinned = null;
          message('ok', '9-LINE supprimé.');
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
    confirmBox('Vider l’historique ?', fmt('Tous les 9-LINE %1 seront supprimés définitivement, pour tous les membres et dans le jeu.', scope), true, function (word) {
      var fd = new FormData();
      fd.append('confirm', word);
      fd.append('map_id', String(historyMap));
      post('/9-line/vider', fd).then(function (r) {
        if (!r.ok) { message('err', r.error || 'Suppression impossible.'); return; }
        pinned = null;
        message('ok', fmt('%1 9-LINE supprimé(s).', r.deleted || 0));
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
      if (err && err.message === 'login') $('nl-selected').innerHTML = '<p class="ce-help">Connectez-vous à Athena pour voir les 9-LINE.</p>';
    });
  }
  function pollAircraft() {
    return getJson('/9-line/appareils').then(function (j) {
      aircraft = (j && j.items) || [];
      renderAircraft();
    }).catch(function () {});
  }

  // ---------- Envoi ----------
  function message(type, text) {
    var el = $('nl-form-msg');
    el.hidden = !text;
    el.className = 'ce-notice nl-form-msg ' + (type === 'ok' ? 'ce-notice--info is-ok' : (type === 'err' ? 'ce-notice--warn' : 'ce-notice--muted'));
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
    Object.keys(selected).forEach(function (cs) { fd.append('aircraft[]', cs); });
    var btn = $('nl-submit');
    btn.disabled = true;
    post('/9-line', fd).then(function (r) {
      btn.disabled = false;
      if (!r.ok) {
        if (r.errors) Object.keys(r.errors).forEach(function (n) { setError(Number(n), r.errors[n]); });
        message('err', r.error || 'Envoi impossible.');
        return;
      }
      resetForm();
      pinned = null;
      message('ok', Object.keys(selected).length ? fmt('9-LINE transmis à %1.', destLabel()) : '9-LINE transmis.');
      pollFeed().then(function () { $('nl-sec-history').scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    });
  });
  $('nl-reset').addEventListener('click', function () {
    var any = false;
    for (var n = 1; n <= 9; n++) if (value(n)) any = true;
    if (any && !window.confirm('Effacer toutes les lignes du formulaire ?')) return;
    resetForm();
    field(1).focus();
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-kind]'), function (b) {
    b.addEventListener('click', function () { applyKind(b.getAttribute('data-kind'), false); });
  });
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
  applyKind(loadDraft(), true);
  renderAircraft();
  pollFeed();
  pollAircraft();
  setInterval(function () { if (!document.hidden) pollFeed(); }, 5000);
  setInterval(function () { if (!document.hidden) pollAircraft(); }, 5000);
})();
