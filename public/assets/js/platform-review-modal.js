(function () {
  'use strict';

  var root = document.getElementById('platform-review-root');
  if (!root) return;

  var csrf = root.getAttribute('data-csrf') || '';
  var stateUrl = root.getAttribute('data-state-url') || '';
  var reviewUrl = root.getAttribute('data-review-url') || '';
  var snoozeUrl = root.getAttribute('data-snooze-url') || '';
  var translationUrl = root.getAttribute('data-translation-url') || '';
  var overlay = document.getElementById('prw-dialog');
  var launcher = document.getElementById('prw-launcher');
  var statusEl = root.querySelector('[data-prw-status]');
  var selectedScore = null;
  var promptOpen = false;

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.classList.toggle('is-error', !!isError);
  }

  function open(tab) {
    if (!overlay) return;
    overlay.hidden = false;
    root.hidden = false;
    if (launcher) launcher.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    if (tab) switchTab(tab);
    var focus = overlay.querySelector('.prw-tab.is-on') || overlay.querySelector('button');
    if (focus) focus.focus();
  }

  function close() {
    if (!overlay) return;
    overlay.hidden = true;
    if (launcher) launcher.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    if (promptOpen) {
      promptOpen = false;
      post(snoozeUrl, {});
    }
  }

  function switchTab(name) {
    root.querySelectorAll('[data-prw-tab]').forEach(function (btn) {
      var on = btn.getAttribute('data-prw-tab') === name;
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('[data-prw-pane]').forEach(function (pane) {
      pane.hidden = pane.getAttribute('data-prw-pane') !== name;
    });
  }

  function headers() {
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrf
    };
  }

  function readError(data, fallback) {
    if (data && data.error && data.error.message) return data.error.message;
    return fallback;
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers(),
      body: JSON.stringify(Object.assign({ _csrf_token: csrf }, body || {}))
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        return { ok: res.ok && data && data.success !== false, data: data, status: res.status };
      });
    });
  }

  root.querySelectorAll('[data-prw-score]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      selectedScore = parseInt(btn.getAttribute('data-prw-score') || '-1', 10);
      root.querySelectorAll('[data-prw-score]').forEach(function (other) {
        other.classList.toggle('is-on', other === btn);
      });
    });
  });

  root.querySelectorAll('[data-prw-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      switchTab(btn.getAttribute('data-prw-tab') || 'review');
    });
  });

  root.querySelectorAll('[data-prw-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      close();
    });
  });

  if (overlay) {
    overlay.addEventListener('click', function (ev) {
      if (ev.target === overlay) close();
    });
  }

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && overlay && !overlay.hidden) close();
  });

    if (launcher) {
    launcher.addEventListener('click', function () {
      promptOpen = false;
      open('review');
    });
  }

  var later = root.querySelector('[data-prw-later]');
  if (later) {
    later.addEventListener('click', function () {
      promptOpen = false;
      post(snoozeUrl, {}).finally(function () {
        close();
        if (launcher) root.hidden = false;
      });
    });
  }

  var saveReview = root.querySelector('[data-prw-save-review]');
  if (saveReview) {
    saveReview.addEventListener('click', function () {
      if (selectedScore === null || selectedScore < 0) {
        setStatus(saveReview.getAttribute('data-need-score') || 'Choisissez une note de 0 à 10.', true);
        return;
      }
      var usage = root.querySelector('[data-prw-usage]');
      var highlights = root.querySelector('[data-prw-highlights]');
      var improvements = root.querySelector('[data-prw-improvements]');
      saveReview.disabled = true;
      post(reviewUrl, {
        score: selectedScore,
        usage_kind: usage ? usage.value : '',
        highlights: highlights ? highlights.value : '',
        improvements: improvements ? improvements.value : ''
      }).then(function (res) {
        if (!res.ok) {
          setStatus(readError(res.data, 'L’avis n’a pas pu être envoyé.'), true);
          return;
        }
        setStatus('Merci. Votre avis a bien été transmis.', false);
        promptOpen = false;
        window.setTimeout(close, 900);
      }).catch(function () {
        setStatus('L’avis n’a pas pu être envoyé.', true);
      }).finally(function () {
        saveReview.disabled = false;
      });
    });
  }

  var saveTranslation = root.querySelector('[data-prw-save-translation]');
  if (saveTranslation) {
    saveTranslation.addEventListener('click', function () {
      var locale = root.querySelector('[data-prw-locale]');
      var area = root.querySelector('[data-prw-area]');
      var original = root.querySelector('[data-prw-original]');
      var proposed = root.querySelector('[data-prw-proposed]');
      var comment = root.querySelector('[data-prw-comment]');
      saveTranslation.disabled = true;
      post(translationUrl, {
        locale: locale ? locale.value : 'en',
        area: area ? area.value : 'other',
        original_text: original ? original.value : '',
        proposed_text: proposed ? proposed.value : '',
        comment: comment ? comment.value : ''
      }).then(function (res) {
        if (!res.ok) {
          setStatus(readError(res.data, 'La proposition n’a pas pu être envoyée.'), true);
          return;
        }
        setStatus('Merci. Votre proposition sera relue.', false);
        if (original) original.value = '';
        if (proposed) proposed.value = '';
        if (comment) comment.value = '';
      }).catch(function () {
        setStatus('La proposition n’a pas pu être envoyée.', true);
      }).finally(function () {
        saveTranslation.disabled = false;
      });
    });
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-platform-review-open]');
    if (!btn) return;
    ev.preventDefault();
    promptOpen = false;
    root.hidden = false;
    open(btn.getAttribute('data-platform-review-open') || 'review');
  });

  function shouldForce() {
    try {
      var params = new URLSearchParams(window.location.search || '');
      return params.get('avis') === '1' || params.get('traduction') === '1';
    } catch (e) {
      return false;
    }
  }

  function forceTab() {
    try {
      var params = new URLSearchParams(window.location.search || '');
      if (params.get('traduction') === '1') return 'translate';
    } catch (e) {}
    return 'review';
  }

  root.hidden = false;

  if (stateUrl) {
    fetch(stateUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.json().catch(function () { return {}; }); })
      .then(function (data) {
        if (shouldForce()) {
          open(forceTab());
          return;
        }
        if (data && data.prompt && !data.has_review) {
          window.setTimeout(function () {
            if (overlay && overlay.hidden) {
              promptOpen = true;
              open('review');
            }
          }, 12000);
        }
      })
      .catch(function () {});
  } else if (shouldForce()) {
    open(forceTab());
  }
})();
