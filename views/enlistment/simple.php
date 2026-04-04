<?php
$tenant = $tenant ?? [];
$formAction = $formAction ?? url('enlistment');
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$communityConfig = $communityConfig ?? [];
$requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
?>
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900 mb-2">Inscription — <?= htmlspecialchars($tenantName) ?></h1>
    <p class="text-sm text-slate-500 mb-8">Mode simple activé : formulaire condensé pour onboarding rapide.</p>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>" class="space-y-5 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">Nom complet</label>
            <input type="text" name="full_name" required class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Prénom Nom">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">Email</label>
            <input type="email" name="email" required class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="email@exemple.com">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">Callsign (optionnel)</label>
            <input type="text" name="callsign" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Ghost-21">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">Disponibilité</label>
            <input type="text" name="availability" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Soirs, week-end...">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">Motivation</label>
            <textarea name="motivation_why_join" rows="4" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Pourquoi souhaitez-vous rejoindre la communauté ?"></textarea>
        </div>

        <?php if ($requireAiAck): ?>
            <label class="flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="no_ai_confirmed" value="1" class="mt-0.5" required>
                <span>Je confirme l'absence d'IA dans ce dossier.</span>
            </label>
        <?php else: ?>
            <input type="hidden" name="no_ai_confirmed" value="1">
        <?php endif; ?>

        <button type="submit" class="w-full py-3.5 bg-slate-900 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-emerald-600">Soumettre la candidature</button>
    </form>
</div>
