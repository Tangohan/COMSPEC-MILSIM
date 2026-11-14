/**
 * Fichiers partagés (/partage, /back-office/partage) : recherche et filtre par type instantanés, tri, bascule album / liste
 * des images, dépôt en un geste, choix du destinataire d'un partage. La page reste entièrement utilisable sans
 * JavaScript (formulaires classiques) ; ce script n'ouvre jamais de fenêtre.
 */
(function () {
  'use strict';

  var VIEW_KEY = 'ath.partage.vue';

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function plural(n, one, many) {
    return n + ' ' + (n > 1 ? many : one);
  }

  // Les rubriques de l'extranet débordent sur écran étroit : montrer celle de la page.
  var showCurrentTab = function () {
    var current = document.querySelector('.ja-hd .ja-tab[aria-current]');
    if (!current || !current.parentElement) return;
    var rail = current.parentElement;
    var over = current.getBoundingClientRect().right - rail.getBoundingClientRect().right;
    if (over > 0) rail.scrollLeft += over + 24;
  };
  showCurrentTab();
  window.addEventListener('load', showCurrentTab);

  // Recherche, type, tri, affichage.
  var bar = document.querySelector('[data-fp-bar]');
  if (bar) {
    bar.hidden = false;
    var input = bar.querySelector('[data-fp-filter]');
    var count = bar.querySelector('[data-fp-count]');
    var none = document.querySelector('[data-fp-none]');
    var lists = Array.prototype.slice.call(document.querySelectorAll('[data-fp-list]'));
    var state = { q: '', kind: '' };

    var apply = function () {
      var q = norm(state.q).trim();
      var shown = 0;
      var total = 0;
      lists.forEach(function (list) {
        var visible = 0;
        list.querySelectorAll('[data-fp-item]').forEach(function (el) {
          total++;
          var ok = (!q || norm(el.getAttribute('data-name')).indexOf(q) !== -1) &&
            (!state.kind || el.getAttribute('data-kind') === state.kind);
          el.hidden = !ok;
          if (ok) visible++;
        });
        shown += visible;
        // Un bloc sans résultat disparaît avec son titre.
        var head = list.previousElementSibling;
        var hide = visible === 0 && (q || state.kind);
        list.hidden = !!hide;
        if (head && head.classList.contains('fp-sub')) head.hidden = !!hide;
      });
      if (count) count.textContent = (q || state.kind) ? shown + ' sur ' + plural(total, 'élément', 'éléments') : plural(total, 'élément', 'éléments');
      if (none) none.hidden = shown !== 0 || (!q && !state.kind);
    };

    if (input) {
      input.addEventListener('input', function () { state.q = input.value; apply(); });
      // « / » place le curseur dans la recherche.
      document.addEventListener('keydown', function (e) {
        var t = e.target;
        var typing = t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable);
        if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) {
          e.preventDefault();
          input.focus();
        } else if (e.key === 'Escape' && t === input && input.value) {
          input.value = '';
          state.q = '';
          apply();
        }
      });
    }

    bar.querySelectorAll('[data-fp-kind]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.kind = btn.getAttribute('data-fp-kind') || '';
        bar.querySelectorAll('[data-fp-kind]').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
        apply();
      });
    });

    var sort = bar.querySelector('[data-fp-sort]');
    if (sort) {
      sort.addEventListener('change', function () {
        var by = sort.value;
        document.querySelectorAll('.fp-files, .fp-album').forEach(function (list) {
          var items = Array.prototype.slice.call(list.querySelectorAll(':scope > [data-fp-item]'));
          items.sort(function (a, b) {
            if (by === 'date') return (+b.getAttribute('data-ts')) - (+a.getAttribute('data-ts'));
            if (by === 'size') return (+b.getAttribute('data-size')) - (+a.getAttribute('data-size'));
            return a.getAttribute('data-title').localeCompare(b.getAttribute('data-title'), 'fr', { sensitivity: 'base', numeric: true });
          });
          items.forEach(function (el) { list.appendChild(el); });
        });
      });
    }

    var album = document.querySelector('[data-fp-album]');
    var setView = function (v) {
      if (!album) return;
      album.classList.toggle('is-list', v === 'list');
      bar.querySelectorAll('[data-fp-view]').forEach(function (b) {
        var on = b.getAttribute('data-fp-view') === v;
        b.classList.toggle('is-on', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    };
    bar.querySelectorAll('[data-fp-view]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var v = btn.getAttribute('data-fp-view');
        setView(v);
        try { localStorage.setItem(VIEW_KEY, v); } catch (e) { /* mémoire du choix facultative */ }
      });
    });
    try {
      var saved = localStorage.getItem(VIEW_KEY);
      if (saved === 'list' || saved === 'album') setView(saved);
    } catch (e) { /* ignoré */ }

    apply();
  }

  // Dépôt : choisir ou glisser des fichiers envoie le formulaire tout de suite.
  document.querySelectorAll('[data-fp-drop]').forEach(function (form) {
    var field = form.querySelector('[data-fp-drop-input]');
    var text = form.querySelector('.fp-drop__text b');
    if (!field) return;
    form.classList.add('is-js');
    var send = function () {
      if (!field.files || !field.files.length) return;
      form.classList.add('is-busy');
      if (text) text.textContent = field.files.length === 1 ? 'Envoi de « ' + field.files[0].name + ' »…' : 'Envoi de ' + field.files.length + ' fichiers…';
      form.submit();
    };
    field.addEventListener('change', send);
    ['dragenter', 'dragover'].forEach(function (t) {
      form.addEventListener(t, function (e) { e.preventDefault(); form.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (t) {
      form.addEventListener(t, function () { form.classList.remove('is-over'); });
    });
    form.addEventListener('drop', function (e) {
      if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
      e.preventDefault();
      try {
        field.files = e.dataTransfer.files;
        send();
      } catch (err) { /* navigateur sans affectation de fichiers : on garde le choix manuel */ }
    });
  });

  // Un partage ou un retrait renvoie sur #fichier-N : ouvrir sa gestion.
  var openFromHash = function () {
    var m = /^#fichier-(\d+)$/.exec(window.location.hash);
    if (!m) return;
    var box = document.getElementById('fp-m-' + m[1]);
    if (box && box.type === 'checkbox') box.checked = true;
  };
  openFromHash();
  window.addEventListener('hashchange', openFromHash);

  // Formulaire de partage : un champ par type de destinataire, recherche d'un membre par son nom.
  document.querySelectorAll('[data-fp-share-form]').forEach(function (form) {
    var sync = function () {
      var checked = form.querySelector('[data-fp-target]:checked');
      var type = checked ? checked.value : 'user';
      form.querySelectorAll('[data-fp-pick]').forEach(function (p) {
        p.hidden = p.getAttribute('data-fp-pick') !== type;
      });
    };
    form.querySelectorAll('[data-fp-target]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();

    var search = form.querySelector('[data-fp-member-search]');
    var select = form.querySelector('[data-fp-member-select]');
    if (search && select) {
      search.hidden = false;
      search.addEventListener('input', function () {
        var v = search.value.trim().toLowerCase();
        var match = Array.prototype.find.call(select.options, function (o) { return o.value && o.text.toLowerCase() === v; });
        if (match) select.value = match.value;
      });
      select.addEventListener('change', function () {
        var o = select.options[select.selectedIndex];
        search.value = o && o.value ? o.text : '';
      });
    }
    form.addEventListener('submit', function (e) {
      var checked = form.querySelector('[data-fp-target]:checked');
      if (checked && checked.value === 'user' && select && !select.value) {
        e.preventDefault();
        (search || select).focus();
      }
    });
  });
})();
