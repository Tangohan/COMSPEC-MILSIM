<?php
declare(strict_types=1);
/*
 * Accueil de l'administration du site (administrateur de la plateforme uniquement).
 * Vue d'ensemble : ce qui demande une action, la santé, les chiffres clés, les accès rapides.
 * Le détail est en onglets à adresse propre (?onglet=sante|chiffres|activite) ; la console d'exploitation
 * (rendue par SystemOpsConsoleController, derrière sa double vérification) garde ses onglets.
 * Une carte dont la source est illisible affiche « Indisponible » : jamais d'erreur, jamais de chiffre inventé.
 */
$envLabel = $adminPlatformEnvLabel ?? '—';
$envRaw = strtolower((string) ($adminPlatformEnvRaw ?? ''));
$healthUrl = $adminHealthCheckUrl ?? url('api/health');
$h = static fn (string|int|float|null $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$appVersion = function_exists('platform_app_version') ? platform_app_version() : '';
$homeTab = (string) ($homeTab ?? 'vue');
$pilotTab = $homeTab;
$nf = static fn (int $n): string => number_format($n, 0, ',', ' ');
// Anciennes ancres du tableau de bord : renvoyées vers l'onglet qui les contient désormais.
$movedAnchors = [
    'hub-communication' => 'sante',
    'hub-comptes' => 'chiffres',
    'hub-inactifs' => 'chiffres',
    'hub-orgs-a-supprimer' => 'chiffres',
    'hub-annuaire' => 'activite',
    'hub-assistance' => 'activite',
];
$homeActions = is_array($homeActions ?? null) ? $homeActions : [];
$homeHealth = is_array($homeHealth ?? null) ? $homeHealth : [];
$homeFigures = is_array($homeFigures ?? null) ? $homeFigures : [];
$homeQuickLinks = is_array($homeQuickLinks ?? null) ? $homeQuickLinks : [];
$tileLabels = [
    'mod_reports' => 'Rapports d’erreurs du mod',
    'purge' => 'Demandes de purge de compte',
    'payments' => 'Paiements à vérifier',
    'migrations' => 'Migrations en attente',
    'abuse' => 'Alertes de détection',
    'reports' => 'Signalements de membres',
    'send_queue' => 'Files d’envoi du mod',
    'load' => 'Charge du site',
    'mod_version' => 'Version du mod en jeu',
    'site_errors' => 'Pages en erreur (24 h)',
    'deploy' => 'Dernier déploiement',
    'communities' => 'Communautés',
    'active' => 'Comptes actifs (30 jours)',
    'inactive' => 'Comptes inactifs (plus de 90 jours)',
    'subscriptions' => 'Abonnements actifs',
];
$loadLevels = [0 => 'Normal', 1 => 'Charge élevée', 2 => 'Charge forte', 3 => 'Saturation'];
$quickLabels = ['users' => 'Comptes', 'tenants' => 'Communautés', 'plans' => 'Formules', 'payments' => 'Paiements'];
/** Une carte : lien vers la page où agir. */
$tile = static function (array $c, string $mode) use ($h, $nf, $tileLabels, $loadLevels): void {
    $key = (string) $c['key'];
    $state = (string) $c['state'];
    $n = $c['n'];
    $x = is_array($c['extra'] ?? null) ? $c['extra'] : [];
    ?>
    <li class="ph-cell">
        <a class="ph-tile ph-tile--<?= $h($state) ?>" href="<?= $h(url((string) $c['href'])) ?>">
            <span class="ph-tile__label"><?= $h($tileLabels[$key] ?? $key) ?></span>
            <?php if ($state === 'na'): ?>
                <span class="ph-tile__text">Indisponible</span>
                <span class="ph-tile__note">Cette information ne peut pas être lue pour le moment.</span>
            <?php elseif ($mode === 'digest'): ?>
                <span class="ph-tile__text"><?= $h((string) ($x['value'] ?? '')) ?></span>
                <?php if ((string) ($x['hint'] ?? '') !== ''): ?>
                    <span class="ph-tile__note"><?= $h((string) $x['hint']) ?></span>
                <?php endif; ?>
            <?php elseif ($key === 'load'): ?>
                <span class="ph-tile__text"><?= $h($loadLevels[(int) $n] ?? 'Normal') ?></span>
                <span class="ph-tile__note">Palier mesuré, voir la page Charge et surcharge.</span>
            <?php elseif ($key === 'deploy'): ?>
                <span class="ph-tile__text"><?= $h((string) ($x['at'] ?? '')) ?></span>
                <?php if ((string) ($x['version'] ?? '') !== ''): ?>
                    <span class="ph-tile__note">Version <?= $h((string) $x['version']) ?></span>
                <?php endif; ?>
            <?php else: ?>
                <span class="ph-tile__value"><?= $h($nf((int) $n)) ?></span>
                <?php if ($mode === 'action' && $state === 'ok'): ?>
                    <span class="ph-tile__note">Rien à traiter</span>
                <?php elseif ($key === 'mod_reports'): ?>
                    <span class="ph-tile__note">nouveau(x) · <?= $h((int) ($x['wip'] ?? 0)) ?> en cours</span>
                <?php elseif ($key === 'purge'): ?>
                    <span class="ph-tile__note">en attente de décision</span>
                <?php elseif ($key === 'payments'): ?>
                    <span class="ph-tile__note">déclarés · le plus ancien attend depuis <?= $h((int) ($x['days'] ?? 0)) ?> jour(s)</span>
                <?php elseif ($key === 'migrations'): ?>
                    <span class="ph-tile__note"><?= (int) ($x['failed'] ?? 0) > 0 ? 'dont ' . $h((int) $x['failed']) . ' en échec' : 'à passer depuis la page Migrations' ?></span>
                <?php elseif ($key === 'abuse'): ?>
                    <span class="ph-tile__note">ouvertes · <?= $h((int) ($x['mod'] ?? 0)) ?> du mod, <?= $h((int) ($x['load'] ?? 0)) ?> de charge</span>
                <?php elseif ($key === 'reports'): ?>
                    <span class="ph-tile__note">à modérer</span>
                <?php elseif ($key === 'communities'): ?>
                    <span class="ph-tile__note">+<?= $h((int) ($x['new'] ?? 0)) ?> sur 30 jours (30 jours d’avant : +<?= $h((int) ($x['before'] ?? 0)) ?>)</span>
                <?php elseif ($key === 'active'): ?>
                    <span class="ph-tile__note">+<?= $h((int) ($x['new'] ?? 0)) ?> nouveaux comptes sur 30 jours (30 jours d’avant : +<?= $h((int) ($x['before'] ?? 0)) ?>)</span>
                <?php elseif ($key === 'inactive'): ?>
                    <span class="ph-tile__note">et <?= $h((int) ($x['never'] ?? 0)) ?> jamais connecté(s)</span>
                <?php elseif ($key === 'subscriptions'): ?>
                    <span class="ph-tile__note">communautés payantes ou en essai</span>
                <?php endif; ?>
            <?php endif; ?>
        </a>
    </li>
    <?php
};
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero pa-hero--compact">
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Administration du site</p>
                    <h1 class="pa-hero__title">Pilotage du site</h1>
                    <form class="pa-search" method="get" action="<?= $h(url('admin/recherche')) ?>" role="search">
                        <label for="pa-dash-search" class="ath-sr-only">Rechercher un compte ou une communauté</label>
                        <input id="pa-dash-search" name="q" type="search" autocomplete="off" spellcheck="false"
                               placeholder="Compte ou communauté : e-mail, pseudo, Steam, indicatif, code…">
                        <button type="submit" class="pa-btn pa-btn--solid">Rechercher</button>
                    </form>
                </div>
                <aside class="pa-hero__meta">
                    <dl style="margin:0;">
                        <div class="pa-hero__meta-row">
                            <dt>Mode</dt>
                            <dd><span class="pa-status<?= $envRaw !== '' && $envRaw !== 'production' && $envRaw !== 'prod' ? ' pa-status--warn' : '' ?>"><?= $h((string) $envLabel) ?></span></dd>
                        </div>
                        <?php if ($appVersion !== ''): ?>
                        <div class="pa-hero__meta-row">
                            <dt>Version</dt>
                            <dd><?= $h($appVersion) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <a class="pa-btn pa-btn--ghost pa-btn--sm pa-btn--block" style="margin-top:8px;" href="<?= $h((string) $healthUrl) ?>" target="_blank" rel="noopener">Vérifier l’état des services</a>
                </aside>
            </div>
        </header>

        <?php require base_path('views/admin/partials/pilotage_tabs.php'); ?>

        <div class="pa-panel">
<?php if ($homeTab === 'vue'): ?>
            <section id="hub-moderation" class="ph-block" aria-labelledby="ph-actions-title">
                <h2 id="ph-actions-title" class="ph-block__title">À traiter</h2>
                <ul class="ph-grid ph-grid--actions">
                    <?php foreach ($homeActions as $c) { $tile($c, 'action'); } ?>
                </ul>
            </section>

            <section id="hub-sante" class="ph-block" aria-labelledby="ph-health-title">
                <div class="ph-block__head">
                    <h2 id="ph-health-title" class="ph-block__title">Santé</h2>
                    <a class="pa-card__link" href="<?= $h(url('admin') . '?onglet=sante') ?>">Tout le détail →</a>
                </div>
                <ul class="ph-grid ph-grid--health">
                    <?php foreach ($homeHealth as $c) { $tile($c, in_array($c['key'], ['send_queue', 'mod_version', 'site_errors'], true) ? 'digest' : 'plain'); } ?>
                </ul>
            </section>

            <section id="hub-plateforme" class="ph-block" aria-labelledby="ph-figures-title">
                <div class="ph-block__head">
                    <h2 id="ph-figures-title" class="ph-block__title">Chiffres clés</h2>
                    <a class="pa-card__link" href="<?= $h(url('admin') . '?onglet=chiffres') ?>">Tout le détail →</a>
                </div>
                <ul class="ph-grid ph-grid--figures">
                    <?php foreach ($homeFigures as $c) { $tile($c, 'plain'); } ?>
                </ul>
            </section>

            <section class="ph-block" aria-labelledby="ph-quick-title">
                <h2 id="ph-quick-title" class="ph-block__title">Accès rapides</h2>
                <ul class="ph-quick">
                    <?php foreach ($homeQuickLinks as $q): ?>
                    <li><a class="pa-btn pa-btn--ghost" href="<?= $h(url((string) $q['href'])) ?>"><?= $h($quickLabels[(string) $q['key']] ?? (string) $q['key']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
<?php elseif ($homeTab === 'sante'): ?>
            <?php if (!empty($adminHealthRows)): ?>
                <?php require base_path('views/admin/partials/platform_health.php'); ?>
            <?php endif; ?>
            <?php require base_path('views/admin/partials/platform_communication.php'); ?>
<?php elseif ($homeTab === 'chiffres'): ?>
            <section id="hub-kpi" class="scroll-mt-24" aria-label="Indicateurs plateforme">
                <?php require base_path('views/admin/partials/kpi_row.php'); ?>
            </section>
            <?php require base_path('views/admin/partials/low_member_orgs_alert.php'); ?>
            <?php require base_path('views/admin/partials/accounts_overview.php'); ?>
            <?php
            $usagePrev = is_array($adminPlatformUsagePreview ?? null) ? $adminPlatformUsagePreview : [];
            $usageErr = isset($usagePrev['error']) ? (string) $usagePrev['error'] : '';
            $snap = is_array($usagePrev['snapshot'] ?? null) ? $usagePrev['snapshot'] : ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []];
            $cats = is_array($usagePrev['categories'] ?? null) ? $usagePrev['categories'] : [];
            $uk = is_array($usagePrev['kpis'] ?? null) ? $usagePrev['kpis'] : [];
            ?>
            <section id="hub-usage" class="pa-hub-grid scroll-mt-24">
                <section class="pa-card" aria-labelledby="usage-preview-heading">
                    <div class="pa-card__head">
                        <span class="pa-icon pa-icon--blue" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></span>
                        <div>
                            <p class="pa-card__kicker">Sept derniers jours</p>
                            <h2 id="usage-preview-heading" class="pa-card__title">Aperçu d’usage</h2>
                        </div>
                        <a class="pa-card__link" href="<?= $h(url('admin/analytics?days=7')) ?>">Voir le détail →</a>
                    </div>
                    <?php if ($usageErr !== ''): ?>
                        <p class="pa-flash pa-flash--warn" style="margin:0;"><?= $h($usageErr) ?></p>
                    <?php else: ?>
                        <dl class="pa-mini-stats">
                            <div><dt>Événements d’usage</dt><dd><?= $h($nf((int) ($uk['usage_events_in_period'] ?? 0))) ?></dd></div>
                            <div><dt>Acteurs distincts</dt><dd><?= $h($nf((int) ($uk['usage_distinct_actors_in_period'] ?? 0))) ?></dd></div>
                            <div><dt>Communautés actives</dt><dd><?= $h($nf((int) ($snap['tenants_with_events'] ?? 0))) ?></dd></div>
                            <div><dt>Nouveaux comptes</dt><dd><?= $h($nf((int) ($uk['users_registered_in_period'] ?? 0))) ?></dd></div>
                        </dl>
                        <?php if ($cats !== []): ?>
                            <div class="pa-chips">
                                <?php foreach ($cats as $c): ?>
                                    <span class="pa-pill pa-pill--slate"><?= (string) ($c['category'] ?? '') === '' ? '—' : $h((string) $c['category']) ?> · <?= $h($nf((int) ($c['events'] ?? 0))) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <p class="pa-card__foot">Événements sur 24 h : <strong><?= $h($nf((int) ($snap['events_24h'] ?? 0))) ?></strong></p>
                    <?php endif; ?>
                </section>
            </section>
<?php else: ?>
            <section id="hub-assistance" class="pa-hub-grid scroll-mt-24">
                <?php require base_path('views/admin/partials/moderation_platform_overview.php'); ?>
                <?php require base_path('views/admin/partials/recent_activity.php'); ?>
                <?php require base_path('views/admin/partials/moderation_audit_extracts.php'); ?>
            </section>
            <?php require base_path('views/admin/partials/platform_site_directory.php'); ?>
            <?php require base_path('views/admin/partials/tenant_session_modules.php'); ?>
<?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var moved = <?= json_encode($movedAnchors, JSON_UNESCAPED_SLASHES) ?>;
    var id = (location.hash || '').replace(/^#/, '');
    if (id && moved[id] && !document.getElementById(id) && <?= json_encode($homeTab) ?> !== moved[id]) {
        location.replace(location.pathname + '?onglet=' + moved[id] + '#' + id);
    }
})();
</script>
