/*
 * Règle d'obtention automatique d'une qualification : blocs (ET / OU) de conditions (seuil mesuré, formation
 * Academy, autre qualification, condition attestée par un instructeur). Les libellés viennent des gabarits de la page
 * (traduits côté serveur) ; ce script ne
 * fait que cloner, lire et écrire la règle dans le champ caché rule_json (format QualificationCriteriaEngine).
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-qr-builder]');
  if (!root) return;
  var form = root.closest('form');
  var json = root.querySelector('[data-qr-json]');
  var groupsEl = root.querySelector('[data-qr-groups]');
  var empty = root.querySelector('[data-qr-empty]');
  var rootOp = root.querySelector('[data-qr-root-op]');
  var tplGroup = root.querySelector('[data-qr-tpl-group]');
  var tplItem = root.querySelector('[data-qr-tpl-item]');
  var auto = document.querySelector('[data-qr-auto]');

  function unitLabel(u) {
    var el = root.querySelector('[data-qr-unit-label="' + u + '"]');
    return el ? el.textContent : u;
  }

  function fillScopes(item, kind, value) {
    var sel = item.querySelector('[data-qr-scope]');
    var wrap = item.querySelector('[data-qr-scope-wrap]');
    var src = root.querySelector('[data-qr-scopes="' + kind + '"]');
    sel.innerHTML = '';
    if (!src) {
      wrap.hidden = true;
      return;
    }
    wrap.hidden = false;
    sel.innerHTML = src.innerHTML;
    if (value != null) sel.value = value;
    if (sel.selectedIndex < 0 && sel.options.length) sel.selectedIndex = 0;
  }

  function syncItem(item, scopeValue) {
    var type = item.querySelector('[data-qr-type]').value;
    Array.prototype.forEach.call(item.querySelectorAll('[data-qr-when]'), function (el) {
      el.hidden = el.getAttribute('data-qr-when') !== type;
    });
    var help = item.querySelector('[data-qr-help]');
    help.textContent = '';
    if (type === 'attestation') {
      var key = item.querySelector('[data-qr-attest-key]');
      var aopt = key.options[key.selectedIndex];
      if (!aopt) return;
      item.querySelector('[data-qr-attest-min-wrap]').hidden = aopt.getAttribute('data-count') !== '1';
      item.querySelector('[data-qr-attest-unit]').textContent = unitLabel(aopt.getAttribute('data-unit'));
      help.textContent = aopt.getAttribute('data-label') || '';
      return;
    }
    if (type !== 'metric') return;
    var metric = item.querySelector('[data-qr-metric]');
    var opt = metric.options[metric.selectedIndex];
    if (!opt) return;
    fillScopes(item, opt.getAttribute('data-scope'), scopeValue);
    item.querySelector('[data-qr-window-wrap]').hidden = opt.getAttribute('data-window') !== '1';
    item.querySelector('[data-qr-unit]').textContent = unitLabel(opt.getAttribute('data-unit'));
    item.querySelector('[data-qr-min]').step = opt.getAttribute('data-step') || '1';
    help.textContent = opt.getAttribute('data-label') || '';
  }

  function addItem(group, c) {
    var node = tplItem.content.firstElementChild.cloneNode(true);
    group.querySelector('[data-qr-items]').appendChild(node);
    c = c || { type: 'metric', metric: 'playtime_hours', min: 10 };
    node.querySelector('[data-qr-type]').value = c.type || 'metric';
    if (c.metric) node.querySelector('[data-qr-metric]').value = c.metric;
    node.querySelector('[data-qr-min]').value = c.min != null ? c.min : '';
    node.querySelector('[data-qr-window]').value = String(c.window_days || 0);
    if (c.course_id) node.querySelector('[data-qr-course]').value = String(c.course_id);
    if (c.course_key) node.querySelector('[data-qr-course]').value = 'key:' + c.course_key;
    if (c.definition_id) node.querySelector('[data-qr-def]').value = String(c.definition_id);
    if (c.type === 'attestation') {
      node.querySelector('[data-qr-attest-key]').value = c.key || 'other';
      node.querySelector('[data-qr-attest-min]').value = c.min > 0 ? c.min : '';
      node.querySelector('[data-qr-attest-label]').value = c.label || '';
    }
    node.querySelector('[data-qr-attest-key]').addEventListener('change', function () { syncItem(node, null); write(); });
    syncItem(node, c.scope != null ? c.scope : null);
    node.querySelector('[data-qr-type]').addEventListener('change', function () { syncItem(node, null); write(); });
    node.querySelector('[data-qr-metric]').addEventListener('change', function () { syncItem(node, null); write(); });
    node.querySelector('[data-qr-del-item]').addEventListener('click', function () {
      node.remove();
      if (!group.querySelector('[data-qr-item]')) group.remove();
      refresh();
    });
    return node;
  }

  function addGroup(g) {
    var node = tplGroup.content.firstElementChild.cloneNode(true);
    groupsEl.appendChild(node);
    node.querySelector('[data-qr-group-op]').value = g && g.op === 'any' ? 'any' : 'all';
    node.querySelector('[data-qr-add-item]').addEventListener('click', function () { addItem(node); refresh(); });
    node.querySelector('[data-qr-del-group]').addEventListener('click', function () { node.remove(); refresh(); });
    var items = g && g.items && g.items.length ? g.items : [null];
    items.forEach(function (c) { addItem(node, c); });
    return node;
  }

  function read() {
    var groups = [];
    Array.prototype.forEach.call(groupsEl.querySelectorAll('[data-qr-group]'), function (gEl) {
      var items = [];
      Array.prototype.forEach.call(gEl.querySelectorAll('[data-qr-item]'), function (it) {
        var type = it.querySelector('[data-qr-type]').value;
        if (type === 'academy') {
          var courseValue = it.querySelector('[data-qr-course]').value;
          // Cours intégré de l'Academy (JTAC, cours OTAN) : « key:<clé> » ; formation de la communauté : son numéro.
          if (courseValue.indexOf('key:') === 0) { items.push({ type: 'academy', course_key: courseValue.slice(4) }); return; }
          var cid = parseInt(courseValue, 10);
          if (cid > 0) items.push({ type: 'academy', course_id: cid });
          return;
        }
        if (type === 'qualification') {
          var did = parseInt(it.querySelector('[data-qr-def]').value, 10);
          if (did > 0) items.push({ type: 'qualification', definition_id: did });
          return;
        }
        if (type === 'attestation') {
          // Condition non mesurée par le jeu, validée membre par membre par un instructeur.
          var akey = it.querySelector('[data-qr-attest-key]');
          var counts = akey.options[akey.selectedIndex] && akey.options[akey.selectedIndex].getAttribute('data-count') === '1';
          var amin = parseFloat(String(it.querySelector('[data-qr-attest-min]').value).replace(',', '.'));
          if (counts && !(amin > 0)) return;
          items.push({ type: 'attestation', key: akey.value, label: it.querySelector('[data-qr-attest-label]').value, min: counts ? amin : 0 });
          return;
        }
        var min = parseFloat(String(it.querySelector('[data-qr-min]').value).replace(',', '.'));
        if (!(min > 0)) return;
        var scope = it.querySelector('[data-qr-scope]');
        items.push({
          type: 'metric',
          metric: it.querySelector('[data-qr-metric]').value,
          scope: scope && !it.querySelector('[data-qr-scope-wrap]').hidden ? scope.value : '',
          min: min,
          window_days: it.querySelector('[data-qr-window-wrap]').hidden ? 0 : parseInt(it.querySelector('[data-qr-window]').value, 10) || 0
        });
      });
      if (items.length) groups.push({ op: gEl.querySelector('[data-qr-group-op]').value, items: items });
    });
    return groups.length ? { op: rootOp.value, groups: groups } : {};
  }

  function write() {
    json.value = JSON.stringify(read());
  }

  function refresh() {
    var groups = groupsEl.querySelectorAll('[data-qr-group]');
    Array.prototype.forEach.call(groups, function (gEl, i) {
      var name = gEl.querySelector('[data-qr-gname]');
      if (name) name.setAttribute('data-n', String(i + 1));
    });
    empty.hidden = groups.length > 0;
    rootOp.closest('.rq-field').hidden = groups.length < 2;
    write();
  }

  function toggleAuto() {
    root.classList.toggle('is-off', !!auto && !auto.checked);
  }

  var initial = {};
  try { initial = JSON.parse(json.value || '{}') || {}; } catch (e) { initial = {}; }
  rootOp.value = initial.op === 'any' ? 'any' : 'all';
  (initial.groups || []).forEach(addGroup);
  root.querySelector('[data-qr-add-group]').addEventListener('click', function () { addGroup(null); refresh(); });
  root.addEventListener('change', write);
  root.addEventListener('input', write);
  if (auto) auto.addEventListener('change', toggleAuto);
  if (form) form.addEventListener('submit', write);
  toggleAuto();
  refresh();
})();
