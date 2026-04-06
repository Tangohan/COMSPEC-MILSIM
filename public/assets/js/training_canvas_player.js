/**
 * Lecteur « canvas » : Swiper si disponible, sinon affichage classique.
 */
(function () {
  'use strict';

  function qs(root, sel) {
    return root.querySelector(sel);
  }

  function validateFillBlanksInSlideEl(slideEl) {
    if (!slideEl) return true;
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
    if (!ok && window.alert) {
      window.alert('Complétez correctement tous les champs avant de passer à l’étape suivante.');
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
      var visited = new Set();
      function noteVisit(swIn) {
        visited.add(swIn.activeIndex);
        if (visited.size >= total && window.LmsLessonProgress && typeof window.LmsLessonProgress.signalComplete === 'function') {
          window.LmsLessonProgress.signalComplete();
        }
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
            noteVisit(swIn);
          },
          slideChange: function (swIn) {
            var i = swIn.activeIndex;
            if (prevBtn) prevBtn.disabled = i === 0;
            if (nextBtn) nextBtn.disabled = i >= total - 1;
            noteVisit(swIn);
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
      if (prevBtn) prevBtn.disabled = true;
      if (nextBtn) nextBtn.disabled = total <= 1;
      return;
    }

    /* Fallback sans Swiper */
    var idx = 0;
    var visitedFb = new Set();

    function checkAllVisited() {
      if (visitedFb.size >= total && window.LmsLessonProgress && typeof window.LmsLessonProgress.signalComplete === 'function') {
        window.LmsLessonProgress.signalComplete();
      }
    }

    function show(i) {
      idx = Math.max(0, Math.min(i, total - 1));
      visitedFb.add(idx);
      checkAllVisited();
      slides.forEach(function (el, j) {
        el.classList.toggle('hidden', j !== idx);
      });
      if (prevBtn) prevBtn.disabled = idx === 0;
      if (nextBtn) nextBtn.disabled = idx >= total - 1;
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
