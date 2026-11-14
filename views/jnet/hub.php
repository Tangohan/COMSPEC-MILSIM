<?php
declare(strict_types=1);

/**
 * JNET — extranet du membre : « Une unité. Un espace. » (JnetPortalController::home pour un membre, et /jnet/espaces).
 * Page autonome : un onglet par rubrique (Mon unité, Mon profil, Mon ATAK, Mes données, Mon service, Autres unités),
 * piloté par l'ancre de l'adresse. Sans JavaScript, toutes les rubriques s'affichent l'une sous l'autre ; avec, une seule.
 * Chaque lien mène à une page qui refait ses propres vérifications ; rien n'est affiché que le lecteur ne voie déjà.
 *
 * @var string $hubCommunity
 * @var string $hubOperator
 * @var list<array<string, mixed>> $hubMine
 * @var list<array<string, mixed>> $hubOthers
 * @var list<array<string, mixed>> $hubTree toutes les unités visibles, dans l'ordre de la hiérarchie
 * @var array{label: string, href: string, unread: int} $hubOrg
 * @var list<array<string, mixed>> $hubSections
 * @var int $hubUnreadMail
 * @var bool $hubCanTba
 */

use App\Services\Jnet\JnetMemberHub;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$mine = is_array($hubMine ?? null) ? $hubMine : [];
$tree = is_array($hubTree ?? null) ? $hubTree : array_merge($mine, is_array($hubOthers ?? null) ? $hubOthers : []);
$org = is_array($hubOrg ?? null) ? $hubOrg : ['label' => 'Organisation', 'href' => url('jnet?commun=1'), 'unread' => 0];
$sections = is_array($hubSections ?? null) ? $hubSections : [];
$operator = trim((string) ($hubOperator ?? ''));
$community = trim((string) ($hubCommunity ?? ''));
$unreadMail = (int) ($hubUnreadMail ?? 0);
$flashErr = \App\Core\Session::getFlash('error');
$flashOk = \App\Core\Session::getFlash('success');
$requestHref = url(JnetMemberHub::REQUEST_PATH);
$pad = static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
$pill = static fn (string $label, string $key): string => '<span class="ja-pill ja-pill--' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
$arrow = '<svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$unreadText = static fn (int $n): string => $n > 0 ? '<b>' . $n . '</b> non lu' . ($n > 1 ? 's' : '') : 'À jour';

$mineUnread = array_sum(array_map(static fn (array $u): int => (int) ($u['unread'] ?? 0), $mine));
$othersUnread = (int) ($org['unread'] ?? 0);
foreach ($tree as $u) {
    if (empty($u['mine'])) {
        $othersUnread += (int) ($u['unread'] ?? 0);
    }
}

/** Rubriques : une par onglet, dans l'ordre de la page. */
$panels = [];
foreach ($sections as $section) {
    $panels[] = ['id' => 'hb-' . $section['key'], 'title' => (string) $section['title'], 'badge' => $section['key'] === 'unite' ? $mineUnread : 0];
}
$panels[] = ['id' => 'hb-autres', 'title' => 'Autres unités', 'badge' => $othersUnread];

/** Phrase sous le nom d'une unité : son type, puis chef, effectif et parent. */
$sub = static function (array $u) use ($h): string {
    $facts = array_values(array_filter(array_map('strval', (array) ($u['facts'] ?? []))));

    return '<small class="hb-sub"><span class="hb-kind">' . $h((string) ($u['kind'] ?? 'Unité')) . '</span>'
        . ($facts !== [] ? '<span class="hb-facts">' . $h(implode(' · ', $facts)) . '</span>' : '') . '</small>';
};

/** Liaisons actives de l'unité avec une unité que le lecteur voit : un marquage, qui n'ouvre aucun droit. */
$linkMarks = static function (array $u) use ($h): string {
    $marks = array_values((array) ($u['links'] ?? []));
    if ($marks === []) {
        return '';
    }
    $out = '<span class="hb-links">';
    foreach (array_slice($marks, 0, 2) as $m) {
        $out .= '<span class="hb-link hb-link--' . $h((string) $m['kind']) . '" title="' . $h((string) $m['sentence']) . '">' . $h((string) $m['kindLabel']) . ' · ' . $h((string) $m['other']) . '</span>';
    }
    if (count($marks) > 2) {
        $out .= '<span class="hb-link hb-link--more">+' . (count($marks) - 2) . '</span>';
    }

    return $out . '</span>';
};
$anyLinked = count(array_filter($tree, static fn (array $u): bool => !empty($u['links']))) > 0;

