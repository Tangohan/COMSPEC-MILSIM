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
    // Accueil en widgets : disposition par personne et par communauté (App\Services\BackOffice\Home\HomeLayout).
    $home = is_array($home ?? null) ? $home : [];
    $homeTasks = is_array($home['tasks'] ?? null) ? $home['tasks'] : [];
    $homeTaskTotal = array_sum(array_map(static fn (array $t): int => (int) $t['count'], $homeTasks));
    // Raccourcis = favoris du menu (libellés et droits déjà résolus par le menu latéral).
    $homeShortcuts = isset($athMenu) && is_array($athMenu['favorites'] ?? null) ? $athMenu['favorites'] : [];
    require base_path('views/admin/organization/_home_widgets.php');
    ?>
</div>
<script>
(function () {
    var root = document.querySelector('[data-pilotage]');
    if (!root) { return; }
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
<script defer src="<?= $pilH(asset_url('assets/js/back-office-bands.js')) ?>"></script>
<script defer src="<?= $pilH(asset_url('assets/js/back-office-home.js')) ?>"></script>
<?php endif; ?>
