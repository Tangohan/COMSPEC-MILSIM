<?php
declare(strict_types=1);

/**
 * Tableau de bord de pilotage (back-office communauté).
 *
 * Données mises en forme par App\Support\PilotageDashboardPresenter (contrôleur OrganizationDashboardController).
 *
 * @var array<string, mixed> $pilotage
 * @var array<string, mixed>|null $initialSetupBanner
 * @var int $configurationUpdateBadge
 * @var bool $discordInviteMissing
 * @var bool $discordBotNudge
 * @var int $missingMediaCount
 * @var array<string, mixed> $orgActivityChart
 * @var list<array<string, mixed>> $orgMessagesRecent
 * @var array<string, mixed> $orgProfileGaps
 */

$pilH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$pil = is_array($pilotage ?? null) ? $pilotage : [];
$pilHello = is_array($pil['hello'] ?? null) ? $pil['hello'] : [];
$pilHero = is_array($pil['hero'] ?? null) ? $pil['hero'] : null;
$pilDecisions = is_array($pil['decisions'] ?? null) ? $pil['decisions'] : ['items' => [], 'total' => 0];
$pilKpis = is_array($pil['kpis'] ?? null) ? $pil['kpis'] : [];
$pilPart = is_array($pil['participation'] ?? null) ? $pil['participation'] : ['bars' => [], 'scale' => 4, 'gridlines' => [], 'has_checkins' => false];
$pilUnits = is_array($pil['units'] ?? null) ? $pil['units'] : [];
$pilPipeline = is_array($pil['pipeline'] ?? null) ? $pil['pipeline'] : null;
$pilAgenda = is_array($pil['agenda'] ?? null) ? $pil['agenda'] : ['title' => 'Cette semaine', 'items' => []];
$pilFeed = is_array($pil['feed'] ?? null) ? $pil['feed'] : [];
$pilLinks = is_array($pil['links'] ?? null) ? $pil['links'] : [];
$pilKpiError = isset($adminKpiBlockError) && $adminKpiBlockError !== null ? (string) $adminKpiBlockError : '';

$setupBanner = is_array($initialSetupBanner ?? null) ? $initialSetupBanner : null;
$discordInviteMissing = !empty($discordInviteMissing);
$missingMediaCount = (int) ($missingMediaCount ?? 0);
$pilCfgCount = (int) ($configurationUpdateBadge ?? 0);

/** Icônes au trait (24×24). */
$pilIcon = static function (string $name, string $class = 'pil-i'): string {
    $paths = [
        'up' => '<path d="m6 15 6-6 6 6"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
        'award' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/>',
        'file' => '<path d="M14 3H6v18h12V7zM14 3v4h4"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'cal' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'pin' => '<path d="M12 21s-7-6-7-12a7 7 0 0 1 14 0c0 6-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'spark' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'warn' => '<path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
    ];

    return '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
};

/** Petite courbe de tendance (valeurs réelles uniquement). */
$pilSpark = static function (?array $series, string $tone): string {
    if ($series === null || count($series) < 2) {
        return '';
    }
    $series = array_values(array_map('intval', $series));
    $min = min($series);
    $max = max($series);
    $range = max(1, $max - $min);
    $n = count($series);
    $pts = [];
    foreach ($series as $i => $v) {
        $x = round($i / ($n - 1) * 96, 1);
        $y = round(28 - ($v - $min) / $range * 24, 1);
        $pts[] = $x . ',' . $y;
    }

    return '<svg class="pil-spark pil-spark--' . htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 96 32" aria-hidden="true" focusable="false"><polyline points="' . implode(' ', $pts) . '"/></svg>';
};

