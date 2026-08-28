/**
 * Rédacteur de Fiches de Renseignement Simplifiées (FRS) intégré dans la vue ATAK.
 *
 * Gère trois états : liste des fiches récentes, rédaction d'une nouvelle fiche,
 * et confirmation d'envoi. Communique avec /api/sse/notes en JSON (session web).
 */
(function () {
  'use strict';

  var PANEL_ID = 'tab-frs';
  var BODY_MAX = 1000;
  var THEMES_MAX = 4;

  /* ---- Catalogue chargé depuis le serveur ---- */
  var catalog = null;

  /* ---- État de la vue ---- */
  var VIEW_LIST = 'list';
  var VIEW_COMPOSE = 'compose';
  var VIEW_SUCCESS = 'success';
  var currentView = VIEW_LIST;
  var submitting = false;

  /* ---- Éléments DOM ---- */
  var panel = null;

  /* ---- Utilitaires ---- */
  function apiBase() {
    return (window.ATAK_API_BASE || '').replace(/\/$/, '');
  }

  function qs(selector, root) {
    return (root || panel).querySelector(selector);
  }

  function show(el) { if (el) el.hidden = false; }
  function hide(el) { if (el) el.hidden = true; }

  /* ====================================================
   * RENDU DE LA LISTE DES FICHES RÉCENTES
   * ==================================================== */

  function loadRecentNotes() {
    var listEl = qs('#frs-notes-list');
    var emptyEl = qs('#frs-notes-empty');
    var loadingEl = qs('#frs-notes-loading');
    if (!listEl) return;

    show(loadingEl); hide(emptyEl);
    listEl.innerHTML = '';

    fetch(apiBase() + '/api/sse/notes?limit=20', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        hide(loadingEl);
        var notes = data.notes || [];
        if (notes.length === 0) {
          show(emptyEl);
          return;
        }
        notes.forEach(function (note) {
          listEl.appendChild(renderNoteCard(note));
        });
      })
      .catch(function () {
        hide(loadingEl);
        show(emptyEl);
        if (emptyEl) emptyEl.textContent = 'Impossible de charger les fiches. Vérifiez la connexion.';
      });
  }

  function renderNoteCard(note) {
    var card = document.createElement('div');
    card.className = 'frs-note-card';

    var urgencyClass = 'frs-urgency--routine';
    if (note.urgency === 'critique' || note.urgency === 'immediate') urgencyClass = 'frs-urgency--immediate';
    else if (note.urgency === 'urgent' || note.urgency === 'priorite') urgencyClass = 'frs-urgency--priorite';
    else if (note.urgency === 'normal') urgencyClass = 'frs-urgency--priorite';

    var statusLabel = {
      brouillon: 'Brouillon',
      transmise: 'Transmise',
      prise_en_compte: 'Prise en compte',
      exploitee: 'Exploitée',
      sans_suite: 'Classée sans suite',
    }[note.status] || note.status || 'Transmise';

    var themes = [];
    if (Array.isArray(note.themes)) {
      themes = note.themes.map(function (t) {
        var tone = 'neutral';
        var label = t;
        if (catalog && catalog.themes) {
          var found = catalog.themes.find(function (th) { return th.code === t; });
          if (found) {
            label = found.code;
            tone = found.tone || 'neutral';
          }
        }
        return { label: label, tone: tone };
      });
    }

    var date = note.observed_at ? note.observed_at.slice(0, 16).replace('T', ' ') : (note.created_at || '').slice(0, 16).replace('T', ' ');
    var title = (note.title || '').trim();
    var bodyPreview = title || (note.body || '');

    card.innerHTML =
      '<div class="frs-note-card__header">' +
        '<span class="frs-note-kind">' + escHtml(note.note_kind || 'FRM') + '</span>' +
        '<span class="frs-note-status">' + escHtml(statusLabel) + '</span>' +
        '<span class="frs-note-date">' + escHtml(date) + '</span>' +
      '</div>' +
      (note.place_label ? '<div class="frs-note-place">' + escHtml(note.place_label) + '</div>' : '') +
      '<div class="frs-note-body">' + escHtml(bodyPreview.slice(0, 120)) + (bodyPreview.length > 120 ? '…' : '') + '</div>' +
      (themes.length ? '<div class="frs-note-themes">' + themes.map(function (t) {
        return '<span class="frs-theme-badge frs-tone-' + escHtml(t.tone) + '">' + escHtml(t.label) + '</span>';
      }).join('') + '</div>' : '') +
      '<div class="frs-note-card__footer ' + urgencyClass + '">' +
        urgencyLabel(note.urgency) +
        (note.reference_code ? ' · ' + escHtml(note.reference_code) : '') +
      '</div>';

    return card;
  }

  function urgencyLabel(code) {
    if (catalog && catalog.urgencies) {
      var found = catalog.urgencies.find(function (u) { return u.code === code; });
      if (found) return found.label;
    }
    if (code === 'critique' || code === 'immediate') return 'Critique';
    if (code === 'urgent' || code === 'priorite') return 'Urgent';
    if (code === 'normal') return 'Normal';
    return 'Routine';
  }

  function escHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ====================================================
   * VUE COMPOSITION
   * ==================================================== */

  function buildComposeView() {
    var composeEl = qs('#frs-compose');
    if (!composeEl || composeEl.hasAttribute('data-frs-built')) return;
    composeEl.setAttribute('data-frs-built', '1');

    if (!catalog) return;

    /* --- Sélection du type --- */
    var kindGrid = qs('#frs-kind-grid');
    if (kindGrid) {
      kindGrid.innerHTML = '';
      catalog.kinds.forEach(function (k, i) {
        var lbl = document.createElement('label');
        lbl.className = 'frs-choice';
        lbl.innerHTML =
          '<input type="radio" name="frs_note_kind" value="' + escHtml(k.code) + '"' + (i === 0 ? ' checked' : '') + '>' +
          '<span class="frs-choice-body"><strong>' + escHtml(k.code) + '</strong> <em>' + escHtml(k.label) + '</em></span>';
        kindGrid.appendChild(lbl);
      });
    }

    /* --- Grille de thèmes --- */
    var themeGrid = qs('#frs-theme-grid');
    if (themeGrid) {
      themeGrid.innerHTML = '';
      catalog.themes.forEach(function (t) {
        var lbl = document.createElement('label');
        lbl.className = 'frs-theme frs-tone-' + escHtml(t.tone);
        lbl.title = t.hint || t.label;
        lbl.innerHTML =
          '<input type="checkbox" name="frs_themes" value="' + escHtml(t.code) + '">' +
          '<span><strong>' + escHtml(t.code) + '</strong> ' + escHtml(t.label) + '</span>';
        themeGrid.appendChild(lbl);
      });
    }

    /* --- Urgence --- */
    var urgencyGrid = qs('#frs-urgency-grid');
    if (urgencyGrid) {
      urgencyGrid.innerHTML = '';
      var defaultUrgency = catalog.default_urgency || 'routine';
      catalog.urgencies.forEach(function (u) {
        var lbl = document.createElement('label');
        lbl.className = 'frs-choice frs-choice--urgency';
        lbl.innerHTML =
          '<input type="radio" name="frs_urgency" value="' + escHtml(u.code) + '"' + (u.code === defaultUrgency ? ' checked' : '') + '>' +
          '<span class="frs-choice-body"><strong>' + escHtml(u.label) + '</strong></span>';
        urgencyGrid.appendChild(lbl);
      });
    }

    var sourceGrid = qs('#frs-source-grid');
    if (sourceGrid && catalog.sources) {
      sourceGrid.innerHTML = '';
      var none = document.createElement('label');
      none.className = 'frs-choice';
      none.innerHTML =
        '<input type="radio" name="frs_intel_source" value="" checked>' +
        '<span class="frs-choice-body"><strong>Non précisé</strong></span>';
      sourceGrid.appendChild(none);
      catalog.sources.forEach(function (s) {
        var lbl = document.createElement('label');
        lbl.className = 'frs-choice';
        lbl.title = s.hint || s.label;
        lbl.innerHTML =
          '<input type="radio" name="frs_intel_source" value="' + escHtml(s.code) + '">' +
          '<span class="frs-choice-body"><strong>' + escHtml(s.code) + '</strong> <em>' + escHtml(s.label) + '</em></span>';
        sourceGrid.appendChild(lbl);
      });
    }

    wireComposeHandlers();
  }

  function wireComposeHandlers() {
    /* Compteur de caractères */
    var bodyField = qs('#frs-body');
    var counter = qs('#frs-body-counter');
    if (bodyField && counter) {
      bodyField.addEventListener('input', function () {
        var len = bodyField.value.length;
        counter.textContent = len + '/' + BODY_MAX;
        counter.classList.toggle('frs-counter--full', len >= BODY_MAX);
      });
    }

    /* Verrouillage des thèmes au max */
    var themeGrid = qs('#frs-theme-grid');
    if (themeGrid) {
      themeGrid.addEventListener('change', function () {
        var checked = themeGrid.querySelectorAll('input:checked');
        var reached = checked.length >= THEMES_MAX;
        Array.prototype.forEach.call(themeGrid.querySelectorAll('input'), function (inp) {
          inp.disabled = reached && !inp.checked;
          if (inp.parentElement) inp.parentElement.classList.toggle('is-blocked', reached && !inp.checked);
        });
      });
    }

    /* Bouton valider */
    var submitBtn = qs('#frs-submit');
    if (submitBtn) {
      submitBtn.addEventListener('click', submitFrs);
    }
  }

  function selectedThemes() {
    var themeGrid = qs('#frs-theme-grid');
    if (!themeGrid) return [];
    return Array.prototype.map.call(
      themeGrid.querySelectorAll('input:checked'),
      function (inp) { return inp.value; }
    );
  }

  function submitFrs() {
    if (submitting) return;

    var bodyField = qs('#frs-body');
    var body = bodyField ? bodyField.value.trim() : '';
    var themes = selectedThemes();
    var feedbackEl = qs('#frs-feedback');

    if (body === '') {
      showFeedback(feedbackEl, 'Rédigez le renseignement avant de valider.', 'error');
      if (bodyField) bodyField.focus();
      return;
    }
    if (themes.length === 0) {
      showFeedback(feedbackEl, 'Choisissez au moins un thème.', 'error');
      return;
    }

    var kindInput = qs('input[name="frs_note_kind"]:checked');
    var urgencyInput = qs('input[name="frs_urgency"]:checked');
    var sourceInput = qs('input[name="frs_intel_source"]:checked');
    var titleField = qs('#frs-title');
    var placeField = qs('#frs-place');
    var gridField = qs('#frs-grid');
    var caseField = qs('#frs-case-code');
    var observedField = qs('#frs-observed');

    var payload = {
      _csrf_token: window.ATAK_CSRF_TOKEN || '',
      body: body,
      note_kind: kindInput ? kindInput.value : 'FRM',
      themes: themes,
      title: titleField ? titleField.value.trim() : '',
      urgency: urgencyInput ? urgencyInput.value : 'routine',
      intel_source: sourceInput ? sourceInput.value : '',
      place_label: placeField ? placeField.value.trim() : '',
      grid_reference: gridField ? gridField.value.trim() : '',
      case_code: caseField ? caseField.value.trim() : '',
      observed_at: observedField && observedField.value ? observedField.value.replace('T', ' ') : null,
      origin: 'atak',
      idempotency_key: 'atak-web-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8),
    };

    submitting = true;
    var submitBtn = qs('#frs-submit');
    if (submitBtn) submitBtn.disabled = true;
    showFeedback(feedbackEl, 'Transmission en cours…', 'info');

    fetch(apiBase() + '/api/sse/notes/web', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        submitting = false;
        if (submitBtn) submitBtn.disabled = false;
        if (data.ok || data.id) {
          showView(VIEW_SUCCESS, data);
        } else {
          showFeedback(feedbackEl, data.message || 'Erreur lors de la transmission.', 'error');
        }
      })
      .catch(function (err) {
        submitting = false;
        if (submitBtn) submitBtn.disabled = false;
        showFeedback(feedbackEl, 'Erreur réseau — vérifiez la connexion.', 'error');
      });
  }

  function showFeedback(el, message, type) {
    if (!el) return;
    el.textContent = message;
    el.className = 'frs-feedback frs-feedback--' + type;
    el.hidden = false;
  }

  /* ====================================================
   * NAVIGATION ENTRE VUES
   * ==================================================== */

  function showView(view, data) {
    currentView = view;
    var listView = qs('#frs-list-view');
    var composeView = qs('#frs-compose');
    var successView = qs('#frs-success');

    hide(listView); hide(composeView); hide(successView);

    if (view === VIEW_LIST) {
      show(listView);
      loadRecentNotes();
    } else if (view === VIEW_COMPOSE) {
      show(composeView);
      buildComposeView();
      resetComposeForm();
    } else if (view === VIEW_SUCCESS) {
      show(successView);
      if (successView) {
        var refEl = successView.querySelector('#frs-success-ref');
        if (refEl && data && data.reference_code) {
          refEl.textContent = data.reference_code;
        }
      }
    }
  }

  function resetComposeForm() {
    var bodyField = qs('#frs-body');
    if (bodyField) bodyField.value = '';

    var counter = qs('#frs-body-counter');
    if (counter) counter.textContent = '0/' + BODY_MAX;

    var placeField = qs('#frs-place');
    if (placeField) placeField.value = '';

    var gridField = qs('#frs-grid');
    if (gridField) gridField.value = '';

    var caseField = qs('#frs-case-code');
    if (caseField) caseField.value = '';

    var observedField = qs('#frs-observed');
    if (observedField) {
      var now = new Date();
      var pad = function (n) { return String(n).padStart(2, '0'); };
      observedField.value = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) +
        'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    }

    var titleField = qs('#frs-title');
    if (titleField) titleField.value = '';

    var kindInputs = panel ? panel.querySelectorAll('input[name="frs_note_kind"]') : [];
    if (kindInputs.length) kindInputs[0].checked = true;

    var defaultUrgency = (catalog && catalog.default_urgency) || 'routine';
    var urgencyInputs = panel ? panel.querySelectorAll('input[name="frs_urgency"]') : [];
    Array.prototype.forEach.call(urgencyInputs, function (inp) {
      inp.checked = inp.value === defaultUrgency;
    });

    var sourceInputs = panel ? panel.querySelectorAll('input[name="frs_intel_source"]') : [];
    Array.prototype.forEach.call(sourceInputs, function (inp) {
      inp.checked = inp.value === '';
    });

    var themeGrid = qs('#frs-theme-grid');
    if (themeGrid) {
      Array.prototype.forEach.call(themeGrid.querySelectorAll('input'), function (inp) {
        inp.checked = false;
        inp.disabled = false;
        if (inp.parentElement) inp.parentElement.classList.remove('is-blocked');
      });
    }

    var feedbackEl = qs('#frs-feedback');
    if (feedbackEl) feedbackEl.hidden = true;
  }

  /* ====================================================
   * INITIALISATION
   * ==================================================== */

  function init() {
    panel = document.getElementById(PANEL_ID);
    if (!panel) return;

    /* Boutons de navigation */
    var btnCompose = qs('#frs-btn-compose');
    if (btnCompose) {
      btnCompose.addEventListener('click', function () { showView(VIEW_COMPOSE); });
    }

    var btnBackFromCompose = qs('#frs-btn-back-list');
    if (btnBackFromCompose) {
      btnBackFromCompose.addEventListener('click', function () { showView(VIEW_LIST); });
    }

    var btnSuccessNew = qs('#frs-success-new');
    if (btnSuccessNew) {
      btnSuccessNew.addEventListener('click', function () { showView(VIEW_COMPOSE); });
    }

    var btnSuccessList = qs('#frs-success-list');
    if (btnSuccessList) {
      btnSuccessList.addEventListener('click', function () { showView(VIEW_LIST); });
    }

    /* Chargement du catalogue */
    fetch(apiBase() + '/api/sse/notes/catalogue', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        catalog = data;
        BODY_MAX = data.body_max_length || 1000;
        THEMES_MAX = data.themes_max || 4;
        showView(VIEW_LIST);
      })
      .catch(function () {
        /* Catalogue indisponible : on garde les valeurs par défaut et on tente quand même */
        showView(VIEW_LIST);
      });
  }

  /* Lancer quand le panneau devient actif */
  function onTabActivated() {
    if (!panel) {
      init();
      return;
    }
    if (currentView === VIEW_LIST) {
      loadRecentNotes();
    }
  }

  /* Exposer pour que atak.php puisse appeler onTabActivated */
  window.ATAKFRS = { init: init, onTabActivated: onTabActivated };

  /* Démarrage automatique si le panneau est déjà visible au chargement */
  document.addEventListener('DOMContentLoaded', function () {
    panel = document.getElementById(PANEL_ID);
    if (panel && !panel.hidden) {
      init();
    }
  });
}());
