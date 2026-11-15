/**
 * JNET — extranet du membre : onglets de rubriques pilotés par l'ancre de l'adresse, et recherche des unités.
 * Amélioration progressive : sans ce script, toutes les rubriques restent affichées l'une sous l'autre.
 */
(function () {
  'use strict';

  var nav = document.querySelector('[data-hb-tabs]');
  var tabs = nav ? [].slice.call(nav.querySelectorAll('[data-hb-tab]')) : [];
  var panels = [];
  tabs.forEach(function (t) {
    var p = document.getElementById((t.getAttribute('href') || '').slice(1));
    if (p) {
      panels.push(p);
    }
  });

  if (nav && tabs.length > 1 && panels.length === tabs.length) {
    document.documentElement.classList.add('hb-js');
    nav.setAttribute('role', 'tablist');
    var ids = panels.map(function (p) { return p.id; });
    tabs.forEach(function (t, i) {
      t.id = t.id || 'hb-tab-' + i;
      t.setAttribute('role', 'tab');
      // L'ancre ne désigne plus aucun élément : le navigateur ne saute donc pas à la rubrique, qui est déjà affichée.
      panels[i].id = ids[i] + '-panel';
      t.setAttribute('aria-controls', panels[i].id);
      panels[i].setAttribute('role', 'tabpanel');
      panels[i].setAttribute('aria-labelledby', t.id);
      panels[i].setAttribute('tabindex', '-1');
    });

    var aliases = { 'hb-organisation': 'hb-autres', 'hb-unites': 'hb-autres' };

    var show = function (id, focusTab) {
      var at = ids.indexOf(id);
      if (at < 0) {
        at = 0;
      }
      tabs.forEach(function (t, i) {
        var on = i === at;
        t.classList.toggle('is-on', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.setAttribute('tabindex', on ? '0' : '-1');
        panels[i].classList.toggle('is-on', on);
      });
      if (focusTab) {
        tabs[at].focus();
      }
    };

    var fromHash = function () {
      var h = decodeURIComponent((location.hash || '').slice(1));
      return aliases[h] || h;
    };

    tabs.forEach(function (t, i) {
      t.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) {
          return;
        }
        e.preventDefault();
        if (fromHash() !== ids[i] && window.history && history.pushState) {
          history.pushState(null, '', '#' + ids[i]);
        }
        show(ids[i], false);
      });
      t.addEventListener('keydown', function (e) {
        var to = -1;
        if (e.key === 'ArrowRight') { to = (i + 1) % tabs.length; }
        else if (e.key === 'ArrowLeft') { to = (i - 1 + tabs.length) % tabs.length; }
        else if (e.key === 'Home') { to = 0; }
        else if (e.key === 'End') { to = tabs.length - 1; }
        if (to < 0) {
          return;
        }
        e.preventDefault();
        if (window.history && history.replaceState) {
          history.replaceState(null, '', '#' + ids[to]);
        }
        show(ids[to], true);
      });
    });

    var sync = function () { show(fromHash(), false); };
    window.addEventListener('hashchange', sync);
    window.addEventListener('popstate', sync);
    sync();
  }

  // Recherche et filtre des unités (« Autres unités »).
  var box = document.querySelector('[data-hb-filter]');
  if (!box) {
    return;
  }
  var units = [].slice.call(document.querySelectorAll('[data-hb-unit]'));
  var input = box.querySelector('[data-hb-q]');
  var chips = [].slice.call(box.querySelectorAll('[data-hb-chip]'));
  var none = document.querySelector('[data-hb-none]');
  var mode = 'all';

  var apply = function () {
    var q = (input.value || '').toLowerCase().trim();
    var shown = 0;
    units.forEach(function (u) {
      var ok = (q === '' || (u.getAttribute('data-q') || '').indexOf(q) !== -1)
        && (mode === 'all' || (mode === 'mine' && u.getAttribute('data-mine') === '1') || (mode === 'unread' && Number(u.getAttribute('data-unread')) > 0) || (mode === 'linked' && u.getAttribute('data-linked') === '1'));
      u.hidden = !ok;
      if (ok) {
        shown++;
      }
    });
    if (none) {
      none.hidden = shown > 0;
    }
  };

  box.hidden = false;
  input.addEventListener('input', apply);
  chips.forEach(function (c) {
    c.addEventListener('click', function () {
      mode = c.getAttribute('data-hb-chip');
      chips.forEach(function (o) {
        var on = o === c;
        o.classList.toggle('is-on', on);
        o.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      apply();
    });
  });
})();
