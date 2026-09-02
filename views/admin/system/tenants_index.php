<?php
declare(strict_types=1);
$rows = is_array($platformTenants ?? null) ? $platformTenants : [];
$plans = is_array($platformSubscriptionPlans ?? null) ? $platformSubscriptionPlans : [];
$plansError = isset($platformSubscriptionPlansError) ? (string) $platformSubscriptionPlansError : '';
$planNameBySlug = is_array($platformPlanNameBySlug ?? null) ? $platformPlanNameBySlug : [];
$statusLabels = is_array($platformSubscriptionStatusLabels ?? null) ? $platformSubscriptionStatusLabels : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fmt = static function (mixed $raw): string {
    if ($raw === null || $raw === '') {
        return '—';
    }
    $t = strtotime((string) $raw);

    return $t ? date('d/m/Y', $t) : (string) $raw;
};
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Communautés
            </p>
            <h1 class="pa-hero__title">Annuaire des communautés</h1>
            <p class="pa-hero__lead">
                Toutes les organisations du site, avec le profil d’outils, la formule et l’effectif.
                Ouvrez Administrer pour l’identité, le profil et la formule, ou Accès super tenant pour intervenir dans son back-office.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--solid" href="<?= $h(url('communities/create')) ?>">Créer une communauté</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('communities')) ?>">Annuaire public</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/subscription-plans')) ?>">Formules d’accès</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/deployment')) ?>">Publications</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/tenant-recovery')) ?>">Récupération orpheline</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Communauté</th>
                            <th>Profil</th>
                            <th>Formule</th>
                            <th>Abonnement</th>
                            <th>Création</th>
                            <th style="text-align:right;">Comptes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                            <tr>
                                <td colspan="7" class="pa-empty">Aucune communauté enregistrée (hors espace technique).</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $t): ?>
                                <?php
                                $id = (int) ($t['id'] ?? 0);
                                $name = (string) ($t['name'] ?? '');
                                $slug = (string) ($t['slug'] ?? '');
                                $uc = (int) ($t['user_count'] ?? 0);
                                $planSlug = (string) ($t['plan_slug'] ?? 'free');
                                $planLabel = (string) ($planNameBySlug[$planSlug] ?? $planSlug);
                                $status = (string) ($t['subscription_status'] ?? 'none');
                                $statusLabel = (string) ($statusLabels[$status] ?? 'Statut inconnu');
                                $publicUrl = $slug !== '' ? url('c/' . rawurlencode($slug)) : '';
                                $editUrl = $id > 0 ? url('admin/tenants/' . $id . '/edit') : '';
                                $interventionUrl = $id > 1 ? url('admin/system/tenants/' . $id . '/intervention') : '';
                                $tenantType = (string) ($t['tenant_type'] ?? 'full');
                                $typeMeta = [
                                    'full' => ['label' => 'Complet', 'pill' => 'slate'],
                                    'effectifs' => ['label' => 'Effectifs', 'pill' => 'blue'],
                                    'atak' => ['label' => 'ATAK', 'pill' => 'amber'],
                                ];
                                $typeInfo = $typeMeta[$tenantType] ?? ['label' => $tenantType, 'pill' => 'slate'];
                                ?>
                                <tr>
                                    <td>
                                        <p class="pa-name"><?= $h($name) ?></p>
                                        <?php if ($publicUrl !== ''): ?>
                                            <p class="pa-sub"><a href="<?= $h($publicUrl) ?>" target="_blank" rel="noopener">Page publique</a></p>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="pa-pill pa-pill--<?= $h((string) $typeInfo['pill']) ?>"><?= $h((string) $typeInfo['label']) ?></span></td>
                                    <td><span class="pa-pill pa-pill--mint"><?= $h($planLabel) ?></span></td>
                                    <td><?= $h($statusLabel) ?></td>
                                    <td><?= $h($fmt($t['created_at'] ?? null)) ?></td>
                                    <td style="text-align:right;font-weight:800;"><?= $uc ?></td>
                                    <td>
                                        <?php if ($editUrl !== ''): ?>
                                            <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.5rem;">
                                                <a class="pa-btn pa-btn--line" href="<?= $h($editUrl) ?>">Administrer</a>
                                                <?php if ($interventionUrl !== ''): ?>
                                                    <a class="pa-btn pa-btn--solid" href="<?= $h($interventionUrl) ?>">Accès super tenant</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <section class="pa-card" style="margin-top:1.25rem;" aria-labelledby="plans-heading">
                <p class="pa-card__kicker">Offre</p>
                <h2 id="plans-heading" class="pa-card__title">Formules d’accès au service</h2>
                <p class="pa-card__help">Paliers proposés aux communautés. Pour en attribuer un, ouvrez Administrer dans le tableau.</p>
                <?php if ($plansError !== ''): ?>
                    <p class="pa-flash pa-flash--warn" style="margin-top:1rem;"><?= $h($plansError) ?></p>
                <?php elseif ($plans === []): ?>
                    <p class="pa-card__help" style="margin-top:1rem;">Aucune formule enregistrée.</p>
                <?php else: ?>
                    <div class="pa-table-wrap" style="margin-top:1rem;box-shadow:none;">
                        <table class="pa-table" style="min-width:36rem;">
                            <thead>
                                <tr>
                                    <th>Intitulé</th>
                                    <th>Ordre</th>
                                    <th>Paiement récurrent</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $p): ?>
                                    <?php
                                    $pid = (int) ($p['id'] ?? 0);
                                    $pname = (string) ($p['name'] ?? '');
                                    $sort = (int) ($p['sort_order'] ?? 0);
                                    $m = trim((string) ($p['stripe_price_id_monthly'] ?? ''));
                                    $y = trim((string) ($p['stripe_price_id_yearly'] ?? ''));
                                    $billing = ($m !== '' || $y !== '') ? 'Réglages présents' : 'Non renseigné';
                                    ?>
                                    <tr>
                                        <td class="pa-name"><?= $h($pname) ?></td>
                                        <td><?= $sort ?></td>
                                        <td><?= $h($billing) ?></td>
                                        <td>
                                            <?php if ($pid > 0): ?>
                                                <a href="<?= $h(url('admin/system/subscription-plans/' . $pid . '/edit')) ?>">Modifier le palier</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
