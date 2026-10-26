/*
 * Marques « donateur » et « communauté soutien » sur tout le site.
 * Les correspondances viennent de #ath-donor-map (ids de comptes, slugs de fiche, communautés) ;
 * on décore les liens vers une fiche (/personnel/…, /membres/{id}), une communauté (/c/{slug})
 * et les éléments portant data-user-id / data-tenant-id. Ajouter data-donor-skip sur un conteneur pour l'exclure.
 */
(function () {
  'use strict';
  const el = document.getElementById('ath-donor-map');
  if (!el) return;
  let map;
  try { map = JSON.parse(el.textContent || '{}'); } catch (e) { return; }
  const U = map.u || {}, S = map.s || {}, T = map.t || {}, TS = map.ts || {};
  const LABEL = { soutien: 'Soutien', allie: 'Allié', pilier: 'Pilier' };
  const KIND = { plan: 'Formule soutien', heart: 'Soutien du cœur', donation: 'Communauté donatrice' };
  const RESERVED = new Set(['me', 'kit-rubans-medailles', 'tutorials', 'mon-espace-rh', 'nouveau', 'create']);
  const HEART = '<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.2-7.5 10.2Z" fill="currentColor"/></svg>';

  const tierForPath = (path) => {
    let m = path.match(/\/personnel\/([^/?#]+)\/?$/);
    if (m) {
      const key = decodeURIComponent(m[1]);
      if (RESERVED.has(key)) return null;
      return /^\d+$/.test(key) ? U[key] || null : S[key] || null;
    }
    m = path.match(/\/membres\/(\d+)\/?$/);
    return m ? U[m[1]] || null : null;
  };
  const kindForPath = (path) => {
    const m = path.match(/\/c\/([^/?#]+)\/?$/);
    return m ? TS[decodeURIComponent(m[1])] || null : null;
  };
  const isTextOnly = (node) => !node.querySelector('img, svg, picture, video, div, p, section, article, h1, h2, h3, h4, li, table') && (node.textContent || '').trim().length > 0 && (node.textContent || '').trim().length <= 80;

  const markUser = (node, tier) => {
    node.dataset.athDonor = tier;
    if (node.classList.contains('is-donor') || node.querySelector('.ath-donor-mark, .is-donor')) return;
    if (isTextOnly(node)) {
      node.classList.add('is-donor', 'is-donor--' + tier);
      const s = document.createElement('span');
      s.className = 'ath-donor-mark ath-donor-mark--' + tier;
      s.title = 'Donateur Athena · ' + (LABEL[tier] || tier);
      s.innerHTML = HEART + '<span class="ath-donor-mark__sr">' + s.title + '</span>';
      node.after(s);
    } else if (node.querySelector('img')) {
      node.classList.add('ath-donor-ring', 'ath-donor-ring--' + tier);
      node.title = node.title || 'Donateur Athena · ' + (LABEL[tier] || tier);
    } else {
      node.classList.add('ath-donor-card', 'ath-donor-card--' + tier);
    }
  };
  const markTenant = (node, kind) => {
    node.dataset.athSupporter = kind;
    if (node.querySelector('.ath-supporter-mark') || node.closest('.cr-card--supporter')) return;
    if (isTextOnly(node)) {
      const s = document.createElement('span');
      s.className = 'ath-supporter-mark';
      s.title = 'Soutient Athena · ' + (KIND[kind] || '');
      s.innerHTML = HEART + '<span class="ath-donor-mark__sr">' + s.title + '</span>';
      node.after(s);
    } else {
      node.classList.add('ath-supporter-card');
    }
  };

  const scan = (root) => {
    if (!root || !root.querySelectorAll) return;
    root.querySelectorAll('a[href]:not([data-ath-donor]):not([data-ath-supporter])').forEach((a) => {
      if (a.closest('[data-donor-skip]')) return;
      let path;
      try { path = new URL(a.getAttribute('href'), location.href).pathname; } catch (e) { return; }
      const tier = tierForPath(path);
      if (tier) { markUser(a, tier); return; }
      const kind = kindForPath(path);
      if (kind) markTenant(a, kind);
    });
    root.querySelectorAll('[data-user-id]:not([data-ath-donor])').forEach((n) => {
      const t = U[n.getAttribute('data-user-id')];
      if (t && !n.closest('[data-donor-skip]')) markUser(n, t);
    });
    root.querySelectorAll('[data-tenant-id]:not([data-ath-supporter])').forEach((n) => {
      const k = T[n.getAttribute('data-tenant-id')];
      if (k && !n.closest('[data-donor-skip]') && n.tagName !== 'BODY' && n.tagName !== 'HTML') markTenant(n, k);
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