$pilPlural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n > 1 ? $many : $one);
?>
<div class="pil" data-pilotage>

    <?php require base_path('views/partials/platform_messages_cards.php'); ?>

    <?php if (!empty($discordBotNudge)): ?>
    <div class="pil-banner" role="status">
        <?= $pilIcon('spark') ?>
        <span class="pil-banner__text"><b>Ajoutez le bot Athena à votre serveur Discord.</b> Il accompagne votre bureau des effectifs : résumés, candidatures, événements et rappels, sans quitter Discord.</span>
        <a class="pil-banner__cta" href="<?= $pilH(url('back-office/discord-bot')) ?>">Ajouter le bot</a>
        <form method="post" action="<?= $pilH(url('back-office/discord-bot/rappel/fermer')) ?>" class="pil-banner__form">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="pil-banner__later">Plus tard</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($discordInviteMissing): ?>
    <div class="pil-banner pil-banner--warn" role="alert">
        <?= $pilIcon('warn') ?>
        <span class="pil-banner__text"><b>Les candidats ne peuvent pas rejoindre votre Discord.</b> Le recrutement passe par Discord, mais aucun lien d’invitation n’est enregistré.</span>
        <a class="pil-banner__cta" href="<?= $pilH(url('back-office/community') . '?onglet=inscription#coordonnees') ?>">Ajouter le lien</a>
    </div>
    <?php endif; ?>

    <?php if ($missingMediaCount > 0): ?>
    <div class="pil-banner pil-banner--warn" role="alert">
        <?= $pilIcon('warn') ?>
        <span class="pil-banner__text"><b><?= $missingMediaCount > 1 ? (int) $missingMediaCount . ' photos de profil ont été perdues' : '1 photo de profil a été perdue' ?> lors de la migration.</b> Demandez aux membres concernés de la remettre en ligne depuis Mon compte › Image / Portrait.</span>
        <a class="pil-banner__cta" href="<?= $pilH(url('back-office/organisation/qualite-donnees') . '#dq-photos') ?>">Voir les membres concernés</a>
    </div>
    <?php endif; ?>

    <?php if ($setupBanner !== null): ?>
    <div class="pil-banner pil-banner--warn" role="status">
        <?= $pilIcon('spark') ?>
        <span class="pil-banner__text"><b>Configuration de la communauté · <?= (int) ($setupBanner['percent'] ?? 0) ?> % terminée.</b> Il reste quelques réglages essentiels : logo, contact, mode d’inscription et modules visibles par le public.</span>
        <a class="pil-banner__cta" href="<?= $pilH(url('back-office/configuration-initiale')) ?>">Reprendre la configuration</a>
        <form method="post" action="<?= $pilH(url('back-office/configuration-initiale/dismiss')) ?>" class="pil-banner__form">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="pil-banner__later">Plus tard</button>
        </form>
    </div>
    <?php elseif ($pilCfgCount > 0): ?>
    <div class="pil-banner pil-banner--info" role="status" data-pil-dismissable="cfg-<?= $pilCfgCount ?>">
        <?= $pilIcon('spark') ?>
        <span class="pil-banner__text"><b><?= $pilH($pilPlural($pilCfgCount, 'nouvelle configuration', 'nouvelles configurations')) ?></b> <?= $pilCfgCount > 1 ? 'disponibles pour votre organisation.' : 'disponible pour votre organisation.' ?></span>
        <a class="pil-banner__cta" href="<?= $pilH(url('back-office/mise-a-niveau')) ?>">Découvrir</a>
        <button type="button" class="pil-banner__close" data-pil-dismiss title="Masquer jusqu’à la prochaine nouveauté" aria-label="Masquer ce bandeau"><?= $pilIcon('x') ?></button>
    </div>
    <?php endif; ?>

    <?php require base_path('views/partials/bo_atak_light_kit.php'); ?>

    <?php
    // Vignettes dans la ligne du titre : les dernières personnes actives (fil « Ce qui a bougé »), sans doublon.
    $pilFaces = [];
    foreach ($pilFeed as $pilF) {
        $pilFaceKey = (string) ($pilF['actor'] ?? '');
        if ($pilFaceKey !== '' && !isset($pilFaces[$pilFaceKey]) && count($pilFaces) < 4) {
            $pilFaces[$pilFaceKey] = (string) ($pilF['initials'] ?? '');
        }
    }
    $pilFaceTones = ['', 'sky', 'amber', 'violet'];
    $pilHelloActions = is_array($pilHello['actions'] ?? null) ? array_values($pilHello['actions']) : [];
    $pilMainAction = $pilHelloActions[0] ?? null;
    $pilDecN = (int) ($pilHello['decisions'] ?? 0);
    ?>
    <header class="bo-band bo-band--hero bo-band--emerald pil-hero">
        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
        <div class="bo-band__glow" aria-hidden="true"></div>
        <div class="bo-band__veil" aria-hidden="true"></div>
        <div class="bo-band__body">
            <div class="bo-band__who">
                <div class="bo-band__heading">
                    <h1 class="bo-band__title" id="ath-page-title"><?= $pilH((string) ($pilHello['greeting'] ?? 'Bonjour')) ?><?= ($pilHello['name'] ?? '') !== '' ? ', ' . $pilH((string) $pilHello['name']) : '' ?>.</h1>
                    <?php if ($pilFaces !== []): ?>
                    <span class="bo-faces" role="img" aria-label="<?= $pilH('Actifs récemment : ' . implode(', ', array_keys($pilFaces))) ?>">
                        <?php $pilFaceN = 0; foreach ($pilFaces as $pilFaceName => $pilFaceIni): ?>
                        <span class="bo-faces__it<?= $pilFaceTones[$pilFaceN % 4] !== '' ? ' bo-faces__it--' . $pilFaceTones[$pilFaceN % 4] : '' ?>" title="<?= $pilH((string) $pilFaceName) ?>"><?= $pilH($pilFaceIni) ?></span>
                        <?php $pilFaceN++; endforeach; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <p class="bo-band__line">
                    <?= $pilH((string) ($pilHello['tenant'] ?? 'Votre communauté')) ?> compte
                    <b><?= $pilH($pilPlural((int) ($pilHello['active'] ?? 0), 'membre actif', 'membres actifs')) ?></b><?php if (isset($pilHello['total']) && $pilHello['total'] !== null && (int) $pilHello['total'] > (int) ($pilHello['active'] ?? 0)): ?> sur <?= (int) $pilHello['total'] ?><?php endif; ?>.
                    <?php $pilNextIn = $pilHello['next_in'] ?? null; ?>
                    <?php if ($pilNextIn === null): ?>
                        Aucune activité n’est programmée<?php elseif ($pilNextIn === 'en cours'): ?>
                        <?= $pilH((string) ($pilHello['next_noun'] ?? 'La prochaine activité')) ?> est <b>en cours</b><?php else: ?>
                        <?= $pilH((string) ($pilHello['next_noun'] ?? 'La prochaine activité')) ?> est dans <b><?= $pilH((string) $pilNextIn) ?></b><?php endif; ?><?php if ($pilDecN > 0): ?>, et <b><?= $pilH($pilPlural($pilDecN, 'décision', 'décisions')) ?></b> <?= $pilDecN > 1 ? 'vous attendent.' : 'vous attend.' ?><?php else: ?>, et rien n’attend votre décision.<?php endif; ?>
                </p>
                <?php if ($pilMainAction !== null || empty($home['customize'])): ?>
                <div class="bo-band__actions">
                    <?php if ($pilMainAction !== null): ?>
                    <a class="bo-band__btn bo-band__btn--solid" href="<?= $pilH((string) $pilMainAction['href']) ?>"><?= $pilH((string) $pilMainAction['label']) ?> <span class="bo-band__arrow" aria-hidden="true">→</span></a>
                    <?php endif; ?>
                    <?php foreach (array_slice($pilHelloActions, 1) as $pilAct): ?>
                    <a class="bo-band__btn" href="<?= $pilH((string) $pilAct['href']) ?>"><?= $pilH((string) $pilAct['label']) ?></a>
                    <?php endforeach; ?>
                    <?php if (empty($home['customize'])): ?>
                    <a class="bo-band__btn" href="<?= $pilH(url('back-office') . '?personnaliser=1#personnaliser') ?>">Personnaliser l’accueil</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="bo-band__side">
                <?php if (($pilHello['date_label'] ?? '') !== ''): ?>
                <p class="bo-band__meta"><span><?php $pilDate = mb_strtolower((string) $pilHello['date_label']); ?><?= $pilH(mb_strtoupper(mb_substr($pilDate, 0, 1)) . mb_substr($pilDate, 1)) ?></span> <?= $pilH((string) ($pilHello['tenant'] ?? '')) ?></p>
                <?php endif; ?>
                <p class="bo-figure<?= $pilDecN > 0 ? ' is-hot' : '' ?>"><strong><?= $pilDecN ?></strong><span><?= $pilDecN > 1 ? 'décisions en attente' : 'décision en attente' ?></span></p>
            </div>
        </div>
    </header>

    <?php
    $pilChart = is_array($orgActivityChart ?? null) ? $orgActivityChart : [];
    $pilPortal = is_array($pilChart['portal'] ?? null) ? $pilChart['portal'] : [];
    $pilAtak = is_array($pilChart['atak'] ?? null) ? $pilChart['atak'] : [];
    $pilDays = is_array($pilChart['days'] ?? null) ? $pilChart['days'] : [];
    $pilMax = max(1, (int) ($pilChart['max'] ?? 1));
    $pilPeriod = (int) ($pilChart['period_days'] ?? 14);
    $pilMessages = is_array($orgMessagesRecent ?? null) ? array_slice($orgMessagesRecent, 0, 3) : [];
    ?>
    <?php
    // Accueil personnalisable : ordre, blocs masqués et largeur par personne (App\Services\BackOffice\Home\HomeLayout).
    $home = is_array($home ?? null) ? $home : [];
    $homeItems = is_array($home['items'] ?? null) ? $home['items'] : \App\Services\BackOffice\Home\HomeLayout::defaults(\App\Services\BackOffice\Home\HomeLayout::ROLE_MANAGER);
    $homeSheets = is_array($home['sheets'] ?? null) ? $home['sheets'] : [];
    $homeTasks = is_array($home['tasks'] ?? null) ? $home['tasks'] : [];
    $homeTaskTotal = array_sum(array_map(static fn (array $t): int => (int) $t['count'], $homeTasks));
    // Raccourcis = favoris du menu (libellés et droits déjà résolus par le menu latéral).
    $homeShortcuts = isset($athMenu) && is_array($athMenu['favorites'] ?? null) ? $athMenu['favorites'] : [];
    $homeVisible = array_values(array_filter($homeItems, static fn (array $i): bool => empty($i['hidden'])));
    ?>
    <?php if (!empty($home['customize'])): ?>
    <div class="home-bar"><span class="home-bar__state">Mode personnalisation</span></div>
    <?php endif; ?>
    <?php if (!empty($home['customize'])): ?>
        <?php require base_path('views/admin/organization/_home_customize.php'); ?>
    <?php endif; ?>

    <?php if ($homeVisible === []): ?>
    <section class="pil-card pil-empty-home">
        <p class="pil-empty">Tous les blocs sont masqués. <a class="pil-link" href="<?= $pilH(url('back-office') . '?personnaliser=1#personnaliser') ?>">Choisir des blocs à afficher</a></p>
    </section>
    <?php endif; ?>

    <div class="home-grid">
    <?php foreach ($homeVisible as $homeItem): ?>
    <?php $homeWid = (string) $homeItem['id']; if (\App\Services\BackOffice\Home\HomeLayout::isSheet($homeWid) && !isset($homeSheets[\App\Services\BackOffice\Home\HomeLayout::sheetId($homeWid)])) { continue; } if ($homeWid === 'pipeline' && $pilPipeline === null) { continue; } if ($homeWid === 'custom' && (!isset($home['custom']) || ($home['custom']['blocks'] === [] && empty($home['custom']['can_edit'])))) { continue; } ?>
    <div class="home-w home-w--<?= $pilH((string) $homeItem['size']) ?>" data-widget="<?= $pilH($homeWid) ?>">
    <?php if ($homeWid === 'operation'): ?>
        <?php if ($pilHero !== null): ?>
        <?php $pilR = $pilHero['rsvp']; $pilCover = (string) ($pilHero['cover_url'] ?? ''); ?>
        <section class="bo-band bo-band--emerald pil-op" aria-labelledby="pil-op-title">
            <?php if ($pilCover !== ''): ?>
            <div class="bo-band__media" style="background-image:url('<?= $pilH($pilCover) ?>')" aria-hidden="true"></div>
            <?php else: ?>
            <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="bo-band__glow" aria-hidden="true"></div>
            <div class="bo-band__veil" aria-hidden="true"></div>
            <div class="bo-band__body">
                <div class="bo-band__identity">
                    <span class="bo-band__index" aria-hidden="true">/ 01</span>
                    <div class="bo-band__who">
                        <h2 class="bo-band__title" id="pil-op-title"><?= $pilH((string) $pilHero['title']) ?></h2>
                        <?php if ($pilHero['date_label'] !== '' || $pilHero['location'] !== ''): ?>
                        <p class="bo-band__line pil-op__det">
                            <?php if ($pilHero['date_label'] !== ''): ?>
                            <span><?= $pilIcon('cal') ?><b><?= $pilH((string) $pilHero['date_label']) ?></b> <?= $pilH((string) $pilHero['time_label']) ?></span>
                            <?php endif; ?>
                            <?php if ($pilHero['location'] !== ''): ?>
                            <span><?= $pilIcon('pin') ?><b><?= $pilH((string) $pilHero['location']) ?></b></span>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                        <p class="bo-band__tag"><?= $pilH((string) $pilHero['kicker']) ?><?= $pilHero['campaign'] !== '' ? ' · ' . $pilH((string) $pilHero['campaign']) : '' ?></p>
                    </div>
                </div>
                <div class="bo-band__side">
                    <?php if (!empty($pilHero['ongoing'])): ?>
                    <p class="bo-band__meta"><span>En cours</span> depuis <?= $pilH((string) $pilHero['time_label']) ?></p>
                    <?php elseif (is_array($pilHero['countdown'] ?? null)): ?>
                    <p class="bo-band__meta pil-cd" data-pil-countdown="<?= $pilH((string) $pilHero['starts_at_iso']) ?>" role="timer" aria-label="Temps restant avant le début">
                        <span>Début dans</span>
                        <b data-cd="d"><?= sprintf('%02d', (int) $pilHero['countdown']['days']) ?></b> j
                        <b data-cd="h"><?= sprintf('%02d', (int) $pilHero['countdown']['hours']) ?></b> h
                        <b data-cd="m"><?= sprintf('%02d', (int) $pilHero['countdown']['minutes']) ?></b> min
                    </p>
                    <?php endif; ?>
                    <div class="bo-band__row">
                        <?php if ($pilHero['href'] !== null): ?>
                        <div class="bo-band__actions">
                            <?php if (!empty($pilHero['answers_href'])): ?>
                            <a class="bo-band__btn" href="<?= $pilH((string) $pilHero['answers_href']) ?>">Voir les réponses</a>
                            <?php endif; ?>
                            <a class="bo-band__btn bo-band__btn--solid" href="<?= $pilH((string) $pilHero['href']) ?>">Ouvrir la fiche</a>
                        </div>
                        <?php endif; ?>
                        <p class="bo-figure is-hot"><strong><?= (int) $pilR['yes'] ?><small> / <?= (int) $pilR['base'] ?></small></strong><span>présences confirmées</span></p>
                    </div>
                </div>
                <div class="bo-band__foot pil-op__rsvp">
                    <div class="pil-rb" role="img" aria-label="<?= (int) $pilR['yes'] ?> confirmés, <?= (int) $pilR['maybe'] ?> peut-être, <?= (int) $pilR['no'] ?> déclinés, <?= (int) $pilR['no_reply'] ?> sans réponse">
                        <i class="pil-rb__yes" style="width:<?= $pilH((string) $pilR['pct']['yes']) ?>%"></i><i class="pil-rb__maybe" style="width:<?= $pilH((string) $pilR['pct']['maybe']) ?>%"></i><i class="pil-rb__no" style="width:<?= $pilH((string) $pilR['pct']['no']) ?>%"></i>
                    </div>
                    <div class="pil-rl">
                        <span><em class="pil-rl__yes"></em><b><?= (int) $pilR['yes'] ?></b> <?= (int) $pilR['yes'] > 1 ? 'confirmés' : 'confirmé' ?></span>
                        <span><em class="pil-rl__maybe"></em><b><?= (int) $pilR['maybe'] ?></b> peut-être</span>
                        <span><em class="pil-rl__no"></em><b><?= (int) $pilR['no'] ?></b> <?= (int) $pilR['no'] > 1 ? 'déclinés' : 'décliné' ?></span>
                        <span><em class="pil-rl__none"></em><b><?= (int) $pilR['no_reply'] ?></b> sans réponse</span>
                    </div>
                </div>
            </div>
        </section>
        <?php else: ?>
        <section class="bo-band bo-band--empty bo-band--sky pil-op pil-op--empty" aria-labelledby="pil-op-title">
            <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
            <div class="bo-band__glow" aria-hidden="true"></div>
            <div class="bo-band__veil" aria-hidden="true"></div>
            <div class="bo-band__body">
                <div class="bo-band__identity">
                    <span class="bo-band__index" aria-hidden="true">/ 01</span>
                    <div class="bo-band__who">
                        <h2 class="bo-band__title" id="pil-op-title">À planifier</h2>
                        <p class="bo-band__line">Aucune activité n’est programmée. Créez le prochain créneau pour que les membres puissent répondre.</p>
                        <p class="bo-band__tag">Prochaine opération</p>
                    </div>
                </div>
                <?php if (($pilLinks['events'] ?? null) !== null): ?>
                <div class="bo-band__side">
                    <div class="bo-band__actions">
                        <a class="bo-band__btn bo-band__btn--solid" href="<?= $pilH((string) $pilLinks['events']) ?>">Programmer une activité <span class="bo-band__arrow" aria-hidden="true">→</span></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    <?php elseif ($homeWid === 'decisions'): ?>
        <section class="pil-card" aria-labelledby="pil-dec-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-dec-title">À décider</h2>
                </div>
                <?php if ((int) $pilDecisions['total'] > 0): ?>
                <span class="pil-count" aria-label="<?= (int) $pilDecisions['total'] ?> en attente"><?= (int) $pilDecisions['total'] ?></span>
                <?php endif; ?>
            </div>
            <?php if ($pilDecisions['items'] === []): ?>
            <div class="pil-done">
                <?= $pilIcon('check', 'pil-i pil-done__i') ?>
                <b>Tout est à jour</b>
                <span>Plus rien n’attend votre validation.</span>
            </div>
            <?php else: ?>
            <ul class="pil-dec">
                <?php foreach (array_slice($pilDecisions['items'], 0, 5) as $pilItem): ?>
                <li class="pil-dec__it">
                    <span class="pil-dec__ic pil-tone--<?= $pilH((string) $pilItem['tone']) ?>"><?= $pilIcon((string) $pilItem['icon']) ?></span>
                    <span class="pil-dec__txt"><b><?= $pilH((string) $pilItem['title']) ?></b><small><?= $pilH((string) $pilItem['meta']) ?></small></span>
                    <a class="pil-btn pil-btn--sm" href="<?= $pilH((string) $pilItem['href']) ?>"><?= $pilH((string) $pilItem['cta']) ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($pilDecisions['items']) > 5): ?>
            <p class="pil-more"><?= $pilH($pilPlural(count($pilDecisions['items']) - 5, 'autre point', 'autres points')) ?> dans les listes ci-dessous et dans Effectifs.</p>
            <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'kpis'): ?>
    <?php if ($pilKpiError !== ''): ?>
    <p class="pil-banner pil-banner--warn" role="status"><?= $pilIcon('warn') ?><span class="pil-banner__text"><?= $pilH($pilKpiError) ?></span></p>
    <?php endif; ?>
    <div class="pil-kpis">
        <?php foreach ($pilKpis as $pilKpi): ?>
        <div class="pil-card pil-kpi">
            <p class="pil-kpi__label"><?= $pilH((string) $pilKpi['label']) ?></p>
            <div class="pil-kpi__v">
                <b><?= $pilH((string) $pilKpi['value']) ?></b>
                <?php if ((string) $pilKpi['unit'] !== ''): ?><span><?= $pilH((string) $pilKpi['unit']) ?></span><?php endif; ?>
                <?php if (is_array($pilKpi['badge'] ?? null)): ?><span class="pil-dl pil-dl--<?= $pilH((string) $pilKpi['badge']['tone']) ?>"><?= $pilH((string) $pilKpi['badge']['text']) ?></span><?php endif; ?>
            </div>
            <div class="pil-kpi__f">
                <?php if (!empty($pilKpi['note_href'])): ?>
                <a href="<?= $pilH((string) $pilKpi['note_href']) ?>"><?= $pilH((string) $pilKpi['note']) ?></a>
                <?php else: ?>
                <small><?= $pilH((string) $pilKpi['note']) ?></small>
                <?php endif; ?>
                <?= $pilSpark(is_array($pilKpi['spark'] ?? null) ? $pilKpi['spark'] : null, (string) $pilKpi['spark_tone']) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php elseif ($homeWid === 'participation'): ?>
        <section class="pil-card" aria-labelledby="pil-part-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-part-title">Participation<?= count($pilPart['bars']) > 1 ? ' · ' . count($pilPart['bars']) . ' dernières' : (count($pilPart['bars']) === 1 ? ' · dernière' : '') ?></h2>
                </div>
                <div class="pil-lg" aria-hidden="true">
                    <span><em class="pil-lg__present"></em>Présents</span>
                    <span><em class="pil-lg__reg"></em>Inscrits</span>
                </div>
            </div>
            <?php if ($pilPart['bars'] === []): ?>
            <p class="pil-empty">Aucune activité terminée pour l’instant. Le graphique se remplira après les premiers créneaux pointés.</p>
            <?php else: ?>
            <div class="pil-chart" style="--pil-cols:<?= count($pilPart['bars']) ?>" role="list" aria-label="Inscrits et présents des dernières activités">
                <?php foreach ($pilPart['gridlines'] as $pilGl): ?>
                <div class="pil-chart__gl" style="bottom:<?= round((int) $pilGl / max(1, (int) $pilPart['scale']) * 100, 2) ?>%" aria-hidden="true"><span><?= (int) $pilGl ?></span></div>
                <?php endforeach; ?>
                <?php foreach ($pilPart['bars'] as $pilBar): ?>
                <?php $pilTip = $pilBar['title'] . ($pilBar['date'] !== '' ? ' (' . $pilBar['date'] . ')' : '') . ' · ' . $pilBar['present'] . ' présent' . ($pilBar['present'] > 1 ? 's' : '') . ' sur ' . $pilBar['registered'] . ' inscrit' . ($pilBar['registered'] > 1 ? 's' : ''); ?>
                <div class="pil-col" role="listitem" tabindex="0" aria-label="<?= $pilH($pilTip) ?>">
                    <div class="pil-col__bar" style="height:<?= $pilH((string) $pilBar['outer_pct']) ?>%"><i style="height:<?= $pilH((string) $pilBar['inner_pct']) ?>%"></i></div>
                    <span class="pil-col__tip" aria-hidden="true"><?= $pilH($pilTip) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="pil-xl" style="--pil-cols:<?= count($pilPart['bars']) ?>" aria-hidden="true">
                <?php foreach ($pilPart['bars'] as $pilBar): ?>
                <span title="<?= $pilH((string) $pilBar['title']) ?>"><?= $pilH((string) $pilBar['short']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php if (empty($pilPart['has_checkins'])): ?>
            <p class="pil-note">Aucun pointage enregistré sur ces activités : seuls les inscrits apparaissent.</p>
            <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'units'): ?>
        <section class="pil-card" aria-labelledby="pil-units-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-units-title">État des unités</h2>
                </div>
                <?php if (($pilLinks['readiness'] ?? null) !== null): ?>
                <a class="pil-link" href="<?= $pilH((string) $pilLinks['readiness']) ?>">Détail par unité</a>
                <?php endif; ?>
            </div>
            <?php if ($pilUnits === []): ?>
            <p class="pil-empty">Aucune unité avec des postes ou des membres pour l’instant. Construisez l’organigramme pour suivre ici le taux de disponibilité.</p>
            <?php else: ?>
            <ul class="pil-units">
                <?php foreach ($pilUnits as $pilUnit): ?>
                <li class="pil-u pil-u--<?= $pilH((string) $pilUnit['tone']) ?>">
                    <span class="pil-u__sw" aria-hidden="true"></span>
                    <div class="pil-u__body">
                        <p class="pil-u__name"><b><?= $pilH((string) $pilUnit['name']) ?></b><small><?= $pilH((string) $pilUnit['posts']) ?></small></p>
                        <div class="pil-u__tr" aria-hidden="true"><i style="width:<?= (int) $pilUnit['score'] ?>%"></i></div>
                    </div>
                    <span class="pil-u__pc"><?= (int) $pilUnit['score'] ?> %</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="pil-note">Score : postes pourvus, qualifications à jour et activité récente.</p>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'pipeline'): ?>
        <section class="pil-card" aria-labelledby="pil-rec-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-rec-title">Recrutement</h2>
                </div>
                <a class="pil-link" href="<?= $pilH((string) $pilLinks['recruitments']) ?>">Tout voir</a>
            </div>
            <div class="pil-pipe">
                <?php foreach ($pilPipeline['tiles'] as $pilTile): ?>
                <div class="<?= !empty($pilTile['hot']) ? 'is-hot' : '' ?>"><b><?= (int) $pilTile['n'] ?></b><small><?= $pilH((string) $pilTile['label']) ?></small></div>
                <?php endforeach; ?>
            </div>
            <?php if ($pilPipeline['people'] === []): ?>
            <p class="pil-empty">Aucune candidature en cours.</p>
            <?php else: ?>
            <ul class="pil-ppl">
                <?php foreach ($pilPipeline['people'] as $pilP): ?>
                <li>
                    <a class="pil-ppl__p" href="<?= $pilH((string) $pilP['href']) ?>">
                        <span class="pil-av"><?= $pilH((string) $pilP['initials']) ?></span>
                        <span class="pil-ppl__txt"><b><?= $pilH((string) $pilP['name']) ?></b><small><?= $pilH((string) $pilP['meta']) ?></small></span>
                        <span class="pil-tag pil-tone--<?= $pilH((string) $pilP['tone']) ?>"><?= $pilH((string) $pilP['tag']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'agenda'): ?>
        <section class="pil-card" aria-labelledby="pil-ag-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-ag-title">Agenda · <?= $pilH(mb_strtolower((string) $pilAgenda['title'])) ?></h2>
                </div>
                <?php if (($pilLinks['events'] ?? null) !== null): ?>
                <a class="pil-link" href="<?= $pilH((string) $pilLinks['events']) ?>">Calendrier</a>
                <?php endif; ?>
            </div>
            <?php if ($pilAgenda['items'] === []): ?>
            <p class="pil-empty">Rien n’est prévu pour le moment.</p>
            <?php else: ?>
            <ul class="pil-ag">
                <?php foreach ($pilAgenda['items'] as $pilEv): ?>
                <li class="pil-ev">
                    <span class="pil-ev__dt<?= !empty($pilEv['highlight']) ? ' is-op' : '' ?>"><small><?= $pilH((string) $pilEv['day']) ?></small><b><?= $pilH((string) $pilEv['num']) ?></b></span>
                    <span class="pil-ev__txt">
                        <?php if (!empty($pilEv['href'])): ?>
                        <a href="<?= $pilH((string) $pilEv['href']) ?>"><b><?= $pilH((string) $pilEv['title']) ?></b></a>
                        <?php else: ?>
                        <b><?= $pilH((string) $pilEv['title']) ?></b>
                        <?php endif; ?>
                        <small><?= $pilH((string) $pilEv['meta']) ?></small>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'feed'): ?>
        <section class="pil-card" aria-labelledby="pil-feed-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-feed-title">Ce qui a bougé</h2>
                </div>
                <?php if (($pilLinks['journal'] ?? null) !== null): ?>
                <a class="pil-link" href="<?= $pilH((string) $pilLinks['journal']) ?>">Journal</a>
                <?php endif; ?>
            </div>
            <?php if ($pilFeed === []): ?>
            <p class="pil-empty">Aucune action récente dans la communauté.</p>
            <?php else: ?>
            <ul class="pil-feed">
                <?php foreach ($pilFeed as $pilF): ?>
                <li class="pil-fi">
                    <span class="pil-av pil-av--<?= $pilH((string) $pilF['tone']) ?>"><?= $pilH((string) $pilF['initials']) ?></span>
                    <p><b><?= $pilH((string) $pilF['actor']) ?></b> · <?= $pilH((string) $pilF['action']) ?><?php if ((string) $pilF['target'] !== ''): ?> <b><?= $pilH((string) $pilF['target']) ?></b><?php endif; ?><?php if ((string) $pilF['when'] !== ''): ?><small><?= $pilH((string) $pilF['when']) ?></small><?php endif; ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'usage'): ?>
        <section class="pil-card" aria-labelledby="pil-use-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-use-title">Connexions sur <?= $pilPeriod ?> jours</h2>
                    <p class="pil-card__sub">Site et téléphones ATAK</p>
                </div>
                <div class="pil-lg" aria-hidden="true">
                    <span><em class="pil-lg__portal"></em>Site</span>
                    <span><em class="pil-lg__atak"></em>ATAK</span>
                </div>
            </div>
            <?php if ($pilDays === []): ?>
            <p class="pil-empty">Aucune donnée d’usage sur la période.</p>
            <?php else: ?>
            <div class="pil-usage" style="--pil-cols:<?= count($pilDays) ?>" role="img" aria-label="Connexions au site et téléphones ATAK actifs sur <?= $pilPeriod ?> jours">
                <?php foreach ($pilDays as $pilI => $pilDay): ?>
                <?php $pilPv = (int) ($pilPortal[$pilI] ?? 0); $pilAv = (int) ($pilAtak[$pilI] ?? 0); ?>
                <div class="pil-usage__d" title="<?= $pilH('Jour ' . $pilDay . ' · site ' . $pilPv . ' · ATAK ' . $pilAv) ?>">
                    <i class="pil-usage__p" style="height:<?= round($pilPv / $pilMax * 100, 1) ?>%"></i>
                    <i class="pil-usage__a" style="height:<?= round($pilAv / $pilMax * 100, 1) ?>%"></i>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="pil-xl pil-xl--days" style="--pil-cols:<?= count($pilDays) ?>" aria-hidden="true">
                <?php foreach ($pilDays as $pilDay): ?><span><?= $pilH((string) $pilDay) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'messages'): ?>
        <section class="pil-card" aria-labelledby="pil-msg-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-msg-title">Derniers messages</h2>
                </div>
                <a class="pil-link" href="<?= $pilH((string) ($pilLinks['messages'] ?? url('messages'))) ?>">Ouvrir</a>
            </div>
            <?php if ($pilMessages === []): ?>
            <p class="pil-empty">Aucune conversation récente dans votre messagerie.</p>
            <?php else: ?>
            <ul class="pil-msgs">
                <?php foreach ($pilMessages as $pilMsg): ?>
                <?php
                $pilSubject = trim((string) ($pilMsg['subject'] ?? ''));
                $pilPreview = trim(preg_replace('/\s+/u', ' ', (string) ($pilMsg['last_preview'] ?? '')) ?? '');
                if (mb_strlen($pilPreview) > 90) {
                    $pilPreview = mb_substr($pilPreview, 0, 89) . '…';
                }
                $pilUpd = strtotime((string) ($pilMsg['updated_at'] ?? '')) ?: null;
                $pilTid = (int) ($pilMsg['id'] ?? 0);
                ?>
                <li>
                    <a class="pil-msg<?= !empty($pilMsg['thread_unread']) ? ' is-unread' : '' ?>" href="<?= $pilH($pilTid > 0 ? url('messages/' . $pilTid) : url('messages')) ?>">
                        <b><?= $pilH($pilSubject !== '' ? $pilSubject : 'Conversation') ?><?php if (!empty($pilMsg['thread_unread'])): ?><span class="pil-tag pil-tone--primary">Non lu</span><?php endif; ?></b>
                        <small><?= $pilH($pilPreview !== '' ? $pilPreview : 'Aucun aperçu') ?><?= $pilUpd !== null ? ' · ' . $pilH(\App\Support\PilotageDashboardPresenter::ago($pilUpd, time())) : '' ?></small>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'gaps'): ?>
    <?php $pilGapTotal = (int) (is_array($orgProfileGaps ?? null) ? ($orgProfileGaps['total'] ?? 0) : 0); ?>
    <details class="pil-gaps" id="dossiers-incomplets">
        <summary>
            <span><b>Dossiers à compléter</b><small><?= $pilGapTotal > 0 ? $pilH($pilPlural($pilGapTotal, 'membre actif concerné', 'membres actifs concernés')) : 'Aucun trou de dossier sur les membres actifs' ?></small></span>
            <span class="pil-gaps__chev" aria-hidden="true"></span>
        </summary>
        <div class="pil-gaps__body">
            <?php require base_path('views/partials/org_dashboard_profile_gaps.php'); ?>
        </div>
    </details>
    <?php elseif ($homeWid === 'tasks'): ?>
        <section class="pil-card" aria-labelledby="pil-task-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-task-title">Tâches en attente</h2>
                </div>
                <?php if ($homeTaskTotal > 0): ?>
                <span class="pil-count" aria-label="<?= (int) $homeTaskTotal ?> en attente"><?= $pilH(\App\Services\BackOffice\Menu\BackOfficeMenuBadges::format($homeTaskTotal)) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($homeTasks === []): ?>
            <div class="pil-done">
                <?= $pilIcon('check', 'pil-i pil-done__i') ?>
                <b>Rien en attente</b>
                <span>Les compteurs du menu sont à zéro : candidatures, corrections, signalements, mise à niveau.</span>
            </div>
            <?php else: ?>
            <ul class="home-tasks">
                <?php $homeBandN = 0; foreach ($homeTasks as $homeTaskN => $homeTask): ?>
                <?php $homeTd = ($homeTask['detail'] ?? null) instanceof \App\Services\BackOffice\Menu\PendingTaskDetail ? $homeTask['detail'] : null; ?>
                <?php if ($homeTd !== null): ?>
                <?php
                // Textes déjà composés dans la langue de la personne (formule) : protégés de la traduction de la page.
                $homeTdNo = $homeTd->localized ? ' translate="no" lang="' . $pilH($homeTd->lang) . '"' : '';
                $homeTdHelpId = 'home-task-help-' . (int) $homeTaskN;
                $homeTdAccent = !empty($homeTask['important']) ? 'rose' : ['amber', 'sky', 'violet', 'emerald'][$homeBandN % 4];
                ?>
                <li class="home-task">
                    <article class="bo-band bo-band--compact bo-band--<?= $homeTdAccent ?>" style="--bo-band-i:<?= (int) $homeBandN ?>" aria-labelledby="home-task-title-<?= (int) $homeTaskN ?>">
                        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
                        <div class="bo-band__glow" aria-hidden="true"></div>
                        <div class="bo-band__veil" aria-hidden="true"></div>
                        <div class="bo-band__body">
                            <div class="bo-band__identity">
                                <span class="bo-band__index" aria-hidden="true">/ <?= sprintf('%02d', $homeBandN + 1) ?></span>
                                <div class="bo-band__who">
                                    <h3 class="bo-band__title home-task__title" id="home-task-title-<?= (int) $homeTaskN ?>"<?= $homeTdNo ?>><?= $pilH($homeTd->title !== '' ? $homeTd->title : (string) $homeTask['title']) ?></h3>
                                    <p class="bo-band__line home-task__why"<?= $homeTdNo ?>><?= $pilH($homeTd->explain) ?></p>
                                    <p class="bo-band__tag"><?= !empty($homeTask['important']) ? 'Important' : 'À traiter' ?></p>
                                </div>
                            </div>
                            <div class="bo-band__side">
                                <dl class="home-task__facts">
                                    <div>
                                        <dt>Si vous ne faites rien</dt>
                                        <dd<?= $homeTdNo ?>><?= $pilH($homeTd->consequence) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Ce qu’il faut faire</dt>
                                        <dd<?= $homeTdNo ?>><?= $pilH($homeTd->action) ?></dd>
                                    </div>
                                </dl>
                                <div class="bo-band__row">
                                    <div class="bo-band__actions">
                                        <a class="bo-band__btn bo-band__btn--solid home-task__btn" href="<?= $pilH(url($homeTd->path)) ?>"><span<?= $homeTdNo ?>><?= $pilH($homeTd->button) ?></span> <span class="bo-band__arrow" aria-hidden="true">→</span></a>
                                    </div>
                                    <p class="bo-figure<?= !empty($homeTask['important']) ? ' is-hot' : '' ?>"><strong><?= $pilH(\App\Services\BackOffice\Menu\BackOfficeMenuBadges::format((int) $homeTask['count'])) ?></strong><span>en attente</span></p>
                                </div>
                                <?php if ($homeTd->help !== '' || $homeTd->who !== ''): ?>
                                <details class="home-task__more" id="<?= $pilH($homeTdHelpId) ?>">
                                    <summary><?= $homeTd->help !== '' ? 'Qu’est-ce que c’est ?' : 'Qui peut le faire ?' ?></summary>
                                    <?php if ($homeTd->help !== ''): ?>
                                    <p<?= $homeTdNo ?>><?= $pilH($homeTd->help) ?></p>
                                    <?php endif; ?>
                                    <?php if ($homeTd->who !== ''): ?>
                                    <p><?php if ($homeTd->help !== ''): ?><b>Qui peut le faire ?</b> <?php endif; ?><span<?= $homeTdNo ?>><?= $pilH($homeTd->who) ?></span></p>
                                    <?php endif; ?>
                                </details>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </li>
                <?php $homeBandN++; ?>
                <?php else: ?>
                <li>
                    <a href="<?= $pilH(url($homeTask['path'])) ?>" class="home-tasks__it">
                        <span class="ath-mbadge <?= !empty($homeTask['important']) ? 'ath-mbadge--important' : 'ath-mbadge--notif' ?>"><?= $pilH(\App\Services\BackOffice\Menu\BackOfficeMenuBadges::format((int) $homeTask['count'])) ?></span>
                        <span><?= $pilH((string) $homeTask['title']) ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'shortcuts'): ?>
        <section class="pil-card" aria-labelledby="pil-sc-title">
            <div class="pil-card__h">
                <div>
                    <h2 class="pil-card__title" id="pil-sc-title">Vos raccourcis</h2>
                </div>
            </div>
            <?php if ($homeShortcuts === []): ?>
            <p class="pil-empty">Aucun raccourci. Survolez une page du menu de gauche et cliquez sur l’étoile : elle apparaîtra ici et en tête du menu.</p>
            <?php else: ?>
            <ul class="home-sc">
                <?php foreach ($homeShortcuts as $homeSc): ?>
                <li><a class="home-sc__it" href="<?= $pilH(url((string) $homeSc['path'])) ?>"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"></path></svg><span><?= $pilH((string) $homeSc['label']) ?></span></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    <?php elseif ($homeWid === 'custom'): ?>
        <?php require base_path('views/admin/organization/_home_custom.php'); ?>
    <?php elseif (\App\Services\BackOffice\Home\HomeLayout::isSheet($homeWid)): ?>
        <?php $homeSid = \App\Services\BackOffice\Home\HomeLayout::sheetId($homeWid); ?>
        <?php if (isset($homeSheets[$homeSid])): ?>
        <section class="pil-card home-sheet" aria-label="<?= $pilH('Tableau ' . $homeSheets[$homeSid]['title']) ?>">
            <?php $sheetView = $homeSheets[$homeSid]; $sheetCompact = true; require base_path('views/admin/organization/_sheet.php'); ?>
        </section>
        <?php endif; ?>
    <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<script>
