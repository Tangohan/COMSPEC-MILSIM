/* Poste TOC web : suppression, sélection multiple, corbeille de 15 minutes et purge de la carte. Un seul module pour la
 * fiche d'un élément, le rectangle de sélection, la liste à cases, la barre d'actions, la touche Suppr, l'annulation dans la
 * ligne d'état, la corbeille et l'écran « Purger » (aperçu exact, archivage préalable, lots de 500, reprise).
 * Les écritures passent par /atak/toc/api/elements/* et /atak/toc/api/purge/* (CSRF, droits rejugés par le serveur).
 * Aucune confirmation native du navigateur : une seule boîte de dialogue du poste, dont le contenu change d'étape.
 * Textes en français (traduits par le dictionnaire du site : chaque texte est une phrase entière ou un modèle « … %1 … »). */
window.TocManage = (function () {
  'use strict';
  var KIND = { marker: 'Marqueur', form: 'Forme', route: 'Route', zone: 'Zone', ping: 'Ping', track: 'Piste' };
  var KIND_PLURAL = { marker: 'Marqueurs', form: 'Formes', route: 'Routes', zone: 'Zones', ping: 'Pings', track: 'Pistes muettes' };
  var PURGE_TYPES = ['marker', 'form', 'route', 'zone', 'ping', 'track'];
  var ERR = {
    csrf: 'Session expirée : rechargez la page.', rate_limited: 'Trop d’actions d’affilée : attendez quelques secondes.', forbidden: 'Vous n’avez pas le droit de faire cela.',
    nothing: 'Rien à faire : ces éléments n’existent plus.', expired: 'Le délai de 15 minutes est passé : la suppression est définitive.', purge_running: 'Une purge est déjà en cours sur cette carte.',
    too_many: 'Trop d’éléments d’un coup : réduisez le périmètre (type, zone ou âge).', archive_unavailable: 'L’archivage de la mission est indisponible : décochez l’option ou réessayez plus tard.',
    archive_failed: 'L’archivage a échoué : rien n’a été supprimé.', unavailable: 'La purge demande la mise à jour du site : elle n’est pas disponible pour l’instant.',
    confirm_required: 'Tapez la phrase demandée pour confirmer.', types_required: 'Choisissez au moins un type d’élément.', preview_changed: 'La carte a changé depuis l’aperçu : vérifiez le nouveau nombre.',
    batch_failed: 'Un lot a échoué : relancez la purge pour reprendre.', pinned_confirm: 'Des éléments permanents demandent une confirmation distincte.'
  };

  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function $(sel, root) { return (root || document).querySelector(sel); }
  function two(n) { return (n < 10 ? '0' : '') + n; }
  function mmss(sec) { sec = Math.max(0, Math.floor(sec)); return two(Math.floor(sec / 60)) + ':' + two(sec % 60); }
  function hhmm(ts) { var m = /(\d{2}):(\d{2})/.exec(String(ts || '')); return m ? m[1] + ':' + m[2] : ''; }
  /** Texte à trous (%1, %2…) : le navigateur traduit la phrase entière par son modèle du dictionnaire. */
  function t(text, args) { return String(text).replace(/%(\d)/g, function (m, i) { return args && args[i - 1] != null ? args[i - 1] : m; }); }
  function pl(n, one, many) { return t(n === 1 ? one : many, [n]); }
  function opt(v, label, on) { return '<option value="' + v + '"' + (on ? ' selected' : '') + '>' + label + '</option>'; }
  function norm(s) {
    var t = String(s || '').toLowerCase();
    return t.normalize ? t.normalize('NFD').replace(/[̀-ͯ]/g, '') : t;
  }
  function refOf(e) { return e.table + ':' + e.id; }

  function create(opts) {
    var tm = opts.tm;
    var map = tm.map;
    var L = window.L;
    var sel = {};
    var byRef = {};
    var list = [];
    var mode = false;
    var view = 'list';
    var filter = { type: '', q: '' };
    var trash = { batches: [], at: 0 };
    var undo = null;
    var timers = [];
    var lastRev = null;
    var drag = null;
    var draw = null;
    var dead = false;
    var P = null;
    var lastFocus = null;
    var offs = [];
    function on(target, type, fn, capture) {
      target.addEventListener(type, fn, !!capture);
      offs.push(function () { target.removeEventListener(type, fn, !!capture); });
    }
    var hi = L.layerGroup().addTo(map);
    var drawLayer = L.layerGroup().addTo(map);
    var el = {
      panel: $('#toc-manage'), btn: $('#toc-manage-btn'), count: $('#toc-manage-count'), trashCount: $('#toc-trash-count'), items: $('#toc-mg-items'),
      all: $('#toc-mg-all'), allText: $('#toc-mg-all-text'), listView: $('#toc-manage-listview'), trashView: $('#toc-manage-trashview'),
      selbar: $('#toc-selbar'), selCount: $('#toc-selbar-count'), selDelete: $('#toc-sb-delete'), rect: $('#toc-selrect'),
      dlg: $('#toc-dlg'), dlgBody: $('#toc-dlg-body'), undo: $('#toc-status-undo'), undoText: $('#toc-status-undo-text'), undoBtn: $('#toc-status-undo-btn'),
      purgeBtn: $('#toc-purge-btn'), purgeHint: $('#toc-purge-hint'), drawbar: $('#toc-drawbar'), drawText: $('#toc-drawbar-text'), wrap: $('#toc-mapwrap'), container: map.getContainer()
    };

    function say(msg, bad) { if (opts.say) opts.say(msg, bad); }
    function call(path, body) {
      var o = body === undefined ? {} : { method: 'POST', body: Object.assign({ mapId: opts.mapId() }, body) };
      return opts.api(path + (body === undefined ? (path.indexOf('?') < 0 ? '?' : '&') + 'mapId=' + opts.mapId() : ''), o);
    }
    function errText(j) { return ERR[j && j.error] || 'Action refusée : réessayez.'; }

    /* ---------- Éléments et sélection ---------- */
    function refresh() {
      list = opts.layers.elements();
      byRef = {};
      list.forEach(function (e) { byRef[refOf(e)] = e; });
      Object.keys(sel).forEach(function (k) { if (!byRef[k]) delete sel[k]; });
      el.count.textContent = list.length;
      render();
    }
    function count() { return Object.keys(sel).length; }
    function selected() { return Object.keys(sel).map(function (k) { return byRef[k]; }).filter(Boolean); }
    function refsOf(arr) { return arr.map(function (e) { return { table: e.table, id: e.id }; }); }
    function setSel(refs, on) {
      refs.forEach(function (r) { if (on) sel[r] = true; else delete sel[r]; });
      render();
    }
    function toggle(r) { setSel([r], !sel[r]); }
    function clearSel() { sel = {}; render(); }

    function visibleList() {
      var q = norm(filter.q);
      return list.filter(function (e) {
        if (filter.type && e.kind !== filter.type) return false;
        if (q && norm(e.label + ' ' + e.author + ' ' + e.type + ' ' + e.unit).indexOf(q) < 0) return false;
        return true;
      }).sort(function (a, b) { return (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0) || String(b.at).localeCompare(String(a.at)) || b.id - a.id; });
    }

    function renderHighlights() {
      hi.clearLayers();
      selected().forEach(function (e) {
        if (!e.pos) return;
        hi.addLayer(L.circleMarker(tm.toLL(e.pos), { radius: e.kind === 'marker' ? 17 : 13, color: '#f5b841', weight: 3, fill: true, fillColor: '#f5b841', fillOpacity: 0.12, interactive: false }));
      });
    }
    function render() {
      renderHighlights();
      var n = count();
      el.selbar.hidden = n === 0 || !!draw;
      if (n) {
        var pinned = selected().filter(function (e) { return e.pinned; }).length;
        el.selCount.innerHTML = '<b>' + n + '</b> <span>' + (n === 1 ? 'élément sélectionné' : 'éléments sélectionnés') + '</span>' + (pinned ? ' <span class="toc-selbar-pin">' + pl(pinned, 'dont %1 permanent', 'dont %1 permanents') + '</span>' : '');
        el.selDelete.textContent = pl(n, 'Supprimer %1 élément', 'Supprimer %1 éléments');
      }
      var shown = visibleList();
      var cap = 400;
      var h = '';
      shown.slice(0, cap).forEach(function (e) {
        var r = refOf(e);
        h += '<li class="toc-mg-row' + (sel[r] ? ' is-sel' : '') + '" data-ref="' + esc(r) + '"><label class="toc-mg-chk"><input type="checkbox" data-mg-sel="' + esc(r) + '"' + (sel[r] ? ' checked' : '') + (e.visible ? '' : ' disabled') + '>' +
          '<span class="toc-mg-dot is-' + esc(e.kind) + '" aria-hidden="true"></span><span class="toc-mg-name">' + esc(e.label || KIND[e.kind]) + '</span>' +
          '<span class="toc-mg-meta"><span>' + KIND[e.kind] + '</span>' + (e.author ? '<span>' + esc(e.author) + '</span>' : '') + (e.at ? '<span class="mono">' + esc(hhmm(e.at)) + '</span>' : '') + (e.pinned ? '<span class="toc-mg-pin">Permanent</span>' : '') + (e.visible ? '' : '<span>Calque masqué</span>') + '</span></label>' +
          '<button type="button" class="toc-btn is-small is-icon" aria-label="Centrer sur cet élément" title="Centrer sur cet élément" data-mg-center><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v5M12 17v5M2 12h5M17 12h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button></li>';
      });
      if (!shown.length) h = '<li class="toc-mg-empty">' + (list.length ? 'Aucun élément ne correspond à ce filtre.' : 'Aucun marqueur ni tracé sur cette carte.') + '</li>';
      if (shown.length > cap) h += '<li class="toc-mg-empty">Liste limitée aux 400 premiers : affinez le filtre.</li>';
      el.items.innerHTML = h;
      var allOn = shown.length > 0 && shown.slice(0, cap).every(function (e) { return !e.visible || sel[refOf(e)]; });
      el.all.checked = allOn;
      el.allText.textContent = shown.length ? t('Tout cocher (%1)', [shown.length]) : 'Tout cocher';
    }

    function elementsAt(rect) {
      return list.filter(function (e) { return e.visible && e.pos && e.pos[0] >= rect[0] && e.pos[0] <= rect[2] && e.pos[1] >= rect[1] && e.pos[1] <= rect[3]; });
    }

    /* ---------- Rectangle de sélection et zone dessinée ---------- */
    function localPoint(ev) { var b = el.container.getBoundingClientRect(); return { x: ev.clientX - b.left, y: ev.clientY - b.top }; }
    function worldOf(pt) { return tm.toWorld(map.containerPointToLatLng(L.point(pt.x, pt.y))); }
    function drawRect(a, b) {
      var wrapBox = el.wrap.getBoundingClientRect();
      var cb = el.container.getBoundingClientRect();
      var x = Math.min(a.x, b.x), y = Math.min(a.y, b.y);
      el.rect.style.left = (cb.left - wrapBox.left + x) + 'px';
      el.rect.style.top = (cb.top - wrapBox.top + y) + 'px';
      el.rect.style.width = Math.abs(a.x - b.x) + 'px';
      el.rect.style.height = Math.abs(a.y - b.y) + 'px';
      el.rect.hidden = false;
    }
    function rectWorld(a, b) {
      var p = worldOf(a), q = worldOf(b);
      return [Math.min(p[0], q[0]), Math.min(p[1], q[1]), Math.max(p[0], q[0]), Math.max(p[1], q[1])];
    }
    function onDown(ev) {
      if (dead || ev.button > 0 || ev.target.closest && ev.target.closest('.leaflet-control, .toc-mapctl, button, a')) return;
      if (!(mode || draw || ev.ctrlKey)) return;
      map.dragging.disable();
      drag = { from: localPoint(ev), to: null, moved: false, additive: ev.shiftKey || ev.ctrlKey || ev.metaKey, id: ev.pointerId, captured: false };
      ev.preventDefault();
      ev.stopPropagation();
    }
    function onMove(ev) {
      if (!drag) return;
      drag.to = localPoint(ev);
      if (Math.abs(drag.to.x - drag.from.x) + Math.abs(drag.to.y - drag.from.y) > 8) drag.moved = true;
      if (drag.moved && !drag.captured) {
        // La capture ne commence qu'au premier vrai glissement : un simple clic reste adressé au symbole touché.
        drag.captured = true;
        try { el.container.setPointerCapture(drag.id); } catch (e) { /* capture indisponible */ }
      }
      if (drag.moved) drawRect(drag.from, drag.to);
    }
    function onUp(ev) {
      if (!drag) return;
      var d = drag;
      drag = null;
      el.rect.hidden = true;
      if (d.captured) { try { el.container.releasePointerCapture(d.id); } catch (e) { /* déjà relâché */ } }
      map.dragging.enable();
      d.to = localPoint(ev);
      if (draw) { drawEnd(d); return; }
      if (!d.moved) return;
      var found = elementsAt(rectWorld(d.from, d.to));
      if (!d.additive) sel = {};
      found.forEach(function (e) { sel[refOf(e)] = true; });
      render();
      say(found.length ? pl(found.length, '%1 élément sélectionné.', '%1 éléments sélectionnés.') : 'Aucun élément dans ce rectangle.');
    }
    on(el.container, 'pointerdown', onDown, true);
    on(el.container, 'pointermove', onMove);
    on(el.container, 'pointerup', onUp);
    on(el.container, 'pointercancel', function () { drag = null; el.rect.hidden = true; map.dragging.enable(); });

    function setMode(on) {
      mode = !!on;
      el.container.classList.toggle('is-selecting', mode);
      var b = $('[data-map="select"]');
      if (b) { b.setAttribute('aria-pressed', String(mode)); b.classList.toggle('is-on', mode); }
      if (mode) say('Sélection : glissez sur la carte pour tracer un rectangle, cliquez un symbole pour l’ajouter ou le retirer.');
    }

    /* Zone dessinée pour la purge : rectangle (glisser) ou polygone (cliquer les sommets, puis Terminer). */
    function startDraw(done) {
      draw = { pts: [], rect: null, done: done };
      setMode(false);
      if (el.dlg.open) el.dlg.close();
      el.container.classList.add('is-selecting');
      el.selbar.hidden = true;
      el.drawbar.hidden = false;
      el.drawText.textContent = 'Dessinez la zone : glissez pour un rectangle, ou cliquez les sommets d’un polygone, puis Terminer.';
      drawLayer.clearLayers();
    }
    function drawRender() {
      drawLayer.clearLayers();
      var st = { color: '#f5b841', weight: 2, fillColor: '#f5b841', fillOpacity: 0.12, dashArray: '6 4', interactive: false };
      if (draw.rect) {
        var r = draw.rect;
        drawLayer.addLayer(L.rectangle([tm.toLL([r[0], r[1]]), tm.toLL([r[2], r[3]])], st));
      } else if (draw.pts.length) {
        var ll = draw.pts.map(function (p) { return tm.toLL(p); });
        drawLayer.addLayer(draw.pts.length > 2 ? L.polygon(ll, st) : L.polyline(ll, st));
        draw.pts.forEach(function (p) { drawLayer.addLayer(L.circleMarker(tm.toLL(p), { radius: 5, color: '#f5b841', fillOpacity: 1, interactive: false })); });
      }
    }
    function drawEnd(d) {
      if (d.moved) { draw.rect = rectWorld(d.from, d.to); draw.pts = []; } else { draw.rect = null; draw.pts.push(worldOf(d.to)); }
      drawRender();
    }
    function stopDraw(accept) {
      var d = draw;
      draw = null;
      el.container.classList.remove('is-selecting');
      el.drawbar.hidden = true;
      var zone = null;
      if (accept && d) {
        if (d.rect) zone = { type: 'rect', rect: d.rect };
        else if (d.pts.length >= 3) zone = { type: 'polygon', pts: d.pts };
      }
      if (!zone) drawLayer.clearLayers();
      if (d && d.done) d.done(zone);
      render();
    }

    /* ---------- Boîte de dialogue du poste ---------- */
    function openDlg() {
      lastFocus = document.activeElement;
      el.dlg.classList.remove('is-wide');
      el.dlgBody.className = 'toc-dlg-box';
      if (!el.dlg.open) el.dlg.showModal();
    }
    function closeDlg() { if (el.dlg.open) el.dlg.close(); }
    on(el.dlg, 'close', function () {
      if (P && P.loop) P.loop.stop = true;
      if (lastFocus && lastFocus.focus && document.contains(lastFocus)) { try { lastFocus.focus(); } catch (e) { /* élément disparu */ } }
    });
    function dlgHead(title, desc) { return '<h2 id="toc-dlg-title">' + esc(title) + '</h2><p class="toc-dlg-desc">' + esc(desc) + '</p>'; }

    /* ---------- Suppression (fiche, sélection, touche Suppr) ---------- */
    function summary(els) {
      var by = {};
      var pinned = 0, game = 0, ping = 0;
      els.forEach(function (e) { by[e.kind] = (by[e.kind] || 0) + 1; pinned += e.pinned ? 1 : 0; game += e.game ? 1 : 0; ping += e.table === 'ping' ? 1 : 0; });
      return { by: by, pinned: pinned, game: game, ping: ping };
    }
    function chips(by) {
      return '<ul class="toc-dlg-chips">' + Object.keys(by).map(function (k) { return '<li><b>' + by[k] + '</b> <span>' + (by[k] === 1 ? KIND[k] : KIND_PLURAL[k] || KIND[k]) + '</span></li>'; }).join('') + '</ul>';
    }
    function askDelete(refs, label) {
      var els = refs.map(function (r) { return byRef[r.table + ':' + r.id] || { table: r.table, id: r.id, kind: r.table === 'shape' ? 'form' : r.table, label: label || '', pinned: false, game: false }; });
      if (!els.length) return;
      var sm = summary(els);
      var n = els.length;
      var soft = opts.softDelete !== false;
      var title = n === 1 ? t('Supprimer « %1 » ?', [els[0].label || KIND[els[0].kind]]) : t('Supprimer %1 éléments ?', [n]);
      var h = dlgHead(title, soft ? 'Ils restent 15 minutes dans la corbeille : vous pouvez annuler la suppression.' : 'Le site n’est pas à jour : la suppression sera définitive, sans corbeille.');
      h += chips(sm.by);
      if (sm.game) h += '<p class="toc-dlg-note">Les marqueurs venus du jeu sont aussi retirés de la carte d’Arma, sans message.</p>';
      if (sm.pinned) h += '<p class="toc-dlg-note is-warn">' + pl(sm.pinned, '%1 élément est permanent : une confirmation distincte suivra.', '%1 éléments sont permanents : une confirmation distincte suivra.') + '</p>';
      if (sm.ping) h += '<p class="toc-dlg-note is-warn">Les pings supprimés ne reviennent pas.</p>';
      h += '<p class="toc-dlg-msg" id="toc-dlg-msg" role="alert"></p><div class="toc-dlg-actions"><button type="button" class="toc-btn" data-dlg="close">Annuler</button><button type="button" class="toc-btn is-danger" data-dlg="delete">' + (n === 1 ? 'Supprimer' : t('Supprimer %1 éléments', [n])) + '</button></div>';
      el.dlgBody.innerHTML = h;
      el.dlgBody._ctx = { refs: refs, pinned: sm.pinned, soft: soft, step: 'confirm' };
      openDlg();
      var b = $('[data-dlg="delete"]', el.dlgBody);
      if (b) b.focus();
    }
    function pinnedStep(ctx) {
      var h = dlgHead('Éléments permanents', pl(ctx.pinned, 'Un élément est épinglé : il reste d’habitude affiché d’un cycle à l’autre.', '%1 éléments sont épinglés : ils restent d’habitude affichés d’un cycle à l’autre.'));
      h += '<label class="toc-dlg-field"><span>Tapez PERMANENT pour les supprimer aussi</span><input class="toc-input" id="toc-dlg-phrase" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false"></label>';
      h += '<p class="toc-dlg-msg" id="toc-dlg-msg" role="alert"></p><div class="toc-dlg-actions"><button type="button" class="toc-btn" data-dlg="close">Annuler</button><button type="button" class="toc-btn is-danger" data-dlg="delete-pinned" disabled>Supprimer, permanents compris</button></div>';
      el.dlgBody.innerHTML = h;
      ctx.step = 'pinned';
      var inp = $('#toc-dlg-phrase', el.dlgBody);
      var go = $('[data-dlg="delete-pinned"]', el.dlgBody);
      inp.addEventListener('input', function () { go.disabled = inp.value.trim().toUpperCase() !== 'PERMANENT'; });
      inp.focus();
    }
    function doDelete(ctx, phrase) {
      var msg = $('#toc-dlg-msg', el.dlgBody);
      var btns = el.dlgBody.querySelectorAll('button');
      btns.forEach(function (b) { b.disabled = true; });
      call('/atak/toc/api/elements/delete', { items: ctx.refs, confirmPinned: phrase || '', hard: !ctx.soft }).then(function (j) {
        if (!j || !j.ok) {
          btns.forEach(function (b) { b.disabled = false; });
          if (j && j.error === 'pinned_confirm' && ctx.step !== 'pinned') { ctx.pinned = j.pinned || 1; pinnedStep(ctx); return; }
          if (msg) msg.textContent = errText(j);
          return;
        }
        closeDlg();
        sel = {};
        afterChange();
        if (j.soft) showUndo(j.batch, j.count, j.undoUntil, j.irreversible);
        else say(pl(j.count, '%1 élément supprimé définitivement.', '%1 éléments supprimés définitivement.'));
        if (j.forbidden) say(pl(j.forbidden, '%1 élément n’était pas à vous : il est resté.', '%1 éléments n’étaient pas à vous : ils sont restés.'), true);
      }).catch(function () { btns.forEach(function (b) { b.disabled = false; }); if (msg) msg.textContent = 'Réseau indisponible : rien n’a été supprimé.'; });
    }
    function askDeleteSelection() {
      var els = selected();
      if (els.length) askDelete(refsOf(els));
    }
    function afterChange() {
      opts.reload();
      loadTrash();
    }

    /* ---------- Annulation immédiate dans la ligne d'état ---------- */
    function showUndo(batch, n, until, irreversible) {
      undo = { batch: batch, n: n, until: until };
      el.undo.hidden = false;
      tickUndo();
      say(pl(n, '%1 élément supprimé.', '%1 éléments supprimés.'));
      if (irreversible) say(pl(irreversible, '%1 élément supprimé ne reviendra pas (ping ou piste).', '%1 éléments supprimés ne reviendront pas (pings ou pistes).'), false);
    }
    function tickUndo() {
      if (!undo) return;
      var left = undo.until - opts.now();
      if (left <= 0) { undo = null; el.undo.hidden = true; return; }
      el.undoText.textContent = t(undo.n === 1 ? '%1 élément supprimé · %2' : '%1 éléments supprimés · %2', [undo.n, mmss(left)]);
    }
    on(el.undoBtn, 'click', function () { if (undo) doUndo(undo.batch); });
    function doUndo(batch) {
      call('/atak/toc/api/elements/undo', { batch: batch }).then(function (j) {
        if (j && j.ok) {
          if (undo && undo.batch === batch) { undo = null; el.undo.hidden = true; }
          say(pl(j.restored, '%1 élément remis sur la carte.', '%1 éléments remis sur la carte.'));
          afterChange();
        } else say(errText(j), true);
      }).catch(function () { say('Réseau indisponible : rien n’a changé.', true); });
    }

    /* ---------- Corbeille ---------- */
    function loadTrash() {
      return call('/atak/toc/api/elements/trash').then(function (j) {
        if (!j || !j.ok) return;
        trash = { batches: j.batches || [], at: opts.now(), soft: j.soft };
        el.trashCount.textContent = trash.batches.reduce(function (a, b) { return a + b.count; }, 0);
        if (view === 'trash') renderTrash();
      }).catch(function () { /* la corbeille se relira au prochain passage */ });
    }
    function renderTrash() {
      var h = '<p class="toc-manage-desc">Les éléments supprimés restent 15 minutes ici, puis disparaissent pour de bon.</p>';
      if (!trash.batches.length) h += '<p class="toc-mg-empty">La corbeille est vide.</p>';
      trash.batches.forEach(function (b) {
        var left = Math.max(0, b.until - opts.now());
        var kinds = Object.keys(b.byKind).map(function (k) { return '<span><b>' + b.byKind[k] + '</b> ' + (b.byKind[k] === 1 ? KIND[k] : KIND_PLURAL[k]) + '</span>'; }).join('');
        h += '<div class="toc-trash-batch" data-batch="' + esc(b.batch) + '"><div class="toc-trash-head"><b>' + pl(b.count, '%1 élément', '%1 éléments') + '</b><span class="mono" data-left="' + b.until + '">' + mmss(left) + '</span></div>' +
          '<p class="toc-trash-kinds">' + kinds + '</p><ul class="toc-trash-items">' + b.items.slice(0, 5).map(function (it) { return '<li>' + esc(it.label || KIND[it.kind]) + (it.pinned ? ' <span class="toc-mg-pin">Permanent</span>' : '') + '</li>'; }).join('') + (b.count > 5 ? '<li class="toc-trash-more">+ ' + (b.count - 5) + '</li>' : '') + '</ul>' +
          '<button type="button" class="toc-btn is-small" data-mg-undo="' + esc(b.batch) + '"' + (b.canUndo && left > 0 ? '' : ' disabled') + '>Annuler la suppression</button></div>';
      });
      el.trashView.innerHTML = h;
    }
    function setView(v) {
      view = v;
      el.listView.hidden = v !== 'list';
      el.trashView.hidden = v !== 'trash';
      $('[data-mg="view-list"]').setAttribute('aria-selected', String(v === 'list'));
      $('[data-mg="view-trash"]').setAttribute('aria-selected', String(v === 'trash'));
      $('[data-mg="view-list"]').classList.toggle('is-on', v === 'list');
      $('[data-mg="view-trash"]').classList.toggle('is-on', v === 'trash');
      if (v === 'trash') { renderTrash(); loadTrash(); }
    }
    function openPanel(on) {
      el.panel.hidden = !on;
      el.btn.setAttribute('aria-expanded', String(on));
      el.btn.classList.toggle('is-on', on);
      if (on) { refresh(); loadTrash(); if (opts.closeLayers) opts.closeLayers(); }
    }

    /* ---------- Épingler, archiver ---------- */
    function pinSelection() {
      var els = selected();
      if (!els.length) return;
      var want = els.some(function (e) { return !e.pinned; });
      call('/atak/toc/api/elements/pin', { items: refsOf(els), pinned: want }).then(function (j) {
        if (!j || !j.ok) { say(errText(j), true); return; }
        say(want ? pl(j.count, '%1 élément épinglé : il reste après la bascule du cycle.', '%1 éléments épinglés : ils restent après la bascule du cycle.') : pl(j.count, '%1 élément désépinglé : il sera archivé à la prochaine bascule.', '%1 éléments désépinglés : ils seront archivés à la prochaine bascule.'));
        opts.reload();
      }).catch(function () { say('Réseau indisponible : rien n’a changé.', true); });
    }
    function archiveSelection(phrase) {
      var els = selected();
      if (!els.length) return;
      call('/atak/toc/api/elements/archive', { items: refsOf(els), confirmPinned: phrase || '' }).then(function (j) {
        if (j && j.error === 'pinned_confirm') {
          var ctx = { refs: refsOf(els), pinned: j.pinned, soft: true, step: 'archive' };
          pinnedStep(ctx);
          el.dlgBody._ctx = ctx;
          $('[data-dlg="delete-pinned"]', el.dlgBody).setAttribute('data-dlg', 'archive-pinned');
          $('[data-dlg="archive-pinned"]', el.dlgBody).textContent = 'Archiver, permanents compris';
          openDlg();
          return;
        }
        if (!j || !j.ok) { say(errText(j), true); return; }
        closeDlg();
        sel = {};
        say(pl(j.count, '%1 élément archivé dans la mission : il a quitté la carte.', '%1 éléments archivés dans la mission : ils ont quitté la carte.'));
        afterChange();
      }).catch(function () { say('Réseau indisponible : rien n’a changé.', true); });
    }

    /* ---------- Purge ---------- */
    function newPurge(running) {
      P = {
        types: { marker: true, form: true, route: true, zone: true, ping: false, track: false }, authors: '', age: '', unit: 'h', zoneMode: 'all', zone: null,
        cycleOnly: false, archive: true, keepPinned: true, preview: null, loop: null, job: running || null, err: '', authorsList: null, token: 0
      };
    }
    function scopeOf() {
      var types = PURGE_TYPES.filter(function (t) { return P.types[t]; });
      var age = parseInt(P.age, 10);
      var zone = null;
      if (P.zoneMode === 'screen') {
        var b = map.getBounds();
        var a = tm.toWorld(b.getSouthWest()), c = tm.toWorld(b.getNorthEast());
        zone = { type: 'rect', rect: [Math.min(a[0], c[0]), Math.min(a[1], c[1]), Math.max(a[0], c[0]), Math.max(a[1], c[1])] };
      } else if (P.zoneMode === 'drawn' && P.zone) zone = P.zone;
      return {
        types: types, authors: P.authors.split(',').map(function (s) { return s.trim(); }).filter(Boolean), olderThanHours: age > 0 ? age * (P.unit === 'd' ? 24 : 1) : null,
        zone: zone, cycleOnly: P.cycleOnly, archiveFirst: P.archive, keepPinned: P.keepPinned
      };
    }
    function openPurge() {
      if (!opts.canPurge) { say('La purge demande le droit « purger le poste TOC web ».', true); return; }
      if (opts.softDelete === false) { say(ERR.unavailable, true); return; }
      newPurge(null);
      openDlg();
      el.dlgBody.innerHTML = '<p class="toc-dlg-desc">Lecture de la carte…</p>';
      call('/atak/toc/api/purge/status').then(function (j) {
        if (j && j.running) { P.job = j.running; progressView(); runLoop(); return; }
        purgeView();
        call('/atak/toc/api/purge/authors').then(function (a) { if (a && a.ok) { P.authorsList = a; fillAuthors(); } }).catch(function () { /* liste facultative */ });
        previewNow();
      }).catch(function () { purgeView(); previewNow(); });
    }
    function fillAuthors() {
      var dl = $('#toc-authors-dl', el.dlgBody);
      if (!dl || !P.authorsList) return;
      dl.innerHTML = (P.authorsList.authors || []).concat(P.authorsList.units || []).map(function (a) { return '<option value="' + esc(a.name) + '">' + esc(a.count) + '</option>'; }).join('');
    }
    function purgeView() {
      el.dlg.classList.add('is-wide');
      el.dlgBody.className = 'toc-dlg-box is-split';
      var h = '<div class="toc-dlg-head">' + dlgHead('Purger la carte', 'Supprime en masse ce que vous choisissez, après un aperçu exact. Rien n’est supprimé avant votre confirmation.') + '</div>';
      h += '<div class="toc-dlg-main"><div class="toc-purge-layout"><div class="toc-purge-scope">';
      h += '<div class="toc-purge-types" role="group" aria-label="Types d’éléments">' + PURGE_TYPES.map(function (t) {
        return '<label class="toc-chip-check"><input type="checkbox" data-pg-type="' + t + '"' + (P.types[t] ? ' checked' : '') + '><span>' + KIND_PLURAL[t] + '</span><b class="mono" data-pg-count="' + t + '">–</b></label>';
      }).join('') + '</div>';
      h += '<div class="toc-purge-grid">';
      h += '<label class="toc-dlg-field"><span>Auteur ou unité</span><input class="toc-input" placeholder="Indicatif, unité (séparés par des virgules)" id="toc-pg-authors" list="toc-authors-dl" autocomplete="off"><datalist id="toc-authors-dl"></datalist></label>';
      h += '<div class="toc-dlg-field"><span>Plus ancien que</span><div class="toc-purge-age"><input class="toc-input" aria-label="Âge minimal" placeholder="–" id="toc-pg-age" type="number" min="1" max="8760" inputmode="numeric"><select class="toc-select" aria-label="Unité" id="toc-pg-unit">' + opt('h', 'heures', P.unit === 'h') + opt('d', 'jours', P.unit === 'd') + '</select></div></div>';
      h += '</div>';
      h += '<fieldset class="toc-purge-zone"><legend>Zone</legend>' +
        [['all', 'Toute la carte'], ['screen', 'Ce que l’écran montre'], ['drawn', P.zone ? 'Zone dessinée' : 'Zone à dessiner']].map(function (z) {
          return '<label class="toc-radio"><input type="radio" name="toc-pg-zone" value="' + z[0] + '"' + (P.zoneMode === z[0] ? ' checked' : '') + '><span>' + z[1] + '</span></label>';
        }).join('') + '<button type="button" class="toc-btn is-small" data-pg="draw">Dessiner la zone…</button></fieldset>';
      h += '<label class="toc-radio"><input type="checkbox" id="toc-pg-cycle"' + (P.cycleOnly ? ' checked' : '') + '><span>Seulement ce qui date du cycle en cours</span></label>';
      h += '<label class="toc-radio"><input type="checkbox" id="toc-pg-archive"' + (P.archive ? ' checked' : '') + '><span>Archiver la mission avant de purger</span></label>';
      h += '<label class="toc-radio"><input type="checkbox" id="toc-pg-keep"' + (P.keepPinned ? ' checked' : '') + '><span>Conserver les éléments épinglés</span></label>';
      h += '</div><div class="toc-purge-side"><div class="toc-purge-preview" id="toc-pg-preview" aria-live="polite"></div></div></div></div>';
      h += '<div class="toc-dlg-foot"><label class="toc-dlg-field"><span id="toc-pg-phrase-label">Tapez PURGER pour confirmer</span><input class="toc-input" id="toc-pg-phrase" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false"></label>';
      h += '<div class="toc-dlg-actions"><button type="button" class="toc-btn" data-dlg="close">Annuler</button><button type="button" class="toc-btn is-danger" data-pg="start" disabled>Purger</button></div><p class="toc-dlg-msg" id="toc-dlg-msg" role="alert"></p></div>';
      el.dlgBody.innerHTML = h;
      $('#toc-pg-authors', el.dlgBody).value = P.authors;
      $('#toc-pg-age', el.dlgBody).value = P.age;
      fillAuthors();
      renderPreview();
    }
    var previewTimer = null;
    function previewSoon() { clearTimeout(previewTimer); previewTimer = setTimeout(previewNow, 350); renderPreview(true); }
    function previewNow() {
      clearTimeout(previewTimer);
      var scope = scopeOf();
      var token = ++P.token;
      if (!scope.types.length) { P.preview = null; P.err = ERR.types_required; renderPreview(); return; }
      call('/atak/toc/api/purge/preview', { scope: scope }).then(function (j) {
        if (!P || token !== P.token) return;
        P.preview = j && j.ok ? j : null;
        P.err = j && j.ok ? '' : errText(j);
        renderPreview();
      }).catch(function () { if (P && token === P.token) { P.preview = null; P.err = 'Réseau indisponible : aperçu impossible.'; renderPreview(); } });
    }
    function renderPreview(loading) {
      var box = $('#toc-pg-preview', el.dlgBody);
      if (!box) return;
      var pv = P.preview;
      PURGE_TYPES.forEach(function (t) {
        var c = $('[data-pg-count="' + t + '"]', el.dlgBody);
        if (c) c.textContent = pv && P.types[t] ? String((pv.byKind || {})[t] || 0) : '–';
      });
      var h = '';
      if (loading) h = '<p class="toc-purge-wait">Calcul de l’aperçu…</p>';
      else if (P.err) h = '<p class="toc-dlg-note is-warn">' + esc(P.err) + '</p>';
      else if (pv) {
        h = '<div class="toc-purge-total"><b>' + pv.total + '</b><span>' + (pv.total === 1 ? 'élément sera supprimé' : 'éléments seront supprimés') + '</span></div><ul class="toc-purge-rows">';
        Object.keys(pv.byKind).forEach(function (k) { if (pv.byKind[k]) h += '<li><span>' + KIND_PLURAL[k] + '</span><b class="mono">' + pv.byKind[k] + '</b></li>'; });
        if (pv.pinnedKept) h += '<li class="is-keep"><span>Épinglés conservés</span><b class="mono">' + pv.pinnedKept + '</b></li>';
        if (pv.pinnedIncluded) h += '<li class="is-warn"><span>Épinglés supprimés aussi</span><b class="mono">' + pv.pinnedIncluded + '</b></li>';
        h += '</ul>';
        if (pv.irreversible) h += '<p class="toc-dlg-note is-warn">' + pl(pv.irreversible, '%1 ping ou piste ne reviendra pas.', '%1 pings ou pistes ne reviendront pas.') + '</p>';
        if (P.archive && !pv.archiveReady) h += '<p class="toc-dlg-note is-warn">' + ERR.archive_unavailable + '</p>';
        if (pv.capped) h += '<p class="toc-dlg-note is-warn">' + t('Plus de %1 éléments : réduisez le périmètre.', [pv.max]) + '</p>';
        h += '<p class="toc-dlg-note">' + (P.archive ? '<span>La mission est archivée avant toute suppression.</span> ' : '') + '<span>Marqueurs et tracés restent 15 minutes dans la corbeille.</span></p>';
      }
      box.innerHTML = h;
      var ok = !loading && pv && pv.total > 0 && !pv.capped && !(P.archive && !pv.archiveReady);
      var inp = $('#toc-pg-phrase', el.dlgBody);
      var go = $('[data-pg="start"]', el.dlgBody);
      if (go) {
        go.disabled = !(ok && inp && inp.value.trim().toUpperCase() === 'PURGER');
        go.textContent = ok ? pl(pv.total, 'Purger %1 élément', 'Purger %1 éléments') : 'Purger';
      }
      if (pv && $('#toc-pg-phrase-label', el.dlgBody)) $('#toc-pg-phrase-label', el.dlgBody).textContent = pv.wholeMap ? 'Toute la carte est concernée : tapez PURGER pour confirmer' : 'Tapez PURGER pour confirmer';
    }
    function startPurge() {
      var msg = $('#toc-dlg-msg', el.dlgBody);
      var go = $('[data-pg="start"]', el.dlgBody);
      go.disabled = true;
      call('/atak/toc/api/purge/start', { scope: scopeOf(), confirm: $('#toc-pg-phrase', el.dlgBody).value.trim(), expected: P.preview ? P.preview.total : -1 }).then(function (j) {
        if (j && j.ok) { P.job = j.job; progressView(); runLoop(); return; }
        if (j && j.error === 'preview_changed') { previewNow(); }
        if (j && j.error === 'purge_running' && j.job) { P.job = j.job; progressView(); runLoop(); return; }
        if (msg) msg.textContent = errText(j);
        go.disabled = false;
      }).catch(function () { if (msg) msg.textContent = 'Réseau indisponible : rien n’a été supprimé.'; go.disabled = false; });
    }
    function progressView() {
      var j = P.job;
      var running = j.status === 'archiving' || j.status === 'deleting';
      var title = running ? 'Purge en cours' : (j.status === 'done' ? 'Purge terminée' : (j.status === 'cancelled' ? 'Purge interrompue' : 'Purge arrêtée'));
      var desc = running ? 'Les lots de 500 éléments se suivent : vous pouvez fermer cette fenêtre, la purge se reprend depuis le bouton Purger.' : (j.status === 'done' ? 'La carte a été purgée. Marqueurs et tracés restent 15 minutes dans la corbeille.' : (j.status === 'failed' ? (ERR[j.error] || 'La purge s’est arrêtée.') : 'Ce qui a déjà été supprimé reste dans la corbeille pendant 15 minutes.'));
      var phase = j.status === 'archiving' ? 'Archivage de la mission avant suppression…' : (j.status === 'deleting' ? 'Suppression par lots…' : '');
      var h = dlgHead(title, desc);
      h += '<div class="toc-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + j.progress + '"><i style="width:' + j.progress + '%"></i></div>';
      if (phase) h += '<p class="toc-progress-phase">' + phase + '</p>';
      h += '<ul class="toc-purge-rows"><li><span>Archivés dans la mission</span><b class="mono">' + j.archived + '</b></li><li><span>Supprimés</span><b class="mono">' + j.deleted + ' / ' + j.total + '</b></li>' + (j.keptPinned ? '<li class="is-keep"><span>Épinglés conservés</span><b class="mono">' + j.keptPinned + '</b></li>' : '') + '</ul>';
      h += '<p class="toc-dlg-msg" id="toc-dlg-msg" role="alert"></p><div class="toc-dlg-actions">';
      if (running) h += '<button type="button" class="toc-btn" data-pg="cancel">Interrompre</button><button type="button" class="toc-btn is-primary" data-pg="resume" hidden>Reprendre</button>';
      else {
        if (j.deleted > 0 && j.undoUntil > opts.now()) h += '<button type="button" class="toc-btn" data-pg="undo">Annuler la purge</button>';
        h += '<button type="button" class="toc-btn is-primary" data-dlg="close">Fermer</button>';
      }
      h += '</div>';
      el.dlgBody.innerHTML = h;
    }
    function runLoop() {
      var loop = { stop: false };
      P.loop = loop;
      (function next() {
        if (loop.stop || dead) return;
        call('/atak/toc/api/purge/step', { job: P.job.id }).then(function (j) {
          if (loop.stop) return;
          if (!j || !j.ok) {
            var m = $('#toc-dlg-msg', el.dlgBody);
            if (m) m.textContent = errText(j);
            var rs = $('[data-pg="resume"]', el.dlgBody);
            if (rs) rs.hidden = false;
            return;
          }
          P.job = j.job;
          if (el.dlg.open) progressView();
          if (j.job.status === 'archiving' || j.job.status === 'deleting') { setTimeout(next, j.busy ? 1200 : 60); return; }
          afterChange();
          say(j.job.status === 'done' ? pl(j.job.deleted, '%1 élément purgé.', '%1 éléments purgés.') : 'Purge arrêtée.', j.job.status === 'failed');
        }).catch(function () {
          if (loop.stop) return;
          var m = $('#toc-dlg-msg', el.dlgBody);
          if (m) m.textContent = 'Connexion perdue : la purge reprendra là où elle s’est arrêtée.';
          var rs = $('[data-pg="resume"]', el.dlgBody);
          if (rs) rs.hidden = false;
        });
      })();
    }

    /* ---------- Panneau, barre, dialogue : événements ---------- */
    on(el.panel, 'click', function (e) {
      var b = e.target.closest('[data-mg],[data-mg-center],[data-mg-undo]');
      if (!b) return;
      if (b.hasAttribute('data-mg-center')) { var it = byRef[b.closest('li').getAttribute('data-ref')]; if (it && it.pos) tm.centerOn(it.pos, Math.max(map.getZoom(), map.getMaxZoom() - 3)); return; }
      if (b.hasAttribute('data-mg-undo')) { doUndo(b.getAttribute('data-mg-undo')); return; }
      var a = b.getAttribute('data-mg');
      if (a === 'close') openPanel(false);
      else if (a === 'view-list') setView('list');
      else if (a === 'view-trash') setView('trash');
      else if (a === 'purge') openPurge();
    });
    on(el.panel, 'change', function (e) {
      var t = e.target;
      if (t.hasAttribute('data-mg-sel')) setSel([t.getAttribute('data-mg-sel')], t.checked);
      else if (t.getAttribute('data-mg') === 'all') setSel(visibleList().filter(function (x) { return x.visible; }).slice(0, 400).map(refOf), t.checked);
      else if (t.hasAttribute('data-mg-filter')) { filter[t.getAttribute('data-mg-filter')] = t.value; render(); }
    });
    on(el.panel, 'input', function (e) { if (e.target.hasAttribute('data-mg-filter')) { filter[e.target.getAttribute('data-mg-filter')] = e.target.value; render(); } });
    on(el.btn, 'click', function () { openPanel(el.panel.hidden); });
    on(el.selbar, 'click', function (e) {
      var b = e.target.closest('[data-sb]');
      if (!b) return;
      var a = b.getAttribute('data-sb');
      if (a === 'delete') askDeleteSelection();
      else if (a === 'pin') pinSelection();
      else if (a === 'archive') archiveSelection();
      else if (a === 'clear') clearSel();
    });
    on(el.drawbar, 'click', function (e) {
      var b = e.target.closest('[data-db]');
      if (b) stopDraw(b.getAttribute('data-db') === 'done');
    });
    on(el.dlg, 'click', function (e) {
      var b = e.target.closest('[data-dlg],[data-pg]');
      if (!b || b.disabled) return;
      var ctx = el.dlgBody._ctx;
      var a = b.getAttribute('data-dlg');
      if (a === 'close') closeDlg();
      else if (a === 'delete') {
        if (ctx.pinned > 0 && ctx.step === 'confirm') pinnedStep(ctx); else doDelete(ctx, '');
      } else if (a === 'delete-pinned') doDelete(ctx, $('#toc-dlg-phrase', el.dlgBody).value.trim());
      else if (a === 'archive-pinned') archiveSelection($('#toc-dlg-phrase', el.dlgBody).value.trim());
      var p = b.getAttribute('data-pg');
      if (p === 'start') startPurge();
      else if (p === 'draw') {
        startDraw(function (zone) {
          if (zone) { P.zone = zone; P.zoneMode = 'drawn'; }
          openDlg();
          purgeView();
          previewNow();
        });
      } else if (p === 'cancel') {
        if (P.loop) P.loop.stop = true;
        call('/atak/toc/api/purge/cancel', { job: P.job.id }).then(function (j) { if (j && j.ok) { P.job = j.job; progressView(); afterChange(); } else { var m = $('#toc-dlg-msg', el.dlgBody); if (m) m.textContent = errText(j); } });
      } else if (p === 'resume') runLoop();
      else if (p === 'undo') doUndo(P.job.batch);
    });
    on(el.dlg, 'input', function (e) {
      var t = e.target;
      if (!P) return;
      if (t.id === 'toc-pg-phrase') { renderPreview(); return; }
      if (t.id === 'toc-pg-authors') { P.authors = t.value; previewSoon(); }
      else if (t.id === 'toc-pg-age') { P.age = t.value; previewSoon(); }
    });
    on(el.dlg, 'change', function (e) {
      var t = e.target;
      if (!P) return;
      if (t.hasAttribute('data-pg-type')) { P.types[t.getAttribute('data-pg-type')] = t.checked; previewSoon(); }
      else if (t.name === 'toc-pg-zone') { P.zoneMode = t.value; if (t.value === 'drawn' && !P.zone) { $('[data-pg="draw"]', el.dlgBody).click(); return; } previewSoon(); }
      else if (t.id === 'toc-pg-unit') { P.unit = t.value; previewSoon(); }
      else if (t.id === 'toc-pg-cycle') { P.cycleOnly = t.checked; previewSoon(); }
      else if (t.id === 'toc-pg-archive') { P.archive = t.checked; previewSoon(); }
      else if (t.id === 'toc-pg-keep') { P.keepPinned = t.checked; previewSoon(); }
    });

    /* ---------- Actions proposées dans la bulle d'un tracé ---------- */
    function elementActions(ref) {
      var r = ref.table + ':' + ref.id;
      var act = function (name, label, cls) { return '<button type="button" class="toc-btn is-small' + cls + '" data-el-act="' + name + '" data-el="' + esc(r) + '">' + label + '</button>'; };
      return '<div class="toc-pop-actions">' + act('select', 'Sélectionner', '') + act('delete', 'Supprimer', ' is-danger') + '</div>';
    }
    function onDocClick(e) {
      var b = e.target.closest && e.target.closest('[data-el-act]');
      if (!b) return;
      var r = b.getAttribute('data-el');
      var parts = r.split(':');
      var ref = { table: parts[0], id: Number(parts[1]) };
      map.closePopup();
      if (b.getAttribute('data-el-act') === 'select') toggle(r); else askDelete([ref]);
    }
    on(document, 'click', onDocClick);

    /* ---------- Touche Suppr, mise à jour en direct ---------- */
    function onKey(e) {
      if (dead || e.key !== 'Delete' && e.key !== 'Suppr') return;
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || el.dlg.open) return;
      if (count()) { e.preventDefault(); askDeleteSelection(); return; }
      var pick = opts.picked ? opts.picked() : null;
      if (pick) { e.preventDefault(); askDelete([pick.ref], pick.label); }
    }
    on(document, 'keydown', onKey);
    /* Révision de la carte lue avec la salle (toutes les 3 s) : un autre poste a supprimé, annulé ou purgé. */
    function applyRoom(m) {
      if (!m) return;
      if (lastRev === null) { lastRev = m.rev; return; }
      if (m.rev === lastRev) return;
      lastRev = m.rev;
      opts.reload();
      if (!el.panel.hidden) loadTrash();
      var ev = m.event;
      if (ev && ev.byId !== opts.userId && opts.now() - ev.at < 60) {
        var by = ev.by || 'Un opérateur';
        if (ev.what === 'undo') say(t(ev.count === 1 ? '%1 a remis 1 élément sur la carte.' : '%1 a remis %2 éléments sur la carte.', [by, ev.count]));
        else if (ev.what === 'archive') say(t(ev.count === 1 ? '%1 a archivé 1 élément.' : '%1 a archivé %2 éléments.', [by, ev.count]));
        else say(t(ev.count === 1 ? '%1 a supprimé 1 élément.' : '%1 a supprimé %2 éléments.', [by, ev.count]));
      }
    }
    timers.push(setInterval(function () {
      tickUndo();
      if (view === 'trash' && !el.panel.hidden) {
        Array.prototype.forEach.call(el.trashView.querySelectorAll('[data-left]'), function (n) { n.textContent = mmss(Number(n.getAttribute('data-left')) - opts.now()); });
      }
    }, 1000));

    /* Droits et mode dégradé affichés dans le panneau. */
    el.purgeBtn.setAttribute('aria-disabled', String(!opts.canPurge || opts.softDelete === false));
    el.purgeHint.textContent = opts.softDelete === false ? 'Mise à jour du site à faire : la purge est désactivée et la suppression est définitive.' : (opts.canPurge ? '' : 'Réservé au droit « purger le poste TOC web ».');
    refresh();
    loadTrash();

    return {
      refresh: refresh,
      applyRoom: applyRoom,
      mode: function () { return mode; },
      setMode: setMode,
      toggleMode: function () { setMode(!mode); },
      toggleFeature: function (it) { if (it && it.data && it.data.type === 'marker' && byRef['marker:' + it.data.id]) toggle('marker:' + it.data.id); },
      askDelete: askDelete,
      selectionCount: count,
      elementActions: elementActions,
      openPanel: openPanel,
      panelOpen: function () { return !el.panel.hidden; },
      busy: function () { return !!draw || el.dlg.open; },
      escape: function () {
        if (draw) { stopDraw(false); return true; }
        if (mode) { setMode(false); return true; }
        if (count()) { clearSel(); return true; }
        if (!el.panel.hidden) { openPanel(false); return true; }
        return false;
      },
      destroy: function () {
        dead = true;
        timers.forEach(clearInterval);
        offs.forEach(function (off) { off(); });
        if (el.dlg.open) el.dlg.close();
        map.removeLayer(hi);
        map.removeLayer(drawLayer);
        el.container.classList.remove('is-selecting');
        el.selbar.hidden = true;
        el.panel.hidden = true;
      }
    };
  }

  return { create: create };
})();
