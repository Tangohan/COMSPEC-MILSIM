/**
 * Tableau de bord : fenêtre modale des documents à signer.
 * S’ouvre à l’arrivée tant qu’une signature est attendue (une fois par session après « Plus tard »),
 * ou depuis le bouton « Signer » d’une ligne. La signature appelle /documents/doctrine/{id}/acknowledge.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-dash-sign-root]');
  var modal = document.querySelector('[data-dash-sign-modal]');
  if (!root || !modal) return;

  var items = [];
  try { items = JSON.parse(root.getAttribute('data-items') || '[]'); } catch (e) { items = []; }
  if (!items.length) return;

  var csrf = root.getAttribute('data-csrf') || '';
  var certifyText = root.getAttribute('data-certify') || '';
  var list = modal.querySelector('[data-dash-sign-modal-list]');
  var progress = modal.querySelector('[data-dash-sign-progress]');
  var titleEl = modal.querySelector('[data-dash-sign-modal-title]');
  var leadEl = modal.querySelector('[data-dash-sign-modal-lead]');
  var panel = modal.querySelector('.dash-sign-modal__panel');
  var signed = {};
  var lastFocus = null;
  var sessionKey = 'athena_dash_sign_later_' + items.map(function (i) { return i.version_id; }).join('-');

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }

  function safeTone(t) { return String(t || 'neutral').replace(/[^a-z]/g, '') || 'neutral'; }

  function updateProgress() {
    var done = items.filter(function (i) { return signed[i.id]; }).length;
    if (progress) {
      progress.textContent = done === items.length
        ? 'Toutes les signatures sont enregistrées.'
        : done + ' / ' + items.length + ' signé' + (done > 1 ? 's' : '');
    }
    if (done === items.length) {
      if (titleEl) titleEl.textContent = 'Signatures enregistrées';
      if (leadEl) leadEl.textContent = 'Merci. Vos prises en compte sont horodatées et rattachées à chaque version.';
    }
  }

  function markRowSigned(id) {
    var row = root.querySelector('[data-dash-sign-item="' + id + '"]');
    if (!row) return;
    row.className = row.className.replace(/dash-sign__item--[a-z]+/, 'dash-sign__item--emerald');
    var chip = row.querySelector('[data-dash-sign-chip]');
    if (chip) {
      chip.textContent = 'Pris en compte';
      chip.className = 'dash-sign__chip dash-sign__chip--emerald';
    }
    var btn = row.querySelector('[data-dash-sign-open]');
    if (btn) btn.remove();
  }

  function buildItem(item) {
    var li = el('li', 'dash-sign-modal__item dash-sign-modal__item--' + safeTone(item.tone));
    li.setAttribute('data-dash-sign-modal-item', String(item.id));

    var head = el('div', 'dash-sign-modal__item-head');
    var meta = el('p', 'dash-sign__meta');
    if (item.reference) meta.appendChild(el('code', 'dash-sign__ref', item.reference));
    if (item.version) meta.appendChild(el('span', 'dash-sign__version', item.version));
    head.appendChild(meta);
    head.appendChild(el('span', 'dash-sign__chip dash-sign__chip--' + safeTone(item.tone), item.status));
    li.appendChild(head);

    li.appendChild(el('p', 'dash-sign-modal__doc', item.title));
    var hint = item.hint + (item.deadline ? ' ' + item.deadline + '.' : '');
    li.appendChild(el('p', 'dash-sign__hint', hint));

    var form = el('form', 'dash-sign-modal__form');
    form.noValidate = true;
    var label = el('label', 'dash-sign-modal__check');
    var box = document.createElement('input');
    box.type = 'checkbox';
    box.name = 'certify';
    box.value = '1';
    label.appendChild(box);
    label.appendChild(el('span', '', certifyText));
    form.appendChild(label);

    var status = el('p', 'dash-sign-modal__status');
    status.hidden = true;

    var actions = el('div', 'dash-sign__actions');
    var read = el('a', 'dash-sign__btn dash-sign__btn--ghost', 'Consulter');
    read.href = item.href;
    read.target = '_blank';
    read.rel = 'noopener';
    var submit = el('button', 'dash-sign__btn dash-sign__btn--primary', 'Signer');
    submit.type = 'submit';
    submit.disabled = true;
    actions.appendChild(read);
    actions.appendChild(submit);
    form.appendChild(actions);
    form.appendChild(status);

    box.addEventListener('change', function () { submit.disabled = !box.checked; });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!box.checked || submit.disabled) return;
      submit.disabled = true;
      status.hidden = false;
      status.className = 'dash-sign-modal__status';
      status.textContent = 'Enregistrement…';
      var fd = new FormData();
      fd.append('csrf_token', csrf);
      fd.append('version_id', String(item.version_id));
      fd.append('certify', '1');
      fetch(item.ack_url, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: fd,
        credentials: 'same-origin'
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, j: j }; }, function () { return { ok: false, j: null }; });
      }).then(function (x) {
        if (x.ok && x.j && x.j.success) {
          signed[item.id] = true;
          li.className = 'dash-sign-modal__item dash-sign-modal__item--emerald is-signed';
          form.replaceWith(el('p', 'dash-sign-modal__done', 'Signé le ' + new Date().toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) + '.'));
          var chip = li.querySelector('.dash-sign__chip');
          if (chip) { chip.textContent = 'Pris en compte'; chip.className = 'dash-sign__chip dash-sign__chip--emerald'; }
          markRowSigned(item.id);
          updateProgress();
        } else {
          submit.disabled = false;
          status.className = 'dash-sign-modal__status is-err';
          status.textContent = (x.j && x.j.error) ? x.j.error : 'Échec de l’enregistrement. Réessayez.';
        }
      }).catch(function () {
        submit.disabled = false;
        status.className = 'dash-sign-modal__status is-err';
        status.textContent = 'Erreur réseau. Réessayez.';
      });
    });

    li.appendChild(form);
    return li;
  }

  items.forEach(function (item) { list.appendChild(buildItem(item)); });
  updateProgress();

  function open(focusId) {
    lastFocus = document.activeElement;
    modal.hidden = false;
    document.documentElement.classList.add('dash-sign-lock');
    requestAnimationFrame(function () { modal.classList.add('is-open'); });
    var target = focusId ? modal.querySelector('[data-dash-sign-modal-item="' + focusId + '"]') : null;
    if (target) {
      target.scrollIntoView({ block: 'nearest' });
      target.classList.add('is-focus');
      setTimeout(function () { target.classList.remove('is-focus'); }, 1600);
      var cb = target.querySelector('input[type=checkbox]');
      if (cb) { try { cb.focus({ preventScroll: true }); } catch (e) { cb.focus(); } return; }
    }
    try { panel.focus({ preventScroll: true }); } catch (e) { panel.focus(); }
  }

  function close() {
    modal.classList.remove('is-open');
    document.documentElement.classList.remove('dash-sign-lock');
    setTimeout(function () { modal.hidden = true; }, 180);
    var remaining = items.some(function (i) { return !signed[i.id]; });
    if (remaining) {
      try { sessionStorage.setItem(sessionKey, '1'); } catch (e) {}
    }
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  modal.querySelectorAll('[data-dash-sign-close]').forEach(function (b) {
    b.addEventListener('click', close);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden) close();
  });
  root.querySelectorAll('[data-dash-sign-open]').forEach(function (b) {
    b.addEventListener('click', function () { open(b.getAttribute('data-dash-sign-open')); });
  });

  var later = false;
  try { later = sessionStorage.getItem(sessionKey) === '1'; } catch (e) { later = false; }
  if (!later) setTimeout(function () { open(null); }, 450);
})();
