/*
 * Marques « équipe Athena » sur tout le site : aigle + nom en rouge pour l'administration de la plateforme,
 * casque + nom en vert pour le support. Les correspondances viennent de #ath-staff-map (ids de comptes, slugs de fiche) ;
 * on décore les liens vers une fiche (/personnel/…, /membres/{id}, fiche effectifs et compte du back-office)
 * et les éléments portant data-user-id.
 * Les noms déjà marqués côté serveur (StaffMarkService::name) portent data-staff-done.
 * Ajouter data-staff-skip sur un conteneur pour l'exclure.
 */
(function () {
  'use strict';
  const el = document.getElementById('ath-staff-map');
  if (!el) return;
  let map;
  try { map = JSON.parse(el.textContent || '{}'); } catch (e) { return; }
  const U = map.u || {}, S = map.s || {};
  const EN = /^en\b/i.test(document.documentElement.lang || '');
  const LABEL = EN
    ? { admin: 'Athena administrator', support: 'Athena support' }
    : { admin: 'Administrateur Athena', support: 'Support Athena' };
  const RESERVED = new Set(['me', 'kit-rubans-medailles', 'tutorials', 'mon-espace-rh', 'nouveau', 'create']);
  const ICON = {
    admin: '<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10.4 8.6L1 5.2L2.9 7.9L1.2 8.4L3.9 10.1L2.6 10.9L5.5 12.1L4.6 12.9L8.1 13.4L10.3 12.2Z"/><path fill="currentColor" d="M13.6 8.6L23 5.2L21.1 7.9L22.8 8.4L20.1 10.1L21.4 10.9L18.5 12.1L19.4 12.9L15.9 13.4L13.7 12.2Z"/><path fill="currentColor" d="M12 3.1a2.1 2.1 0 0 0-2.1 2.1c0 .6.2 1.1.6 1.5-.8.8-1.2 2.2-1.2 4 0 1.6.4 2.8 1 3.6L9 19.2l1.6-1 1.4 2.5 1.4-2.5 1.6 1-1.3-4.9c.6-.8 1-2 1-3.6 0-1.8-.4-3.2-1.2-4l.6-.4 1.6-.3-1.6-.6A2.1 2.1 0 0 0 12 3.1Z"/></svg>',
    support: '<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.5a8.5 8.5 0 0 0-8.5 8.5v5.2c0 1.3 1 2.3 2.3 2.3h1.4c.6 0 1-.4 1-1v-5.4c0-.6-.4-1-1-1H5.6V11a6.4 6.4 0 0 1 12.8 0v.1h-1.6c-.6 0-1 .4-1 1v5.4c0 .6.4 1 1 1h1.5c-.4 1.1-1.5 1.8-2.7 1.8h-2c-.2-.6-.8-1-1.4-1h-.7a1.5 1.5 0 0 0 0 3h.7c.6 0 1.2-.4 1.4-1h2a5 5 0 0 0 4.9-4.1c.6-.4 1-1.1 1-1.9V11A8.5 8.5 0 0 0 12 2.5Z"/></svg>'
  };

  const kindForPath = (path) => {
    let m = path.match(/\/personnel\/([^/?#]+)\/?$/);
    if (m) {
      const key = decodeURIComponent(m[1]);
      if (RESERVED.has(key)) return null;
      return /^\d+$/.test(key) ? U[key] || null : S[key] || null;
    }
    // Fiche membre (site), fiche effectifs et compte (back-office) : l'id est celui du compte.
    m = path.match(/\/(?:membres|back-office\/users|back-office\/ressources\/effectifs\/membres)\/(\d+)\/?$/);
    return m ? U[m[1]] || null : null;
  };
  const isTextOnly = (node) => {
    const t = (node.textContent || '').trim();
    return t.length > 0 && t.length <= 80 && !node.querySelector('img, svg, picture, video, div, p, section, article, h1, h2, h3, h4, li, table, input, select, button');
  };
  const isAction = (node) => node.matches('button, form, .btn, [class*="btn-"], [class*="-btn"], [class*="button"], [role="button"], [role="menuitem"], [role="tab"], nav a');

  const badge = (kind) => {
    const s = document.createElement('span');
    s.className = 'ath-staff-mark ath-staff-mark--' + kind;
    s.title = LABEL[kind];
    s.setAttribute('role', 'img');
    s.setAttribute('aria-label', LABEL[kind]);
    s.innerHTML = ICON[kind];
    return s;
  };
  const mark = (node, kind) => {
    node.dataset.athStaff = kind;
    if (node.hasAttribute('data-staff-done') || node.querySelector('.ath-staff-mark, [data-staff-done]')) return;
    const next = node.nextElementSibling;
    if (next && next.classList.contains('ath-staff-mark')) return;
    if (isTextOnly(node)) {
      if (isAction(node)) return;
      node.classList.add('is-staff', 'is-staff--' + kind);
      node.after(badge(kind));
    } else if (node.querySelector('img')) {
      node.classList.add('ath-staff-ring', 'ath-staff-ring--' + kind);
      if (!node.title) node.title = LABEL[kind];
    }
  };

  const scan = (root) => {
    if (!root || !root.querySelectorAll) return;
    root.querySelectorAll('a[href]:not([data-ath-staff])').forEach((a) => {
      if (a.closest('[data-staff-skip]')) return;
      let path;
      try { path = new URL(a.getAttribute('href'), location.href).pathname; } catch (e) { return; }
      const kind = kindForPath(path);
      if (kind) mark(a, kind);
    });
    root.querySelectorAll('[data-user-id]:not([data-ath-staff])').forEach((n) => {
      const k = U[n.getAttribute('data-user-id')];
      if (k && !n.closest('[data-staff-skip]') && n.tagName !== 'BODY' && n.tagName !== 'HTML' && n.tagName !== 'OPTION') mark(n, k);
    });
  };

  const start = () => {
    scan(document.body);
    // Contenus chargés après coup (listes filtrées, messagerie, flux).
    let pending = false;
    new MutationObserver((muts) => {
      if (pending) return;
      if (!muts.some((m) => m.addedNodes.length)) return;
      pending = true;
      requestAnimationFrame(() => { pending = false; scan(document.body); });
    }).observe(document.body, { childList: true, subtree: true });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
