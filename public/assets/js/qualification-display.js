/*
 * Affichage des qualifications : interrupteurs « Visible sur Arma » / « Visible sur le profil »
 * (views/partials/qualification_display.php). Enregistrement immediat, sans recharger la page ;
 * si le serveur refuse, l'interrupteur revient a son etat reel et la raison s'affiche sur la ligne.
 * Les messages sont dans la page (elements [data-qd-msg]) : aucun texte ici.
 */
(function () {
  'use strict';

  function init(root) {
    var url = root.getAttribute('data-qd-url') || '';
    var csrf = root.getAttribute('data-qd-csrf') || '';
    if (!url) {
      return;
    }

    function message(code) {
      var el = root.querySelector('[data-qd-msg="' + code + '"]') || root.querySelector('[data-qd-msg="error"]');
      return el ? el.textContent : '';
    }

    function switches(surface) {
      return Array.prototype.slice.call(root.querySelectorAll('[data-qd-switch="' + surface + '"]'));
    }

    function rowOf(sw) {
      return sw.closest('[data-qd-row]');
    }

    function paint(sw, visible) {
      sw.setAttribute('aria-checked', visible ? 'true' : 'false');
      var row = rowOf(sw);
      var id = row ? row.getAttribute('data-qd-row') : '';
      var flags = document.querySelectorAll('[data-qd-flag="' + id + ':' + sw.getAttribute('data-qd-switch') + '"]');
      Array.prototype.forEach.call(flags, function (flag) {
        flag.hidden = visible;
      });
    }

    function say(target, text, isError) {
      if (!target) {
        return;
      }
      target.textContent = text;
      target.classList.toggle('is-error', !!isError);
      if (target._qdTimer) {
        clearTimeout(target._qdTimer);
      }
      if (!isError && text) {
        target._qdTimer = setTimeout(function () {
          target.textContent = '';
        }, 2500);
      }
    }

    function send(awardId, surface, visible) {
      var body = new FormData();
      body.append('_csrf_token', csrf);
      body.append('qualification_id', awardId);
      body.append('surface', surface);
      body.append('visible', visible ? '1' : '0');
      return fetch(url, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (res) {
        return res.json().catch(function () {
          return { ok: false, code: 'error' };
        });
      }).catch(function () {
        return { ok: false, code: 'error' };
      });
    }

    // Etat reel renvoye par le serveur : { id: { game: bool, profile: bool } }.
    function applyStates(states) {
      Object.keys(states || {}).forEach(function (id) {
        var row = root.querySelector('[data-qd-row="' + id + '"]');
        if (!row) {
          return;
        }
        Array.prototype.forEach.call(row.querySelectorAll('[data-qd-switch]'), function (sw) {
          var value = states[id][sw.getAttribute('data-qd-switch')];
          if (typeof value === 'boolean') {
            paint(sw, value);
          }
        });
      });
    }

    function busy(list, on) {
      list.forEach(function (el) {
        if (on) {
          el.setAttribute('aria-busy', 'true');
        } else {
          el.removeAttribute('aria-busy');
        }
      });
    }

    root.addEventListener('click', function (event) {
      var sw = event.target.closest('[data-qd-switch]');
      if (sw && root.contains(sw)) {
        if (sw.disabled || sw.getAttribute('aria-busy') === 'true') {
          return;
        }
        var row = rowOf(sw);
        var before = sw.getAttribute('aria-checked') === 'true';
        var state = row ? row.querySelector('[data-qd-state]') : null;
        paint(sw, !before);
        busy([sw], true);
        say(state, '', false);
        send(row.getAttribute('data-qd-row'), sw.getAttribute('data-qd-switch'), !before).then(function (data) {
          busy([sw], false);
          if (data && data.ok) {
            applyStates(data.states);
            say(state, message('saved'), false);
          } else {
            paint(sw, before);
            say(state, message((data && data.code) || 'error'), true);
          }
        });
        return;
      }

      var bulk = event.target.closest('[data-qd-bulk]');
      if (bulk && root.contains(bulk)) {
        var surface = bulk.getAttribute('data-qd-bulk');
        var visible = bulk.getAttribute('data-qd-visible') === '1';
        var list = switches(surface);
        var status = root.querySelector('[data-qd-status]');
        if (list.some(function (el) { return el.getAttribute('aria-busy') === 'true'; })) {
          return;
        }
        var previous = list.map(function (el) { return el.getAttribute('aria-checked') === 'true'; });
        list.forEach(function (el) { paint(el, visible); });
        busy(list, true);
        bulk.disabled = true;
        say(status, '', false);
        send('all', surface, visible).then(function (data) {
          busy(list, false);
          bulk.disabled = false;
          if (data && data.ok) {
            applyStates(data.states);
            say(status, message('saved_all'), false);
          } else {
            list.forEach(function (el, i) { paint(el, previous[i]); });
            say(status, message((data && data.code) || 'error'), true);
          }
        });
      }
    });
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-qd]'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
