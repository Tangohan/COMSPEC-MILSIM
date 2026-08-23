/* Journal produit Athena — filtres, recherche, lightbox, reveal */
(function () {
  'use strict';

  var root = document.querySelector('[data-cl-root]');
  if (!root) return;

  var domain = 'all';
  var year = 'all';
  var query = '';

  var domainBtns = root.querySelectorAll('[data-cl-domain]');
  var yearBtns = root.querySelectorAll('[data-cl-year]');
  var search = root.querySelector('[data-cl-search]');
  var cards = root.querySelectorAll('[data-cl-card]');
  var years = root.querySelectorAll('[data-cl-year-block]');
  var empty = root.querySelector('[data-cl-empty]');
  var lb = document.getElementById('cl-lightbox');
  var lbImg = lb ? lb.querySelector('img') : null;

  function norm(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function apply() {
    var q = norm(query);
    var visibleYears = {};
    var shown = 0;
    cards.forEach(function (card) {
      var groups = (card.getAttribute('data-groups') || '').split(/\s+/);
      var y = card.getAttribute('data-year') || '';
      var hay = card.getAttribute('data-search') || '';
      var okDomain = domain === 'all' || groups.indexOf(domain) !== -1;
      var okYear = year === 'all' || y === year;
      var okQ = !q || norm(hay).indexOf(q) !== -1;
      var on = okDomain && okYear && okQ;
      card.classList.toggle('is-hidden', !on);
      if (on) {
        shown += 1;
        visibleYears[y] = true;
      }
    });
    years.forEach(function (block) {
      var y = block.getAttribute('data-cl-year-block') || '';
      block.classList.toggle('is-hidden', !visibleYears[y]);
    });
    if (empty) empty.classList.toggle('is-shown', shown === 0);
    root.classList.toggle('is-filtered', domain !== 'all' || year !== 'all' || q !== '');
  }

  function activate(btns, attr, value) {
    btns.forEach(function (btn) {
      var on = btn.getAttribute(attr) === value;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  domainBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      domain = btn.getAttribute('data-cl-domain') || 'all';
      activate(domainBtns, 'data-cl-domain', domain);
      apply();
    });
  });
  yearBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      year = btn.getAttribute('data-cl-year') || 'all';
      activate(yearBtns, 'data-cl-year', year);
      apply();
    });
  });
  if (search) {
    search.addEventListener('input', function () {
      query = search.value || '';
      apply();
    });
  }

  if (lb && lbImg) {
    document.addEventListener('click', function (ev) {
      var t = ev.target;
      if (!t || !t.closest) return;
      var open = t.closest('[data-cl-img]');
      if (open) {
        ev.preventDefault();
        lbImg.src = open.getAttribute('data-cl-img') || '';
        lbImg.alt = open.getAttribute('data-cl-alt') || '';
        lb.hidden = false;
        document.body.style.overflow = 'hidden';
        return;
      }
      if (t.closest('[data-cl-lb-close]') || t === lb) {
        lb.hidden = true;
        lbImg.src = '';
        document.body.style.overflow = '';
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !lb.hidden) {
        lb.hidden = true;
        lbImg.src = '';
        document.body.style.overflow = '';
      }
    });
  }

  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
    root.classList.add('cl-io');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('cl-reveal');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    root.querySelectorAll('[data-cl-reveal]').forEach(function (el) { io.observe(el); });
  }
})();
