<?php

declare(strict_types=1);

/**
 * Outils opérateur : tous les outils du membre sur une page (OperatorHubController::index), dans le gabarit et avec les
 * composants du tableau de bord (bandeau sombre, briefing en lignes, tuiles « dash-quick », zones « dash-zone »).
 *
 * @var array{favorites: list<array<string, mixed>>, groups: list<array{key: string, label: string, desc: string, tools: list<array<string, mixed>>}>} $hubLayout
 * @var string $hubCallsign
 * @var string $hubUnit
 * @var list<array{label: string, value: string, hint: string, tone: string, href: string}> $hubFigures
 * @var int $hubToolCount
 * @var string $csrfToken
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_icons.php';
$favUrl = url('operateur/favoris');
$badgeClass = ['ok' => 'cc-badge--on-dark', 'warn' => 'cc-badge--amber', 'wait' => 'cc-badge--amber', 'bad' => 'cc-badge--rose', 'info' => 'cc-badge--on-dark', 'idle' => 'cc-badge--on-dark'];
$briefTone = ['ok' => '', 'warn' => ' dash-hero__brief-item--hot', 'bad' => ' dash-hero__brief-item--hot', 'idle' => ' dash-hero__brief-item--idle'];

$tile = static function (array $tool) use ($h, $opxIcon, $favUrl, $csrfToken, $badgeClass): string {
    $badge = is_array($tool['badge'] ?? null) ? $tool['badge'] : null;
    $locked = !empty($tool['locked']);
    $search = mb_strtolower($tool['label'] . ' ' . $tool['desc'] . ' ' . ($tool['keywords'] ?? ''));
    $pinned = !empty($tool['favorite']);
    ob_start(); ?>
    <li class="opx-tile<?= $locked ? ' is-locked' : '' ?>" data-opx-tool="<?= $h($tool['key']) ?>" data-opx-search="<?= $h($search) ?>">
        <?php if ($locked): ?>
            <div class="dash-quick__item opx-tile__locked" aria-disabled="true">
                <span class="dash-quick__icon" aria-hidden="true"><?= $opxIcon((string) $tool['icon'], '') ?></span>
                <span class="dash-quick__text">
                    <strong><?= $h($tool['label']) ?></strong>
                    <em><?= $h($tool['desc']) ?></em>
                </span>
                <span class="opx-tile__lock"><?= $opxIcon('lock', '') ?> Accès réservé : <?= $h($tool['needs_label']) ?></span>
            </div>
        <?php else: ?>
            <a class="dash-quick__item" href="<?= $h(url($tool['path'])) ?>">
                <span class="dash-quick__icon" aria-hidden="true"><?= $opxIcon((string) $tool['icon'], '') ?></span>
                <span class="dash-quick__text">
                    <strong><?= $h($tool['label']) ?></strong>
                    <em><?= $h($tool['desc']) ?></em>
                </span>
                <?php if ($badge !== null): ?>
                    <span class="cc-badge <?= $h($badgeClass[$badge['tone']] ?? 'cc-badge--on-dark') ?>"><?= $h($badge['label']) ?></span>
                <?php endif; ?>
                <span class="dash-quick__arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg></span>
            </a>
            <form class="opx-pin" method="post" action="<?= $h($favUrl) ?>" data-opx-fav>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <input type="hidden" name="tool" value="<?= $h($tool['key']) ?>">
                <button type="submit" class="opx-pin__btn<?= $pinned ? ' is-on' : '' ?>" aria-pressed="<?= $pinned ? 'true' : 'false' ?>" title="<?= $pinned ? 'Retirer des épinglés' : 'Épingler en haut de la page' ?>">
                    <?= $opxIcon('star', '') ?>
                    <span class="sr-only"><?= $pinned ? 'Retirer des épinglés' : 'Épingler' ?> : <?= $h($tool['label']) ?></span>
                </button>
            </form>
        <?php endif; ?>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<div class="opx" data-opx-hub>
    <header class="opx-top">
        <div class="opx-top__inner">
            <div class="opx-top__copy">
                <h1 class="dash-hero__title">Outils opérateur<span class="dash-hero__title-dot" aria-hidden="true">.</span></h1>
                <p class="opx-top__lead">
                    <strong translate="no"><?= $h($hubCallsign) ?></strong><?php if ($hubUnit !== ''): ?> · <span translate="no"><?= $h($hubUnit) ?></span><?php endif; ?>.
                    Tout ce qui vous sert avant, pendant et après la mission.
                </p>
            </div>
            <?php if ($hubFigures !== []): ?>
                <ul class="dash-hero__brief opx-top__brief" aria-label="Votre situation">
                    <?php foreach ($hubFigures as $f): ?>
                        <li>
                            <a class="dash-hero__brief-item<?= $briefTone[$f['tone']] ?? '' ?>" href="<?= $h(url($f['href'])) ?>">
                                <span class="dash-hero__brief-k"><?= $h($f['label']) ?></span>
                                <span class="dash-hero__brief-v"><?= $h($f['value']) ?></span>
                                <span class="dash-hero__brief-meta"><?= $h($f['hint']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($hubToolCount === 0): ?>
        <section class="dash-zone dash-zone--actions">
            <div class="dash-zone__inner">
                <div class="cc-empty">
                    <p><strong>Aucun outil pour l’instant.</strong> Votre communauté n’a ouvert aucun outil à votre compte. Voyez un responsable.</p>
                </div>
            </div>
        </section>
    <?php else: ?>
        <div class="opx-searchbar">
            <div class="opx-searchbar__inner">
                <label class="sr-only" for="opx-search-input">Chercher un outil</label>
                <?= $opxIcon('search', 'opx-searchbar__ico') ?>
                <input id="opx-search-input" type="search" autocomplete="off" placeholder="Chercher un outil" data-opx-search-input>
                <kbd aria-hidden="true">/</kbd>
            </div>
        </div>

        <nav class="dash-quick" data-opx-section data-opx-favsection aria-labelledby="opx-sec-fav"<?php if ($hubLayout['favorites'] === []): ?> hidden<?php endif; ?>>
            <div class="dash-quick__inner">
                <h2 id="opx-sec-fav" class="dash-quick__title">Épinglés<span class="dash-quick__dot" aria-hidden="true">.</span></h2>
                <ul class="dash-quick__grid" data-opx-favlist>
                    <?php foreach ($hubLayout['favorites'] as $tool): ?>
                        <?= $tile($tool) ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>

        <?php foreach ($hubLayout['groups'] as $n => $group): ?>
            <section class="dash-zone<?= $n % 2 === 0 ? ' dash-zone--actions' : '' ?>" data-opx-section aria-labelledby="opx-sec-<?= $h($group['key']) ?>">
                <div class="dash-zone__inner">
                    <header class="dash-zone__head">
                        <h2 id="opx-sec-<?= $h($group['key']) ?>" class="dash-zone__title"><?= $h($group['label']) ?></h2>
                        <p class="dash-zone__lead"><?= $h($group['desc']) ?></p>
                    </header>
                    <ul class="dash-quick__grid opx-grid">
                        <?php foreach ($group['tools'] as $tool): ?>
                            <?= $tile($tool) ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="dash-zone" data-opx-noresult hidden>
            <div class="dash-zone__inner">
                <div class="cc-empty"><p>Aucun outil ne correspond. Essayez un autre mot.</p></div>
            </div>
        </section>
        <p class="opx-pinhint" data-opx-favempty<?php if ($hubLayout['favorites'] !== []): ?> hidden<?php endif; ?>>Astuce : l’étoile d’une tuile l’épingle en haut de cette page.</p>
    <?php endif; ?>
</div>
