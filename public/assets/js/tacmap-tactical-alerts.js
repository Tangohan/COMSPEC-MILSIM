/**
 * Panneau signalements Tacmap — miroir web de l’inbox cTab Athena / ATAK Enhanced
 * (Contact, fin de contact, FRAGO, SALUTE, opérateur à terre, bilan des dégâts).
 */
(function (global) {
  'use strict';

  var FILTERS = [
    { id: 'all', label: 'Tout' },
    { id: 'tic', label: 'Contact' },
    { id: 'bda', label: 'BDA' },
    { id: 'frago', label: 'FRAGO' },
    { id: 'salute', label: 'SALUTE' },
    { id: 'eagle_down', label: 'À terre' },
  ];

  var BDA_LABELS = {
    observer: 'Observateur',
    grid: 'Grille',
    time: 'Heure',
    dtg: 'Groupe date-heure',
    unit: 'Unité',
    trn: 'Numéro de transmission',
    type: 'Nature de la cible',
    desc: 'Description',
    ordnance: 'Munition employée',
    munitions: 'Munitions',
    platform: 'Plateforme',
    ekia: 'Pertes ennemies estimées',
    equip: 'Matériel observé',
    rating: 'Notation',
    reattack: 'Nouvelle attaque',
    send_to: 'Destinataires',
    reports: 'Comptes rendus liés',
    target: 'Cible',
    damage: 'Dégâts observés',
    enemy: 'Effets ennemis',
    friendly: 'Effets amis / civils',
    remarks: 'Remarques'
  };

  var EAGLE_LABELS = {
    category: 'Catégorie',
    dtg: 'Groupe date-heure',
    callsign: 'Indicatif',
    grid: 'Grille',
    casualty: 'Blessé',
    status: 'État',
    mechanism: 'Mécanisme',
    situation: 'Situation',
    medevac: 'Évacuation sanitaire',
    lz: 'Zone d’atterrissage',
    treatment: 'Traitement en cours',
    remarks: 'Remarques'
  };

  var TIC_LABELS = {
    unit: 'Unité',
    grid: 'Grille',
    desc: 'Description',
    send_to: 'Destinataires'
  };

  var SALUTE_LABELS = {
    size: 'Taille',
    activity: 'Activité',
    location: 'Localisation',
    unit: 'Unité / uniforme',
    time: 'Heure observée',
    equipment: 'Équipement'
  };

  var FRAGO_LABELS = {
    reference: 'Référence',
    situation: 'Situation',
    mission: 'Mission',
    execution: 'Exécution',
    support: 'Soutien',
    command: 'Commandement',
    acknowledge: 'Accusé de réception'
  };

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function stripHtmlLite(s) {
    return String(s == null ? '' : s)
      .replace(/<br\s*\/?>/gi, '\n')
      .replace(/<[^>]+>/g, '')
      .replace(/&amp;/g, '&')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&quot;/g, '"')
      .trim();
  }

  function parseBdaFields(summary) {
    var s = stripHtmlLite(summary);
    if (!s) return null;
    s = s.replace(/\s*[|·•]+\s*/g, '\n');
    s = s.replace(/\s+[—–]\s+(?=\d\.\s)/g, '\n');
    var rules = [
      ['observer', /^(?:Observer|Observateur|Émetteur)\s*:\s*(.+)$/i],
      ['grid', /^(?:Grid|Grille|Reported Grid)\s*:\s*(.+)$/i],
      ['time', /^(?:Time|Heure)\s*:\s*(.+)$/i],
      ['dtg', /^(?:DTG)\s*:\s*(.+)$/i],
      ['unit', /^(?:Unit|Unité)\s*:\s*(.+)$/i],
      ['trn', /^(?:TRN)\s*:\s*(.+)$/i],
      ['type', /^(?:Type)\s*:\s*(.+)$/i],
      ['desc', /^(?:Desc|Description)\s*:\s*(.+)$/i],
      ['ordnance', /^(?:Ordnance|Munition)\s*:\s*(.+)$/i],
      ['platform', /^(?:Platform|Plateforme)\s*:\s*(.+)$/i],
      ['ekia', /^(?:EKIA)\s*:\s*(.+)$/i],
      ['equip', /^(?:Equip|Équipement|Equipement)\s*:\s*(.+)$/i],
      ['rating', /^(?:Rating|Notation)\s*:\s*(.+)$/i],
      ['reattack', /^(?:Reattack|Nouvelle attaque)\s*:\s*(.+)$/i],
      ['target', /^(?:1\.\s*)?(?:Target\/?Objective|Cible(?:\s*\/\s*Objectif)?)\s*:\s*(.+)$/i],
      ['damage', /^(?:2\.\s*)?(?:Damage\s*Observed|Dégâts(?:\s*observés)?)\s*:\s*(.+)$/i],
      ['enemy', /^(?:3\.\s*)?(?:Enemy\s*BDA|Effets\s*ennemis)\s*:\s*(.+)$/i],
      ['friendly', /^(?:4\.\s*)?(?:Friendly\/?Civilian\s*Effects|Effets\s*amis(?:\s*\/\s*civils)?)\s*:\s*(.+)$/i],
      ['munitions', /^(?:5\.\s*)?(?:Munition\(s\) Count|Munitions Count|Munitions\/?Method|Munitions(?:\s*\/\s*méthode)?)\s*:\s*(.+)$/i],
      ['remarks', /^(?:6\.\s*)?(?:Remarks|Remarques)\s*:\s*(.+)$/i],
    ];
    var out = {};
    s.split(/\n+/).forEach(function (line) {
      line = String(line || '').trim();
      if (!line || /^BDA(?:\s*REPORT)?$/i.test(line)) return;
      for (var i = 0; i < rules.length; i++) {
        var id = rules[i][0];
        if (out[id]) continue;
        var m = line.match(rules[i][1]);
        if (!m) continue;
        var val = String(m[1] || '').trim().replace(/^[—\-–·|\s]+|[—\-–·|\s]+$/g, '');
        if (val && !/^(n\/?a|—|-)$/i.test(val)) out[id] = val;
        break;
      }
    });
    return Object.keys(out).length ? out : null;
  }

  function severityClass(sev) {
    if (sev === 'critical') return 'tacmap-talert--critical';
    if (sev === 'high') return 'tacmap-talert--high';
    if (sev === 'info') return 'tacmap-talert--info';
    return 'tacmap-talert--mid';
  }

  function normalizeKind(k) {
    return String(k || 'tic').toLowerCase().replace(/[\s-]+/g, '_');
  }

  function hasMapPos(a) {
    var x = a && a.pos_x != null ? parseFloat(a.pos_x) : NaN;
    var y = a && a.pos_y != null ? parseFloat(a.pos_y) : NaN;
    return !isNaN(x) && !isNaN(y) && !(Math.abs(x) < 0.5 && Math.abs(y) < 0.5);
  }

  function cleanSummary(summary, kind, callSign, grid) {
    var s = String(summary || '').trim();
    if (!s) return '';
    s = s.replace(/^ORDER_ID=[^\s|—\-]+[\s|—\-]*/i, '').trim();
    var labelMap = {
      frago: 'Ordre fragmentaire',
      bda: 'Bilan des dégâts',
      eagle_down: 'Opérateur à terre',
      tic: 'Contact',
      tic_clear: 'Fin de contact',
      salute: 'Compte rendu SALUTE',
    };
    var label = labelMap[normalizeKind(kind)] || '';
    var prev;
    do {
      prev = s;
      if (label) s = s.replace(new RegExp('^' + label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*[—\\-–·|]+\\s*', 'i'), '');
      s = s.replace(/^FRAGO\s*[—\-–·|]+\s*/i, '');
      if (callSign) {
        var csEsc = String(callSign).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        s = s.replace(new RegExp('^' + csEsc + '\\s*[—\\-–·|]+\\s*', 'i'), '');
        s = s.replace(new RegExp('^' + csEsc + '\\s*[·•]\\s*grille\\s+\\S+(?:\\s*[—\\-–·|]+\\s*)?', 'i'), '');
        s = s.replace(new RegExp('^' + csEsc + '\\s*[—\\-–]\\s*Grille\\s+\\S+(?:\\s*[—\\-–·|]+\\s*)?', 'i'), '');
      }
      if (grid) {
        var gEsc = String(grid).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        s = s.replace(new RegExp('^Grille\\s+' + gEsc + '(?:\\s*[—\\-–·|]+\\s*)?', 'i'), '');
      }
      s = s.replace(/^grille\s+\S+(?:\s*[—\-–·|]+\s*)?/i, '');
      s = s.replace(/^FRAGO\s*[—\-–]\s*[^\—\-–]+[·•]\s*grille\s+\S+\s*[—\-–]\s*/i, '');
      if (label && s.toLowerCase() === label.toLowerCase()) s = '';
      s = s.trim().replace(/^[—\-–·|\s]+|[—\-–·|\s]+$/g, '');
    } while (s !== prev);
    return s;
  }

  function parseFragoSections(summary) {
    var s = String(summary || '');
    var out = {};
    var keys = [
      ['situation', 'Situation'],
      ['mission', 'Mission'],
      ['execution', 'Exécution'],
      ['support', 'Soutien'],
      ['command', 'Commandement'],
    ];
    keys.forEach(function (pair) {
      var re = new RegExp(
        pair[1] + '\\s*:\\s*(.+?)(?=\\s*[—\\-–]\\s*(?:Situation|Mission|Exécution|Soutien|Commandement)\\s*:|$)',
        'i'
      );
      var m = s.match(re);
      if (m && m[1] && String(m[1]).trim()) out[pair[0]] = String(m[1]).trim();
    });
    return out;
  }

  function bodyPreview(a) {
    var kind = normalizeKind(a.kind);
    var summary = cleanSummary(a.summary, kind, a.call_sign, a.grid);
    var frago = a.frago && typeof a.frago === 'object' ? a.frago : parseFragoSections(summary);
    if (kind === 'frago' && frago && Object.keys(frago).length) {
      return Object.keys(FRAGO_LABELS)
        .filter(function (k) { return frago[k]; })
        .map(function (k) { return FRAGO_LABELS[k] + ' : ' + frago[k]; })
        .slice(0, 2)
        .join(' · ');
    }
    if (a.salute && typeof a.salute === 'object') {
      return Object.keys(a.salute)
        .map(function (k) { return String(a.salute[k] || '').trim(); })
        .filter(Boolean)
        .slice(0, 3)
        .join(' · ');
    }
    var bda = a.bda && typeof a.bda === 'object' ? a.bda : (kind === 'bda' ? parseBdaFields(a.summary || summary) : null);
    if (kind === 'bda' && bda && Object.keys(bda).length) {
      return ['target', 'damage', 'remarks']
        .filter(function (k) { return bda[k]; })
        .map(function (k) { return BDA_LABELS[k] + ' : ' + bda[k]; })
        .slice(0, 2)
        .join(' · ') || summary || 'Bilan des dégâts';
    }
    return summary || 'Aucun détail textuel.';
  }

  function formatDetailTime(raw) {
    var s = String(raw || '').trim();
    if (!s) return '';
    var d = new Date(s);
    if (isNaN(d.getTime())) {
      var iso = s.indexOf('T') >= 0 ? s : s.replace(' ', 'T');
      if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) iso += 'Z';
      d = new Date(iso);
    }
    if (isNaN(d.getTime())) return s;
    try {
      return d.toLocaleString('fr-FR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      }).replace(',', '');
    } catch (e) {
      return s;
    }
  }

  function ensureFilterBar(listEl) {
    if (!listEl || !listEl.parentNode) return null;
    var host = listEl.parentNode;
    var bar = host.querySelector('[data-tacmap-talert-filters]');
    if (bar) return bar;
    bar = document.createElement('div');
    bar.className = 'tacmap-talert-filters';
    bar.setAttribute('data-tacmap-talert-filters', '1');
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Filtrer les signalements');
    bar.innerHTML = FILTERS.map(function (f, i) {
      return (
        '<button type="button" class="tacmap-talert-filter' + (i === 0 ? ' is-active' : '') + '" data-filter="' +
        escapeHtml(f.id) + '">' + escapeHtml(f.label) + '</button>'
      );
    }).join('');
    host.insertBefore(bar, listEl);
    return bar;
  }

  function currentFilter(bar) {
    if (!bar) return 'all';
    var active = bar.querySelector('.tacmap-talert-filter.is-active');
    return active ? String(active.getAttribute('data-filter') || 'all') : 'all';
  }

  function filterAlerts(alerts, kind) {
    var list = Array.isArray(alerts) ? alerts : [];
    if (!kind || kind === 'all') return list;
    if (kind === 'tic') {
      return list.filter(function (a) {
        var k = normalizeKind(a.kind);
        return k === 'tic' || k === 'tic_clear';
      });
    }
    return list.filter(function (a) { return normalizeKind(a.kind) === kind; });
  }

  function ensureModal() {
    var el = document.getElementById('tacmap-talert-modal');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'tacmap-talert-modal';
    el.className = 'tacmap-talert-modal';
    el.hidden = true;
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'tacmap-talert-modal-title');
    el.innerHTML =
      '<div class="tacmap-talert-modal__backdrop" data-talert-close="1"></div>' +
      '<div class="tacmap-talert-modal__panel">' +
        '<header class="tacmap-talert-modal__head">' +
          '<h2 id="tacmap-talert-modal-title">Signalement</h2>' +
          '<button type="button" class="tacmap-talert-modal__close" data-talert-close="1" aria-label="Fermer">×</button>' +
        '</header>' +
        '<div class="tacmap-talert-modal__body" id="tacmap-talert-modal-body"></div>' +
        '<footer class="tacmap-talert-modal__foot" id="tacmap-talert-modal-foot"></footer>' +
      '</div>';
    document.body.appendChild(el);
    el.addEventListener('click', function (ev) {
      var t = ev.target;
      if (t && t.getAttribute && t.getAttribute('data-talert-close')) {
        el.hidden = true;
      }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !el.hidden) el.hidden = true;
    });
    return el;
  }

  function renderFieldDl(css, labels, bag) {
    if (!bag || typeof bag !== 'object') return '';
    var html = '<dl class="' + css + '">';
    var any = false;
    Object.keys(labels).forEach(function (k) {
      var v = String(bag[k] || '').trim();
      if (!v) return;
      any = true;
      html += '<div><dt>' + escapeHtml(labels[k]) + '</dt><dd>' + escapeHtml(v) + '</dd></div>';
    });
    html += '</dl>';
    return any ? html : '';
  }

  function openDetail(a, onLocate) {
    var modal = ensureModal();
    var title = document.getElementById('tacmap-talert-modal-title');
    var body = document.getElementById('tacmap-talert-modal-body');
    var foot = document.getElementById('tacmap-talert-modal-foot');
    if (!body || !foot) return;

    var kind = normalizeKind(a.kind);
    var summary = cleanSummary(a.summary, kind, a.call_sign, a.grid);
    var frago = a.frago && typeof a.frago === 'object' && Object.keys(a.frago).length
      ? a.frago
      : parseFragoSections(summary);
    var bda = a.bda && typeof a.bda === 'object' && Object.keys(a.bda).length
      ? a.bda
      : (kind === 'bda' ? parseBdaFields(String(a.summary || '') || summary) : null);
    var eagle = a.eagle_down && typeof a.eagle_down === 'object' ? a.eagle_down : null;
    var tic = a.tic && typeof a.tic === 'object' ? a.tic : null;

    if (title) title.textContent = a.kind_label || 'Signalement';

    var rows = '';
    rows += '<div class="tacmap-talert-modal__meta">' +
      '<div><span>Émetteur</span><strong>' + escapeHtml(a.call_sign || a.author || '—') + '</strong></div>' +
      '<div><span>Grille</span><strong>' + escapeHtml(a.grid || '—') + '</strong></div>' +
      (a.created_at ? '<div><span>Heure</span><strong>' + escapeHtml(formatDetailTime(a.created_at)) + '</strong></div>' : '') +
      '</div>';

    var structured = '';
    if (kind === 'frago' && frago && Object.keys(frago).length) {
      structured += '<ol class="tacmap-talert-modal__frago">';
      Object.keys(FRAGO_LABELS).forEach(function (k) {
        if (!frago[k]) return;
        structured += '<li><strong>' + escapeHtml(FRAGO_LABELS[k]) + '</strong><p>' + escapeHtml(frago[k]) + '</p></li>';
      });
      structured += '</ol>';
    } else if (kind === 'salute' || (a.salute && typeof a.salute === 'object')) {
      structured += renderFieldDl('tacmap-talert-modal__salute', SALUTE_LABELS, a.salute);
    } else if (kind === 'bda' && bda && Object.keys(bda).length) {
      structured += renderFieldDl('tacmap-talert-modal__bda', BDA_LABELS, bda);
    } else if (kind === 'eagle_down' && eagle) {
      structured += renderFieldDl('tacmap-talert-modal__eagle', EAGLE_LABELS, eagle);
    } else if (kind === 'tic' && tic) {
      structured += renderFieldDl('tacmap-talert-modal__tic', TIC_LABELS, tic);
    }
    if (structured) {
      rows += structured;
    } else {
      rows += '<p class="tacmap-talert-modal__text">' + escapeHtml(summary || 'Aucun détail textuel.') + '</p>';
    }
    body.innerHTML = rows;

    var actions = '';
    if (hasMapPos(a)) {
      actions += '<button type="button" class="tacmap-talert-modal__btn" data-talert-locate="1">Centrer sur la carte</button>';
    }
    if (a.order_id) {
      actions += '<button type="button" class="tacmap-talert-modal__btn tacmap-talert-modal__btn--primary" data-talert-order="' +
        escapeHtml(a.order_id) + '">Ouvrir l’ordre</button>';
    }
    actions += '<button type="button" class="tacmap-talert-modal__btn" data-talert-close="1">Fermer</button>';
    foot.innerHTML = actions;

    foot.querySelectorAll('[data-talert-locate]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (typeof onLocate === 'function') onLocate(parseFloat(a.pos_x), parseFloat(a.pos_y), a.id);
        modal.hidden = true;
      });
    });
    foot.querySelectorAll('[data-talert-order]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var oid = btn.getAttribute('data-talert-order');
        modal.hidden = true;
        try {
          global.dispatchEvent(new CustomEvent('atak:open-order', { detail: { orderId: oid } }));
        } catch (e) { /* ignore */ }
        if (typeof global.ATAKOpenOrder === 'function') {
          global.ATAKOpenOrder(oid);
        } else {
          var ordersTab = document.querySelector('[data-atak-tab="orders"], #atak-tab-orders, [href*="ordres"]');
          if (ordersTab) ordersTab.click();
        }
      });
    });

    modal.hidden = false;
  }

  function renderList(el, alerts, opts) {
    opts = opts || {};
    if (!el) return;
    var bar = ensureFilterBar(el);
    var kind = opts.filter != null ? opts.filter : currentFilter(bar);
    var filtered = filterAlerts(alerts, kind);

    if (!filtered.length) {
      el.innerHTML = '<p class="text-sm text-[color:var(--tm-muted)]">Aucun signalement pour ce filtre.</p>';
      return;
    }

    el.innerHTML = filtered.slice().reverse().map(function (a) {
      var id = a.id != null ? String(a.id) : '';
      var preview = bodyPreview(a);
      var openLabel = normalizeKind(a.kind) === 'frago' ? 'Ouvrir le FRAGO' : 'Ouvrir';
      return (
        '<article class="tacmap-talert ' + severityClass(a.severity) + '"' +
          (id ? ' data-alert-id="' + escapeHtml(id) + '"' : '') + '>' +
          '<header><strong>' + escapeHtml(a.kind_label || 'Alerte') + '</strong>' +
          '<span>' + escapeHtml(a.call_sign || a.author || '') + '</span></header>' +
          (a.grid ? '<p class="tacmap-talert__grid">Grille ' + escapeHtml(a.grid) + '</p>' : '') +
          '<p class="tacmap-talert__preview">' + escapeHtml(preview) + '</p>' +
          '<div class="tacmap-talert__actions">' +
            '<button type="button" class="tacmap-talert__btn tacmap-talert__btn--open" data-talert-open="1">'+
              escapeHtml(openLabel) + '</button>' +
            (hasMapPos(a)
              ? '<button type="button" class="tacmap-talert__btn" data-talert-locate="1" data-pos-x="' +
                escapeHtml(a.pos_x) + '" data-pos-y="' + escapeHtml(a.pos_y) + '">Carte</button>'
              : '') +
          '</div>' +
        '</article>'
      );
    }).join('');

    // Stash alerts for open handlers
    el._talertCache = filtered;
  }

  function bindUi(listEl, getAlerts, onLocate) {
    if (!listEl || listEl.getAttribute('data-talert-bound') === '1') return;
    listEl.setAttribute('data-talert-bound', '1');
    var bar = ensureFilterBar(listEl);
    if (bar) {
      bar.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-filter]') : null;
        if (!btn) return;
        bar.querySelectorAll('.tacmap-talert-filter').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        renderList(listEl, typeof getAlerts === 'function' ? getAlerts() : [], {
          filter: btn.getAttribute('data-filter'),
        });
      });
    }
    listEl.addEventListener('click', function (ev) {
      var openBtn = ev.target && ev.target.closest ? ev.target.closest('[data-talert-open]') : null;
      if (openBtn) {
        var art = openBtn.closest('.tacmap-talert');
        var id = art ? art.getAttribute('data-alert-id') : '';
        var alerts = typeof getAlerts === 'function' ? getAlerts() : (listEl._talertCache || []);
        var found = null;
        (alerts || []).forEach(function (a) {
          if (String(a.id) === String(id)) found = a;
        });
        if (!found && art) {
          var idx = Array.prototype.indexOf.call(listEl.querySelectorAll('.tacmap-talert'), art);
          var rev = (alerts || []).slice().reverse();
          found = rev[idx] || null;
        }
        if (found) openDetail(found, onLocate);
        return;
      }
      var locBtn = ev.target && ev.target.closest ? ev.target.closest('[data-talert-locate]') : null;
      if (!locBtn || typeof onLocate !== 'function') return;
      var x = parseFloat(locBtn.getAttribute('data-pos-x'));
      var y = parseFloat(locBtn.getAttribute('data-pos-y'));
      if (!isNaN(x) && !isNaN(y)) onLocate(x, y);
    });
  }

  function buildUrl(apiBase, mapId) {
    var base = String(apiBase || '').replace(/\/$/, '');
    if (base.indexOf('/atak') >= 0) {
      return base + '/tactical-alerts?mapId=' + encodeURIComponent(mapId || 1) + '&limit=40';
    }
    return base + '/atak/tactical-alerts?mapId=' + encodeURIComponent(mapId || 1) + '&limit=40';
  }

  function pollFlexible(apiBase, mapId, listEl, opts) {
    opts = opts || {};
    var cacheRef = { alerts: [] };
    bindUi(listEl, function () { return cacheRef.alerts; }, opts.onLocate);

    return fetch(buildUrl(apiBase, mapId), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var alerts = (data && data.alerts) ? data.alerts : [];
        cacheRef.alerts = alerts;
        renderList(listEl, alerts);
        if (typeof opts.onAlerts === 'function') opts.onAlerts(alerts);
        return alerts;
      })
      .catch(function () {
        cacheRef.alerts = [];
        renderList(listEl, []);
        if (typeof opts.onAlerts === 'function') opts.onAlerts([]);
        return [];
      });
  }

  function parseChatBody(body) {
    var raw = String(body || '').trim();
    raw = raw.replace(
      /^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*/u,
      ''
    );
    var upper = raw.toUpperCase();
    if (upper.indexOf('ALERTE TACTIQUE') !== 0) return null;
    var parts = raw.split('|').map(function (p) { return String(p || '').trim(); });
    var kindRaw = String(parts[1] || 'TIC').toUpperCase().replace(/[\s-]+/g, '_');
    var kindMap = {
      TIC: 'tic', CLEAR: 'tic_clear', TIC_CLEAR: 'tic_clear', TICCLEAR: 'tic_clear',
      FRAGO: 'frago', SALUTE: 'salute', EAGLE_DOWN: 'eagle_down', EAGLEDOWN: 'eagle_down',
      PANIC: 'eagle_down', BDA: 'bda', BDA_REPORT: 'bda',
    };
    var kind = kindMap[kindRaw] || 'tic';
    var labels = {
      tic: 'Contact', tic_clear: 'Fin de contact', frago: 'Ordre fragmentaire',
      salute: 'Compte rendu SALUTE', eagle_down: 'Opérateur à terre', bda: 'Bilan des dégâts',
    };
    var callSign = parts[2] || '';
    var grid = parts[3] || '';
    var posX = parts[4] !== undefined && parts[4] !== '' ? parseFloat(parts[4]) : NaN;
    var posY = parts[5] !== undefined && parts[5] !== '' ? parseFloat(parts[5]) : NaN;
    var tail = parts.slice(6);
    var orderId = '';
    if (tail.length && /^ORDER_ID=/i.test(tail[0])) {
      orderId = String(tail[0]).replace(/^ORDER_ID=/i, '').trim();
      tail = tail.slice(1);
    }
    var summary = cleanSummary(tail.join(' — ').trim(), kind, callSign, grid);
    var frago = kind === 'frago' ? parseFragoSections(summary) : {};
    var bda = kind === 'bda' ? parseBdaFields(tail.join(' — ').trim() || summary) : null;
    if (kind === 'bda' && bda) {
      var bits = ['target', 'damage', 'enemy', 'friendly', 'munitions', 'remarks']
        .filter(function (k) { return bda[k]; })
        .map(function (k) { return BDA_LABELS[k] + ' : ' + bda[k]; });
      if (bits.length) summary = bits.join(' — ');
    }
    return {
      is_tactical: true,
      kind: kind,
      kind_label: labels[kind] || 'Alerte',
      call_sign: callSign,
      grid: grid,
      pos_x: !isNaN(posX) ? posX : undefined,
      pos_y: !isNaN(posY) ? posY : undefined,
      summary: summary,
      order_id: orderId || undefined,
      frago: Object.keys(frago).length ? frago : undefined,
      bda: bda || undefined,
      severity: (kind === 'eagle_down' || kind === 'tic') ? 'critical' : (kind === 'frago' || kind === 'bda' ? 'high' : (kind === 'tic_clear' ? 'info' : 'medium')),
    };
  }

  function formatChatBody(body) {
    var p = parseChatBody(body);
    if (!p) return null;
    var preview = bodyPreview(p);
    var meta = escapeHtml(p.call_sign || '') + (p.grid ? ' · grille ' + escapeHtml(p.grid) : '');
    var bodyLine = '';
    if (
      preview &&
      preview !== 'Aucun détail textuel.' &&
      preview.toLowerCase() !== String(p.kind_label || '').toLowerCase()
    ) {
      bodyLine = '<br/><span class="atak-chat-talert-body">' + escapeHtml(preview) + '</span>';
    }
    return (
      '<span class="atak-chat-talert-badge">' + escapeHtml(p.kind_label) + '</span> ' +
      meta +
      bodyLine
    );
  }

  function parseGroupBody(body) {
    var raw = String(body || '').trim();
    raw = raw.replace(
      /^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*/u,
      ''
    );
    if (raw.toUpperCase().indexOf('GROUPE|') !== 0 && raw.toUpperCase() !== 'GROUPE') return null;
    var parts = raw.split('|').map(function (p) { return String(p || '').trim(); });
    return {
      is_group: true,
      label: 'Message de groupe',
      group_id: parts[1] || '',
      call_sign: parts[2] || '',
      grid: parts[3] || '',
      text: parts.slice(4).join('|') || 'Message de groupe',
    };
  }

  /**
   * Message privé cTab archivé : MP|from|to|texte
   */
  function parseMpBody(body) {
    var raw = String(body || '').trim();
    raw = raw.replace(
      /^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*/u,
      ''
    );
    if (raw.toUpperCase().indexOf('MP|') !== 0 && raw.toUpperCase() !== 'MP') return null;
    var parts = raw.split('|').map(function (p) { return String(p || '').trim(); });
    return {
      is_mp: true,
      label: 'Message privé',
      from: parts[1] || '',
      to: parts[2] || '',
      text: parts.slice(3).join('|') || 'Message privé',
    };
  }

  /**
   * @param {string} body
   * @param {{ outgoing?: boolean }=} opts
   */
  function formatGroupChatBody(body, opts) {
    var p = parseGroupBody(body);
    if (!p) return null;
    opts = opts || {};
    var outgoing = !!opts.outgoing;
    var dirLabel = outgoing ? 'Transmis' : 'Reçu';
    var dirCls = outgoing ? 'atak-chat-group-dir--out' : 'atak-chat-group-dir--in';
    var metaBits = [];
    if (p.group_id) metaBits.push('<span class="atak-chat-group-meta-item"><em>Groupe</em> ' + escapeHtml(p.group_id) + '</span>');
    if (p.call_sign) metaBits.push('<span class="atak-chat-group-meta-item"><em>Indicatif</em> ' + escapeHtml(p.call_sign) + '</span>');
    if (p.grid) metaBits.push('<span class="atak-chat-group-meta-item"><em>Grille</em> ' + escapeHtml(p.grid) + '</span>');
    return (
      '<div class="atak-chat-group-card' + (outgoing ? ' atak-chat-group-card--out' : '') + '">' +
        '<div class="atak-chat-group-head">' +
          '<span class="atak-chat-group-badge">' + escapeHtml(p.label) + '</span>' +
          '<span class="atak-chat-group-dir ' + dirCls + '">' + dirLabel + '</span>' +
        '</div>' +
        '<div class="atak-chat-group-text">' + escapeHtml(p.text) + '</div>' +
        (metaBits.length
          ? '<div class="atak-chat-group-meta">' + metaBits.join('') + '</div>'
          : '') +
      '</div>'
    );
  }

  /**
   * @param {string} body
   * @param {{ outgoing?: boolean }=} opts
   */
  function formatMpChatBody(body, opts) {
    var p = parseMpBody(body);
    if (!p) return null;
    opts = opts || {};
    var outgoing = !!opts.outgoing;
    var dirLabel = outgoing ? 'Envoyé' : 'Reçu';
    var dirCls = outgoing ? 'atak-chat-group-dir--out' : 'atak-chat-group-dir--in';
    var metaBits = [];
    if (p.from) metaBits.push('<span class="atak-chat-group-meta-item"><em>De</em> ' + escapeHtml(p.from) + '</span>');
    if (p.to) metaBits.push('<span class="atak-chat-group-meta-item"><em>À</em> ' + escapeHtml(p.to) + '</span>');
    return (
      '<div class="atak-chat-group-card atak-chat-mp-card' + (outgoing ? ' atak-chat-group-card--out' : '') + '">' +
        '<div class="atak-chat-group-head">' +
          '<span class="atak-chat-group-badge atak-chat-mp-badge">' + escapeHtml(p.label) + '</span>' +
          '<span class="atak-chat-group-dir ' + dirCls + '">' + dirLabel + '</span>' +
        '</div>' +
        '<div class="atak-chat-group-text">' + escapeHtml(p.text) + '</div>' +
        (metaBits.length
          ? '<div class="atak-chat-group-meta">' + metaBits.join('') + '</div>'
          : '') +
      '</div>'
    );
  }

  global.TacmapTacticalAlerts = {
    renderList: renderList,
    poll: pollFlexible,
    parseChatBody: parseChatBody,
    formatChatBody: formatChatBody,
    parseGroupBody: parseGroupBody,
    formatGroupChatBody: formatGroupChatBody,
    parseMpBody: parseMpBody,
    formatMpChatBody: formatMpChatBody,
    hasMapPos: hasMapPos,
    openDetail: openDetail,
    cleanSummary: cleanSummary,
    bodyPreview: bodyPreview,
  };
})(window);
