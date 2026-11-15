<?php
declare(strict_types=1);
/**
 * Page Personnes — contenu (sans la coque du bureau).
 *
 * @var array<string, mixed> $listing résultat de SsePersonWorkService::listing
 * @var \App\Services\Sse\SsePersonViewer $viewer
 * @var string $csrf
 * @var list<array<string, mixed>> $caseOptions dossiers où rattacher une sélection
 * @var int $now
 */

use App\Support\SsePersonUi as Ui;
use App\Support\SsePersonWork as W;

$h ??= static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$now = (int) ($now ?? time());
$q = $listing['query'];
$rows = $listing['rows'];
$counts = $listing['counts'];
$ready = (bool) $listing['ready'];
$canAct = $ready && $viewer->canWrite;
$view = $q['vue'] === 'cartes' ? 'cartes' : 'tableau';
$base = url('atak/sse/identites');
$link = static fn (array $override = []): string => $base . (($qs = W::queryString($q, $override + ['p' => 1])) !== '' ? '?' . $qs : '');
$ficheUrl = static fn (int $id): string => url('atak/sse/identites/' . $id);
$denyReason = !$ready
    ? 'Disponible après la mise à jour de la base de données (étape « SSE Personnes »).'
    : 'Droit manquant : écrire dans « Fichiers et sujets ».';

$tone = static fn (string $t): string => 'pe-tone-' . $t;
$ico = static fn (string $name): string => '<svg class="pe-ico" width="18" height="18" aria-hidden="true" focusable="false"><use href="#pe-i-' . $name . '"/></svg>';

