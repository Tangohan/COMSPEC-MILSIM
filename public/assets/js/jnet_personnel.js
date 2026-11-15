/**
 * JNET — annuaire du personnel (views/jnet/personnel.php) : recherche instantanée, filtres (unité, grade, fonction,
 * statut), tri, regroupement par unité ou par grade, lignes ou cartes. Tout se joue sur les lignes déjà rendues par le
 * serveur : aucune donnée n'est ajoutée ni demandée. Le regroupement et l'affichage se retiennent dans ce navigateur.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-pe]');
  var list = root && root.querySelector('[data-pe-list]');
  if (!root || !list) {
    return;
  }
  root.classList.add('pe-js');

  var items = [].slice.call(list.querySelectorAll('[data-pe-item]'));
  var q = root.querySelector('[data-pe-q]');
  var selUnit = root.querySelector('[data-pe-unit]');
  var selGrade = root.querySelector('[data-pe-grade]');
  var selFn = root.querySelector('[data-pe-fn]');
  var selStatus = root.querySelector('[data-pe-status]');
  var selSort = root.querySelector('[data-pe-sort]');
  var counter = root.querySelector('[data-pe-n]');
  var none = root.querySelector('[data-pe-none]');
  var resets = [].slice.call(root.querySelectorAll('[data-pe-reset]'));
  var groupBtns = [].slice.call(root.querySelectorAll('[data-pe-group]'));
  var viewBtns = [].slice.call(root.querySelectorAll('[data-pe-view]'));
  var groupBy = '';
  var view = 'list';
  var KEY = 'jnet.personnel.';

  function store(name, value) {
    try {
      if (value === undefined) {
        return window.localStorage.getItem(KEY + name);
      }
      window.localStorage.setItem(KEY + name, value);
    } catch (e) {
      return null;
    }
    return null;
  }

  function norm(s) {
    s = String(s || '').toLowerCase();
    return s.normalize ? s.normalize('NFD').replace(/[̀-ͯ]/g, '') : s;
  }

  var collator = window.Intl && Intl.Collator ? new Intl.Collator('fr', { numeric: true, sensitivity: 'base' }) : null;
  function cmp(a, b) {
    return collator ? collator.compare(a, b) : (a < b ? -1 : a > b ? 1 : 0);
  }

  items.forEach(function (li) {
    li._q = norm(li.getAttribute('data-q'));
    li._name = li.getAttribute('data-name') || '';
    li._rank = parseInt(li.getAttribute('data-rank'), 10) || 999;
    li._unit = li.getAttribute('data-unit') || '';
    li._grade = li.getAttribute('data-grade') || '';
  });

  function setPressed(btns, attr, value) {
    btns.forEach(function (b) {
      b.setAttribute('aria-pressed', b.getAttribute(attr) === value ? 'true' : 'false');
    });
  }

  function matches(li) {
    var text = norm(q.value.trim());
    if (text) {
      var words = text.split(/\s+/);
      for (var i = 0; i < words.length; i++) {
        if (li._q.indexOf(words[i]) === -1) {
          return false;
        }
      }
    }
    if (selUnit.value && li._unit !== selUnit.value) {
      return false;
    }
    if (selGrade.value && li._grade !== selGrade.value) {
      return false;
    }
    if (selFn.value && li.getAttribute('data-fn') !== selFn.value) {
      return false;
    }
    if (selStatus.value && li.getAttribute('data-duty') !== selStatus.value) {
      return false;
    }
    return true;
  }

  function sortKey(a, b) {
    var by = selSort.value;
    if (by === 'grade') {
      return (a._rank - b._rank) || cmp(a._name, b._name);
    }
    if (by === 'unit') {
      return cmp(a._unit || '￿', b._unit || '￿') || (a._rank - b._rank) || cmp(a._name, b._name);
    }
    return cmp(a._name, b._name);
  }

  function groupLabel(li) {
    if (groupBy === 'unit') {
      return li._unit || 'Sans unité';
    }
    return li._grade || 'Sans grade';
  }

  function render() {
    var shown = items.filter(matches);
    var hidden = items.filter(function (li) { return shown.indexOf(li) === -1; });
    hidden.forEach(function (li) { li.hidden = true; });
    shown.forEach(function (li) { li.hidden = false; });

    shown.sort(function (a, b) {
      if (groupBy === 'unit') {
        var u = cmp(a._unit || '￿', b._unit || '￿');
        if (u) { return u; }
      } else if (groupBy === 'grade') {
        var g = (a._grade ? a._rank : 100000) - (b._grade ? b._rank : 100000);
        if (g) { return g; }
      }
      return sortKey(a, b);
    });

    [].slice.call(list.querySelectorAll('.pe-group')).forEach(function (g) { g.parentNode.removeChild(g); });
    var frag = document.createDocumentFragment();
    var last = null;
    var head = null;
    var count = 0;
    shown.forEach(function (li) {
      if (groupBy) {
        var label = groupLabel(li);
        if (label !== last) {
          if (head) { head.querySelector('.pe-group__n').textContent = String(count); }
          head = document.createElement('li');
          head.className = 'pe-group';
          var title = document.createElement('b');
          title.textContent = label;
          head.appendChild(title);
          if (groupBy === 'unit' && li.getAttribute('data-chain')) {
            var fil = document.createElement('small');
            fil.textContent = li.getAttribute('data-chain');
            head.appendChild(fil);
          }
          var n = document.createElement('span');
          n.className = 'pe-group__n';
          head.appendChild(n);
          frag.appendChild(head);
          last = label;
          count = 0;
        }
        count++;
      }
      frag.appendChild(li);
    });
    if (head) { head.querySelector('.pe-group__n').textContent = String(count); }
    hidden.forEach(function (li) { frag.appendChild(li); });
    list.appendChild(frag);

    counter.textContent = String(shown.length);
    var active = !!(q.value.trim() || selUnit.value || selGrade.value || selFn.value || selStatus.value);
    resets.forEach(function (b) {
      if (b.hasAttribute('data-pe-reset') && !b.closest('[data-pe-none]')) {
        b.hidden = !active;
      }
    });
    list.hidden = shown.length === 0;
    root.querySelector('.pe-cols').hidden = shown.length === 0;
    none.hidden = shown.length !== 0;
  }

  function setGroup(value) {
    groupBy = value;
    setPressed(groupBtns, 'data-pe-group', value);
    store('group', value);
    render();
  }

  function setView(value) {
    view = value === 'cards' ? 'cards' : 'list';
    root.classList.toggle('pe--cards', view === 'cards');
    setPressed(viewBtns, 'data-pe-view', view);
    store('view', view);
  }

  function reset() {
    q.value = '';
    selUnit.value = '';
    selGrade.value = '';
    selFn.value = '';
    selStatus.value = '';
    render();
  }

  q.addEventListener('input', render);
  [selUnit, selGrade, selFn, selStatus, selSort].forEach(function (s) { s.addEventListener('change', render); });
  groupBtns.forEach(function (b) { b.addEventListener('click', function () { setGroup(b.getAttribute('data-pe-group')); }); });
  viewBtns.forEach(function (b) { b.addEventListener('click', function () { setView(b.getAttribute('data-pe-view')); }); });
  resets.forEach(function (b) { b.addEventListener('click', reset); });

  // Début de saisie : la touche « / » met le focus sur la recherche.
  document.addEventListener('keydown', function (e) {
    var t = e.target;
    var typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);
    if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) {
      e.preventDefault();
      q.focus();
      q.select();
    }
  });

  // État de départ : le filtre de l'adresse d'abord, puis les derniers choix d'affichage de ce navigateur.
  var initStatus = root.getAttribute('data-init-status');
  if (initStatus) { selStatus.value = initStatus; }
  var savedGroup = store('group');
  var savedView = store('view');
  setView(savedView === 'cards' ? 'cards' : 'list');
  groupBy = savedGroup === 'unit' || savedGroup === 'grade' ? savedGroup : '';
  setPressed(groupBtns, 'data-pe-group', groupBy);
  render();
})();
