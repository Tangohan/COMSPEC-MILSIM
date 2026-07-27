/**
 * Lecteur de leçon « scène 16:9 ».
 *
 * Navigation clavier (flèches, Origine/Fin), pastilles, progression d'étapes.
 * Reprend le contrat de complétion des lecteurs existants :
 *   - window.__LMS_LESSON_PROGRESS__.strict.slideDwellMs : durée minimale par étape
 *   - window.LmsLessonProgress.signalComplete()          : la leçon est terminée
 *
 * Une étape ne compte comme vue qu'après le temps de lecture minimal : passer
 * rapidement d'une flèche à l'autre ne valide pas la leçon.
 */
(function () {
  'use strict';

  var DEFAULT_DWELL_MS = 2600;

  function dwellMs() {
    var cfg = window.__LMS_LESSON_PROGRESS__;
    if (cfg && cfg.strict && typeof cfg.strict.slideDwellMs === 'number' && cfg.strict.slideDwellMs > 0) {
      return cfg.strict.slideDwellMs;
    }
    return DEFAULT_DWELL_MS;
  }

  function init(root) {
    if (!root || root.dataset.stagePlayerReady === '1') {
      return;
    }
    root.dataset.stagePlayerReady = '1';

    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-stage-slide]'));
    var total = slides.length;
    if (total === 0) {
      return;
    }

    var prevBtn = root.querySelector('[data-stage-prev]');
    var nextBtn = root.querySelector('[data-stage-next]');
    var meterFill = root.querySelector('[data-stage-meter-fill]');
    var meterLabel = root.querySelector('[data-stage-meter-label]');
    var dots = Array.prototype.slice.call(root.querySelectorAll('[data-stage-dot]'));
    var liveRegion = root.querySelector('[data-stage-live]');

    var index = 0;
    var enteredAt = Object.create(null);
    var seen = Object.create(null);
    var dwellTimer = null;
    var completed = false;

    function markSeen(i) {
      if (seen[i]) {
        return;
      }
      var t0 = enteredAt[i];
      if (t0 == null || Date.now() - t0 < dwellMs()) {
        return;
      }
      seen[i] = true;
      if (dots[i]) {
        dots[i].classList.add('stage-player__dot--seen');
      }
      tryComplete();
    }

    function tryComplete() {
      if (completed) {
        return;
      }
      for (var i = 0; i < total; i++) {
        if (!seen[i]) {
          return;
        }
      }
      completed = true;
      if (window.LmsLessonProgress && typeof window.LmsLessonProgress.signalComplete === 'function') {
        window.LmsLessonProgress.signalComplete();
      }
    }

    function scheduleDwell(i) {
      clearTimeout(dwellTimer);
      dwellTimer = setTimeout(function () {
        markSeen(i);
      }, dwellMs() + 120);
    }

    function render() {
      slides.forEach(function (slide, i) {
        var active = i === index;
        slide.hidden = !active;
        slide.setAttribute('aria-hidden', active ? 'false' : 'true');
      });
      dots.forEach(function (dot, i) {
        dot.setAttribute('aria-current', i === index ? 'true' : 'false');
      });
      if (prevBtn) {
        prevBtn.disabled = index === 0;
      }
      if (nextBtn) {
        nextBtn.disabled = index === total - 1;
      }
      if (meterFill) {
        meterFill.style.width = Math.round(((index + 1) / total) * 100) + '%';
      }
      if (meterLabel) {
        meterLabel.textContent = 'Étape ' + (index + 1) + ' sur ' + total;
      }
      if (liveRegion) {
        liveRegion.textContent = 'Étape ' + (index + 1) + ' sur ' + total;
      }
    }

    function goTo(target) {
      var next = Math.max(0, Math.min(total - 1, target));
      if (next === index) {
        return;
      }
      // L'étape quittée ne compte que si elle a été lue assez longtemps.
      markSeen(index);
      index = next;
      enteredAt[index] = Date.now();
      render();
      scheduleDwell(index);
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        goTo(index - 1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        goTo(index + 1);
      });
    }
    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function () {
        goTo(i);
      });
    });

    // Clavier : uniquement quand le lecteur a le focus, pour ne pas capturer
    // les flèches d'un champ de saisie ailleurs dans la page.
    root.addEventListener('keydown', function (event) {
      if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
        return;
      }
      var tag = (event.target && event.target.tagName ? event.target.tagName : '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select') {
        return;
      }
      switch (event.key) {
        case 'ArrowRight':
        case 'PageDown':
          goTo(index + 1);
          event.preventDefault();
          break;
        case 'ArrowLeft':
        case 'PageUp':
          goTo(index - 1);
          event.preventDefault();
          break;
        case 'Home':
          goTo(0);
          event.preventDefault();
          break;
        case 'End':
          goTo(total - 1);
          event.preventDefault();
          break;
        default:
          break;
      }
    });

    enteredAt[0] = Date.now();
    render();
    scheduleDwell(0);
  }

  function boot() {
    Array.prototype.slice
      .call(document.querySelectorAll('[data-stage-player]'))
      .forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.TrainingStagePlayer = { init: init, boot: boot };
})();