/** Une ligne du registre : l'ensemble est cliquable, « Demander » (autres unités) reste un lien à part. */
$unitRow = static function (array $u, ?int $index, bool $ask, bool $inTree) use ($h, $pad, $pill, $arrow, $unreadText, $requestHref, $sub, $linkMarks): string {
    $right = (array) $u['right'];
    $accent = (string) ($u['accent'] ?? '');
    $depth = $inTree ? max(1, (int) ($u['depth'] ?? 1)) : 1;
    $banner = is_string($u['banner'] ?? null) && $u['banner'] !== '' ? (string) $u['banner'] : asset_url((string) ($u['photo'] ?? JnetUi::genericPhoto((int) $u['id'])));
    $emblem = is_string($u['emblem'] ?? null) ? (string) $u['emblem'] : null;
    $style = ($accent !== '' ? '--u: ' . $h($accent) . ';' : '') . ($inTree ? '--d: ' . ($depth - 1) . ';' : '');
    $fil = $inTree && count((array) ($u['chain'] ?? [])) > 1 ? implode(' › ', array_map('strval', (array) $u['chain'])) : '';
    $search = mb_strtolower(implode(' ', array_filter([(string) $u['label'], (string) ($u['kind'] ?? ''), implode(' ', (array) ($u['facts'] ?? [])), $fil])));

    $html = '<div class="hb-urow' . ($inTree ? ' hb-urow--tree' : '') . ($inTree && $depth > 1 ? ' is-sub' : '') . '" style="' . $style . '"'
        . ($inTree ? ' data-hb-unit data-q="' . $h($search) . '" data-mine="' . (!empty($u['mine']) ? '1' : '0') . '" data-unread="' . (int) ($u['unread'] ?? 0) . '" data-linked="' . (!empty($u['links']) ? '1' : '0') . '"' : '') . '>';
    $html .= '<a class="hb-urow__link" href="' . $h((string) $u['href']) . '"><span class="ja-sr">Entrer dans ' . $h((string) $u['label']) . '</span></a>';
    $html .= '<span class="hb-ph" style="--ph: url(\'' . $h($banner) . '\')" aria-hidden="true"></span>';
    $html .= '<span class="hb-n">' . ($index !== null ? $pad($index) : '') . '</span>';
    $html .= '<span class="hb-nm">' . JnetUi::crest((string) $u['monogram'], $emblem, 'ja-crest ja-crest--md')
        . '<span class="hb-nm__t"><b>' . $h((string) $u['label']) . '</b>' . $sub($u) . $linkMarks($u)
        . ($fil !== '' ? '<span class="hb-fil">' . $h($fil) . '</span>' : '')
        . '<span class="hb-nr">' . $pill((string) $right['label'], (string) $right['key']) . '</span></span></span>';
    $html .= '<span class="hb-c">' . $pill((string) $right['label'], (string) $right['key']) . '</span>';
    $html .= '<span class="hb-c">' . $unreadText((int) ($u['unread'] ?? 0)) . '</span>';
    $html .= '<span class="hb-go">Entrer ' . $arrow . '</span>';
    if ($ask && empty($u['mine']) && (string) $right['key'] === JnetMemberHub::RIGHT_READ) {
        $html .= '<a class="hb-ask" href="' . $h((string) ($u['requestHref'] ?? $requestHref)) . '">Demander l’affectation</a>';
    }

    return $html . '</div>';
};
$registerHead = '<div class="hb-reg-h" aria-hidden="true"><span>N°</span><span>Unité</span><span>Votre droit</span><span>À lire</span><span></span></div>';