(function () {
    var root = document.querySelector('[data-pilotage]');
    if (!root) { return; }
    // Ouvre la liste des dossiers quand on y arrive par un lien.
    function openGaps() {
        if (location.hash === '#dossiers-incomplets') {
            var d = document.getElementById('dossiers-incomplets');
            if (d) { d.open = true; }
        }
    }
    openGaps();
    window.addEventListener('hashchange', openGaps);
    // Bandeau de nouveautés : masqué jusqu’à ce que leur nombre change.
    root.querySelectorAll('[data-pil-dismissable]').forEach(function (el) {
        var key = 'pil-hide-' + el.getAttribute('data-pil-dismissable');
        try { if (localStorage.getItem(key) === '1') { el.hidden = true; } } catch (e) {}
        var btn = el.querySelector('[data-pil-dismiss]');
        if (btn) {
            btn.addEventListener('click', function () {
                el.hidden = true;
                try { localStorage.setItem(key, '1'); } catch (e) {}
            });
        }
    });
    // Compte à rebours de la prochaine activité.
    var cd = root.querySelector('[data-pil-countdown]');
    if (cd) {
        var target = Date.parse(cd.getAttribute('data-pil-countdown'));
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var tick = function () {
            var d = Math.max(0, target - Date.now());
            cd.querySelector('[data-cd="d"]').textContent = pad(Math.floor(d / 864e5));
            cd.querySelector('[data-cd="h"]').textContent = pad(Math.floor(d % 864e5 / 36e5));
            cd.querySelector('[data-cd="m"]').textContent = pad(Math.floor(d % 36e5 / 6e4));
        };
        if (!isNaN(target)) { setInterval(tick, 30000); }
    }
})();
</script>
<script defer src="<?= $pilH(asset_url('assets/js/back-office-sheet.js')) ?>"></script>
<?php if (!empty($home['customize'])): ?>
<script defer src="<?= $pilH(asset_url('assets/js/back-office-home.js')) ?>"></script>
<?php endif; ?>
