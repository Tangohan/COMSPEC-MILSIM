/* Overwatch Beta — télémétrie mod (médical, LOGSTAT, COMMS, journal enrichi) */
(function () {
  'use strict';

  var medicalCache = null;
  var logisticsCache = null;
  var commsCache = [];
  var commsAfterId = 0;
  var pollTimer = null;
  var started = false;
  var lastEmergency = 0;
  var logFilter = 'all';

  var TRIAGE = [
    { value: 'a_secourir', label: 'À secourir' },
    { value: 'en_cours', label: 'En cours' },
    { value: 'traite', label: 'Traité' },
    { value: 'kia', label: 'Hors combat' },
    { value: 'annule', label: 'Annulé' }
  ];

  function beta() {
    return window.OverwatchBeta || null;
  }

  function api(path, opts) {
    var b = beta();
    if (b && typeof b.api === 'function') return b.api(path, opts);
    return Promise.reject(new Error('api_unavailable'));
  }

  function mapId() {
    var b = beta();
    return b && b.mapId != null ? b.mapId : 1;
  }

  function esc(s) {
    var b = beta();
    if (b && b.escapeHtml) return b.escapeHtml(s);
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function toast(t) {
    var b = beta();
    if (b && b.toast) b.toast(t);
  }

  function clean(v, fb) {
    var b = beta();
    if (b && b.clean) return b.clean(v, fb);
    return v || fb || '';
  }

  function asList(payload, key) {
    var b = beta();
    if (b && b.asList) return b.asList(payload, key);
    if (payload && Array.isArray(payload[key])) return payload[key];
    return [];
  }

  function severityLabel(sev) {
    var s = String(sev || '').toLowerCase();
    if (s === 'critical') return 'Urgence';
    if (s === 'high' || s === 'urgent') return 'Prioritaire';
    if (s === 'medium') return 'À suivre';
    return 'Signalement';
  }

  function medicalKindLabel(kind) {
    var k = String(kind || '').toLowerCase();
    if (k === 'unconscious' || k === 'inconscient') return 'Inconscient';
    if (k === 'cardiac_arrest' || k === 'cardiac') return 'Arrêt cardiaque';
    if (k === 'kia' || k === 'dead') return 'Hors combat';
    if (k === 'injured' || k === 'wounded') return 'Blessé';
    return kind || 'Alerte santé';
  }

  function paintMissionBadge(emergency) {
    var btn = document.querySelector('.ow-nav [data-view="mission"]');
    if (!btn) return;
    var badge = btn.querySelector('.ow-unread');
    if (emergency > 0) {
      if (!badge) {
        badge = document.createElement('b');
        badge.className = 'ow-unread';
        btn.appendChild(badge);
      }
      badge.hidden = false;
      badge.textContent = String(emergency);
    } else if (badge) {
      badge.hidden = true;
      badge.textContent = '';
    }
    if (emergency > lastEmergency && emergency > 0) {
      toast(emergency === 1 ? 'Une urgence santé remonte du terrain.' : emergency + ' urgences santé remontent du terrain.');
    }
    lastEmergency = emergency;
  }

  function paintMedicalHost() {
    var host = document.getElementById('ow-med-alerts-host');
    if (!host) return;
    var data = medicalCache;
    if (!data) {
      host.innerHTML = '<p class="ow-help">Chargement des alertes santé…</p>';
      return;
    }
    var alerts = Array.isArray(data.alerts) ? data.alerts : [];
    var active = alerts.filter(function (a) {
      return !(a && a.triage && a.triage.is_resolved);
    });
    if (!active.length) {
      host.innerHTML = '<p class="ow-help">Aucune alerte santé active.</p>';
      return;
    }
    var canTriage = !!data.can_triage;
    var html = active
      .slice(0, 12)
      .map(function (a) {
        var id = String(a.id || a.alert_id || a.uid || a.call_sign || '');
        var cs = clean(a.call_sign || a.author || a.unit, 'Opérateur');
        var sev = String(a.severity || 'medium');
        var kind = medicalKindLabel(a.kind || a.type || a.status_label || a.label);
        var triage = (a.triage && a.triage.status_label) || '';
        var tagCls = sev === 'critical' ? ' amber' : '';
        var triageCtrl = '';
        if (canTriage && id) {
          triageCtrl =
            '<label class="ow-row">Secours<span class="ow-select"><select data-ow-med-triage="' +
            esc(id) +
            '">' +
            TRIAGE.map(function (o) {
              var sel = a.triage && a.triage.status === o.value ? ' selected' : '';
              return '<option value="' + o.value + '"' + sel + '>' + esc(o.label) + '</option>';
            }).join('') +
            '</select></span></label>';
        } else if (triage) {
          triageCtrl = '<span class="ow-tag">' + esc(triage) + '</span>';
        }
        return (
          '<div class="ow-event ow-med-alert" data-cs="' +
          esc(cs) +
          '"><span><strong>' +
          esc(cs) +
          '</strong> · ' +
          esc(kind) +
          '</span><span class="ow-tag' +
          tagCls +
          '">' +
          esc(severityLabel(sev)) +
          '</span>' +
          triageCtrl +
          '</div>'
        );
      })
      .join('');
    host.innerHTML = html;
  }

  function paintLogisticsHost() {
    var host = document.getElementById('ow-logistics-host');
    if (!host) return;
    var data = logisticsCache;
    if (!data) {
      host.innerHTML = '<p class="ow-help">Chargement du soutien…</p>';
      return;
    }
    var rows = Array.isArray(data.units || data.rows || data.items) ? data.units || data.rows || data.items : [];
    var alerts = data.alerts || {};
    var head = '';
    if ((alerts.critical || 0) > 0 || (alerts.low || 0) > 0) {
      head =
        '<div class="ow-form-actions" style="margin-bottom:8px">' +
        ((alerts.critical || 0) > 0
          ? '<span class="ow-tag amber">' + alerts.critical + ' critique(s)</span>'
          : '') +
        ((alerts.low || 0) > 0 ? '<span class="ow-tag">' + alerts.low + ' bas</span>' : '') +
        '</div>';
    }
    var needy = rows.filter(function (r) {
      return r && r.needs_resupply;
    });
    var show = (needy.length ? needy : rows).slice(0, 10);
    if (!show.length) {
      host.innerHTML = head + '<p class="ow-help">Aucun besoin de soutien signalé.</p>';
      return;
    }
    var html =
      head +
      show
        .map(function (r) {
          var cs = clean(r.call_sign, '—');
          var fuel = r.fuel != null ? Math.round(Number(r.fuel)) + ' %' : '—';
          var ammo = r.ammo != null && r.ammo !== '' ? String(r.ammo) : '—';
          var sig = r.signal || '';
          var btn =
            '<button type="button" class="ow-tag" data-ow-resupply="' +
            esc(cs) +
            '">Demander un ravitaillement</button>';
          return (
            '<div class="ow-event"><span><strong>' +
            esc(cs) +
            '</strong><small> Carburant ' +
            esc(fuel) +
            ' · Munitions ' +
            esc(ammo) +
            (sig ? ' · ' + esc(sig) : '') +
            '</small></span>' +
            btn +
            '</div>'
          );
        })
        .join('');
    host.innerHTML = html;
  }

  function paintCommsHost() {
    var host = document.getElementById('ow-comms-history');
    if (!host) return;
    if (!commsCache.length) {
      host.innerHTML = '<p class="ow-help">Aucune émission journalisée pour le moment.</p>';
      return;
    }
    var html = commsCache
      .slice()
      .reverse()
      .slice(0, 40)
      .map(function (ev) {
        var cs = clean(ev.call_sign, '—');
        var action = String(ev.action || 'tx').toLowerCase();
        var actLabel =
          action === 'tx_start' || action === 'start'
            ? 'Début d’émission'
            : action === 'tx_end' || action === 'end'
              ? 'Fin d’émission'
              : 'Émission';
        var meta = [];
        if (ev.freq) meta.push(String(ev.freq));
        if (ev.channel) meta.push('canal ' + ev.channel);
        if (ev.duration_s != null) meta.push(Math.round(Number(ev.duration_s)) + ' s');
        var when = ev.ts
          ? new Date(Number(ev.ts) * 1000).toLocaleTimeString('fr-FR', {
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit'
            })
          : '';
        return (
          '<div class="ow-event"><span><strong>' +
          esc(cs) +
          '</strong> · ' +
          esc(actLabel) +
          (meta.length ? '<small> ' + esc(meta.join(' · ')) + '</small>' : '') +
          '</span><small>' +
          esc(when) +
          '</small></div>'
        );
      })
      .join('');
    host.innerHTML = html;
  }

  function eventBucket(row) {
    var t = String(row.type || '').toLowerCase();
    var label = String(row.label || row.message || '').toLowerCase();
    if (t === 'medevac' || /santé|médic|bless|inconscient|casevac|medevac/.test(label)) return 'medical';
    if (t === 'tactical_alert' || /impact|tir|contact armé|combat/.test(label)) return 'combat';
    if (t === 'sigint' || /radio|émetteur|comms|émission/.test(label)) return 'radio';
    if (/logstat|carburant|munition|soutien|ravitail/.test(label)) return 'logistics';
    if (t === 'ingest' || /embarq|débarq|véhicule|chef de/.test(label)) return 'unit';
    if (t === 'tactical_report' || /salute|reco|bda|piste/.test(label)) return 'intel';
    return 'other';
  }

  function bucketLabel(b) {
    return (
      {
        all: 'Tout',
        medical: 'Sanitaire',
        combat: 'Contact armé',
        logistics: 'Soutien',
        radio: 'Radio',
        unit: 'Unités',
        intel: 'Renseignement',
        other: 'Autre'
      }[b] || b
    );
  }

  function openEnrichedLogs() {
    var b = beta();
    if (!b) return;
    api('/api/atak/activity?mapId=' + encodeURIComponent(mapId()) + '&limit=80&page=1').then(function (payload) {
      var events = asList(payload, 'events');
      var filters = ['all', 'medical', 'combat', 'logistics', 'radio', 'unit', 'intel'];
      var tabs =
        '<div class="ow-form-actions ow-log-filters" style="flex-wrap:wrap;gap:6px;margin-bottom:10px">' +
        filters
          .map(function (f) {
            var active = logFilter === f ? ' ow-primary' : ' ow-secondary';
            return (
              '<button type="button" class="ow-tag' +
              active +
              '" data-ow-log-filter="' +
              f +
              '">' +
              esc(bucketLabel(f)) +
              '</button>'
            );
          })
          .join('') +
        '</div>';
      var filtered =
        logFilter === 'all'
          ? events
          : events.filter(function (row) {
              return eventBucket(row) === logFilter;
            });
      var list =
        filtered
          .map(function (row) {
            var bucket = eventBucket(row);
            var cls = 'ow-event ow-log-' + bucket;
            var title = clean(row.label || row.message || row.type, 'Événement');
            var when = clean(row.at || row.created_at || row.ts, '');
            var meta = row.meta && typeof row.meta === 'object' ? row.meta : {};
            var jump = '';
            if (meta.x != null && meta.y != null && b.worldToLatLng) {
              jump =
                ' data-ow-log-x="' +
                esc(meta.x) +
                '" data-ow-log-y="' +
                esc(meta.y) +
                '"';
            }
            return (
              '<div class="' +
              cls +
              '"' +
              jump +
              '><span><span class="ow-tag">' +
              esc(bucketLabel(bucket)) +
              '</span> ' +
              esc(title) +
              '</span><small>' +
              esc(when) +
              '</small></div>'
            );
          })
          .join('') || '<p class="ow-help">Aucun événement dans ce filtre.</p>';
      b.openDrawer('Mission', 'Journal', tabs + list);
    }).catch(function () {
      b.openDrawer('Mission', 'Journal', '<p class="ow-help">Journal indisponible pour le moment.</p>');
    });
  }

  function refreshMedical() {
    return api('/api/atak/medical-alerts?mapId=' + encodeURIComponent(mapId()) + '&limit=40')
      .then(function (payload) {
        medicalCache = payload || { alerts: [], counts: {} };
        var emergency = Number((payload && payload.counts && payload.counts.emergency) || 0);
        paintMissionBadge(emergency);
        paintMedicalHost();
      })
      .catch(function () {
        medicalCache = medicalCache || { alerts: [], counts: {} };
        paintMedicalHost();
      });
  }

  function refreshLogistics() {
    return api('/api/atak/logistics?mapId=' + encodeURIComponent(mapId()))
      .then(function (payload) {
        logisticsCache = payload || { units: [], alerts: {} };
        if (!logisticsCache.units && Array.isArray(logisticsCache.rows)) {
          logisticsCache.units = logisticsCache.rows;
        }
        paintLogisticsHost();
      })
      .catch(function () {
        logisticsCache = logisticsCache || { units: [], alerts: {} };
        paintLogisticsHost();
      });
  }

  function refreshComms() {
    return api(
      '/api/atak/telemetry/comms?mapId=' +
        encodeURIComponent(mapId()) +
        '&after_id=' +
        encodeURIComponent(commsAfterId) +
        '&limit=80'
    )
      .then(function (payload) {
        var events = asList(payload, 'events');
        if (events.length) {
          events.forEach(function (ev) {
            commsCache.push(ev);
            if (ev.id && Number(ev.id) > commsAfterId) commsAfterId = Number(ev.id);
          });
          if (commsCache.length > 200) commsCache = commsCache.slice(-200);
        } else if (payload && payload.last_id != null && Number(payload.last_id) > commsAfterId) {
          commsAfterId = Number(payload.last_id);
        }
        paintCommsHost();
      })
      .catch(function () {
        paintCommsHost();
      });
  }

  function refreshMissionPanels() {
    return Promise.all([refreshMedical(), refreshLogistics()]);
  }

  function refreshAll() {
    var tasks = [refreshMedical(), refreshLogistics()];
    var radioOpen = document.querySelector('.ow-workspace.is-comms') == null;
    var panel = document.getElementById('tab-radio');
    if (panel && !panel.hidden && panel.offsetParent !== null) {
      tasks.push(refreshComms());
    } else if (document.getElementById('ow-comms-history')) {
      tasks.push(refreshComms());
    }
    return Promise.all(tasks);
  }

  function triage(alertId, status) {
    return api('/api/atak/medical-alerts/' + encodeURIComponent(alertId) + '/triage', {
      method: 'POST',
      body: { status: status, mapId: mapId() }
    })
      .then(function (payload) {
        if (payload && payload.ok === false) {
          toast('Triage refusé.');
          return;
        }
        toast('Statut de secours mis à jour.');
        return refreshMedical();
      })
      .catch(function () {
        toast('Impossible de mettre à jour le secours.');
      });
  }

  function requestResupply(cs) {
    return api('/api/atak/logistics/resupply', {
      method: 'POST',
      body: { mapId: mapId(), call_sign: cs, note: 'Demande depuis le poste' }
    })
      .then(function () {
        toast('Demande de ravitaillement enregistrée pour ' + cs + '.');
        return refreshLogistics();
      })
      .catch(function () {
        toast('Demande de ravitaillement refusée.');
      });
  }

  function bindUi() {
    document.addEventListener('change', function (ev) {
      var sel = ev.target && ev.target.closest ? ev.target.closest('[data-ow-med-triage]') : null;
      if (!sel) return;
      triage(sel.getAttribute('data-ow-med-triage'), sel.value);
    });
    document.addEventListener('click', function (ev) {
      var t = ev.target;
      if (!t || !t.closest) return;
      var res = t.closest('[data-ow-resupply]');
      if (res) {
        requestResupply(res.getAttribute('data-ow-resupply'));
        return;
      }
      var filt = t.closest('[data-ow-log-filter]');
      if (filt) {
        logFilter = filt.getAttribute('data-ow-log-filter') || 'all';
        openEnrichedLogs();
        return;
      }
      var jump = t.closest('[data-ow-log-x]');
      if (jump) {
        var b = beta();
        var x = Number(jump.getAttribute('data-ow-log-x'));
        var y = Number(jump.getAttribute('data-ow-log-y'));
        if (b && b.worldToLatLng && b.map) {
          var ll = b.worldToLatLng(x, y);
          if (ll) b.map.setView(ll, Math.max(b.map.getZoom(), 4));
        }
      }
    });
  }

  function onMissionOpened() {
    refreshMissionPanels();
  }

  function onRadioOpened() {
    refreshComms();
  }

  function start() {
    if (started) return;
    started = true;
    bindUi();
    refreshAll();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(function () {
      refreshMedical();
      var drawerTitle = document.getElementById('ow-drawer-title');
      var drawer = document.getElementById('ow-drawer');
      var missionOpen = drawer && !drawer.hidden && drawerTitle && drawerTitle.textContent === 'Mission';
      if (missionOpen) refreshLogistics();
      var radioPanel = document.getElementById('tab-radio');
      if (radioPanel && radioPanel.offsetParent !== null) refreshComms();
    }, 10000);
  }

  function boot() {
    if (beta()) {
      start();
      return;
    }
    var tries = 0;
    var t = setInterval(function () {
      tries++;
      if (beta()) {
        clearInterval(t);
        start();
      } else if (tries > 40) clearInterval(t);
    }, 250);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();

  window.OverwatchTelemetry = {
    refresh: refreshAll,
    refreshMedical: refreshMedical,
    refreshLogistics: refreshLogistics,
    refreshComms: refreshComms,
    paintMission: function () {
      paintMedicalHost();
      paintLogisticsHost();
      refreshMissionPanels();
    },
    onMissionOpened: onMissionOpened,
    onRadioOpened: onRadioOpened,
    openLogs: openEnrichedLogs
  };
})();
