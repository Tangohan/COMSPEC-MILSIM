/**
 * JNET — personnalisation d'un espace d'unité (views/jnet/customize.php) : l'aperçu suit le thème, la couleur, la devise,
 * la mention d'accès et les images choisies, avant l'enregistrement. Sans ce script, le formulaire marche pareil.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-jn-custom]');
  if (!root) {
    return;
  }
  var themes = {};
  try {
    themes = JSON.parse(root.getAttribute('data-themes') || '{}');
  } catch (e) {
    themes = {};
  }
  var orbatAccent = root.getAttribute('data-orbat-accent') || '';
  var orbatMotto = root.getAttribute('data-orbat-motto') || '';
  var accentToggle = root.querySelector('[data-jn-accent-toggle]');
  var accentInput = root.querySelector('[data-jn-accent]');
  var taglineInput = root.querySelector('[data-jn-tagline]');
  var accessInput = root.querySelector('[data-jn-access]');
  var emblemInput = root.querySelector('[data-jn-emblem]');
  var bannerInput = root.querySelector('[data-jn-banner]');
  var frame = root.querySelector('[data-jn-preview]');
  var pvTagline = root.querySelector('[data-jn-pv-tagline]');
  var pvAccess = root.querySelector('[data-jn-pv-access]');
  var pvEmblem = root.querySelector('[data-jn-pv-emblem]');

  function currentTheme() {
    var checked = root.querySelector('input[name="theme"]:checked');
    return themes[checked ? checked.value : 'operations'] || null;
  }

  function apply() {
    var t = currentTheme();
    if (!t) {
      return;
    }
    var accent = accentToggle && accentToggle.checked && accentInput ? accentInput.value : (orbatAccent || t.accent);
    root.style.setProperty('--jn-unit', accent);
    root.style.setProperty('--jn-space-bg', t.bg);
    root.style.setProperty('--jn-space-panel', t.panel);
    root.style.setProperty('--jn-space-ink', t.ink);
    if (accentInput && !(accentToggle && accentToggle.checked)) {
      accentInput.value = accent;
    }
    if (pvTagline && taglineInput) {
      var tagline = taglineInput.value.trim() || orbatMotto;
      pvTagline.textContent = '« ' + tagline + ' »';
      pvTagline.hidden = tagline === '';
    }
    if (pvAccess && accessInput) {
      pvAccess.textContent = accessInput.value.trim() || accessInput.getAttribute('placeholder') || '';
    }
  }

  function previewFile(input, onUrl) {
    if (!input || !input.files || !input.files[0] || !window.URL) {
      return;
    }
    onUrl(window.URL.createObjectURL(input.files[0]));
  }

  root.addEventListener('change', function (event) {
    if (event.target === emblemInput) {
      previewFile(emblemInput, function (src) {
        pvEmblem.style.backgroundImage = 'url("' + src + '")';
        pvEmblem.classList.add('has-img');
      });
    } else if (event.target === bannerInput) {
      previewFile(bannerInput, function (src) {
        frame.style.setProperty('--pv-banner', 'url("' + src + '")');
        frame.classList.add('has-banner');
      });
    } else if (event.target === accentInput && accentToggle) {
      accentToggle.checked = true;
    }
    apply();
  });
  root.addEventListener('input', function (event) {
    if (event.target === accentInput && accentToggle) {
      accentToggle.checked = true;
    }
    apply();
  });
})();
