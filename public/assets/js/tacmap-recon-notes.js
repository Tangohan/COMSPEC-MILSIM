/**
 * Notes de reconnaissance (INTEL_MARK / recon_note) sur la carte du poste.
 */
(function (global) {
  'use strict';

  var TAGS = {
    vehicle: { label: 'Véhicule', color: '#f97316' },
    armed_group: { label: 'Groupe armé', color: '#ef4444' },
    static: { label: 'Position statique', color: '#eab308' },
    mine: { label: 'Obstacle / mine', color: '#ec4899' },
    civilian: { label: 'Civil', color: '#22c55e' },
    infrastructure: { label: 'Infrastructure', color: '#38bdf8' },
    other: { label: 'Autre', color: '#94a3b8' }
  };

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function tagSpec(tag) {
    return TAGS[String(tag || '')] || { label: 'Observation', color: '#94a3b8' };
  }

  function ageLabel(sec) {
    var n = Number(sec) || 0;
    if (n < 60) return 'à l’instant';
    if (n < 3600) return 'il y a ' + Math.round(n / 60) + ' min';
    var h = Math.floor(n / 3600);
    var m = Math.round((n % 3600) / 60);
    return m === 0 ? ('il y a ' + h + ' h') : ('il y a ' + h + ' h ' + m + ' min');
  }

  function hasPos(n) {
    var x = n && n.pos_x != null ? parseFloat(n.pos_x) : NaN;
    var y = n && n.pos_y != null ? parseFloat(n.pos_y) : NaN;
    return !isNaN(x) && !isNaN(y) && !(Math.abs(x) < 0.5 && Math.abs(y) < 0.5);
  }

  function renderList(el, notes, opts) {
    opts = opts || {};
    if (!el) return;
    var list = Array.isArray(notes) ? notes : [];
    if (!list.length) {
      el.innerHTML = '<p class="text-sm text-[color:var(--tm-muted)]">Aucune note de reco récente.</p>';
      return;
    }
    el.innerHTML = list.slice(0, 40).map(function (n) {
      var spec = tagSpec(n.tag);
      var clickable = hasPos(n);
      var opacity = n.opacity != null ? Number(n.opacity) : 1;
      var conf = n.confidence_label || '';
      return (
        '<article class="tacmap-recon-note' + (clickable ? ' tacmap-recon-note--locate' : '') +
          (n.freshness === 'stale' ? ' is-stale' : (n.freshness === 'aging' ? ' is-aging' : '')) + '"' +
          (clickable ? ' data-pos-x="' + escapeHtml(n.pos_x) + '" data-pos-y="' + escapeHtml(n.pos_y) + '" tabindex="0" role="button"' : '') +
          ' style="opacity:' + opacity + '">' +
          '<span class="tacmap-recon-note__chip" style="background:' + spec.color + '"></span>' +
          '<div class="tacmap-recon-note__body">' +
            '<strong>' + escapeHtml(spec.label) + '</strong>' +
            '<span>' + escapeHtml(n.author || '') + (conf ? ' · ' + escapeHtml(conf) : '') + '</span>' +
            (n.text ? '<p>' + escapeHtml(n.text) + '</p>' : '') +
            '<em>' + escapeHtml(ageLabel(n.age_sec)) + '</em>' +
          '</div>' +
        '</article>'
      );
    }).join('');

    if (typeof opts.onLocate === 'function' && !el.getAttribute('data-recon-notes-bound')) {
      el.setAttribute('data-recon-notes-bound', '1');
      el.addEventListener('click', function (ev) {
        var art = ev.target && ev.target.closest ? ev.target.closest('.tacmap-recon-note--locate') : null;
        if (!art) return;
        var x = parseFloat(art.getAttribute('data-pos-x'));
        var y = parseFloat(art.getAttribute('data-pos-y'));
        if (!isNaN(x) && !isNaN(y)) opts.onLocate(x, y);
      });
    }
  }

  function buildUrl(apiBase, mapId) {
    var base = String(apiBase || '').replace(/\/$/, '');
    var qs = 'limit=80';
    if (mapId) qs += '&mapId=' + encodeURIComponent(mapId);
    if (base.indexOf('/api') >= 0) {
      if (base.indexOf('/api/atak') >= 0) {
        return base.replace(/\/api\/atak$/, '/api') + '/recon/notes?' + qs;
      }
      return base + '/recon/notes?' + qs;
    }
    return base + '/api/recon/notes?' + qs;
  }

  function poll(apiBase, mapId, listEl, opts) {
    opts = opts || {};
    return fetch(buildUrl(apiBase, mapId), { credentials: 'include' })
      .then(function (r) { return r.ok ? r.json() : { notes: [] }; })
      .then(function (data) {
        var list = Array.isArray(data) ? data : ((data && data.notes) || []);
        renderList(listEl, list, opts);
        if (typeof opts.onNotes === 'function') opts.onNotes(list);
        return list;
      })
      .catch(function () {
        renderList(listEl, [], opts);
        if (typeof opts.onNotes === 'function') opts.onNotes([]);
        return [];
      });
  }

  global.TacmapReconNotes = {
    renderList: renderList,
    poll: poll,
    hasPos: hasPos,
    tagSpec: tagSpec
  };
})(window);
