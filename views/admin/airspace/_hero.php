<?php
declare(strict_types=1);

/**
 * Espace aérien : bandeau commun (titre, chiffres clés, actions, onglets) des pages aviation du back-office.
 *
 * @var string $aeroTab onglet actif : ligne, cursus, pilotes, carnet, flotte ou reglages (page Cursus pilote)
 * @var array{pilots: int, current_ok: int, current_total: int, hours_30: float, queue: int}|null $aeroHud
 * @var array<string, int> $aeroTabCounts compteurs d'onglet (ligne, carnet)
 * @var bool $allowed
 */
$aeroH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$aeroTab = (string) ($aeroTab ?? 'ligne');
$aeroHud = $aeroHud ?? null;
$aeroTabCounts = is_array($aeroTabCounts ?? null) ? $aeroTabCounts : [];
$aeroIcons = [
    'radar' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 12 19 6"/>',
    'route' => '<circle cx="5" cy="18" r="2"/><circle cx="19" cy="6" r="2"/><path d="M7 18h7a3 3 0 0 0 0-6h-4a3 3 0 0 1 0-6h7"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.3-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5c2 .6 3.2 2.5 3.5 5.5"/>',
    'book' => '<path d="M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4zM20 4h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6z"/>',
    'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
    'sliders' => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'bolt' => '<path d="M13 3 4 14h7l-1 7 9-11h-7z"/>',
];
$aeroIcon = static fn (string $n): string => '<svg class="aero-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($aeroIcons[$n] ?? '') . '</svg>';
$aeroTabs = [
    ['ligne', 'Ligne de vol', 'radar', url('back-office/espace-aerien')],
    ['cursus', 'Cursus pilote', 'route', url('back-office/espace-aerien') . '?vue=cursus'],
    ['pilotes', 'Pilotes', 'users', url('back-office/espace-aerien') . '?vue=pilotes'],
    ['carnet', 'Carnet de vol', 'book', url('back-office/espace-aerien') . '?vue=carnet'],
    ['flotte', 'Flotte et filières', 'cog', url('back-office/espace-aerien') . '?vue=flotte'],
    ['reglages', 'Réglages du cursus', 'sliders', url('back-office/referentiels/qualifications/cursus-pilote')],
];
?>
<section class="aero-hero" aria-labelledby="aero-title">
    <div class="aero-hero__bg" aria-hidden="true"></div>
    <div class="aero-hero__in">
        <div>
            <div class="aero-kk"><i></i>Opérations · Aviation</div>
            <h1 id="aero-title"><?= $aeroTab === 'reglages' ? 'Cursus pilote' : 'Espace aérien' ?></h1>
            <p><?= $aeroTab === 'reglages' ? 'Paliers, appareils et familles du cursus : les heures viennent du mod et du carnet de vol, les paliers suivent, les instructeurs valident.' : 'Ligne de vol, cursus des pilotes et carnet de vol. Les heures viennent du mod, les paliers suivent, les instructeurs valident.' ?></p>
        </div>
        <div class="aero-hero__side">
            <?php if (is_array($aeroHud)): ?>
            <div class="aero-hud" role="list">
                <div role="listitem"><small>Pilotes</small><b><?= (int) $aeroHud['pilots'] ?></b></div>
                <div role="listitem"><small>À jour</small><b><?= (int) $aeroHud['current_ok'] ?>/<?= (int) $aeroHud['current_total'] ?></b></div>
                <div role="listitem"><small>30 jours</small><b><?= $aeroH(number_format((float) $aeroHud['hours_30'], 0, ',', ' ')) ?> h</b></div>
                <div role="listitem"><small>À valider</small><b class="<?= (int) $aeroHud['queue'] > 0 ? 'is-hot' : '' ?>"><?= (int) $aeroHud['queue'] ?></b></div>
            </div>
            <?php endif; ?>
            <div class="aero-hero__acts">
                <a class="aero-hb2" href="<?= $aeroH(url('back-office/ma-situation/carnet-de-vol')) ?>"><?= $aeroIcon('plus') ?>Noter un vol</a>
                <form method="post" action="<?= $aeroH(url('back-office/referentiels/qualifications/cursus-pilote/evaluer')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="retour" value="<?= $aeroTab === 'reglages' ? 'back-office/referentiels/qualifications/cursus-pilote' : 'back-office/espace-aerien' ?>">
                    <button type="submit" class="aero-hb1"<?= !empty($allowed) ? '' : ' disabled' ?>><?= $aeroIcon('bolt') ?>Évaluer maintenant</button>
                </form>
            </div>
        </div>
    </div>
    <nav class="aero-nav" aria-label="Espace aérien">
        <?php foreach ($aeroTabs as [$key, $label, $icon, $href]): ?>
        <a class="aero-tab<?= $aeroTab === $key ? ' is-on' : '' ?>" href="<?= $aeroH($href) ?>"<?= $aeroTab === $key ? ' aria-current="page"' : '' ?>><?= $aeroIcon($icon) ?><?= $aeroH($label) ?><?php if (!empty($aeroTabCounts[$key])): ?><span class="aero-tab__n<?= $key === 'ligne' ? ' is-hot' : '' ?>"><?= (int) $aeroTabCounts[$key] ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
</section>
