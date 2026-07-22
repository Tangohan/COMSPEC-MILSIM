/* COMSPEC ATAK — Historique des marqueurs (panneau gauche) */
window.ATAKMarkers = (function () {
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function askConfirm(message) {
    if (window.ATAKContextMenu && typeof window.ATAKContextMenu.confirmAction === 'function') {
      return window.ATAKContextMenu.confirmAction(message);
    }
    return Promise.resolve(window.confirm(message));
  }

  function getListEl() {
    return document.getElementById('atak-markers-list');
  }

  function renderFromMap() {
    var el = getListEl();
    if (!el) return;
    if (!window.ATAKMap || !window.ATAKMap.listMarkers) {
      el.innerHTML = '<div class="atak-empty-state"><p class="atak-empty-state-title">Carte non prête</p><p class="atak-empty-state-text">Réessayez dans un instant.</p></div>';
      return;
    }
    var list = window.ATAKMap.listMarkers();
    // Historique : marqueurs manuels, Arma, ou enrichis
    list = list.filter(function (item) {
      var d = item.data || {};
      if (d.suppressed) return false;
      var isArma = window.ArmaMapMarkers && window.ArmaMapMarkers.isArmaStyleMarker
        ? window.ArmaMapMarkers.isArmaStyleMarker(d)
        : (d.source === 'arma' || (d.type && String(d.type).indexOf('mil_') === 0));
      return isArma || d.type === 'manual' || d.color || d.icon || d.description || d.label || d.text;
    });
    list.sort(function (a, b) {
      var ta = (a.data && a.data.created_at) || '';
      var tb = (b.data && b.data.created_at) || '';
      return ta < tb ? 1 : ta > tb ? -1 : 0;
    });
    if (list.length === 0) {
      el.innerHTML = '<div class="atak-empty-state">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">⌖</div>' +
        '<p class="atak-empty-state-title">Aucun marqueur</p>' +
        '<p class="atak-empty-state-text">Clic droit sur la carte → Placer un marqueur.</p></div>';
      return;
    }
    el.innerHTML = list.map(function (item) {
      var d = item.data || {};
      var label = d.label || d.text || d.symbolName || d.name || 'Marqueur';
      var desc = d.description || '';
      var gx = item.gridLng != null ? Math.round(Number(item.gridLng)) : '—';
      var gy = item.gridLat != null ? Math.round(Number(item.gridLat)) : '—';
      var color = d.color || '#34d399';
      if (window.ArmaMapMarkers && window.ArmaMapMarkers.armaColorHex && String(color).indexOf('Color') === 0) {
        color = window.ArmaMapMarkers.armaColorHex(color);
      }
      var metaExtra = '';
      if (d.symbolName || d.affiliation) {
        var affFr = (window.MilstdCatalog && window.MilstdCatalog.affiliationLabelFr)
          ? window.MilstdCatalog.affiliationLabelFr(d.affiliation)
          : '';
        var bits = [];
        if (d.symbolName) bits.push(escapeHtml(d.symbolName));
        if (affFr) bits.push(escapeHtml(affFr));
        if (bits.length) metaExtra = '<span class="atak-marker-item__symbol">' + bits.join(' · ') + '</span>';
      }
      var thumb = '';
      if ((d.sidc || d.icon === 'milsymbol') && window.NatoSidcIcons && window.NatoSidcIcons.listBadgeHtml) {
        thumb = window.NatoSidcIcons.listBadgeHtml({
          sidc: d.sidc,
          affiliation: d.affiliation || 'friend',
          functionid: d.functionid,
          size: 20,
        });
      }
      return '<div class="atak-marker-item" data-id="' + escapeHtml(item.id) + '">' +
        '<button type="button" class="atak-marker-item__main" data-focus="' + escapeHtml(item.id) + '">' +
        (thumb || '<span class="atak-marker-item__swatch" style="background:' + escapeHtml(color) + '" aria-hidden="true"></span>') +
        '<span class="atak-marker-item__body">' +
        '<strong>' + escapeHtml(label) + '</strong>' +
        metaExtra +
        '<span class="atak-marker-item__meta">Grille ' + gx + ' / ' + gy + '</span>' +
        (desc ? '<span class="atak-marker-item__desc">' + escapeHtml(desc) + '</span>' : '') +
        '</span></button>' +
        '<button type="button" class="atak-marker-item__del" data-delete="' + escapeHtml(item.id) + '" title="Supprimer">×</button>' +
        '</div>';
    }).join('');
    bindList();
  }

  function bindList() {
    var el = getListEl();
    if (!el || el._bound) return;
    el._bound = true;
    el.addEventListener('click', function (e) {
      var focusBtn = e.target.closest('[data-focus]');
      var delBtn = e.target.closest('[data-delete]');
      if (delBtn) {
        e.preventDefault();
        e.stopPropagation();
        var delId = delBtn.getAttribute('data-delete');
        if (!delId || !window.ATAKMap || !window.ATAKMap.deleteMarkerById) return;
        askConfirm('Supprimer ce marqueur ?').then(function (ok) {
          if (!ok) return;
          window.ATAKMap.deleteMarkerById(delId).then(function () {
            renderFromMap();
            if (window.ATAKShowNotification) window.ATAKShowNotification('Marqueur supprimé.');
          }).catch(function () {
            if (window.ATAKShowError) window.ATAKShowError('Impossible de supprimer le marqueur.');
          });
        });
        return;
      }
      if (focusBtn) {
        var id = focusBtn.getAttribute('data-focus');
        if (id && window.ATAKMap && window.ATAKMap.focusMarker) {
          window.ATAKMap.focusMarker(id);
        }
      }
    });
  }

  function refresh() {
    if (window.ATAKMap && window.ATAKMap.pollMarkers) {
      window.ATAKMap.pollMarkers();
    } else {
      renderFromMap();
    }
  }

  return { renderFromMap: renderFromMap, refresh: refresh };
})();
