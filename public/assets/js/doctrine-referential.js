(function () {
  'use strict';

  var table = document.querySelector('[data-doctrine-table]');
  if (table) {
    var tbody = table.querySelector('tbody');
    var headers = table.querySelectorAll('th[data-sort]');
    headers.forEach(function (th, colIndex) {
      th.addEventListener('click', function () {
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        var asc = th.getAttribute('data-sort-dir') !== 'asc';
        th.setAttribute('data-sort-dir', asc ? 'asc' : 'desc');
        rows.sort(function (a, b) {
          var av = (a.children[colIndex] && a.children[colIndex].textContent) || '';
          var bv = (b.children[colIndex] && b.children[colIndex].textContent) || '';
          return asc ? av.localeCompare(bv, 'fr') : bv.localeCompare(av, 'fr');
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
      });
    });
  }

  var modal = document.querySelector('[data-doctrine-ack-modal]');
  if (modal) {
    modal.hidden = false;
    var form = modal.querySelector('[data-doctrine-ack-form]');
    var status = modal.querySelector('[data-doctrine-ack-status]');
    modal.querySelectorAll('[data-doctrine-ack-close]').forEach(function (el) {
      el.addEventListener('click', function () {
        /* Modal obligatoire : pas de fermeture sans signature */
      });
    });
    var certify = modal.querySelector('[data-doctrine-ack-certify]');
    var submit = modal.querySelector('[data-doctrine-ack-submit]');
    function syncSubmit() {
      if (submit) {
        submit.disabled = !(certify && certify.checked);
      }
    }
    if (certify) {
      certify.addEventListener('change', syncSubmit);
      syncSubmit();
    }
    var panel = modal.querySelector('.doctrine-ack-modal__panel');
    if (panel && typeof panel.focus === 'function') {
      panel.setAttribute('tabindex', '-1');
      try { panel.focus({ preventScroll: true }); } catch (err) { panel.focus(); }
    }
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var endpoint = form.getAttribute('data-endpoint') || '';
        var fd = new FormData(form);
        if (!fd.get('certify')) {
          if (status) {
            status.hidden = false;
            status.textContent = 'Cochez la case de certification.';
            status.className = 'doctrine-ack-modal__status is-err';
          }
          return;
        }
        if (status) {
          status.hidden = false;
          status.textContent = 'Enregistrement…';
          status.className = 'doctrine-ack-modal__status is-ok';
        }
        fetch(endpoint, {
          method: 'POST',
          headers: { Accept: 'application/json' },
          body: fd,
          credentials: 'same-origin'
        }).then(function (r) {
          return r.json().then(function (j) { return { ok: r.ok, j: j }; });
        }).then(function (x) {
          if (x.ok && x.j && x.j.success) {
            if (status) {
              status.textContent = 'Prise en compte enregistrée.';
              status.className = 'doctrine-ack-modal__status is-ok';
            }
            modal.hidden = true;
            window.location.reload();
          } else if (status) {
            status.textContent = (x.j && x.j.error) ? x.j.error : 'Échec de l’enregistrement.';
            status.className = 'doctrine-ack-modal__status is-err';
          }
        }).catch(function () {
          if (status) {
            status.textContent = 'Erreur réseau.';
            status.className = 'doctrine-ack-modal__status is-err';
          }
        });
      });
    }
  }
})();
