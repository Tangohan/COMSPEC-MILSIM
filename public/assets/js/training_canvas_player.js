/**
 * Lecteur « canvas » : Swiper si disponible, sinon affichage classique.
 */
(function () {
  'use strict';

  function qs(root, sel) {
    return root.querySelector(sel);
  }

  function showCanvasToast(root, message) {
    if (!root || !message) return;
    var t = root.querySelector('[data-lms-canvas-toast]');
    if (!t) return;
    t.textContent = message;
    t.classList.remove('hidden');
    var wrong = root.querySelector('[data-lms-blank].ring-rose-400');
    if (wrong && typeof wrong.focus === 'function') {
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

  /** Temps minimum sur chaque étape (configurable via window.__LMS_LESSON_PROGRESS__.strict.slideDwellMs). */
  function getSlideDwellMs() {
    var c = window.__LMS_LESSON_PROGRESS__;
    var v = c && c.strict && c.strict.slideDwellMs;
    return typeof v === 'number' && v > 0 ? v : 2600;
  }

  function validateFillBlanksInSlideEl(slideEl) {
    if (!slideEl) return true;
    var root = slideEl.closest('[data-lms-canvas-player]');
    var host = slideEl.querySelector('[data-lms-fill-blanks-slide]');
    if (!host) return true;
    var inputs = host.querySelectorAll('[data-lms-blank]');
    if (!inputs.length) return true;
    var ok = true;
    inputs.forEach(function (inp) {
      var exp = (inp.getAttribute('data-expected') || '').trim().toLowerCase();
      var val = (inp.value || '').trim().toLowerCase();
      if (val !== exp) ok = false;
      inp.classList.remove('ring-2', 'ring-rose-400', 'ring-emerald-400', 'bg-rose-50');
      if (!val) {
        inp.classList.add('ring-2', 'ring-rose-400');
      } else if (val !== exp) {
        inp.classList.add('ring-2', 'ring-rose-400', 'bg-rose-50');
      } else {
        inp.classList.add('ring-2', 'ring-emerald-400');
      }
    });
    if (!ok && root) {
      showCanvasToast(
        root,
        'Vérifiez les champs surlignés : ils doivent correspondre exactement aux termes attendus avant de passer à l’étape suivante.'
      );
    }
    return ok;
  }

  function initPlayer(root) {
    if (!root || root.__lmsPlayerInit) return;
    root.__lmsPlayerInit = true;
    var swiperEl = root.querySelector('.lms-canvas-swiper');
    var prevBtn = qs(root, '[data-lms-prev]');
    var nextBtn = qs(root, '[data-lms-next]');
    var slides = root.querySelectorAll('[data-lms-slide]');
    var total = slides.length;

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

    if (swiperEl && typeof window.Swiper === 'function' && total > 0) {
      var MIN_DWELL = getSlideDwellMs();
      var slideEnterTime = Object.create(null);
      var slideConfirmed = new Set();
      var lastSlideTimer = null;

      function confirmIfDwelt(index) {
        if (typeof index !== 'number' || index < 0 || index >= total) return;
        var t0 = slideEnterTime[index];
        if (t0 != null && Date.now() - t0 >= MIN_DWELL) {
          slideConfirmed.add(index);
        }
      }

      function scheduleLastDwell(activeIndex) {
        clearTimeout(lastSlideTimer);
        if (activeIndex !== total - 1) return;
        lastSlideTimer = setTimeout(function () {
          confirmIfDwelt(total - 1);
          trySignalLessonComplete();
        }, MIN_DWELL + 120);
      }

      function trySignalLessonComplete() {
        if (slideConfirmed.size < total) return;
        for (var k = 0; k < total; k++) {
          if (!slideConfirmed.has(k)) return;
        }
        if (window.LmsLessonProgress && typeof window.LmsLessonProgress.signalComplete === 'function') {
          window.LmsLessonProgress.signalComplete();
        }
      }

      function onSwiperSlideChange(swIn) {
        var i = swIn.activeIndex;
        var prev = swIn.previousIndex;
        if (typeof prev === 'number' && prev >= 0 && prev !== i) {
          confirmIfDwelt(prev);
        }
        slideEnterTime[i] = Date.now();
        updateSlideProgress(root, i, total);
        scheduleLastDwell(i);
        trySignalLessonComplete();
      }

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
            slideEnterTime[swIn.activeIndex] = Date.now();
            updateSlideProgress(root, swIn.activeIndex, total);
            scheduleLastDwell(swIn.activeIndex);
            trySignalLessonComplete();
            if (prevBtn) prevBtn.disabled = swIn.activeIndex === 0;
            if (nextBtn) nextBtn.disabled = total <= 1;
          },
          slideChange: function (swIn) {
            var i = swIn.activeIndex;
            if (prevBtn) prevBtn.disabled = i === 0;
            if (nextBtn) nextBtn.disabled = i >= total - 1;
            onSwiperSlideChange(swIn);
          },
        },
      });

      var origSlideTo = sw.slideTo.bind(sw);
      sw.slideTo = function (index, speed, runCallbacks, internal) {
        var cur = sw.activeIndex;
        var target = typeof index === 'number' ? index : parseInt(index, 10);
        if (target > cur && !validateFillBlanksInSlideEl(sw.slides[cur])) {
          return sw;
        }
        return origSlideTo(index, speed, runCallbacks, internal);
      };

      if (prevBtn) {
        prevBtn.addEventListener('click', function () {
          sw.slidePrev();
        });
      }
      if (nextBtn) {
        nextBtn.addEventListener('click', function () {
          if (!validateFillBlanksInSlideEl(sw.slides[sw.activeIndex])) return;
          sw.slideNext();
        });
      }
      document.addEventListener('keydown', function (e) {
        if (!root.getBoundingClientRect || root.offsetParent === null) return;
        var r = root.getBoundingClientRect();
        if (r.top > window.innerHeight || r.bottom < 0) return;
        if (e.key === 'ArrowRight') {
          if (!validateFillBlanksInSlideEl(sw.slides[sw.activeIndex])) return;
          sw.slideNext();
        }
        if (e.key === 'ArrowLeft') sw.slidePrev();
      });
      return;
    }

    /* Fallback sans Swiper */
    var idx = -1;
    var MIN_DWELL_FB = getSlideDwellMs();
    var slideEnterTimeFb = Object.create(null);
    var slideConfirmedFb = new Set();
    var lastTimerFb = null;

    function confirmIfDweltFb(index) {
      if (typeof index !== 'number' || index < 0 || index >= total) return;
      var t0 = slideEnterTimeFb[index];
      if (t0 != null && Date.now() - t0 >= MIN_DWELL_FB) {
        slideConfirmedFb.add(index);
      }
    }

    function scheduleLastFb(activeIndex) {
      clearTimeout(lastTimerFb);
      if (activeIndex !== total - 1) return;
      lastTimerFb = setTimeout(function () {
        confirmIfDweltFb(total - 1);
        tryCompleteFb();
      }, MIN_DWELL_FB + 120);
    }

    function tryCompleteFb() {
      if (slideConfirmedFb.size < total) return;
      for (var k = 0; k < total; k++) {
        if (!slideConfirmedFb.has(k)) return;
      }
      if (window.LmsLessonProgress && typeof window.LmsLessonProgress.signalComplete === 'function') {
        window.LmsLessonProgress.signalComplete();
      }
    }

    function show(i) {
      var newI = Math.max(0, Math.min(i, total - 1));
      if (newI !== idx) {
        if (idx >= 0) confirmIfDweltFb(idx);
        idx = newI;
        slideEnterTimeFb[idx] = Date.now();
        scheduleLastFb(idx);
      }
      slides.forEach(function (el, j) {
        el.classList.toggle('hidden', j !== idx);
      });
      if (prevBtn) prevBtn.disabled = idx === 0;
      if (nextBtn) nextBtn.disabled = idx >= total - 1;
      updateSlideProgress(root, idx, total);
      tryCompleteFb();
    }

    if (prevBtn)
      prevBtn.addEventListener('click', function () {
        show(idx - 1);
      });
    if (nextBtn)
      nextBtn.addEventListener('click', function () {
        var curEl = slides[idx];
        if (!validateFillBlanksInSlideEl(curEl)) return;
        show(idx + 1);
      });
    document.addEventListener('keydown', function (e) {
      if (!root.getBoundingClientRect || root.offsetParent === null) return;
      var r = root.getBoundingClientRect();
      if (r.top > window.innerHeight || r.bottom < 0) return;
      if (e.key === 'ArrowRight') {
        if (!validateFillBlanksInSlideEl(slides[idx])) return;
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
