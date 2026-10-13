/**
 * Back-office — page Configuration (views/admin/configuration.php).
 * Recherche de réglage, section active dans la navigation, copie du lien public,
 * suivi des modifications du formulaire (modifié / enregistré) et confirmations.
 * Les textes affichés viennent du HTML (traduits côté serveur), jamais du script.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-bo-cfg]');
  if (!root) {
    return;
  }

  function fold(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .replace(/[’']/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  /* ── Recherche ─────────────────────────────────────────────────── */
  var input = root.querySelector('[data-cfg-search-input]');
  var status = root.querySelector('[data-cfg-search-status]');
  var countEl = root.querySelector('[data-cfg-search-count]');
  var noResult = root.querySelector('[data-cfg-noresult]');
  var sections = Array.prototype.slice.call(root.querySelectorAll('[data-cfg-section]'));
  var navLinks = Array.prototype.slice.call(root.querySelectorAll('[data-cfg-nav]'));

  sections.forEach(function (sec) {
    sec._cfgItems = Array.prototype.slice.call(sec.querySelectorAll('.bo-cfg-item')).map(function (li) {
      return { el: li, text: fold((li.getAttribute('data-cfg-search') || '') + ' ' + li.textContent) };
    });
    sec._cfgText = fold((sec.getAttribute('data-cfg-search') || '') + ' ' + (sec.querySelector('.bo-cfg-section__head') || sec).textContent);
  });

  function navFor(id) {
    for (var i = 0; i < navLinks.length; i++) {
      if (navLinks[i].getAttribute('data-cfg-nav') === id) {
        return navLinks[i];
      }
    }
    return null;
  }

  function applySearch() {
    var q = fold(input ? input.value : '');
    var terms = q === '' ? [] : q.split(' ');
    var total = 0;

    sections.forEach(function (sec) {
      var id = sec.getAttribute('data-cfg-section');
      var link = navFor(id);
      var visible = 0;

      if (terms.length === 0) {
        sec.hidden = false;
        sec._cfgItems.forEach(function (it) { it.el.hidden = false; });
        if (link) {
          link.classList.remove('is-empty');
        }
        var c0 = root.querySelector('[data-cfg-nav-count="' + id + '"]');
        if (c0) {
          c0.textContent = String(sec._cfgItems.length);
        }
        return;
      }

      if (sec.hasAttribute('data-cfg-static')) {
        sec.hidden = true;
        if (link) {
          link.classList.add('is-empty');
        }
        return;
      }

      var matches = function (text) {
        return terms.every(function (t) { return text.indexOf(t) !== -1; });
      };

      if (sec._cfgItems.length > 0) {
        var sectionHit = matches(sec._cfgText);
        sec._cfgItems.forEach(function (it) {
          var hit = sectionHit || matches(it.text);
          it.el.hidden = !hit;
          if (hit) {
            visible++;
          }
        });
      } else if (matches(sec._cfgText + ' ' + fold(sec.textContent))) {
        visible = 1;
      }

      sec.hidden = visible === 0;
      total += visible;
      if (link) {
        link.classList.toggle('is-empty', visible === 0);
      }
      var c = root.querySelector('[data-cfg-nav-count="' + id + '"]');
      if (c) {
        c.textContent = String(sec._cfgItems.length > 0 ? visible : 0);
      }
    });

    Array.prototype.forEach.call(root.querySelectorAll('[data-cfg-hide-on-search]'), function (el) {
      el.hidden = terms.length > 0;
    });
    if (status) {
      status.hidden = terms.length === 0;
    }
    if (countEl) {
      countEl.textContent = String(total);
    }
    if (noResult) {
      noResult.hidden = terms.length === 0 || total > 0;
    }
  }

  if (input) {
    input.addEventListener('input', applySearch);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        input.value = '';
        applySearch();
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) {
        return;
      }
      var t = e.target;
      var tag = t && t.tagName ? t.tagName.toLowerCase() : '';
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || (t && t.isContentEditable)) {
        return;
      }
      e.preventDefault();
      input.focus();
      input.select();
    });
  }
  var clearBtn = root.querySelector('[data-cfg-search-clear]');
  if (clearBtn && input) {
    clearBtn.addEventListener('click', function () {
      input.value = '';
      applySearch();
      input.focus();
    });
  }

  /* ── Section active dans la navigation ─────────────────────────── */
  function setActive(id) {
    navLinks.forEach(function (a) {
      var on = a.getAttribute('data-cfg-nav') === id;
      a.classList.toggle('is-active', on);
      if (on) {
        a.setAttribute('aria-current', 'true');
        var nav = a.closest('.bo-cfg-nav');
        if (nav && nav.scrollWidth > nav.clientWidth) {
          var left = a.offsetLeft - (nav.clientWidth - a.offsetWidth) / 2;
          nav.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
        }
      } else {
        a.removeAttribute('aria-current');
      }
    });
  }

  if ('IntersectionObserver' in window && sections.length > 0) {
    var ratios = {};
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        ratios[en.target.getAttribute('data-cfg-section')] = en.isIntersecting ? en.intersectionRect.top : null;
      });
      var best = null;
      sections.forEach(function (sec) {
        var id = sec.getAttribute('data-cfg-section');
        if (best === null && ratios[id] !== null && ratios[id] !== undefined && !sec.hidden) {
          best = id;
        }
      });
      if (best) {
        setActive(best);
      }
    }, { rootMargin: '-90px 0px -55% 0px', threshold: 0 });
    sections.forEach(function (sec) { io.observe(sec); });
  }
  navLinks.forEach(function (a) {
    a.addEventListener('click', function () {
      setActive(a.getAttribute('data-cfg-nav'));
    });
  });
  setActive((location.hash || '#cfg-apercu').replace('#cfg-', '') || 'apercu');

  /* ── Copie du lien public ──────────────────────────────────────── */
  var copiedText = root.querySelector('[data-cfg-copied-label]');
  Array.prototype.forEach.call(root.querySelectorAll('[data-cfg-copy]'), function (btn) {
    btn.addEventListener('click', function () {
      var value = btn.getAttribute('data-cfg-copy') || '';
      var label = btn.querySelector('[data-cfg-copy-label]');
      var done = function () {
        if (!label) {
          return;
        }
        var old = label.textContent;
        label.textContent = copiedText ? copiedText.textContent : old;
        btn.classList.add('is-done');
        setTimeout(function () {
          label.textContent = old;
          btn.classList.remove('is-done');
        }, 1800);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done, function () {});
      } else {
        var ta = document.createElement('textarea');
        ta.value = value;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
      }
    });
  });

  /* ── Formulaires : modifié / enregistré ────────────────────────── */
  Array.prototype.forEach.call(root.querySelectorAll('[data-cfg-form]'), function (form) {
    var fields = Array.prototype.slice.call(form.querySelectorAll('[data-cfg-field]'));
    var bar = form.querySelector('[data-cfg-savebar]');
    var submit = form.querySelector('[data-cfg-submit]');
    var resetBtn = form.querySelector('[data-cfg-reset]');
    var defaultsBtn = form.querySelector('[data-cfg-defaults]');
    var dirtyCount = form.querySelector('[data-cfg-dirty-count]');
    var initialState = bar ? bar.getAttribute('data-state') : 'clean';
    var submitting = false;

    function read(field) {
      var box = field.querySelector('input[type="checkbox"]');
      if (box) {
        return box.checked ? '1' : '0';
      }
      var r = field.querySelector('input[type="radio"]:checked');
      return r ? r.value : '';
    }

    function write(field, value) {
      var box = field.querySelector('input[type="checkbox"]');
      if (box) {
        box.checked = value === '1';
        return;
      }
      Array.prototype.forEach.call(field.querySelectorAll('input[type="radio"]'), function (r) {
        r.checked = r.value === value;
      });
    }

    fields.forEach(function (f) { f._cfgInitial = read(f); });

    function refresh() {
      var n = 0;
      fields.forEach(function (f) {
        var dirty = read(f) !== f._cfgInitial;
        f.classList.toggle('is-dirty', dirty);
        var badge = f.querySelector('[data-cfg-dirty-badge]');
        if (badge) {
          badge.hidden = !dirty;
        }
        if (dirty) {
          n++;
        }
      });
      if (dirtyCount) {
        dirtyCount.textContent = String(n);
      }
      if (bar) {
        bar.setAttribute('data-state', n > 0 ? 'dirty' : initialState);
      }
      if (submit) {
        submit.disabled = n === 0;
      }
      if (resetBtn) {
        resetBtn.disabled = n === 0;
      }
      if (defaultsBtn) {
        defaultsBtn.disabled = fields.every(function (f) {
          return !f.hasAttribute('data-cfg-default') || read(f) === f.getAttribute('data-cfg-default');
        });
      }
      form._cfgDirty = n > 0;
    }

    form.addEventListener('change', refresh);
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        fields.forEach(function (f) { write(f, f._cfgInitial); });
        refresh();
      });
    }
    if (defaultsBtn) {
      defaultsBtn.addEventListener('click', function () {
        fields.forEach(function (f) {
          if (f.hasAttribute('data-cfg-default')) {
            write(f, f.getAttribute('data-cfg-default'));
          }
        });
        refresh();
      });
    }
    form.addEventListener('submit', function () {
      submitting = true;
      if (bar) {
        bar.classList.add('is-busy');
      }
    });
    window.addEventListener('beforeunload', function (e) {
      if (form._cfgDirty && !submitting) {
        e.preventDefault();
        e.returnValue = form.getAttribute('data-cfg-confirm-leave') || '';
        return e.returnValue;
      }
      return undefined;
    });
    refresh();
  });

  /* ── Confirmation des actions sensibles ────────────────────────── */
  Array.prototype.forEach.call(root.querySelectorAll('form[data-confirm]'), function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm') || '')) {
        e.preventDefault();
      }
    });
  });
})();
