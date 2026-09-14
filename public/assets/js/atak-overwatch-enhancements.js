/* Améliorations Overwatch Beta - Accordéons, Loader, SVG, Couleurs */

// Système d'accordéons pour Mission
function initAccordions() {
  document.addEventListener('click', function (event) {
    var header = event.target.closest('[data-ow-accordion]');
    if (!header) return;
    
    var targetId = header.getAttribute('data-ow-accordion');
    var body = document.querySelector('[data-ow-accordion-body="' + targetId + '"]');
    if (!body) return;
    
    var isOpen = body.classList.contains('is-open');
    body.classList.toggle('is-open', !isOpen);
    header.classList.toggle('is-open', !isOpen);
    
    // Rotation de la flèche SVG
    var svg = header.querySelector('svg');
    if (svg) {
      svg.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
    }
  });
}

// Loader global
function showLoader(message) {
  var loader = document.getElementById('ow-global-loader');
  if (!loader) {
    loader = document.createElement('div');
    loader.id = 'ow-global-loader';
    loader.className = 'ow-loader';
    loader.innerHTML = '<div class="ow-loader-content">' +
      '<svg class="ow-loader-spinner" width="48" height="48" viewBox="0 0 48 48">' +
      '<circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" stroke-width="4" stroke-dasharray="31.4 31.4" stroke-linecap="round">' +
      '<animateTransform attributeName="transform" type="rotate" from="0 24 24" to="360 24 24" dur="1s" repeatCount="indefinite"/>' +
      '</circle></svg>' +
      '<p id="ow-loader-message">Chargement...</p>' +
      '</div>';
    document.body.appendChild(loader);
  }
  
  var messageEl = document.getElementById('ow-loader-message');
  if (messageEl && message) messageEl.textContent = message;
  
  loader.hidden = false;
}

function hideLoader() {
  var loader = document.getElementById('ow-global-loader');
  if (loader) loader.hidden = true;
}

function updateLoaderMessage(message) {
  var messageEl = document.getElementById('ow-loader-message');
  if (messageEl) messageEl.textContent = message;
}

// Loader avec étapes
function loadAllData() {
  showLoader('Initialisation...');
  
  var steps = [
    { fn: 'loadChannels', msg: 'Chargement des canaux...' },
    { fn: 'loadChat', msg: 'Chargement des messages...' },
    { fn: 'loadShapes', msg: 'Chargement des tracés...' },
    { fn: 'loadPoMarkers', msg: 'Chargement des objectifs...' },
    { fn: 'loadRallyPoints', msg: 'Chargement des points de ralliement...' },
    { fn: 'loadGroupTasks', msg: 'Chargement des tâches...' },
    { fn: 'loadAlerts', msg: 'Chargement des alertes...' },
    { fn: 'loadWeather', msg: 'Chargement de la météo...' },
    { fn: 'refreshUnits', msg: 'Chargement des unités...' }
  ];
  
  var index = 0;
  
  function loadNext() {
    if (index >= steps.length) {
      updateLoaderMessage('✓ Tout chargé !');
      setTimeout(hideLoader, 500);
      return;
    }
    
    var step = steps[index];
    updateLoaderMessage(step.msg);
    index++;
    
    setTimeout(loadNext, 300);
  }
  
  loadNext();
}

// Icônes SVG
var SVG_ICONS = {
  chevronDown: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  upload: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2L10 14M10 2L6 6M10 2L14 6M4 18L16 18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  download: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2L10 14M10 14L6 10M10 14L14 10M4 18L16 18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  trash: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M4 6L16 6M8 4L12 4M7 6L7 16L13 16L13 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  eye: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M1 10C1 10 4 4 10 4C16 4 19 10 19 10C19 10 16 16 10 16C4 16 1 10 1 10Z" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="10" cy="10" r="3" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
  map: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M2 4L8 2L14 4L18 2L18 16L14 18L8 16L2 18L2 4Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  send: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M2 2L18 10L2 18L6 10L2 2Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  edit: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M14 2L18 6L8 16L2 18L4 12L14 2Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  copy: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><rect x="6" y="6" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"/><path d="M2 14L2 2L14 2" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
  check: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M4 10L8 14L16 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  x: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4L16 16M16 4L4 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  plus: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10 4L10 16M4 10L16 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  minus: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M4 10L16 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  search: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" fill="none"/><path d="M13 13L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  filter: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M2 4L8 10L8 16L12 18L12 10L18 4L2 4Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  settings: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="3" stroke="currentColor" stroke-width="2" fill="none"/><path d="M10 2L10 5M10 15L10 18M2 10L5 10M15 10L18 10M4 4L6 6M14 14L16 16M16 4L14 6M6 14L4 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  info: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none"/><path d="M10 10L10 14M10 6L10 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  alert: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2L18 16L2 16L10 2Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 8L10 12M10 14L10 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  target: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="10" cy="10" r="4" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="10" cy="10" r="1" fill="currentColor"/></svg>',
  location: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2C6 2 3 5 3 9C3 13 10 18 10 18C10 18 17 13 17 9C17 5 14 2 10 2Z" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="10" cy="9" r="2" fill="currentColor"/></svg>',
  user: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="6" r="4" stroke="currentColor" stroke-width="2" fill="none"/><path d="M3 18C3 14 6 12 10 12C14 12 17 14 17 18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>',
  users: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><circle cx="7" cy="6" r="3" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="14" cy="6" r="3" stroke="currentColor" stroke-width="2" fill="none"/><path d="M1 16C1 13 3 11 7 11C11 11 13 13 13 16M9 16C9 13 11 11 14 11C17 11 19 13 19 16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>',
  layers: '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6L10 2L18 6L10 10L2 6Z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 10L10 14L18 10M2 14L10 18L18 14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>'
};

// Export des fonctions
if (typeof window !== 'undefined') {
  window.OverwatchEnhancements = {
    initAccordions: initAccordions,
    showLoader: showLoader,
    hideLoader: hideLoader,
    updateLoaderMessage: updateLoaderMessage,
    loadAllData: loadAllData,
    SVG_ICONS: SVG_ICONS
  };
}
