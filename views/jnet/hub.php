<?php
declare(strict_types=1);

/**
 * JNET — extranet du membre : « Une unité. Un espace. » (JnetPortalController::home pour un membre, et /jnet/espaces).
 * Page autonome : registre des unités visibles du lecteur (droit, non-lus, Entrer), puis ses rubriques personnelles.
 * Chaque lien mène à une page qui refait ses propres vérifications ; rien n'est affiché que le lecteur ne voie déjà.
 *
 * @var string $hubCommunity
 * @var string $hubOperator
 * @var list<array<string, mixed>> $hubMine
 * @var list<array<string, mixed>> $hubOthers
 * @var array{label: string, href: string, unread: int} $hubOrg
 * @var list<array<string, mixed>> $hubSections
 * @var int $hubUnreadMail
 * @var bool $hubCanTba
 */

use App\Services\Jnet\JnetMemberHub;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$mine = is_array($hubMine ?? null) ? $hubMine : [];
$others = is_array($hubOthers ?? null) ? $hubOthers : [];
$org = is_array($hubOrg ?? null) ? $hubOrg : ['label' => 'Organisation', 'href' => url('jnet?commun=1'), 'unread' => 0];
$sections = is_array($hubSections ?? null) ? $hubSections : [];
$operator = trim((string) ($hubOperator ?? ''));
$community = trim((string) ($hubCommunity ?? ''));
$unreadMail = (int) ($hubUnreadMail ?? 0);
$flashErr = \App\Core\Session::getFlash('error');
$flashOk = \App\Core\Session::getFlash('success');
$requestHref = url(JnetMemberHub::REQUEST_PATH);
$pad = static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
$pill = static fn (string $label, string $key): string => '<span class="hb-pill hb-pill--' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
$arrow = '<svg class="hb-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$unreadText = static fn (int $n): string => $n > 0 ? '<b>' . $n . '</b> non lu' . ($n > 1 ? 's' : '') : 'À jour';

$jump = [['hb-unite', 'Mon unité']];
foreach ($sections as $section) {
    if ($section['key'] !== 'unite') {
        $jump[] = ['hb-' . $section['key'], (string) $section['title']];
    }
}
$jump[] = ['hb-autres', 'Autres unités'];

/** Une ligne du registre : l'ensemble est cliquable, « Demander » (autres unités) reste un lien à part. */
$unitRow = static function (array $u, int $index, bool $ask) use ($h, $pad, $pill, $arrow, $unreadText, $requestHref): string {
    $right = (array) $u['right'];
    $accent = (string) ($u['accent'] ?? '');
    $style = $accent !== '' ? ' style="--u: ' . $h($accent) . '"' : '';
    $banner = is_string($u['banner'] ?? null) ? (string) $u['banner'] : '';
    $emblem = is_string($u['emblem'] ?? null) ? (string) $u['emblem'] : '';
    $members = (int) ($u['members'] ?? 0);
    $sub = array_values(array_filter([(string) ($u['kind'] ?? ''), $members > 0 ? $members . ' membre' . ($members > 1 ? 's' : '') : '']));
    $html = '<div class="hb-urow"' . $style . '>';
    $html .= '<a class="hb-urow__link" href="' . $h((string) $u['href']) . '"><span class="hb-sr">Entrer dans ' . $h((string) $u['label']) . '</span></a>';
    if ($banner !== '') {
        $html .= '<span class="hb-ph" style="background-image: url(\'' . $h($banner) . '\')" aria-hidden="true"></span>';
    }
    $html .= '<span class="hb-n">' . $pad($index) . '</span>';
    $html .= '<span class="hb-nm"><span class="hb-crest" aria-hidden="true">'
        . ($emblem !== '' ? '<img src="' . $h($emblem) . '" alt="">' : $h((string) $u['monogram']))
        . '</span><span><b>' . $h((string) $u['label']) . '</b><small>' . $h(implode(' · ', $sub))
        . '<span class="hb-nr">' . $pill((string) $right['label'], (string) $right['key']) . '</span></small></span></span>';
    $html .= '<span class="hb-c">' . $pill((string) $right['label'], (string) $right['key']) . '</span>';
    $html .= '<span class="hb-c">' . $unreadText((int) ($u['unread'] ?? 0)) . '</span>';
    $html .= '<span class="hb-go">Entrer ' . $arrow . '</span>';
    if ($ask && (string) $right['key'] === JnetMemberHub::RIGHT_READ) {
        $html .= '<a class="hb-ask" href="' . $h((string) ($u['requestHref'] ?? $requestHref)) . '">Demander l’affectation</a>';
    }

    return $html . '</div>';
};
$registerHead = '<div class="hb-reg-h" aria-hidden="true"><span>N°</span><span>Unité</span><span>Votre droit</span><span>À lire</span><span></span></div>';
?>
<!DOCTYPE html>
<html lang="<?= $h(function_exists('html_lang') ? html_lang() : 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#050505">
    <title>Mon extranet — JNET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,700;1,900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_hub.css')) ?>">
</head>
<body class="hb">
<a class="hb-skip" href="#hb-main">Aller au contenu</a>

