<?php
/** @var string $feature */
/** @var string|null $planName */
?>
<div class="max-w-xl mx-auto px-4 py-16 text-center">
    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-amber-500 mb-2">Fonctionnalité premium</p>
    <h1 class="text-2xl font-black text-white mb-3">Passez à un plan supérieur</h1>
    <p class="text-neutral-400 text-sm mb-6">
        La fonctionnalité « <?= htmlspecialchars((string) $feature) ?> » n’est pas incluse dans votre offre actuelle.
        Passez à <?= htmlspecialchars((string) ($planName ?? 'Standard ou Pro')) ?> pour l’activer (facturation Stripe si configurée).
    </p>
    <a href="<?= url('dashboard') ?>" class="inline-block px-5 py-2 rounded border border-white/20 text-sm font-semibold text-white hover:bg-white/5">Retour au tableau de bord</a>
</div>
