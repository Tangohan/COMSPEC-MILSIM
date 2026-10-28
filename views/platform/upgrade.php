<?php
/** @var string $feature */
/** @var string|null $featureKey */
/** @var string|null $planName */
/** @var string|null $quotaContext Message complémentaire (quota, alternative gratuite). */
/** @var string|null $upgradeCtaPath Route relative pour le CTA (défaut : dashboard). */
/** @var string|null $upgradeFrom Paramètre d’URL ?from= (ex. quota_events). */
/** @var list<array{slug: string, name: string, monthly: ?int, yearly: ?int, available: bool}>|null $paymentOffers */
/** @var array<string, mixed>|null $openPayment Demande de paiement encore ouverte pour la communauté */
/** @var bool|null $canManageBilling */
/** @var string|null $csrfToken */
$upgradeFrom = $upgradeFrom ?? '';
$featureKey = (string) ($featureKey ?? '');
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
        $hasPaidSubscription = in_array($st, ['active', 'trialing'], true)
            || \App\Support\PlanGift::activeFromSettings($t['settings'] ?? null) !== null;
        $raw = $t['settings'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $d = json_decode($raw, true);
            if (is_array($d) && ! empty($d['founder_trial_ends_at'])) {
                $founderTrialEnds = (string) $d['founder_trial_ends_at'];
            }
        }
    }
}
$cta = isset($upgradeCtaPath) && is_string($upgradeCtaPath) && $upgradeCtaPath !== '' ? $upgradeCtaPath : 'dashboard';
$ctaIsDashboard = $cta === 'dashboard';
$showFounderTrial = ! $hasPaidSubscription && is_string($founderTrialEnds) && $founderTrialEnds !== '' && strtotime($founderTrialEnds) > time();
$trialEndFormatted = $showFounderTrial ? date('d/m/Y', strtotime((string) $founderTrialEnds)) : '';
$planLabel = (string) ($planName ?? 'Standard ou Pro');
$offerCards = is_array($paymentOffers ?? null) ? $paymentOffers : [];
// Formules retirées de la vente par l’administration : non proposées.
$offerCards = array_values(array_filter($offerCards, static fn ($o): bool => is_array($o) && ($o['on_sale'] ?? true) !== false));
$openPayment = is_array($openPayment ?? null) ? $openPayment : null;
$canBill = !empty($canManageBilling);
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
?>
<div class="min-h-[calc(80vh-2rem)] bg-gradient-to-b from-slate-100 via-slate-50 to-slate-100">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:py-14">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_24px_80px_-32px_rgba(15,23,42,0.35)]">
            <div class="relative border-b border-slate-800/10 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 px-6 py-10 sm:px-10">
                <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-emerald-500/15 blur-3xl" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-xl">
                        <p class="text-[11px] font-black uppercase tracking-[0.35em] text-emerald-400/95">Fonctionnalité premium</p>
                        <h1 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">Passez à un plan supérieur</h1>
                        <p class="mt-4 text-sm leading-relaxed text-slate-300">
                            Débloquez les modules avancés de votre communauté. Choisissez une formule et payez-la par PayPal.
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/5 px-5 py-4 backdrop-blur-sm lg:mt-1">
                        <div class="flex items-center gap-4">
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-500/20 text-amber-300 ring-1 ring-amber-400/30">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </span>
                            <div class="text-left">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Fonctionnalité concernée</p>
                                <p class="mt-1 text-lg font-black tracking-tight text-white"><?= htmlspecialchars((string) $feature) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-8 px-6 py-8 sm:px-10 sm:py-10">
                <?php if ($showFounderTrial): ?>
                <div class="flex gap-4 overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50 to-cyan-50/50 p-5 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/25">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800">Essai fondateur actif</p>
                        <p class="mt-2 text-sm leading-relaxed text-emerald-950/90">
                            En tant que fondateur, votre communauté bénéficie d’un <strong class="font-semibold">essai Pro</strong> jusqu’au
                            <time datetime="<?= htmlspecialchars((string) $founderTrialEnds) ?>"><?= htmlspecialchars($trialEndFormatted) ?></time>.
                            Après cette date, un abonnement actif conserve les options avancées.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (! empty($quotaContext)): ?>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-relaxed text-amber-950">
                    <p><?= htmlspecialchars((string) $quotaContext) ?></p>
                </div>
                <?php endif; ?>

                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Comparer les formules</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Essentiel</p>
                            <p class="mt-2 text-xl font-black text-slate-900">Gratuit</p>
                            <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400"></span> Documents, messagerie</li>
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400"></span> Effectifs et formations de base</li>
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400"></span> Quotas limités sur certains modules</li>
                            </ul>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-sky-700/80">Équipe</p>
                            <p class="mt-2 text-xl font-black text-slate-900">Standard</p>
                            <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500"></span> Événements, courrier, mur opérationnel</li>
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500"></span> Carte tactique ATAK</li>
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500"></span> Alertes communauté</li>
                            </ul>
                        </div>
                        <div class="relative rounded-2xl border border-emerald-200/80 bg-gradient-to-b from-white to-emerald-50/40 p-5 shadow-sm ring-1 ring-emerald-500/10">
                            <span class="absolute right-4 top-4 rounded-full bg-emerald-600 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-white">Recommandé</span>
                            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-700/80">Complet</p>
                            <p class="mt-2 text-xl font-black text-slate-900">Pro</p>
                            <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span> Recrutement et coopération</li>
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span> Analytics organisation</li>
                                <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span> Plafonds relevés</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if ($openPayment !== null): ?>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-relaxed text-amber-950">
                    <p>
                        Une demande de paiement est en cours (référence <strong><?= htmlspecialchars((string) $openPayment['reference'], ENT_QUOTES, 'UTF-8') ?></strong>,
                        <?= ((string) $openPayment['status']) === 'declared' ? 'paiement déclaré, vérification en cours' : 'en attente de paiement' ?>).
                        <a class="font-semibold underline" href="<?= htmlspecialchars(url('paiement/' . rawurlencode((string) $openPayment['reference'])), ENT_QUOTES, 'UTF-8') ?>">Ouvrir la page de paiement</a>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($canBill && $offerCards !== []): ?>
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Souscrire maintenant</p>
                    <p class="mt-2 text-sm text-slate-600">Paiement par PayPal sur la page de paiement d’Athena. Une référence vous est donnée ; l’équipe Athena active la formule dès réception (un mois ou un an, sans renouvellement automatique).</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <?php foreach ($offerCards as $card): ?>
                            <?php
                            $cardSlug = (string) ($card['slug'] ?? '');
                            $cardName = (string) ($card['name'] ?? $cardSlug);
                            $prices = array_filter([
                                'monthly' => $card['monthly'] ?? null,
                                'yearly' => $card['yearly'] ?? null,
                            ], static fn ($v): bool => $v !== null);
                            ?>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                <p class="text-lg font-black text-slate-900"><?= htmlspecialchars($cardName, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($prices === []): ?>
                                    <p class="mt-1 text-xs text-slate-500">Prix bientôt disponible</p>
                                    <button type="button" disabled class="mt-4 inline-flex w-full items-center justify-center rounded-full bg-slate-900 px-4 py-2.5 text-[11px] font-black uppercase tracking-[0.18em] text-white transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-40">Bientôt disponible</button>
                                <?php endif; ?>
                                <?php foreach ($prices as $interval => $cents): ?>
                                <form method="post" action="<?= htmlspecialchars(url('platform/upgrade/checkout'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="plan_slug" value="<?= htmlspecialchars($cardSlug, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="interval" value="<?= htmlspecialchars($interval, ENT_QUOTES, 'UTF-8') ?>">
                                    <p class="text-xs text-slate-500"><?= $interval === 'yearly' ? '1 an' : '1 mois' ?> · <strong class="text-slate-900"><?= htmlspecialchars(\App\Support\ManualBilling::formatAmount((int) $cents), ENT_QUOTES, 'UTF-8') ?></strong></p>
                                    <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-full bg-slate-900 px-4 py-2.5 text-[11px] font-black uppercase tracking-[0.18em] text-white transition hover:bg-emerald-600">
                                        Payer avec PayPal
                                    </button>
                                </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif (!$canBill): ?>
                    <p class="text-sm text-slate-600">
                        Demandez à un responsable de votre communauté de souscrire à <?= htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8') ?> depuis cette page.
                    </p>
                <?php endif; ?>

                <div class="flex flex-col gap-4 border-t border-slate-200 pt-8 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">
                        Les formules débloquent les modules selon le palier choisi. Le paiement se fait chez PayPal ; Athena ne voit jamais vos coordonnées bancaires.
                    </p>
                    <a href="<?= url($cta) ?>" class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-3 text-[11px] font-black uppercase tracking-[0.2em] text-slate-800 hover:bg-slate-50">
                        <?= $ctaIsDashboard ? 'Retour au tableau de bord' : 'Continuer' ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
