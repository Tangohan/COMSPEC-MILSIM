/* COMSPEC ATAK — Ordres C2 (destinataires structurés, ACK, annulation, délais radio) */
window.ATAKOrders = (function () {
  'use strict';

  var pollTimer = null;
  var lastFingerprint = '';
  var canIssue = false;
  var recipientsCache = null;
  var recipientsLoading = false;
  /** @type {Array<{id:string,label:string,default_payload:string,source?:string}>} */
  var customTemplates = [];
  var templatesLoaded = false;
  var serverTemplatesReady = false;
  /** @type {Record<string, object>} cache local pour merge delta (?since=) */
  var ordersById = {};
  /** Curseur datetime SQL du dernier poll (null = snapshot complet) */
  var ordersSince = null;
  var currentMapIdForOrders = null;

  var BUILTIN_TYPES = {
    MOVE: 'Se déplacer',
    HOLD: 'Tenir la position',
    RECON: 'Reconnaissance',
    CAS: 'Appui aérien',
    QRF: 'Force de réaction'
  };

  function getApiBase() {
    if (window.ATAKSocket && window.ATAKSocket.getApiBase) return window.ATAKSocket.getApiBase();
    return (window.ATAK_API_BASE || '').replace(/\/$/, '');
  }

  function getMapId() {
    if (window.ATAKSocket && window.ATAKSocket.getMapId) return window.ATAKSocket.getMapId();
    return (window.ATAK_DEFAULT_MAP_ID != null && window.ATAK_DEFAULT_MAP_ID > 0) ? window.ATAK_DEFAULT_MAP_ID : 1;
  }

  function getAuthor() {
    var u = window.ATAK_USER;
    if (u && (u.callsign || u.displayName)) return u.callsign || u.displayName;
    return '';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function parseOrderChatBody(body) {
    body = String(body || '').trim();
    if (!body || body.toUpperCase().indexOf('ORDER|') !== 0) return null;
    var parts = body.split('|');
    if (parts.length < 6) return null;
    return {
      id: parts[1] || '',
      type: parts[2] || 'MOVE',
      target: parts[3] || '',
      priority: parts[4] || 'IMPORTANT',
      issuer: parts[5] || '',
      payload: parts.slice(6).join('|')
    };
  }

  function typeLabelFr(type, customLabel) {
    var t = String(type || '').toUpperCase();
    if (BUILTIN_TYPES[t]) return BUILTIN_TYPES[t];
    var custom = String(customLabel || '').trim();
    if (t === 'CUSTOM' || t.indexOf('CUSTOM_') === 0 || t.indexOf('TPL_') === 0) {
      return custom || 'Ordre personnalisé';
    }
    return custom || 'Se déplacer';
  }

  function templatesStorageKey() {
    var u = window.ATAK_USER || {};
    var tid = u.tenantId != null ? String(u.tenantId) : '0';
    var uid = u.id != null ? String(u.id) : 'anon';
    return 'atak_order_templates_v1_' + tid + '_' + uid;
  }

  function loadLocalTemplates() {
    try {
      var raw = localStorage.getItem(templatesStorageKey());
      if (!raw) return [];
      var parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return [];
      return parsed.map(function (t) {
        return {
          id: String(t.id || ''),
          label: String(t.label || '').trim(),
          default_payload: String(t.default_payload || t.defaultPayload || '').trim(),
          source: t.source === 'server' ? 'server' : 'local'
        };
      }).filter(function (t) { return t.id && t.label; });
    } catch (e) {
      return [];
    }
  }

  function saveLocalTemplates(list) {
    customTemplates = Array.isArray(list) ? list : [];
    try {
      localStorage.setItem(templatesStorageKey(), JSON.stringify(customTemplates.map(function (t) {
        return {
          id: t.id,
          label: t.label,
          default_payload: t.default_payload || '',
          source: t.source === 'server' ? 'server' : 'local'
        };
      })));
    } catch (e) {}
  }

  function findTemplate(id) {
    var sid = String(id || '');
    for (var i = 0; i < customTemplates.length; i++) {
      if (String(customTemplates[i].id) === sid) return customTemplates[i];
    }
    return null;
  }

  function fillTypeSelect(preserveSelection) {
    var sel = document.getElementById('atak-order-type');
    var group = document.getElementById('atak-order-type-custom');
    if (!sel || !group) return;
    var prev = preserveSelection ? String(sel.value || '') : '';
    group.innerHTML = '';
    if (!customTemplates.length) {
      group.hidden = true;
    } else {
      group.hidden = false;
      customTemplates.forEach(function (t) {
        var opt = document.createElement('option');
        opt.value = 'tpl:' + t.id;
        opt.textContent = t.label;
        group.appendChild(opt);
      });
    }
    if (prev) {
      sel.value = prev;
      if (sel.value !== prev && prev.indexOf('tpl:') === 0) {
        sel.value = 'MOVE';
      }
    }
    updateTemplateDeleteVisibility();
  }

  function updateTemplateDeleteVisibility() {
    var sel = document.getElementById('atak-order-type');
    var btn = document.getElementById('atak-order-tpl-delete-btn');
    if (!btn) return;
    var v = sel ? String(sel.value || '') : '';
    btn.hidden = v.indexOf('tpl:') !== 0;
  }

  function applyTemplateToForm(template) {
    if (!template) return;
    var payloadEl = document.getElementById('atak-order-payload');
    if (payloadEl && template.default_payload) {
      payloadEl.value = template.default_payload;
    }
  }

  function resolveIssueType() {
    var typeEl = document.getElementById('atak-order-type');
    var raw = typeEl ? String(typeEl.value || 'MOVE') : 'MOVE';
    if (raw.indexOf('tpl:') === 0) {
      var tpl = findTemplate(raw.slice(4));
      return {
        type: 'CUSTOM',
        type_label: tpl ? tpl.label : 'Ordre personnalisé',
        template: tpl
      };
    }
    return {
      type: raw || 'MOVE',
      type_label: '',
      template: null
    };
  }

  function loadTemplates(force) {
    if (templatesLoaded && !force) {
      return Promise.resolve(customTemplates);
    }
    var local = loadLocalTemplates();
    customTemplates = local.slice();
    fillTypeSelect(true);

    var base = getApiBase();
    if (!base) {
      templatesLoaded = true;
      return Promise.resolve(customTemplates);
    }
    return fetch(base + '/api/atak/orders/templates', {
      credentials: 'include',
      cache: 'no-store'
    })
      .then(function (r) {
        if (!r.ok) throw new Error('templates ' + r.status);
        return r.json();
      })
      .then(function (data) {
        serverTemplatesReady = !!(data && data.persisted);
        var serverList = (data && Array.isArray(data.templates)) ? data.templates : [];
        if (serverTemplatesReady) {
          var fromServer = serverList.map(function (t) {
            return {
              id: String(t.id),
              label: String(t.label || '').trim(),
              default_payload: String(t.default_payload || '').trim(),
              source: 'server'
            };
          }).filter(function (t) { return t.id && t.label; });
          // Conservatoire : garder les modèles purement locaux (créés avant migration)
          var localOnly = local.filter(function (t) {
            return t.source !== 'server' && String(t.id).charAt(0) === 'L';
          });
          customTemplates = fromServer.concat(localOnly);
          saveLocalTemplates(customTemplates);
        } else if (!customTemplates.length && local.length) {
          customTemplates = local;
        }
        templatesLoaded = true;
        fillTypeSelect(true);
        return customTemplates;
      })
      .catch(function () {
        serverTemplatesReady = false;
        customTemplates = local;
        templatesLoaded = true;
        fillTypeSelect(true);
        return customTemplates;
      });
  }

  function showTplForm(show) {
    var form = document.getElementById('atak-orders-tpl-form');
    if (!form) return;
    form.hidden = !show;
    if (show) {
      var labelEl = document.getElementById('atak-order-tpl-label');
      var payloadTplEl = document.getElementById('atak-order-tpl-payload');
      var payloadEl = document.getElementById('atak-order-payload');
      var typeEl = document.getElementById('atak-order-type');
      if (labelEl) {
        var resolved = resolveIssueType();
        labelEl.value = resolved.template
          ? resolved.template.label
          : (typeEl && BUILTIN_TYPES[typeEl.value] ? '' : '');
        labelEl.focus();
      }
      if (payloadTplEl) {
        payloadTplEl.value = payloadEl ? String(payloadEl.value || '') : '';
      }
    }
  }

  function createTemplateFromForm() {
    var labelEl = document.getElementById('atak-order-tpl-label');
    var payloadTplEl = document.getElementById('atak-order-tpl-payload');
    var label = labelEl ? String(labelEl.value || '').trim() : '';
    var defaultPayload = payloadTplEl ? String(payloadTplEl.value || '').trim() : '';
    if (!label) {
      if (window.ATAKShowError) window.ATAKShowError('Indiquez un nom pour ce modèle d’ordre.');
      return;
    }

    var base = getApiBase();
    var finishLocal = function (tpl) {
      var next = customTemplates.filter(function (t) { return String(t.id) !== String(tpl.id); });
      next.push(tpl);
      saveLocalTemplates(next);
      fillTypeSelect(false);
      var sel = document.getElementById('atak-order-type');
      if (sel) sel.value = 'tpl:' + tpl.id;
      updateTemplateDeleteVisibility();
      showTplForm(false);
      if (window.ATAKShowNotification) window.ATAKShowNotification('Modèle enregistré.');
    };

    if (base) {
      fetch(base + '/api/atak/orders/templates', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          label: label,
          default_payload: defaultPayload
        })
      })
        .then(function (r) {
          return r.json().then(function (d) { return { ok: r.ok, status: r.status, data: d }; });
        })
        .then(function (res) {
          if (res.ok && res.data && res.data.template) {
            serverTemplatesReady = true;
            var t = res.data.template;
            finishLocal({
              id: String(t.id),
              label: String(t.label || label),
              default_payload: String(t.default_payload || defaultPayload),
              source: 'server'
            });
            return;
          }
          if (res.status === 503 || (res.data && res.data.error === 'not_migrated')) {
            serverTemplatesReady = false;
            finishLocal({
              id: 'L' + Date.now().toString(36),
              label: label,
              default_payload: defaultPayload,
              source: 'local'
            });
            return;
          }
          if (window.ATAKShowError) {
            window.ATAKShowError((res.data && res.data.message) || 'Impossible d’enregistrer le modèle.');
          }
        })
        .catch(function () {
          finishLocal({
            id: 'L' + Date.now().toString(36),
            label: label,
            default_payload: defaultPayload,
            source: 'local'
          });
        });
      return;
    }

    finishLocal({
      id: 'L' + Date.now().toString(36),
      label: label,
      default_payload: defaultPayload,
      source: 'local'
    });
  }

  function deleteSelectedTemplate() {
    var sel = document.getElementById('atak-order-type');
    var raw = sel ? String(sel.value || '') : '';
    if (raw.indexOf('tpl:') !== 0) return;
    var id = raw.slice(4);
    var tpl = findTemplate(id);
    if (!tpl) return;
    if (!window.confirm('Retirer le modèle « ' + tpl.label + ' » ?')) return;

    var finish = function () {
      customTemplates = customTemplates.filter(function (t) { return String(t.id) !== String(id); });
      saveLocalTemplates(customTemplates);
      fillTypeSelect(false);
      if (sel) sel.value = 'MOVE';
      updateTemplateDeleteVisibility();
      if (window.ATAKShowNotification) window.ATAKShowNotification('Modèle retiré.');
    };

    var base = getApiBase();
    if (base && tpl.source === 'server') {
      fetch(base + '/api/atak/orders/templates/' + encodeURIComponent(id) + '/delete', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
      })
        .then(function (r) {
          return r.json().then(function (d) { return { ok: r.ok, data: d }; });
        })
        .then(function (res) {
          if (!res.ok) {
            if (window.ATAKShowError) {
              window.ATAKShowError((res.data && res.data.message) || 'Impossible de retirer le modèle.');
            }
            return;
          }
          finish();
        })
        .catch(function () {
          if (window.ATAKShowError) window.ATAKShowError('Impossible de retirer le modèle.');
        });
      return;
    }
    finish();
  }

  function priorityLabelFr(p) {
    switch (String(p || '').toUpperCase()) {
      case 'URGENT': return 'Urgent';
      case 'CONTACT': return 'Contact';
      case 'ROUTINE': return 'Routine';
      default: return 'Important';
    }
  }

  function statusLabelFr(s, isOverdue) {
    if (isOverdue && (String(s || '').toUpperCase() === 'PENDING' || String(s || '').toUpperCase() === 'DELIVERED')) {
      return 'En retard';
    }
    switch (String(s || '').toUpperCase()) {
      case 'DELIVERED': return 'Reçu';
      case 'ACK': return 'Confirmé';
      case 'EXEC': return 'En cours';
      case 'FAILED': return 'Échec';
      case 'CANCELLED': return 'Annulé';
      default: return 'Émis';
    }
  }

  function formatChatBody(body) {
    var parsed = parseOrderChatBody(body);
    if (!parsed) return null;
    var summary = 'Ordre — ' + typeLabelFr(parsed.type) + ' (' + priorityLabelFr(parsed.priority) + ')';
    if (parsed.target) summary += ' → ' + parsed.target;
    return '<span class="atak-order-chat-flag" title="Ordre tactique">' + escapeHtml(summary) + '</span>';
  }

  function formatTime(iso) {
    if (!iso) return '—';
    var d = new Date(String(iso).replace(' ', 'T') + (String(iso).indexOf('Z') >= 0 ? '' : 'Z'));
    if (isNaN(d.getTime())) {
      d = new Date(iso);
    }
    if (isNaN(d.getTime())) return '—';
    var h = d.getUTCHours();
    var m = d.getUTCMinutes();
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ' Z';
  }

  function statusClass(status, isOverdue) {
    var s = String(status || '').toUpperCase();
    if (isOverdue && (s === 'PENDING' || s === 'DELIVERED')) return 'atak-order-item--overdue';
    switch (s) {
      case 'DELIVERED': return 'atak-order-item--delivered';
      case 'ACK': return 'atak-order-item--ack';
      case 'EXEC': return 'atak-order-item--exec';
      case 'FAILED': return 'atak-order-item--failed';
      case 'CANCELLED': return 'atak-order-item--cancelled';
      default: return 'atak-order-item--pending';
    }
  }

  function updateBadge(pending) {
    var badge = document.getElementById('atak-orders-tab-badge');
    if (!badge) return;
    var n = pending || 0;
    badge.textContent = n > 0 ? String(n) : '';
    badge.hidden = n <= 0;
  }

  function renderIssueForm(visible) {
    var wrap = document.getElementById('atak-orders-issue');
    if (!wrap) return;
    var wasHidden = !!wrap.hidden;
    wrap.hidden = !visible;
    // Ne recharger / reconstruire le select qu’à l’ouverture : le poll 4 s
    // réinitialisait sinon la sélection (« Choisir… ») avant l’émission.
    if (visible && (wasHidden || !recipientsCache)) {
      loadRecipients().then(function () {
        var typeEl = document.getElementById('atak-order-target-type');
        fillTargetOptions(typeEl ? typeEl.value : 'all', true);
      });
    }
    if (visible) {
      loadTemplates(false);
    }
  }

  function targetTypeLabel(type) {
    switch (String(type || '')) {
      case 'user': return 'Utilisateur';
      case 'group': return 'Groupe en jeu';
      case 'fire_team': return 'Fire team';
      case 'channel': return 'Canal';
      case 'solo': return 'ATAK Solo';
      default: return 'Toute l’équipe';
    }
  }

  function emptyRecipientMessage(type) {
    switch (String(type || '')) {
      case 'user': return 'Aucun utilisateur disponible';
      case 'group': return 'Aucun groupe en jeu disponible';
      case 'fire_team': return 'Aucune fire team disponible';
      case 'channel': return 'Aucun canal disponible';
      case 'solo': return 'Aucun terminal ATAK disponible';
      default: return 'Aucun destinataire disponible';
    }
  }

  function recipientsForType(type) {
    if (!recipientsCache) return [];
    if (type === 'user') return recipientsCache.users || [];
    if (type === 'group') return recipientsCache.groups || [];
    if (type === 'fire_team') return recipientsCache.fire_teams || [];
    if (type === 'channel') return recipientsCache.channels || [];
    if (type === 'solo') return recipientsCache.solos || [];
    return [];
  }

  function fillTargetOptions(type, preserveSelection) {
    var wrap = document.getElementById('atak-order-target-wrap');
    var sel = document.getElementById('atak-order-target-ref');
    var labelEl = document.getElementById('atak-order-target-label');
    if (!wrap || !sel) return;

    if (!type || type === 'all') {
      wrap.hidden = true;
      sel.innerHTML = '<option value="">—</option>';
      return;
    }

    wrap.hidden = false;
    if (labelEl) labelEl.textContent = targetTypeLabel(type);

    var prev = preserveSelection ? String(sel.value || '').trim() : '';
    var list = recipientsForType(type);

    if (!list.length) {
      sel.innerHTML = '<option value="">' + escapeHtml(emptyRecipientMessage(type)) + '</option>';
      return;
    }

    sel.innerHTML = '<option value="">Choisir…</option>';
    list.forEach(function (item) {
      var opt = document.createElement('option');
      opt.value = String(item.id == null ? '' : item.id);
      var label = String(item.label == null ? item.id : item.label);
      var color = String(item.color || '').trim();
      if (type === 'fire_team' && /^#[0-9A-Fa-f]{6}$/i.test(color)) {
        opt.textContent = '● ' + label;
        opt.style.color = color;
        opt.setAttribute('data-ft-color', color);
      } else {
        opt.textContent = label;
      }
      sel.appendChild(opt);
    });

    if (prev) {
      sel.value = prev;
      if (sel.value !== prev) {
        for (var i = 0; i < sel.options.length; i++) {
          if (String(sel.options[i].value) === prev) {
            sel.selectedIndex = i;
            break;
          }
        }
      }
    }
  }

  function loadRecipients(force) {
    if (recipientsLoading) return Promise.resolve(recipientsCache);
    if (recipientsCache && !force) {
      return Promise.resolve(recipientsCache);
    }
    var base = getApiBase();
    if (!base) return Promise.resolve(null);
    recipientsLoading = true;
    return fetch(base + '/api/atak/orders/recipients?mapId=' + encodeURIComponent(getMapId()), {
      credentials: 'include',
      cache: 'no-store'
    })
      .then(function (r) {
        if (!r.ok) throw new Error('recipients ' + r.status);
        return r.json();
      })
      .then(function (data) {
        recipientsCache = data || {};
        return recipientsCache;
      })
      .catch(function () {
        recipientsCache = recipientsCache || { users: [], groups: [], fire_teams: [], channels: [], solos: [] };
        return recipientsCache;
      })
      .finally(function () {
        recipientsLoading = false;
      });
  }

  function renderList(orders) {
    var list = document.getElementById('atak-orders-list');
    var empty = document.getElementById('atak-orders-empty');
    if (!list) return;
    orders = Array.isArray(orders) ? orders : [];
    if (!orders.length) {
      list.innerHTML = '';
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.hidden = true;
    list.innerHTML = orders.map(function (o) {
      var id = o.id || '';
      var status = String(o.status || 'PENDING').toUpperCase();
      var isOverdue = !!o.is_overdue;
      var actions = '';
      var radioLine = '';

      if (o.radio_sim && o.sim_state && o.sim_state !== 'delivered' && status === 'PENDING') {
        radioLine =
          '<div class="atak-order-radio">' +
            escapeHtml(o.sim_state_label || 'Transmission…') +
            (o.sim_event_label ? ' · ' + escapeHtml(o.sim_event_label) : '') +
            (o.sim_latency_sec ? ' (~' + escapeHtml(String(o.sim_latency_sec)) + ' s)' : '') +
          '</div>';
      }

      if (status !== 'CANCELLED' && status !== 'FAILED') {
        var btns = [];
        if (status === 'PENDING' || status === 'DELIVERED') {
          btns.push('<button type="button" class="atak-order-btn" data-order-action="ACK" data-order-id="' + escapeHtml(id) + '">Confirmer réception</button>');
        }
        if (status === 'ACK' || status === 'DELIVERED' || status === 'PENDING') {
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--exec" data-order-action="EXEC" data-order-id="' + escapeHtml(id) + '">En cours</button>');
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--fail" data-order-action="FAILED" data-order-id="' + escapeHtml(id) + '">Échec</button>');
        }
        if (canIssue && status !== 'EXEC') {
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--cancel" data-order-action="CANCELLED" data-order-id="' + escapeHtml(id) + '">Annuler</button>');
        }
        if (btns.length) {
          actions = '<div class="atak-order-actions">' + btns.join('') + '</div>';
        }
      }

      var dest = o.target_label || o.target || 'Toute l’équipe';
      var ackLine = '';
      if (o.ack_by || o.ack_at) {
        ackLine = '<div class="atak-order-ack">Confirmé par ' + escapeHtml(o.ack_by || '—') +
          (o.ack_at ? ' · ' + escapeHtml(formatTime(o.ack_at)) : '') + '</div>';
      }
      if (status === 'CANCELLED' && (o.cancelled_by || o.cancelled_at)) {
        ackLine = '<div class="atak-order-ack">Annulé par ' + escapeHtml(o.cancelled_by || '—') +
          (o.cancelled_at ? ' · ' + escapeHtml(formatTime(o.cancelled_at)) : '') + '</div>';
      }

      return (
        '<article class="atak-order-item ' + statusClass(status, isOverdue) + '" data-order-id="' + escapeHtml(id) + '">' +
          '<div class="atak-order-item-top">' +
            '<span class="atak-order-type">' + escapeHtml(o.type_label || typeLabelFr(o.type)) + '</span>' +
            '<span class="atak-order-status">' + escapeHtml(o.status_label || statusLabelFr(status, isOverdue)) + '</span>' +
          '</div>' +
          '<div class="atak-order-meta">' +
            '<span>' + escapeHtml(o.priority_label || priorityLabelFr(o.priority)) + '</span>' +
            '<span>' + escapeHtml(o.target_type_label || targetTypeLabel(o.target_type)) + ' · ' + escapeHtml(dest) + '</span>' +
            '<span>' + escapeHtml(formatTime(o.updated_at || o.created_at)) + '</span>' +
          '</div>' +
          '<div class="atak-order-issuer">De ' + escapeHtml(o.issuer || '—') + '</div>' +
          radioLine +
          (o.payload ? '<p class="atak-order-payload">' + escapeHtml(o.payload) + '</p>' : '') +
          ackLine +
          actions +
        '</article>'
      );
    }).join('');
  }

  function resetOrdersCache() {
    ordersById = {};
    ordersSince = null;
    lastFingerprint = '';
  }

  function ordersSortedFromCache() {
    return Object.keys(ordersById).map(function (k) {
      return ordersById[k];
    }).sort(function (a, b) {
      var ua = String((a && a.updated_at) || (a && a.created_at) || '');
      var ub = String((b && b.updated_at) || (b && b.created_at) || '');
      if (ua === ub) return 0;
      return ua < ub ? 1 : -1;
    });
  }

  function mergeOrdersDelta(incoming, isDelta) {
    incoming = Array.isArray(incoming) ? incoming : [];
    if (!isDelta) {
      ordersById = {};
    }
    incoming.forEach(function (o) {
      if (!o || !o.id) return;
      ordersById[String(o.id)] = o;
    });
    return ordersSortedFromCache();
  }

  function advanceOrdersSince(data, merged) {
    var cursor = data && (data.cursor || data.server_time);
    if (cursor) {
      ordersSince = String(cursor);
      return;
    }
    var max = ordersSince || '';
    (merged || []).forEach(function (o) {
      var u = String((o && o.updated_at) || '');
      if (u && (!max || u > max)) max = u;
    });
    if (max) ordersSince = max;
  }

  function fetchOrders() {
    var base = getApiBase();
    if (!base) return Promise.resolve();
    var mapId = getMapId();
    if (currentMapIdForOrders != null && String(currentMapIdForOrders) !== String(mapId)) {
      resetOrdersCache();
    }
    currentMapIdForOrders = mapId;

    var url = base + '/api/atak/orders?mapId=' + encodeURIComponent(mapId) + '&limit=80';
    if (ordersSince) {
      url += '&since=' + encodeURIComponent(ordersSince);
    }
    return fetch(url, {
      credentials: 'include',
      cache: 'no-store'
    })
      .then(function (r) {
        if (!r.ok) throw new Error('orders ' + r.status);
        return r.json();
      })
      .then(function (data) {
        canIssue = !!(data && data.canIssue);
        if (typeof window.ATAK_CAN_ISSUE_ORDERS === 'boolean') {
          canIssue = canIssue || window.ATAK_CAN_ISSUE_ORDERS;
        }
        if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.canIssueOrders === 'function'
            && window.ATAK_SESSION_PROFILE) {
          canIssue = !!window.ATAKSessionProfile.canIssueOrders();
        }
        renderIssueForm(canIssue);
        var incoming = (data && data.orders) || [];
        var isDelta = !!(data && data.delta && ordersSince);
        var orders = mergeOrdersDelta(incoming, isDelta);
        advanceOrdersSince(data, orders);
        var pending = (data && data.counts && data.counts.pending);
        if (pending == null) {
          pending = orders.filter(function (o) {
            var s = String((o && o.status) || '').toUpperCase();
            return s === 'PENDING' || s === 'DELIVERED';
          }).length;
        }
        var fp = JSON.stringify(orders.map(function (o) {
          return [o.id, o.status, o.updated_at, o.sim_state, o.is_overdue, o.deleted];
        }));
        if (fp !== lastFingerprint) {
          lastFingerprint = fp;
          renderList(orders);
          updateBadge(pending);
          if (window.ATAKShowNotification && pending > 0 && data._notify) {
            var high = false;
            for (var hi = 0; hi < incoming.length; hi++) {
              var pr = String((incoming[hi] && incoming[hi].priority) || '').toUpperCase();
              if (pr === 'URGENT' || pr === 'CONTACT') { high = true; break; }
            }
            window.ATAKShowNotification('Nouvel ordre en attente', { order: true, highPriority: high });
          }
        } else {
          updateBadge(pending);
        }
      })
      .catch(function () {
        /* silent — ne pas spammer si migration absente */
      });
  }

  function issueOrder() {
    if (!canIssue && !window.ATAK_CAN_ISSUE_ORDERS) {
      if (window.ATAKShowError) window.ATAKShowError('Connectez-vous pour émettre un ordre.');
      return;
    }
    var prioEl = document.getElementById('atak-order-priority');
    var targetTypeEl = document.getElementById('atak-order-target-type');
    var targetRefEl = document.getElementById('atak-order-target-ref');
    var payloadEl = document.getElementById('atak-order-payload');
    var radioEl = document.getElementById('atak-order-radio-sim');

    var resolved = resolveIssueType();
    var type = resolved.type || 'MOVE';
    var typeLabel = resolved.type_label || '';
    var priority = prioEl ? prioEl.value : 'IMPORTANT';
    var targetType = targetTypeEl ? targetTypeEl.value : 'all';
    var targetRef = targetRefEl ? String(targetRefEl.value || '').trim() : '';
    var targetLabel = '';
    if (targetRefEl && targetRefEl.selectedIndex >= 0) {
      var opt = targetRefEl.options[targetRefEl.selectedIndex];
      if (opt && targetRef) targetLabel = String(opt.textContent || '').trim();
    }
    var payload = payloadEl ? String(payloadEl.value || '').trim() : '';
    var radioSim = radioEl ? !!radioEl.checked : true;

    if (targetType !== 'all' && !targetRef) {
      var available = recipientsForType(targetType);
      var msg = available.length
        ? 'Choisissez un destinataire dans la liste.'
        : emptyRecipientMessage(targetType) + '.';
      if (window.ATAKShowError) window.ATAKShowError(msg);
      return;
    }

    var base = getApiBase();
    if (!base) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Tacmap indisponible.');
      return;
    }
    fetch(base + '/api/atak/orders', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mapId: getMapId(),
        type: type,
        type_label: typeLabel,
        priority: priority,
        target_type: targetType,
        target_ref: targetRef,
        target_label: targetLabel,
        payload: payload,
        issuer: getAuthor(),
        radio_sim: radioSim
      })
    })
      .then(function (r) {
        return r.json().then(function (d) { return { ok: r.ok, data: d }; });
      })
      .then(function (res) {
        if (!res.ok) {
          if (window.ATAKShowError) {
            window.ATAKShowError((res.data && res.data.message) || 'Impossible d’émettre l’ordre.');
          }
          return;
        }
        if (payloadEl) payloadEl.value = '';
        var msg = 'Ordre émis.';
        if (res.data && res.data.order && res.data.order.radio_sim && res.data.order.sim_latency_sec > 0) {
          msg = 'Ordre émis — livraison radio estimée ~' + res.data.order.sim_latency_sec + ' s.';
        }
        if (window.ATAKShowNotification) window.ATAKShowNotification(msg);
        resetOrdersCache();
        fetchOrders();
      })
      .catch(function () {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’émettre l’ordre.');
      });
  }

  function updateStatus(orderId, status) {
    var base = getApiBase();
    if (!base || !orderId) return;
    if (status === 'CANCELLED') {
      if (!window.confirm('Annuler cet ordre ?')) return;
    }
    fetch(base + '/api/atak/orders/' + encodeURIComponent(orderId) + '/status', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mapId: getMapId(),
        status: status,
        by: getAuthor()
      })
    })
      .then(function (r) {
        return r.json().then(function (d) { return { ok: r.ok, data: d }; });
      })
      .then(function (res) {
        if (!res.ok) {
          if (window.ATAKShowError) {
            window.ATAKShowError((res.data && res.data.message) || 'Impossible de mettre à jour l’ordre.');
          }
          return;
        }
        if (window.ATAKShowNotification) {
          if (status === 'ACK') window.ATAKShowNotification('Réception confirmée.');
          else if (status === 'CANCELLED') window.ATAKShowNotification('Ordre annulé.');
        }
        resetOrdersCache();
        fetchOrders();
      })
      .catch(function () {
        if (window.ATAKShowError) window.ATAKShowError('Impossible de mettre à jour l’ordre.');
      });
  }

  function bindUi() {
    var issueBtn = document.getElementById('atak-order-issue-btn');
    if (issueBtn && !issueBtn._bound) {
      issueBtn._bound = true;
      issueBtn.addEventListener('click', function () { issueOrder(); });
    }
    var typeEl = document.getElementById('atak-order-target-type');
    if (typeEl && !typeEl._bound) {
      typeEl._bound = true;
      typeEl.addEventListener('change', function () {
        var t = typeEl.value;
        if (t === 'all') {
          fillTargetOptions('all');
          return;
        }
        loadRecipients().then(function () {
          fillTargetOptions(t, false);
        });
      });
    }
    var orderTypeEl = document.getElementById('atak-order-type');
    if (orderTypeEl && !orderTypeEl._boundTpl) {
      orderTypeEl._boundTpl = true;
      orderTypeEl.addEventListener('change', function () {
        updateTemplateDeleteVisibility();
        var resolved = resolveIssueType();
        if (resolved.template) applyTemplateToForm(resolved.template);
      });
    }
    var saveBtn = document.getElementById('atak-order-tpl-save-btn');
    if (saveBtn && !saveBtn._bound) {
      saveBtn._bound = true;
      saveBtn.addEventListener('click', function () { showTplForm(true); });
    }
    var cancelBtn = document.getElementById('atak-order-tpl-cancel-btn');
    if (cancelBtn && !cancelBtn._bound) {
      cancelBtn._bound = true;
      cancelBtn.addEventListener('click', function () { showTplForm(false); });
    }
    var confirmBtn = document.getElementById('atak-order-tpl-confirm-btn');
    if (confirmBtn && !confirmBtn._bound) {
      confirmBtn._bound = true;
      confirmBtn.addEventListener('click', function () { createTemplateFromForm(); });
    }
    var deleteBtn = document.getElementById('atak-order-tpl-delete-btn');
    if (deleteBtn && !deleteBtn._bound) {
      deleteBtn._bound = true;
      deleteBtn.addEventListener('click', function () { deleteSelectedTemplate(); });
    }
    var list = document.getElementById('atak-orders-list');
    if (list && !list._bound) {
      list._bound = true;
      list.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-order-action]') : null;
        if (!btn) return;
        var action = btn.getAttribute('data-order-action');
        var id = btn.getAttribute('data-order-id');
        if (action && id) updateStatus(id, action);
      });
    }
  }

  function refreshIssuePermission() {
    if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.canIssueOrders === 'function'
        && window.ATAK_SESSION_PROFILE) {
      canIssue = !!window.ATAKSessionProfile.canIssueOrders();
    } else {
      canIssue = !!window.ATAK_CAN_ISSUE_ORDERS;
    }
    renderIssueForm(canIssue);
  }

  function startPolling(intervalMs) {
    bindUi();
    refreshIssuePermission();
    if (!window.__atakOrdersProfileBound) {
      window.__atakOrdersProfileBound = true;
      document.addEventListener('atak:session-profile', refreshIssuePermission);
    }
    fetchOrders();
    if (pollTimer) clearInterval(pollTimer);
    // Poll plus fréquent pendant les délais radio (2–15 s)
    pollTimer = setInterval(fetchOrders, intervalMs || 4000);
  }

  return {
    fetchOrders: fetchOrders,
    startPolling: startPolling,
    loadRecipients: loadRecipients,
    loadTemplates: loadTemplates,
    parseOrderChatBody: parseOrderChatBody,
    formatChatBody: formatChatBody,
    typeLabelFr: typeLabelFr
  };
})();
