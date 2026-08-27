<?php
declare(strict_types=1);
/**
 * Toasts fixes (coin supérieur droit), superposés au contenu — ne décale pas la mise en page.
 *
 * Variable : $flash_toasts = list<array{variant: string, message: string}>
 * variant : error | success | warning | info
 */
$flash_toasts = $flash_toasts ?? [];
$items = [];
foreach ($flash_toasts as $row) {
    if (! is_array($row)) {
        continue;
    }
    $v = (string) ($row['variant'] ?? 'info');
    $m = trim((string) ($row['message'] ?? ''));
    if ($m === '') {
        continue;
    }
    $items[] = ['variant' => $v, 'message' => $m];
}
if ($items === []) {
    return;
}

$themes = [
    'error' => [
        'wrap' => 'border-red-200/90 bg-red-50 shadow-[0_20px_50px_-12px_rgba(127,29,29,0.25)]',
        'iconWrap' => 'bg-red-100 text-red-700 ring-1 ring-red-200',
        'eyebrow' => 'text-red-600',
        'heading' => 'text-red-900',
        'icon' => 'error',
    ],
    'success' => [
        'wrap' => 'border-emerald-200/90 bg-emerald-50 shadow-[0_20px_50px_-12px_rgba(6,78,59,0.2)]',
        'iconWrap' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
        'eyebrow' => 'text-emerald-600',
        'heading' => 'text-emerald-900',
        'icon' => 'success',
    ],
    'warning' => [
        'wrap' => 'border-amber-200/90 bg-amber-50 shadow-[0_20px_50px_-12px_rgba(120,53,15,0.18)]',
        'iconWrap' => 'bg-amber-100 text-amber-800 ring-1 ring-amber-200',
        'eyebrow' => 'text-amber-700',
        'heading' => 'text-amber-950',
        'icon' => 'warning',
    ],
    'info' => [
        'wrap' => 'border-slate-200/90 bg-white shadow-[0_20px_50px_-12px_rgba(15,23,42,0.15)]',
        'iconWrap' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        'eyebrow' => 'text-slate-600',
        'heading' => 'text-slate-900',
        'icon' => 'info',
    ],
];

$eyebrowFor = static function (string $variant, string $message): string {
    return \App\Support\FlashAlertTitle::for($variant, $message);
};
?>
<div id="flash-toast-root"
     class="flash-toast-root fixed right-4 flex max-h-[calc(100vh-2rem)] w-[min(100vw-2rem,22rem)] sm:w-[min(100vw-3rem,26rem)] flex-col gap-2 overflow-y-auto pointer-events-none sm:right-6"
     style="top:calc(var(--flash-toast-top-offset, 1rem));">
    <?php foreach ($items as $idx => $item): ?>
        <?php
        $variant = $item['variant'];
        if (! isset($themes[$variant])) {
            $variant = 'info';
        }
        $t = $themes[$variant];
        $iconKind = $t['icon'];
        $msg = $item['message'];
        $eyebrow = $eyebrowFor($item['variant'], $msg);
        $dismissMs = $variant === 'error' ? 9000 : 5500;
        // Ne pas utiliser $role : collision avec les vues métier (rôle communauté / système).
        $ariaRole = $variant === 'error' ? 'alert' : 'status';
        $live = $variant === 'error' ? 'assertive' : 'polite';
        ?>
    <div data-flash-toast data-dismiss-ms="<?= (int) $dismissMs ?>"
         class="flash-toast-item pointer-events-auto overflow-hidden rounded-2xl border <?= htmlspecialchars($t['wrap'], ENT_QUOTES, 'UTF-8') ?> transition-all duration-200 ease-out motion-safe:animate-[flashToastIn_0.38s_ease-out]"
         role="<?= htmlspecialchars($ariaRole, ENT_QUOTES, 'UTF-8') ?>"
         aria-live="<?= htmlspecialchars($live, ENT_QUOTES, 'UTF-8') ?>">
        <div class="flex items-start gap-3 px-3 py-3 sm:gap-4 sm:px-4 sm:py-3.5">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl <?= htmlspecialchars($t['iconWrap'], ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($iconKind === 'success'): ?>
                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <?php elseif ($iconKind === 'warning'): ?>
                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <?php elseif ($iconKind === 'info'): ?>
                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                </svg>
                <?php else: ?>
                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                </svg>
                <?php endif; ?>
            </div>
            <div class="min-w-0 flex-1 pt-0.5">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] <?= htmlspecialchars($t['eyebrow'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="mt-0.5 text-xs sm:text-sm font-semibold leading-snug <?= htmlspecialchars($t['heading'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <button type="button" data-flash-toast-close
                    class="shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-black/5 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                    aria-label="Fermer la notification">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<style>
/* z-index hors Tailwind (z-[250] souvent absente du build purge) :
   header Athena / dash 96 · rail BO 100 · rail effectifs 120 · toasts 180 */
#flash-toast-root.flash-toast-root {
  position: fixed;
  z-index: 180;
}
@keyframes flashToastIn {
  from { opacity: 0; transform: translateX(0.75rem) scale(0.98); }
  to { opacity: 1; transform: translateX(0) scale(1); }
}
@media (prefers-reduced-motion: reduce) {
  .flash-toast-item { animation: none !important; }
}
</style>
<script>
(function () {
  function updateToastOffset() {
    var root = document.getElementById('flash-toast-root');
    if (!root) return;
    var nav = document.querySelector('[data-portal-nav]')
      || document.querySelector('[data-athena-header]')
      || document.querySelector('.athena-header')
      || document.querySelector('.dash-topnav')
      || document.querySelector('.eff-topnav');
    var safeTop = 16; // 1rem fallback
    if (nav && typeof nav.getBoundingClientRect === 'function') {
      var rect = nav.getBoundingClientRect();
      if (rect && Number.isFinite(rect.bottom)) {
        safeTop = Math.max(safeTop, Math.round(rect.bottom + 10));
      }
    }
    root.style.setProperty('--flash-toast-top-offset', safeTop + 'px');
    root.style.maxHeight = 'calc(100vh - ' + (safeTop + 16) + 'px)';
  }

  function hideToast(el) {
    if (!el || el.getAttribute('data-toast-closing') === '1') return;
    el.setAttribute('data-toast-closing', '1');
    el.style.opacity = '0';
    el.style.transform = 'translateX(0.5rem)';
    window.setTimeout(function () { el.remove(); }, 220);
  }
  var root = document.getElementById('flash-toast-root');
  if (!root) return;
  updateToastOffset();
  window.addEventListener('resize', updateToastOffset, { passive: true });
  root.querySelectorAll('[data-flash-toast]').forEach(function (el) {
    var ms = parseInt(el.getAttribute('data-dismiss-ms') || '6000', 10);
    var t = window.setTimeout(function () { hideToast(el); }, ms);
    var close = el.querySelector('[data-flash-toast-close]');
    if (close) {
      close.addEventListener('click', function () {
        window.clearTimeout(t);
        hideToast(el);
      });
    }
  });
})();
</script>
