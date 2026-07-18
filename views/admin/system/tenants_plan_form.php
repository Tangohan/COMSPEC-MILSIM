<?php
declare(strict_types=1);
$tenant = is_array($platformTenant ?? null) ? $platformTenant : [];
$plans = is_array($platformSubscriptionPlans ?? null) ? $platformSubscriptionPlans : [];
$statusLabels = is_array($platformSubscriptionStatusLabels ?? null) ? $platformSubscriptionStatusLabels : [];
$formAction = (string) ($platformTenantPlanFormAction ?? '');
$founderTrialEndsAt = isset($platformFounderTrialEndsAt) ? (string) $platformFounderTrialEndsAt : '';
$name = (string) ($tenant['name'] ?? '');
$currentPlan = (string) ($tenant['plan_slug'] ?? 'free');
$currentStatus = (string) ($tenant['subscription_status'] ?? 'none');
$periodEnd = isset($tenant['subscription_current_period_end']) && $tenant['subscription_current_period_end'] !== null && $tenant['subscription_current_period_end'] !== ''
    ? (string) $tenant['subscription_current_period_end']
    : '';
$stripeSub = trim((string) ($tenant['stripe_subscription_id'] ?? ''));
$hasStripe = $stripeSub !== '';
$trialActive = false;
if ($founderTrialEndsAt !== '') {
    $trialTs = strtotime($founderTrialEndsAt);
    $trialActive = $trialTs !== false && $trialTs > time();
}
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
        <nav class="text-sm text-slate-500">
            <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration plateforme</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="<?= htmlspecialchars(url('admin/tenants'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Communautés</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Formule d’accès</span>
        </nav>

        <?php $err = \App\Core\Session::getFlash('error'); ?>
        <?php if ($err): ?>
            <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <header>
            <h1 class="text-2xl font-black text-slate-900">Formule d’accès</h1>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                Choisissez le palier proposé à <strong class="font-semibold text-slate-800"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong>
                et le statut d’abonnement associé. Les capacités du portail (effectifs max, modules, quotas) suivent immédiatement cette formule.
            </p>
        </header>

        <?php if ($hasStripe): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 leading-relaxed" role="status">
                Cette communauté a déjà un abonnement lié au prestataire de paiement.
                Un changement manuel peut être écrasé lors du prochain renouvellement ou événement de facturation.
                Préférez un acompte / crédit côté prestataire si la facturation doit rester synchronisée.
            </div>
        <?php endif; ?>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Formule actuelle</dt>
                <dd class="mt-1 font-semibold text-slate-900">
                    <?php
                    $currentPlanLabel = $currentPlan;
                    foreach ($plans as $p) {
                        if ((string) ($p['slug'] ?? '') === $currentPlan) {
                            $currentPlanLabel = (string) ($p['name'] ?? $currentPlan);
                            break;
                        }
                    }
                    echo htmlspecialchars($currentPlanLabel, ENT_QUOTES, 'UTF-8');
                    ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Statut actuel</dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= htmlspecialchars($statusLabels[$currentStatus] ?? 'Inconnu', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php if ($periodEnd !== ''): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Fin de période</dt>
                    <dd class="mt-1 text-slate-800"><?= htmlspecialchars($periodEnd, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($trialActive): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Essai fondateur</dt>
                    <dd class="mt-1 text-emerald-800 font-medium">Actif jusqu’au <?= htmlspecialchars($founderTrialEndsAt, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            <?php endif; ?>
        </dl>

        <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-8">
            <?= \App\Core\Csrf::field() ?>

            <div>
                <label for="plan_slug" class="block text-sm font-semibold text-slate-800">Formule d’accès</label>
                <p class="mt-1 text-xs text-slate-500">Le palier détermine les modules et les plafonds disponibles pour la communauté.</p>
                <select id="plan_slug" name="plan_slug" required class="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                    <?php if ($plans === []): ?>
                        <option value="">Aucune formule disponible</option>
                    <?php else: ?>
                        <?php foreach ($plans as $p): ?>
                            <?php
                            $slug = (string) ($p['slug'] ?? '');
                            $label = (string) ($p['name'] ?? $slug);
                            if ($slug === '') {
                                continue;
                            }
                            ?>
                            <option value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" <?= $slug === $currentPlan ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div>
                <label for="subscription_status" class="block text-sm font-semibold text-slate-800">Statut d’abonnement</label>
                <p class="mt-1 text-xs text-slate-500">
                    Pour une attribution gratuite ou de courtoisie, choisissez « Abonnement actif » ou « Sans abonnement payant » selon le cas.
                </p>
                <select id="subscription_status" name="subscription_status" required class="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $value === $currentStatus ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($trialActive): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="end_founder_trial" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500">
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">Clôturer l’essai fondateur</span>
                            <span class="mt-1 block text-xs text-slate-600 leading-relaxed">
                                L’essai Pro fondateur ne s’appliquera plus : seule la formule choisie ci-dessus comptera.
                            </span>
                        </span>
                    </label>
                </div>
            <?php endif; ?>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button type="submit" class="inline-flex rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                    Enregistrer la formule
                </button>
                <a href="<?= htmlspecialchars(url('admin/tenants'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
