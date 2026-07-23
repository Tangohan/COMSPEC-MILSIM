/**
 * Bandeau météo mission (ATAK Enhanced Weather → Athena).
 */
(function (global) {
  'use strict';

  function buildUrl(apiBase, mapId) {
    var base = String(apiBase || '').replace(/\/$/, '');
    if (base.indexOf('/atak') >= 0) {
      return base + '/weather?mapId=' + encodeURIComponent(mapId || 1);
    }
    return base + '/atak/weather?mapId=' + encodeURIComponent(mapId || 1);
  }

  function formatBanner(w) {
    if (!w || !w.condition) return '';
    var parts = [w.condition];
    if (w.temperature_c != null) parts.push(w.temperature_c + ' °C');
    if (w.wind_kph != null) parts.push('Vent ' + w.wind_kph + ' km/h');
    if (w.cloud_pct != null) parts.push('Nuages ' + w.cloud_pct + ' %');
    if (w.fog_pct != null && Number(w.fog_pct) > 0) parts.push('Brouillard ' + w.fog_pct + ' %');
    if (w.rain_pct != null && Number(w.rain_pct) > 0) parts.push('Pluie ' + w.rain_pct + ' %');
    return parts.join(' · ');
  }

  function formatCompact(w) {
    if (!w || !w.condition) return '';
    var parts = [w.condition];
    if (w.temperature_c != null) parts.push(w.temperature_c + '°C');
    if (w.wind_kph != null) parts.push(w.wind_kph + ' km/h');
    return parts.join(' · ');
  }

  function render(el, weather, opts) {
    if (!el) return;
    opts = opts || {};
    var compact = !!opts.compact;
    var valueEl = opts.valueEl || null;
    var text = compact ? formatCompact(weather) : formatBanner(weather);
    var title = weather && weather.call_sign
      ? ('Météo rapportée par ' + weather.call_sign)
      : 'Météo mission';
    if (!text) {
      el.hidden = true;
      if (valueEl) valueEl.textContent = '';
      else el.textContent = '';
      return;
    }
    el.hidden = false;
    el.title = title + (compact && formatBanner(weather) ? (' — ' + formatBanner(weather)) : '');
    if (valueEl) {
      valueEl.textContent = text;
    } else {
      el.textContent = text;
    }
  }

  function poll(apiBase, mapId, el, opts) {
    return fetch(buildUrl(apiBase, mapId), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var w = (data && data.weather) ? data.weather : null;
        render(el, w, opts);
        return w;
      })
      .catch(function () {
        render(el, null, opts);
        return null;
      });
  }

  global.TacmapWeather = {
    poll: poll,
    render: render,
    formatBanner: formatBanner,
    formatCompact: formatCompact,
  };
})(window);
