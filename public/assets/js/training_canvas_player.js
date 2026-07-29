/**
 * Lecteur « canvas » : Swiper si disponible, sinon affichage classique.
 * Validation leçon : toutes les étapes visitées + confirmation sur la dernière
 * (attente courte ou clic « Suivant » / Terminer).
 */
(function () {
  'use strict';

  function qs(root, sel) {
    return root.querySelector(sel);
  }

  function qsa(root, sel) {
    return Array.prototype.slice.call(root.querySelectorAll(sel));
  }

  function showCanvasToast(root, message) {
    if (!root || !message) return;
    var t = root.querySelector('[data-lms-canvas-toast]');
    if (!t) return;
    t.textContent = message;
    t.classList.remove('hidden');
    var wrong =
      root.querySelector('[data-lms-blank].lms-fill-blank-input--wrong') ||
      root.querySelector('[data-lms-blank].ring-rose-400');
    if (wrong && typeof wrong.focus === 'function' && !wrong.readOnly) {
      try {
        wrong.focus();
      } catch (e) {}
    }
    if (t._hideT) clearTimeout(t._hideT);
    t._hideT = setTimeout(function () {
      t.classList.add('hidden');
      t.textContent = '';
    }, 7000);
  }

  function updateSlideProgress(root, activeIndex, totalSlides) {
    if (!root) return;
    var total = totalSlides;
    if (!total) {
      total = parseInt(root.getAttribute('data-lms-canvas-slide-count') || '0', 10) || 0;
    }
    if (!total) return;
    var cur = (activeIndex | 0) + 1;
    var label = qs(root, '[data-lms-canvas-slide-label]');
    var bar = qs(root, '[data-lms-canvas-slide-progress-bar]');
    if (label) label.textContent = 'Étape ' + cur + ' sur ' + total;
    var pct = Math.min(100, Math.round((100 * cur) / total));
    if (bar) bar.style.width = pct + '%';
  }

  /** Temps minimum sur la dernière étape avant déblocage du bouton Terminer (ms). */
  function getLastSlideDwellMs() {
    var c = window.__LMS_LESSON_PROGRESS__;
    var v = c && c.strict && c.strict.slideDwellMs;
    return typeof v === 'number' && v > 0 ? v : 1800;
  }

  function signalLessonComplete(immediate) {
    if (window.LmsLessonProgress && typeof window.LmsLessonProgress.signalComplete === 'function') {
      window.LmsLessonProgress.signalComplete(!!immediate);
    }
  }

  function normalizeBlankValue(s) {
    return String(s || '')
      .trim()
      .toLowerCase()
      .normalize('NFC')
      .replace(/\s+/g, ' ');
  }

  function isBlankLocked(inp) {
    return !!(inp && (inp.readOnly || inp.getAttribute('data-lms-blank-locked') === '1'));
  }

  function clearBlankTransientClasses(inp) {
    if (!inp) return;
    inp.classList.remove(
      'ring-2',
      'ring-rose-400',
      'ring-emerald-400',
      'bg-rose-50',
      'lms-fill-blank-input--wrong',
      'lms-fill-blank-input--ok'
    );
  }

  function lockBlankInput(inp) {
    if (!inp || isBlankLocked(inp)) return;
    var expected = (inp.getAttribute('data-expected') || '').trim();
    if (expected !== '') {
      inp.value = expected;
    }
    inp.readOnly = true;
    inp.setAttribute('data-lms-blank-locked', '1');
    inp.setAttribute('aria-invalid', 'false');
    inp.setAttribute('aria-readonly', 'true');
    clearBlankTransientClasses(inp);
    inp.classList.add('lms-fill-blank-input--ok', 'ring-2', 'ring-emerald-400');
  }

  function markBlankWrong(inp) {
    if (!inp || isBlankLocked(inp)) return;
    clearBlankTransientClasses(inp);
    inp.classList.add('lms-fill-blank-input--wrong', 'ring-2', 'ring-rose-400', 'bg-rose-50');
    inp.setAttribute('aria-invalid', 'true');
  }

  function clearBlankFeedback(inp) {
    if (!inp || isBlankLocked(inp)) return;
    clearBlankTransientClasses(inp);
    inp.removeAttribute('aria-invalid');
  }

  /**
   * Vérifie un champ : lock immédiat si correct ; feedback soft si incorrect.
   * @param {{showWrong?: boolean}} opts
   * @returns {boolean}
   */
  function evaluateBlankInput(inp, opts) {
    opts = opts || {};
    if (!inp) return true;
    if (isBlankLocked(inp)) return true;
    var exp = normalizeBlankValue(inp.getAttribute('data-expected'));
    var val = normalizeBlankValue(inp.value);
    if (val === '') {
      clearBlankFeedback(inp);
      return false;
    }
    if (val === exp) {
      lockBlankInput(inp);
      return true;
    }
    if (opts.showWrong !== false) {
      markBlankWrong(inp);
    }
    return false;
  }

  function slideHasFillBlanks(slideEl) {
    return !!(slideEl && slideEl.querySelector('[data-lms-fill-blanks-slide] [data-lms-blank]'));
  }

  function fillBlanksCompleteInSlide(slideEl) {
    if (!slideEl) return true;
    var host = slideEl.querySelector('[data-lms-fill-blanks-slide]');
    if (!host) return true;
    var inputs = host.querySelectorAll('[data-lms-blank]');
    if (!inputs.length) return true;
    for (var i = 0; i < inputs.length; i++) {
      var inp = inputs[i];
      if (isBlankLocked(inp)) continue;
      if (normalizeBlankValue(inp.value) !== normalizeBlankValue(inp.getAttribute('data-expected'))) {
        return false;
      }
    }
    return true;
  }

  /**
   * @param {Element|null} slideEl
   * @param {{toast?: boolean, markEmpty?: boolean}} opts
   */
  function validateFillBlanksInSlideEl(slideEl, opts) {
    opts = opts || {};
    if (!slideEl) return true;
    var root = slideEl.closest('[data-lms-canvas-player]');
    var host = slideEl.querySelector('[data-lms-fill-blanks-slide]');
    if (!host) return true;
    var inputs = host.querySelectorAll('[data-lms-blank]');
    if (!inputs.length) return true;
    var ok = true;
    inputs.forEach(function (inp) {
      if (isBlankLocked(inp)) return;
      var exp = normalizeBlankValue(inp.getAttribute('data-expected'));
      var val = normalizeBlankValue(inp.value);
      if (val === exp && val !== '') {
        lockBlankInput(inp);
        return;
      }
      ok = false;
      if (val === '' && opts.markEmpty !== false) {
        clearBlankTransientClasses(inp);
        inp.classList.add('ring-2', 'ring-rose-400');
        inp.setAttribute('aria-invalid', 'true');
      } else if (val !== '') {
        markBlankWrong(inp);
      }
    });
    if (!ok && opts.toast !== false && root) {
      showCanvasToast(
        root,
        'Complétez chaque champ : une réponse correcte se verrouille tout de suite. Corrigez les champs surlignés avant de continuer.'
      );
    }
    return ok;
  }

  function wireLiveFillBlanks(root, onChange) {
    root.querySelectorAll('[data-lms-fill-blanks-slide]').forEach(function (host) {
      if (host.__lmsFillLiveWired) return;
      host.__lmsFillLiveWired = true;
      host.querySelectorAll('[data-lms-blank]').forEach(function (inp) {
        inp.addEventListener('input', function () {
          if (isBlankLocked(inp)) return;
          var exp = normalizeBlankValue(inp.getAttribute('data-expected'));
          var val = normalizeBlankValue(inp.value);
          if (val === '') {
            clearBlankFeedback(inp);
          } else if (val === exp) {
            lockBlankInput(inp);
          } else {
            /* Feedback immédiat sans bloquer la frappe */
            markBlankWrong(inp);
          }
          if (typeof onChange === 'function') onChange();
        });
        inp.addEventListener('blur', function () {
          if (isBlankLocked(inp)) return;
          evaluateBlankInput(inp, { showWrong: true });
          if (typeof onChange === 'function') onChange();
        });
        inp.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            evaluateBlankInput(inp, { showWrong: true });
            if (typeof onChange === 'function') onChange();
          }
        });
      });
    });
  }

  function initPlayer(root) {
    if (!root || root.__lmsPlayerInit) return;
    root.__lmsPlayerInit = true;
    var swiperEl = root.querySelector('.lms-canvas-swiper');
    var prevBtns = qsa(root, '[data-lms-prev]');
    var nextBtns = qsa(root, '[data-lms-next]');
    var prevBtn = prevBtns[0] || null;
    var nextBtn = nextBtns[0] || null;
    var dots = qsa(root, '[data-lms-canvas-dot]');
    var slides = root.querySelectorAll('[data-lms-slide]');
    var total = slides.length;
    var visited = new Set();
    var lastDwellTimer = null;

    function setPrevDisabled(disabled) {
      prevBtns.forEach(function (b) {
        b.disabled = !!disabled;
      });
    }

    function syncDots(activeIndex) {
      dots.forEach(function (d, i) {
        var on = i === activeIndex;
        d.classList.toggle('is-active', on);
        d.setAttribute('aria-current', on ? 'true' : 'false');
      });
    }

    function wireScenarioChoices() {
      root.querySelectorAll('[data-lms-scenario]').forEach(function (box) {
        if (box.__lmsScenarioWired) return;
        box.__lmsScenarioWired = true;
        var correct = String(box.getAttribute('data-correct') || '');
        var explain = box.parentElement
          ? box.parentElement.querySelector('[data-lms-scenario-explain]')
          : null;
        box.querySelectorAll('[data-lms-scenario-option]').forEach(function (inp) {
          inp.addEventListener('change', function () {
            var ok = correct !== '' && inp.value === correct;
            box.querySelectorAll('label').forEach(function (lab) {
              lab.classList.remove('border-emerald-500', 'bg-emerald-50', 'border-rose-300', 'bg-rose-50');
            });
            var lab = inp.closest('label');
            if (lab) {
              lab.classList.add(
                ok ? 'border-emerald-500' : 'border-rose-300',
                ok ? 'bg-emerald-50' : 'bg-rose-50'
              );
            }
            if (explain) {
              if (ok) explain.classList.remove('hidden');
              else explain.classList.add('hidden');
            }
          });
        });
      });
    }

    function markVisited(index) {
      if (typeof index === 'number' && index >= 0 && index < total) {
        visited.add(index);
      }
    }

    function allVisited() {
      if (visited.size < total) return false;
      for (var k = 0; k < total; k++) {
        if (!visited.has(k)) return false;
      }
      return true;
    }

    function currentSlideEl(activeIndex) {
      if (swiperEl && root.__lmsSwiper && root.__lmsSwiper.slides) {
        return root.__lmsSwiper.slides[activeIndex] || null;
      }
      return slides[activeIndex] || null;
    }

    function syncNextButton(activeIndex) {
      if (!nextBtns.length) return;
      var onLast = activeIndex >= total - 1;
      var slideEl = currentSlideEl(activeIndex);
      var blanksBlock = slideHasFillBlanks(slideEl) && !fillBlanksCompleteInSlide(slideEl);
      nextBtns.forEach(function (btn) {
        btn.disabled = !!blanksBlock;
        if (onLast) {
          btn.textContent = 'Terminer →';
          btn.setAttribute(
            'aria-label',
            blanksBlock
              ? 'Terminer — complétez d’abord tous les champs'
              : 'Terminer le parcours interactif'
          );
        } else {
          btn.textContent = 'Suivant →';
          btn.setAttribute(
            'aria-label',
            blanksBlock ? 'Étape suivante — complétez d’abord tous les champs' : 'Étape suivante'
          );
        }
      });
    }

    function refreshNextFromActive() {
      var i =
        swiperEl && root.__lmsSwiper
          ? root.__lmsSwiper.activeIndex
          : root.__lmsFbIndex | 0;
      syncNextButton(i);
    }

    function isOnLast() {
      if (swiperEl && root.__lmsSwiper) {
        return root.__lmsSwiper.activeIndex >= total - 1;
      }
      return (root.__lmsFbIndex | 0) >= total - 1;
    }

    /** @param {boolean} force — clic « Terminer » ; sinon après délai sur la dernière étape (débloque seulement) */
    function tryComplete(force) {
      if (!allVisited()) {
        if (force) {
          showCanvasToast(
            root,
            'Parcourez toutes les étapes du parcours (Précédent / Suivant) avant de terminer.'
          );
        }
        return;
      }
      if (!isOnLast()) return;
      if (!force && !root.__lmsLastDwellOk) return;
      signalLessonComplete(!!force);
    }

    function scheduleLastDwell(activeIndex) {
      clearTimeout(lastDwellTimer);
      root.__lmsLastDwellOk = false;
      if (activeIndex !== total - 1) return;
      lastDwellTimer = setTimeout(function () {
        root.__lmsLastDwellOk = true;
        tryComplete(false);
      }, getLastSlideDwellMs());
    }

    function wireModals() {
      root.querySelectorAll('[data-lms-open-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-lms-open-modal');
          var panel = document.getElementById('lms-modal-' + id);
          if (panel) {
            panel.classList.remove('hidden');
            panel.setAttribute('aria-hidden', 'false');
          }
        });
      });
      document.querySelectorAll('[data-lms-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var panel = btn.closest('[data-lms-modal-panel]');
          if (panel) {
            panel.classList.add('hidden');
            panel.setAttribute('aria-hidden', 'true');
          }
        });
      });
    }

    wireModals();
    wireLiveFillBlanks(root, refreshNextFromActive);
    wireScenarioChoices();

    if (swiperEl && typeof window.Swiper === 'function' && total > 0) {
      var sw = new window.Swiper(swiperEl, {
        slidesPerView: 1,
        spaceBetween: 0,
        pagination: {
          el: swiperEl.querySelector('.swiper-pagination'),
          clickable: true,
        },
        keyboard: { enabled: true },
        a11y: { enabled: true },
        on: {
          init: function (swIn) {
            root.__lmsSwiper = swIn;
            markVisited(swIn.activeIndex);
            updateSlideProgress(root, swIn.activeIndex, total);
            syncNextButton(swIn.activeIndex);
            syncDots(swIn.activeIndex);
            scheduleLastDwell(swIn.activeIndex);
            setPrevDisabled(swIn.activeIndex === 0);
          },
          slideChange: function (swIn) {
            var i = swIn.activeIndex;
            markVisited(i);
            setPrevDisabled(i === 0);
            syncNextButton(i);
            syncDots(i);
            updateSlideProgress(root, i, total);
            scheduleLastDwell(i);
          },
        },
      });
      root.__lmsSwiper = sw;

      var origSlideTo = sw.slideTo.bind(sw);
      sw.slideTo = function (index, speed, runCallbacks, internal) {
        var cur = sw.activeIndex;
        var target = typeof index === 'number' ? index : parseInt(index, 10);
        if (target > cur && !validateFillBlanksInSlideEl(sw.slides[cur], { toast: true })) {
          syncNextButton(cur);
          return sw;
        }
        return origSlideTo(index, speed, runCallbacks, internal);
      };

      function goPrev() {
        sw.slidePrev();
      }
      function goNext() {
        var cur = sw.activeIndex;
        if (!validateFillBlanksInSlideEl(sw.slides[cur], { toast: true })) {
          syncNextButton(cur);
          return;
        }
        markVisited(cur);
        if (cur >= total - 1) {
          tryComplete(true);
          return;
        }
        sw.slideNext();
      }

      prevBtns.forEach(function (b) {
        b.addEventListener('click', goPrev);
      });
      nextBtns.forEach(function (b) {
        b.addEventListener('click', goNext);
      });
      dots.forEach(function (d) {
        d.addEventListener('click', function () {
          var i = parseInt(d.getAttribute('data-lms-canvas-dot') || '0', 10);
          if (isNaN(i)) return;
          var cur = sw.activeIndex;
          if (i > cur && !validateFillBlanksInSlideEl(sw.slides[cur], { toast: true })) {
            syncNextButton(cur);
            return;
          }
          sw.slideTo(i);
        });
      });
      document.addEventListener('keydown', function (e) {
        if (!root.getBoundingClientRect || root.offsetParent === null) return;
        var r = root.getBoundingClientRect();
        if (r.top > window.innerHeight || r.bottom < 0) return;
        if (e.key === 'ArrowRight') goNext();
        if (e.key === 'ArrowLeft') goPrev();
      });
      return;
    }

    /* Fallback sans Swiper */
    var idx = -1;
    root.__lmsFbIndex = 0;

    function show(i) {
      var newI = Math.max(0, Math.min(i, total - 1));
      idx = newI;
      root.__lmsFbIndex = idx;
      markVisited(idx);
      slides.forEach(function (el, j) {
        el.classList.toggle('hidden', j !== idx);
      });
      setPrevDisabled(idx === 0);
      syncNextButton(idx);
      syncDots(idx);
      updateSlideProgress(root, idx, total);
      scheduleLastDwell(idx);
    }

    prevBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        show(idx - 1);
      });
    });
    nextBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        var curEl = slides[idx];
        if (!validateFillBlanksInSlideEl(curEl, { toast: true })) {
          syncNextButton(idx);
          return;
        }
        markVisited(idx);
        if (idx >= total - 1) {
          tryComplete(true);
          return;
        }
        show(idx + 1);
      });
    });
    dots.forEach(function (d) {
      d.addEventListener('click', function () {
        var i = parseInt(d.getAttribute('data-lms-canvas-dot') || '0', 10);
        if (!isNaN(i)) show(i);
      });
    });
    document.addEventListener('keydown', function (e) {
      if (!root.getBoundingClientRect || root.offsetParent === null) return;
      var r = root.getBoundingClientRect();
      if (r.top > window.innerHeight || r.bottom < 0) return;
      if (e.key === 'ArrowRight') {
        if (!validateFillBlanksInSlideEl(slides[idx], { toast: true })) {
          syncNextButton(idx);
          return;
        }
        markVisited(idx);
        if (idx >= total - 1) {
          tryComplete(true);
          return;
        }
        show(idx + 1);
      }
      if (e.key === 'ArrowLeft') show(idx - 1);
    });
    show(0);
  }

  function boot() {
    document.querySelectorAll('[data-lms-canvas-player]').forEach(initPlayer);
    document.querySelectorAll('[data-lms-modal-panel]').forEach(function (panel) {
      panel.addEventListener('click', function (e) {
        if (e.target === panel) {
          panel.classList.add('hidden');
          panel.setAttribute('aria-hidden', 'true');
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
