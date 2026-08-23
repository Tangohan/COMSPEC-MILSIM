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
  /** @type {Array<{id:string,code:string,label:string,description:string}>} */
  var customOrderTypes = [];
  var orderTypesLoaded = false;
  var serverOrderTypesReady = false;
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
    QRF: 'Force de réaction',
    FRAGO: 'Ordre fragmentaire'
  };

  function getApiBase() {
    if (window.ATAKSocket && window.ATAKSocket.getApiBase) return window.ATAKSocket.getApiBase();
    return (window.ATAK_API_BASE || '').replace(/\/$/, '');
  }

  function getMapId() {
    var mid = 1;
    if (window.ATAKSocket && window.ATAKSocket.getMapId) {
      mid = window.ATAKSocket.getMapId();
    } else if (window.ATAK_DEFAULT_MAP_ID != null && window.ATAK_DEFAULT_MAP_ID > 0) {
      mid = window.ATAK_DEFAULT_MAP_ID;
    }
    mid = parseInt(mid, 10);
    return (!mid || mid < 1 || isNaN(mid)) ? 1 : mid;
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
    if (t.indexOf('TYP_') === 0) {
      var typeId = t.slice(4);
      for (var ti = 0; ti < customOrderTypes.length; ti++) {
        if (String(customOrderTypes[ti].id) === typeId) return customOrderTypes[ti].label;
      }
      return custom || 'Ordre personnalisé';
    }
    if (t === 'CUSTOM' || t.indexOf('CUSTOM_') === 0 || t.indexOf('TPL_') === 0) {
      return custom || 'Ordre personnalisé';
    }
    return custom || 'Se déplacer';
  }

  function findOrderType(id) {
    var sid = String(id || '');
    for (var i = 0; i < customOrderTypes.length; i++) {
      if (String(customOrderTypes[i].id) === sid) return customOrderTypes[i];
    }
    return null;
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
    var tplGroup = document.getElementById('atak-order-type-custom');
    var typeGroup = document.getElementById('atak-order-type-custom-types');
    if (!sel) return;
    var prev = preserveSelection ? String(sel.value || '') : '';

    if (typeGroup) {
      typeGroup.innerHTML = '';
      if (!customOrderTypes.length) {
        typeGroup.hidden = true;
      } else {
        typeGroup.hidden = false;
        customOrderTypes.forEach(function (t) {
          var opt = document.createElement('option');
          opt.value = 'typ:' + t.id;
          opt.textContent = t.label;
          if (t.description) opt.title = t.description;
          typeGroup.appendChild(opt);
        });
      }
    }

    if (tplGroup) {
      tplGroup.innerHTML = '';
      if (!customTemplates.length) {
        tplGroup.hidden = true;
      } else {
        tplGroup.hidden = false;
        customTemplates.forEach(function (t) {
          var opt = document.createElement('option');
          opt.value = 'tpl:' + t.id;
          opt.textContent = t.label;
          tplGroup.appendChild(opt);
        });
      }
    }

    if (prev) {
      sel.value = prev;
      if (sel.value !== prev && (prev.indexOf('tpl:') === 0 || prev.indexOf('typ:') === 0)) {
        sel.value = 'MOVE';
      }
    }
    updateTemplateDeleteVisibility();
    updateTypeDeleteVisibility();
    syncInlineFragoMode();
  }

  function updateTypeDeleteVisibility() {
    var sel = document.getElementById('atak-order-type');
    var btn = document.getElementById('atak-order-type-delete-btn');
    if (!btn) return;
    var v = sel ? String(sel.value || '') : '';
    btn.hidden = v.indexOf('typ:') !== 0;
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
    if (raw.indexOf('typ:') === 0) {
      var ot = findOrderType(raw.slice(4));
      return {
        type: ot ? (ot.code || ('TYP_' + ot.id)) : 'CUSTOM',
        type_label: ot ? ot.label : 'Ordre personnalisé',
        template: null
      };
    }
    if (raw.indexOf('tpl:') === 0) {
      var tpl = findTemplate(raw.slice(4));
      return {
        type: 'CUSTOM',
        type_label: tpl ? tpl.label : 'Ordre personnalisé',
        template: tpl
      };
    }
    if (String(raw).toUpperCase() === 'FRAGO') {
      return { type: 'FRAGO', type_label: 'Ordre fragmentaire', template: null };
    }
    return {
      type: raw || 'MOVE',
      type_label: BUILTIN_TYPES[String(raw || '').toUpperCase()] || '',
      template: null
    };
  }

  function readFragoParts(prefix) {
    prefix = prefix || 'atak-order-frago';
    function val(suffix) {
      var el = document.getElementById(prefix + '-' + suffix);
      return el ? String(el.value || '').trim() : '';
    }
    return {
      sit: val('sit'),
      mis: val('mis'),
      exe: val('exe'),
      sup: val('sup'),
      cmd: val('cmd')
    };
  }

  function buildFragoPayload(parts) {
    parts = parts || {};
    var out = [];
    if (parts.sit) out.push('Situation: ' + parts.sit);
    if (parts.mis) out.push('Mission: ' + parts.mis);
    if (parts.exe) out.push('Exécution: ' + parts.exe);
    if (parts.sup) out.push('Soutien: ' + parts.sup);
    if (parts.cmd) out.push('Commandement: ' + parts.cmd);
    return out.join(' — ');
  }

  function parseFragoPayload(payload) {
    var text = String(payload || '');
    var map = { sit: '', mis: '', exe: '', sup: '', cmd: '' };
    var re = /(Situation|Mission|Exécution|Execution|Soutien|Support|Commandement|Command)\s*:\s*([^—\-|]+)/gi;
    var m;
    while ((m = re.exec(text)) !== null) {
      var key = String(m[1] || '').toLowerCase();
      var val = String(m[2] || '').trim().replace(/\s+/g, ' ');
      if (key.indexOf('situ') === 0) map.sit = val;
      else if (key.indexOf('miss') === 0) map.mis = val;
      else if (key.indexOf('ex') === 0) map.exe = val;
      else if (key.indexOf('sout') === 0 || key.indexOf('supp') === 0) map.sup = val;
      else if (key.indexOf('comm') === 0) map.cmd = val;
    }
    return map;
  }

  function fragoPartsFilled(parts) {
    parts = parts || {};
    return !!(parts.sit || parts.mis || parts.exe || parts.sup || parts.cmd);
  }

  function isFragoOrder(o) {
    return String((o && o.type) || '').toUpperCase() === 'FRAGO';
  }

  function orderTypeModifier(type) {
    var t = String(type || '').toUpperCase();
    if (t === 'FRAGO') return 'atak-order-item--frago';
    if (t === 'MOVE' || t === 'HOLD' || t === 'RECON' || t === 'CAS' || t === 'QRF') {
      return 'atak-order-item--c2 atak-order-item--' + t.toLowerCase();
    }
    return 'atak-order-item--c2';
  }

  function typeCodeLabel(type) {
    var t = String(type || '').toUpperCase();
    if (t === 'FRAGO') return 'FRAGO';
    if (t === 'MOVE') return 'MOVE';
    if (t === 'HOLD') return 'HOLD';
    if (t === 'RECON') return 'RECON';
    if (t === 'CAS') return 'CAS';
    if (t === 'QRF') return 'QRF';
    if (t.indexOf('TYP_') === 0 || t === 'CUSTOM' || t.indexOf('CUSTOM_') === 0) return 'C2';
    return 'C2';
  }

  function renderFragoSectionsHtml(parts) {
    var rows = [
      { key: 'sit', label: 'Situation' },
      { key: 'mis', label: 'Mission' },
      { key: 'exe', label: 'Exécution' },
      { key: 'sup', label: 'Soutien' },
      { key: 'cmd', label: 'Commandement' }
    ];
    var html = '<ol class="atak-order-frago-sections">';
    var any = false;
    rows.forEach(function (row) {
      var val = String((parts && parts[row.key]) || '').trim();
      if (!val) return;
      any = true;
      html +=
        '<li class="atak-order-frago-sec atak-order-frago-sec--' + row.key + '">' +
          '<span class="atak-order-frago-sec__label">' + escapeHtml(row.label) + '</span>' +
          '<p class="atak-order-frago-sec__text">' + escapeHtml(val) + '</p>' +
        '</li>';
    });
    html += '</ol>';
    return any ? html : '';
  }

  function fillFragoFields(prefix, parts) {
    prefix = prefix || 'atak-order-frago';
    parts = parts || {};
    ['sit', 'mis', 'exe', 'sup', 'cmd'].forEach(function (k) {
      var el = document.getElementById(prefix + '-' + k);
      if (el) el.value = parts[k] || '';
    });
  }

  function syncInlineFragoMode() {
    var typeEl = document.getElementById('atak-order-type');
    var isFrago = typeEl && String(typeEl.value || '').toUpperCase() === 'FRAGO';
    var payloadWrap = document.getElementById('atak-order-payload-wrap');
    var fragoWrap = document.getElementById('atak-order-frago-fields');
    if (payloadWrap) payloadWrap.hidden = !!isFrago;
    if (fragoWrap) fragoWrap.hidden = !isFrago;
  }

  function syncComposeFragoMode() {
    var typeEl = document.getElementById('atak-compose-type');
    var isFrago = typeEl && String(typeEl.value || '').toUpperCase() === 'FRAGO';
    var payloadWrap = document.getElementById('atak-compose-payload-wrap');
    var fragoWrap = document.getElementById('atak-compose-frago-fields');
    var title = document.getElementById('atak-order-compose-title');
    if (payloadWrap) payloadWrap.hidden = !!isFrago;
    if (fragoWrap) fragoWrap.hidden = !isFrago;
    if (title) title.textContent = isFrago ? 'Ordre fragmentaire' : 'Émettre un ordre';
  }

  function fillComposeTargetOptions(type, preserveSelection) {
    var wrap = document.getElementById('atak-compose-target-wrap');
    var sel = document.getElementById('atak-compose-target-ref');
    var labelEl = document.getElementById('atak-compose-target-label');
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
      opt.textContent = String(item.label == null ? item.id : item.label);
      sel.appendChild(opt);
    });
    if (prev) sel.value = prev;
  }

  function openComposeModal(opts) {
    opts = opts || {};
    if (!canIssue && !window.ATAK_CAN_ISSUE_ORDERS) {
      if (window.ATAKShowError) window.ATAKShowError('Profil commandement requis pour émettre un ordre.');
      return;
    }
    var modal = document.getElementById('atak-order-compose-modal');
    if (!modal) return;
    var typeEl = document.getElementById('atak-compose-type');
    var prioEl = document.getElementById('atak-compose-priority');
    var targetTypeEl = document.getElementById('atak-compose-target-type');
    var payloadEl = document.getElementById('atak-compose-payload');
    if (typeEl) typeEl.value = opts.type || (opts.frago ? 'FRAGO' : 'MOVE');
    if (prioEl) prioEl.value = opts.priority || (opts.frago ? 'URGENT' : 'IMPORTANT');
    if (targetTypeEl) targetTypeEl.value = opts.target_type || 'all';
    if (payloadEl) payloadEl.value = opts.payload || '';
    if (opts.frago || (opts.type && String(opts.type).toUpperCase() === 'FRAGO')) {
      fillFragoFields('atak-compose-frago', opts.fragoParts || parseFragoPayload(opts.payload || ''));
    } else {
      fillFragoFields('atak-compose-frago', {});
    }
    syncComposeFragoMode();
    loadRecipients().then(function () {
      fillComposeTargetOptions(targetTypeEl ? targetTypeEl.value : 'all', false);
      var refEl = document.getElementById('atak-compose-target-ref');
      if (refEl && opts.target_ref) refEl.value = String(opts.target_ref);
    });
    modal.hidden = false;
    document.body.classList.add('atak-order-compose-open');
    var focusEl = document.getElementById(opts.frago ? 'atak-compose-frago-sit' : 'atak-compose-payload');
    if (focusEl) setTimeout(function () { focusEl.focus(); }, 30);
  }

  function closeComposeModal() {
    var modal = document.getElementById('atak-order-compose-modal');
    if (modal) modal.hidden = true;
    document.body.classList.remove('atak-order-compose-open');
  }

  function postOrder(payload) {
    var base = getApiBase();
    if (!base) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Tacmap indisponible.');
      return Promise.reject(new Error('no_base'));
    }
    return fetch(base + '/api/atak/orders', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (r) {
        return r.json().then(function (d) { return { ok: r.ok, data: d }; });
      });
  }

  function loadOrderTypes(force) {
    if (orderTypesLoaded && !force) {
      return Promise.resolve(customOrderTypes);
    }
    var base = getApiBase();
    if (!base) {
      orderTypesLoaded = true;
      return Promise.resolve(customOrderTypes);
    }
    return fetch(base + '/api/atak/orders/types', {
      credentials: 'include',
      cache: 'no-store'
    })
      .then(function (r) {
        if (!r.ok) throw new Error('types ' + r.status);
        return r.json();
      })
      .then(function (data) {
        serverOrderTypesReady = !!(data && data.persisted);
        var serverList = (data && Array.isArray(data.types)) ? data.types : [];
        customOrderTypes = serverList.map(function (t) {
          return {
            id: String(t.id),
            code: String(t.code || ('TYP_' + t.id)),
            label: String(t.label || '').trim(),
            description: String(t.description || '').trim()
          };
        }).filter(function (t) { return t.id && t.label; });
        orderTypesLoaded = true;
        fillTypeSelect(true);
        return customOrderTypes;
      })
      .catch(function () {
        serverOrderTypesReady = false;
        customOrderTypes = [];
        orderTypesLoaded = true;
        fillTypeSelect(true);
        return customOrderTypes;
      });
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

  function showTypeForm(show) {
    var form = document.getElementById('atak-orders-type-form');
    if (!form) return;
    form.hidden = !show;
    if (show) {
      var labelEl = document.getElementById('atak-order-type-label');
      var descEl = document.getElementById('atak-order-type-desc');
      if (labelEl) {
        labelEl.value = '';
        labelEl.focus();
      }
      if (descEl) descEl.value = '';
    }
  }

  function createTypeFromForm() {
    var labelEl = document.getElementById('atak-order-type-label');
    var descEl = document.getElementById('atak-order-type-desc');
    var label = labelEl ? String(labelEl.value || '').trim() : '';
    var description = descEl ? String(descEl.value || '').trim() : '';
    if (!label) {
      if (window.ATAKShowError) window.ATAKShowError('Indiquez un intitulé pour ce type d’ordre.');
      return;
    }

    var base = getApiBase();
    if (!base) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Tacmap indisponible.');
      return;
    }

    fetch(base + '/api/atak/orders/types', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        label: label,
        description: description
      })
    })
      .then(function (r) {
        return r.json().then(function (d) { return { ok: r.ok, status: r.status, data: d }; });
      })
      .then(function (res) {
        if (!res.ok || !res.data || !res.data.type) {
          if (window.ATAKShowError) {
            window.ATAKShowError((res.data && res.data.message) || 'Impossible de créer ce type d’ordre.');
          }
          return;
        }
        serverOrderTypesReady = true;
        var t = res.data.type;
        var entry = {
          id: String(t.id),
          code: String(t.code || ('TYP_' + t.id)),
          label: String(t.label || label),
          description: String(t.description || description)
        };
        customOrderTypes = customOrderTypes.filter(function (x) { return String(x.id) !== entry.id; });
        customOrderTypes.push(entry);
        fillTypeSelect(false);
        var sel = document.getElementById('atak-order-type');
        if (sel) sel.value = 'typ:' + entry.id;
        updateTypeDeleteVisibility();
        showTypeForm(false);
        if (window.ATAKShowNotification) window.ATAKShowNotification('Type d’ordre enregistré.');
      })
      .catch(function () {
        if (window.ATAKShowError) window.ATAKShowError('Impossible de créer ce type d’ordre.');
      });
  }

  function deleteSelectedType() {
    var sel = document.getElementById('atak-order-type');
    var raw = sel ? String(sel.value || '') : '';
    if (raw.indexOf('typ:') !== 0) return;
    var id = raw.slice(4);
    var ot = findOrderType(id);
    if (!ot) return;
    if (!window.confirm('Retirer le type « ' + ot.label + ' » ?')) return;

    var base = getApiBase();
    var finish = function () {
      customOrderTypes = customOrderTypes.filter(function (t) { return String(t.id) !== String(id); });
      fillTypeSelect(false);
      if (sel) sel.value = 'MOVE';
      updateTypeDeleteVisibility();
      if (window.ATAKShowNotification) window.ATAKShowNotification('Type retiré.');
    };

    if (!base || !serverOrderTypesReady) {
      finish();
      return;
    }

    fetch(base + '/api/atak/orders/types/' + encodeURIComponent(id), {
      method: 'DELETE',
      credentials: 'include'
    })
      .then(function (r) {
        if (!r.ok) throw new Error('delete type');
        finish();
      })
      .catch(function () {
        if (window.ATAKShowError) window.ATAKShowError('Impossible de retirer ce type d’ordre.');
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

  function priorityBadgeClass(p) {
    switch (String(p || '').toUpperCase()) {
      case 'URGENT': return 'atak-order-badge--prio-urgent';
      case 'CONTACT': return 'atak-order-badge--prio-contact';
      case 'ROUTINE': return 'atak-order-badge--prio-routine';
      default: return 'atak-order-badge--prio-important';
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

  function statusBadgeClass(s, isOverdue) {
    var st = String(s || '').toUpperCase();
    if (isOverdue && (st === 'PENDING' || st === 'DELIVERED')) return 'atak-order-badge--status-overdue';
    switch (st) {
      case 'DELIVERED': return 'atak-order-badge--status-delivered';
      case 'ACK': return 'atak-order-badge--status-ack';
      case 'EXEC': return 'atak-order-badge--status-exec';
      case 'FAILED': return 'atak-order-badge--status-failed';
      case 'CANCELLED': return 'atak-order-badge--status-cancelled';
      default: return 'atak-order-badge--status-pending';
    }
  }

  function targetBadgeClass(type) {
    switch (String(type || '')) {
      case 'user': return 'atak-order-badge--target-user';
      case 'group': return 'atak-order-badge--target-group';
      case 'fire_team': return 'atak-order-badge--target-ft';
      case 'channel': return 'atak-order-badge--target-channel';
      case 'solo': return 'atak-order-badge--target-solo';
      default: return 'atak-order-badge--target-all';
    }
  }

  function isOwnIssuedOrder(o) {
    var me = String(getAuthor() || '').trim().toLowerCase();
    if (!me) return false;
    var issuer = String((o && o.issuer) || '').trim().toLowerCase();
    return !!issuer && issuer === me;
  }

  function prefillIssueForm(o, asFrago) {
    if (!canIssue && !window.ATAK_CAN_ISSUE_ORDERS) return;
    if (asFrago) {
      openComposeModal({
        frago: true,
        type: 'FRAGO',
        priority: 'URGENT',
        target_type: (o && o.target_type) || 'all',
        target_ref: (o && o.target_ref) || '',
        payload: '',
        fragoParts: (function () {
          var parentType = (o && (o.type_label || typeLabelFr(o.type))) || 'ordre';
          var base = parseFragoPayload((o && o.payload) || '');
          if (!base.sit) {
            base.sit = 'Suite à « ' + parentType + ' »'
              + ((o && o.payload) ? ' — ' + String(o.payload).slice(0, 120) : '');
          }
          return base;
        })()
      });
      return;
    }
    openComposeModal({
      type: (o && o.type) || 'MOVE',
      priority: (o && o.priority) || 'IMPORTANT',
      target_type: (o && o.target_type) || 'all',
      target_ref: (o && o.target_ref) || '',
      payload: (o && o.payload) || '',
      frago: !!(o && String(o.type || '').toUpperCase() === 'FRAGO'),
      fragoParts: parseFragoPayload((o && o.payload) || '')
    });
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
      loadOrderTypes(false);
      syncInlineFragoMode();
    }
    if (window.ATAKC2Workspace && typeof window.ATAKC2Workspace.syncIssueAccess === 'function') {
      window.ATAKC2Workspace.syncIssueAccess();
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
      var frago = isFragoOrder(o);
      var fragoParts = parseFragoPayload(o.payload_display || o.payload || '');
      var fragoHtml = frago ? renderFragoSectionsHtml(fragoParts) : '';

      if (o.radio_sim && o.sim_state && o.sim_state !== 'delivered' && status === 'PENDING') {
        radioLine =
          '<div class="atak-order-radio">' +
            escapeHtml(o.sim_state_label || 'Transmission…') +
            (o.sim_event_label ? ' · ' + escapeHtml(o.sim_event_label) : '') +
            (o.sim_latency_sec ? ' (~' + escapeHtml(String(o.sim_latency_sec)) + ' s)' : '') +
          '</div>';
      }

      var payloadText = (window.ATAKWaypoints && window.ATAKWaypoints.displayPayload)
        ? window.ATAKWaypoints.displayPayload(o)
        : (o.payload_display || o.payload || '');
      if (frago && fragoPartsFilled(fragoParts)) {
        payloadText = '';
      }
      var waypointMeta = (window.ATAKWaypoints && window.ATAKWaypoints.renderWaypointMetaHtml)
        ? window.ATAKWaypoints.renderWaypointMetaHtml(o)
        : '';
      var wpBtn = (window.ATAKWaypoints && window.ATAKWaypoints.waypointFromOrder && window.ATAKWaypoints.waypointFromOrder(o))
        ? '<button type="button" class="atak-order-btn atak-order-btn--cmd" data-order-cmd="focus-wp" data-order-id="' + escapeHtml(id) + '">Voir sur la carte</button>'
        : '';

      if (status !== 'CANCELLED' && status !== 'FAILED') {
        var btns = [];
        if (status === 'PENDING' || status === 'DELIVERED') {
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--primary" data-order-action="ACK" data-order-id="' + escapeHtml(id) + '">Confirmer réception</button>');
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--fail" data-order-action="FAILED" data-order-id="' + escapeHtml(id) + '">Refuser</button>');
        }
        if (status === 'ACK') {
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--exec" data-order-action="EXEC" data-order-id="' + escapeHtml(id) + '">En cours</button>');
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--fail" data-order-action="FAILED" data-order-id="' + escapeHtml(id) + '">Échec</button>');
        }
        if (status === 'EXEC') {
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--fail" data-order-action="FAILED" data-order-id="' + escapeHtml(id) + '">Échec</button>');
        }
        if (canIssue && status !== 'EXEC') {
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--cancel" data-order-action="CANCELLED" data-order-id="' + escapeHtml(id) + '">Annuler</button>');
        }
        if (canIssue) {
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--cmd" data-order-cmd="reissue" data-order-id="' + escapeHtml(id) + '">Relancer</button>');
          btns.push('<button type="button" class="atak-order-btn atak-order-btn--cmd" data-order-cmd="frago" data-order-id="' + escapeHtml(id) + '">FRAGO de suite</button>');
        }
        if (wpBtn) btns.push(wpBtn);
        if (btns.length) {
          actions = '<div class="atak-order-actions">' + btns.join('') + '</div>';
        }
      } else if (wpBtn) {
        actions = '<div class="atak-order-actions">' + wpBtn + '</div>';
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

      var ownBadge = isOwnIssuedOrder(o)
        ? '<span class="atak-order-badge atak-order-badge--own">Émis par vous</span>'
        : '';
      var sourceBadge = '';
      var src = String(o.source || '').toLowerCase();
      if (src === 'game') {
        sourceBadge = '<span class="atak-order-badge atak-order-badge--source-game">Terrain</span>';
      } else if (src === 'web') {
        sourceBadge = '<span class="atak-order-badge atak-order-badge--source-web">Poste de commandement</span>';
      }

      var kindLabel = frago ? 'Ordre fragmentaire' : 'Ordre C2';
      var bodyHtml = fragoHtml
        || (payloadText ? '<p class="atak-order-payload">' + escapeHtml(payloadText) + '</p>' : '')
        || '<p class="atak-order-payload atak-order-payload--empty">Aucun détail textuel.</p>';

      return (
        '<article class="atak-order-item ' + statusClass(status, isOverdue) + ' ' + orderTypeModifier(o.type) + '" data-order-id="' + escapeHtml(id) + '" data-order-kind="' + (frago ? 'frago' : 'c2') + '">' +
          '<header class="atak-order-card-head">' +
            '<div class="atak-order-card-kicker">' +
              '<span class="atak-order-code" aria-hidden="true">' + escapeHtml(typeCodeLabel(o.type)) + '</span>' +
              '<span class="atak-order-kind">' + escapeHtml(kindLabel) + '</span>' +
            '</div>' +
            '<div class="atak-order-item-top">' +
              '<span class="atak-order-type">' + escapeHtml(o.type_label || typeLabelFr(o.type)) + '</span>' +
              '<span class="atak-order-badges">' +
                '<span class="atak-order-badge ' + statusBadgeClass(status, isOverdue) + '">' +
                  escapeHtml(o.status_label || statusLabelFr(status, isOverdue)) +
                '</span>' +
                ownBadge +
                sourceBadge +
              '</span>' +
            '</div>' +
          '</header>' +
          '<div class="atak-order-meta atak-order-meta--badges">' +
            '<span class="atak-order-badge ' + priorityBadgeClass(o.priority) + '">' +
              escapeHtml(o.priority_label || priorityLabelFr(o.priority)) +
            '</span>' +
            '<span class="atak-order-badge ' + targetBadgeClass(o.target_type) + '" title="' + escapeHtml(dest) + '">' +
              escapeHtml(o.target_type_label || targetTypeLabel(o.target_type)) +
              (dest && String(o.target_type || '') !== 'all' ? ' · ' + escapeHtml(dest) : '') +
            '</span>' +
            '<span class="atak-order-badge atak-order-badge--time">' + escapeHtml(formatTime(o.updated_at || o.created_at)) + '</span>' +
          '</div>' +
          '<div class="atak-order-issuer">De <strong>' + escapeHtml(o.issuer || '—') + '</strong></div>' +
          radioLine +
          '<div class="atak-order-body">' + bodyHtml + '</div>' +
          waypointMeta +
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

  function isTerminalSignal(o) {
    var t = String((o && o.type) || '').toUpperCase();
    return t === 'VIBRATE' || t === 'NOTIFY' || t === 'HELMET_SNAP' || t === 'HELMET_SNAP_HD' || t === 'HELMET_STREAM';
  }

  function mergeOrdersDelta(incoming, isDelta) {
    incoming = Array.isArray(incoming) ? incoming : [];
    if (!isDelta) {
      ordersById = {};
    }
    incoming.forEach(function (o) {
      if (!o || !o.id) return;
      var id = String(o.id);
      // Signaux terminal : pas des ordres C2 — hors panneau / compteurs
      if (isTerminalSignal(o)) {
        delete ordersById[id];
        return;
      }
      ordersById[id] = o;
    });
    return ordersSortedFromCache().filter(function (o) { return !isTerminalSignal(o); });
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
    var isFrago = String(type).toUpperCase() === 'FRAGO';
    var payload = '';
    if (isFrago) {
      payload = buildFragoPayload(readFragoParts('atak-order-frago'));
      if (!payload) {
        if (window.ATAKShowError) window.ATAKShowError('Renseignez au moins une rubrique du FRAGO.');
        return;
      }
      typeLabel = typeLabel || 'Ordre fragmentaire';
    } else {
      payload = payloadEl ? String(payloadEl.value || '').trim() : '';
    }
    var radioSim = radioEl ? !!radioEl.checked : true;

    if (targetType !== 'all' && !targetRef) {
      var available = recipientsForType(targetType);
      var msg = available.length
        ? 'Choisissez un destinataire dans la liste.'
        : emptyRecipientMessage(targetType) + '.';
      if (window.ATAKShowError) window.ATAKShowError(msg);
      return;
    }

    postOrder({
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
      .then(function (res) {
        if (!res.ok) {
          if (window.ATAKShowError) {
            window.ATAKShowError((res.data && res.data.message) || 'Impossible d’émettre l’ordre.');
          }
          return;
        }
        if (payloadEl) payloadEl.value = '';
        fillFragoFields('atak-order-frago', {});
        var msgOk = isFrago ? 'FRAGO émis.' : 'Ordre émis.';
        if (res.data && res.data.order && res.data.order.radio_sim && res.data.order.sim_latency_sec > 0) {
          msgOk = (isFrago ? 'FRAGO émis' : 'Ordre émis') + ' — livraison radio estimée ~' + res.data.order.sim_latency_sec + ' s.';
        }
        if (window.ATAKShowNotification) window.ATAKShowNotification(msgOk);
        resetOrdersCache();
        fetchOrders();
        if (window.ATAKC2Workspace && typeof window.ATAKC2Workspace.setWork === 'function') {
          window.ATAKC2Workspace.setWork('suivi');
        }
      })
      .catch(function () {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’émettre l’ordre.');
      });
  }

  function issueOrderFromCompose() {
    if (!canIssue && !window.ATAK_CAN_ISSUE_ORDERS) {
      if (window.ATAKShowError) window.ATAKShowError('Profil commandement requis pour émettre un ordre.');
      return;
    }
    var typeEl = document.getElementById('atak-compose-type');
    var prioEl = document.getElementById('atak-compose-priority');
    var targetTypeEl = document.getElementById('atak-compose-target-type');
    var targetRefEl = document.getElementById('atak-compose-target-ref');
    var payloadEl = document.getElementById('atak-compose-payload');
    var radioEl = document.getElementById('atak-compose-radio-sim');
    var type = typeEl ? String(typeEl.value || 'MOVE') : 'MOVE';
    var isFrago = type.toUpperCase() === 'FRAGO';
    var typeLabel = BUILTIN_TYPES[type.toUpperCase()] || '';
    var priority = prioEl ? prioEl.value : 'IMPORTANT';
    var targetType = targetTypeEl ? targetTypeEl.value : 'all';
    var targetRef = targetRefEl ? String(targetRefEl.value || '').trim() : '';
    var targetLabel = '';
    if (targetRefEl && targetRefEl.selectedIndex >= 0) {
      var opt = targetRefEl.options[targetRefEl.selectedIndex];
      if (opt && targetRef) targetLabel = String(opt.textContent || '').trim();
    }
    var payload = '';
    if (isFrago) {
      payload = buildFragoPayload(readFragoParts('atak-compose-frago'));
      if (!payload) {
        if (window.ATAKShowError) window.ATAKShowError('Renseignez au moins une rubrique du FRAGO.');
        return;
      }
    } else {
      payload = payloadEl ? String(payloadEl.value || '').trim() : '';
    }
    if (targetType !== 'all' && !targetRef) {
      var available = recipientsForType(targetType);
      var msg = available.length
        ? 'Choisissez un destinataire dans la liste.'
        : emptyRecipientMessage(targetType) + '.';
      if (window.ATAKShowError) window.ATAKShowError(msg);
      return;
    }
    postOrder({
      mapId: getMapId(),
      type: type,
      type_label: typeLabel,
      priority: priority,
      target_type: targetType,
      target_ref: targetRef,
      target_label: targetLabel,
      payload: payload,
      issuer: getAuthor(),
      radio_sim: radioEl ? !!radioEl.checked : true
    })
      .then(function (res) {
        if (!res.ok) {
          if (window.ATAKShowError) {
            window.ATAKShowError((res.data && res.data.message) || 'Impossible d’émettre l’ordre.');
          }
          return;
        }
        closeComposeModal();
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification(isFrago ? 'FRAGO émis.' : 'Ordre émis.');
        }
        resetOrdersCache();
        fetchOrders();
        if (window.ATAKC2Workspace && typeof window.ATAKC2Workspace.setWork === 'function') {
          window.ATAKC2Workspace.setWork('suivi');
        }
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
    var cached = ordersById[String(orderId)] || null;
    var payload = {
      mapId: getMapId(),
      status: status,
      by: getAuthor(),
      id: String(orderId),
      external_id: String(orderId)
    };
    if (cached && cached.db_id) {
      payload.db_id = cached.db_id;
    }
    fetch(base + '/api/atak/orders/' + encodeURIComponent(orderId) + '/status', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
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
          else if (status === 'EXEC') window.ATAKShowNotification('Ordre passé en cours d’exécution.');
          else if (status === 'FAILED') window.ATAKShowNotification('Ordre marqué en échec.');
          else if (status === 'CANCELLED') window.ATAKShowNotification('Ordre annulé.');
          else window.ATAKShowNotification('Statut de l’ordre mis à jour.');
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
        updateTypeDeleteVisibility();
        syncInlineFragoMode();
        var resolved = resolveIssueType();
        if (resolved.template) applyTemplateToForm(resolved.template);
      });
    }
    var composeOpen = document.getElementById('atak-order-compose-open-btn');
    if (composeOpen && !composeOpen._bound) {
      composeOpen._bound = true;
      composeOpen.addEventListener('click', function () { openComposeModal({}); });
    }
    var composeFrago = document.getElementById('atak-order-compose-frago-btn');
    if (composeFrago && !composeFrago._bound) {
      composeFrago._bound = true;
      composeFrago.addEventListener('click', function () { openComposeModal({ frago: true }); });
    }
    var composeType = document.getElementById('atak-compose-type');
    if (composeType && !composeType._bound) {
      composeType._bound = true;
      composeType.addEventListener('change', syncComposeFragoMode);
    }
    var composeTargetType = document.getElementById('atak-compose-target-type');
    if (composeTargetType && !composeTargetType._bound) {
      composeTargetType._bound = true;
      composeTargetType.addEventListener('change', function () {
        var t = composeTargetType.value;
        loadRecipients().then(function () {
          fillComposeTargetOptions(t, false);
        });
      });
    }
    var composeSend = document.getElementById('atak-compose-send-btn');
    if (composeSend && !composeSend._bound) {
      composeSend._bound = true;
      composeSend.addEventListener('click', function () { issueOrderFromCompose(); });
    }
    var composeModal = document.getElementById('atak-order-compose-modal');
    if (composeModal && !composeModal._bound) {
      composeModal._bound = true;
      composeModal.addEventListener('click', function (ev) {
        var closer = ev.target && ev.target.closest ? ev.target.closest('[data-order-compose-close]') : null;
        if (closer) closeComposeModal();
      });
      document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && composeModal && !composeModal.hidden) {
          closeComposeModal();
        }
      });
    }
    var typeAddBtn = document.getElementById('atak-order-type-add-btn');
    if (typeAddBtn && !typeAddBtn._bound) {
      typeAddBtn._bound = true;
      typeAddBtn.addEventListener('click', function () { showTypeForm(true); });
    }
    var typeCancelBtn = document.getElementById('atak-order-type-cancel-btn');
    if (typeCancelBtn && !typeCancelBtn._bound) {
      typeCancelBtn._bound = true;
      typeCancelBtn.addEventListener('click', function () { showTypeForm(false); });
    }
    var typeConfirmBtn = document.getElementById('atak-order-type-confirm-btn');
    if (typeConfirmBtn && !typeConfirmBtn._bound) {
      typeConfirmBtn._bound = true;
      typeConfirmBtn.addEventListener('click', function () { createTypeFromForm(); });
    }
    var typeDeleteBtn = document.getElementById('atak-order-type-delete-btn');
    if (typeDeleteBtn && !typeDeleteBtn._bound) {
      typeDeleteBtn._bound = true;
      typeDeleteBtn.addEventListener('click', function () { deleteSelectedType(); });
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
        var cmdBtn = ev.target && ev.target.closest ? ev.target.closest('[data-order-cmd]') : null;
        if (cmdBtn) {
          var cmd = cmdBtn.getAttribute('data-order-cmd');
          var cid = cmdBtn.getAttribute('data-order-id');
          var order = cid ? ordersById[String(cid)] : null;
          if (order && cmd === 'reissue') prefillIssueForm(order, false);
          else if (order && cmd === 'frago') prefillIssueForm(order, true);
          else if (order && cmd === 'focus-wp' && window.ATAKWaypoints && window.ATAKWaypoints.focusOrderWaypoint) {
            window.ATAKWaypoints.focusOrderWaypoint(order);
          }
          return;
        }
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

  function openOrder(orderId) {
    var oid = String(orderId || '').trim();
    if (!oid) return false;

    if (window.ATAKSectionNav && typeof window.ATAKSectionNav.setSection === 'function') {
      window.ATAKSectionNav.setSection('c2');
    }
    var tab = document.querySelector('#atak-panel-left .atak-tab[data-tab="orders"]');
    if (tab && typeof tab.click === 'function') {
      tab.click();
    }
    if (window.ATAKC2Workspace && typeof window.ATAKC2Workspace.setWork === 'function') {
      window.ATAKC2Workspace.setWork('suivi');
    }

    var found = ordersById[oid] || null;
    if (!found) {
      Object.keys(ordersById).forEach(function (k) {
        var o = ordersById[k];
        if (!o) return;
        if (String(o.id) === oid || String(o.external_id || '') === oid) found = o;
      });
    }

    var focusId = found ? String(found.id || found.external_id || oid) : oid;
    setTimeout(function () {
      var el =
        document.querySelector('.atak-order-item[data-order-id="' + focusId.replace(/"/g, '') + '"]') ||
        document.querySelector('[data-order-id="' + focusId.replace(/"/g, '') + '"]');
      if (!el && found && found.external_id) {
        el = document.querySelector('.atak-order-item[data-order-id="' + String(found.external_id).replace(/"/g, '') + '"]');
      }
      if (!el) return;
      document.querySelectorAll('.atak-order-item--focus').forEach(function (n) {
        n.classList.remove('atak-order-item--focus');
      });
      el.classList.add('atak-order-item--focus');
      if (typeof el.scrollIntoView === 'function') {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }, 120);

    return !!found;
  }

  window.ATAKOpenOrder = openOrder;
  document.addEventListener('atak:open-order', function (ev) {
    var detail = ev && ev.detail ? ev.detail : {};
    openOrder(detail.orderId || detail.id || '');
  });

  return {
    fetchOrders: fetchOrders,
    startPolling: startPolling,
    loadRecipients: loadRecipients,
    loadTemplates: loadTemplates,
    loadOrderTypes: loadOrderTypes,
    parseOrderChatBody: parseOrderChatBody,
    formatChatBody: formatChatBody,
    typeLabelFr: typeLabelFr,
    targetTypeLabel: targetTypeLabel,
    recipientsForType: recipientsForType,
    openComposeModal: openComposeModal,
    closeComposeModal: closeComposeModal,
    openOrder: openOrder
  };
})();
