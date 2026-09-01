<?php
declare(strict_types=1);
$tenant = is_array($platformTenant ?? null) ? $platformTenant : [];
$plans = is_array($platformSubscriptionPlans ?? null) ? $platformSubscriptionPlans : [];
$plansError = isset($platformSubscriptionPlansError) ? (string) $platformSubscriptionPlansError : '';
$statusLabels = is_array($platformSubscriptionStatusLabels ?? null) ? $platformSubscriptionStatusLabels : [];
$typeOptions = is_array($platformTenantTypes ?? null) ? $platformTenantTypes : [];
$currentType = (string) ($platformTenantCurrentType ?? 'full');
$identityAction = (string) ($platformTenantIdentityFormAction ?? '');
$typeAction = (string) ($platformTenantTypeFormAction ?? '');
$formAction = (string) ($platformTenantPlanFormAction ?? '');
$founderTrialEndsAt = isset($platformFounderTrialEndsAt) ? (string) $platformFounderTrialEndsAt : '';
$name = (string) ($tenant['name'] ?? '');
$slug = (string) ($tenant['slug'] ?? '');
$communityCode = trim((string) ($tenant['community_code'] ?? ''));
$createdAt = isset($tenant['created_at']) && $tenant['created_at'] !== null && $tenant['created_at'] !== ''
    ? (string) $tenant['created_at']
    : '';
