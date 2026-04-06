/**
 * Toasts pour les pages formation (validation de leçon, erreurs réseau, etc.).
 * Style aligné sur views/partials/flash_toasts.php
 */
(function () {
  'use strict';

  var THEMES = {
    success: {
      wrap: 'border-emerald-200/90 bg-emerald-50 shadow-[0_20px_50px_-12px_rgba(6,78,59,0.2)]',
      iconWrap: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
      eyebrowCls: 'text-emerald-600',
      headingCls: 'text-emerald-900',
      eyebrowText: 'Succès',
      dismissMs: 5500,
      role: 'status',
      live: 'polite',
      iconSvg:
        '<svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
    },
    error: {
      wrap: 'border-red-200/90 bg-red-50 shadow-[0_20px_50px_-12px_rgba(127,29,29,0.25)]',
      iconWrap: 'bg-red-100 text-red-700 ring-1 ring-red-200',
      eyebrowCls: 'text-red-600',
      headingCls: 'text-red-900',
      eyebrowText: 'Erreur',
      dismissMs: 9000,
      role: 'alert',
      live: 'assertive',
      iconSvg:
        '<svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>',
    },
    warning: {
      wrap: 'border-amber-200/90 bg-amber-50 shadow-[0_20px_50px_-12px_rgba(120,53,15,0.18)]',
      iconWrap: 'bg-amber-100 text-amber-800 ring-1 ring-amber-200',
      eyebrowCls: 'text-amber-700',
      headingCls: 'text-amber-950',
      eyebrowText: 'Attention',
      dismissMs: 7000,
      role: 'status',
      live: 'polite',
      iconSvg:
        '<svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>',
    },
    info: {
      wrap: 'border-slate-200/90 bg-white shadow-[0_20px_50px_-12px_rgba(15,23,42,0.15)]',
      iconWrap: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
      eyebrowCls: 'text-slate-600',
      headingCls: 'text-slate-900',
      eyebrowText: 'Information',
      dismissMs: 5500,
      role: 'status',
      live: 'polite',
      iconSvg:
        '<svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>',
    },
  };

  var ROOT_CLASS =
    'fixed z-[250] top-4 right-4 flex max-h-[calc(100vh-2rem)] w-[min(100vw-2rem,22rem)] sm:w-[min(100vw-3rem,26rem)] flex-col gap-2 overflow-y-auto pointer-events-none sm:top-6 sm:right-6';

  function injectKeyframesOnce() {
    if (document.getElementById('lms-training-toast-keyframes')) return;
    var s = document.createElement('style');
    s.id = 'lms-training-toast-keyframes';
    s.textContent =
      '@keyframes lmsTrainingToastIn{from{opacity:0;transform:translateX(0.75rem) scale(0.98)}to{opacity:1;transform:translateX(0) scale(1)}}@media (prefers-reduced-motion:reduce){.lms-training-toast-item{animation:none!important}}';
    document.head.appendChild(s);
  }

  function ensureRoot() {
    injectKeyframesOnce();
    var root = document.getElementById('lms-training-toast-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'lms-training-toast-root';
      root.className = ROOT_CLASS;
      (document.body || document.documentElement).appendChild(root);
    }
    return root;
  }

  function hideToast(el) {
    if (!el || el.getAttribute('data-lms-toast-closing') === '1') return;
    el.setAttribute('data-lms-toast-closing', '1');
    el.style.opacity = '0';
    el.style.transform = 'translateX(0.5rem)';
    window.setTimeout(function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 220);
  }

  /**
   * @param {string} message
   * @param {'success'|'error'|'warning'|'info'} [variant]
   */
  function lmsTrainingToastShow(message, variant) {
    var msg = typeof message === 'string' ? message.trim() : '';
    if (!msg) return;

    variant = variant || 'info';
    var t = THEMES[variant] || THEMES.info;

    var root = ensureRoot();

    var el = document.createElement('div');
    el.className =
      'lms-training-toast-item pointer-events-auto overflow-hidden rounded-2xl border ' +
      t.wrap +
      ' transition-all duration-200 ease-out motion-safe:animate-[lmsTrainingToastIn_0.38s_ease-out]';
    el.setAttribute('role', t.role);
    el.setAttribute('aria-live', t.live);
    el.setAttribute('data-lms-training-toast', '1');
    el.setAttribute('data-dismiss-ms', String(t.dismissMs));

    var inner = document.createElement('div');
    inner.className = 'flex items-start gap-3 px-3 py-3 sm:gap-4 sm:px-4 sm:py-3.5';

    var iconWrap = document.createElement('div');
    iconWrap.className =
      'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ' + t.iconWrap;
    iconWrap.innerHTML = t.iconSvg;

    var textCol = document.createElement('div');
    textCol.className = 'min-w-0 flex-1 pt-0.5';

    var eyebrow = document.createElement('p');
    eyebrow.className =
      'text-[9px] font-black uppercase tracking-[0.2em] ' + t.eyebrowCls;
    eyebrow.textContent = t.eyebrowText;

    var p = document.createElement('p');
    p.className = 'mt-0.5 text-xs sm:text-sm font-semibold leading-snug ' + t.headingCls;
    p.textContent = msg;

    textCol.appendChild(eyebrow);
    textCol.appendChild(p);

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className =
      'shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-black/5 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400';
    closeBtn.setAttribute('aria-label', 'Fermer la notification');
    closeBtn.innerHTML =
      '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';

    inner.appendChild(iconWrap);
    inner.appendChild(textCol);
    inner.appendChild(closeBtn);
    el.appendChild(inner);
    root.appendChild(el);

    var ms = t.dismissMs;
    var timer = window.setTimeout(function () {
      hideToast(el);
    }, ms);

    closeBtn.addEventListener('click', function () {
      window.clearTimeout(timer);
      hideToast(el);
    });
  }

  window.lmsTrainingToastShow = lmsTrainingToastShow;
})();
