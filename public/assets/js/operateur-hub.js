/**
 * Outils opérateur (/operateur) et Mon identité (/operateur/identite).
 *
 * Page d'accueil : recherche d'un outil (touche « / »), épingles sans rechargement (repli : envoi du formulaire).
 * Mon identité : fenêtres (élément dialog), confirmation des actions définitives, recto/verso d'un papier, types de
 * papier selon le pays, étapes de création d'une couverture. Spécimen : impression et image PNG.
 * Sans JavaScript, tout reste utilisable : les fenêtres sont alors des blocs dans la page.
 */
(function () {
  'use strict';

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  // ---- Fenêtres
  var canDialog = typeof HTMLDialogElement !== 'undefined';
  if (canDialog) {
    document.querySelectorAll('dialog.opx-dialog').forEach(function (d) { d.classList.add('is-js'); });
    document.addEventListener('click', function (e) {
      var opener = e.target.closest('[data-opx-open]');
      if (opener) {
        var dialog = document.getElementById(opener.getAttribute('data-opx-open'));
        if (!dialog || typeof dialog.showModal !== 'function') { return; }
        e.preventDefault();
        // « champ:valeur » : présélection dans la fenêtre (ex. l'identité pour laquelle on demande un papier).
        var set = opener.getAttribute('data-opx-set');
        if (set && set.indexOf(':') !== -1) {
          var field = document.getElementById(set.split(':')[0]);
          if (field) { field.value = set.split(':')[1]; }
        }
        if (!dialog.open) { dialog.showModal(); }
        var href = opener.getAttribute('href');
        var target = href && href.charAt(0) === '#' ? document.getElementById(href.slice(1)) : null;
        if (target && dialog.contains(target) && typeof target.focus === 'function') { target.focus(); }
        return;
      }
      var closer = e.target.closest('[data-opx-close]');
      if (closer) {
        var host = closer.closest('dialog');
        if (host && host.open) { host.close(); }
        return;
      }
      // Clic sur le fond de la fenêtre : fermeture.
      if (e.target instanceof HTMLDialogElement && e.target.classList.contains('opx-dialog') && e.target.open) {
        var r = e.target.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) { e.target.close(); }
      }
    });
    var auto = document.querySelector('dialog.opx-dialog[data-opx-autoopen]');
    if (auto && typeof auto.showModal === 'function') { auto.showModal(); }
  }

  // ---- Recherche d'un outil
  var hub = document.querySelector('[data-opx-hub]');
  if (hub) {
    var input = hub.querySelector('[data-opx-search-input]');
    var noResult = hub.querySelector('[data-opx-noresult]');
    var favSection = hub.querySelector('[data-opx-favsection]');
    var favList = hub.querySelector('[data-opx-favlist]');
    var favEmpty = hub.querySelector('[data-opx-favempty]');
    var filter = function () {
      var q = input ? norm(input.value).trim() : '';
      var any = false;
      hub.querySelectorAll('[data-opx-section]').forEach(function (section) {
        var shown = 0;
        section.querySelectorAll('[data-opx-tool]').forEach(function (card) {
          var ok = q === '' || norm(card.getAttribute('data-opx-search')).indexOf(q) !== -1;
          card.hidden = !ok;
          if (ok) { shown++; }
        });
        section.hidden = shown === 0;
        if (shown > 0) { any = true; }
      });
      if (noResult) { noResult.hidden = q === '' || any; }
    };
    if (input) {
      input.addEventListener('input', filter);
      document.addEventListener('keydown', function (e) {
        var t = e.target;
        var typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);
        if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) {
          e.preventDefault();
          input.focus();
          input.select();
        } else if (e.key === 'Escape' && t === input && input.value !== '') {
          input.value = '';
          filter();
        }
      });
    }

    // ---- Épingles
    var setStar = function (key, on) {
      hub.querySelectorAll('[data-opx-tool="' + key + '"] .opx-pin__btn').forEach(function (btn) {
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.title = on ? 'Retirer des épinglés' : 'Épingler en haut de la page';
      });
    };
    hub.addEventListener('submit', function (e) {
      var form = e.target.closest('[data-opx-fav]');
      if (!form || !window.fetch) { return; }
      e.preventDefault();
      var key = form.closest('[data-opx-tool]').getAttribute('data-opx-tool');
      fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (res) {
          if (!res.ok || !res.body.ok) {
            window.alert(res.body && res.body.message ? res.body.message : 'Action impossible pour l’instant.');
            return;
          }
          var on = !!res.body.favorite;
          setStar(key, on);
          if (!favList) { return; }
          var pinned = favList.querySelector('[data-opx-tool="' + key + '"]');
          if (on && !pinned) {
            var source = hub.querySelector('[data-opx-tool="' + key + '"]');
            var copy = source.cloneNode(true);
            copy.hidden = false;
            favList.appendChild(copy);
          } else if (!on && pinned) {
            pinned.remove();
          }
          var has = favList.children.length > 0;
          if (favSection) { favSection.hidden = !has; }
          if (favEmpty) { favEmpty.hidden = has; }
        })
        .catch(function () { form.submit(); });
    });
  }

  // ---- Confirmation des actions définitives
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-opx-confirm]');
    if (form && !window.confirm(form.getAttribute('data-opx-confirm'))) {
      e.preventDefault();
    }
  });

  // ---- Recto / verso d'un papier
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-opx-flip]');
    if (!btn) { return; }
    var doc = btn.closest('[data-opx-doc]');
    var recto = doc.querySelector('[data-opx-face="recto"]');
    var verso = doc.querySelector('[data-opx-face="verso"]');
    var showVerso = verso.hidden;
    verso.hidden = !showVerso;
    recto.hidden = showVerso;
    btn.setAttribute('aria-pressed', showVerso ? 'true' : 'false');
    btn.textContent = showVerso ? 'Voir le recto' : 'Voir le verso';
  });

  // ---- Demande de papier : seuls les types délivrés par le pays choisi
  var req = document.querySelector('[data-opx-doc-request]');
  if (req) {
    var types = {};
    try { types = JSON.parse(req.getAttribute('data-opx-types') || '{}'); } catch (err) { types = {}; }
    var country = req.querySelector('[data-opx-req-country]');
    var type = req.querySelector('[data-opx-req-type]');
    var syncTypes = function () {
      var allowed = types[country.value] || null;
      var first = null;
      type.querySelectorAll('option').forEach(function (o) {
        var ok = !allowed || allowed.indexOf(o.value) !== -1;
        o.hidden = !ok;
        o.disabled = !ok;
        if (ok && first === null) { first = o.value; }
      });
      // Un groupe sans type délivré disparaît aussi.
      type.querySelectorAll('optgroup').forEach(function (g) {
        g.hidden = !g.querySelector('option:not([disabled])');
      });
      if (type.selectedOptions[0] && type.selectedOptions[0].disabled && first !== null) { type.value = first; }
    };
    if (country && type) {
      country.addEventListener('change', syncTypes);
      syncTypes();
    }
  }

  // ---- Création d'une couverture : le pays et le portrait ne servent que pour une personne fictive
  var coverForm = document.querySelector('[data-opx-cover-form]');
  if (coverForm) {
    var randomStep = coverForm.querySelector('[data-opx-cover-random]');
    var syncSource = function () {
      var checked = coverForm.querySelector('[data-opx-cover-source]:checked');
      if (randomStep) { randomStep.hidden = !!checked && checked.value === 'athena'; }
    };
    coverForm.addEventListener('change', syncSource);
    syncSource();
  }

  // ---- Spécimen : impression et image PNG
  var specimen = document.querySelector('[data-opx-specimen]');
  if (specimen) {
    var base = specimen.getAttribute('data-opx-specimen') || 'specimen';
    specimen.addEventListener('click', function (e) {
      if (e.target.closest('[data-opx-print]')) {
        window.print();
        return;
      }
      var btn = e.target.closest('[data-opx-png]');
      if (!btn) { return; }
      var side = btn.getAttribute('data-opx-png');
      var svg = document.querySelector('[data-opx-face="' + side + '"] svg');
      if (!svg) { return; }
      // Certains navigateurs ne dessinent un SVG sur un canevas que s'il porte une largeur et une hauteur.
      var copy = svg.cloneNode(true);
      copy.setAttribute('width', '856');
      copy.setAttribute('height', '540');
      var xml = new XMLSerializer().serializeToString(copy);
      var img = new Image();
      img.onload = function () {
        var canvas = document.createElement('canvas');
        canvas.width = 1712;
        canvas.height = 1080;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
          if (!blob) { return; }
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = base + '-' + side + '.png';
          document.body.appendChild(a);
          a.click();
          a.remove();
          setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
        }, 'image/png');
      };
      img.onerror = function () { window.alert('L’image n’a pas pu être préparée. Utilisez l’impression en PDF.'); };
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
    });
  }
})();
