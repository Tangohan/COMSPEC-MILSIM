<?php

declare(strict_types=1);

/**
 * Outils opérateur : tous les outils du membre sur une page (OperatorHubController::index).
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

$card = static function (array $tool, bool $pinned) use ($h, $opxIcon, $favUrl, $csrfToken): string {
    $badge = is_array($tool['badge'] ?? null) ? $tool['badge'] : null;
    $search = mb_strtolower($tool['label'] . ' ' . $tool['desc'] . ' ' . ($tool['keywords'] ?? ''));
    ob_start(); ?>
    <li class="opx-card<?= $pinned ? ' opx-card--pinned' : '' ?>" data-opx-tool="<?= $h($tool['key']) ?>" data-opx-search="<?= $h($search) ?>">
        <a class="opx-card__link" href="<?= $h(url($tool['path'])) ?>">
            <span class="opx-card__icon"><?= $opxIcon((string) $tool['icon']) ?></span>
            <span class="opx-card__body">
                <strong class="opx-card__title"><?= $h($tool['label']) ?></strong>
                <span class="opx-card__desc"><?= $h($tool['desc']) ?></span>
                <?php if ($badge !== null): ?>
                    <span class="opx-pill opx-pill--<?= $h($badge['tone']) ?>"><?= $h($badge['label']) ?></span>
                <?php endif; ?>
            </span>
        </a>
        <form class="opx-card__fav" method="post" action="<?= $h($favUrl) ?>" data-opx-fav>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="tool" value="<?= $h($tool['key']) ?>">
            <button type="submit" class="opx-fav<?= !empty($tool['favorite']) ? ' is-on' : '' ?>" aria-pressed="<?= !empty($tool['favorite']) ? 'true' : 'false' ?>"
                    title="<?= !empty($tool['favorite']) ? 'Retirer des épinglés' : 'Épingler en haut de la page' ?>">
                <?= $opxIcon('star', 'opx-ico opx-ico--sm') ?>
                <span class="opx-sr"><?= !empty($tool['favorite']) ? 'Retirer des épinglés' : 'Épingler' ?> : <?= $h($tool['label']) ?></span>
            </button>
        </form>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<link href="<?= $h(asset_url('assets/css/operateur-hub.css')) ?>" rel="stylesheet">

<div class="opx" data-opx-hub>
    <header class="opx-hero">
        <div class="opx-hero__inner">
            <div class="opx-hero__copy">
                <h1>Outils opérateur</h1>
                <p class="opx-hero__lead">
                    <span translate="no"><?= $h($hubCallsign) ?></span><?php if ($hubUnit !== ''): ?> · <span translate="no"><?= $h($hubUnit) ?></span><?php endif; ?>.
                    Tout ce qui vous sert avant, pendant et après la mission.
                </p>
            </div>
            <?php if ($hubFigures !== []): ?>
                <ul class="opx-figures">
                    <?php foreach ($hubFigures as $f): ?>
                        <li>
                            <a class="opx-figure opx-figure--<?= $h($f['tone']) ?>" href="<?= $h(url($f['href'])) ?>">
                                <span class="opx-figure__label"><?= $h($f['label']) ?></span>
                                <strong class="opx-figure__value"><?= $h($f['value']) ?></strong>
                                <span class="opx-figure__hint"><?= $h($f['hint']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </header>

    <div class="opx-wrap">
        <?php if ($hubToolCount === 0): ?>
            <div class="opx-empty">
                <h2>Aucun outil pour l’instant</h2>
                <p>Votre communauté n’a ouvert aucun outil à votre compte. Voyez un responsable.</p>
            </div>
        <?php else: ?>
            <div class="opx-search">
                <label class="opx-sr" for="opx-search-input">Chercher un outil</label>
                <?= $opxIcon('search', 'opx-ico opx-search__ico') ?>
                <input id="opx-search-input" type="search" autocomplete="off" placeholder="Chercher un outil" data-opx-search-input>
                <kbd class="opx-search__key" aria-hidden="true">/</kbd>
            </div>
            <p class="opx-empty opx-empty--inline" data-opx-noresult hidden>Aucun outil ne correspond. Essayez un autre mot.</p>

            <section class="opx-section" data-opx-section aria-labelledby="opx-sec-fav">
                <div class="opx-section__head">
                    <h2 id="opx-sec-fav">Épinglés</h2>
                    <p>Vos raccourcis, toujours en haut.</p>
                </div>
                <ul class="opx-grid" data-opx-favlist>
                    <?php foreach ($hubLayout['favorites'] as $tool): ?>
                        <?= $card($tool, true) ?>
                    <?php endforeach; ?>
                </ul>
                <p class="opx-hint" data-opx-favempty<?= $hubLayout['favorites'] !== [] ? ' hidden' : '' ?>>Rien d’épinglé. Cliquez l’étoile d’un outil pour le garder ici.</p>
            </section>

            <?php foreach ($hubLayout['groups'] as $group): ?>
                <section class="opx-section" data-opx-section aria-labelledby="opx-sec-<?= $h($group['key']) ?>">
                    <div class="opx-section__head">
                        <h2 id="opx-sec-<?= $h($group['key']) ?>"><?= $h($group['label']) ?></h2>
                        <p><?= $h($group['desc']) ?></p>
                    </div>
                    <ul class="opx-grid">
                        <?php foreach ($group['tools'] as $tool): ?>
                            <?= $card($tool, false) ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/operateur-hub.js')) ?>" defer></script>
