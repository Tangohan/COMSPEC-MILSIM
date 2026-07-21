/* COMSPEC ATAK — Historique des marqueurs (panneau gauche) */
window.ATAKMarkers = (function () {
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
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
    // Historique : marqueurs manuels / enrichis en tête, ordre stable
    list = list.filter(function (item) {
      var d = item.data || {};
      return d.type === 'manual' || d.color || d.icon || d.description || d.label;
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
      var label = d.label || d.name || 'Marqueur';
      var desc = d.description || '';
      var gx = item.gridLng != null ? Math.round(Number(item.gridLng)) : '—';
      var gy = item.gridLat != null ? Math.round(Number(item.gridLat)) : '—';
      var color = d.color || '#34d399';
      return '<div class="atak-marker-item" data-id="' + escapeHtml(item.id) + '">' +
        '<button type="button" class="atak-marker-item__main" data-focus="' + escapeHtml(item.id) + '">' +
        '<span class="atak-marker-item__swatch" style="background:' + escapeHtml(color) + '" aria-hidden="true"></span>' +
        '<span class="atak-marker-item__body">' +
        '<strong>' + escapeHtml(label) + '</strong>' +
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
        if (!window.confirm('Supprimer ce marqueur ?')) return;
        window.ATAKMap.deleteMarkerById(delId).then(function () {
          renderFromMap();
          if (window.ATAKShowNotification) window.ATAKShowNotification('Marqueur supprimé.');
        }).catch(function () {
          if (window.ATAKShowError) window.ATAKShowError('Impossible de supprimer le marqueur.');
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
