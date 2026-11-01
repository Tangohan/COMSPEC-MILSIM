<?php
declare(strict_types=1);

use App\Support\ServerControlPresenter as P;
use App\Support\ServerControlWiring as W;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$themes = is_array($themes ?? null) ? $themes : [];
$soon = is_array($soon ?? null) ? $soon : [];
$stateCounts = is_array($stateCounts ?? null) ? $stateCounts : [];
$preview = is_array($preview ?? null) ? $preview : [];
$presence = is_array($presence ?? null) ? $presence : [];
$now = (int) ($now ?? time());
$netLive = is_array($netLive ?? null) ? $netLive : [];
$phoneApps = is_array($phoneApps ?? null) ? $phoneApps : [];
$appsPolicy = is_array($appsPolicy ?? null) ? $appsPolicy : [];
$relays = is_array($relays ?? null) ? $relays : [];
$journal = is_array($journal ?? null) ? $journal : [];
$maintenanceEnabled = !empty($maintenanceEnabled);
$schemaReady = !empty($experienceSchemaReady);
$savedAt = (string) ($savedAt ?? '');
$savedTs = $savedAt !== '' ? strtotime($savedAt) : false;
$success = $success ?? null;
$error = $error ?? null;

$appsBySection = [];
foreach ($phoneApps as $app) {
    $appsBySection[(string) ($app['section'] ?? '')][] = $app;
}
$appsRequired = count(array_filter($appsPolicy, static fn ($r): bool => $r === 'required'));
$appsForbidden = count(array_filter($appsPolicy, static fn ($r): bool => $r === 'forbidden'));
$appsBelow = P::countBelow(is_array($presence['versions'] ?? null) ? $presence['versions'] : [], (string) W::APPS['min']);
$aliveRelays = count(array_filter($relays, static fn (array $r): bool => !empty($r['alive'])));
$plural = static fn (int $n, string $one, string $many): string => $n > 1 ? $many : $one;

/** Indicateur à trois crans : enregistré sur le site, transmis au jeu, appliqué en jeu. */
$badge = static function (string $state) use ($h): string {
    $label = W::STATES[$state] ?? W::STATES['todo'];
    $lit = ['live' => 3, 'partial' => 2][$state] ?? 1;
    $bars = '';
    foreach (['Enregistré sur le site', 'Transmis au jeu', 'Appliqué en jeu'] as $i => $step) {
        $bars .= '<i class="' . ($i < $lit ? 'on' : '') . '" title="' . $h($step) . '"></i>';
    }

    return '<span class="sc-state sc-state--' . $h($state) . '"><span class="sc-state__bars" aria-hidden="true">' . $bars . '</span>' . $h($label) . '</span>';
};

/** Contrôle d'un réglage : interrupteur, choix segmenté (trois choix au plus) ou liste. */
$control = static function (array $item) use ($h, $schemaReady): string {
    $id = (string) $item['id'];
    $isModule = $item['kind'] === 'module';
    $name = ($isModule ? 'module_' : 'experience_') . $id;
    $domId = 'sc-' . ($isModule ? 'm-' : 'r-') . $id;
    $off = !$isModule && !$schemaReady ? ' disabled' : '';
    $type = (string) $item['type'];
    if ($type === 'bool' || $type === 'module') {
        return '<label class="sc-switch" for="' . $h($domId) . '"><input type="hidden" name="' . $h($name) . '" value="0"' . $off . '>'
            . '<input type="checkbox" role="switch" id="' . $h($domId) . '" name="' . $h($name) . '" value="1"' . (!empty($item['value']) ? ' checked' : '') . $off . '>'
            . '<span class="sc-switch__track" aria-hidden="true"></span><span class="sc-switch__txt sc-switch__txt--on">' . ($isModule ? 'Fonction activée' : 'Activé') . '</span><span class="sc-switch__txt sc-switch__txt--off">' . ($isModule ? 'Fonction coupée' : 'Désactivé') . '</span></label>';
    }
    $choices = is_array($item['choices'] ?? null) ? $item['choices'] : [];
    $value = (string) $item['value'];
    if (count($choices) <= 3) {
        $out = '<fieldset class="sc-seg"' . $off . '><legend class="sc-sr">' . $h($item['label']) . '</legend>';
        foreach ($choices as $i => $c) {
            $cv = (string) ($c['value'] ?? '');
            $cid = $domId . '-' . $i;
            $out .= '<input type="radio" id="' . $h($cid) . '" name="' . $h($name) . '" value="' . $h($cv) . '"' . ($cv === $value ? ' checked' : '') . '>'
                . '<label for="' . $h($cid) . '">' . $h($c['label'] ?? $cv) . '</label>';
        }

        return $out . '</fieldset>';
    }
    $out = '<label class="sc-sr" for="' . $h($domId) . '">' . $h($item['label']) . '</label><select class="sc-select" id="' . $h($domId) . '" name="' . $h($name) . '"' . $off . '>';
    foreach ($choices as $c) {
        $cv = (string) ($c['value'] ?? '');
        $out .= '<option value="' . $h($cv) . '"' . ($cv === $value ? ' selected' : '') . '>' . $h($c['label'] ?? $cv) . '</option>';
    }

    return $out . '</select>';
};

