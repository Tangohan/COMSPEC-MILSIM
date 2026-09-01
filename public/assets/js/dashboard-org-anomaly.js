/**
 * Formulaire « Signaler une anomalie » du tableau de bord.
 * Délégation d’événements : le rail clone le panneau, les écouteurs restent valides.
 */
(function () {
  'use strict';

  if (document.documentElement.getAttribute('data-org-anomaly-init') === '1') {
    return;
  }
  document.documentElement.setAttribute('data-org-anomaly-init', '1');

  function formRoot(el) {
    return el && el.closest ? el.closest('[data-org-anomaly-form]') : null;
  }

  function setStatus(form, message, ok) {
    var box = form.querySelector('[data-org-anomaly-status]');
    if (!box) {
      return;
    }
    box.hidden = !message;
    box.textContent = message || '';
    box.classList.toggle('is-ok', !!ok);
    box.classList.toggle('is-err', !!message && !ok);
  }

  document.addEventListener('submit', function (event) {
    var form = formRoot(event.target);
    if (!form) {
      return;
    }
    event.preventDefault();

    var subject = (form.querySelector('[name="help_subject"]') || {}).value || '';
    var details = (form.querySelector('[name="details"]') || {}).value || '';
    if (!subject.trim()) {
      setStatus(form, 'Choisissez la nature de l’anomalie.', false);
      return;
    }
    if (!details.trim() || details.trim().length < 10) {
      setStatus(form, 'Décrivez l’anomalie en quelques phrases.', false);
      return;
    }

    var endpoint = form.getAttribute('data-org-anomaly-endpoint') || '';
    var csrf = form.getAttribute('data-org-anomaly-csrf') || '';
    var submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
    }
    setStatus(form, 'Envoi en cours…', true);

    var payload = {
      csrf_token: csrf,
      target_type: 'org_anomaly',
      target_id: 0,
      help_subject: subject,
      reference_note: (form.querySelector('[name="reference_note"]') || {}).value || '',
      reason: 'other',
      details: details,
      page_url: window.location.href
    };

    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin'
    })
      .then(function (r) {
        return r.json().then(function (j) {
          return { ok: r.ok, j: j };
        });
      })
      .then(function (x) {
        if (x.ok && x.j && x.j.success) {
          form.reset();
          setStatus(form, 'Message transmis à la gestion de l’organisation. Un accusé de réception vous est envoyé.', true);
        } else {
          setStatus(form, (x.j && x.j.error) ? x.j.error : 'Envoi impossible pour le moment.', false);
        }
      })
      .catch(function () {
        setStatus(form, 'La transmission a échoué. Réessayez dans un instant.', false);
      })
      .then(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
        }
      });
  });
})();
