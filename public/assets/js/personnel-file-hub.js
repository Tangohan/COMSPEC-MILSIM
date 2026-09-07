/**
 * Fiche personnel publique : mémorise la rubrique ouverte.
 */
(function () {
  var map = {
    resume: 'resume',
    portrait: 'resume',
    seniorite: 'ops',
    ops: 'ops',
    unite: 'ops',
    formation: 'formation',
    logistique: 'formation',
    parcours: 'formation',
    historique: 'historique',
    bilans: 'historique',
    suivi: 'historique',
    'suivi-complet': 'historique',
    'parcours-rh': 'historique',
    administratif: 'administratif',
    dossier: 'administratif',
    tableau: 'administratif'
  };
  var storageKey = 'personnel-file-tab';

  function normalize(raw) {
    var key = String(raw || '').toLowerCase();
    return map[key] || 'resume';
  }

  function fromPage() {
    try {
      var q = new URLSearchParams(window.location.search);
      var fromQuery = q.get('onglet') || q.get('tab');
      if (fromQuery) {
        return normalize(fromQuery);
      }
    } catch (e) {}
    var hash = (window.location.hash || '').replace(/^#/, '');
    if (hash && map[hash]) {
      return normalize(hash);
    }
    try {
      var stored = window.localStorage.getItem(storageKey);
      if (stored) {
        return normalize(stored);
      }
    } catch (e) {}
    return '';
  }

  function persist(tab) {
    tab = normalize(tab);
    try {
      window.localStorage.setItem(storageKey, tab);
    } catch (e) {}
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('onglet', tab);
      url.searchParams.delete('tab');
      history.replaceState({}, '', url.pathname + url.search + url.hash);
    } catch (e) {}
  }

  document.addEventListener('alpine:init', function () {
    window.Alpine.data('personnelFileTabs', function (initial) {
      var start = fromPage() || normalize(initial);
      return {
        tab: start,
        init: function () {
          persist(this.tab);
          this.$watch('tab', persist);
        },
        setTab: function (next) {
          this.tab = normalize(next);
        }
      };
    });
  });
})();
