/* Athena — messagerie interne : recherche, filtre « non lues », saisie (Ctrl+Entrée), brouillons, défilement. */
(function () {
  'use strict';

  var root = document.querySelector('[data-msgx]');
  if (!root) return;

  function normalize(value) {
    value = String(value || '');
    return value.normalize ? value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() : value.toLowerCase();
  }

  function store(action, key, value) {
    try {
      if (action === 'get') return window.localStorage.getItem(key);
      if (action === 'set') window.localStorage.setItem(key, value);
      if (action === 'del') window.localStorage.removeItem(key);
    } catch (e) { /* stockage indisponible : on ignore */ }
    return null;
  }

  /* ---- Recherche + filtre ---- */
  var search = root.querySelector('[data-msg-search]');
  var filters = root.querySelectorAll('[data-msg-filter]');
  var items = root.querySelectorAll('[data-msg-thread]');
  var noResults = root.querySelector('[data-msg-no-results]');
  var mode = 'all';

  function applyFilter() {
    var query = search ? normalize(search.value.trim()) : '';
    var visible = 0;
    items.forEach(function (item) {
      var okText = query === '' || normalize(item.getAttribute('data-search')).indexOf(query) !== -1;
      var okMode = mode === 'all' || item.getAttribute('data-unread') === '1';
      item.hidden = !(okText && okMode);
      if (!item.hidden) visible += 1;
    });
    if (noResults) noResults.hidden = visible !== 0 || items.length === 0;
  }

  if (search) {
    search.addEventListener('input', applyFilter);
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { search.value = ''; applyFilter(); }
    });
  }
  filters.forEach(function (btn) {
    btn.addEventListener('click', function () {
      mode = btn.getAttribute('data-msg-filter') || 'all';
      filters.forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      applyFilter();
    });
  });

  /* ---- Bandeaux ---- */
  root.querySelectorAll('[data-msg-dismiss]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var flash = btn.closest('.msgx-flash');
      if (flash) flash.remove();
    });
  });
  var success = root.querySelector('.msgx-flash--success');
  if (success) window.setTimeout(function () { if (success.isConnected) success.remove(); }, 6000);

  /* ---- Brouillons : effacés seulement une fois l’envoi confirmé par le serveur ---- */
  if (success) {
    store('del', 'athena.msg.draft.new');
    root.querySelectorAll('[data-msg-draft]').forEach(function (f) {
      store('del', 'athena.msg.draft.' + f.getAttribute('data-msg-draft'));
    });
  }

  /* ---- Formulaires ---- */
  root.querySelectorAll('[data-msg-form]').forEach(function (form) {
    var body = form.querySelector('[data-msg-body]');
    var counter = form.querySelector('[data-msg-count]');
    var submit = form.querySelector('[data-msg-submit]');
    var subject = form.querySelector('[data-msg-subject]');
    var draftKey = form.getAttribute('data-msg-draft') ? 'athena.msg.draft.' + form.getAttribute('data-msg-draft') : null;
    var sending = false;
    if (!body) return;

    function autosize() {
      if (!body.hasAttribute('data-msg-autosize')) return;
      body.style.height = 'auto';
      body.style.height = Math.min(body.scrollHeight, 220) + 'px';
    }
    function update() {
      if (counter) counter.textContent = String(body.value.length);
      autosize();
    }

    if (draftKey && body.value === '') {
      var saved = store('get', draftKey);
      if (saved) {
        try {
          var d = JSON.parse(saved);
          if (d && typeof d.body === 'string') body.value = d.body;
          if (subject && d && typeof d.subject === 'string' && subject.value === '') subject.value = d.subject;
        } catch (e) { /* brouillon illisible */ }
      }
    }

    function saveDraft() {
      if (!draftKey) return;
      var payload = { body: body.value, subject: subject ? subject.value : '' };
      if (payload.body.trim() === '' && payload.subject.trim() === '') store('del', draftKey);
      else store('set', draftKey, JSON.stringify(payload));
    }

    body.addEventListener('input', function () { update(); saveDraft(); });
    if (subject) subject.addEventListener('input', saveDraft);

    body.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        if (body.value.trim() === '') return;
        if (form.requestSubmit) form.requestSubmit(); else form.submit();
      }
    });

    form.addEventListener('submit', function (e) {
      if (sending || body.value.trim() === '') {
        e.preventDefault();
        if (!sending) body.focus();
        return;
      }
      sending = true;
      if (submit) {
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
      }
    });

    update();
  });

  /* ---- Fil : aller au premier message non lu, sinon au dernier ---- */
  var stream = root.querySelector('[data-msg-stream]');
  if (stream) {
    var unread = stream.querySelector('[data-msg-unread]');
    var scrollsInside = stream.scrollHeight > stream.clientHeight + 1 && window.getComputedStyle(stream).overflowY !== 'visible';
    if (scrollsInside) {
      stream.scrollTop = unread
        ? Math.max(0, unread.getBoundingClientRect().top - stream.getBoundingClientRect().top + stream.scrollTop - 24)
        : stream.scrollHeight;
    } else if (unread) {
      unread.scrollIntoView({ block: 'center' });
    } else {
      window.scrollTo(0, document.documentElement.scrollHeight);
    }
  }

  /* ---- Nouveau message : focus direct sur l’objet ---- */
  var compose = root.querySelector('#msg-compose');
  if (compose && /[?&]nouveau=/.test(window.location.search)) {
    var first = compose.querySelector('[data-msg-subject]');
    if (first && window.matchMedia('(min-width: 761px)').matches) first.focus();
  }
}());
