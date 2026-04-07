<?php
/** Voile affiché après détection touche Impr. écran (quand le navigateur la signale). */
?>
<div class="doc-screenshot-shield absolute inset-0 z-30 hidden flex-col items-center justify-center gap-4 bg-slate-900/65 px-6 text-center opacity-0 pointer-events-none transition-opacity duration-200" aria-live="polite" aria-hidden="true">
    <p class="text-sm font-semibold text-white max-w-sm">Pour limiter les copies d’écran, l’affichage du document a été masqué.</p>
    <button type="button" class="doc-screenshot-restore rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80">Afficher à nouveau</button>
</div>