/** Une ligne de réglage : nom, état réel, impact pour les joueurs, où / quand / version, contrôle. */
$row = static function (array $item) use ($h, $badge, $control, $plural): string {
    $meta = '';
    if ((string) $item['where'] !== '') {
        $meta .= '<span><b>Où</b> ' . $h($item['where']) . '</span>';
    }
    if ((string) $item['delay_label'] !== '') {
        $meta .= '<span><b>Délai</b> ' . $h($item['delay_label']) . '</span>';
    }
    if ($item['min'] !== null) {
        $below = (int) $item['below_min'];
        $meta .= '<span><b>Mod</b> ATAK natif ' . $h($item['min']) . ' ou plus récent'
            . ($below > 0 ? ' <em class="sc-warn">' . $below . ' ' . $plural($below, 'téléphone trop ancien', 'téléphones trop anciens') . ' cette semaine</em>' : '')
            . '</span>';
    }
    if (($item['note'] ?? '') === 'retired') {
        $meta .= '<span><b>Origine</b> Ancien mod, plus maintenu</span>';
    }
    $imposed = !empty($item['imposed']) && $item['state'] !== 'todo';
    $tag = $item['kind'] === 'module' ? 'Fonction coupée' : 'Imposé à tous';

    return '<div class="sc-row' . ($imposed ? ' is-imposed' : '') . '" data-sc-row>'
        . '<div class="sc-row__text"><div class="sc-row__head"><h4 class="sc-row__label">' . $h($item['label']) . '</h4>' . $badge((string) $item['state'])
        . ($imposed ? '<span class="sc-tag">' . $tag . '</span>' : '') . '</div>'
        . '<p class="sc-row__impact">' . $h($item['impact']) . '</p>'
        . ($meta !== '' ? '<p class="sc-row__meta">' . $meta . '</p>' : '')
        . '</div><div class="sc-row__ctl">' . $control($item) . '</div></div>';
};
?>
<div class="sc">
    <?php if ($success): ?>
        <p class="sc-flash sc-flash--ok" role="status"><?= $h($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="sc-flash sc-flash--err" role="alert"><?= $h($error) ?></p>
    <?php endif; ?>

    <section class="sc-hero" aria-labelledby="sc-hero-title">
        <div class="sc-hero__server">
            <h2 id="sc-hero-title" class="sc-hero__title">Serveur de jeu de la communauté</h2>
            <?php if (empty($presence['has_data'])): ?>
                <p class="sc-hero__empty">Aucun téléphone n’a encore remonté d’information. Lancez une partie avec le mod ATAK natif : l’état du serveur apparaîtra ici.</p>
            <?php else: ?>
                <?php $online = (int) $presence['online']; ?>
                <p class="sc-hero__now">
                    <span class="sc-live<?= $online > 0 ? ' is-on' : '' ?>" aria-hidden="true"></span>
                    <strong class="sc-hero__big"><?= $online ?></strong>
                    <span><?= $online > 0 ? $plural($online, 'joueur en jeu en ce moment', 'joueurs en jeu en ce moment') : 'personne en jeu en ce moment' ?></span>
                </p>
                <dl class="sc-facts">
                    <div><dt>Dernière remontée</dt><dd><?= $h(P::ago($presence['last_seen'], $now)) ?></dd></div>
                    <?php if ($presence['server'] !== ''): ?>
                        <div><dt>Serveur</dt><dd><?= $h($presence['server']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($presence['mission'] !== ''): ?>
                        <div><dt>Mission</dt><dd><?= $h($presence['mission']) ?><?= $presence['world'] !== '' ? ' sur ' . $h($presence['world']) : '' ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Téléphones vus cette semaine</dt><dd><?= (int) $presence['recent'] ?></dd></div>
                    <?php if ($presence['mod_top'] !== ''): ?>
                        <div><dt>Version du mod la plus courante</dt><dd><?= $h($presence['mod_top']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($presence['mod_oldest'] !== '' && $presence['mod_oldest'] !== $presence['mod_top']): ?>
                        <div><dt>Version la plus ancienne</dt><dd><?= $h($presence['mod_oldest']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($presence['link_top'] !== ''): ?>
                        <div><dt>COMSPEC Link</dt><dd><?= $h($presence['link_top']) ?></dd></div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>
            <?php if ($maintenanceEnabled): ?>
                <p class="sc-hero__alert">Maintenance active : la carte et la liaison jeu sont coupées pour les joueurs. Rétablissez-les depuis <a href="<?= $h(url('back-office/ressources/atak-config')) ?>">Configuration ATAK</a>.</p>
            <?php endif; ?>
            <p class="sc-hero__links">
                <a href="<?= $h(url('back-office/atak/parc-atak')) ?>">Parc ATAK des membres</a>
                <a href="<?= $h(url('back-office/atak/roleplay')) ?>">Mode roleplay</a>
                <a href="<?= $h(url('back-office/atak/relays-network')) ?>">Réseau de relais</a>
                <a href="<?= $h(url('back-office/ressources/atak-config')) ?>">Configuration ATAK</a>
            </p>
        </div>

        <aside class="sc-phone" aria-labelledby="sc-phone-title">
            <div class="sc-phone__notch" aria-hidden="true"></div>
            <h3 id="sc-phone-title" class="sc-phone__title">Ce que vos joueurs vont avoir</h3>
            <?php if ($preview === []): ?>
                <p class="sc-phone__empty">Aucune règle imposée : chaque joueur règle son téléphone comme il veut.</p>
            <?php else: ?>
                <ul class="sc-phone__list">
                    <?php foreach (array_slice($preview, 0, 9) as $p): ?>
                        <li class="sc-phone__item<?= $p['state'] === 'partial' ? ' is-partial' : '' ?>"><span><?= $h($p['label']) ?></span><b><?= $h($p['value']) ?></b></li>
                    <?php endforeach; ?>
                </ul>
                <?php $extra = count($preview) - 9; ?>
                <?php if ($extra > 0): ?>
                    <p class="sc-phone__more">Et <?= $extra ?> <?= $plural($extra, 'autre règle imposée', 'autres règles imposées') ?>.</p>
                <?php endif; ?>
            <?php endif; ?>
            <p class="sc-phone__foot">Ces règles se verrouillent dans les réglages du téléphone. Un guide les reprend dans le journal de la carte en jeu.</p>
        </aside>
    </section>

    <section class="sc-legend" aria-label="Ce que signifient les états">
        <p class="sc-legend__lead">Chaque réglage dit s’il agit vraiment en jeu : enregistré sur le site, transmis au jeu, puis appliqué.</p>
        <ul class="sc-legend__list">
            <li><?= $badge('live') ?><span><?= (int) ($stateCounts['live'] ?? 0) ?> réglages agissent en jeu.</span></li>
            <li><?= $badge('partial') ?><span><?= (int) ($stateCounts['partial'] ?? 0) ?> agissent en partie ou avec retard.</span></li>
            <li><?= $badge('todo') ?><span><?= (int) ($stateCounts['todo'] ?? 0) ?> sans effet pour l’instant, rangés dans « Bientôt ».</span></li>
        </ul>
    </section>

    <nav class="sc-nav" aria-label="Thèmes de la page">
        <?php foreach ($themes as $tid => $theme): ?>
            <a href="#theme-<?= $h($tid) ?>"><?= $h($theme['label']) ?></a>
        <?php endforeach; ?>
        <a href="#apps">Apps du téléphone</a>
        <a href="#relais">Relais</a>
        <?php if ($soon !== []): ?><a href="#bientot">Bientôt</a><?php endif; ?>
        <a href="#journal">Journal</a>
    </nav>

    <?php if (!$schemaReady): ?>
        <p class="sc-flash sc-flash--err" role="alert">Les règles et les apps ne peuvent pas encore être enregistrées : la base n’est pas à jour. Contactez le support. Les fonctions reliées restent modifiables.</p>
    <?php endif; ?>

    <form method="post" action="<?= $h(url('back-office/atak/controle-serveur/regles')) ?>" id="regles" class="sc-form" data-sc-form>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <span id="fonctions" class="sc-anchor" aria-hidden="true"></span>

        <?php foreach ($themes as $tid => $theme): ?>
            <section class="sc-card" id="theme-<?= $h($tid) ?>" aria-labelledby="theme-<?= $h($tid) ?>-t">
                <header class="sc-card__h">
                    <h3 class="sc-card__title" id="theme-<?= $h($tid) ?>-t"><?= $h($theme['label']) ?></h3>
                    <p class="sc-card__lead"><?= $h($theme['lead']) ?></p>
                </header>
                <?php if ($tid === 'reseau'): ?>
                    <?php
                    $liveCond = is_array($netLive['conditions'] ?? null) ? $netLive['conditions'] : [];
                    $liveAt = (string) ($netLive['at'] ?? '');
                    $liveTs = $liveAt !== '' ? strtotime($liveAt) : false;
                    $byId = array_column($theme['items'], null, 'id');
                    ?>
                    <div class="sc-ingame" id="conditions-en-jeu">
                        <h4 class="sc-ingame__t">Conditions appliquées en jeu</h4>
                        <?php if ($liveTs === false): ?>
                            <p class="sc-ingame__p">Aucune pour l’instant. Elles apparaissent ici dès qu’un administrateur du serveur les applique depuis le menu Échap.</p>
                        <?php else: ?>
                            <p class="sc-ingame__p">
                                <?= !empty($netLive['reset']) ? 'Retour aux réglages du serveur' : 'Appliquées' ?> par <strong><?= $h((string) ($netLive['applied_by'] ?? '?')) ?></strong>
                                <?= $h(P::ago((int) $liveTs, $now)) ?><?php if ((string) ($netLive['mission'] ?? '') !== ''): ?>, mission <?= $h((string) $netLive['mission']) ?><?php endif; ?><?php if ((string) ($netLive['world'] ?? '') !== ''): ?> (<?= $h((string) $netLive['world']) ?>)<?php endif; ?>.
                            </p>
                            <?php if ($liveCond !== []): ?>
                                <ul class="sc-ingame__list">
                                    <?php foreach ($liveCond as $cid => $cv): ?>
                                        <?php $ci = $byId[$cid] ?? null; if ($ci === null) { continue; } ?>
                                        <li><?= $h($ci['label']) ?> : <strong><?= $h(P::valueLabel($ci, $cv)) ?></strong><?= !empty($ci['imposed']) ? ' <em>(la règle du site passe avant)</em>' : '' ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="submit" form="net-adopt" class="sc-btn sc-btn--ghost"<?= $schemaReady ? '' : ' disabled' ?>>Garder ces conditions comme règles de la communauté</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="sc-rows">
                    <?php foreach ($theme['items'] as $item): ?>
                        <?= $row($item) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="sc-card" id="apps" aria-labelledby="apps-t">
            <header class="sc-card__h">
                <div class="sc-card__hrow">
                    <h3 class="sc-card__title" id="apps-t">Apps du téléphone</h3>
                    <?= $badge((string) W::APPS['state']) ?>
                </div>
                <p class="sc-card__lead">Chaque joueur installe ses apps dans l’App Store de son téléphone. <b>Obligatoire</b> : installée chez tous, impossible à désinstaller. <b>Interdite</b> : retirée des téléphones, elle ne s’ouvre plus. Un Zeus ou l’administrateur du serveur peut en ajouter pour une partie.</p>
                <p class="sc-row__meta">
                    <span><b>Délai</b> <?= $h(W::DELAYS[W::APPS['delay']]) ?></span>
                    <span><b>Mod</b> ATAK natif <?= $h(W::APPS['min']) ?> ou plus récent<?= $appsBelow > 0 ? ' <em class="sc-warn">' . $appsBelow . ' ' . $plural($appsBelow, 'téléphone trop ancien', 'téléphones trop anciens') . ' cette semaine</em>' : '' ?></span>
                    <span><b>En ce moment</b> <?= $appsRequired ?> <?= $plural($appsRequired, 'obligatoire', 'obligatoires') ?>, <?= $appsForbidden ?> <?= $plural($appsForbidden, 'interdite', 'interdites') ?></span>
                </p>
            </header>
            <?php foreach ($appsBySection as $section => $apps): ?>
                <h4 class="sc-apps__section"><?= $h($section) ?></h4>
                <div class="sc-apps">
                    <?php foreach ($apps as $app): ?>
                        <?php $aid = (string) $app['id']; $rule = (string) ($appsPolicy[$aid] ?? 'player'); ?>
                        <div class="sc-app sc-app--<?= $h($rule) ?>" data-sc-row>
                            <div class="sc-app__text">
                                <span class="sc-app__name"><?= $h((string) $app['name']) ?></span>
                                <span class="sc-app__desc"><?= $h((string) ($app['description'] ?? '')) ?></span>
                            </div>
                            <fieldset class="sc-seg sc-seg--sm"<?= $schemaReady ? '' : ' disabled' ?>>
                                <legend class="sc-sr"><?= $h((string) $app['name']) ?></legend>
                                <?php foreach (['player' => 'Au choix', 'required' => 'Obligatoire', 'forbidden' => 'Interdite'] as $rv => $rl): ?>
                                    <input type="radio" id="app-<?= $h($aid . '-' . $rv) ?>" name="app_<?= $h($aid) ?>" value="<?= $h($rv) ?>"<?= $rule === $rv ? ' checked' : '' ?>>
                                    <label for="app-<?= $h($aid . '-' . $rv) ?>"><?= $h($rl) ?></label>
                                <?php endforeach; ?>
                            </fieldset>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <p class="sc-card__foot">Réglages, App Store et Alertes font partie du téléphone : toujours installés.</p>
        </section>

        <?php if ($soon !== []): ?>
            <details class="sc-card sc-soon" id="bientot">
                <summary class="sc-soon__sum">
                    <span class="sc-card__title">Bientôt</span>
                    <span class="sc-soon__count"><?= count($soon) ?> réglages sans effet en jeu pour l’instant</span>
                </summary>
                <p class="sc-card__lead">Ces réglages sont gardés tels quels, mais le mod actuel ne les lit pas : les changer ne modifie rien pour les joueurs.</p>
                <div class="sc-rows">
                    <?php foreach ($soon as $item): ?>
                        <?= $row($item) ?>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>

        <div class="sc-savebar" data-sc-savebar role="region" aria-label="Enregistrement">
            <p class="sc-savebar__msg" data-sc-msg aria-live="polite"><?= $savedTs !== false ? 'Tout est enregistré. Dernier enregistrement ' . $h(P::ago((int) $savedTs, $now)) . '.' : 'Tout est enregistré.' ?></p>
            <div class="sc-savebar__acts">
                <button type="reset" class="sc-btn sc-btn--ghost" data-sc-reset hidden>Annuler les modifications</button>
                <button type="submit" class="sc-btn sc-btn--solid" data-sc-submit>Enregistrer</button>
            </div>
        </div>
    </form>
    <form method="post" action="<?= $h(url('back-office/atak/controle-serveur/reseau-adopter')) ?>" id="net-adopt" hidden>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
    </form>

    <section class="sc-card" id="relais" aria-labelledby="relais-t">
        <header class="sc-card__h">
            <div class="sc-card__hrow">
                <h3 class="sc-card__title" id="relais-t">Relais de liaison</h3>
                <span class="sc-count"><?= $aliveRelays ?> <?= $plural($aliveRelays, 'intact', 'intacts') ?> sur <?= count($relays) ?></span>
            </div>
            <p class="sc-card__lead">Les mâts se posent en jeu (éditeur ou Zeus) ; ceux déjà vus sont listés ici. Le réglage « Exiger un relais pour la liaison » se trouve dans <a href="<?= $h(url('back-office/atak/roleplay')) ?>">Mode roleplay</a>.</p>
        </header>
        <?php if ($relays === []): ?>
            <p class="sc-empty">Aucun relais vu pour l’instant. Posez-en un en mission pour qu’il apparaisse ici.</p>
        <?php else: ?>
            <ul class="sc-relays">
                <?php foreach ($relays as $relay): ?>
                    <?php
                    $alive = !empty($relay['alive']);
                    $name = trim((string) ($relay['display_name'] ?? '')) ?: (trim((string) ($relay['identity'] ?? '')) ?: (string) ($relay['relay_uid'] ?? 'Relais'));
                    $bits = [];
                    if ((string) ($relay['map_label'] ?? '') !== '') {
                        $bits[] = (string) $relay['map_label'];
                    }
                    $range = (int) round((float) ($relay['range_m'] ?? 0));
                    if ($range > 0) {
                        $bits[] = 'portée ' . $range . ' m';
                    }
                    if ($alive) {
                        $bits[] = rtrim(rtrim(number_format((float) ($relay['throughput_mbps'] ?? 0), 1, ',', ''), '0'), ',') . ' Mbit/s';
                        $bits[] = 'fiabilité ' . (int) ($relay['reliability_pct'] ?? 0) . ' %';
                        if ((int) ($relay['slots'] ?? 0) > 0) {
                            $bits[] = (int) ($relay['slots_used'] ?? 0) . ' / ' . (int) $relay['slots'] . ' places';
                        }
                    }
                    if (trim((string) ($relay['ip_addr'] ?? '')) !== '') {
                        $bits[] = 'adresse réseau ' . trim((string) $relay['ip_addr']);
                    }
                    $seen = \App\Support\UtcDateTime::timestamp($relay['last_seen_at'] ?? null);
                    if ($seen !== null) {
                        $bits[] = 'vu ' . P::ago($seen, $now);
                    }
                    ?>
                    <li class="sc-relay">
                        <div><p class="sc-relay__name"><?= $h($name) ?></p><p class="sc-relay__meta"><?= $h(implode(', ', $bits)) ?></p></div>
                        <span class="sc-pill <?= $alive ? 'sc-pill--ok' : 'sc-pill--ko' ?>"><?= $alive ? 'Intact' : 'Hors service' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="sc-card" id="journal" aria-labelledby="journal-t">
        <header class="sc-card__h">
            <h3 class="sc-card__title" id="journal-t">Journal des changements</h3>
            <p class="sc-card__lead">Qui a changé quoi sur cette page, du plus récent au plus ancien.</p>
        </header>
        <?php if ($journal === []): ?>
            <p class="sc-empty">Aucun changement enregistré depuis la mise en place du journal.</p>
        <?php else: ?>
            <ol class="sc-journal">
                <?php foreach ($journal as $j): ?>
                    <li>
                        <p class="sc-journal__h"><b><?= $h($j['who']) ?></b> <?= $h($j['title']) ?><?php if ($j['at'] !== ''): ?> <time><?= $h($j['at']) ?></time><?php endif; ?></p>
                        <?php if ($j['changes'] !== []): ?>
                            <ul class="sc-journal__list">
                                <?php foreach ($j['changes'] as $c): ?><li><?= $h($c) ?></li><?php endforeach; ?>
                                <?php if ($j['more'] > 0): ?><li>et <?= (int) $j['more'] ?> <?= $plural((int) $j['more'], 'autre', 'autres') ?></li><?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var form = document.querySelector('[data-sc-form]');
    if (!form) { return; }
    var msg = form.querySelector('[data-sc-msg]');
    var bar = form.querySelector('[data-sc-savebar]');
    var reset = form.querySelector('[data-sc-reset]');
    var submit = form.querySelector('[data-sc-submit]');
    var clean = msg.textContent;
    var sending = false;
    function changed(el) {
        if (el.type === 'checkbox' || el.type === 'radio') { return el.checked !== el.defaultChecked; }
        if (el.tagName === 'SELECT') { return el.selectedIndex >= 0 && !el.options[el.selectedIndex].defaultSelected; }
        return false;
    }
    function refresh() {
        var rows = new Set();
        form.querySelectorAll('[data-sc-row].is-dirty').forEach(function (r) { r.classList.remove('is-dirty'); });
        form.querySelectorAll('input[type=checkbox], input[type=radio], select').forEach(function (el) {
            var r = changed(el) ? el.closest('[data-sc-row]') : null;
            if (r) { rows.add(r); }
        });
        rows.forEach(function (r) { r.classList.add('is-dirty'); });
        var n = rows.size;
        bar.classList.toggle('is-dirty', n > 0);
        reset.hidden = n === 0;
        msg.textContent = n === 0 ? clean : n + (n > 1 ? ' réglages modifiés, pas encore enregistrés.' : ' réglage modifié, pas encore enregistré.');
        return n;
    }
    form.addEventListener('change', refresh);
    form.addEventListener('reset', function () { setTimeout(refresh, 0); });
    form.addEventListener('submit', function () {
        sending = true;
        submit.disabled = true;
        submit.textContent = 'Enregistrement…';
    });
    window.addEventListener('beforeunload', function (e) {
        if (!sending && refresh() > 0) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>
