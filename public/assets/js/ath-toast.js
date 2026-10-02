/**
 * athToast(variant, message) — toast client au même rendu que views/partials/flash_toasts.php.
 * variant : success | error | warning | info. Annonce accessible (role status/alert, aria-live),
 * fermeture automatique après 5 s, sauf les erreurs (fermeture manuelle).
 */
(function () {
  'use strict';
  if (window.athToast) return;

  var THEMES = {
    error: { wrap: 'border-red-200/90 bg-red-50', icon: 'bg-red-100 text-red-700 ring-1 ring-red-200', eyebrow: 'text-red-600', heading: 'text-red-900', label: 'Erreur' },
    success: { wrap: 'border-emerald-200/90 bg-emerald-50', icon: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200', eyebrow: 'text-emerald-600', heading: 'text-emerald-900', label: 'C’est fait' },
    warning: { wrap: 'border-amber-200/90 bg-amber-50', icon: 'bg-amber-100 text-amber-800 ring-1 ring-amber-200', eyebrow: 'text-amber-700', heading: 'text-amber-950', label: 'Attention' },
    info: { wrap: 'border-slate-200/90 bg-white', icon: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200', eyebrow: 'text-slate-600', heading: 'text-slate-900', label: 'Information' }
  };
  var ICONS = {
    success: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    error: 'M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0Z',
    warning: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    info: 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z'
  };

  function root() {
    var r = document.getElementById('flash-toast-root');
    if (r) return r;
    r = document.createElement('div');
    r.id = 'flash-toast-root';
    r.className = 'flash-toast-root fixed right-4 flex w-[min(100vw-2rem,22rem)] sm:w-[min(100vw-3rem,26rem)] flex-col gap-2 pointer-events-none sm:right-6';
    r.style.cssText = 'position:fixed;z-index:180;top:calc(var(--flash-toast-top-offset, 1rem));max-height:calc(100vh - 2rem);overflow-y:auto;';
    document.body.appendChild(r);
    return r;
  }

  window.athToast = function (variant, message) {
    var msg = String(message || '').trim();
    if (!msg) return null;
    var v = THEMES[variant] ? variant : 'info';
    var t = THEMES[v];
    var item = document.createElement('div');
    item.setAttribute('data-flash-toast', '');
    item.setAttribute('role', v === 'error' ? 'alert' : 'status');
    item.setAttribute('aria-live', v === 'error' ? 'assertive' : 'polite');
    item.className = 'flash-toast-item pointer-events-auto overflow-hidden rounded-2xl border shadow-lg transition-all duration-200 ease-out ' + t.wrap;
    item.innerHTML =
      '<div class="flex items-start gap-3 px-3 py-3 sm:gap-4 sm:px-4 sm:py-3.5">' +
        '<div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ' + t.icon + '">' +
          '<svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' + ICONS[v] + '"/></svg>' +
        '</div>' +
        '<div class="min-w-0 flex-1 pt-0.5">' +
          '<p class="text-[9px] font-black uppercase tracking-[0.2em] ' + t.eyebrow + '"></p>' +
          '<p class="mt-0.5 text-xs sm:text-sm font-semibold leading-snug ' + t.heading + '"></p>' +
        '</div>' +
        '<button type="button" class="shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-black/5 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400" aria-label="Fermer la notification">' +
          '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
        '</button>' +
      '</div>';
    var ps = item.querySelectorAll('p');
    ps[0].textContent = t.label;   // textContent : aucun HTML injecté depuis le message
    ps[1].textContent = msg;
    var close = function () {
      item.style.opacity = '0';
      setTimeout(function () { if (item.parentNode) item.parentNode.removeChild(item); }, 200);
    };
    item.querySelector('button').addEventListener('click', close);
    root().appendChild(item);
    if (v !== 'error') setTimeout(close, 5000);
    return item;
  };
})();
