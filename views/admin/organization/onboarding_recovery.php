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

$successFlash = \App\Core\Session::getFlash('success');
$errorFlash = \App\Core\Session::getFlash('error');

$shortcuts = [
    ['href' => url('back-office/configuration-initiale'), 'title' => 'Configuration initiale', 'desc' => 'Logo, contact, inscription et modules visibles.'],
    ['href' => url('back-office/community'), 'title' => 'Identité de la communauté', 'desc' => 'Nom, langue, visibilité et options générales.'],
    ['href' => url('back-office/organisation-effectifs'), 'title' => 'Structure des effectifs', 'desc' => 'Organigramme, groupes, sections et équipes.'],
    ['href' => url('back-office/onboarding-members'), 'title' => 'Onboarding membres', 'desc' => 'Suivi des nouveaux arrivants et relances.'],
];
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-onboarding-recovery.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="bo-recovery">
    <header class="bo-recovery__hero">
        <div class="bo-recovery__hero-inner">
            <div class="bo-recovery__hero-main">
                <a href="<?= htmlspecialchars(url('back-office/configuration'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__back">← Paramètres avancés</a>
                <p class="bo-recovery__eyebrow">Communauté · Premiers pas</p>
                <h1 class="bo-recovery__title">
                    Aide après inscription
                    <?php if ($communityName !== ''): ?>
                        <span class="block text-lg font-bold text-neutral-400 mt-1"><?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </h1>
                <p class="bo-recovery__lead">
                    Cet assistant repère les éléments manquants par rapport au parcours de création complet d’Athena.
                    Complétez la checklist ci-dessous ou appliquez un modèle minimal français — sans supprimer ce qui existe déjà.
                </p>
            </div>
            <div class="bo-recovery__hero-actions">
                <div class="bo-recovery__progress-card" aria-label="Progression du rattrapage">
                    <p class="bo-recovery__progress-kicker">Configuration essentielle</p>
                    <p class="bo-recovery__progress-value"><?= $percent ?>%</p>
                    <p class="bo-recovery__progress-meta"><?= $done ?>/<?= $total ?> point<?= $total > 1 ? 's' : '' ?> validé<?= $done > 1 ? 's' : '' ?></p>
                    <div class="bo-recovery__progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>">
                        <div class="bo-recovery__progress-fill" style="width: <?= $percent ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="bo-recovery__deck">
        <div class="bo-recovery__deck-inner">
            <?php if ($successFlash): ?>
                <div class="bo-recovery__flash bo-recovery__flash--ok" role="status"><?= htmlspecialchars((string) $successFlash, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($errorFlash): ?>
                <div class="bo-recovery__flash bo-recovery__flash--err" role="alert"><?= htmlspecialchars((string) $errorFlash, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (!$needs): ?>
                <section class="bo-recovery__success" role="status">
                    <h2>Tout est en ordre</h2>
                    <p>
                        Les points essentiels du parcours de création sont couverts pour votre communauté.
                        Vous pouvez poursuivre la personnalisation ou suivre l’accueil des nouveaux membres.
                    </p>
                    <div class="bo-recovery__success-actions">
                        <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__link-btn">Retour au back-office</a>
                        <a href="<?= htmlspecialchars(url('back-office/onboarding-members'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__link-btn">Suivi onboarding membres</a>
                    </div>
                </section>
            <?php else: ?>
                <section class="bo-recovery__panel" aria-labelledby="recovery-checklist-title">
                    <div class="bo-recovery__panel-head">
                        <h2 id="recovery-checklist-title">Checklist de rattrapage</h2>
                        <p>
                            <?= $pendingCount ?> point<?= $pendingCount > 1 ? 's' : '' ?> restant<?= $pendingCount > 1 ? 's' : '' ?> sur <?= $total ?>.
                            Les étapes marquées « correction automatique » peuvent être traitées en un clic plus bas.
                        </p>
                    </div>
                    <ul class="bo-recovery__checklist">
                        <?php foreach ($checklist as $item): ?>
                            <?php
                            $isDone = !empty($item['done']);
                            $autoFix = !empty($item['auto_fixable']);
                            $actionHref = trim((string) ($item['action_href'] ?? ''));
                            $actionLabel = trim((string) ($item['action_label'] ?? 'Ouvrir'));
                            ?>
                            <li class="bo-recovery__check-item">
                                <div class="bo-recovery__check-main">
                                    <span class="bo-recovery__check-icon <?= $isDone ? 'bo-recovery__check-icon--ok' : 'bo-recovery__check-icon--todo' ?>" aria-hidden="true">
                                        <?= $isDone ? '✓' : '!' ?>
                                    </span>
                                    <div>
                                        <p class="bo-recovery__check-label"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if (!$isDone): ?>
                                            <p class="bo-recovery__check-detail"><?= htmlspecialchars((string) ($item['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                            <div class="bo-recovery__check-tags">
                                                <span class="bo-recovery__tag <?= $autoFix ? 'bo-recovery__tag--auto' : 'bo-recovery__tag--manual' ?>">
                                                    <?= $autoFix ? 'Correction automatique possible' : 'À faire manuellement' ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <p class="bo-recovery__check-detail">Validé pour cette communauté.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($actionHref !== '' && !$isDone): ?>
                                    <div class="bo-recovery__check-action">
                                        <a href="<?= htmlspecialchars($actionHref, ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__link-btn"><?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?></a>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <?php if ($canAutoApply): ?>
                    <section class="bo-recovery__panel" aria-labelledby="recovery-auto-title">
                        <div class="bo-recovery__panel-head">
                            <h2 id="recovery-auto-title">Correction rapide</h2>
                            <p>Applique un socle minimal pour repartir sur des bases saines, sans effacer vos données.</p>
                        </div>
                        <div class="bo-recovery__apply">
                            <?php if ($autoSummary !== ''): ?>
                                <p><?= htmlspecialchars($autoSummary, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <form method="post" action="<?= htmlspecialchars(url('back-office/onboarding-recovery/apply'), ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__apply-form">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="bo-recovery__submit">Appliquer le modèle français minimal</button>
                                <p class="bo-recovery__apply-note">Les rôles, unités et grades déjà présents sont conservés. Seuls les éléments manquants sont complétés.</p>
                            </form>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>

            <section class="bo-recovery__panel" aria-labelledby="recovery-shortcuts-title">
                <div class="bo-recovery__panel-head">
                    <h2 id="recovery-shortcuts-title">Écrans utiles ensuite</h2>
                    <p>Poursuivez la mise en place depuis les modules liés au parcours de création.</p>
                </div>
                <div class="bo-recovery__apply">
                    <div class="bo-recovery__shortcuts">
                        <?php foreach ($shortcuts as $shortcut): ?>
                            <a href="<?= htmlspecialchars((string) $shortcut['href'], ENT_QUOTES, 'UTF-8') ?>" class="bo-recovery__shortcut">
                                <strong><?= htmlspecialchars((string) $shortcut['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars((string) $shortcut['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
