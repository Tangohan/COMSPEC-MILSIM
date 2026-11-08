/**
 * Menu latéral du back-office : pastilles mises à jour sans rechargement, favoris en arrière-plan.
 *
 * Sans ce script tout marche : les étoiles et les boutons des favoris envoient le formulaire #ath-fav-form
 * (rechargement de la page), les pastilles sont celles du rendu serveur. Le serveur revérifie tout (droits,
 * communauté de la session, jeton CSRF).
 */
(function () {
  'use strict';

  var nav = document.querySelector('[data-ath-menu]');
  var form = document.getElementById('ath-fav-form');
  if (!nav || !form) return;

  function fmt(n) { return n < 1 ? '' : (n > 9 ? '9+' : String(n)); }
  function toast(kind, msg) {
    if (typeof window.athToast === 'function') { window.athToast(kind, msg); }
  }
  function csrf() {
    var i = form.querySelector('input[name="_csrf_token"]');
    return i ? i.value : '';
  }

  /* ——— Pastilles ——— */
  function applyBadges(map) {
    document.querySelectorAll('[data-ath-badge-keys]').forEach(function (el) {
      var keys = (el.getAttribute('data-ath-badge-keys') || '').split(' ');
      var n = 0;
      var important = false;
      var titles = [];
      keys.forEach(function (k) {
        var b = map[k];
        if (!b || !b.n) return;
        n += b.n;
        important = important || !!b.important;
        if (b.title) titles.push(b.title);
      });
      var out = el.querySelector('[data-ath-badge-n]');
      if (out) out.textContent = fmt(n);
      el.hidden = n < 1;
      el.classList.toggle('ath-mbadge--important', important);
      el.classList.toggle('ath-mbadge--notif', !important);
      var sr = el.querySelector('[data-ath-badge-sr]');
      if (sr) sr.textContent = important ? 'Important : ' : 'À traiter : ';
      if (n > 0) { el.setAttribute('title', titles.join(' · ')); } else { el.removeAttribute('title'); }
    });
  }

  var poll = parseInt(nav.getAttribute('data-ath-poll') || '0', 10);
  var badgesUrl = nav.getAttribute('data-ath-badges-url');
  if (poll > 0 && badgesUrl && typeof window.fetch === 'function'
      && document.querySelector('[data-ath-badge-keys]')) {
    var busy = false;
    var refresh = function () {
      if (busy || document.hidden) return;
      busy = true;
      fetch(badgesUrl, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { if (data && data.ok && data.badges) applyBadges(data.badges); })
        .catch(function () { /* hors ligne : on garde l'affichage */ })
        .then(function () { busy = false; });
    };
    window.setInterval(refresh, Math.max(30, poll) * 1000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
  }

  /* ——— Favoris ——— */
  var box = nav.querySelector('[data-ath-favs]');
  var list = box ? box.querySelector('[data-ath-favs-list]') : null;
  var empty = box ? box.querySelector('[data-ath-favs-empty]') : null;
  var counter = box ? box.querySelector('[data-ath-favs-count]') : null;
  var max = box ? parseInt(box.getAttribute('data-max') || '12', 10) : 12;

  function items() { return list ? Array.prototype.slice.call(list.querySelectorAll('[data-ath-fav-item]')) : []; }

  function refreshFavsUi() {
    var all = items();
    if (list) list.hidden = all.length === 0;
    if (empty) empty.hidden = all.length !== 0;
    if (counter) {
      counter.textContent = all.length + '/' + max;
      counter.setAttribute('aria-label', all.length + ' favoris sur ' + max);
    }
    all.forEach(function (li, i) {
      var up = li.querySelector('[data-ath-fav-move="up"]');
      var down = li.querySelector('[data-ath-fav-move="down"]');
      if (up) up.disabled = i === 0;
      if (down) down.disabled = i === all.length - 1;
    });
  }

  function setStars(path, on) {
    document.querySelectorAll('[data-ath-fav-path]').forEach(function (b) {
      if (b.getAttribute('data-ath-fav-path') !== path) return;
      var label = b.getAttribute('data-ath-fav-label') || '';
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      var head = b.classList.contains('ath-topbar__fav');
      var aria = head
        ? (on ? 'Retirer cette page des favoris' : 'Ajouter cette page aux favoris')
        : (on ? 'Retirer « ' + label + ' » des favoris' : 'Ajouter « ' + label + ' » aux favoris');
      b.setAttribute('aria-label', aria);
      b.setAttribute('title', head ? aria : (on ? 'Retirer des favoris' : 'Ajouter aux favoris'));
    });
  }

  function svg(d) {
    return '<svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true"><path d="' + d + '"></path></svg>';
  }

  function buildItem(path, label, href) {
    var li = document.createElement('li');
    li.className = 'ath-favs__it';
    li.setAttribute('data-ath-fav-item', '');
    li.setAttribute('data-path', path);
    li.draggable = true;
    var a = document.createElement('a');
    a.className = 'ath-sidebar__child ath-favs__link';
    a.href = href;
    a.innerHTML = '<span class="ath-favs__grip" aria-hidden="true">⋮⋮</span><span class="ath-sidebar__child-label"></span>';
    a.querySelector('.ath-sidebar__child-label').textContent = label;
    var tools = document.createElement('span');
    tools.className = 'ath-favs__tools';
    [['up', 'Monter', 'm6 15 6-6 6 6'], ['down', 'Descendre', 'm6 9 6 6 6-6'], ['remove', 'Retirer des favoris', 'M18 6 6 18M6 6l12 12']].forEach(function (t) {
      var b = document.createElement('button');
      b.type = 'submit';
      b.setAttribute('form', 'ath-fav-form');
      b.name = 'op';
      b.value = t[0] + '|' + path;
      b.className = 'ath-favs__btn' + (t[0] === 'remove' ? ' ath-favs__btn--rm' : '');
      if (t[0] === 'remove') { b.setAttribute('data-ath-fav-remove', ''); } else { b.setAttribute('data-ath-fav-move', t[0]); }
      b.title = t[1];
      b.setAttribute('aria-label', t[0] === 'remove' ? 'Retirer « ' + label + ' » des favoris' : t[1] + ' « ' + label + ' »');
      b.innerHTML = svg(t[2]);
      tools.appendChild(b);
    });
    li.appendChild(a);
    li.appendChild(tools);
    wireDrag(li);
    return li;
  }

  function hrefFor(path, star) {
    var row = star ? star.parentElement : null;
    var a = row ? row.querySelector('a[href]') : null;
    if (a) return a.href;
    // Étoile de l'en-tête : la page courante.
    return window.location.pathname + window.location.search;
  }

  function send(op, extra) {
    var fd = new FormData(form);
    fd.set('op', op);
    (extra || []).forEach(function (kv) { fd.append(kv[0], kv[1]); });
    return fetch(form.action, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() }
    }).then(function (r) {
      return r.json().then(function (data) { return { status: r.status, data: data }; }, function () { return { status: r.status, data: null }; });
    });
  }

  form.addEventListener('submit', function (e) {
    var btn = e.submitter;
    if (!btn || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') return;
    var op = btn.value || '';
    var bar = op.indexOf('|');
    if (bar < 0) return;
    e.preventDefault();
    var verb = op.slice(0, bar);
    var path = op.slice(bar + 1).split('|')[0];
    var li = list ? list.querySelector('[data-ath-fav-item][data-path="' + (window.CSS && CSS.escape ? CSS.escape(path) : path) + '"]') : null;
    var label = btn.getAttribute('data-ath-fav-label') || '';
    btn.disabled = true;
    send(op).then(function (res) {
      btn.disabled = false;
      var data = res.data;
      if (!data) { form.submit(); return; }
      if (!data.ok) { toast('error', data.message || 'Action impossible.'); return; }
      if (verb === 'toggle') {
        var on = (data.favorites || []).indexOf(path) !== -1;
        setStars(path, on);
        if (on && !li && list) {
          list.appendChild(buildItem(path, label || path, hrefFor(path, btn.classList.contains('ath-topbar__fav') ? null : btn)));
        } else if (!on && li) {
          li.remove();
        }
      } else if (verb === 'remove' && li) {
        li.remove();
        setStars(path, false);
      } else if ((verb === 'up' || verb === 'down') && li) {
        if (verb === 'up' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
        if (verb === 'down' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
        var again = li.querySelector('[data-ath-fav-move="' + verb + '"]');
        if (again && !again.disabled) again.focus();
      }
      refreshFavsUi();
      if (verb === 'toggle' || verb === 'remove') toast('success', data.message);
    }, function () {
      btn.disabled = false;
      form.submit();
    });
  });

  /* Glisser-déposer : l'ordre est envoyé au lâcher (les boutons monter / descendre font la même chose au clavier). */
  var dragged = null;
  function wireDrag(li) {
    li.addEventListener('dragstart', function (e) {
      dragged = li;
      li.classList.add('is-dragging');
      if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', li.getAttribute('data-path') || ''); }
    });
    li.addEventListener('dragover', function (e) {
      if (!dragged || dragged === li) return;
      e.preventDefault();
      var r = li.getBoundingClientRect();
      var after = e.clientY > r.top + r.height / 2;
      list.insertBefore(dragged, after ? li.nextElementSibling : li);
    });
    li.addEventListener('dragend', function () {
      li.classList.remove('is-dragging');
      if (!dragged) return;
      dragged = null;
      refreshFavsUi();
      var order = items().map(function (x) { return ['order[]', x.getAttribute('data-path') || '']; });
      send('order', order).then(function (res) {
        if (!res.data || !res.data.ok) toast('error', (res.data && res.data.message) || 'Ordre non enregistré.');
      }, function () { toast('error', 'Ordre non enregistré.'); });
    });
  }
  items().forEach(wireDrag);
  if (list) list.addEventListener('dragover', function (e) { if (dragged) e.preventDefault(); });
})();
