/**
 * Formulaire « Contacter l’administration du site » (organisateurs).
 */
(function () {
  'use strict';

  if (document.documentElement.getAttribute('data-site-support-init') === '1') {
    return;
  }
  document.documentElement.setAttribute('data-site-support-init', '1');

  function formRoot(el) {
    return el && el.closest ? el.closest('[data-site-support-form]') : null;
  }

  function setStatus(form, message, ok) {
    var box = form.querySelector('[data-site-support-status]');
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
      setStatus(form, 'Choisissez le type de demande.', false);
      return;
    }
    if (!details.trim() || details.trim().length < 10) {
      setStatus(form, 'Décrivez la demande en quelques phrases.', false);
      return;
    }

    var endpoint = form.getAttribute('data-site-support-endpoint') || '';
    var csrf = form.getAttribute('data-site-support-csrf') || '';
    var submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
    }
    setStatus(form, 'Envoi en cours…', true);

    var payload = {
      csrf_token: csrf,
      target_type: 'site_support_request',
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
          setStatus(form, 'Demande transmise à l’administration du site. Un accusé de réception vous est envoyé.', true);
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
