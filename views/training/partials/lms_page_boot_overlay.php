<?php
declare(strict_types=1);
/** @var string $lmsBootMessage */
$lmsBootMessage = isset($lmsBootMessage) && trim((string) $lmsBootMessage) !== ''
    ? trim((string) $lmsBootMessage)
    : 'Chargement…';
?>
<div id="lms-page-boot" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-100/85 backdrop-blur-[2px] transition-opacity duration-300 ease-out" role="status" aria-live="polite" aria-busy="true">
    <div class="lms-panel rounded-2xl p-8 md:p-10 text-center shadow-xl max-w-sm mx-4 border border-slate-200/80">
        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 mb-4" aria-hidden="true">
            <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-slate-600 text-sm font-medium"><?= htmlspecialchars($lmsBootMessage, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
<script>
(function () {
    var boot = document.getElementById('lms-page-boot');
    if (!boot) return;
    function dismiss() {
        boot.classList.add('opacity-0', 'pointer-events-none');
        boot.setAttribute('aria-busy', 'false');
        boot.setAttribute('aria-hidden', 'true');
        setTimeout(function () {
            if (boot.parentNode) boot.parentNode.removeChild(boot);
        }, 320);
    }
    if (document.readyState !== 'loading') {
        dismiss();
    } else {
        document.addEventListener('DOMContentLoaded', dismiss);
    }
})();
</script>
