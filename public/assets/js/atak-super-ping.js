/* COMSPEC ATAK — Super ping : pulse visuel sur la carte du poste. */
window.ATAKSuperPing = (function () {
  var LIFE_MS = 7000;
  var RING_M = [80, 220, 420];
  var seen = {};
  var primed = false;
  var layer = null;
  var pulses = [];
  var lastAt = {};

  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function latLngFromWorld(x, y) {
    if (window.ATAKMap && typeof window.ATAKMap.latLngFromWorld === 'function') {
      return window.ATAKMap.latLngFromWorld(x, y);
    }
    return window.L ? window.L.latLng(y, x) : null;
  }

  function isSuperMessage(msg) {
    var raw = String(msg || '');
    var m = raw.match(/^\s*\[([^\]]+)\]/);
    var tag = m ? m[1].toLowerCase() : raw.toLowerCase();
    return tag.indexOf('super') >= 0;
  }

  function ensureLayer() {
    var map = getMap();
    if (!map || !window.L) return null;
    if (!layer) {
      layer = window.L.layerGroup().addTo(map);
    }
    return layer;
  }

  function playSound() {
    if (window.ATAKSounds && typeof window.ATAKSounds.play === 'function') {
      window.ATAKSounds.play('ping');
    }
  }

  function play(posX, posY, opts) {
    opts = opts || {};
    var map = getMap();
    var lg = ensureLayer();
    if (!map || !lg || !window.L) return false;
    var x = Number(posX);
    var y = Number(posY);
    if (!isFinite(x) || !isFinite(y)) return false;
    var lk = Math.round(x) + ':' + Math.round(y);
    if (!opts.force && lastAt[lk] && (Date.now() - lastAt[lk]) < 5000) return false;
    if (!opts.force) lastAt[lk] = Date.now();
    var ll = latLngFromWorld(x, y);
    if (!ll) return false;

    var html =
      '<span class="atak-super-ping__ring"></span>' +
      '<span class="atak-super-ping__ring atak-super-ping__ring--d2"></span>' +
      '<span class="atak-super-ping__ring atak-super-ping__ring--d3"></span>' +
      '<span class="atak-super-ping__core"></span>';
    var icon = window.L.divIcon({
      className: 'atak-super-ping-icon',
      html: '<div class="atak-super-ping">' + html + '</div>',
      iconSize: [160, 160],
      iconAnchor: [80, 80]
    });
    var marker = window.L.marker(ll, {
      icon: icon,
      interactive: false,
      keyboard: false,
      zIndexOffset: 2200
    }).addTo(lg);

    var circles = RING_M.map(function (r, i) {
      return window.L.circle(ll, {
        radius: 24,
        color: '#38bdf8',
        weight: i === 0 ? 3 : 2,
        opacity: 0.95,
        fillColor: '#22d3ee',
        fillOpacity: i === 0 ? 0.12 : 0.04,
        dashArray: i ? '6 5' : null,
        interactive: false
      }).addTo(lg);
    });

    var started = Date.now();
    var pulse = { marker: marker, circles: circles, started: started, max: RING_M };
    pulses.push(pulse);
    if (opts.sound !== false) playSound();
    tick();
    return true;
  }

  function tick() {
    var now = Date.now();
    var keep = [];
    pulses.forEach(function (p) {
      var t = (now - p.started) / LIFE_MS;
      if (t >= 1) {
        try { if (layer) layer.removeLayer(p.marker); } catch (e0) {}
        (p.circles || []).forEach(function (c) {
          try { if (layer) layer.removeLayer(c); } catch (e1) {}
        });
        return;
      }
      var ease = 1 - Math.pow(1 - t, 1.35);
      (p.circles || []).forEach(function (c, i) {
        var maxR = p.max[i] || 400;
        var r = 20 + ease * maxR;
        var fade = Math.max(0, 1 - t);
        try {
          c.setRadius(r);
          c.setStyle({ opacity: 0.25 + fade * 0.7, fillOpacity: fade * (i === 0 ? 0.14 : 0.05) });
        } catch (e2) {}
      });
      keep.push(p);
    });
    pulses = keep;
    if (pulses.length) {
      window.requestAnimationFrame(tick);
    }
  }

  function ingest(list, opts) {
    opts = opts || {};
    var rows = Array.isArray(list) ? list : [];
    if (!primed) {
      rows.forEach(function (p) {
        if (p && p.id != null) seen[String(p.id)] = true;
      });
      primed = true;
      return;
    }
    rows.forEach(function (p) {
      if (!p) return;
      var id = p.id != null ? String(p.id) : '';
      if (!id || seen[id]) return;
      seen[id] = true;
      if (!isSuperMessage(p.message)) return;
      play(p.pos_x, p.pos_y, { sound: opts.sound !== false });
    });
  }

  function fromPing(p, opts) {
    if (!p || !isSuperMessage(p.message)) return;
    var id = p.id != null ? String(p.id) : '';
    if (id) {
      if (seen[id]) return;
      seen[id] = true;
    }
    play(p.pos_x, p.pos_y, opts || {});
  }

  return {
    play: play,
    ingest: ingest,
    fromPing: fromPing,
    isSuperMessage: isSuperMessage
  };
})();