<header class="hb-top">
    <a class="hb-brand" href="<?= $h(url('jnet')) ?>"><i aria-hidden="true">A</i><span>ATHENA<em>.</em></span></a>
    <span class="hb-top__who">extranet<?= $community !== '' ? ' · ' . $h($community) : '' ?><?= $operator !== '' ? ' · session <span translate="no">' . $h($operator) . '</span>' : '' ?></span>
    <nav class="hb-top__nav" aria-label="Navigation de l’extranet">
        <a href="<?= $h(url('jnet/courrier')) ?>">Messagerie<?= $unreadMail > 0 ? ' <b>' . min(99, $unreadMail) . '</b>' : '' ?></a>
        <a href="<?= $h(url('dashboard')) ?>">Accueil Athena</a>
        <?php if (!empty($hubCanTba)): ?><a href="<?= $h(url('back-office')) ?>">Gestion</a><?php endif; ?>
        <form method="post" action="<?= $h(url('logout')) ?>"><?= \App\Core\Csrf::field() ?><button type="submit">Déconnexion</button></form>
    </nav>
</header>

<main class="hb-main" id="hb-main">
    <?php if ($flashErr): ?><p class="hb-flash is-bad" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="hb-flash" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>

    <section class="hb-gate" aria-labelledby="hb-title">
        <h1 class="hb-disp" id="hb-title">Une unité.<br><span>Un espace.</span></h1>
        <p class="hb-lead">Consignes, suivi, photos et documents : tout ce qui concerne votre unité, et rien d’autre. Choisissez votre espace.</p>
        <nav class="hb-jump" aria-label="Rubriques de l’extranet">
            <?php foreach ($jump as [$anchor, $label]): ?>
                <a href="#<?= $h($anchor) ?>"><?= $h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </section>

    <section class="hb-block" id="hb-unite" aria-labelledby="hb-unite-title">
        <h2 class="hb-disp hb-h2" id="hb-unite-title">Mon unité</h2>
        <?php if ($mine === []): ?>
            <div class="hb-empty">
                <p><strong>Vous n’êtes affecté à aucune unité pour le moment.</strong></p>
                <p>Votre espace apparaît ici dès votre affectation. Vous pouvez la demander depuis votre avancement.</p>
                <p><a class="hb-b1" href="<?= $h($requestHref) ?>">Demander une affectation</a></p>
            </div>
        <?php else: ?>
            <div class="hb-reg">
                <?= $registerHead ?>
                <?php foreach ($mine as $i => $u): ?><?= $unitRow($u, $i + 1, false) ?><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php foreach ($sections as $section): if ($section['key'] !== 'unite') { continue; } ?>
            <p class="hb-lead hb-lead--sub"><?= $h((string) $section['lead']) ?></p>
            <div class="hb-rows">
                <?php foreach ($section['items'] as $i => $item): ?>
                    <a class="hb-row" href="<?= $h((string) $item['href']) ?>">
                        <span class="hb-n"><?= $pad($i + 1) ?></span>
                        <span class="hb-t"><b><?= $h((string) $item['label']) ?></b><small><?= $h((string) $item['desc']) ?></small></span>
                        <span class="hb-m"><?php if ((int) $item['badge'] > 0): ?><em><?= min(99, (int) $item['badge']) ?></em><?php endif; ?></span>
                        <span class="hb-ar"><?= $arrow ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <?php foreach ($sections as $section): if ($section['key'] === 'unite') { continue; } ?>
        <section class="hb-block" id="hb-<?= $h((string) $section['key']) ?>" aria-labelledby="hb-<?= $h((string) $section['key']) ?>-title">
            <h2 class="hb-disp hb-h2" id="hb-<?= $h((string) $section['key']) ?>-title"><?= $h((string) $section['title']) ?></h2>
            <p class="hb-lead hb-lead--sub"><?= $h((string) $section['lead']) ?></p>
            <div class="hb-rows">
                <?php foreach ($section['items'] as $i => $item): ?>
                    <a class="hb-row" href="<?= $h((string) $item['href']) ?>">
                        <span class="hb-n"><?= $pad($i + 1) ?></span>
                        <span class="hb-t"><b><?= $h((string) $item['label']) ?></b><small><?= $h((string) $item['desc']) ?></small></span>
                        <span class="hb-m"><?php if ((int) $item['badge'] > 0): ?><em><?= min(99, (int) $item['badge']) ?></em><?php endif; ?></span>
                        <span class="hb-ar"><?= $arrow ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="hb-block" id="hb-autres" aria-labelledby="hb-autres-title">
        <h2 class="hb-disp hb-h2" id="hb-autres-title">Autres unités</h2>
        <p class="hb-lead hb-lead--sub">Les espaces de l’organisation que vous pouvez consulter. Pour y être affecté, faites-en la demande.</p>
        <div class="hb-reg">
            <?= $registerHead ?>
            <div class="hb-urow hb-urow--org">
                <a class="hb-urow__link" href="<?= $h((string) $org['href']) ?>"><span class="hb-sr">Entrer dans l’espace commun</span></a>
                <span class="hb-n">00</span>
                <span class="hb-nm"><span class="hb-crest" aria-hidden="true">ORG</span><span><b><?= $h((string) $org['label']) ?></b><small>Espace commun de l’organisation<span class="hb-nr"><?= $pill('Lecture seule', 'read') ?></span></small></span></span>
                <span class="hb-c"><?= $pill('Lecture seule', 'read') ?></span>
                <span class="hb-c"><?= $unreadText((int) ($org['unread'] ?? 0)) ?></span>
                <span class="hb-go">Entrer <?= $arrow ?></span>
            </div>
            <?php foreach ($others as $i => $u): ?><?= $unitRow($u, $i + 1, true) ?><?php endforeach; ?>
        </div>
    </section>
</main>

<footer class="hb-foot">
    <span>Accès réservé aux membres affectés · journalisé</span>
    <span>JNET<?= $community !== '' ? ' · ' . $h($community) : '' ?></span>
</footer>
</body>
</html>
