<?php
declare(strict_types=1);

/**
 * Tableau de bord — zone « Catalogue » : formations et tenues mises en avant par l’unité.
 * Composants Alpine : public/assets/js/dashboard-catalog.js (trainingShowcase / kitShowcase).
 *
 * @var bool $showcase_training_feature
 * @var list<array<string,mixed>> $showcase_items
 * @var bool $showcase_kit_feature
 * @var list<array<string,mixed>> $showcase_kit_items
 * @var bool $can_manage_kit_pins
 */

$catEsc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$catTrainingOn = !empty($showcase_training_feature);
$catKitOn = !empty($showcase_kit_feature);
$catCourses = is_array($showcase_items ?? null) ? array_values($showcase_items) : [];
$catKits = is_array($showcase_kit_items ?? null) ? array_values($showcase_kit_items) : [];
$catCanManageTraining = function_exists('can') && (can('training.create') || can('training.manage'));
$catCanManageKits = !empty($can_manage_kit_pins) || \App\Authorization\DashboardPinsAccess::canManage();
$catStudioUrl = function_exists('training_studio_url') ? training_studio_url() : url('formations');

$catInProgress = 0;
$catDone = 0;
$catEnrolled = 0;
foreach ($catCourses as $cc) {
    $st = (string) ($cc['member_status'] ?? '');
    if ($st !== '') {
        $catEnrolled++;
    }
    if ($st === 'in_progress' || $st === 'assigned') {
        $catInProgress++;
    } elseif ($st === 'completed') {
        $catDone++;
    }
}
$catCourseCount = count($catCourses);
$catCourseLead = $catCourseCount === 0
    ? 'Les parcours publiés par votre unité apparaîtront ici.'
    : ($catCourseCount === 1 ? '1 parcours publié' : $catCourseCount . ' parcours publiés');
if ($catCourseCount > 0 && $catInProgress > 0) {
    $catCourseLead .= ' · ' . $catInProgress . ' à poursuivre pour vous';
}
if ($catCourseCount > 0 && $catDone > 0) {
    $catCourseLead .= ' · ' . $catDone . ($catDone > 1 ? ' terminés' : ' terminé');
}
$catKitCount = count($catKits);
$catKitLead = $catKitCount === 0
    ? 'Les tenues de référence choisies par l’unité apparaîtront ici.'
    : ($catKitCount === 1 ? '1 tenue de référence' : $catKitCount . ' tenues de référence') . ' choisie' . ($catKitCount > 1 ? 's' : '') . ' par l’unité';