$createdLabel = '—';
if ($createdAt !== '') {
    $createdTs = strtotime($createdAt);
    $createdLabel = $createdTs ? date('d/m/Y', $createdTs) : $createdAt;
}
$tenantId = (int) ($tenant['id'] ?? 0);
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
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$publicUrl = $slug !== '' ? url('c/' . rawurlencode($slug)) : '';
$usersUrl = $tenantId > 0 ? url('admin/users') . '?tenant_id=' . $tenantId : url('admin/users');
$currentPlanLabel = $currentPlan;
foreach ($plans as $p) {
    if ((string) ($p['slug'] ?? '') === $currentPlan) {
        $currentPlanLabel = (string) ($p['name'] ?? $currentPlan);
        break;
    }
}
$currentTypeLabel = (string) ($typeOptions[$currentType]['label'] ?? $currentType);
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                <span aria-hidden="true"> / </span>
                Fiche communauté
            </p>
            <h1 class="pa-hero__title"><?= $h($name !== '' ? $name : 'Communauté') ?></h1>
            <p class="pa-hero__lead">
                Identité, profil d’outils et formule d’accès. Membres, unités et recrutement se gèrent dans le back-office de cette communauté.
            </p>
            <div class="pa-chips">
                <span class="pa-pill pa-pill--slate"><?= $h($currentTypeLabel) ?></span>
                <span class="pa-pill pa-pill--mint"><?= $h($currentPlanLabel) ?></span>
                <span class="pa-pill pa-pill--amber"><?= $h((string) ($statusLabels[$currentStatus] ?? 'Inconnu')) ?></span>
                <?php if ($trialActive): ?>
                    <span class="pa-pill pa-pill--mint">Essai fondateur en cours</span>
                <?php endif; ?>
            </div>
            <div class="pa-hero__actions">
                <?php if ($publicUrl !== ''): ?>
                    <a class="pa-btn pa-btn--solid" href="<?= $h($publicUrl) ?>" target="_blank" rel="noopener">Page publique</a>
                <?php endif; ?>
                <a class="pa-btn pa-btn--ghost" href="<?= $h($usersUrl) ?>">Comptes</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/audit')) ?>">Journal d’audit</a>
            </div>
        </header>

        <div class="pa-panel">
            <div class="pa-narrow">
                <?php if ($ok): ?>
                    <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
                <?php endif; ?>
                <?php if ($err): ?>
                    <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
                <?php endif; ?>

                <dl class="pa-stats">
                    <div class="pa-stat">
                        <dt>Création</dt>
                        <dd style="font-size:1rem;"><?= $h($createdLabel) ?></dd>
                    </div>
                    <?php if ($communityCode !== ''): ?>
                        <div class="pa-stat">
                            <dt>Code pour rejoindre</dt>
                            <dd style="font-size:1rem;"><?= $h($communityCode) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($periodEnd !== ''): ?>
                        <div class="pa-stat">
                            <dt>Fin de période</dt>
                            <dd style="font-size:1rem;"><?= $h($periodEnd) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($trialActive): ?>
                        <div class="pa-stat">
                            <dt>Essai fondateur</dt>
                            <dd style="font-size:1rem;">Jusqu’au <?= $h($founderTrialEndsAt) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <form id="identite" class="pa-card scroll-mt-24" method="post" action="<?= $h($identityAction) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="pa-card__kicker">01 · Identité</p>
                    <h2 class="pa-card__title">Nom et page publique</h2>
                    <p class="pa-card__help">Changer l’adresse courte casse les anciens liens vers cette communauté.</p>
                    <div class="pa-field">
                        <label for="tenant_name">Nom affiché</label>
                        <input id="tenant_name" name="tenant_name" type="text" required maxlength="255" value="<?= $h($name) ?>">
                    </div>
                    <div class="pa-field">
                        <label for="tenant_slug">Adresse courte de la page publique</label>
                        <p class="pa-hint">Lettres minuscules, chiffres et tirets, 50 caractères au plus.</p>
                        <input id="tenant_slug" name="tenant_slug" type="text" required maxlength="50" pattern="[a-z0-9]([-a-z0-9]*[a-z0-9])?" value="<?= $h($slug) ?>">
                    </div>
                    <div class="pa-actions">
                        <button type="submit" class="pa-btn pa-btn--ink">Enregistrer l’identité</button>
                    </div>
                </form>

                <form id="profil" class="pa-card scroll-mt-24" method="post" action="<?= $h($typeAction) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="pa-card__kicker">02 · Profil</p>
                    <h2 class="pa-card__title">Profil d’outils</h2>
                    <p class="pa-card__help">Le profil détermine les outils visibles pour tous les membres. Changer ou réappliquer ajuste les menus, sans effacer les données.</p>
                    <fieldset style="border:0;margin:1rem 0 0;padding:0;">
                        <legend class="sr-only">Profil de la communauté</legend>
                        <?php foreach ($typeOptions as $typeKey => $typeMeta): ?>
                            <?php
                            $typeKey = (string) $typeKey;
                            $isCurrent = $typeKey === $currentType;
                            $inputId = 'tenant_type_' . preg_replace('/[^a-z0-9_-]/i', '', $typeKey);
                            ?>
                            <label class="pa-choice" for="<?= $h($inputId) ?>">
                                <input id="<?= $h($inputId) ?>" type="radio" name="tenant_type" value="<?= $h($typeKey) ?>" <?= $isCurrent ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= $h((string) ($typeMeta['label'] ?? $typeKey)) ?><?= $isCurrent ? ' · actuel' : '' ?></strong>
                                    <em><?= $h((string) ($typeMeta['description'] ?? '')) ?></em>
                                    <?php if (!empty($typeMeta['consequences'])): ?>
                                        <em><?= $h((string) $typeMeta['consequences']) ?></em>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <label class="pa-choice" style="margin-top:0.85rem;">
                        <input type="checkbox" name="confirm_type_change" value="1" required>
                        <span>
                            <strong>Confirmation</strong>
                            <em>J’applique (ou réapplique) ce profil pour toute la communauté.</em>
                        </span>
                    </label>
                    <div class="pa-actions">
                        <button type="submit" class="pa-btn pa-btn--ink">Appliquer le profil</button>
                    </div>
                </form>

                <section id="formule" class="pa-card scroll-mt-24">
                    <p class="pa-card__kicker">03 · Formule</p>
                    <h2 class="pa-card__title">Formule d’accès</h2>
                    <p class="pa-card__help">Le palier détermine les modules et les plafonds. Le changement s’applique immédiatement.</p>

                    <?php if ($plansError !== ''): ?>
                        <p class="pa-flash pa-flash--warn" style="margin-top:1rem;"><?= $h($plansError) ?></p>
                    <?php else: ?>
                        <?php if ($hasStripe): ?>
                            <p class="pa-flash pa-flash--warn" style="margin-top:1rem;">
                                Un abonnement est déjà lié au prestataire de paiement. Un changement manuel peut être écrasé au prochain renouvellement.
                            </p>
                        <?php endif; ?>

                        <form method="post" action="<?= $h($formAction) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <div class="pa-field">
                                <label for="plan_slug">Formule d’accès</label>
                                <select id="plan_slug" name="plan_slug" required>
                                    <?php if ($plans === []): ?>
                                        <option value="">Aucune formule disponible</option>
                                    <?php else: ?>
                                        <?php foreach ($plans as $p): ?>
                                            <?php
                                            $planValue = (string) ($p['slug'] ?? '');
                                            $label = (string) ($p['name'] ?? $planValue);
                                            if ($planValue === '') {
                                                continue;
                                            }
                                            ?>
                                            <option value="<?= $h($planValue) ?>" <?= $planValue === $currentPlan ? 'selected' : '' ?>><?= $h($label) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="pa-field">
                                <label for="subscription_status">Statut d’abonnement</label>
                                <p class="pa-hint">Pour une attribution de courtoisie, choisissez « Abonnement actif » ou « Sans abonnement payant ».</p>
                                <select id="subscription_status" name="subscription_status" required>
                                    <?php foreach ($statusLabels as $value => $label): ?>
                                        <option value="<?= $h((string) $value) ?>" <?= (string) $value === $currentStatus ? 'selected' : '' ?>><?= $h((string) $label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($trialActive): ?>
                                <label class="pa-choice" style="margin-top:1rem;">
                                    <input type="checkbox" name="end_founder_trial" value="1">
                                    <span>
                                        <strong>Clôturer l’essai fondateur</strong>
                                        <em>Seule la formule choisie ci-dessus comptera ensuite.</em>
                                    </span>
                                </label>
                            <?php endif; ?>
                            <div class="pa-actions">
                                <button type="submit" class="pa-btn pa-btn--ink">Enregistrer la formule</button>
                                <a class="pa-btn pa-btn--line" href="<?= $h(url('admin/tenants')) ?>">Retour à l’annuaire</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</div>
