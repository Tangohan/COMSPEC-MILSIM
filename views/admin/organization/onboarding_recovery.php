<?php
declare(strict_types=1);

/** @var array<string, mixed> $health */
/** @var array<string, mixed> $tenant */

$checklist = is_array($health['checklist'] ?? null) ? $health['checklist'] : [];
$progress = is_array($health['progress'] ?? null) ? $health['progress'] : ['done' => 0, 'total' => 0, 'percent' => 0];
$needs = !empty($health['needs_recovery']);
$canAutoApply = !empty($health['can_auto_apply']);
$autoSummary = trim((string) ($health['auto_apply_summary'] ?? ''));
$communityName = trim((string) ($tenant['name'] ?? ''));
$percent = max(0, min(100, (int) ($progress['percent'] ?? 0)));
$done = (int) ($progress['done'] ?? 0);
$total = (int) ($progress['total'] ?? 0);
$pendingCount = max(0, $total - $done);
$autoPendingCount = 0;
foreach ($checklist as $item) {
    if (empty($item['done']) && !empty($item['auto_fixable'])) {
        $autoPendingCount++;
    }
}

$successFlash = \App\Core\Session::getFlash('success');
$errorFlash = \App\Core\Session::getFlash('error');

$shortcuts = [
    ['href' => url('back-office/configuration-initiale'), 'title' => 'Configuration initiale', 'desc' => 'Logo, contact, inscription et modules visibles.'],
    ['href' => url('back-office/community'), 'title' => 'Identité de la communauté', 'desc' => 'Nom, langue, visibilité et options générales.'],
    ['href' => url('back-office/organisation-effectifs'), 'title' => 'Structure des effectifs', 'desc' => 'Organigramme, groupes, sections et équipes.'],
    ['href' => url('back-office/onboarding-members'), 'title' => 'Accueil des nouveaux membres', 'desc' => 'Suivi des arrivants et relances utiles.'],
];
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-onboarding-recovery.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="bo-recovery">
    <header class="bo-recovery__hero">
        <div class="bo-recovery__hero-inner">
            <div class="bo-recovery__hero-main">
                <a href="<?= htmlspecialchars(url('back-office/configuration'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__back">← Paramètres avancés</a>
                <p class="bo-recovery__eyebrow">Communauté · Premiers pas</p>
                <h1 class="bo-recovery__title">Aide après inscription</h1>
                <?php if ($communityName !== ''): ?>
                    <p class="bo-recovery__community"><?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <p class="bo-recovery__lead">
                    Cet assistant repère ce qui manque encore par rapport au parcours de création complet.
                    Complétez la liste ci-dessous, ou appliquez un démarrage type français — sans effacer ce qui existe déjà.
                </p>
            </div>
            <div class="bo-recovery__hero-actions">
                <a href="<?= htmlspecialchars(url('back-office/configuration-initiale'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__btn bo-recovery__btn--ghost">Configuration initiale</a>
                <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__btn bo-recovery__btn--solid">Centre de pilotage</a>
            </div>
        </div>
    </header>

    <div class="bo-recovery__deck">
        <?php if ($successFlash): ?>
            <div class="bo-recovery__flash bo-recovery__flash--ok" role="status"><?= htmlspecialchars((string) $successFlash, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($errorFlash): ?>
            <div class="bo-recovery__flash bo-recovery__flash--err" role="alert"><?= htmlspecialchars((string) $errorFlash, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="bo-recovery__kpi-grid" aria-label="Synthèse de la configuration">
            <div class="bo-recovery__kpi">
                <p class="bo-recovery__kpi-label">Progression</p>
                <p class="bo-recovery__kpi-value"><?= $percent ?>%</p>
                <p class="bo-recovery__kpi-meta"><?= $done ?> / <?= $total ?> point<?= $total > 1 ? 's' : '' ?> validé<?= $done > 1 ? 's' : '' ?></p>
                <div class="bo-recovery__kpi-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>">
                    <div class="bo-recovery__kpi-fill" style="width: <?= $percent ?>%"></div>
                </div>
            </div>
            <div class="bo-recovery__kpi">
                <p class="bo-recovery__kpi-label">Restants</p>
                <p class="bo-recovery__kpi-value"><?= $pendingCount ?></p>
                <p class="bo-recovery__kpi-meta"><?= $pendingCount === 0 ? 'Rien à rattraper' : 'À traiter encore' ?></p>
            </div>
            <div class="bo-recovery__kpi">
                <p class="bo-recovery__kpi-label">Correction rapide</p>
                <p class="bo-recovery__kpi-value"><?= $autoPendingCount ?></p>
                <p class="bo-recovery__kpi-meta"><?= $autoPendingCount === 0 ? 'Aucune disponible' : 'Peut être complété automatiquement' ?></p>
            </div>
            <div class="bo-recovery__kpi">
                <p class="bo-recovery__kpi-label">État</p>
                <p class="bo-recovery__kpi-value bo-recovery__kpi-value--status <?= $needs ? 'is-warn' : 'is-ok' ?>">
                    <?= $needs ? 'À compléter' : 'En ordre' ?>
                </p>
                <p class="bo-recovery__kpi-meta"><?= $needs ? 'Des étapes essentielles manquent' : 'Parcours de création couvert' ?></p>
            </div>
        </div>

        <?php if (!$needs): ?>
            <section class="bo-recovery__success" role="status" aria-labelledby="recovery-success-title">
                <div class="bo-recovery__success-icon" aria-hidden="true">✓</div>
                <div class="bo-recovery__success-body">
                    <h2 id="recovery-success-title">Tout est en ordre</h2>
                    <p>
                        Les points essentiels du parcours de création sont couverts pour votre communauté.
                        Vous pouvez poursuivre la personnalisation ou suivre l’accueil des nouveaux membres.
                    </p>
                    <div class="bo-recovery__success-actions">
                        <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__btn bo-recovery__btn--ink">Retour au centre de pilotage</a>
                        <a href="<?= htmlspecialchars(url('back-office/onboarding-members'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__btn bo-recovery__btn--quiet">Accueil des nouveaux membres</a>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <?php if ($canAutoApply): ?>
                <section class="bo-recovery__panel bo-recovery__panel--accent" aria-labelledby="recovery-auto-title">
                    <div class="bo-recovery__panel-head">
                        <h2 id="recovery-auto-title">Correction rapide</h2>
                        <p>Complète automatiquement ce qui peut l’être, sans effacer vos données existantes.</p>
                    </div>
                    <div class="bo-recovery__apply">
                        <?php if ($autoSummary !== ''): ?>
                            <p class="bo-recovery__apply-summary"><?= htmlspecialchars($autoSummary, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/onboarding-recovery/apply'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__apply-form">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="bo-recovery__btn bo-recovery__btn--primary">Appliquer le démarrage type français</button>
                            <p class="bo-recovery__apply-note">Les rôles, unités et grades déjà présents sont conservés. Seuls les éléments manquants sont complétés.</p>
                        </form>
                    </div>
                </section>
            <?php endif; ?>

            <section class="bo-recovery__panel" aria-labelledby="recovery-checklist-title">
                <div class="bo-recovery__panel-head">
                    <h2 id="recovery-checklist-title">Points à vérifier</h2>
                    <p>
                        <?= $pendingCount ?> point<?= $pendingCount > 1 ? 's' : '' ?> restant<?= $pendingCount > 1 ? 's' : '' ?> sur <?= $total ?>.
                        <?php if ($autoPendingCount > 0): ?>
                            Les étapes marquées « correction rapide » peuvent être traitées en un clic ci-dessus.
                        <?php else: ?>
                            Les étapes restantes demandent une action manuelle depuis l’écran indiqué.
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($checklist === []): ?>
                    <div class="bo-recovery__empty">
                        <div class="bo-recovery__empty-icon" aria-hidden="true">∅</div>
                        <p>Aucun point à afficher pour le moment</p>
                        <span>Rechargez la page ou revenez depuis les paramètres avancés si le diagnostic n’apparaît pas.</span>
                    </div>
                <?php else: ?>
                    <ul class="bo-recovery__checklist">
                        <?php foreach ($checklist as $item): ?>
                            <?php
                            $isDone = !empty($item['done']);
                            $autoFix = !empty($item['auto_fixable']);
                            $actionHref = trim((string) ($item['action_href'] ?? ''));
                            $actionLabel = trim((string) ($item['action_label'] ?? 'Ouvrir'));
                            ?>
                            <li class="bo-recovery__check-item<?= $isDone ? ' is-done' : '' ?>">
                                <div class="bo-recovery__check-main">
                                    <span class="bo-recovery__check-icon <?= $isDone ? 'bo-recovery__check-icon--ok' : 'bo-recovery__check-icon--todo' ?>" aria-hidden="true">
                                        <?= $isDone ? '✓' : '!' ?>
                                    </span>
                                    <div class="bo-recovery__check-copy">
                                        <p class="bo-recovery__check-label"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if (!$isDone): ?>
                                            <p class="bo-recovery__check-detail"><?= htmlspecialchars((string) ($item['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                            <div class="bo-recovery__check-tags">
                                                <span class="bo-recovery__tag <?= $autoFix ? 'bo-recovery__tag--auto' : 'bo-recovery__tag--manual' ?>">
                                                    <?= $autoFix ? 'Correction rapide possible' : 'À faire manuellement' ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <p class="bo-recovery__check-detail">Validé pour cette communauté.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($actionHref !== '' && !$isDone): ?>
                                    <div class="bo-recovery__check-action">
                                        <a href="<?= htmlspecialchars($actionHref, ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__btn bo-recovery__btn--quiet"><?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?></a>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="bo-recovery__panel" aria-labelledby="recovery-shortcuts-title">
            <div class="bo-recovery__panel-head">
                <h2 id="recovery-shortcuts-title">Écrans utiles ensuite</h2>
                <p>Poursuivez la mise en place depuis les modules liés au parcours de création.</p>
            </div>
            <div class="bo-recovery__shortcuts">
                <?php foreach ($shortcuts as $shortcut): ?>
                    <a href="<?= htmlspecialchars((string) $shortcut['href'], ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__shortcut">
                        <strong><?= htmlspecialchars((string) $shortcut['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars((string) $shortcut['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
