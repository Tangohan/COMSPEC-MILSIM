/**
 * Effectifs — Chaîne de commandement : « Remonter la chaîne » d’un membre (surbrillance + fil),
 * sélecteur du chef d’une unité (envoie le formulaire habituel commanders[unité]) et menu d’un membre.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-eff-chain]');
  var dataEl = document.getElementById('eff-chain-data');
  if (!root || !dataEl || !window.EffKit) return;
  var K = window.EffKit;
  var data;
  try {
    data = JSON.parse(dataEl.textContent || '{}');
  } catch (e) {
    return;
  }
  var units = data.units || {};
  var members = data.members || {};
  var canManage = root.hasAttribute('data-can-manage');
  var chart = root.querySelector('[data-chain-chart]');
  var path = root.querySelector('[data-chain-path]');
  var q = root.querySelector('[data-chain-q]');
  var quick = root.querySelector('[data-chain-quick]');
  var quickField = root.querySelector('[data-chain-quick-field]');
  var pop = root.querySelector('[data-chain-pop]');
  var menu = root.querySelector('[data-chain-menu]');

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }
  function memberAvatar(id, size) {
    var m = members[id] || {};
    return K.avatar(m.initials, m.color, size);
  }
  function memberSub(id) {
    var m = members[id] || {};
    if (m.commands && m.commands.length) return 'Chef de ' + m.commands[0];
    return m.unitName || 'Sans unité';
  }

  // ——— Remonter la chaîne ———
  function follow(id) {
    if (!chart || !path) return;
    var ids = id ? [id].concat((members[id] || {}).chain || []) : [];
    chart.classList.toggle('is-focus', ids.length > 0);
    chart.querySelectorAll('[data-chain-member]').forEach(function (el) {
      var mid = parseInt(el.getAttribute('data-chain-member'), 10);
      el.classList.toggle('is-hl', ids.indexOf(mid) !== -1);
      el.classList.toggle('is-me', mid === id);
    });
    if (!id) {
      path.hidden = true;
      path.innerHTML = '';
      return;
    }
    var steps = ids.slice().reverse();
    var html = steps.map(function (sid, i) {
      var m = members[sid] || {};
      return (i ? '<span class="effk-path__ar" aria-hidden="true">→</span>' : '')
        + '<span class="effk-path__st' + (sid === id ? ' is-me' : '') + '">' + memberAvatar(sid, 'sm')
        + '<b>' + K.esc(m.label || '') + '</b><small>' + K.esc(memberSub(sid)) + '</small></span>';
    }).join('');
    if (steps.length === 1) {
      html += '<span class="effk-path__none">Aucun chef au-dessus : chef non désigné ou sommet de l’organigramme.</span>';
    }
    path.innerHTML = '<span class="sr-only">Chaîne de ' + K.esc((members[id] || {}).label || '') + ', du sommet au membre :</span>'
      + html + '<button type="button" data-chain-clear>Effacer</button>';
    path.hidden = false;
  }
  function findMember(value) {
    var v = norm(value).trim();
    if (!v) return 0;
    var ids = Object.keys(members);
    var exact = ids.filter(function (id) { return norm(members[id].label) === v; });
    if (exact.length) return parseInt(exact[0], 10);
    if (v.length < 2) return 0;
    var part = ids.filter(function (id) { return norm(members[id].label).indexOf(v) !== -1; });
    return part.length ? parseInt(part[0], 10) : 0;
  }
  if (q) {
    q.addEventListener('input', function () { follow(findMember(q.value)); });
  }

  // ——— Envoi d’un chef (même formulaire que le tableau) ———
  function submitChef(unitId, memberId) {
    if (!quick || !quickField) return;
    quickField.name = 'commanders[' + unitId + ']';
    quickField.value = String(memberId);
    quick.submit();
  }

  // ——— Sélecteur de chef ———
  function pickerItems(unitId, filter) {
    var u = units[unitId] || {};
    var inUnit = {};
    (u.members || []).forEach(function (id) { inUnit[id] = true; });
    var f = norm(filter).trim();
    var same = [];
    var other = [];
    Object.keys(members).forEach(function (key) {
      var id = parseInt(key, 10);
      var m = members[key];
      if (f && norm(m.label).indexOf(f) === -1) return;
      (inUnit[id] ? same : other).push(id);
    });
    var sort = function (a, b) { return String(members[a].label).localeCompare(String(members[b].label)); };
    same.sort(sort);
    other.sort(sort);
    var item = function (id) {
      var cur = id === u.chef;
      return '<button type="button" class="effk-pi" data-pick="' + id + '"' + (cur ? ' aria-current="true"' : '') + '>'
        + memberAvatar(id, 'sm') + '<span>' + K.esc(members[id].label) + '</span><small>' + K.esc(cur ? 'Chef actuel' : memberSub(id)) + '</small></button>';
    };
    var html = '';
    if (same.length) html += '<p class="effk-pop__g">Membres de l’unité</p>' + same.map(item).join('');
    if (other.length) html += '<p class="effk-pop__g">Autres membres</p>' + other.slice(0, 200).map(item).join('');
    if (!same.length && !other.length) html += '<p class="effk-pop__none">Aucun membre ne correspond.</p>';
    if (u.chef) html += '<div class="effk-pop__sep"><button type="button" class="effk-pi is-red" data-pick="0">Libérer le poste : aucun chef pour le moment</button></div>';
    return html;
  }
  function openPicker(anchor, unitId) {
    if (!pop || !canManage || !units[unitId]) return;
    var title = pop.querySelector('[data-chain-pop-title]');
    var list = pop.querySelector('[data-chain-pop-list]');
    var filter = pop.querySelector('[data-chain-pop-q]');
    title.textContent = 'Chef de ' + units[unitId].name;
    filter.value = '';
    list.innerHTML = pickerItems(unitId, '');
    filter.oninput = function () { list.innerHTML = pickerItems(unitId, filter.value); };
    list.onclick = function (e) {
      var b = e.target.closest('[data-pick]');
      if (!b) return;
      var id = parseInt(b.getAttribute('data-pick'), 10);
      if (id === units[unitId].chef) {
        K.closePop();
        return;
      }
      b.disabled = true;
      submitChef(unitId, id);
    };
    K.pop(pop, anchor);
  }

  // ——— Menu d’un membre ———
  function openMenu(anchor, memberId) {
    var m = members[memberId];
    if (!menu || !m) return;
    menu.querySelector('[data-chain-menu-title]').textContent = m.label;
    var list = menu.querySelector('[data-chain-menu-list]');
    var html = '<button type="button" class="effk-pi" data-act="follow">Remonter sa chaîne</button>'
      + '<a class="effk-pi" href="' + K.esc(m.url) + '">Ouvrir sa fiche</a>';
    if (canManage && m.unit && units[m.unit] && units[m.unit].chef !== memberId) {
      html += '<button type="button" class="effk-pi" data-act="chef">Nommer chef de ' + K.esc(units[m.unit].name) + '</button>';
    }
    html += '<a class="effk-pi" href="' + K.esc(String(data.affectations || '') + '?q=' + encodeURIComponent(m.label)) + '">Muter vers une autre unité…</a>';
    list.innerHTML = html;
    list.onclick = function (e) {
      var b = e.target.closest('[data-act]');
      if (!b) return;
      if (b.getAttribute('data-act') === 'follow') {
        K.closePop(false);
        if (q) q.value = m.label;
        follow(memberId);
        if (path) path.scrollIntoView({ block: 'nearest' });
      } else if (b.getAttribute('data-act') === 'chef') {
        b.disabled = true;
        submitChef(m.unit, memberId);
      }
    };
    K.pop(menu, anchor);
  }

  root.addEventListener('click', function (e) {
    if (e.target.closest('[data-chain-clear]')) {
      if (q) q.value = '';
      follow(0);
      if (q) q.focus();
      return;
    }
    var slot = e.target.closest('[data-chain-slot]');
    if (slot && canManage) {
      openPicker(slot, parseInt(slot.getAttribute('data-chain-slot'), 10));
      return;
    }
    var chip = e.target.closest('[data-chain-chip]');
    if (chip) {
      e.preventDefault();
      openMenu(chip, parseInt(chip.getAttribute('data-chain-chip'), 10));
    }
  });
  root.querySelectorAll('[data-chain-chip]').forEach(function (chip) {
    chip.setAttribute('aria-haspopup', 'dialog');
  });
})();
