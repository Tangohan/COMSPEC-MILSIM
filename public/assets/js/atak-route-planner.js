/**
 * Planification d’itinéraire road-aware — POST /api/atak/route/plan
 */
(function (global) {
  'use strict';

  function create(apiBase, mapId) {
    var base = (apiBase || '').replace(/\/$/, '');
    var mid = mapId || 1;

    function planRoute(startXY, endXY, viaXY, mode, snapM) {
      if (!base || !startXY || !endXY) {
        return Promise.resolve({ ok: false, error: 'missing_params' });
      }
      var body = {
        mapId: mid,
        start: { x: startXY[0], y: startXY[1] },
        end: { x: endXY[0], y: endXY[1] },
        mode: mode || 'foot',
        snap_m: snapM || 150,
      };
      if (viaXY && viaXY.length) {
        body.via = viaXY.map(function (p) { return { x: p[0], y: p[1] }; });
      }
      var headers = { 'Content-Type': 'application/json' };
      var csrf = global.document && global.document.querySelector('meta[name="csrf-token"]');
      if (csrf && csrf.getAttribute('content')) {
        headers['X-CSRF-Token'] = csrf.getAttribute('content');
      }
      return fetch(base + '/atak/route/plan', {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers,
        body: JSON.stringify(body),
      }).then(function (r) { return r.json(); });
    }

    function pointsToXY(plan) {
      if (!plan || !plan.points) return [];
      return plan.points.map(function (p) { return [p.x, p.y]; });
    }

    return {
      planRoute: planRoute,
      pointsToXY: pointsToXY,
    };
  }

  global.AtakRoutePlanner = { create: create };
})(window);
