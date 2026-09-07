/**
 * Hub paramètres communauté : rubriques, mémoire d’écran, barre Enregistrer.
 * Racine : [data-settings-hub]
 */
(function () {
  var root = document.querySelector('[data-settings-hub]');
  if (!root) {
    return;
  }

  var storageKey = 'bo-community-settings-tab';
  var tabs = ['identite', 'vitrine', 'inscription', 'accueil', 'portail', 'profil'];
  var hashMap = {
    identite: 'identite',
    affiliation: 'identite',
    'representation-unite': 'identite',
    vitrine: 'vitrine',
    'textes-publics': 'vitrine',
    visibilite: 'vitrine',
    timezone: 'vitrine',
    inscription: 'inscription',
    parcours: 'inscription',
    coordonnees: 'inscription',
    dossier: 'inscription',
    contact: 'inscription',
    accueil: 'accueil',
    'accueil-connexion': 'accueil',
    portail: 'portail',
    navigation: 'portail',
    profil: 'profil',
    'cycle-effectif': 'profil',
    'org-profil': 'profil'
  };
  var hints = {
    identite: 'Enregistre le nom, le logo, la représentation, la vitrine, le portail et le cycle.',
    vitrine: 'Enregistre le nom, le logo, la représentation, la vitrine, le portail et le cycle.',
    portail: 'Enregistre le nom, le logo, la représentation, la vitrine, le portail et le cycle.',
    profil: 'Enregistre le cycle administratif. Le type de communauté s’applique avec le bouton du cadre ci-dessus.',
    inscription: 'Enregistre le parcours d’arrivée, le contact des candidats et le dossier.',
    accueil: 'Chaque photo et le défilement s’enregistrent avec les boutons de cette rubrique.'
  };
  var submitFor = {
    identite: 'bo-community-settings-form',
    vitrine: 'bo-community-settings-form',
    portail: 'bo-community-settings-form',
    profil: 'bo-community-settings-form',
    inscription: 'bo-inscription-settings-form',
    accueil: ''
  };

  function readQueryTab() {
    try {
      var q = new URLSearchParams(window.location.search).get('onglet');
      if (q && tabs.indexOf(q) !== -1) {
        return q;
      }
    } catch (e) {}
    return '';
  }

  function readHashTab() {
    var h = (window.location.hash || '').replace(/^#/, '');
    if (h && hashMap[h]) {
      return hashMap[h];
    }
    return '';
  }

  function readStoredTab() {
    try {
      var stored = window.localStorage.getItem(storageKey);
      if (stored && tabs.indexOf(stored) !== -1) {
        return stored;
      }
    } catch (e) {}
    return '';
  }

  function queryTab() {
    return readQueryTab() || readHashTab() || readStoredTab() || 'identite';
  }

  function setHiddenTabs(tab) {
    root.querySelectorAll('input[name="settings_tab"]').forEach(function (input) {
      input.value = tab;
    });
  }

  function setTab(tab, push) {
    if (tabs.indexOf(tab) === -1) {
      tab = 'identite';
    }
    root.setAttribute('data-active-tab', tab);
    root.querySelectorAll('[data-settings-tab]').forEach(function (btn) {
      var on = btn.getAttribute('data-settings-tab') === tab;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
      btn.setAttribute('tabindex', on ? '0' : '-1');
    });
    root.querySelectorAll('[data-settings-panel]').forEach(function (panel) {
      var on = panel.getAttribute('data-settings-panel') === tab;
      if (on) {
        panel.removeAttribute('hidden');
      } else {
        panel.setAttribute('hidden', 'hidden');
      }
    });
    var hint = root.querySelector('[data-settings-save-hint]');
    if (hint) {
      hint.textContent = hints[tab] || '';
    }
    var submitBtn = root.querySelector('[data-settings-submit]');
    var formId = submitFor[tab] || '';
    if (submitBtn) {
      if (formId) {
        submitBtn.hidden = false;
        submitBtn.setAttribute('data-settings-submit', formId);
      } else {
        submitBtn.hidden = true;
        submitBtn.setAttribute('data-settings-submit', '');
      }
    }
    try {
      window.localStorage.setItem(storageKey, tab);
    } catch (e) {}
    setHiddenTabs(tab);
    if (push) {
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('onglet', tab);
        history.replaceState({}, '', url.pathname + url.search);
      } catch (e) {}
    }
  }

  function scrollToHash() {
    var h = (window.location.hash || '').replace(/^#/, '');
    if (!h) {
      return;
    }
    var target = document.getElementById(h);
    if (!target) {
      return;
    }
    window.requestAnimationFrame(function () {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  root.querySelectorAll('[data-settings-tab]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      setTab(btn.getAttribute('data-settings-tab') || 'identite', true);
    });
  });

  var submitBtn = root.querySelector('[data-settings-submit]');
  if (submitBtn) {
    submitBtn.addEventListener('click', function () {
      var formId = submitBtn.getAttribute('data-settings-submit');
      var form = formId ? document.getElementById(formId) : null;
      if (!form) {
        return;
      }
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });
  }

  setTab(queryTab(), false);
  scrollToHash();
})();
