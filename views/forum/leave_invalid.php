<?php $baseUrl = url(''); ?>
<div class="flex-1 flex flex-col items-center justify-center px-4 py-16 min-h-screen bg-gradient-to-b from-slate-100 via-white to-slate-50">
    <div class="w-full max-w-md rounded-2xl border border-slate-200/80 bg-white shadow-lg shadow-slate-200/50 p-8 text-center">
        <p class="text-slate-600 text-sm leading-relaxed mb-6">Ce lien de sortie est invalide ou a expiré. Utilise un lien récent depuis le forum.</p>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/forum" class="inline-flex items-center justify-center w-full px-4 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition-colors">
            Retour au forum
        </a>
    </div>
</div>