/** Pastille de statut : point de couleur et libellé court (clos prime). */
$status = static function (array $r) use ($h): string {
    $short = ['civil' => 'Civil', 'combattant' => 'Combattant', 'detenu' => 'Détenu', 'prioritaire' => 'Prioritaire'];
    if ($r['closed']) {
        return '<span class="pe-status"><span class="pe-dot pe-dot--closed" aria-hidden="true"></span>Clos</span>'
            . ($r['close_reason'] !== null ? '<small>' . $h(W::closeReasonLabel($r['close_reason'])) . '</small>' : '');
    }

    return '<span class="pe-status" title="' . $h($r['status_label']) . '"><span class="pe-dot pe-dot--' . $h($r['status_tone']) . '" aria-hidden="true"></span>' . $h($short[$r['status']] ?? $r['status_label']) . '</span>';
};
$classBadge = static function (array $r) use ($h, $ico): string {
    $c = $r['classification'];
    $lock = $r['lock']['locked'] ?? false;
    if ($c === null && !$lock) {
        return '<span class="pe-none" aria-label="Non classifiée">—</span>';
    }
    $out = '';
    if ($c !== null) {
        $out .= '<span class="pe-class pe-class--' . $h(Ui::classTone($c)) . '" title="' . $h(W::classificationLabel($c)) . '">' . $h(W::classificationBadge($c)) . '</span>';
    }
    if ($lock) {
        $why = !empty($r['lock']['auto']) ? 'Verrouillée par la règle de la communauté' : 'Verrouillée';
        $out .= '<span class="pe-lockmark" title="' . $h($why) . '">' . $ico('lock') . '<span class="pe-sr">' . $h($why) . '</span></span>';
    }

    return $out;
};
$wantedBadge = static function (array $r) use ($h): string {
    $w = $r['wanted'];
    if ($w === null) {
        return '<span class="pe-none" aria-label="Aucun avis de recherche">—</span>';
    }

    return '<a class="pe-badge pe-badge--' . $h(Ui::threatTone($w['threat'])) . '" href="' . $h(url('atak/sse/papiers/identites/' . $w['identity_id'])) . '" title="Avis ' . $h($w['ref']) . ' — ' . 'ouvrir l’avis' . '">'
        . 'Recherché<small>' . $h(Ui::threat($w['threat'])) . '</small></a>';
};
$signBadge = static function (array $r) use ($h, $now): string {
    if (!$r['signed']) {
        return '<span class="pe-badge pe-badge--warn pe-badge--plain">Non signé</span>';
    }
    $s = $r['sig'];
    $bits = array_filter([$s['callsign'], $s['terminal'] !== '' ? 'terminal ' . $s['terminal'] : '']);
    $when = $s['at'] !== '' ? Ui::day($s['at'], $now) : '';

    return '<span class="pe-badge pe-badge--ok pe-badge--plain" title="' . $h(implode(' · ', $bits) . ($when !== '' ? ' · ' . Ui::full($s['at']) : '')) . '">' . 'Signé'
        . '<small>' . $h(trim(($s['callsign'] !== '' ? $s['callsign'] : '') . ($when !== '' ? ($s['callsign'] !== '' ? ' · ' : '') . $when : ''))) . '</small></span>';
};
$bioPills = static function (array $r) use ($h): string {
    $names = ['empreintes' => 'Empreintes', 'iris' => 'Iris', 'adn' => 'ADN'];
    $short = ['empreintes' => 'E', 'iris' => 'I', 'adn' => 'A'];
    $state = ['present' => 'présent', 'partial' => 'partiel', 'absent' => 'absent'];
    $out = '<span class="pe-bio">';
    foreach ($names as $k => $label) {
        $s = $r['bio'][$k] ?? 'absent';
        $out .= '<span class="pe-pill pe-pill--' . $h($s) . '" title="' . $h($label . ' : ' . $state[$s]) . '"><span aria-hidden="true">' . $short[$k] . '</span><span class="pe-sr">' . $h($label . ' ' . $state[$s]) . '</span></span>';
    }

    return $out . '</span>';
};
$avatar = static function (array $r) use ($h): string {
    return $r['photo'] !== null
        ? '<span class="pe-avatar"><img src="' . $h($r['photo']) . '" alt="" loading="lazy"></span>'
        : '<span class="pe-avatar pe-avatar--initials" aria-hidden="true">' . $h($r['initials']) . '</span>';
};
$seen = static function (array $r) use ($h, $now): string {
    $mine = $r['my_opened_at'] !== null ? '<span title="' . $h(Ui::full($r['my_opened_at'])) . '">' . 'Vous : ' . $h(Ui::day($r['my_opened_at'], $now)) . '</span>' : '<span class="pe-none">Jamais ouverte</span>';
    $lr = $r['last_read'];
    $other = $lr === null
        ? ''
        : '<small title="' . $h(trim(($lr['who'] ?? '') . ' ' . Ui::full($lr['at']))) . '">Lue : ' . $h(($lr['who'] !== null ? preg_replace('/ \(.*$/u', '', $lr['who']) . ' · ' : '') . Ui::day($lr['at'], $now)) . '</small>';

    return $mine . $other;
};
/** Boutons d'action d'une fiche (survol, focus, cartes). */
$actions = static function (array $r) use ($h, $ico, $viewer, $canAct, $denyReason, $ficheUrl): string {
    $id = (int) $r['id'];
    $out = '<a class="pe-act" href="' . $h($ficheUrl($id)) . '" title="' . 'Ouvrir la fiche (o)' . '">' . $ico('open') . '<span class="pe-sr">' . 'Ouvrir la fiche' . '</span></a>';
    $w = $r['wanted'];
    if ($w !== null) {
        $out .= '<a class="pe-act" data-pe-wanted href="' . $h(url('atak/sse/papiers/identites/' . $w['identity_id'])) . '" title="' . 'Voir l’avis de recherche' . '">' . $ico('alert') . '<span class="pe-sr">' . 'Voir l’avis de recherche' . '</span></a>';
    } elseif ($viewer->canWanted) {
        $href = $r['registry_id'] !== null ? url('atak/sse/papiers/identites/' . $r['registry_id'] . '/avis/nouveau') : $ficheUrl($id) . '?fiche=personne';
        $out .= '<a class="pe-act" data-pe-wanted href="' . $h($href) . '" title="' . 'Placer un avis de recherche (a)' . '">' . $ico('alert') . '<span class="pe-sr">' . 'Placer un avis de recherche' . '</span></a>';
    } else {
        $out .= '<button type="button" class="pe-act" disabled title="Droit manquant : placer un avis de recherche">' . $ico('alert') . '<span class="pe-sr">Placer un avis de recherche</span></button>';
    }
    foreach ([['classify', 'tag', 'Classifier (c)', 'Classifier'], ['lock', 'lock', 'Verrouiller (l)', 'Verrouiller'], ['close', 'check', 'Clore le dossier (e)', 'Clore le dossier']] as [$act, $icon, $title, $text]) {
        $can = $canAct && $r['can_modify'] && !($act === 'close' && $r['closed']);
        if ($act === 'lock' && $r['lock']['manual']) {
            $act = 'unlock';
            $title = 'Déverrouiller';
            $text = 'Déverrouiller';
        }
        $out .= $can
            ? '<button type="button" class="pe-act" data-pe-act="' . $act . '" data-ids="' . $id . '" title="' . $h($title) . '">' . $ico($icon) . '<span class="pe-sr">' . $h($text) . '</span></button>'
            : '<button type="button" class="pe-act" disabled title="' . $h($denyReason) . '">' . $ico($icon) . '<span class="pe-sr">' . $h($text) . '</span></button>';
    }

    return $out;
};
/** Menu « … » : actions rares. */
$more = static function (array $r) use ($h, $ico, $canAct, $ficheUrl): string {
    $id = (int) $r['id'];
    $items = '<a role="menuitem" href="' . $h($ficheUrl($id)) . '">' . 'Ouvrir la fiche' . '</a>'
        . '<button type="button" role="menuitem" data-pe-open="' . $id . '" data-pe-focus="sign">' . 'Voir la signature' . '</button>'
        . '<button type="button" role="menuitem" data-pe-copy="' . $h($ficheUrl($id)) . '">' . 'Copier le lien' . '</button>';
    if ($canAct && $r['can_modify']) {
        $items .= $r['closed']
            ? '<button type="button" role="menuitem" data-pe-act="reopen" data-ids="' . $id . '">' . 'Rouvrir le dossier' . '</button>'
            : '';
        $items .= $r['lock']['manual'] ? '<button type="button" role="menuitem" data-pe-act="unlock" data-ids="' . $id . '">' . 'Déverrouiller' . '</button>' : '';
    }

    return '<details class="pe-more"><summary class="pe-act" title="Plus d’actions" aria-label="Plus d’actions">' . $ico('more') . '</summary><div class="pe-more__menu" role="menu">' . $items . '</div></details>';
};

