/**
 * Deck de slides — page d’enrôlement public.
 * Navigation Suivant / Précédent, dots, clavier, ancres (#candidature, #parcours…).
 */
(function () {
  'use strict';

  var deck = document.getElementById('ce-deck');
  if (!deck) return;

  var slidesRoot = document.getElementById('ce-slides');
  var slides = slidesRoot
    ? Array.prototype.slice.call(slidesRoot.querySelectorAll('.ce-slide'))
    : [];
  if (!slides.length) return;

  var dotsRoot = document.getElementById('ce-deck-dots');
  var btnPrev = document.getElementById('ce-deck-prev');
  var btnNext = document.getElementById('ce-deck-next');
  var live = document.getElementById('ce-deck-live');
  var reduceMotion = false;
  try {
    reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  } catch (e) {}

  var index = 0;
  var transitioning = false;
  var HASH_MAP = {
    hero: 0,
    'slide-hero': 0,
    parcours: 1,
    attentes: 2,
    offres: 3,
    candidature: 4
  };

  function clamp(i) {
    if (i < 0) return 0;
    if (i > slides.length - 1) return slides.length - 1;
    return i;
  }

  function slideLabel(i) {
    var el = slides[i];
    if (!el) return 'Écran ' + (i + 1);
    return el.getAttribute('data-ce-label') || ('Écran ' + (i + 1));
  }

  function syncDots() {
    if (!dotsRoot) return;
    var buttons = dotsRoot.querySelectorAll('[data-ce-dot]');
    buttons.forEach(function (btn) {
      var i = parseInt(btn.getAttribute('data-ce-dot'), 10);
      var on = i === index;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-current', on ? 'true' : 'false');
      btn.setAttribute('aria-label', slideLabel(i) + (on ? ' (actuel)' : ''));
    });
  }

  function syncControls() {
    if (btnPrev) {
      btnPrev.disabled = index <= 0;
      btnPrev.setAttribute('aria-disabled', index <= 0 ? 'true' : 'false');
    }
    if (btnNext) {
      var last = index >= slides.length - 1;
      btnNext.disabled = last;
      btnNext.setAttribute('aria-disabled', last ? 'true' : 'false');
      var nextLabel = last ? 'Fin' : 'Suivant';
      btnNext.textContent = nextLabel;
    }
    if (live) {
      live.textContent = slideLabel(index) + ' — ' + (index + 1) + ' sur ' + slides.length;
    }
    deck.setAttribute('data-ce-index', String(index));
    deck.classList.toggle('ce-deck--form', index === slides.length - 1);
  }

  function setHashForIndex(i, replace) {
    var id = slides[i] && slides[i].id ? slides[i].id : '';
    if (!id) return;
    var url = window.location.pathname + window.location.search + '#' + id;
    try {
      if (replace) {
        history.replaceState(null, '', url);
      } else {
        history.replaceState(null, '', url);
      }
    } catch (e) {}
  }

  function goTo(target, opts) {
    opts = opts || {};
    var next = clamp(typeof target === 'number' ? target : 0);
    if (next === index && !opts.force) {
      syncControls();
      syncDots();
      return;
    }
    if (transitioning && !opts.force) return;

    var prev = index;
    var prevEl = slides[prev];
    var nextEl = slides[next];
    if (!nextEl) return;

    transitioning = !reduceMotion;
    index = next;

    slides.forEach(function (el, i) {
      var active = i === next;
      el.classList.toggle('is-active', active);
      el.classList.toggle('is-exit', !active && i === prev && !reduceMotion);
      el.setAttribute('aria-hidden', active ? 'false' : 'true');
      if (active) {
        el.removeAttribute('tabindex');
      } else {
        el.setAttribute('tabindex', '-1');
      }
    });

    syncDots();
    syncControls();

    if (opts.updateHash !== false) {
      setHashForIndex(next, !!opts.replaceHash);
    }

    if (nextEl.scrollTop) {
      nextEl.scrollTop = 0;
    }

    if (reduceMotion) {
      transitioning = false;
      if (prevEl) prevEl.classList.remove('is-exit');
      return;
    }

    window.setTimeout(function () {
      transitioning = false;
      slides.forEach(function (el) {
        el.classList.remove('is-exit');
      });
    }, 420);
  }

  function indexFromHash() {
    var raw = (window.location.hash || '').replace(/^#/, '');
    if (!raw) return null;
    if (Object.prototype.hasOwnProperty.call(HASH_MAP, raw)) {
      return HASH_MAP[raw];
    }
    for (var i = 0; i < slides.length; i++) {
      if (slides[i].id === raw) return i;
    }
    return null;
  }

  function buildDots() {
    if (!dotsRoot) return;
    dotsRoot.innerHTML = '';
    slides.forEach(function (el, i) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ce-deck-dot';
      btn.setAttribute('data-ce-dot', String(i));
      btn.setAttribute('aria-label', slideLabel(i));
      btn.addEventListener('click', function () {
        goTo(i);
      });
      dotsRoot.appendChild(btn);
    });
  }

  function isTypingTarget(el) {
    if (!el || !el.tagName) return false;
    var tag = el.tagName.toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
    if (el.isContentEditable) return true;
    return false;
  }

  buildDots();

  if (btnPrev) {
    btnPrev.addEventListener('click', function () {
      goTo(index - 1);
    });
  }
  if (btnNext) {
    btnNext.addEventListener('click', function () {
      goTo(index + 1);
    });
  }

  document.addEventListener('keydown', function (ev) {
    if (isTypingTarget(ev.target)) return;
    if (ev.key === 'ArrowRight' || ev.key === 'PageDown') {
      ev.preventDefault();
      goTo(index + 1);
    } else if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') {
      ev.preventDefault();
      goTo(index - 1);
    } else if (ev.key === 'Home') {
      ev.preventDefault();
      goTo(0);
    } else if (ev.key === 'End') {
      ev.preventDefault();
      goTo(slides.length - 1);
    }
  });

  document.addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a[href^="#"]') : null;
    if (!a) return;
    var href = a.getAttribute('href') || '';
    var id = href.replace(/^#/, '');
    if (!id) return;
    var mapped = Object.prototype.hasOwnProperty.call(HASH_MAP, id) ? HASH_MAP[id] : null;
    if (mapped === null) {
      for (var i = 0; i < slides.length; i++) {
        if (slides[i].id === id) {
          mapped = i;
          break;
        }
      }
    }
    if (mapped === null) return;
    ev.preventDefault();
    goTo(mapped);
  });

  window.addEventListener('hashchange', function () {
    var fromHash = indexFromHash();
    if (fromHash !== null) goTo(fromHash, { updateHash: false, force: true });
  });

  var start = indexFromHash();
  if (start === null) {
    var boot = deck.getAttribute('data-ce-start') || '';
    if (boot && Object.prototype.hasOwnProperty.call(HASH_MAP, boot)) {
      start = HASH_MAP[boot];
    }
  }
  if (start === null) start = 0;
  goTo(start, { force: true, replaceHash: true, updateHash: start !== 0 });
})();
