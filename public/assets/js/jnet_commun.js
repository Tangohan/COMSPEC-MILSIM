/**
 * JNET — Espace commun (centre de commandement à la Discord) : améliorations facultatives.
 * Sans JavaScript, chaque salon est une page servie par le serveur (?salon=), chaque filtre, tri et dépli une adresse, chaque
 * épingle un formulaire. Ce script ajoute :
 *   - le changement de salon sans rechargement (la page du salon est lue puis substituée ; repli : navigation normale) ;
 *   - flèches haut et bas dans la barre des salons, touche « / » pour chercher dans le salon ;
 *   - sections et panneau de droite repliables (mémorisés dans ce navigateur) ;
 *   - dépli instantané des lignes du tableau, filtre au fil de la frappe ;
 *   - épingler, désépingler et monter/descendre sans rechargement, glisser-déposer des épinglés, recherche d'une unité
 *     ou d'une page à épingler.
 * Le serveur revérifie chaque droit : rien ici ne décide d'un accès.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-jc]');
  if (!root) {
    return;
  }

  var store = {
    get: function (k) {
      try { return window.localStorage.getItem(k); } catch (e) { return null; }
    },
    set: function (k, v) {
      try { window.localStorage.setItem(k, v); } catch (e) { /* stockage indisponible : sans mémoire */ }
    }
  };

  var live = document.createElement('p');
  live.className = 'ja-sr';
  live.setAttribute('role', 'status');
  live.setAttribute('aria-live', 'polite');
  document.body.appendChild(live);

  function fold(text) {
    return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/œ/g, 'oe').replace(/’/g, "'").replace(/\s+/g, ' ').trim();
  }

  function say(message) {
    live.textContent = '';
    window.setTimeout(function () { live.textContent = message; }, 30);
  }

  // ——— Panneau de droite et sections repliables ———

  function applyMemory() {
    root.classList.toggle('is-aside-off', store.get('jnet.commun.aside') === 'off');
    var toggle = root.querySelector('[data-jc-aside-toggle]');
    if (toggle) {
      toggle.setAttribute('aria-pressed', root.classList.contains('is-aside-off') ? 'false' : 'true');
    }
    root.querySelectorAll('details[data-jc-sec]').forEach(function (d) {
      var saved = store.get('jnet.commun.sec.' + d.getAttribute('data-jc-sec'));
      if (saved === 'closed') { d.removeAttribute('open'); }
    });
    var finder = root.querySelector('[data-jc-finder]');
    if (finder) { finder.hidden = false; }
  }

  // ——— Changement de salon sans rechargement ———

  var loading = false;

  function load(url, push) {
    if (loading) { return; }
    loading = true;
    root.setAttribute('aria-busy', 'true');
    window.fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (res) {
        if (!res.ok || res.redirected) { throw new Error('http'); }
        return res.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var next = doc.querySelector('[data-jc]');
        if (!next) { throw new Error('page'); }
        root.innerHTML = next.innerHTML;
        root.setAttribute('data-salon', next.getAttribute('data-salon') || '');
        document.title = doc.title || document.title;
        if (push) { window.history.pushState({ jc: 1 }, '', url); }
        applyMemory();
        var hash = (url.split('#')[1] || '');
        var target = hash ? document.getElementById(hash) : null;
        var view = root.querySelector('[data-jc-view]');
        if (target) {
          target.scrollIntoView();
        } else if (view) {
          view.scrollIntoView({ block: 'start' });
          view.focus({ preventScroll: true });
        }
        var title = root.querySelector('#jc-title');
        if (title) { say(title.textContent || ''); }
      })
      .catch(function () {
        window.location.href = url;
      })
      .then(function () {
        loading = false;
        root.removeAttribute('aria-busy');
      });
  }

  function sameOrigin(a) {
    try {
      var u = new URL(a.href, window.location.href);
      return u.origin === window.location.origin && u.pathname === window.location.pathname;
    } catch (e) {
      return false;
    }
  }

  root.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-jc-salon]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
    if (!sameOrigin(a)) { return; }
    e.preventDefault();
    load(a.href, true);
  });

  window.addEventListener('popstate', function (e) {
    if (e.state && e.state.jc) { load(window.location.href, false); }
  });
  try { window.history.replaceState({ jc: 1 }, '', window.location.href); } catch (e) { /* historique indisponible */ }

  // ——— Tableau : dépli instantané ———

  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-jc-exp]');
    if (!btn) { return; }
    var detail = document.getElementById(btn.getAttribute('aria-controls') || '');
    if (!detail) { return; }
    e.preventDefault();
    var open = detail.hidden;
    detail.hidden = !open;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    var row = btn.closest('tr');
    if (row) { row.classList.toggle('is-open', open); }
  });

  // ——— Sections et panneau ———

  root.addEventListener('toggle', function (e) {
    var d = e.target;
    if (d && d.matches && d.matches('details[data-jc-sec]')) {
      store.set('jnet.commun.sec.' + d.getAttribute('data-jc-sec'), d.open ? 'open' : 'closed');
    }
  }, true);

  root.addEventListener('click', function (e) {
    var t = e.target.closest('[data-jc-aside-toggle]');
    if (!t) { return; }
    var off = root.classList.toggle('is-aside-off');
    t.setAttribute('aria-pressed', off ? 'false' : 'true');
    store.set('jnet.commun.aside', off ? 'off' : 'on');
  });

  // ——— Filtre au fil de la frappe ———

  root.addEventListener('input', function (e) {
    var input = e.target.closest('[data-jc-search]');
    if (!input) { return; }
    var selector = input.getAttribute('data-jc-filter') || '[data-jc-row]';
    var needle = fold(input.value);
    root.querySelectorAll(selector).forEach(function (el) {
      var hit = needle === '' || fold(el.getAttribute('data-jc-q')).indexOf(needle) !== -1;
      el.hidden = !hit;
      // Un tableau : la ligne de détail suit sa ligne.
      var next = el.nextElementSibling;
      if (next && next.classList.contains('jc-detail')) {
        next.hidden = !hit || el.classList.contains('is-open') === false;
      }
    });
  });

  // ——— Clavier ———

  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase();
    var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
    if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) {
      var field = root.querySelector('[data-jc-search], [data-jc-finder-q]');
      if (field) {
        e.preventDefault();
        field.focus();
        field.select();
      }
      return;
    }
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && e.target.closest && e.target.closest('[data-jc-rail]')) {
      var links = Array.prototype.slice.call(root.querySelectorAll('[data-jc-rail] a.jc-ch'));
      var i = links.indexOf(e.target.closest('a.jc-ch'));
      if (i === -1) { return; }
      var j = e.key === 'ArrowDown' ? Math.min(links.length - 1, i + 1) : Math.max(0, i - 1);
      e.preventDefault();
      links[j].focus();
    }
  });

  // ——— Épingles : écritures sans rechargement ———

  function post(form, done) {
    var data = new FormData(form);
    window.fetch(form.action, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
    })
      .then(function (res) {
        return res.json().then(function (j) { return { ok: res.ok && j.ok !== false, message: j.message || '' }; });
      })
      .then(done)
      .catch(function () { done({ ok: false, message: 'Erreur de connexion, rien n’a été enregistré.' }); });
  }

  function flash(message, ok) {
    var view = root.querySelector('[data-jc-view]');
    if (!view || !message) { return; }
    var old = view.querySelector('.jc-flash');
    if (old) { old.remove(); }
    var p = document.createElement('p');
    p.className = 'jnet-flash jc-flash ' + (ok ? 'jnet-flash--ok' : 'jnet-flash--err');
    p.setAttribute('role', ok ? 'status' : 'alert');
    p.textContent = message;
    var head = view.querySelector('.jc-head');
    if (head && head.nextSibling) { view.insertBefore(p, head.nextSibling); } else { view.appendChild(p); }
    say(message);
  }

  root.addEventListener('submit', function (e) {
    var form = e.target.closest('form[data-jc-pin]');
    if (!form || !window.fetch) { return; }
    e.preventDefault();
    var button = form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; }
    post(form, function (r) {
      if (r.ok) {
        load(window.location.href, false);
        window.setTimeout(function () { flash(r.message, true); }, 250);
      } else {
        if (button) { button.disabled = false; }
        flash(r.message, false);
      }
    });
  });

  // ——— Glisser-déposer des épinglés ———

  var dragged = null;

  root.addEventListener('dragstart', function (e) {
    var li = e.target.closest && e.target.closest('[data-jc-pin-id]');
    if (!li || !li.closest('[data-jc-sortable]')) { return; }
    dragged = li;
    li.classList.add('is-dragging');
    if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; }
    try { if (e.dataTransfer) { e.dataTransfer.setData('text/plain', li.getAttribute('data-jc-pin-id') || ''); } } catch (err) { /* navigateur sans données de glissement */ }
  });

  root.addEventListener('dragover', function (e) {
    var li = e.target.closest && e.target.closest('[data-jc-pin-id]');
    if (!dragged || !li || li === dragged || !li.closest('[data-jc-sortable]')) { return; }
    e.preventDefault();
    var rect = li.getBoundingClientRect();
    var after = e.clientY > rect.top + rect.height / 2;
    li.parentNode.insertBefore(dragged, after ? li.nextSibling : li);
  });

  root.addEventListener('dragend', function () {
    if (!dragged) { return; }
    var list = dragged.closest('[data-jc-sortable]');
    dragged.classList.remove('is-dragging');
    dragged = null;
    if (!list) { return; }
    var token = root.querySelector('input[name="_csrf_token"]');
    var data = new FormData();
    data.append('salon', 'epingles');
    if (token) { data.append('_csrf_token', token.value); }
    list.querySelectorAll('[data-jc-pin-id]').forEach(function (li) { data.append('ids[]', li.getAttribute('data-jc-pin-id')); });
    window.fetch(list.getAttribute('data-jc-order-url'), {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
    })
      .then(function (res) { return res.json().then(function (j) { return { ok: res.ok && j.ok !== false, message: j.message || '' }; }); })
      .catch(function () { return { ok: false, message: 'Erreur de connexion, rien n’a été enregistré.' }; })
      .then(function (r) {
        load(window.location.href, false);
        window.setTimeout(function () { flash(r.message, r.ok); }, 250);
      });
  });

  // ——— Recherche d'une unité ou d'une page à épingler ———

  var timer = null;

  root.addEventListener('input', function (e) {
    var input = e.target.closest('[data-jc-finder-q]');
    if (!input) { return; }
    var box = input.closest('[data-jc-finder]');
    var list = box.querySelector('[data-jc-finder-list]');
    window.clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { list.innerHTML = ''; return; }
    timer = window.setTimeout(function () {
      window.fetch(box.getAttribute('data-url') + '?types=unite,page&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.json(); })
        .then(function (j) {
          list.innerHTML = '';
          var csrf = box.querySelector('.jc-finder__csrf input');
          (j.results || []).forEach(function (r) {
            var parts = String(r.ref || '').split(':');
            if (parts.length !== 2) { return; }
            var li = document.createElement('li');
            var label = document.createElement('span');
            var strong = document.createElement('strong');
            strong.textContent = r.title;
            var em = document.createElement('em');
            em.textContent = (r.typeLabel || '') + (r.sub ? ' · ' + r.sub : '');
            label.appendChild(strong);
            label.appendChild(em);
            var form = document.createElement('form');
            form.method = 'post';
            form.action = box.getAttribute('data-action');
            form.setAttribute('data-jc-pin', '');
            [['salon', 'epingles'], ['type', parts[0]], ['id', parts[1]], ['_csrf_token', csrf ? csrf.value : '']].forEach(function (pair) {
              var hidden = document.createElement('input');
              hidden.type = 'hidden';
              hidden.name = pair[0];
              hidden.value = pair[1];
              form.appendChild(hidden);
            });
            var btn = document.createElement('button');
            btn.type = 'submit';
            btn.className = 'jc-tool';
            btn.textContent = 'Épingler';
            form.appendChild(btn);
            li.appendChild(label);
            li.appendChild(form);
            list.appendChild(li);
          });
        })
        .catch(function () { list.innerHTML = ''; });
    }, 250);
  });

  applyMemory();
})();