/** Lignes de rubriques (pages existantes) d'une section. */
$itemRows = static function (array $section) use ($h, $pad, $arrow): string {
    $out = '<div class="ja-rows">';
    foreach ($section['items'] as $i => $item) {
        $out .= '<a class="ja-row" href="' . $h((string) $item['href']) . '"><span class="n">' . $pad($i + 1) . '</span>'
            . '<span class="t"><b>' . $h((string) $item['label']) . '</b><small>' . $h((string) $item['desc']) . '</small></span>'
            . '<span class="m">' . ((int) $item['badge'] > 0 ? '<em class="ja-badge">' . min(99, (int) $item['badge']) . '</em>' : '') . '</span>'
            . '<span class="ar">' . $arrow . '</span></a>';
    }

    return $out . '</div>';
};
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
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_athena.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_hub.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_links.css?r=202611150001')) ?>">
</head>
<body class="ja hb">
<a class="ja-skip" href="#hb-main">Aller au contenu</a>

<header class="ja-hd">
    <a class="ja-brand" href="<?= $h(url('jnet')) ?>"><i aria-hidden="true">A</i><span>ATHENA<em>.</em></span></a>
    <div class="ja-hd__r">
        <span class="ja-who">extranet<?= $community !== '' ? ' · ' . $h($community) : '' ?></span>
        <?= JnetUi::account($operator) ?>
        <a class="ja-ib" href="<?= $h(url('jnet/courrier')) ?>"><?= JnetUi::icon('mail') ?><span class="ja-ib__t">Messagerie</span><?= $unreadMail > 0 ? ' <b>' . min(99, $unreadMail) . '</b>' : '' ?></a>
        <a class="ja-ib" href="<?= $h(url('dashboard')) ?>"><?= JnetUi::icon('home') ?><span class="ja-ib__t">Accueil Athena</span></a>
        <?php if (!empty($hubCanTba)): ?><a class="ja-ib" href="<?= $h(url('back-office')) ?>"><?= JnetUi::icon('manage') ?><span class="ja-ib__t">Gestion</span></a><?php endif; ?>
        <form method="post" action="<?= $h(url('logout')) ?>"><?= \App\Core\Csrf::field() ?><button class="ja-ib" type="submit" title="Déconnexion"><?= JnetUi::icon('logout') ?><span class="ja-ib__t">Déconnexion</span></button></form>
    </div>
</header>