$activeChips = [];
$chip = static function (string $label, string $key) use (&$activeChips, $link): void {
    $activeChips[] = ['label' => $label, 'href' => $link([$key => ''])];
};
if ($q['q'] !== '') {
    $chip('Recherche : ' . $q['q'], 'q');
}
if ($q['status'] !== '') {
    $chip('Statut : ' . \App\Repositories\SsePersonRepository::STATUS_LABELS[$q['status']], 'status');
}
if ($q['cls'] !== '') {
    $chip('Classification : ' . ($q['cls'] === 'none' ? 'aucune' : W::classificationBadge($q['cls'])), 'cls');
}
if ($q['wanted'] !== '') {
    $chip($q['wanted'] === '1' ? 'Recherchées' : 'Sans avis de recherche', 'wanted');
}
if ($q['signed'] !== '') {
    $chip($q['signed'] === '1' ? 'Signées' : 'Non signées', 'signed');
}
if ($q['bio'] !== '') {
    $chip($q['bio'] === 'full' ? 'Biométrie complète' : 'Biométrie incomplète', 'bio');
}
if ($q['nat'] !== '') {
    $chip('Nationalité : ' . $q['nat'], 'nat');
}
if ($q['ctx'] > 0) {
    $chip('Carte ' . $q['ctx'], 'ctx');
}
if ($q['period'] !== '') {
    $chip(W::PERIOD_LABELS[$q['period']], 'period');
}
$filterCount = count(array_filter($activeChips, static fn (array $c): bool => !str_starts_with($c['label'], 'Recherche')));
$pageFrom = $listing['total'] === 0 ? 0 : ($listing['page'] - 1) * $listing['perPage'] + 1;
$pageTo = min($listing['total'], $listing['page'] * $listing['perPage']);
$selectable = array_values(array_filter($rows, static fn (array $r): bool => !$r['restricted']));
$levels = W::assignableLevels($viewer->level);
?>
<svg class="pe-sprite" width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute">
    <symbol id="pe-i-open" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="pe-i-alert" viewBox="0 0 24 24"><path d="M12 3 2.5 20h19L12 3zM12 10v4.5M12 17.5v.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="pe-i-tag" viewBox="0 0 24 24"><path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9zM8 8v.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="pe-i-lock" viewBox="0 0 24 24"><path d="M6 11h12v9H6zM8.5 11V8a3.5 3.5 0 0 1 7 0v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="pe-i-check" viewBox="0 0 24 24"><path d="M4 12.5 9.5 18 20 6.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="pe-i-more" viewBox="0 0 24 24"><path d="M5 12h.01M12 12h.01M19 12h.01" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/></symbol>
    <symbol id="pe-i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="pe-i-filter" viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></symbol>
    <symbol id="pe-i-table" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="pe-i-cards" viewBox="0 0 24 24"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></symbol>
    <symbol id="pe-i-keys" viewBox="0 0 24 24"><path d="M3 7h18v10H3zM7 11h.01M11 11h.01M15 11h.01M8 14h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="pe-i-x" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="pe-i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M18.7 5.3l-2.1 2.1M7.4 16.6l-2.1 2.1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
