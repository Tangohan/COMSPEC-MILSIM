<?php
/** @var string $referral_code */
/** @var string $register_url */
/** @var string $create_community_url */
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-emerald-600 mb-2">Parrainage</p>
    <h1 class="text-2xl font-black text-slate-900 mb-4">Inviter une autre unité</h1>
    <p class="text-slate-600 text-sm mb-6">
        Partagez votre code ou un lien : lorsqu’une nouvelle communauté est créée ou qu’un premier paiement Stripe est enregistré avec votre parrainage,
        l’attribution est enregistrée (récompenses matérielles pouvant rester manuelles au début).
    </p>
    <div class="rounded-xl border border-slate-200 bg-white p-6 mb-6">
        <p class="text-xs text-slate-500 uppercase font-bold mb-2">Votre code</p>
        <p class="text-2xl font-mono font-black tracking-widest text-slate-900"><?= htmlspecialchars($referral_code) ?></p>
    </div>
    <div class="space-y-3 text-sm">
        <p class="text-slate-500">Lien inscription (nouveau compte) :</p>
        <p class="break-all rounded bg-slate-100 px-3 py-2 font-mono text-xs"><?= htmlspecialchars($register_url) ?></p>
        <p class="text-slate-500 mt-4">Lien création de communauté (compte existant) :</p>
        <p class="break-all rounded bg-slate-100 px-3 py-2 font-mono text-xs"><?= htmlspecialchars($create_community_url) ?></p>
    </div>
    <p class="mt-8 text-xs text-slate-400">
        Données traitées pour l’attribution du parrainage ; le filleul est informé dans les conditions d’utilisation.
    </p>
</div>
