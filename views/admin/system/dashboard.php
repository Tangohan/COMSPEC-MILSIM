<?php
declare(strict_types=1);
$kpis = $adminKpis ?? [];
$blockError = $adminKpiBlockError ?? null;
$envLabel = $adminPlatformEnvLabel ?? '—';
$healthUrl = $adminHealthCheckUrl ?? url('api/health');
$gate = \App\Core\Gate::getInstance();
$hasOrgPath = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
$isPlatformAdmin = $gate->allows('admin.system');
$isSupportHub = $gate->allows('site.support') && !$isPlatformAdmin;
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
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
                        Communautés, comptes, formules, alertes et exploitation — à l’échelle du site entier.
                        La vie quotidienne d’une unité se gère dans le <a href="<?= $h(url('back-office')) ?>">back-office</a>.
                    </p>
                    <?php endif; ?>
                    <div class="pa-hero__actions">
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('dashboard')) ?>">Portail</a>
                        <?php if ($hasOrgPath): ?>
                        <a class="pa-btn pa-btn--solid" href="<?= $h(url('back-office')) ?>">Back-office communauté</a>
                        <?php endif; ?>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Environnement</p>
                    <dl>
                        <div class="pa-hero__meta-row">
                            <dt>Mode</dt>
                            <dd><?= $h((string) $envLabel) ?></dd>
                        </div>
                    </dl>
                    <a class="pa-btn pa-btn--ghost pa-btn--block" style="margin-top:0.85rem;" href="<?= $h((string) $healthUrl) ?>" target="_blank" rel="noopener">Vérifier l’état des services</a>
                </aside>
            </div>
        </header>

        <nav class="pa-jump" aria-label="Sections du tableau de bord">
            <?php if ($isPlatformAdmin): ?>
            <a href="#hub-annuaire">Carte d’administration</a>
            <?php endif; ?>
            <a href="#hub-plateforme">Indicateurs</a>
            <a href="#hub-moderation">Modération</a>
            <a href="#hub-assistance">Activité récente</a>
        </nav>

        <div class="pa-panel">
            <?php if ($isPlatformAdmin): ?>
                <?php require base_path('views/admin/partials/platform_site_directory.php'); ?>
            <?php endif; ?>

            <section id="hub-plateforme" class="scroll-mt-24">
                <div class="pa-map__head">
                    <div>
                        <p class="pa-map__kicker">Indicateurs plateforme</p>
                        <h2 class="pa-map__title">Volume actuel, toutes communautés</h2>
                    </div>
                </div>
                <div class="pa-kpis">
                    <?php require base_path('views/admin/partials/kpi_row.php'); ?>
                </div>

                <?php
                $usagePrev = is_array($adminPlatformUsagePreview ?? null) ? $adminPlatformUsagePreview : [];
                $usageErr = isset($usagePrev['error']) ? (string) $usagePrev['error'] : '';
                $snap = is_array($usagePrev['snapshot'] ?? null) ? $usagePrev['snapshot'] : ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []];
                $cats = is_array($usagePrev['categories'] ?? null) ? $usagePrev['categories'] : [];
                $uk = is_array($usagePrev['kpis'] ?? null) ? $usagePrev['kpis'] : [];
                $ev7 = (int) ($uk['usage_events_in_period'] ?? 0);
                $actors7 = (int) ($uk['usage_distinct_actors_in_period'] ?? 0);
                $newUsers7 = (int) ($uk['users_registered_in_period'] ?? 0);
                ?>
                <section class="pa-card" aria-labelledby="usage-preview-heading">
                    <div class="pa-map__head" style="margin-bottom:0;">
                        <div>
                            <p class="pa-card__kicker">Sept derniers jours</p>
                            <h2 id="usage-preview-heading" class="pa-card__title">Aperçu d’usage</h2>
                            <p class="pa-card__help">Même source que la page d’indicateurs détaillés.</p>
                        </div>
                        <a class="pa-btn pa-btn--mint" href="<?= $h(url('admin/analytics?days=7')) ?>">Voir le détail</a>
                    </div>
                    <?php if ($usageErr !== ''): ?>
                        <p class="pa-flash pa-flash--warn" style="margin-top:1rem;"><?= $h($usageErr) ?></p>
                    <?php else: ?>
                        <dl class="pa-stats" style="margin-top:1rem;margin-bottom:0;">
                            <div class="pa-stat">
                                <dt>Événements d’usage</dt>
                                <dd><?= number_format($ev7, 0, ',', ' ') ?></dd>
                            </div>
                            <div class="pa-stat">
                                <dt>Acteurs distincts</dt>
                                <dd><?= number_format($actors7, 0, ',', ' ') ?></dd>
                            </div>
                            <div class="pa-stat">
                                <dt>Communautés actives</dt>
                                <dd><?= number_format((int) ($snap['tenants_with_events'] ?? 0), 0, ',', ' ') ?></dd>
                            </div>
                            <div class="pa-stat">
                                <dt>Nouveaux comptes</dt>
                                <dd><?= number_format($newUsers7, 0, ',', ' ') ?></dd>
                            </div>
                        </dl>
                        <?php if ($cats !== []): ?>
                            <div class="pa-chips">
                                <?php foreach ($cats as $c): ?>
                                    <?php
                                    $cl = (string) ($c['category'] ?? '');
                                    $cn = (int) ($c['events'] ?? 0);
                                    ?>
                                    <span class="pa-pill pa-pill--slate">
                                        <?= $cl === '' ? '—' : $h($cl) ?>
                                        · <?= number_format($cn, 0, ',', ' ') ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <p class="pa-card__help" style="margin-top:0.75rem;">Événements sur 24 h : <strong><?= number_format((int) ($snap['events_24h'] ?? 0), 0, ',', ' ') ?></strong></p>
                    <?php endif; ?>
                </section>
            </section>

            <?php if ($isSupportHub): ?>
                <?php require base_path('views/admin/partials/quick_actions_support_hub.php'); ?>
            <?php endif; ?>

            <section id="hub-moderation" class="scroll-mt-24">
                <?php require base_path('views/admin/partials/moderation_platform_overview.php'); ?>
            </section>

            <section id="hub-assistance" class="scroll-mt-24">
                <?php require base_path('views/admin/partials/recent_activity.php'); ?>
            </section>

            <?php require base_path('views/admin/partials/tenant_session_modules.php'); ?>
        </div>
    </div>
</div>
