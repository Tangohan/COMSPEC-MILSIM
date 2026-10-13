<?php
declare(strict_types=1);
$kpis = $adminKpis ?? [];
$blockError = $adminKpiBlockError ?? null;
$envLabel = $adminPlatformEnvLabel ?? '—';
$envRaw = strtolower((string) ($adminPlatformEnvRaw ?? ''));
$healthUrl = $adminHealthCheckUrl ?? url('api/health');
$gate = \App\Core\Gate::getInstance();
$hasOrgPath = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
$isPlatformAdmin = $gate->allows('admin.system');
$isSupportHub = $gate->allows('site.support') && !$isPlatformAdmin;
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$appVersion = function_exists('platform_app_version') ? platform_app_version() : '';
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker"><?= $isSupportHub ? 'Pilotage site · assistance' : 'Administration du site' ?></p>
                    <h1 class="pa-hero__title">Centre opérateur</h1>
                    <?php if ($isSupportHub): ?>
                    <p class="pa-hero__lead">
                        Indicateurs, journaux et synthèses pour l’équipe assistance.
                        Les réglages sensibles restent réservés aux administrateurs du site.
                        Pour une communauté, ouvrez le <a href="<?= $h(url('back-office')) ?>">back-office</a>.
                    </p>
                    <?php else: ?>
                    <p class="pa-hero__lead">
                        Communautés, comptes, formules, alertes et exploitation, à l’échelle du site entier.
                        La vie quotidienne d’une unité se gère dans le <a href="<?= $h(url('back-office')) ?>">back-office</a>.
                    </p>
                    <?php endif; ?>
                    <div class="pa-hero__actions">
                        <?php if ($isPlatformAdmin): ?>
                        <a class="pa-btn pa-btn--solid" href="<?= $h(url('admin/tenants')) ?>">Annuaire des communautés</a>
                        <?php endif; ?>
                        <?php if ($hasOrgPath): ?>
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('back-office')) ?>">Back-office communauté</a>
                        <?php endif; ?>
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('dashboard')) ?>">Portail</a>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Environnement</p>
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
                    <a class="pa-btn pa-btn--ghost pa-btn--sm pa-btn--block" style="margin-top:10px;" href="<?= $h((string) $healthUrl) ?>" target="_blank" rel="noopener">Vérifier l’état des services</a>
                </aside>
            </div>
        </header>

        <nav class="pa-jump" aria-label="Sections du tableau de bord">
            <a class="pa-jump__link" href="#hub-plateforme">Indicateurs</a>
            <?php if ($isPlatformAdmin): ?>
            <a class="pa-jump__link" href="#hub-annuaire">Outils du site</a>
            <?php endif; ?>
            <a class="pa-jump__link" href="#hub-moderation">Usage et modération</a>
            <a class="pa-jump__link" href="#hub-assistance">Activité récente</a>
        </nav>

        <div class="pa-panel">
            <section id="hub-plateforme" class="scroll-mt-24" aria-label="Indicateurs plateforme">
                <?php require base_path('views/admin/partials/kpi_row.php'); ?>
            </section>

            <?php if ($isSupportHub): ?>
                <?php require base_path('views/admin/partials/quick_actions_support_hub.php'); ?>
            <?php endif; ?>

            <?php if ($isPlatformAdmin): ?>
                <?php require base_path('views/admin/partials/platform_site_directory.php'); ?>
            <?php endif; ?>

            <?php
            $usagePrev = is_array($adminPlatformUsagePreview ?? null) ? $adminPlatformUsagePreview : [];
            $usageErr = isset($usagePrev['error']) ? (string) $usagePrev['error'] : '';
            $snap = is_array($usagePrev['snapshot'] ?? null) ? $usagePrev['snapshot'] : ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []];
            $cats = is_array($usagePrev['categories'] ?? null) ? $usagePrev['categories'] : [];
            $uk = is_array($usagePrev['kpis'] ?? null) ? $usagePrev['kpis'] : [];
            $ev7 = (int) ($uk['usage_events_in_period'] ?? 0);
            $actors7 = (int) ($uk['usage_distinct_actors_in_period'] ?? 0);
            $newUsers7 = (int) ($uk['users_registered_in_period'] ?? 0);
            $nf = static fn (int $n): string => number_format($n, 0, ',', ' ');
            ?>
            <section id="hub-moderation" class="pa-hub-grid scroll-mt-24">
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
                            <div><dt>Événements d’usage</dt><dd><?= $nf($ev7) ?></dd></div>
                            <div><dt>Acteurs distincts</dt><dd><?= $nf($actors7) ?></dd></div>
                            <div><dt>Communautés actives</dt><dd><?= $nf((int) ($snap['tenants_with_events'] ?? 0)) ?></dd></div>
                            <div><dt>Nouveaux comptes</dt><dd><?= $nf($newUsers7) ?></dd></div>
                        </dl>
                        <?php if ($cats !== []): ?>
                            <div class="pa-chips">
                                <?php foreach ($cats as $c): ?>
                                    <?php
                                    $cl = (string) ($c['category'] ?? '');
                                    $cn = (int) ($c['events'] ?? 0);
                                    ?>
                                    <span class="pa-pill pa-pill--slate"><?= $cl === '' ? '—' : $h($cl) ?> · <?= $nf($cn) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <p class="pa-card__foot">Événements sur 24 h : <strong><?= $nf((int) ($snap['events_24h'] ?? 0)) ?></strong></p>
                    <?php endif; ?>
                </section>

                <?php require base_path('views/admin/partials/moderation_platform_overview.php'); ?>
            </section>

            <section id="hub-assistance" class="pa-hub-grid scroll-mt-24">
                <?php require base_path('views/admin/partials/recent_activity.php'); ?>
                <?php require base_path('views/admin/partials/moderation_audit_extracts.php'); ?>
            </section>

            <?php require base_path('views/admin/partials/tenant_session_modules.php'); ?>
        </div>
    </div>
</div>
