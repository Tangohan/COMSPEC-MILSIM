/**
 * Photos de terrain (BCE / Photo Library Iceman → Athena) sur Tacmap.
 */
(function (global) {
  'use strict';

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function resolveUrl(apiBase, p) {
    var u = (p && (p.url || p.path)) || '';
    if (!u && p && p.image_path) {
      u = '/uploads/recon/' + String(p.image_path).split('/').pop();
    }
    if (!u) return '';
    if (u.indexOf('http') === 0 || u.indexOf('//') === 0) return u;
    var origin = '';
    try {
      // apiBase = …/api → racine publique
      var base = String(apiBase || '').replace(/\/$/, '');
      origin = base.replace(/\/api(?:\/atak)?$/, '') || '';
    } catch (e) {}
    return origin + (u.charAt(0) === '/' ? u : '/' + u);
  }

  function hasPos(p) {
    var x = p && p.pos_x != null ? parseFloat(p.pos_x) : NaN;
    var y = p && p.pos_y != null ? parseFloat(p.pos_y) : NaN;
    return !isNaN(x) && !isNaN(y) && !(Math.abs(x) < 0.5 && Math.abs(y) < 0.5);
  }

  function renderList(el, photos, apiBase, opts) {
    opts = opts || {};
    if (!el) return;
    var list = Array.isArray(photos) ? photos : [];
    if (!list.length) {
      el.innerHTML = '<p class="text-sm text-[color:var(--tm-muted)]">Aucune photo de terrain récente.</p>';
      return;
    }
    el.innerHTML = list.slice(0, 24).map(function (p) {
      var src = resolveUrl(apiBase, p);
      var author = p.author_callsign || p.author || p.unit_name || '';
      var caption = p.caption || '';
      var grid = p.grid_ref || p.grid || '';
      var clickable = hasPos(p);
      return (
        '<article class="tacmap-recon-item' + (clickable ? ' tacmap-recon-item--locate' : '') + '"' +
          (p.id != null ? ' data-photo-id="' + escapeHtml(p.id) + '"' : '') +
          (clickable ? ' data-pos-x="' + escapeHtml(p.pos_x) + '" data-pos-y="' + escapeHtml(p.pos_y) + '" tabindex="0" role="button"' : '') +
          '>' +
          (src ? '<img src="' + escapeHtml(src) + '" alt="" loading="lazy" />' : '') +
          '<div class="tacmap-recon-meta">' +
            '<strong>' + escapeHtml(author || 'Recon') + '</strong>' +
            (grid ? '<span>Grille ' + escapeHtml(grid) + '</span>' : '') +
            (caption ? '<p>' + escapeHtml(caption) + '</p>' : '') +
          '</div>' +
        '</article>'
      );
    }).join('');

    if (typeof opts.onLocate === 'function' && !el.getAttribute('data-recon-bound')) {
      el.setAttribute('data-recon-bound', '1');
      el.addEventListener('click', function (ev) {
        var art = ev.target && ev.target.closest ? ev.target.closest('.tacmap-recon-item--locate') : null;
        if (!art) return;
        var x = parseFloat(art.getAttribute('data-pos-x'));
        var y = parseFloat(art.getAttribute('data-pos-y'));
        if (!isNaN(x) && !isNaN(y)) opts.onLocate(x, y, art.getAttribute('data-photo-id'));
      });
    }
  }

  function buildUrl(apiBase, mapId, missionId) {
    var base = String(apiBase || '').replace(/\/$/, '');
    var qs = 'limit=40';
    if (missionId) qs += '&missionId=' + encodeURIComponent(missionId);
    // Certains déploiements filtrent via mission ; mapId informatif
    if (mapId) qs += '&mapId=' + encodeURIComponent(mapId);
    if (base.indexOf('/api') >= 0) {
      return base + '/recon/images?' + qs;
    }
    return base + '/api/recon/images?' + qs;
  }

  function poll(apiBase, mapId, listEl, opts) {
    opts = opts || {};
    var missionId = opts.missionId || ('mission_' + (opts.tenantId || 0) + '_map_' + (mapId || 1));
    return fetch(buildUrl(apiBase, mapId, missionId), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var list = Array.isArray(data) ? data : [];
        renderList(listEl, list, apiBase, opts);
        if (typeof opts.onPhotos === 'function') opts.onPhotos(list);
        return list;
      })
      .catch(function () {
        renderList(listEl, [], apiBase, opts);
        if (typeof opts.onPhotos === 'function') opts.onPhotos([]);
        return [];
      });
  }

  global.TacmapRecon = {
    renderList: renderList,
    poll: poll,
    resolveUrl: resolveUrl,
    hasPos: hasPos,
  };
})(window);
