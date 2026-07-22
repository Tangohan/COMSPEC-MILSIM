/* COMSPEC — Assistances médicales (alertes mod → portail) */
window.ATAKMedicalAlerts = (function () {
  'use strict';

  var lastFingerprint = '';
  var pollTimer = null;

  function getApiBase() {
    if (window.ATAKSocket && window.ATAKSocket.getApiBase) return window.ATAKSocket.getApiBase();
    if (window.overwatchApiBase) return window.overwatchApiBase;
    return '';
  }

  function getMapId() {
    if (window.ATAKSocket && window.ATAKSocket.getMapId) return window.ATAKSocket.getMapId();
    if (window.OverwatchState && window.OverwatchState.currentMapId != null) return window.OverwatchState.currentMapId;
    return 1;
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function parseMessage(body) {
    body = String(body || '').trim();
    if (!body) return null;
    var upper = body.toUpperCase();
    if (upper.indexOf('ALERTE MÉDICALE') === 0 || upper.indexOf('ALERTE MEDICALE') === 0) {
      var parts = body.split('|').map(function (p) { return p.trim(); });
      var callSign = parts[1] || '';
      var label = parts[2] || 'Assistance médicale';
      var hrMatch = (parts[3] || '').match(/(\d+)/);
      var bloodMatch = (parts[4] || '').match(/(\d+)/);
      var grid = (parts[5] || '').replace(/^Grille\s+/i, '');
      var kind = 'medical_alert';
      var severity = 'urgent';
      var ll = label.toLowerCase();
      if (ll.indexOf('arrêt cardiaque') >= 0 || ll.indexOf('rythme à zéro') >= 0 || (hrMatch && parseInt(hrMatch[1], 10) <= 0)) {
        kind = 'cardiac_arrest';
        severity = 'critical';
      } else if (ll.indexOf('inconscient') >= 0 || ll.indexOf('au sol') >= 0) {
        kind = 'unconscious';
        severity = 'critical';
      }
      return {
        is_medical: true,
        kind: kind,
        severity: severity,
        call_sign: callSign,
        label: label,
        heart_rate: hrMatch ? parseInt(hrMatch[1], 10) : null,
        blood_pct: bloodMatch ? parseInt(bloodMatch[1], 10) : null,
        grid: grid,
        summary: [callSign, label, hrMatch ? ('FC ' + hrMatch[1]) : '', grid ? ('Grille ' + grid) : ''].filter(Boolean).join(' — '),
        body: body
      };
    }
    if (upper.indexOf('WIA|') === 0) {
      var wp = body.split('|').map(function (p) { return p.trim(); });
      var status = wp[1] || 'Blessé';
      var bm = (wp[2] || '').match(/(\d+)/);
      var hm = (wp[3] || '').match(/(\d+)/);
      return {
        is_medical: true,
        kind: 'wia_report',
        severity: 'attention',
        call_sign: '',
        label: 'Bilan santé — ' + status,
        heart_rate: hm ? parseInt(hm[1], 10) : null,
        blood_pct: bm ? parseInt(bm[1], 10) : null,
        grid: '',
        summary: 'Bilan santé — ' + status,
        body: body
      };
    }
    return null;
  }

  function kindLabelFr(kind) {
    var k = String(kind || '').toLowerCase();
    if (k === 'cardiac_arrest') return 'Arrêt cardiaque';
    if (k === 'unconscious') return 'Inconscient';
    if (k === 'wia_report') return 'Bilan santé';
    return 'Assistance médicale';
  }

  function formatChatBody(body) {
    var parsed = parseMessage(body);
    if (!parsed) return escapeHtml(body);
    return '<span class="atak-medical-chat-flag" title="Assistance médicale">' + escapeHtml(parsed.summary || body) + '</span>';
  }

  var audioCtx = null;

  function getAudioCtx() {
    var Ctor = window.AudioContext || window.webkitAudioContext;
    if (!Ctor) return null;
    if (!audioCtx) audioCtx = new Ctor();
    if (audioCtx.state === 'suspended') audioCtx.resume().catch(function () {});
    return audioCtx;
  }

  // Première interaction utilisateur : débloque l’audio (autoplay policy des navigateurs).
  ['pointerdown', 'keydown'].forEach(function (evt) {
    document.addEventListener(evt, function () { getAudioCtx(); }, { once: true, passive: true });
  });

  // Bip synthétisé (pas de fichier audio externe à charger/oublier) : deux tons descendants,
  // reconnaissables comme une alerte critique sans dépendre d’un asset qui peut manquer.
  function playAlertSound() {
    var ctx = getAudioCtx();
    if (!ctx || ctx.state !== 'running') return;
    [880, 660].forEach(function (freq, i) {
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      var start = ctx.currentTime + i * 0.22;
      osc.type = 'square';
      osc.frequency.setValueAtTime(freq, start);
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(0.18, start + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.2);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(start);
      osc.stop(start + 0.22);
    });
  }

  function showToast(summary) {
    var toast = document.getElementById('atak-notification-toast')
      || document.getElementById('atak-medical-toast')
      || document.getElementById('overwatch-medical-toast');
    if (!toast) return;
    toast.textContent = 'Assistance médicale — ' + (summary || 'Nouvelle alerte');
    toast.classList.add('visible', 'atak-medical-toast-visible');
    toast.hidden = false;
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () {
      toast.classList.remove('visible', 'atak-medical-toast-visible');
    }, 8000);
  }

  function renderBanner(data) {
    var banner = document.getElementById('atak-medical-banner')
      || document.getElementById('overwatch-medical-banner')
      || document.getElementById('tacmap-medical-banner');
    if (!banner) return;
    var alerts = (data && data.alerts) || [];
    var units = (data && data.criticalUnits) || [];
    var emergency = (data && data.counts && data.counts.emergency) || 0;
    if (!alerts.length && !units.length) {
      banner.hidden = true;
      banner.innerHTML = '';
      return;
    }
    var latest = alerts[alerts.length - 1] || null;
    var unitBits = units.slice(0, 3).map(function (u) {
      return escapeHtml((u.call_sign || '?') + ' · ' + (u.health_label || u.health || ''));
    }).join(' · ');
    banner.hidden = false;
    banner.innerHTML =
      '<div class="atak-medical-banner-inner">' +
      '<strong>Assistances médicales</strong>' +
      (emergency ? ' <span class="atak-medical-badge">' + emergency + ' critique(s)</span>' : '') +
      (latest ? '<span class="atak-medical-banner-msg">' + escapeHtml(latest.summary || latest.label || '') + '</span>' : '') +
      (unitBits ? '<span class="atak-medical-banner-units">' + unitBits + '</span>' : '') +
      '</div>';
  }

  function renderList(data) {
    var list = document.getElementById('atak-medical-list')
      || document.getElementById('overwatch-medical-list');
    // TACMAP : liste gérée par ComspecOperationalMap (éviter d’écraser le panneau unités).
    if (!list) return;
    var alerts = (data && data.alerts) || [];
    var units = (data && data.criticalUnits) || [];
    if (!alerts.length && !units.length) {
      list.innerHTML = '<div class="atak-empty-state atak-medical-empty">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">✚</div>' +
        '<p class="atak-empty-state-title">Aucune assistance</p>' +
        '<p class="atak-empty-state-text">Les demandes médicales en cours s’afficheront ici.</p></div>';
      return;
    }
    var html = '';
    if (units.length) {
      html += '<div class="atak-medical-section-title">Unités à secourir</div>';
      html += units.map(function (u) {
        var sev = u.severity === 'critical' ? 'critical' : 'attention';
        return '<div class="atak-medical-item atak-medical-' + sev + '" data-callsign="' + escapeHtml(u.call_sign || '') + '">' +
          '<div class="atak-medical-item-title">' + escapeHtml(u.call_sign || '—') + '</div>' +
          '<div class="atak-medical-item-label">' + escapeHtml(u.health_label || kindLabelFr(u.health)) + '</div>' +
          (u.grid_ref ? '<div class="atak-medical-item-meta">Grille ' + escapeHtml(u.grid_ref) + '</div>' : '') +
          '</div>';
      }).join('');
    }
    if (alerts.length) {
      html += '<div class="atak-medical-section-title">Alertes reçues</div>';
      html += alerts.slice().reverse().slice(0, 25).map(function (a) {
        var sev = a.severity === 'critical' ? 'critical' : (a.severity === 'attention' ? 'attention' : 'urgent');
        var t = a.created_at ? String(a.created_at).replace('T', ' ').substring(0, 19) : '';
        return '<div class="atak-medical-item atak-medical-' + sev + '">' +
          '<div class="atak-medical-item-title">' + escapeHtml(kindLabelFr(a.kind)) + (a.call_sign ? ' — ' + escapeHtml(a.call_sign) : '') + '</div>' +
          '<div class="atak-medical-item-label">' + escapeHtml(a.label || a.summary || '') + '</div>' +
          '<div class="atak-medical-item-meta">' + escapeHtml(t) + (a.grid ? ' · Grille ' + escapeHtml(a.grid) : '') + '</div>' +
          '</div>';
      }).join('');
    }
    list.innerHTML = html;
    list.querySelectorAll('[data-callsign]').forEach(function (el) {
      el.addEventListener('click', function () {
        var cs = el.getAttribute('data-callsign');
        if (!cs) return;
        if (typeof window.focusUnitByCallsign === 'function') {
          window.focusUnitByCallsign(cs);
          return;
        }
        document.querySelectorAll('.atak-unit-card').forEach(function (c) {
          var call = c.querySelector('.atak-unit-callsign');
          if (call && call.textContent && call.textContent.toUpperCase().indexOf(cs.toUpperCase()) >= 0) {
            c.click();
          }
        });
      });
    });
  }

  function apply(data) {
    renderBanner(data);
    renderList(data);
    var fp = JSON.stringify({
      a: ((data && data.alerts) || []).map(function (x) { return x.id || x.summary; }),
      u: ((data && data.criticalUnits) || []).map(function (x) { return (x.call_sign || '') + ':' + (x.health || ''); })
    });
    if (fp !== lastFingerprint) {
      var prev = lastFingerprint;
      lastFingerprint = fp;
      var alerts = (data && data.alerts) || [];
      if (prev && alerts.length) {
        var latest = alerts[alerts.length - 1];
        if (latest && latest.severity === 'critical') {
          showToast(latest.summary || latest.label);
          playAlertSound();
        }
      }
    }
    var badge = document.getElementById('atak-medical-tab-badge')
      || document.getElementById('overwatch-medical-tab-badge');
    if (badge) {
      var n = ((data && data.counts && data.counts.emergency) || 0);
      badge.textContent = n > 0 ? String(n) : '';
      badge.hidden = n <= 0;
    }
  }

  function fetchAlerts() {
    var base = String(getApiBase() || '').replace(/\/$/, '');
    if (!base) return Promise.resolve(null);
    var path = /\/api$/i.test(base)
      ? '/atak/medical-alerts'
      : '/api/atak/medical-alerts';
    var url = base + path + '?mapId=' + encodeURIComponent(getMapId()) + '&limit=40';
    return fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('medical-alerts ' + r.status);
        return r.json();
      })
      .then(function (data) {
        apply(data || {});
        return data;
      })
      .catch(function () { return null; });
  }

  function startPolling(intervalMs) {
    stopPolling();
    fetchAlerts();
    pollTimer = setInterval(fetchAlerts, intervalMs || 5000);
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function notifyFromChatMessage(msg) {
    var body = msg && (msg.body || msg.message);
    var parsed = (msg && msg.medical) || parseMessage(body);
    if (!parsed) return;
    showToast(parsed.summary || parsed.label);
    if (parsed.severity === 'critical') playAlertSound();
    fetchAlerts();
  }

  return {
    parseMessage: parseMessage,
    formatChatBody: formatChatBody,
    fetchAlerts: fetchAlerts,
    startPolling: startPolling,
    stopPolling: stopPolling,
    notifyFromChatMessage: notifyFromChatMessage,
    apply: apply,
    kindLabelFr: kindLabelFr
  };
})();
