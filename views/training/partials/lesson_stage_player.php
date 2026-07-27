<?php
declare(strict_types=1);

/**
 * Lecteur de leçon « scène 16:9 » — nouvelle lecture de formation.
 *
 * Coexiste avec lesson_slideshow_player.php (Swiper, images seules) : la bascule se fait
 * par formation via training_courses.lesson_player_mode. Ce partial ne remplace rien.
 *
 * Accepte deux formes de diapositive, pour lire les decks existants sans migration :
 *   - image   : { imageUrl, title?, caption? }        → forme historique
 *   - contenu : { title, body?|caption?, eyebrow?, cover? }
 *
 * @var array<string, mixed> $deck
 */

$deck = $deck ?? [];
$rawSlides = isset($deck['slides']) && is_array($deck['slides']) ? $deck['slides'] : [];

/** Normalisation : on ne garde que les diapositives qui affichent quelque chose. */
$slides = [];
foreach ($rawSlides as $raw) {
    if (!is_array($raw)) {
        continue;
    }
    $imageUrl = trim((string) ($raw['imageUrl'] ?? ''));
    $title = trim((string) ($raw['title'] ?? ''));
    $caption = trim((string) ($raw['caption'] ?? ''));
    $body = trim((string) ($raw['body'] ?? ''));
    if ($imageUrl === '' && $title === '' && $caption === '' && $body === '') {
        continue;
    }
    $slides[] = [
        'imageUrl' => $imageUrl,
        'title' => $title,
        'caption' => $caption,
        'body' => $body !== '' ? $body : $caption,
        'eyebrow' => trim((string) ($raw['eyebrow'] ?? '')),
        'cover' => !empty($raw['cover']),
    ];
}

if ($slides === []) {
    echo '<p class="text-slate-500">Aucune diapositive à afficher pour cette leçon.</p>';

    return;
}

$total = count($slides);
$playerId = 'stage-' . bin2hex(random_bytes(4));
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<div class="stage-player"
     data-stage-player
     id="<?= $h($playerId) ?>"
     tabindex="0"
     role="group"
     aria-roledescription="diaporama"
     aria-label="Parcours de la leçon, <?= (int) $total ?> étapes">

    <div class="stage-player__bar">
        <p class="stage-player__hint">
            Parcours interactif — <strong>flèches ← → du clavier</strong> ou boutons.
        </p>
        <div class="stage-player__controls">
            <button type="button" class="stage-player__btn" data-stage-prev>← Précédent</button>
            <button type="button" class="stage-player__btn stage-player__btn--go" data-stage-next>Suivant →</button>
        </div>
    </div>

    <div>
        <div class="stage-player__meter-head">
            <span>Progression des étapes</span>
            <span class="stage-player__meter-count" data-stage-meter-label>Étape 1 sur <?= (int) $total ?></span>
        </div>
        <div class="stage-player__meter" role="presentation">
            <div class="stage-player__meter-fill" data-stage-meter-fill style="width: <?= (int) round(100 / $total) ?>%"></div>
        </div>
    </div>

    <div class="stage-player__stage">
        <?php foreach ($slides as $i => $slide): ?>
            <?php
            $isImage = $slide['imageUrl'] !== '';
            $classes = 'stage-player__slide';
            if ($isImage) {
                $classes .= ' stage-player__slide--image';
            } elseif ($slide['cover'] || ($i === 0 && $slide['body'] === '')) {
                $classes .= ' stage-player__slide--cover';
            }
            ?>
            <div class="<?= $h($classes) ?>"
                 data-stage-slide
                 <?= $i === 0 ? '' : 'hidden' ?>
                 aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">

                <?php if ($isImage): ?>
                    <img src="<?= $h($slide['imageUrl']) ?>"
                         alt="<?= $h($slide['title'] !== '' ? $slide['title'] : 'Diapositive ' . ($i + 1)) ?>"
                         class="stage-player__img"
                         loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                    <?php if ($slide['title'] !== '' || $slide['caption'] !== ''): ?>
                        <div class="stage-player__caption">
                            <?php if ($slide['title'] !== ''): ?>
                                <p class="stage-player__caption-title"><?= $h($slide['title']) ?></p>
                            <?php endif; ?>
                            <?php if ($slide['caption'] !== ''): ?>
                                <p class="stage-player__caption-text"><?= $h($slide['caption']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($slide['eyebrow'] !== ''): ?>
                        <p class="stage-player__eyebrow"><?= $h($slide['eyebrow']) ?></p>
                    <?php else: ?>
                        <p class="stage-player__eyebrow">Étape <?= (int) ($i + 1) ?></p>
                    <?php endif; ?>

                    <?php if ($slide['title'] !== ''): ?>
                        <h3 class="stage-player__title"><?= $h($slide['title']) ?></h3>
                    <?php endif; ?>

                    <?php if ($slide['body'] !== ''): ?>
                        <?php if (str_contains($classes, 'stage-player__slide--cover')): ?>
                            <p class="stage-player__lead"><?= $h($slide['body']) ?></p>
                        <?php else: ?>
                            <div class="stage-player__body"><?= nl2br($h($slide['body'])) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total > 1): ?>
        <ul class="stage-player__dots">
            <?php foreach ($slides as $i => $slide): ?>
                <li>
                    <button type="button"
                            class="stage-player__dot"
                            data-stage-dot
                            aria-current="<?= $i === 0 ? 'true' : 'false' ?>"
                            aria-label="Aller à l’étape <?= (int) ($i + 1) ?><?= $slide['title'] !== '' ? ' : ' . $h($slide['title']) : '' ?>"></button>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p class="sr-only" aria-live="polite" data-stage-live></p>
</div>
