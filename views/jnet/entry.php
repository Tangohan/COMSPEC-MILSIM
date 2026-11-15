<?php
declare(strict_types=1);

/**
 * JNET — écran d'entrée d'un espace d'unité (JnetPortalController::unitEntry) : la séquence de connexion du gabarit
 * « Extranet » (insigne, nom, vérifications, accès autorisé), aux couleurs de l'unité, puis l'espace. Sans JavaScript :
 * la balise refresh conduit à l'espace ; le bouton « Entrer » y va tout de suite.
 *
 * @var array<string, mixed> $entryLook JnetUnitSpaceSettings::look()
 * @var string $entryUnit
 * @var int $entryUnitId
 * @var list<string> $entryChain
 * @var string $entryCommunity
 * @var string $entryOperator
 * @var string $entryRole
 * @var string $entryRight
 * @var int $entryUnread
 * @var string $entryKind
 * @var list<string> $entryFacts
 * @var string $entryNext
 * @var string $entryLeave
 * @var string $entryLeaveLabel
 */
use App\Services\Jnet\JnetUnitSpaceSettings;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$look = is_array($entryLook ?? null) ? $entryLook : JnetUnitSpaceSettings::look(JnetUnitSpaceSettings::defaults(), []);
$unit = (string) ($entryUnit ?? 'Unité');
$chain = is_array($entryChain ?? null) ? $entryChain : [];
$operator = trim((string) ($entryOperator ?? ''));
$next = (string) ($entryNext ?? url('jnet'));
$banner = is_string($look['banner'] ?? null) && $look['banner'] !== '' ? (string) $look['banner'] : asset_url(JnetUi::genericPhoto((int) ($entryUnitId ?? 0)));
$emblem = is_string($look['emblem'] ?? null) ? $look['emblem'] : null;
$welcome = (string) ($look['welcome'] ?? '');
$unread = (int) ($entryUnread ?? 0);
$facts = array_values(array_filter(array_map('strval', is_array($entryFacts ?? null) ? $entryFacts : [])));
$sub = implode(' · ', array_filter(array_merge([(string) ($entryKind ?? '')], $facts)));
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,700;1,900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_athena.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_entry.css')) ?>">
</head>
<body class="ja jx jx--<?= $h($look['theme']) ?>" style="<?= $h(JnetUnitSpaceSettings::cssVars($look)) ?>">
    <div class="jx-bg" style="background-image: url('<?= $h($banner) ?>')" aria-hidden="true"></div>
    <div class="jx-veil" aria-hidden="true"></div>

    <header class="ja-hd">
        <a class="ja-brand" href="<?= $h((string) ($entryLeave ?? url('jnet'))) ?>" title="Mon extranet" aria-label="ATHENA — Mon extranet"><?php $athSeasonal = athena_seasonal_logo_img(); ?><i aria-hidden="true"<?= $athSeasonal !== '' ? ' style="background:transparent"' : '' ?>><?= $athSeasonal !== '' ? $athSeasonal : 'A' ?></i><span>ATHENA<em>.</em></span></a>
        <div class="ja-hd__r">
            <span class="ja-who">extranet<?= ($entryCommunity ?? '') !== '' ? ' · ' . $h($entryCommunity) : '' ?></span>
            <a class="ja-ib ja-ib--nav" href="<?= $h((string) ($entryLeave ?? url('jnet'))) ?>"><?= JnetUi::icon('extranet') ?><span class="ja-ib__t">Mon extranet</span></a>
            <span class="jx-access"><?= $h($look['access_label']) ?></span>
        </div>
    </header>

    <main class="jx-main">
        <div class="jx-in">
            <?= JnetUi::crest((string) $look['monogram'], $emblem, 'ja-crest ja-crest--lg jx-crest') ?>
            <h1 class="ja-disp ja-disp--lg jx-name"><?= $h($unit) ?></h1>
            <?php if ($sub !== ''): ?><p class="jx-sub"><?= $h($sub) ?></p><?php endif; ?>
            <?php if (count($chain) > 1): ?><p class="jx-fil"><?= $h(implode(' › ', $chain)) ?></p><?php endif; ?>
            <?php if (($look['tagline'] ?? '') !== ''): ?>
                <p class="jx-tagline">« <?= $h($look['tagline']) ?> »</p>
            <?php endif; ?>

            <div class="jx-bar" aria-hidden="true"><i></i></div>
            <ol class="jx-log" aria-label="Connexion à l’espace">
                <li><span>Identité</span><b translate="no"><?= $h($operator !== '' ? $operator : 'Opérateur') ?></b></li>
                <li><span>Affectation à l’unité</span><b><?= $h((string) ($entryRole ?? '')) ?></b></li>
                <li><span>Droit d’accès</span><b><?= $h((string) ($entryRight ?? '')) ?></b></li>
                <li><span>Synchronisation de l’espace</span><b><?= $unread > 0 ? $unread . ' à lire' : 'À jour' ?></b></li>
            </ol>
            <p class="jx-stamp" role="status">Accès autorisé</p>

            <?php if ($welcome !== ''): ?>
                <p class="jx-welcome"><?= nl2br($h($welcome)) ?></p>
            <?php endif; ?>

            <div class="jx-actions">
                <a class="ja-b1 sm jx-enter" href="<?= $h($next) ?>" autofocus>Entrer dans l’espace</a>
                <a class="ja-b2 sm jx-leave" href="<?= $h((string) ($entryLeave ?? url('jnet'))) ?>"><?= $h((string) ($entryLeaveLabel ?? 'Mon extranet')) ?></a>
            </div>
        </div>
    </main>
</body>
</html>
