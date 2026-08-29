(function () {
  'use strict';

  var root = document.getElementById('ux-feedback-root');
  if (!root) return;

  var csrf = root.getAttribute('data-csrf') || '';
  var stateUrl = root.getAttribute('data-state-url') || '';
  var ratingUrl = root.getAttribute('data-rating-url') || '';
  var surveyUrl = root.getAttribute('data-survey-url') || '';
  var launcher = document.getElementById('uxfb-launcher');
  var panel = document.getElementById('uxfb-panel');
  var statusEl = root.querySelector('[data-uxfb-status]');

  function pageMeta() {
    var path = window.location.pathname || '/';
    var titleEl = document.getElementById('ath-page-title')
      || document.querySelector('.ath-page-head__title')
      || document.querySelector('h1');
    var title = titleEl ? (titleEl.textContent || '').trim() : document.title;
    var key = path.replace(/^\/+|\/+$/g, '').toLowerCase();
    if (!key) key = 'back-office';
    return { page_key: key, page_path: path, page_title: title || key };
  }

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.classList.toggle('is-error', !!isError);
  }

  function selectedStars(group) {
    var active = group.querySelector('.uxfb-star.is-on');
    return active ? parseInt(active.getAttribute('data-value') || '0', 10) : 0;
  }

  function wireStars(container) {
    container.querySelectorAll('[data-uxfb-stars]').forEach(function (group) {
      group.querySelectorAll('.uxfb-star').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var val = parseInt(btn.getAttribute('data-value') || '0', 10);
          group.querySelectorAll('.uxfb-star').forEach(function (star) {
            var sVal = parseInt(star.getAttribute('data-value') || '0', 10);
            star.classList.toggle('is-on', sVal <= val);
          });
        });
      });
    });
  }

  function applyRating(rating) {
    if (!rating) return;
    var group = root.querySelector('[data-uxfb-stars="rating"]');
    if (!group) return;
    group.querySelectorAll('.uxfb-star').forEach(function (star) {
      var sVal = parseInt(star.getAttribute('data-value') || '0', 10);
      star.classList.toggle('is-on', sVal <= rating);
    });
  }

  function openPanel() {
    panel.hidden = false;
    launcher.setAttribute('aria-expanded', 'true');
  }

  function closePanel() {
    panel.hidden = true;
    launcher.setAttribute('aria-expanded', 'false');
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf,
      },
      body: JSON.stringify(Object.assign({ _csrf_token: csrf }, payload)),
    }).then(function (res) {
      return res.json().then(function (data) {
        return { ok: res.ok, data: data };
      });
    });
  }

  function loadState() {
    var meta = pageMeta();
    var q = stateUrl + (stateUrl.indexOf('?') >= 0 ? '&' : '?') + 'page_key=' + encodeURIComponent(meta.page_key);
    fetch(q, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        var data = payload && payload.data ? payload.data : payload;
        if (!data || data.ready === false) return;
        applyRating(data.rating || 0);
        if (data.survey_done) {
          setStatus('Questionnaire déjà enregistré pour cette page. Vous pouvez mettre à jour vos réponses.');
        }
      })
      .catch(function () { /* silencieux */ });
  }

  wireStars(root);

  launcher.addEventListener('click', function () {
    if (panel.hidden) openPanel(); else closePanel();
  });
  root.querySelectorAll('[data-uxfb-close]').forEach(function (btn) {
    btn.addEventListener('click', closePanel);
  });

  var saveRatingBtn = root.querySelector('[data-uxfb-save-rating]');
  if (saveRatingBtn) {
    saveRatingBtn.addEventListener('click', function () {
      var meta = pageMeta();
      var ratingGroup = root.querySelector('[data-uxfb-stars="rating"]');
      var rating = ratingGroup ? selectedStars(ratingGroup) : 0;
      if (rating < 1) {
        setStatus('Choisissez une note entre 1 et 5.', true);
        return;
      }
      var commentEl = root.querySelector('[data-uxfb-rating-comment]');
      var comment = commentEl ? commentEl.value.trim() : '';
      setStatus('Enregistrement…');
      postJson(ratingUrl, Object.assign({}, meta, { rating: rating, comment: comment }))
        .then(function (result) {
          if (!result.ok) {
            var msg = (result.data && result.data.message) || 'Enregistrement impossible.';
            setStatus(msg, true);
            return;
          }
          setStatus('Merci — note enregistrée.');
        })
        .catch(function () { setStatus('Erreur réseau.', true); });
    });
  }

  var saveSurveyBtn = root.querySelector('[data-uxfb-save-survey]');
  if (saveSurveyBtn) {
    saveSurveyBtn.addEventListener('click', function () {
      var meta = pageMeta();
      var payload = Object.assign({}, meta, {
        ease_rating: selectedStars(root.querySelector('[data-uxfb-stars="ease_rating"]')),
        clarity_rating: selectedStars(root.querySelector('[data-uxfb-stars="clarity_rating"]')),
        design_rating: selectedStars(root.querySelector('[data-uxfb-stars="design_rating"]')),
        usefulness_rating: selectedStars(root.querySelector('[data-uxfb-stars="usefulness_rating"]')),
        improvement_text: (root.querySelector('[data-uxfb-improvement]') || {}).value || '',
        issues: [],
      });
      if (payload.ease_rating < 1 || payload.clarity_rating < 1 || payload.design_rating < 1 || payload.usefulness_rating < 1) {
        setStatus('Complétez les quatre critères du questionnaire (1 à 5).', true);
        return;
      }
      root.querySelectorAll('.uxfb-issues input[type="checkbox"]:checked').forEach(function (cb) {
        payload.issues.push(cb.value);
      });
      var recommend = root.querySelector('input[name="would_recommend"]:checked');
      if (recommend) {
        payload.would_recommend = recommend.value === '1';
      }
      setStatus('Envoi du questionnaire…');
      postJson(surveyUrl, payload)
        .then(function (result) {
          if (!result.ok) {
            var msg = (result.data && result.data.message) || 'Envoi impossible.';
            setStatus(msg, true);
            return;
          }
          setStatus('Merci — questionnaire enregistré.');
        })
        .catch(function () { setStatus('Erreur réseau.', true); });
    });
  }

  root.hidden = false;
  loadState();
})();
