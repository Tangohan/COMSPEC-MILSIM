<?php
declare(strict_types=1);

/**
 * JNET — écran d'entrée d'un espace d'unité (JnetPortalController::unitEntry) : page plein écran aux couleurs de l'unité,
 * puis l'espace. Sans JavaScript : la balise refresh conduit à l'espace ; le bouton « Entrer » y va tout de suite.
 *
 * @var array<string, mixed> $entryLook JnetUnitSpaceSettings::look()
 * @var string $entryUnit
 * @var list<string> $entryChain
 * @var string $entryCommunity
 * @var string $entryOperator
 * @var string $entryRole
 * @var string $entryNext
 * @var string $entryLeave
 * @var string $entryLeaveLabel
 */
use App\Services\Jnet\JnetUnitSpaceSettings;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$look = is_array($entryLook ?? null) ? $entryLook : JnetUnitSpaceSettings::look(JnetUnitSpaceSettings::defaults(), []);
$unit = (string) ($entryUnit ?? 'Unité');
$chain = is_array($entryChain ?? null) ? $entryChain : [];
$operator = trim((string) ($entryOperator ?? ''));
$next = (string) ($entryNext ?? url('jnet'));
$banner = is_string($look['banner'] ?? null) ? $look['banner'] : '';
$emblem = is_string($look['emblem'] ?? null) ? $look['emblem'] : '';
$welcome = (string) ($look['welcome'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= $h(function_exists('html_lang') ? html_lang() : 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="<?= $welcome !== '' ? 6 : 3 ?>;url=<?= $h($next) ?>">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="<?= $h($look['bg']) ?>">
    <title><?= $h($unit) ?> — JNET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_entry.css?r=202611141800')) ?>">
</head>
<body class="jx jx--<?= $h($look['theme']) ?>" style="<?= $h(JnetUnitSpaceSettings::cssVars($look)) ?>">
    <?php if ($banner !== ''): ?>
        <div class="jx-bg" style="background-image: url('<?= $h($banner) ?>')" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="jx-veil" aria-hidden="true"></div>
    <div class="jx-grid" aria-hidden="true"></div>

    <header class="jx-top">
        <span class="jx-top__net">JNET<?= ($entryCommunity ?? '') !== '' ? ' · ' . $h($entryCommunity) : '' ?></span>
        <span class="jx-top__access"><?= $h($look['access_label']) ?></span>
    </header>

    <main class="jx-main">
        <div class="jx-emblem">
            <?php if ($emblem !== ''): ?>
                <img src="<?= $h($emblem) ?>" alt="Insigne de <?= $h($unit) ?>">
            <?php else: ?>
                <svg viewBox="0 0 80 92" aria-hidden="true"><path d="M40 3 76 15v27c0 22-15 38-36 47C19 80 4 64 4 42V15z" fill="var(--jn-space-panel)" stroke="var(--jn-unit)" stroke-width="3" stroke-linejoin="round"/></svg>
                <span class="jx-emblem__mark" aria-hidden="true"><?= $h($look['monogram']) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($chain !== []): ?>
            <p class="jx-chain"><?= $h(implode(' › ', $chain)) ?></p>
        <?php endif; ?>
        <h1 class="jx-name"><?= $h($unit) ?></h1>
        <?php if (($look['tagline'] ?? '') !== ''): ?>
            <p class="jx-tagline">« <?= $h($look['tagline']) ?> »</p>
        <?php endif; ?>

        <ol class="jx-seq" aria-label="Connexion à l’espace">
            <li><span>Identification</span><b translate="no"><?= $h($operator !== '' ? $operator : 'Opérateur') ?></b></li>
            <li><span>Habilitation</span><b><?= $h((string) ($entryRole ?? '')) ?></b></li>
            <li><span>Liaison</span><b>Chiffrée, établie</b></li>
        </ol>
        <div class="jx-bar" aria-hidden="true"><i></i></div>

        <?php if ($welcome !== ''): ?>
            <p class="jx-welcome"><?= nl2br($h($welcome)) ?></p>
        <?php endif; ?>

        <div class="jx-actions">
            <a class="jx-enter" href="<?= $h($next) ?>" autofocus>Entrer dans l’espace</a>
            <a class="jx-leave" href="<?= $h((string) ($entryLeave ?? url('jnet'))) ?>"><?= $h((string) ($entryLeaveLabel ?? 'Espace commun')) ?></a>
        </div>
    </main>
</body>
</html>
