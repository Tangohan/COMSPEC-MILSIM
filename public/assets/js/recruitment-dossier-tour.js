/**
 * Guide animé — fiche dossier Bureau recrutement.
 * Surligne les sections, explique le rôle de chacune, mémorise « déjà vu ».
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'athena.recruitment.dossierTour.v1';
  var AUTO_PARAM = 'guide';

  var steps = [
    {
      id: 'recap-dossier',
      title: 'Récapitulatif',
      body: 'Ici vous voyez en un coup d’œil où en est le dossier : étape, statut, référent et nature (externe ou mobilité interne).'
    },
    {
      id: 'coordination-dossier',
      title: 'Coordination',
      body: 'Indiquez qui pilote l’instruction et qui se porte volontaire pour aider. Cela évite les dossiers sans propriétaire.'
    },
    {
      id: 'couverture-dossier',
      title: 'En-tête candidat',
      body: 'Identité, date de réception, accès rapide au fil de suivi et au portail vu par le candidat.'
    },
    {
      id: 'portail-candidat',
      title: 'Portail candidat',
      body: 'Réglez ce que le candidat peut envoyer (pièces, audio) et comment son avancement s’affiche de son côté.'
    },
    {
      id: 'identite-reception',
      title: 'Identité & réception',
      body: 'Coordonnées, avis de poste associé et éléments transmis au dépôt. C’est la base factuelle avant toute décision.'
    },
    {
      id: 'instruction-dossier',
      title: 'Décision',
      body: 'Accepter, refuser, mettre en attente, proposer un entretien… Le message joint part au candidat selon le choix.'
    },
    {
      id: 'journal-dossier',
      title: 'Journal',
      body: 'Toute la chronologie : échanges, pièces, notes internes. Ajoutez une note pour tracer sans écrire au candidat.'
    },
    {
      id: 'bilan-recrutement',
      title: 'Bilan après 30 jours',
      body: 'Quand le dossier a plus d’un mois, laissez une courte note pour améliorer le processus. Optionnel mais utile.'
    }
  ];

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function visibleSteps() {
    return steps.filter(function (s) {
      return !!document.getElementById(s.id);
    });
  }

  function buildUi() {
    if ($('#dossier-tour-root')) return;
    var root = document.createElement('div');
    root.id = 'dossier-tour-root';
    root.className = 'dossier-tour';
    root.setAttribute('hidden', '');
    root.innerHTML =
      '<div class="dossier-tour__backdrop" data-tour-close></div>' +
      '<div class="dossier-tour__spotlight" aria-hidden="true"></div>' +
      '<div class="dossier-tour__card" role="dialog" aria-modal="true" aria-labelledby="dossier-tour-title">' +
      '  <p class="dossier-tour__eyebrow">Guide du dossier</p>' +
      '  <h2 id="dossier-tour-title" class="dossier-tour__title"></h2>' +
      '  <p class="dossier-tour__body"></p>' +
      '  <div class="dossier-tour__progress"><span class="dossier-tour__bar"></span></div>' +
      '  <div class="dossier-tour__actions">' +
      '    <button type="button" class="dossier-tour__btn dossier-tour__btn--ghost" data-tour-skip>Passer</button>' +
      '    <div class="dossier-tour__actions-right">' +
      '      <button type="button" class="dossier-tour__btn dossier-tour__btn--ghost" data-tour-prev>Retour</button>' +
      '      <button type="button" class="dossier-tour__btn dossier-tour__btn--primary" data-tour-next>Suivant</button>' +
      '    </div>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(root);
  }

  var state = { index: 0, list: [], open: false };

  function markSeen() {
    try { localStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
  }

  function hasSeen() {
    try { return localStorage.getItem(STORAGE_KEY) === '1'; } catch (e) { return false; }
  }

  function positionSpotlight(el) {
    var spot = $('.dossier-tour__spotlight');
    var card = $('.dossier-tour__card');
    if (!spot || !card || !el) return;
    var r = el.getBoundingClientRect();
    var pad = 10;
    spot.style.top = Math.max(8, r.top - pad + window.scrollY) + 'px';
    spot.style.left = Math.max(8, r.left - pad + window.scrollX) + 'px';
    spot.style.width = Math.min(window.innerWidth - 16, r.width + pad * 2) + 'px';
    spot.style.height = r.height + pad * 2 + 'px';

    var preferBelow = r.bottom + 220 < window.innerHeight;
    var cardTop = preferBelow
      ? r.bottom + 16 + window.scrollY
      : Math.max(window.scrollY + 16, r.top + window.scrollY - 200);
    var cardLeft = Math.min(
      window.scrollX + window.innerWidth - 380,
      Math.max(window.scrollX + 16, r.left + window.scrollX)
    );
    card.style.top = cardTop + 'px';
    card.style.left = cardLeft + 'px';
  }

  function renderStep() {
    var step = state.list[state.index];
    if (!step) return;
    var el = document.getElementById(step.id);
    var title = $('.dossier-tour__title');
    var body = $('.dossier-tour__body');
    var bar = $('.dossier-tour__bar');
    var nextBtn = $('[data-tour-next]');
    var prevBtn = $('[data-tour-prev]');
    if (title) title.textContent = step.title;
    if (body) body.textContent = step.body;
    if (bar) bar.style.width = (((state.index + 1) / state.list.length) * 100) + '%';
    if (nextBtn) nextBtn.textContent = state.index >= state.list.length - 1 ? 'Terminer' : 'Suivant';
    if (prevBtn) prevBtn.disabled = state.index === 0;

    document.querySelectorAll('.dossier-tour-target').forEach(function (n) {
      n.classList.remove('dossier-tour-target');
    });
    if (el) {
      el.classList.add('dossier-tour-target');
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      window.setTimeout(function () { positionSpotlight(el); }, 280);
    }
  }

  function openTour(startIndex) {
    buildUi();
    state.list = visibleSteps();
    if (!state.list.length) return;
    state.index = Math.max(0, Math.min(startIndex || 0, state.list.length - 1));
    state.open = true;
    var root = $('#dossier-tour-root');
    if (root) root.removeAttribute('hidden');
    document.documentElement.classList.add('dossier-tour-active');
    renderStep();
  }

  function closeTour(completed) {
    state.open = false;
    var root = $('#dossier-tour-root');
    if (root) root.setAttribute('hidden', '');
    document.documentElement.classList.remove('dossier-tour-active');
    document.querySelectorAll('.dossier-tour-target').forEach(function (n) {
      n.classList.remove('dossier-tour-target');
    });
    if (completed) markSeen();
  }

  function next() {
    if (state.index >= state.list.length - 1) {
      closeTour(true);
      return;
    }
    state.index += 1;
    renderStep();
  }

  function prev() {
    if (state.index <= 0) return;
    state.index -= 1;
    renderStep();
  }

  function onDocClick(e) {
    var t = e.target;
    if (!t) return;
    if (t.closest && (t.closest('#dossier-tour-start') || t.closest('#dossier-tour-start-mobile'))) {
      e.preventDefault();
      openTour(0);
      return;
    }
    if (!state.open) return;
    if (t.closest && t.closest('[data-tour-next]')) { next(); return; }
    if (t.closest && t.closest('[data-tour-prev]')) { prev(); return; }
    if (t.closest && (t.closest('[data-tour-skip]') || t.closest('[data-tour-close]'))) {
      closeTour(false);
    }
  }

  function syncActiveNav() {
    var links = document.querySelectorAll('[data-dossier-nav]');
    if (!links.length) return;
    var current = null;
    var mid = window.scrollY + 120;
    links.forEach(function (a) {
      var id = a.getAttribute('data-dossier-nav');
      var el = id ? document.getElementById(id) : null;
      if (el && el.offsetTop <= mid) current = id;
    });
    links.forEach(function (a) {
      var on = a.getAttribute('data-dossier-nav') === current;
      a.classList.toggle('is-active', on);
    });
  }

  document.addEventListener('click', onDocClick);
  window.addEventListener('scroll', function () {
    if (state.open) {
      var step = state.list[state.index];
      var el = step ? document.getElementById(step.id) : null;
      if (el) positionSpotlight(el);
    }
    syncActiveNav();
  }, { passive: true });
  window.addEventListener('resize', function () {
    if (!state.open) return;
    var step = state.list[state.index];
    var el = step ? document.getElementById(step.id) : null;
    if (el) positionSpotlight(el);
  });

  document.addEventListener('keydown', function (e) {
    if (!state.open) return;
    if (e.key === 'Escape') closeTour(false);
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft') prev();
  });

  function boot() {
    syncActiveNav();
    var params = new URLSearchParams(window.location.search || '');
    if (params.get(AUTO_PARAM) === '1') {
      openTour(0);
      return;
    }
    if (!hasSeen() && visibleSteps().length >= 3) {
      window.setTimeout(function () { openTour(0); }, 700);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