$catIcon = [
    'clock' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>',
    'cal' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4" stroke-linecap="round"/></svg>',
    'pin' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    'box' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7l9-4 9 4-9 4-9-4zM3 7v10l9 4 9-4V7" stroke-linejoin="round"/></svg>',
    'target' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4" stroke-linecap="round"/></svg>',
    'check' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12l5 5 9-10" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'prev' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'next' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'close' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round"/></svg>',
    'book' => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2V5z"/><path d="M4 19a2 2 0 012-2h13" stroke-linecap="round"/></svg>',
    'kit' => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M8 3l4 2 4-2 4 3-2 4h-2v11H8V10H6L4 6l4-3z" stroke-linejoin="round"/></svg>',
];
$catNav = static function (string $label) use ($catIcon): string {
    return '<div class="dash-cat__nav" x-show="canPrev || canNext" role="group" aria-label="Défilement ' . $label . '">'
        . '<button type="button" class="dash-cat__nav-btn" @click="scrollTrack(-1)" :disabled="!canPrev" aria-label="' . $label . ' précédentes">' . $catIcon['prev'] . '</button>'
        . '<button type="button" class="dash-cat__nav-btn" @click="scrollTrack(1)" :disabled="!canNext" aria-label="' . $label . ' suivantes">' . $catIcon['next'] . '</button>'
        . '</div>';
};
?>
<?php if ($catTrainingOn || $catKitOn): ?>
<div class="dash-zone dash-zone--catalog dash-cat dash-reveal" data-dash-reveal>
    <div class="dash-zone__inner dash-zone__inner--flush">
        <header class="dash-zone__head dash-zone__head--pad">
            <p class="dash-zone__kicker">Ressources</p>
            <h2 class="dash-zone__title">Catalogue</h2>
            <p class="dash-zone__lead">Formations et tenues mises en avant par votre unité.</p>
        </header>

        <?php if ($catTrainingOn): ?>
        <section class="dash-cat__section" id="dash-tour-formations" aria-labelledby="dash-showcase-heading"
            <?php if ($catCourses !== []): ?>x-data="trainingShowcase" @keydown.escape.window="close()"<?php endif; ?>>
            <div class="dash-cat__head">
                <div class="dash-cat__head-text">
                    <p class="dash-cat__kicker">Instruction</p>
                    <h3 id="dash-showcase-heading" class="dash-cat__title">Nos formations</h3>
                    <p class="dash-cat__lead"><?= $catEsc($catCourseLead) ?></p>
                </div>
                <div class="dash-cat__tools">
                    <?php if ($catEnrolled > 0): ?>
                    <a href="<?= url('formations/mes-formations') ?>" class="dash-cat__link" data-lms-module-entry="formation">Mes parcours</a>
                    <?php endif; ?>
                    <?php if ($catCourses !== []): ?>
                    <a href="<?= url('formations') ?>" class="dash-cat__link" data-lms-module-entry="formation">Tout le catalogue</a>
                    <?php endif; ?>
                    <?php if ($catCanManageTraining && $catCourses !== []): ?>
                    <a href="<?= $catEsc($catStudioUrl) ?>" class="dash-cat__link dash-cat__link--admin">Gérer</a>
                    <?php endif; ?>
                    <?php if ($catCourseCount > 1): ?><?= $catNav('Formations') ?><?php endif; ?>
                </div>
            </div>

            <?php if ($catCourses === []): ?>
            <div class="dash-cat__empty">
                <span class="dash-cat__empty-icon"><?= $catIcon['book'] ?></span>
                <div class="dash-cat__empty-text">
                    <?php if ($catCanManageTraining): ?>
                    <p class="dash-cat__empty-title">Aucune formation publiée</p>
                    <p>Publiez un parcours depuis le studio : il apparaîtra ici pour tous les membres de l’unité.</p>
                    <?php else: ?>
                    <p class="dash-cat__empty-title">Pas encore de formation mise en avant</p>
                    <p>Votre unité n’a pas encore publié de parcours. Le catalogue complet reste accessible.</p>
                    <?php endif; ?>
                </div>
                <div class="dash-cat__empty-actions">
                    <?php if ($catCanManageTraining): ?>
                    <a href="<?= $catEsc($catStudioUrl) ?>" class="dash-cat-btn dash-cat-btn--primary">Créer une formation</a>
                    <a href="<?= url('formations') ?>" class="dash-cat-btn dash-cat-btn--ghost" data-lms-module-entry="formation">Voir le catalogue</a>
                    <?php else: ?>
                    <a href="<?= url('formations') ?>" class="dash-cat-btn dash-cat-btn--primary" data-lms-module-entry="formation">Ouvrir le catalogue</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <ul x-ref="track" @scroll.passive.debounce.80ms="syncNav()" class="dash-cat__track" role="list" aria-label="Formations mises en avant">
                <?php foreach ($catCourses as $sc): ?>
                <?php
                $scId = (int) $sc['id'];
                $scEyebrow = implode(' · ', array_filter([
                    trim((string) ($sc['category'] ?? '')),
                    trim((string) ($sc['level_label'] ?? '')),
                ], static fn (string $s): bool => $s !== ''));
                $scStatus = (string) ($sc['member_status'] ?? '');
                $scPct = isset($sc['progress_pct']) && $sc['progress_pct'] !== null ? max(0, min(100, (int) $sc['progress_pct'])) : null;
                $scBadge = (string) ($sc['badge'] ?? 'open');
                $scCta = trim((string) ($sc['cta_label'] ?? '')) ?: 'Voir la formation';
                $scDuration = trim((string) ($sc['duration_label'] ?? ''));
                $scSession = trim((string) ($sc['session_label'] ?? ''));
                $scLocation = trim((string) ($sc['location'] ?? ''));
                $scThumb = trim((string) ($sc['thumb'] ?? ''));
                ?>
                <li class="dash-cat__item">
                    <article class="dash-cat-card" aria-labelledby="dash-cat-f-<?= $scId ?>">
                        <div class="dash-cat-card__media">
                            <?php if ($scThumb !== ''): ?>
                            <img src="<?= $catEsc($scThumb) ?>" alt="" width="320" height="180" loading="lazy" decoding="async">
                            <?php else: ?>
                            <span class="dash-cat-card__placeholder"><?= $catIcon['book'] ?></span>
                            <?php endif; ?>
                            <span class="dash-cat-pill dash-cat-pill--<?= $catEsc($scBadge) ?>"><?= $catEsc($sc['badge_label'] ?? '') ?></span>
                            <?php if (!empty($sc['is_mandatory'])): ?>
                            <span class="dash-cat-pill dash-cat-pill--flag">Obligatoire</span>
                            <?php elseif (!empty($sc['is_certifying'])): ?>
                            <span class="dash-cat-pill dash-cat-pill--flag">Certifiante</span>
                            <?php endif; ?>
                        </div>
                        <div class="dash-cat-card__body">
                            <?php if ($scEyebrow !== ''): ?>
                            <p class="dash-cat-card__eyebrow"><?= $catEsc($scEyebrow) ?></p>
                            <?php endif; ?>
                            <h4 id="dash-cat-f-<?= $scId ?>" class="dash-cat-card__title"><?= $catEsc($sc['title'] ?? '') ?></h4>
                            <?php if ($scDuration !== '' || $scSession !== '' || $scLocation !== ''): ?>
                            <ul class="dash-cat-card__facts">
                                <?php if ($scDuration !== ''): ?>
                                <li><?= $catIcon['clock'] ?><span><span class="dash-cat-sr">Durée : </span><?= $catEsc($scDuration) ?></span></li>
                                <?php endif; ?>
                                <?php if ($scSession !== ''): ?>
                                <li><?= $catIcon['cal'] ?><span>Session le <?= $catEsc($scSession) ?></span></li>
                                <?php endif; ?>
                                <?php if ($scLocation !== ''): ?>
                                <li><?= $catIcon['pin'] ?><span><span class="dash-cat-sr">Lieu : </span><?= $catEsc($scLocation) ?></span></li>
                                <?php endif; ?>
                            </ul>
                            <?php endif; ?>

                            <?php if ($scStatus === 'in_progress' && $scPct !== null): ?>
                            <div class="dash-cat-progress">
                                <div class="dash-cat-progress__row"><span>En cours</span><strong><?= $scPct ?>&nbsp;%</strong></div>
                                <div class="dash-cat-progress__bar" role="progressbar" aria-valuenow="<?= $scPct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Avancement">
                                    <span style="width:<?= $scPct ?>%"></span>
                                </div>
                            </div>
                            <?php elseif ($scStatus === 'completed'): ?>
                            <p class="dash-cat-status dash-cat-status--done"><?= $catIcon['check'] ?> Formation terminée</p>
                            <?php elseif ($scStatus !== ''): ?>
                            <p class="dash-cat-status"><?= $catEsc($sc['member_status_label'] ?? '') ?><?= $scStatus === 'assigned' ? ' · pas encore commencée' : '' ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="dash-cat-card__foot">
                            <a href="<?= $catEsc($sc['course_url'] ?? '#') ?>" class="dash-cat-btn dash-cat-btn--primary" data-lms-module-entry="formation"
                               aria-label="<?= $catEsc($scCta . ' : ' . ($sc['title'] ?? '')) ?>"><?= $catEsc($scCta) ?></a>
                            <button type="button" class="dash-cat-btn dash-cat-btn--ghost" @click="open(<?= $scId ?>, $event)" aria-haspopup="dialog"
                                    aria-label="<?= $catEsc('Détails : ' . ($sc['title'] ?? '')) ?>">Détails</button>
                        </div>
                    </article>
                </li>
                <?php endforeach; ?>
            </ul>

            <template x-if="openModal !== null && active()">
                <div class="dash-cat-modal">
                    <div class="dash-cat-modal__scrim" @click="close()" aria-hidden="true"></div>
                    <div class="dash-cat-modal__panel" data-cat-dialog role="dialog" aria-modal="true"
                         aria-labelledby="dash-cat-f-modal-title" @keydown.tab="trap($event)">
                        <button type="button" class="dash-cat-modal__close" data-cat-close @click="close()" aria-label="Fermer la fenêtre">
                            <?= $catIcon['close'] ?>
                        </button>
                        <div class="dash-cat-modal__art dash-cat-modal__art--photo">
                            <img :src="active().banner || active().thumb" alt="">
                        </div>
                        <div class="dash-cat-modal__content">
                            <p class="dash-cat__kicker" x-text="[active().category, active().level_label].filter(Boolean).join(' · ') || 'Formation'"></p>
                            <h2 id="dash-cat-f-modal-title" class="dash-cat-modal__title" x-text="active().title"></h2>
                            <dl class="dash-cat-modal__facts">
                                <div><dt>Statut</dt><dd x-text="active().badge_label"></dd></div>
                                <template x-if="active().duration_label"><div><dt>Durée</dt><dd x-text="active().duration_label"></dd></div></template>
                                <template x-if="active().session_label"><div><dt>Prochaine session</dt><dd x-text="active().session_label"></dd></div></template>
                                <template x-if="active().location"><div><dt>Lieu</dt><dd x-text="active().location"></dd></div></template>
                                <template x-if="active().member_status_label"><div><dt>Votre suivi</dt><dd x-text="active().member_status_label + (active().member_status === 'in_progress' && active().progress_pct !== null ? ' · ' + active().progress_pct + '\u00a0%' : '')"></dd></div></template>
                            </dl>
                            <template x-if="active().member_status === 'in_progress' && active().progress_pct !== null">
                                <div class="dash-cat-progress__bar dash-cat-progress__bar--lg" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="active().progress_pct" aria-label="Avancement">
                                    <span :style="'width:' + active().progress_pct + '%'"></span>
                                </div>
                            </template>
                            <p class="dash-cat-modal__text" x-text="active().description || 'Aucune description pour cette formation.'"></p>
                            <div class="dash-cat-modal__actions">
                                <a :href="active().course_url" class="dash-cat-btn dash-cat-btn--primary dash-cat-btn--lg" data-lms-module-entry="formation" x-text="active().cta_label || 'Ouvrir la formation'"></a>
                                <button type="button" class="dash-cat-btn dash-cat-btn--ghost dash-cat-btn--lg" @click="close()">Fermer</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($catKitOn): ?>
        <section class="dash-cat__section dash-cat__section--kits" id="dash-tour-kits" aria-labelledby="dash-kit-heading"
            <?php if ($catKits !== []): ?>x-data="kitShowcase" @keydown.escape.window="close()"<?php endif; ?>>
            <div class="dash-cat__head">
                <div class="dash-cat__head-text">
                    <p class="dash-cat__kicker">Équipement</p>
                    <h3 id="dash-kit-heading" class="dash-cat__title">Nos tenues</h3>
                    <p class="dash-cat__lead"><?= $catEsc($catKitLead) ?></p>
                </div>
                <div class="dash-cat__tools">
                    <?php if ($catKits !== []): ?>
                    <a href="<?= url('equipment') ?>" class="dash-cat__link">Tout l’équipement</a>
                    <?php endif; ?>
                    <?php if ($catCanManageKits && $catKits !== []): ?>
                    <a href="<?= url('back-office/dashboard-tenues') ?>" class="dash-cat__link dash-cat__link--admin">Choisir les tenues</a>
                    <?php endif; ?>
                    <?php if ($catKitCount > 1): ?><?= $catNav('Tenues') ?><?php endif; ?>
                </div>
            </div>

            <?php if ($catKits === []): ?>
            <div class="dash-cat__empty">
                <span class="dash-cat__empty-icon"><?= $catIcon['kit'] ?></span>
                <div class="dash-cat__empty-text">
                    <?php if ($catCanManageKits): ?>
                    <p class="dash-cat__empty-title">Aucune tenue mise en avant</p>
                    <p>Choisissez dans l’arsenal les tenues de référence à présenter aux membres sur leur tableau de bord.</p>
                    <?php else: ?>
                    <p class="dash-cat__empty-title">Pas encore de tenue mise en avant</p>
                    <p>Votre unité n’a pas encore choisi de tenue de référence. Toutes les tenues restent consultables dans l’équipement.</p>
                    <?php endif; ?>
                </div>
                <div class="dash-cat__empty-actions">
                    <?php if ($catCanManageKits): ?>
                    <a href="<?= url('back-office/dashboard-tenues') ?>" class="dash-cat-btn dash-cat-btn--primary">Choisir les tenues</a>
                    <a href="<?= url('equipment') ?>" class="dash-cat-btn dash-cat-btn--ghost">Ouvrir l’équipement</a>
                    <?php else: ?>
                    <a href="<?= url('equipment') ?>" class="dash-cat-btn dash-cat-btn--primary">Ouvrir l’équipement</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <ul x-ref="track" @scroll.passive.debounce.80ms="syncNav()" class="dash-cat__track" role="list" aria-label="Tenues mises en avant">
                <?php foreach ($catKits as $kit): ?>
                <?php
                $kId = (int) $kit['id'];
                $kFigure = trim((string) ($kit['figure'] ?? ''));
                $kBackdrop = trim((string) ($kit['backdrop'] ?? ''));
                $kCover = trim((string) ($kit['cover'] ?? ''));
                $kCount = (int) ($kit['item_count'] ?? 0);
                $kWeapon = trim((string) ($kit['primary_weapon'] ?? ''));
                $kKinds = is_array($kit['kinds'] ?? null) ? array_values($kit['kinds']) : [];
                $kNotes = trim((string) ($kit['notes'] ?? ''));
                ?>
                <li class="dash-cat__item dash-cat__item--kit">
                    <article class="dash-cat-card dash-cat-card--kit" aria-labelledby="dash-cat-k-<?= $kId ?>">
                        <div class="dash-cat-card__media dash-cat-card__media--kit<?= ($kFigure !== '' && !empty($kit['has_figure'])) ? ' has-figure' : '' ?>">
                            <?php if (!empty($kit['has_figure']) && $kFigure !== ''): ?>
                                <?php if ($kBackdrop !== ''): ?>
                                <img class="dash-cat-card__bg" src="<?= $catEsc($kBackdrop) ?>" alt="" loading="lazy" decoding="async">
                                <?php endif; ?>
                                <img class="dash-cat-card__figure" src="<?= $catEsc($kFigure) ?>" alt="" loading="lazy" decoding="async">
                            <?php elseif ($kCover !== ''): ?>
                                <img src="<?= $catEsc($kCover) ?>" alt="" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="dash-cat-card__placeholder"><?= $catIcon['kit'] ?><span>Pas de visuel</span></span>
                            <?php endif; ?>
                            <span class="dash-cat-pill dash-cat-pill--role"><?= $catEsc($kit['badge_label'] ?? 'Tenue') ?></span>
                        </div>
                        <div class="dash-cat-card__body">
                            <h4 id="dash-cat-k-<?= $kId ?>" class="dash-cat-card__title"><?= $catEsc($kit['title'] ?? '') ?></h4>
                            <?php if ($kCount > 0 || $kWeapon !== ''): ?>
                            <ul class="dash-cat-card__facts">
                                <?php if ($kCount > 0): ?>
                                <li><?= $catIcon['box'] ?><span><?= $kCount ?> élément<?= $kCount > 1 ? 's' : '' ?></span></li>
                                <?php endif; ?>
                                <?php if ($kWeapon !== ''): ?>
                                <li><?= $catIcon['target'] ?><span><span class="dash-cat-sr">Arme principale : </span><?= $catEsc($kWeapon) ?></span></li>
                                <?php endif; ?>
                            </ul>
                            <?php endif; ?>
                            <?php if ($kKinds !== []): ?>
                            <ul class="dash-cat-chips" aria-label="Contenu">
                                <?php foreach (array_slice($kKinds, 0, 3) as $kind): ?>
                                <li><?= $catEsc($kind) ?></li>
                                <?php endforeach; ?>
                                <?php if (count($kKinds) > 3): ?><li>+<?= count($kKinds) - 3 ?></li><?php endif; ?>
                            </ul>
                            <?php elseif ($kNotes !== ''): ?>
                            <p class="dash-cat-card__note"><?= $catEsc($kNotes) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="dash-cat-card__foot">
                            <a href="<?= $catEsc($kit['href'] ?? '#') ?>" class="dash-cat-btn dash-cat-btn--primary"
                               aria-label="<?= $catEsc('Ouvrir la tenue : ' . ($kit['title'] ?? '')) ?>">Ouvrir la tenue</a>
                            <button type="button" class="dash-cat-btn dash-cat-btn--ghost" @click="open(<?= $kId ?>, $event)" aria-haspopup="dialog"
                                    aria-label="<?= $catEsc('Aperçu : ' . ($kit['title'] ?? '')) ?>">Aperçu</button>
                        </div>
                    </article>
                </li>
                <?php endforeach; ?>
            </ul>

            <template x-if="openModal !== null && active()">
                <div class="dash-cat-modal">
                    <div class="dash-cat-modal__scrim" @click="close()" aria-hidden="true"></div>
                    <div class="dash-cat-modal__panel" data-cat-dialog role="dialog" aria-modal="true"
                         aria-labelledby="dash-cat-k-modal-title" @keydown.tab="trap($event)">
                        <button type="button" class="dash-cat-modal__close" data-cat-close @click="close()" aria-label="Fermer la fenêtre">
                            <?= $catIcon['close'] ?>
                        </button>
                        <div class="dash-cat-modal__art" :class="active().figure ? 'has-figure' : ''">
                            <template x-if="active().figure && active().backdrop">
                                <img :src="active().backdrop" alt="" class="dash-cat-modal__bg">
                            </template>
                            <template x-if="active().figure || active().cover">
                                <img :src="active().figure || active().cover" alt="" :class="active().figure ? 'dash-cat-modal__figure' : 'dash-cat-modal__cover'">
                            </template>
                            <template x-if="!active().figure && !active().cover">
                                <span class="dash-cat-card__placeholder"><?= $catIcon['kit'] ?><span>Pas de visuel</span></span>
                            </template>
                        </div>
                        <div class="dash-cat-modal__content">
                            <p class="dash-cat__kicker" x-text="active().badge_label || 'Tenue'"></p>
                            <h2 id="dash-cat-k-modal-title" class="dash-cat-modal__title" x-text="active().title"></h2>
                            <dl class="dash-cat-modal__facts" x-show="active().item_count > 0 || active().primary_weapon">
                                <template x-if="active().item_count > 0"><div><dt>Éléments</dt><dd x-text="active().item_count"></dd></div></template>
                                <template x-if="active().primary_weapon"><div><dt>Arme principale</dt><dd x-text="active().primary_weapon"></dd></div></template>
                            </dl>
                            <template x-if="active().sections && active().sections.length">
                                <div class="dash-cat-modal__block">
                                    <h3 class="dash-cat-modal__subtitle">Contenu</h3>
                                    <ul class="dash-cat-modal__sections">
                                        <template x-for="sec in active().sections" :key="sec.title">
                                            <li><span x-text="sec.title"></span><strong x-text="sec.count"></strong></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                            <div class="dash-cat-modal__block">
                                <h3 class="dash-cat-modal__subtitle">Consignes de l’unité</h3>
                                <p class="dash-cat-modal__text" x-text="active().notes || 'Aucune consigne particulière pour cette tenue.'"></p>
                            </div>
                            <div class="dash-cat-modal__actions">
                                <a :href="active().href" class="dash-cat-btn dash-cat-btn--primary dash-cat-btn--lg">Ouvrir la tenue</a>
                                <button type="button" class="dash-cat-btn dash-cat-btn--ghost dash-cat-btn--lg" @click="close()">Fermer</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