<main class="ja-main hb-main" id="hb-main">
    <?php if ($flashErr): ?><p class="ja-flash is-bad" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="ja-flash" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>

    <section class="hb-gate" aria-labelledby="hb-title">
        <h1 class="ja-disp ja-disp--xl" id="hb-title">Une unité.<br><span>Un espace.</span></h1>
        <p class="ja-lead">Consignes, suivi, photos et documents : tout ce qui concerne votre unité, et rien d’autre. Choisissez votre espace.</p>
    </section>

    <nav class="ja-tabs ja-tabs--page hb-tabs" aria-label="Rubriques de l’extranet" data-hb-tabs>
        <?php foreach ($panels as $i => $p): ?>
            <a class="ja-tab" href="#<?= $h($p['id']) ?>" data-hb-tab><span class="ja-mono"><?= $pad($i) ?></span><?= $h($p['title']) ?><?php if ((int) $p['badge'] > 0): ?><em><?= min(99, (int) $p['badge']) ?></em><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="hb-panels">
        <?php foreach ($sections as $section): ?>
            <?php if ($section['key'] === 'unite'): ?>
                <section class="hb-panel" id="hb-unite" aria-labelledby="hb-unite-title" data-hb-panel>
                    <h2 class="ja-disp ja-disp--md" id="hb-unite-title">Mon unité</h2>
                    <?php if ($mine === []): ?>
                        <div class="ja-empty hb-empty">
                            <p><strong>Vous n’êtes affecté à aucune unité pour le moment.</strong></p>
                            <p>Votre espace apparaît ici dès votre affectation. Vous pouvez la demander depuis votre avancement.</p>
                            <p><a class="ja-b1" href="<?= $h($requestHref) ?>">Demander une affectation</a></p>
                        </div>
                    <?php else: ?>
                        <div class="hb-reg">
                            <?= $registerHead ?>
                            <?php foreach ($mine as $i => $u): ?><?= $unitRow($u, $i + 1, false, false) ?><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <p class="ja-lead hb-sublead"><?= $h((string) $section['lead']) ?></p>
                    <?= $itemRows($section) ?>
                </section>
            <?php else: ?>
                <section class="hb-panel" id="hb-<?= $h((string) $section['key']) ?>" aria-labelledby="hb-<?= $h((string) $section['key']) ?>-title" data-hb-panel>
                    <h2 class="ja-disp ja-disp--md" id="hb-<?= $h((string) $section['key']) ?>-title"><?= $h((string) $section['title']) ?></h2>
                    <p class="ja-lead hb-sublead"><?= $h((string) $section['lead']) ?></p>
                    <?= $itemRows($section) ?>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <section class="hb-panel" id="hb-autres" aria-labelledby="hb-autres-title" data-hb-panel>
            <h2 class="ja-disp ja-disp--md" id="hb-autres-title">Autres unités</h2>
            <p class="ja-lead hb-sublead">Toutes les unités de l’organisation que vous pouvez consulter, de l’échelon le plus haut au plus bas. Pour être affecté à l’une d’elles, faites-en la demande.</p>

            <div class="hb-filter" data-hb-filter hidden>
                <label class="ja-fi">
                    <svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <span class="ja-sr">Rechercher une unité</span>
                    <input type="search" placeholder="Unité, chef, échelon" autocomplete="off" data-hb-q>
                </label>
                <div class="ja-chips" role="group" aria-label="Filtrer les unités">
                    <button type="button" class="ja-chip is-on" data-hb-chip="all" aria-pressed="true">Toutes</button>
                    <button type="button" class="ja-chip" data-hb-chip="mine" aria-pressed="false">Mes unités</button>
                    <button type="button" class="ja-chip" data-hb-chip="unread" aria-pressed="false">Non lus</button>
                    <?php if ($anyLinked): ?><button type="button" class="ja-chip" data-hb-chip="linked" aria-pressed="false">Unités liées</button><?php endif; ?>
                </div>
            </div>

            <div class="hb-reg">
                <?= $registerHead ?>
                <div class="hb-urow hb-urow--org" data-hb-unit data-q="<?= $h(mb_strtolower((string) $org['label'] . ' organisation espace commun')) ?>" data-mine="0" data-unread="<?= (int) ($org['unread'] ?? 0) ?>">
                    <a class="hb-urow__link" href="<?= $h((string) $org['href']) ?>"><span class="ja-sr">Entrer dans l’espace commun</span></a>
                    <span class="hb-ph" style="--ph: url('<?= $h(asset_url(JnetUi::GENERIC_PHOTOS[1])) ?>')" aria-hidden="true"></span>
                    <span class="hb-n">00</span>
                    <span class="hb-nm"><?= JnetUi::crest('ORG', null, 'ja-crest ja-crest--md') ?><span class="hb-nm__t"><b><?= $h((string) $org['label']) ?></b><small class="hb-sub"><span class="hb-kind">Organisation</span><span class="hb-facts">Espace commun · <?= $h(JnetUi::count(count($tree), 'unité')) ?></span></small><span class="hb-nr"><?= $pill('Lecture seule', 'read') ?></span></span></span>
                    <span class="hb-c"><?= $pill('Lecture seule', 'read') ?></span>
                    <span class="hb-c"><?= $unreadText((int) ($org['unread'] ?? 0)) ?></span>
                    <span class="hb-go">Entrer <?= $arrow ?></span>
                </div>
                <?php foreach ($tree as $u): ?><?= $unitRow($u, null, true, true) ?><?php endforeach; ?>
            </div>
            <p class="hb-none" data-hb-none hidden>Aucune unité ne correspond.</p>
        </section>
    </div>
</main>

<footer class="ja-foot">
    <span>Accès réservé aux membres affectés · journalisé</span>
    <span>JNET<?= $community !== '' ? ' · ' . $h($community) : '' ?></span>
</footer>
<script src="<?= $h(asset_url('assets/js/jnet_hub.js')) ?>"></script>
</body>
</html>
