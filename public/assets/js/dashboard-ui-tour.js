/**
 * Guide visuel du tableau de bord — barre haute et sections.
 * Masquable à tout moment ; l’état est enregistré côté compte.
 */
(function () {
  'use strict';

  var cfg = window.__dashboardUiTour || {};
  if (!cfg || cfg.enabled === false) return;

  var steps = [
    {
      id: 'dash-tour-nav',
      title: 'Barre de navigation',
      body: 'En haut, vous changez d’espace : tableau de bord, forum, formations, effectifs, et votre espace communautaire. Le nom de la communauté reste visible à gauche.'
    },
    {
      id: 'dash-tour-nav-links',
      title: 'Les pages principales',
      body: 'Ces liens mènent aux pages que vous utilisez le plus. La page ouverte est marquée, les autres restent à un clic.'
    },
    {
      id: 'dash-tour-nav-actions',
      title: 'Raccourcis de la barre',
      body: 'À droite : créer une manœuvre (ou ouvrir la carte), les espaces Athena, les annonces, et votre profil. Vous pouvez y revenir depuis n’importe quelle page.'
    },
    {
      id: 'dash-rail',
      title: 'Panneau de gauche',
      body: 'Survolez ce panneau pour ouvrir les mêmes destinations, plus les tuiles de situation. Un clic sur une tuile affiche son contenu sans quitter la page.'
    },
    {
      id: 'dash-tour-identity',
      title: 'Votre identité du jour',
      body: 'Communauté, grade, matricule et raccourcis (fiche, messages, carte). C’est le point de départ de la journée.'
    },
    {
      id: 'dash-tour-hero',
      title: 'Le briefing',
      body: 'Ici s’affiche la consigne de votre unité et la prochaine manœuvre. C’est le cœur du tableau de bord.'
    },
    {
      id: 'dash-tour-join',
      title: 'Rejoindre une communauté',
      body: 'Vous n’êtes rattaché à aucune organisation pour l’instant. Parcourez le registre ou utilisez un code d’invitation.'
    },
    {
      id: 'dash-tour-formations',
      title: 'Formations',
      body: 'Les formations mises en avant par votre communauté. Ouvrez le catalogue pour vous inscrire ou reprendre un parcours.'
    },
    {
      id: 'dash-tour-kits',
      title: 'Tenues',
      body: 'Les tenues présentées par l’unité. Vous pouvez les ouvrir depuis l’équipement.'
    },
    {
      id: 'dash-tour-liaison',
      title: 'Liaison tactique',
      body: 'L’état des opérateurs actuellement connectés à la carte. Réservé à l’encadrement lorsqu’il est visible.'
    },
    {
      id: 'dash-tour-rsvp',
      title: 'Réponse rapide',
      body: 'Indiquez si vous serez présent à la prochaine manœuvre, sans ouvrir le calendrier complet.'
    },
    {
      id: 'dash-tour-activity',
      title: 'Votre activité',
      body: 'Les formations à traiter en priorité, puis le reste de votre activité dans l’unité.'
    },
    {
      id: 'dash-tour-applications',
      title: 'Candidatures',
      body: 'Le suivi des dossiers déposés, les vôtres ou ceux à instruire si vous y êtes habilité.'
    },
    {
      id: 'dash-tour-effectifs',
      title: 'Effectifs',
      body: 'Un aperçu des membres de la communauté. Ouvrez le registre pour le détail.'
    },
    {
      id: 'dashboard-community-pins',
      title: 'Épingles de la communauté',
      body: 'Messages et documents que l’encadrement a voulu garder sous les yeux de tout le monde.'
    },
    {
      id: 'connexion-steam',
      title: 'Connexion Steam',
      body: 'Associez votre compte Steam pour relier le jeu et le portail. À faire une fois.'
    },
    {
      id: 'dash-tour-modules',
      title: 'Accès rapides',
      body: 'Les portes d’entrée de ce profil : carte, liaisons, réglages. Chaque carte ouvre un espace précis.'
    },
    {
      id: 'dash-tour-tools',
      title: 'Outils',
      body: 'Les outils secondaires de la carte et de l’administration, regroupés pour un accès rapide.'
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
    if ($('#dash-tour-root')) return;
    var root = document.createElement('div');
    root.id = 'dash-tour-root';
    root.className = 'dash-tour';
    root.setAttribute('hidden', '');
    root.innerHTML =
      '<div class="dash-tour__backdrop" data-dash-tour-close></div>' +
      '<div class="dash-tour__spotlight" aria-hidden="true"></div>' +
      '<div class="dash-tour__card" role="dialog" aria-modal="true" aria-labelledby="dash-tour-title">' +
      '  <p class="dash-tour__eyebrow">Guide du tableau de bord</p>' +
      '  <h2 id="dash-tour-title" class="dash-tour__title"></h2>' +
      '  <p class="dash-tour__body"></p>' +
      '  <div class="dash-tour__progress"><span class="dash-tour__bar"></span></div>' +
      '  <div class="dash-tour__actions">' +
      '    <button type="button" class="dash-tour__btn dash-tour__btn--ghost" data-dash-tour-skip>Masquer le guide</button>' +
      '    <div class="dash-tour__actions-right">' +
      '      <button type="button" class="dash-tour__btn dash-tour__btn--ghost" data-dash-tour-prev>Retour</button>' +
      '      <button type="button" class="dash-tour__btn dash-tour__btn--primary" data-dash-tour-next>Suivant</button>' +
      '    </div>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(root);
  }

  var state = { index: 0, list: [], open: false, persistSent: false };

  function persist(completed) {
    if (state.persistSent) return;
    state.persistSent = true;
    var url = cfg.save_url;
    var csrf = cfg.csrf;
    if (!url || !csrf) return;
    var body = new URLSearchParams();
    body.set('_csrf_token', csrf);
    body.set('tour_key', cfg.key || 'dashboard.v1');
    body.set('completed', completed ? '1' : '0');
    try {
      fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        body: body
      }).catch(function () {});
    } catch (e) {}
  }

  function positionSpotlight(el) {
    var spot = $('.dash-tour__spotlight');
    var card = $('.dash-tour__card');
    if (!spot || !card || !el) return;
    var r = el.getBoundingClientRect();
    var pad = 8;
    spot.style.top = Math.max(6, r.top - pad) + 'px';
    spot.style.left = Math.max(6, r.left - pad) + 'px';
    spot.style.width = Math.min(window.innerWidth - 12, r.width + pad * 2) + 'px';
    spot.style.height = Math.min(window.innerHeight - 12, r.height + pad * 2) + 'px';

    var cardW = Math.min(360, window.innerWidth - 24);
    var preferBelow = r.bottom + 230 < window.innerHeight;
    var top = preferBelow ? r.bottom + 14 : Math.max(12, r.top - 210);
    if (top + 200 > window.innerHeight) {
      top = Math.max(12, window.innerHeight - 220);
    }
    var left = Math.min(window.innerWidth - cardW - 12, Math.max(12, r.left));
    card.style.width = cardW + 'px';
    card.style.top = top + 'px';
    card.style.left = left + 'px';
  }

  function clearTarget() {
    document.querySelectorAll('.dash-tour-target').forEach(function (n) {
      n.classList.remove('dash-tour-target');
    });
    var rail = document.getElementById('dash-rail');
    if (rail) rail.classList.remove('is-expanded');
  }

  function renderStep() {
    var step = state.list[state.index];
    if (!step) return;
    var el = document.getElementById(step.id);
    var title = $('.dash-tour__title');
    var body = $('.dash-tour__body');
    var bar = $('.dash-tour__bar');
    var nextBtn = $('[data-dash-tour-next]');
    var prevBtn = $('[data-dash-tour-prev]');
    if (title) title.textContent = step.title;
    if (body) body.textContent = step.body;
    if (bar) bar.style.width = (((state.index + 1) / state.list.length) * 100) + '%';
    if (nextBtn) nextBtn.textContent = state.index >= state.list.length - 1 ? 'Terminer' : 'Suivant';
    if (prevBtn) prevBtn.disabled = state.index === 0;

    clearTarget();
    if (el) {
      el.classList.add('dash-tour-target');
      if (step.id === 'dash-rail') {
        el.classList.add('is-expanded');
      }
      try {
        el.scrollIntoView({ behavior: 'smooth', block: step.id === 'dash-tour-nav' || step.id === 'dash-tour-nav-links' || step.id === 'dash-tour-nav-actions' ? 'start' : 'center' });
      } catch (e) {}
      window.setTimeout(function () { positionSpotlight(el); }, 280);
    }
  }

  function openTour(startIndex) {
    buildUi();
    state.list = visibleSteps();
    if (!state.list.length) return;
    state.index = Math.max(0, Math.min(startIndex || 0, state.list.length - 1));
    state.open = true;
    state.persistSent = false;
    var root = $('#dash-tour-root');
    if (root) root.removeAttribute('hidden');
    document.documentElement.classList.add('dash-tour-active');
    renderStep();
  }

  function closeTour(completed) {
    state.open = false;
    var root = $('#dash-tour-root');
    if (root) root.setAttribute('hidden', '');
    document.documentElement.classList.remove('dash-tour-active');
    clearTarget();
    persist(!!completed);
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
    if (!t || !t.closest) return;
    if (t.closest('#dash-tour-start') || t.closest('[data-dash-tour-start]')) {
      e.preventDefault();
      openTour(0);
      return;
    }
    if (!state.open) return;
    if (t.closest('[data-dash-tour-next]')) { next(); return; }
    if (t.closest('[data-dash-tour-prev]')) { prev(); return; }
    if (t.closest('[data-dash-tour-skip]') || t.closest('[data-dash-tour-close]')) {
      closeTour(false);
    }
  }

  document.addEventListener('click', onDocClick);
  window.addEventListener('scroll', function () {
    if (!state.open) return;
    var step = state.list[state.index];
    var el = step ? document.getElementById(step.id) : null;
    if (el) positionSpotlight(el);
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
    var params = new URLSearchParams(window.location.search || '');
    if (params.get('guide') === '1') {
      window.setTimeout(function () { openTour(0); }, 400);
      return;
    }
    if (!cfg.dismissed && visibleSteps().length >= 2) {
      var reduce = false;
      try {
        reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      } catch (e) {}
      window.setTimeout(function () { openTour(0); }, reduce ? 200 : 800);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
