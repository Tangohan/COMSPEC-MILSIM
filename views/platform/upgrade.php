<?php
/** @var string $feature */
/** @var string|null $planName */
/** @var string|null $quotaContext Message complémentaire (quota, alternative gratuite). */
/** @var string|null $upgradeCtaPath Route relative pour le CTA (défaut : dashboard). */
/** @var string|null $upgradeFrom Paramètre d’URL ?from= (ex. quota_events). */
$upgradeFrom = $upgradeFrom ?? '';
if (($quotaContext ?? null) === null || trim((string) $quotaContext) === '') {
    if (str_starts_with($upgradeFrom, 'quota_')) {
        $qk = substr($upgradeFrom, strlen('quota_'));
        if ($qk === 'events') {
            $quotaContext = 'Vous avez atteint la limite de créations d’événements incluse dans l’offre gratuite. Avec Standard ou Pro, vos organisateurs peuvent créer des événements sans ce plafond mensuel.';
        } else {
            $quotaContext = 'Une limite d’usage du plan gratuit s’applique à cette fonctionnalité. Passez à une offre supérieure pour lever les plafonds correspondants.';
        }
    }
}
$tenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
$founderTrialEnds = null;
$hasPaidSubscription = false;
if ($tenantId > 0) {
    $t = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->findById($tenantId);
    if ($t) {
        $st = (string) ($t['subscription_status'] ?? 'none');
        $hasPaidSubscription = in_array($st, ['active', 'trialing'], true);
        $raw = $t['settings'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $d = json_decode($raw, true);
            if (is_array($d) && ! empty($d['founder_trial_ends_at'])) {
                $founderTrialEnds = (string) $d['founder_trial_ends_at'];
            }
        }
    }
}
?>
<div class="max-w-xl mx-auto px-4 py-16 text-center">
    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-amber-500 mb-2">Fonctionnalité premium</p>
    <h1 class="text-2xl font-black text-white mb-3">Passez à un plan supérieur</h1>
    <?php if (! $hasPaidSubscription && is_string($founderTrialEnds) && $founderTrialEnds !== '' && strtotime($founderTrialEnds) > time()): ?>
        <p class="text-emerald-400/90 text-xs mb-4">
            En tant que fondateur, votre communauté bénéficie d’un essai Pro jusqu’au <?= htmlspecialchars(date('d/m/Y', strtotime($founderTrialEnds))) ?>
            (ATAK, événements, analytics). Après cette date, un abonnement Stripe actif reprend le relais pour conserver ces options.
        </p>
    <?php endif; ?>
    <p class="text-neutral-400 text-sm mb-6">
        La fonctionnalité « <?= htmlspecialchars((string) $feature) ?> » n’est pas incluse dans votre offre actuelle.
        Passez à <?= htmlspecialchars((string) ($planName ?? 'Standard ou Pro')) ?> pour l’activer (facturation Stripe si configurée).
    </p>
    <?php if (!empty($quotaContext)): ?>
        <p class="text-neutral-500 text-xs mb-6 text-left max-w-md mx-auto"><?= htmlspecialchars((string) $quotaContext) ?></p>
    <?php endif; ?>
    <?php $cta = isset($upgradeCtaPath) && is_string($upgradeCtaPath) && $upgradeCtaPath !== '' ? $upgradeCtaPath : 'dashboard'; ?>
    <a href="<?= url($cta) ?>" class="inline-block px-5 py-2 rounded border border-white/20 text-sm font-semibold text-white hover:bg-white/5"><?= $cta === 'dashboard' ? 'Retour au tableau de bord' : 'Voir les offres' ?></a>
</div>