</svg>

<div class="pe" data-pe data-view="<?= $h($view) ?>" data-base="<?= $h($base) ?>" data-drawer-url="<?= $h(url('atak/sse/identites/{id}/apercu')) ?>" data-batch-url="<?= $h(url('atak/sse/identites/lot')) ?>" data-settings-url="<?= $h(url('atak/sse/identites/reglages')) ?>" data-fiche-url="<?= $h(url('atak/sse/identites/{id}')) ?>" data-csrf="<?= $h($csrf) ?>" data-open="<?= (int) $q['open'] ?>" data-can-act="<?= $canAct ? '1' : '0' ?>" data-can-wanted="<?= $viewer->canWanted ? '1' : '0' ?>">
    <header class="pe-head">
        <div class="pe-head__text">
            <h1>Personnes</h1>
            <p>Les personnes contrôlées sur le terrain : repérez celles qui sont recherchées ou non signées, ouvrez un aperçu, classez, verrouillez ou clôturez.</p>
        </div>
        <div class="pe-head__tools">
            <div class="pe-seg" role="group" aria-label="Affichage">
                <a class="pe-seg__btn" href="<?= $h($link(['vue' => 'tableau'])) ?>" data-pe-view="tableau"<?= $view === 'tableau' ? ' aria-current="true"' : '' ?>><?= $ico('table') ?><span>Tableau</span></a>
                <a class="pe-seg__btn" href="<?= $h($link(['vue' => 'cartes'])) ?>" data-pe-view="cartes"<?= $view === 'cartes' ? ' aria-current="true"' : '' ?>><?= $ico('cards') ?><span class="pe-hide-sm">Cartes compactes</span><span class="pe-show-sm">Cartes</span></a>
            </div>
            <button type="button" class="pe-btn pe-btn--icon" data-pe-dialog="help" title="Raccourcis clavier (?)" aria-label="Raccourcis clavier"><?= $ico('keys') ?></button>
            <?php if ($viewer->canManage): ?>
                <button type="button" class="pe-btn pe-btn--icon" data-pe-dialog="settings" title="Verrouillage automatique" aria-label="Verrouillage automatique"><?= $ico('gear') ?></button>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!$ready): ?>
        <p class="pe-notice" role="note">Classer, verrouiller et clore seront disponibles après la mise à jour de la base de données (étape « SSE Personnes »). En attendant, la liste, les filtres et les aperçus fonctionnent.</p>
    <?php endif; ?>
    <?php if ($listing['truncated']): ?>
        <p class="pe-notice" role="note">Seules les <?= (int) W::LOAD_LIMIT ?> fiches les plus récentes sont chargées : affinez la recherche pour retrouver les plus anciennes.</p>
    <?php endif; ?>

    <nav class="pe-tabs" aria-label="Sélections de personnes">
        <?php foreach (W::TABS as $tab): ?>
            <a class="pe-tab<?= $q['tab'] === $tab ? ' is-active' : '' ?>" href="<?= $h($link(['tab' => $tab])) ?>"<?= $q['tab'] === $tab ? ' aria-current="page"' : '' ?>>
                <span><?= $h(W::TAB_LABELS[$tab]) ?></span><span class="pe-count"><?= (int) $counts[$tab] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="pe-bar" method="get" action="<?= $h($base) ?>" role="search" data-pe-form>
        <input type="hidden" name="tab" value="<?= $h($q['tab']) ?>">
        <?php if ($q['vue'] !== ''): ?><input type="hidden" name="vue" value="<?= $h($q['vue']) ?>"><?php endif; ?>
        <div class="pe-search">
            <label class="pe-sr" for="pe-q">Rechercher une personne</label>
            <?= $ico('search') ?>
            <input id="pe-q" name="q" type="search" value="<?= $h($q['q']) ?>" placeholder="Nom, alias, IDN, nationalité, grille…" autocomplete="off" enterkeyhint="search" data-pe-search>
            <kbd class="pe-kbd" aria-hidden="true">/</kbd>
        </div>
        <details class="pe-filters">
            <summary class="pe-btn"><?= $ico('filter') ?><span>Filtres</span><?php if ($filterCount > 0): ?><span class="pe-count"><?= $filterCount ?></span><?php endif; ?></summary>
            <div class="pe-filters__panel">
                <label>Statut
                    <select name="status" data-pe-auto>
                        <option value="">Tous</option>
                        <?php foreach (\App\Repositories\SsePersonRepository::STATUS_LABELS as $code => $label): ?>
                            <option value="<?= $h($code) ?>"<?= $q['status'] === $code ? ' selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Classification
                    <select name="cls" data-pe-auto>
                        <option value="">Toutes</option>
                        <option value="none"<?= $q['cls'] === 'none' ? ' selected' : '' ?>>Non classifiée</option>
                        <?php foreach (\App\Support\SseUsClassification::codes() as $code): ?>
                            <option value="<?= $h($code) ?>"<?= $q['cls'] === $code ? ' selected' : '' ?>><?= $h(W::classificationLabel($code)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Avis de recherche
                    <select name="wanted" data-pe-auto>
                        <option value="">Indifférent</option>
                        <option value="1"<?= $q['wanted'] === '1' ? ' selected' : '' ?>>Recherchée</option>
                        <option value="0"<?= $q['wanted'] === '0' ? ' selected' : '' ?>>Sans avis</option>
                    </select>
                </label>
                <label>Signature
                    <select name="signed" data-pe-auto>
                        <option value="">Indifférent</option>
                        <option value="1"<?= $q['signed'] === '1' ? ' selected' : '' ?>>Signée</option>
                        <option value="0"<?= $q['signed'] === '0' ? ' selected' : '' ?>>Non signée</option>
                    </select>
                </label>
                <label>Biométrie
                    <select name="bio" data-pe-auto>
                        <option value="">Indifférent</option>
                        <option value="full"<?= $q['bio'] === 'full' ? ' selected' : '' ?>>Complète</option>
                        <option value="partial"<?= $q['bio'] === 'partial' ? ' selected' : '' ?>>Incomplète</option>
                    </select>
                </label>
                <label>Nationalité
                    <select name="nat" data-pe-auto>
                        <option value="">Toutes</option>
                        <?php foreach ($listing['facets']['nationalities'] as $nat => $n): ?>
                            <option value="<?= $h($nat) ?>"<?= mb_strtolower($q['nat']) === mb_strtolower((string) $nat) ? ' selected' : '' ?>><?= $h($nat) ?> (<?= (int) $n ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Opération / carte
                    <select name="ctx" data-pe-auto>
                        <option value="0">Toutes</option>
                        <?php foreach ($listing['facets']['contexts'] as $ctx => $n): ?>
                            <option value="<?= (int) $ctx ?>"<?= $q['ctx'] === (int) $ctx ? ' selected' : '' ?>>Carte <?= (int) $ctx ?> (<?= (int) $n ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Période (dernière modification)
                    <select name="period" data-pe-auto>
                        <option value="">Toute la période</option>
                        <?php foreach (W::PERIOD_LABELS as $code => $label): ?>
                            <option value="<?= $h($code) ?>"<?= $q['period'] === $code ? ' selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </details>
        <label class="pe-sort">
            <span>Trier par</span>
            <select name="sort" data-pe-auto>
                <?php foreach (W::SORT_LABELS as $code => $label): ?>
                    <option value="<?= $h($code) ?>"<?= $q['sort'] === $code ? ' selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="pe-btn pe-btn--solid">Appliquer</button>
    </form>

    <?php if ($activeChips !== []): ?>
        <div class="pe-chips" aria-label="Filtres actifs">
            <?php foreach ($activeChips as $c): ?>
                <a class="pe-chip" href="<?= $h($c['href']) ?>" title="Retirer ce filtre"><span><?= $h($c['label']) ?></span><?= $ico('x') ?></a>
            <?php endforeach; ?>
            <a class="pe-chip pe-chip--reset" href="<?= $h($base . '?tab=' . $q['tab']) ?>">Tout effacer</a>
        </div>
    <?php endif; ?>

    <div class="pe-selbar" data-pe-selbar hidden role="region" aria-label="Actions sur la sélection">
        <strong data-pe-selcount></strong>
        <div class="pe-selbar__acts">
            <button type="button" class="pe-btn" data-pe-act="classify"<?= $canAct ? '' : ' disabled title="' . $h($denyReason) . '"' ?>><?= $ico('tag') ?><span>Classifier</span></button>
            <button type="button" class="pe-btn" data-pe-act="lock"<?= $canAct ? '' : ' disabled title="' . $h($denyReason) . '"' ?>><?= $ico('lock') ?><span>Verrouiller</span></button>
            <button type="button" class="pe-btn" data-pe-act="close"<?= $canAct ? '' : ' disabled title="' . $h($denyReason) . '"' ?>><?= $ico('check') ?><span>Clore</span></button>
            <button type="button" class="pe-btn" data-pe-act="export"<?= $viewer->canExport && !$viewer->guest ? '' : ' disabled title="Droit manquant : exporter"' ?>><span>Exporter CSV</span></button>
            <button type="button" class="pe-btn" data-pe-act="link"<?= $viewer->canCases ? '' : ' disabled title="' . $h('Droit manquant : écrire dans « Dossiers d’enquête »') . '"' ?>><span>Ajouter à un dossier</span></button>
            <button type="button" class="pe-btn pe-btn--ghost" data-pe-selclear><span>Désélectionner</span></button>
        </div>
    </div>

    <?php if ($rows === []): ?>
        <?php if (!$listing['anyPerson']): ?>
            <div class="pe-empty">
                <h2>Aucune personne enregistrée</h2>
                <p>Les personnes contrôlées avec le terminal SEEK II arrivent ici dès leur transmission, avec leur photo, leurs relevés biométriques et leur signature.</p>
            </div>
        <?php elseif ($listing['filtering']): ?>
            <div class="pe-empty">
                <h2><?= match ($q['tab']) {
                    'closes' => 'Aucun dossier clos',
                    'verrouillees' => 'Aucune fiche verrouillée',
                    'recherchees' => 'Aucune personne recherchée',
                    'non_signees' => 'Toutes les fiches sont signées',
                    'classifiees' => 'Aucune fiche classifiée',
                    default => 'Aucune personne ne correspond',
                } ?></h2>
                <p><?= match ($q['tab']) {
                    'closes' => 'Un dossier clos reste consultable ici et peut être rouvert à tout moment.',
                    'verrouillees' => 'Une fiche verrouillée n’est lisible que par les personnes dont l’habilitation atteint sa classification.',
                    default => 'Aucune fiche ne répond à ces critères : retirez un filtre ou élargissez la recherche.',
                } ?></p>
                <a class="pe-btn" href="<?= $h($base) ?>">Voir toutes les personnes</a>
            </div>
        <?php else: ?>
            <div class="pe-empty">
                <h2>Toutes les fiches sont closes</h2>
                <p>Les dossiers clos sont masqués de la vue principale. Ouvrez l’onglet « Closes » pour les consulter ou les rouvrir.</p>
                <a class="pe-btn" href="<?= $h($link(['tab' => 'closes'])) ?>">Voir les dossiers clos</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="pe-list" data-pe-list>
            <div class="pe-tablewrap">
            <table class="pe-table" data-pe-table>
                <caption class="pe-sr">Personnes : <?= (int) $listing['total'] ?> résultat(s)</caption>
                <thead>
                <tr>
                    <th scope="col" class="pe-c-check"><label class="pe-check" title="Tout sélectionner"><input type="checkbox" data-pe-selall aria-label="Tout sélectionner"><span></span></label></th>
                    <th scope="col" class="pe-c-person"><a href="<?= $h($link(['sort' => 'nom'])) ?>">Nom</a></th>
                    <th scope="col" class="pe-c-status">Statut</th>
                    <th scope="col" class="pe-c-class"><abbr title="Classification">Classif.</abbr></th>
                    <th scope="col" class="pe-c-wanted"><a href="<?= $h($link(['sort' => 'priorite'])) ?>" >Avis de recherche</a></th>
                    <th scope="col" class="pe-c-sign">Signature</th>
                    <th scope="col" class="pe-c-bio">Biométrie</th>
                    <th scope="col" class="pe-c-origin">Origine</th>
                    <th scope="col" class="pe-c-mod"><a href="<?= $h($link(['sort' => 'modification'])) ?>">Modifiée</a></th>
                    <th scope="col" class="pe-c-seen"><a href="<?= $h($link(['sort' => 'ouverture'])) ?>">Ouverte · lue</a></th>
                    <th scope="col" class="pe-c-links">Liens</th>
                    <th scope="col" class="pe-c-act"><span class="pe-sr">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php if ($r['restricted']): ?>
                        <tr class="pe-row pe-row--restricted">
                            <td class="pe-c-check"></td>
                            <th scope="row" class="pe-c-person"><span class="pe-avatar pe-avatar--lock"><?= $ico('lock') ?></span><span class="pe-restricted"><strong>Élément restreint</strong><small>Verrouillé : habilitation supérieure requise</small></span></th>
                            <?php foreach (['status', 'class', 'wanted', 'sign', 'bio', 'origin', 'mod', 'seen', 'links', 'act'] as $col): ?><td class="pe-c-<?= $col ?>"></td><?php endforeach; ?>
                        </tr>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php $id = (int) $r['id']; ?>
                    <tr class="pe-row<?= $r['closed'] ? ' is-closed' : '' ?>" data-pe-item data-id="<?= $id ?>" data-name="<?= $h($r['name']) ?>" data-wanted="<?= $r['wanted'] !== null ? '1' : '0' ?>" data-class="<?= $h((string) $r['classification']) ?>" data-fiche="<?= $h($ficheUrl($id)) ?>" data-locked="<?= !empty($r['lock']['manual']) ? '1' : '0' ?>" data-closed="<?= $r['closed'] ? '1' : '0' ?>">
                        <td class="pe-c-check"><label class="pe-check"><input type="checkbox" data-pe-select value="<?= $id ?>" aria-label="Sélectionner <?= $h($r['name']) ?>"><span></span></label></td>
                        <th scope="row" class="pe-c-person">
                            <?= $avatar($r) ?>
                            <span class="pe-who">
                                <a class="pe-name" href="<?= $h($ficheUrl($id)) ?>" data-pe-open="<?= $id ?>"><?= $h($r['name']) ?></a>
                                <small><?= $h(trim(($r['alias'] !== '' ? '« ' . $r['alias'] . ' » · ' : '') . $r['idn'])) ?></small>
                            </span>
                        </th>
                        <td class="pe-c-status"><?= $status($r) ?></td>
                        <td class="pe-c-class"><?= $classBadge($r) ?></td>
                        <td class="pe-c-wanted"><?= $wantedBadge($r) ?></td>
                        <td class="pe-c-sign"><?= $signBadge($r) ?></td>
                        <td class="pe-c-bio"><?= $bioPills($r) ?></td>
                        <td class="pe-c-origin"><span><?= $h($r['nationality'] !== '' ? $r['nationality'] : '—') ?></span><small><?= $h(trim($r['circumstances'] . ($r['quality'] !== null ? ' · qualité ' . (int) $r['quality'] . ' %' : ''), ' ·')) ?></small></td>
                        <td class="pe-c-mod" title="<?= $h(Ui::full($r['updated_at'])) ?>"><?= $h(Ui::day($r['updated_at'], $now)) ?></td>
                        <td class="pe-c-seen"><?= $seen($r) ?></td>
                        <td class="pe-c-links"><?= (int) $r['links'] ?></td>
                        <td class="pe-c-act"><div class="pe-acts"><?= $actions($r) ?></div><?= $more($r) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <ul class="pe-cards" data-pe-cards>
                <?php foreach ($rows as $r): ?>
                    <?php if ($r['restricted']): ?>
                        <li class="pe-card pe-card--restricted"><span class="pe-avatar pe-avatar--lock"><?= $ico('lock') ?></span><span class="pe-restricted"><strong>Élément restreint</strong><small>Verrouillé : habilitation supérieure requise</small></span></li>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php $id = (int) $r['id']; ?>
                    <li class="pe-card<?= $r['closed'] ? ' is-closed' : '' ?>" data-pe-item data-id="<?= $id ?>" data-name="<?= $h($r['name']) ?>" data-wanted="<?= $r['wanted'] !== null ? '1' : '0' ?>" data-class="<?= $h((string) $r['classification']) ?>" data-fiche="<?= $h($ficheUrl($id)) ?>" data-locked="<?= !empty($r['lock']['manual']) ? '1' : '0' ?>" data-closed="<?= $r['closed'] ? '1' : '0' ?>">
                        <div class="pe-card__top">
                            <label class="pe-check"><input type="checkbox" data-pe-select value="<?= $id ?>" aria-label="Sélectionner <?= $h($r['name']) ?>"><span></span></label>
                            <?= $avatar($r) ?>
                            <span class="pe-who">
                                <a class="pe-name" href="<?= $h($ficheUrl($id)) ?>" data-pe-open="<?= $id ?>"><?= $h($r['name']) ?></a>
                                <small><?= $h(trim(($r['alias'] !== '' ? '« ' . $r['alias'] . ' » · ' : '') . $r['idn'])) ?></small>
                            </span>
                            <?= $more($r) ?>
                        </div>
                        <div class="pe-card__flags">
                            <span class="pe-status"><?= $status($r) ?></span>
                            <?= $classBadge($r) ?>
                            <?= $wantedBadge($r) ?>
                            <?= $signBadge($r) ?>
                        </div>
                        <div class="pe-card__meta">
                            <?= $bioPills($r) ?>
                            <span><?= $h($r['nationality'] !== '' ? $r['nationality'] : '—') ?></span>
                            <span title="<?= $h(Ui::full($r['updated_at'])) ?>">Modifiée <?= $h(Ui::when($r['updated_at'], $now)) ?></span>
                        </div>
                        <div class="pe-card__seen"><?= $seen($r) ?></div>
                        <div class="pe-card__acts"><?= $actions($r) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <nav class="pe-pager" aria-label="Pagination">
            <span><?= (int) $pageFrom ?>–<?= (int) $pageTo ?> sur <?= (int) $listing['total'] ?></span>
            <?php if ($listing['pages'] > 1): ?>
                <span class="pe-pager__nav">
                    <?php if ($listing['page'] > 1): ?><a class="pe-btn" href="<?= $h($link(['p' => $listing['page'] - 1])) ?>" rel="prev">Précédent</a><?php endif; ?>
                    <span>Page <?= (int) $listing['page'] ?> sur <?= (int) $listing['pages'] ?></span>
                    <?php if ($listing['page'] < $listing['pages']): ?><a class="pe-btn" href="<?= $h($link(['p' => $listing['page'] + 1])) ?>" rel="next">Afficher la suite</a><?php endif; ?>
                </span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <aside class="pe-drawer" id="pe-drawer" role="dialog" aria-modal="false" aria-label="Aperçu de la personne" tabindex="-1" hidden>
        <div class="pe-drawer__bar">
            <button type="button" class="pe-act" data-pe-drawer-close title="Fermer (Échap)"><?= $ico('x') ?><span class="pe-sr">Fermer l’aperçu</span></button>
        </div>
        <div class="pe-drawer__body" data-pe-drawer-body></div>
    </aside>
    <div class="pe-scrim" data-pe-drawer-close hidden></div>

    <div class="pe-undo" data-pe-undo role="status" aria-live="polite" hidden>
        <span data-pe-undo-text></span>
        <button type="button" class="pe-btn" data-pe-undo-btn></button>
    </div>

    <div hidden data-pe-msgs>
        <span data-k="selected">Sélection : %1</span>
        <span data-k="closed">Dossiers clos : %1</span>
        <span data-k="undo">Annuler (%1 s)</span>
        <span data-k="undone">Clôture annulée.</span>
        <span data-k="targets">Personnes concernées (%1)</span>
        <span data-k="error">L’action a échoué : réessayez.</span>
        <span data-k="copied">Lien copié.</span>
        <span data-k="lift">Lever l’avis et clore</span>
        <span data-k="close">Clore le dossier</span>
        <span data-k="wantedblock">Cette personne est sous avis de recherche actif : l’avis doit être levé avant la clôture.</span>
        <span data-k="wantednoright">Vous n’avez pas le droit de lever un avis de recherche : cette clôture est impossible.</span>
    </div>
</div>

<?php require __DIR__ . '/_dialogs.php'; ?>
