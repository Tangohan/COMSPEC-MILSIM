/* COMSPEC ATAK — Menu contextuel carte (clic droit) */
window.ATAKContextMenu = (function () {
  var menuEl = null;
  var promptEl = null;
  var markerFormEl = null;
  var lastLatLng = null;
  var boundMap = null;
  var drawMode = null;
  var drawPoints = [];
  var drawPreview = null;
  var hintEl = null;
  var promptResolve = null;
  var markerFormResolve = null;

  var MARKER_COLORS = [
    { value: '#34d399', label: 'Vert' },
    { value: '#ef4444', label: 'Rouge' },
    { value: '#eab308', label: 'Jaune' },
    { value: '#60a5fa', label: 'Bleu' },
    { value: '#f97316', label: 'Orange' },
    { value: '#a78bfa', label: 'Violet' },
    { value: '#f8fafc', label: 'Blanc' }
  ];
  var MARKER_ICONS = [
    { value: 'dot', label: 'Point' },
    { value: 'pin', label: 'Épingle' },
    { value: 'flag', label: 'Drapeau' },
    { value: 'warning', label: 'Alerte' },
    { value: 'target', label: 'Cible' }
  ];
  var MARKER_SIZES = [
    { value: 'sm', label: 'Petit' },
    { value: 'md', label: 'Moyen' },
    { value: 'lg', label: 'Grand' }
  ];

  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function getApiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
  }

  function getMapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }

  function getAuthor() {
    var u = window.ATAK_USER;
    if (u && (u.callsign || u.displayName)) return u.callsign || u.displayName;
    return 'Opérateur';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function gridLabel(latlng) {
    if (!latlng) return '—';
    return Math.round(latlng.lng) + ' / ' + Math.round(latlng.lat);
  }

  function ensureMenu() {
    if (menuEl) return menuEl;
    menuEl = document.createElement('div');
    menuEl.id = 'atak-ctx-menu';
    menuEl.className = 'atak-ctx-menu';
    menuEl.setAttribute('role', 'menu');
    menuEl.hidden = true;
    menuEl.innerHTML =
      '<div class="atak-ctx-menu__coords" id="atak-ctx-coords"></div>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="marker" role="menuitem">Placer un marqueur</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="ping" role="menuitem">Envoyer un ping</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="line" role="menuitem">Tracer un trait</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="comment" role="menuitem">Ajouter un commentaire</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="zone" role="menuitem">Zone circulaire</button>' +
      '<div class="atak-ctx-menu__sep" role="separator"></div>' +
      '<button type="button" class="atak-ctx-menu__item atak-ctx-menu__item--muted" data-action="copy" role="menuitem">Copier les coordonnées</button>';
    document.body.appendChild(menuEl);
    menuEl.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-action]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      var action = btn.getAttribute('data-action');
      hideMenu();
      runAction(action);
    });
    return menuEl;
  }

  function ensurePrompt() {
    if (promptEl) return promptEl;
    promptEl = document.createElement('div');
    promptEl.id = 'atak-input-modal';
    promptEl.className = 'atak-input-modal';
    promptEl.hidden = true;
    promptEl.setAttribute('aria-hidden', 'true');
    promptEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-atak-prompt-cancel></div>' +
      '<div class="atak-input-modal__box" role="dialog" aria-modal="true" aria-labelledby="atak-input-modal-title">' +
      '<h3 class="atak-input-modal__title" id="atak-input-modal-title"></h3>' +
      '<p class="atak-input-modal__hint" id="atak-input-modal-hint"></p>' +
      '<input type="text" class="atak-input-modal__field" id="atak-input-modal-field" maxlength="200" autocomplete="off" />' +
      '<div class="atak-input-modal__actions">' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-atak-prompt-cancel>Annuler</button>' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--primary" id="atak-input-modal-ok">Valider</button>' +
      '</div></div>';
    document.body.appendChild(promptEl);

    function cancel() {
      closePrompt(null);
    }
    promptEl.querySelectorAll('[data-atak-prompt-cancel]').forEach(function (el) {
      el.addEventListener('click', cancel);
    });
    document.getElementById('atak-input-modal-ok').addEventListener('click', function () {
      var field = document.getElementById('atak-input-modal-field');
      closePrompt(field ? field.value : '');
    });
    document.getElementById('atak-input-modal-field').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        closePrompt(this.value);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closePrompt(null);
      }
    });
    return promptEl;
  }

  function openPrompt(title, hint, placeholder, defaultValue) {
    ensurePrompt();
    var titleEl = document.getElementById('atak-input-modal-title');
    var hintElLocal = document.getElementById('atak-input-modal-hint');
    var field = document.getElementById('atak-input-modal-field');
    if (titleEl) titleEl.textContent = title || '';
    if (hintElLocal) {
      hintElLocal.textContent = hint || '';
      hintElLocal.hidden = !hint;
    }
    if (field) {
      field.value = defaultValue != null ? String(defaultValue) : '';
      field.placeholder = placeholder || '';
    }
    promptEl.hidden = false;
    promptEl.setAttribute('aria-hidden', 'false');
    setTimeout(function () { if (field) field.focus(); }, 30);
    return new Promise(function (resolve) {
      promptResolve = resolve;
    });
  }

  function closePrompt(value) {
    if (!promptEl) return;
    promptEl.hidden = true;
    promptEl.setAttribute('aria-hidden', 'true');
    var cb = promptResolve;
    promptResolve = null;
    if (cb) cb(value);
  }

  function optionButtons(name, items, selected) {
    return items.map(function (it) {
      var checked = it.value === selected ? ' checked' : '';
      var swatch = name === 'color'
        ? '<span class="atak-marker-form__swatch" style="background:' + it.value + '"></span>'
        : '';
      return '<label class="atak-marker-form__choice">' +
        '<input type="radio" name="' + name + '" value="' + escapeHtml(it.value) + '"' + checked + ' />' +
        swatch + '<span>' + escapeHtml(it.label) + '</span></label>';
    }).join('');
  }

  function ensureMarkerForm() {
    if (markerFormEl) return markerFormEl;
    markerFormEl = document.createElement('div');
    markerFormEl.id = 'atak-marker-modal';
    markerFormEl.className = 'atak-input-modal';
    markerFormEl.hidden = true;
    markerFormEl.setAttribute('aria-hidden', 'true');
    markerFormEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-atak-marker-cancel></div>' +
      '<div class="atak-input-modal__box atak-marker-form" role="dialog" aria-modal="true" aria-labelledby="atak-marker-modal-title">' +
      '<h3 class="atak-input-modal__title" id="atak-marker-modal-title">Nouveau marqueur</h3>' +
      '<p class="atak-input-modal__hint" id="atak-marker-modal-coords"></p>' +
      '<label class="atak-marker-form__label">Libellé' +
      '<input type="text" class="atak-input-modal__field" id="atak-marker-label" maxlength="80" placeholder="Ex. point de ralliement" autocomplete="off" /></label>' +
      '<label class="atak-marker-form__label">Description' +
      '<textarea class="atak-marker-form__textarea" id="atak-marker-desc" maxlength="300" rows="2" placeholder="Précisions utiles pour l’équipe (optionnel)"></textarea></label>' +
      '<fieldset class="atak-marker-form__fieldset"><legend>Couleur</legend><div class="atak-marker-form__choices" id="atak-marker-colors"></div></fieldset>' +
      '<fieldset class="atak-marker-form__fieldset"><legend>Icône</legend><div class="atak-marker-form__choices" id="atak-marker-icons"></div></fieldset>' +
      '<fieldset class="atak-marker-form__fieldset"><legend>Taille</legend><div class="atak-marker-form__choices" id="atak-marker-sizes"></div></fieldset>' +
      '<div class="atak-input-modal__actions">' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-atak-marker-cancel>Annuler</button>' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--primary" id="atak-marker-ok">Placer</button>' +
      '</div></div>';
    document.body.appendChild(markerFormEl);

    document.getElementById('atak-marker-colors').innerHTML = optionButtons('color', MARKER_COLORS, '#34d399');
    document.getElementById('atak-marker-icons').innerHTML = optionButtons('icon', MARKER_ICONS, 'pin');
    document.getElementById('atak-marker-sizes').innerHTML = optionButtons('size', MARKER_SIZES, 'md');

    function cancel() { closeMarkerForm(null); }
    markerFormEl.querySelectorAll('[data-atak-marker-cancel]').forEach(function (el) {
      el.addEventListener('click', cancel);
    });
    document.getElementById('atak-marker-ok').addEventListener('click', function () {
      closeMarkerForm(readMarkerForm());
    });
    document.getElementById('atak-marker-label').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        closeMarkerForm(readMarkerForm());
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closeMarkerForm(null);
      }
    });
    return markerFormEl;
  }

  function readMarkerForm() {
    var labelEl = document.getElementById('atak-marker-label');
    var descEl = document.getElementById('atak-marker-desc');
    var color = (markerFormEl.querySelector('input[name="color"]:checked') || {}).value || '#34d399';
    var icon = (markerFormEl.querySelector('input[name="icon"]:checked') || {}).value || 'pin';
    var size = (markerFormEl.querySelector('input[name="size"]:checked') || {}).value || 'md';
    return {
      label: labelEl ? labelEl.value : '',
      description: descEl ? descEl.value : '',
      color: color,
      icon: icon,
      size: size
    };
  }

  function openMarkerForm(latlng) {
    ensureMarkerForm();
    var coordsEl = document.getElementById('atak-marker-modal-coords');
    if (coordsEl) coordsEl.textContent = 'Position grille : ' + gridLabel(latlng);
    var labelEl = document.getElementById('atak-marker-label');
    var descEl = document.getElementById('atak-marker-desc');
    if (labelEl) labelEl.value = 'Marqueur';
    if (descEl) descEl.value = '';
    var colorDef = markerFormEl.querySelector('input[name="color"][value="#34d399"]');
    var iconDef = markerFormEl.querySelector('input[name="icon"][value="pin"]');
    var sizeDef = markerFormEl.querySelector('input[name="size"][value="md"]');
    if (colorDef) colorDef.checked = true;
    if (iconDef) iconDef.checked = true;
    if (sizeDef) sizeDef.checked = true;
    markerFormEl.hidden = false;
    markerFormEl.setAttribute('aria-hidden', 'false');
    setTimeout(function () {
      if (labelEl) { labelEl.focus(); labelEl.select(); }
    }, 30);
    return new Promise(function (resolve) {
      markerFormResolve = resolve;
    });
  }

  function closeMarkerForm(value) {
    if (!markerFormEl) return;
    markerFormEl.hidden = true;
    markerFormEl.setAttribute('aria-hidden', 'true');
    var cb = markerFormResolve;
    markerFormResolve = null;
    if (cb) cb(value);
  }

  function ensureHint() {
    if (hintEl) return hintEl;
    hintEl = document.createElement('div');
    hintEl.id = 'atak-draw-hint';
    hintEl.className = 'atak-draw-hint';
    hintEl.hidden = true;
    hintEl.innerHTML =
      '<div class="atak-draw-hint__text" id="atak-draw-hint-text"></div>' +
      '<div class="atak-draw-hint__actions" id="atak-draw-hint-actions">' +
      '<button type="button" class="atak-draw-hint__btn atak-draw-hint__btn--primary" data-draw-finish>Terminer</button>' +
      '<button type="button" class="atak-draw-hint__btn" data-draw-cancel>Annuler</button>' +
      '</div>';
    document.body.appendChild(hintEl);
    hintEl.addEventListener('click', function (e) {
      var finish = e.target.closest('[data-draw-finish]');
      var cancel = e.target.closest('[data-draw-cancel]');
      if (finish) {
        e.preventDefault();
        e.stopPropagation();
        if (drawMode === 'line') finishLine();
        return;
      }
      if (cancel) {
        e.preventDefault();
        e.stopPropagation();
        cancelDraw();
        if (window.ATAKShowNotification) window.ATAKShowNotification('Dessin annulé.');
      }
    });
    return hintEl;
  }

  function showHint(text, opts) {
    opts = opts || {};
    ensureHint();
    var textEl = document.getElementById('atak-draw-hint-text');
    var actions = document.getElementById('atak-draw-hint-actions');
    if (textEl) textEl.textContent = text;
    if (actions) {
      actions.hidden = !opts.showFinish;
      var finishBtn = actions.querySelector('[data-draw-finish]');
      if (finishBtn) {
        finishBtn.disabled = !!opts.finishDisabled;
        finishBtn.textContent = opts.finishLabel || 'Terminer';
      }
    }
    hintEl.hidden = false;
  }

  function hideHint() {
    if (hintEl) hintEl.hidden = true;
  }

  function updateLineHint() {
    var n = drawPoints.length;
    if (n < 2) {
      showHint(
        'Trait en cours — cliquez pour placer les points (minimum 2). Échap ou Annuler pour abandonner.',
        { showFinish: true, finishDisabled: true, finishLabel: 'Terminer' }
      );
    } else {
      showHint(
        'Trait : ' + n + ' points. Cliquez pour continuer, ou Terminer / double-clic / Entrée pour valider. Échap pour annuler.',
        { showFinish: true, finishDisabled: false, finishLabel: 'Terminer le trait' }
      );
    }
  }

  function hideMenu() {
    if (!menuEl) return;
    menuEl.hidden = true;
  }

  function showMenu(clientX, clientY, latlng) {
    ensureMenu();
    lastLatLng = latlng;
    var coords = document.getElementById('atak-ctx-coords');
    if (coords && latlng) {
      coords.textContent = 'Grille ' + gridLabel(latlng);
    }
    menuEl.hidden = false;
    var pad = 8;
    var mw = menuEl.offsetWidth || 220;
    var mh = menuEl.offsetHeight || 260;
    var x = clientX;
    var y = clientY;
    if (x + mw + pad > window.innerWidth) x = window.innerWidth - mw - pad;
    if (y + mh + pad > window.innerHeight) y = window.innerHeight - mh - pad;
    if (x < pad) x = pad;
    if (y < pad) y = pad;
    menuEl.style.left = x + 'px';
    menuEl.style.top = y + 'px';
  }

  function onContextMenu(e) {
    if (!e || !e.originalEvent) return;
    e.originalEvent.preventDefault();
    e.originalEvent.stopPropagation();
    if (drawMode === 'line') {
      if (drawPoints.length >= 2) {
        finishLine();
      } else {
        cancelDraw();
        if (window.ATAKShowNotification) window.ATAKShowNotification('Trait annulé (pas assez de points).');
      }
      return;
    }
    if (drawMode) {
      cancelDraw();
      return;
    }
    showMenu(e.originalEvent.clientX, e.originalEvent.clientY, e.latlng);
  }

  function bindMap(map) {
    if (!map) return;
    if (boundMap === map) return;
    if (boundMap) {
      try { boundMap.off('contextmenu', onContextMenu); } catch (err) {}
      try { boundMap.off('click', onDrawClick); } catch (err) {}
      try { boundMap.off('dblclick', onDrawDblClick); } catch (err) {}
      try { boundMap.off('mousemove', onDrawMove); } catch (err) {}
    }
    boundMap = map;
    map.on('contextmenu', onContextMenu);
  }

  function clearDrawPreview() {
    var map = getMap();
    if (drawPreview && map) {
      try { map.removeLayer(drawPreview); } catch (err) {}
    }
    drawPreview = null;
  }

  function cancelDraw() {
    var map = getMap();
    if (map) {
      map.off('click', onDrawClick);
      map.off('dblclick', onDrawDblClick);
      map.off('mousemove', onDrawMove);
      if (map.doubleClickZoom) map.doubleClickZoom.enable();
    }
    clearDrawPreview();
    drawMode = null;
    drawPoints = [];
    hideHint();
  }

  function updateDrawPreview(hoverLatLng) {
    var map = getMap();
    if (!map || !drawMode || drawPoints.length === 0) return;
    clearDrawPreview();
    var pts = drawPoints.slice();
    if (hoverLatLng) pts.push(hoverLatLng);
    if (drawMode === 'line' && pts.length >= 2) {
      drawPreview = L.polyline(pts, { color: '#34d399', weight: 2, dashArray: '6 4', opacity: 0.9 });
      drawPreview.addTo(map);
    } else if (drawMode === 'zone' && pts.length >= 1 && hoverLatLng) {
      var r = map.distance(pts[0], hoverLatLng);
      drawPreview = L.circle(pts[0], { radius: Math.max(r, 10), color: '#eab308', fillColor: '#eab308', fillOpacity: 0.12, weight: 2, dashArray: '4 4' });
      drawPreview.addTo(map);
    } else if (pts.length === 1) {
      drawPreview = L.circleMarker(pts[0], { radius: 5, color: '#34d399', fillColor: '#34d399', fillOpacity: 1 });
      drawPreview.addTo(map);
    }
  }

  function onDrawClick(e) {
    if (!drawMode || !e || !e.latlng) return;
    L.DomEvent.stopPropagation(e);
    drawPoints.push(e.latlng);
    if (drawMode === 'zone' && drawPoints.length >= 2) {
      finishZone();
      return;
    }
    updateDrawPreview(null);
    if (drawMode === 'line') updateLineHint();
  }

  function onDrawDblClick(e) {
    if (!drawMode) return;
    L.DomEvent.preventDefault(e);
    L.DomEvent.stopPropagation(e);
    if (drawMode === 'line') finishLine();
  }

  function onDrawMove(e) {
    if (!drawMode || !e || !e.latlng) return;
    updateDrawPreview(e.latlng);
  }

  function startDraw(mode, startLatLng) {
    cancelDraw();
    var map = getMap();
    if (!map) return;
    drawMode = mode;
    drawPoints = startLatLng ? [startLatLng] : [];
    if (map.doubleClickZoom) map.doubleClickZoom.disable();
    map.on('click', onDrawClick);
    map.on('dblclick', onDrawDblClick);
    map.on('mousemove', onDrawMove);
    updateDrawPreview(null);
    if (mode === 'line') {
      updateLineHint();
    } else if (mode === 'zone') {
      showHint('Zone : cliquez pour fixer le rayon. Échap ou Annuler pour abandonner.', {
        showFinish: true,
        finishDisabled: true,
        finishLabel: 'Terminer'
      });
    }
  }

  function finishLine() {
    if (drawMode !== 'line' || drawPoints.length < 2) {
      if (window.ATAKShowError) window.ATAKShowError('Placez au moins deux points, puis cliquez sur Terminer.');
      updateLineHint();
      return;
    }
    var pts = drawPoints.slice();
    cancelDraw();
    openPrompt('Libellé du trait', 'Optionnel — laissez vide pour un trait sans nom.', 'Ex. axe d’approche', '').then(function (label) {
      if (label === null) return;
      var coordinates = pts.map(function (p) { return [p.lng, p.lat]; });
      var payload = {
        mapId: getMapId(),
        type: 'LINE',
        label: (label || '').trim() || 'Trait',
        color: '#34d399',
        stroke: 2,
        createdBy: getAuthor(),
        geometry: { coordinates: coordinates },
        meta: { category: 'manual', kind: 'line' }
      };
      if (window.ATAKMapShapes && window.ATAKMapShapes.createShape) {
        window.ATAKMapShapes.createShape(payload);
      } else {
        postShape(payload);
      }
    });
  }

  function finishZone() {
    if (drawMode !== 'zone' || drawPoints.length < 2) return;
    var map = getMap();
    var center = drawPoints[0];
    var edge = drawPoints[1];
    var radius = map ? map.distance(center, edge) : 100;
    cancelDraw();
    openPrompt('Libellé de la zone', 'Optionnel.', 'Ex. zone d’interdiction', '').then(function (label) {
      if (label === null) return;
      var payload = {
        mapId: getMapId(),
        type: 'CIRCLE',
        label: (label || '').trim() || 'Zone',
        color: '#eab308',
        stroke: 2,
        fillOpacity: 0.15,
        createdBy: getAuthor(),
        geometry: { center: [center.lng, center.lat], radius: Math.max(Math.round(radius), 10) },
        meta: { category: 'manual', kind: 'zone' }
      };
      if (window.ATAKMapShapes && window.ATAKMapShapes.createShape) {
        window.ATAKMapShapes.createShape(payload);
      } else {
        postShape(payload);
      }
    });
  }

  function postShape(payload) {
    var base = getApiBase();
    if (!base) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Tacmap indisponible pour enregistrer la forme.');
      return;
    }
    fetch(base + '/api/map-shapes', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer la forme.');
        return null;
      }
      return r.json();
    }).then(function (row) {
      if (row && window.ATAKMapShapes && window.ATAKMapShapes.fetchShapes) {
        window.ATAKMapShapes.fetchShapes();
      }
      if (row && window.ATAKShowNotification) window.ATAKShowNotification('Forme enregistrée.');
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer la forme.');
    });
  }

  function createMarkerAt(latlng, opts) {
    opts = opts || {};
    var base = getApiBase();
    var label = (opts.label || '').trim() || 'Marqueur';
    var markerData = {
      pos: [latlng.lng, latlng.lat],
      label: label,
      description: (opts.description || '').trim(),
      color: opts.color || '#34d399',
      icon: opts.icon || 'pin',
      size: opts.size || 'md',
      type: 'manual',
      author: getAuthor(),
      created_at: new Date().toISOString()
    };
    var localId = 'local_m_' + Date.now();
    if (window.ATAKMap && window.ATAKMap.addOrUpdateMarker) {
      window.ATAKMap.addOrUpdateMarker({ id: localId, layerId: 1, data: markerData });
    }
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('Marqueur placé — grille ' + gridLabel(latlng));
    }
    if (!base) {
      if (window.ATAKMarkers && window.ATAKMarkers.refresh) window.ATAKMarkers.refresh();
      return;
    }
    fetch(base + '/api/markers', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ mapId: getMapId(), layerId: 1, markerData: markerData })
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer le marqueur.');
        return null;
      }
      return r.json();
    }).then(function (row) {
      if (!row) return;
      if (window.ATAKMap && window.ATAKMap.removeMarker) {
        window.ATAKMap.removeMarker({ id: localId });
      }
      if (window.ATAKMap && window.ATAKMap.addOrUpdateMarker) {
        var data = typeof row.markerData === 'string'
          ? (function () { try { return JSON.parse(row.markerData); } catch (e) { return markerData; } })()
          : (row.markerData || markerData);
        window.ATAKMap.addOrUpdateMarker({ id: row.id, layerId: row.layerId != null ? row.layerId : 1, data: data });
      }
      if (window.ATAKMarkers && window.ATAKMarkers.refresh) window.ATAKMarkers.refresh();
      if (window.ATAKShowNotification) window.ATAKShowNotification('Marqueur enregistré — grille ' + gridLabel(latlng));
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer le marqueur.');
    });
  }

  function createCommentAt(latlng, text) {
    var payload = {
      mapId: getMapId(),
      type: 'POINT',
      label: text,
      color: '#60a5fa',
      stroke: 2,
      createdBy: getAuthor(),
      geometry: { center: [latlng.lng, latlng.lat] },
      meta: { category: 'manual', kind: 'comment', author: getAuthor() }
    };
    if (window.ATAKMapShapes && window.ATAKMapShapes.createShape) {
      window.ATAKMapShapes.createShape(payload);
    } else {
      postShape(payload);
    }
  }

  function runAction(action) {
    if (!lastLatLng) return;
    var ll = lastLatLng;
    if (action === 'copy') {
      var text = gridLabel(ll);
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification('Coordonnées copiées : ' + text);
        }).catch(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification(text);
        });
      } else if (window.ATAKShowNotification) {
        window.ATAKShowNotification(text);
      }
      return;
    }
    if (action === 'ping') {
      openPrompt('Message du ping', 'Optionnel — visible par les opérateurs connectés.', 'Ex. contact hostile', '').then(function (msg) {
        if (msg === null) return;
        if (window.ATAKPings && window.ATAKPings.createPingAt) {
          window.ATAKPings.createPingAt(ll.lng, ll.lat, msg || '');
        }
      });
      return;
    }
    if (action === 'marker') {
      openMarkerForm(ll).then(function (opts) {
        if (!opts) return;
        createMarkerAt(ll, opts);
      });
      return;
    }
    if (action === 'comment') {
      openPrompt('Commentaire', 'Note visible sur la carte pour votre équipe.', 'Ex. couverture au nord', '').then(function (text) {
        if (text === null) return;
        var t = (text || '').trim();
        if (!t) {
          if (window.ATAKShowError) window.ATAKShowError('Saisissez un commentaire.');
          return;
        }
        createCommentAt(ll, t);
      });
      return;
    }
    if (action === 'line') {
      startDraw('line', ll);
      return;
    }
    if (action === 'zone') {
      startDraw('zone', ll);
      return;
    }
  }

  function onDocClick(e) {
    if (!menuEl || menuEl.hidden) return;
    if (menuEl.contains(e.target)) return;
    hideMenu();
  }

  function onKeyDown(e) {
    if (e.key === 'Escape') {
      if (markerFormEl && !markerFormEl.hidden) {
        closeMarkerForm(null);
        return;
      }
      if (promptEl && !promptEl.hidden) {
        closePrompt(null);
        return;
      }
      if (drawMode) {
        cancelDraw();
        if (window.ATAKShowNotification) window.ATAKShowNotification('Dessin annulé.');
        return;
      }
      hideMenu();
      return;
    }
    if (e.key === 'Enter' && drawMode === 'line' && !(promptEl && !promptEl.hidden) && !(markerFormEl && !markerFormEl.hidden)) {
      e.preventDefault();
      finishLine();
    }
  }

  function onMapReady() {
    bindMap(getMap());
    cancelDraw();
    hideMenu();
  }

  function init() {
    ensureMenu();
    ensurePrompt();
    ensureMarkerForm();
    ensureHint();
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKeyDown);
    window.addEventListener('atak:mapready', onMapReady);
    var tries = 0;
    (function retry() {
      var map = getMap();
      if (map) {
        bindMap(map);
        return;
      }
      tries += 1;
      if (tries < 40) setTimeout(retry, 250);
    })();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    hide: hideMenu,
    cancelDraw: cancelDraw,
    openPrompt: openPrompt,
    finishLine: finishLine
  };
})();
