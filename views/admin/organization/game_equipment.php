<?php
declare(strict_types=1);

use App\Services\GameEquipment\GameItemKinds;
use App\Support\ArsenalLoadoutItems;

/**
 * Équipement des joueurs — ce que le mod remonte à chaque synchronisation du profil opérateur.
 *
 * L’en-tête de page est rendu par la coque back-office (config/back_office_pages.php).
 * Styles : public/assets/css/back-office-game-equipment.css ; comportements : back-office-game-equipment.js.
 *
 * @var bool $geReady
 * @var int $geDays
 * @var list<int> $gePeriods
 * @var array{players: int, items: int, observations: int} $geSummary
 * @var array<string, list<array{class: string, name: string, kind: string, mod: string, players: int, player_days: int, last_seen: string}>> $geTops
 * @var list<string> $geKinds
 * @var list<array{dir: string, name: string, workshop_id: string, is_dlc: bool, players: int, last_seen: string}> $geMods
 * @var array<string, array{items: int, players: int}> $geItemMods
 * @var int $geModPlayers
 * @var int $geKitCount
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$int = static fn (int $n): string => number_format($n, 0, ',', ' ');
$geReady = !empty($geReady);
$geDays = (int) ($geDays ?? 30);
$gePeriods = is_array($gePeriods ?? null) ? $gePeriods : [7, 30, 90];
$geSummary = is_array($geSummary ?? null) ? $geSummary + ['players' => 0, 'items' => 0, 'observations' => 0] : ['players' => 0, 'items' => 0, 'observations' => 0];
$geTops = is_array($geTops ?? null) ? $geTops : [];
$geKinds = is_array($geKinds ?? null) ? $geKinds : [];
$geMods = is_array($geMods ?? null) ? $geMods : [];
$geItemMods = is_array($geItemMods ?? null) ? $geItemMods : [];
$geModPlayers = (int) ($geModPlayers ?? 0);
$geKitCount = (int) ($geKitCount ?? 0);
$hasData = $geSummary['observations'] > 0;
$activeMods = array_values(array_filter($geMods, static fn (array $m): bool => $m['players'] > 0));

$mainKinds = ['primary', 'vest', 'headgear', 'uniform', 'backpack', 'handgun'];
$otherKinds = array_values(array_diff($geKinds, $mainKinds));

$label = static function (array $row): string {
    $name = trim((string) ($row['name'] ?? ''));

    return $name !== '' ? $name : ArsenalLoadoutItems::displayName((string) ($row['class'] ?? ''));
};
$modLabel = static function (string $mod): string {
    $mod = trim($mod);
    if ($mod === '') {
        return '';
    }

    return strcasecmp($mod, 'Arma 3') === 0 ? 'Arma 3 (base)' : $mod;
};
$fmtDate = static function (string $raw): string {
    $ts = $raw !== '' ? strtotime($raw) : false;

    return $ts ? date('d/m/Y', $ts) : '—';
};

$icon = static function (string $name): string {
    $paths = match ($name) {
        'rifle' => '<path d="M2 13h13l2-2h5v3h-4l-1 2h-3l-1 3H9l1-3H2z"/><path d="M6 13v-2h4"/>',
        'vest' => '<path d="M8 3l-4 3v14h6v-6h4v6h6V6l-4-3-2 3h-4z"/>',
        'helmet' => '<path d="M3 15a9 9 0 0118 0v2H3z"/><path d="M12 6v5M3 17h18"/>',
        'shirt' => '<path d="M8 3l-5 3 2 4 3-1v12h8V9l3 1 2-4-5-3a4 4 0 01-8 0z"/>',
        'bag' => '<rect x="5" y="7" width="14" height="14" rx="3"/><path d="M9 7V5a3 3 0 016 0v2M9 13h6"/>',
        'pistol' => '<path d="M3 8h16v4h-8l-1 2H8l-1 5H4l1-7H3z"/>',
        'scope' => '<circle cx="12" cy="12" r="8"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
        'radio' => '<rect x="6" y="7" width="12" height="14" rx="2"/><path d="M9 7l6-5M9 12h6M9 16h2"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'puzzle' => '<path d="M10 3h4v3a2 2 0 104 0V3h3v7h-3a2 2 0 100 4h3v7h-7v-3a2 2 0 10-4 0v3H3v-7h3a2 2 0 100-4H3V3z"/>',
        'kit' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M3 12h18"/>',
        'users' => '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8"/>',
        'box' => '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.01"/>',
        default => '<circle cx="12" cy="12" r="9"/>',
    };

    return '<svg class="bo-ge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};
$kindIcon = static fn (string $kind): string => match ($kind) {
    'primary', 'secondary' => 'rifle',
    'handgun' => 'pistol',
    'vest' => 'vest',
    'headgear' => 'helmet',
    'uniform' => 'shirt',
    'backpack' => 'bag',
    'optic', 'muzzle', 'pointer', 'bipod', 'binocular' => 'scope',
    'radio' => 'radio',
    'nvg', 'goggles' => 'eye',
    default => 'box',
};

$renderRank = static function (string $kind, array $rows, bool $big) use ($h, $int, $label, $modLabel, $icon, $kindIcon): void {
    $max = 1;
    foreach ($rows as $r) {
        $max = max($max, (int) $r['players']);
    }
    echo '<article class="bo-ge-rank' . ($big ? ' bo-ge-rank--big' : '') . '" data-ge-rank>';
    echo '<header class="bo-ge-rank__head"><span class="bo-ge-rank__icon">' . $icon($kindIcon($kind)) . '</span>'
        . '<h3 class="bo-ge-rank__title">' . $h(GameItemKinds::plural($kind)) . '</h3>'
        . '<span class="bo-ge-count">' . count($rows) . '</span></header>';
    if ($rows === []) {
        echo '<p class="bo-ge-empty bo-ge-empty--sm">Rien d’observé sur la période.</p></article>';

        return;
    }
    echo '<ol class="bo-ge-rank__list">';
    foreach ($rows as $i => $r) {
        $pct = (int) round(((int) $r['players']) / $max * 100);
        $mod = $modLabel((string) $r['mod']);
        echo '<li class="bo-ge-rank__row' . ($i >= 5 ? ' is-extra' : '') . '">'
            . '<span class="bo-ge-rank__pos">' . ($i + 1) . '</span>'
            . (($ico = \App\Repositories\GameItemIconRepository::urlFor((string) $r['class'])) !== null
                ? '<img class="bo-ge-itemico" src="' . $h($ico) . '" alt="" loading="lazy" decoding="async" width="48" height="48">' : '')
            . '<span class="bo-ge-rank__body">'
            . '<span class="bo-ge-rank__name" translate="no">' . $h($label($r)) . '</span>'
            . '<span class="bo-ge-rank__meta"><code class="bo-ge-mono" translate="no">' . $h((string) $r['class']) . '</code>'
            . ($mod !== '' ? '<span class="bo-ge-mod" translate="no">' . $h($mod) . '</span>' : '')
            . '</span>'
            . '<span class="bo-ge-rank__bar" aria-hidden="true"><span style="width:' . max(4, $pct) . '%"></span></span>'
            . '</span>'
            . '<span class="bo-ge-rank__value" title="' . $h($int((int) $r['player_days'])) . ' jour(s)-joueur">'
            . '<strong>' . $h($int((int) $r['players'])) . '</strong><small>' . ((int) $r['players'] > 1 ? 'joueurs' : 'joueur') . '</small></span>'
            . '</li>';
    }
    echo '</ol>';
    if (count($rows) > 5) {
        echo '<button type="button" class="bo-ge-linkbtn" data-ge-more aria-expanded="false">Voir le top ' . count($rows) . '</button>';
    }
    echo '</article>';
};
?>
<div class="bo-ge" data-ge>
    <div class="bo-ge__toolbar">
        <nav class="bo-ge-seg" aria-label="Période">
            <?php foreach ($gePeriods as $p): ?>
            <a class="bo-ge-seg__opt" href="<?= $h(url('back-office/equipement-joueurs') . '?jours=' . (int) $p) ?>"<?= (int) $p === $geDays ? ' aria-current="true"' : '' ?>><?= (int) $p ?> jours</a>
            <?php endforeach; ?>
        </nav>
        <span class="bo-ge__window">Depuis le <?= $h(date('d/m/Y', strtotime(\App\Repositories\GameEquipmentRepository::sinceDay($geDays)) ?: time())) ?></span>
        <span class="bo-ge__spacer"></span>
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/kits-roles/nouveau')) ?>"><?= $icon('kit') ?> Composer un kit</a>
    </div>

    <?php if (!$geReady): ?>
    <section class="bo-ge-note bo-ge-note--warn" role="status">
        <?= $icon('info') ?>
        <div>
            <p class="bo-ge-note__title">Mise à jour à appliquer</p>
            <p class="bo-ge-note__text">Les tables de l’équipement observé n’existent pas encore sur ce serveur. Lancez <code>php run-migrations.php</code>, puis rechargez la page.</p>
        </div>
    </section>
    <?php endif; ?>

    <section class="bo-ge-brief" aria-label="En bref">
        <div class="bo-ge-brief__card">
            <span class="bo-ge-brief__label"><?= $icon('users') ?> Joueurs observés</span>
            <span class="bo-ge-brief__value"><?= $h($int($geSummary['players'])) ?></span>
            <span class="bo-ge-brief__hint">ayant synchronisé leur équipement sur la période</span>
        </div>
        <div class="bo-ge-brief__card">
            <span class="bo-ge-brief__label"><?= $icon('box') ?> Objets différents</span>
            <span class="bo-ge-brief__value"><?= $h($int($geSummary['items'])) ?></span>
            <span class="bo-ge-brief__hint">armes, tenues, accessoires et matériel</span>
        </div>
        <div class="bo-ge-brief__card">
            <span class="bo-ge-brief__label"><?= $icon('puzzle') ?> Mods détectés</span>
            <span class="bo-ge-brief__value"><?= $h($int(count($activeMods))) ?></span>
            <span class="bo-ge-brief__hint"><?= $geModPlayers > 0 ? $h($int($geModPlayers)) . ' joueur(s) ont envoyé leur liste' : 'liste envoyée par les versions récentes du mod' ?></span>
        </div>
        <a class="bo-ge-brief__card bo-ge-brief__card--link" href="<?= $h(url('back-office/kits-roles')) ?>">
            <span class="bo-ge-brief__label"><?= $icon('kit') ?> Kits par rôle</span>
            <span class="bo-ge-brief__value"><?= $h($int($geKitCount)) ?></span>
            <span class="bo-ge-brief__hint">proposés dans l’arsenal en jeu</span>
        </a>
    </section>

    <?php if ($geReady && !$hasData): ?>
    <section class="bo-ge-note" role="status">
        <?= $icon('info') ?>
        <div>
            <p class="bo-ge-note__title">Aucun équipement remonté pour l’instant</p>
            <p class="bo-ge-note__text">Le mod envoie l’équipement de chaque joueur à sa connexion, puis à chaque changement de tenue. Les classements se remplissent dès la prochaine session de jeu.</p>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($hasData): ?>
    <section class="bo-ge-section" id="ge-top" aria-labelledby="ge-top-t">
        <header class="bo-ge-section__head">
            <span class="bo-ge-section__icon"><?= $icon('rifle') ?></span>
            <div>
                <h2 class="bo-ge-section__title" id="ge-top-t">Le plus porté</h2>
                <p class="bo-ge-section__lead">Classement par nombre de joueurs différents ; le nombre de jours-joueurs départage les égalités.</p>
            </div>
        </header>
        <div class="bo-ge-grid bo-ge-grid--main">
            <?php foreach ($mainKinds as $kind): ?>
                <?php $renderRank($kind, $geTops[$kind] ?? [], true); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bo-ge-section" id="ge-accessoires" aria-labelledby="ge-acc-t">
        <header class="bo-ge-section__head">
            <span class="bo-ge-section__icon"><?= $icon('scope') ?></span>
            <div>
                <h2 class="bo-ge-section__title" id="ge-acc-t">Accessoires et matériel</h2>
                <p class="bo-ge-section__lead">Optiques, silencieux, vision nocturne, radios, médical, munitions.</p>
            </div>
        </header>
        <div class="bo-ge-grid">
            <?php foreach ($otherKinds as $kind): ?>
                <?php if (($geTops[$kind] ?? []) === []) { continue; } ?>
                <?php $renderRank($kind, $geTops[$kind], false); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php include __DIR__ . '/partials/game_player_kits.php'; ?>

    <section class="bo-ge-section" id="ge-mods" aria-labelledby="ge-mods-t">
        <header class="bo-ge-section__head">
            <span class="bo-ge-section__icon"><?= $icon('puzzle') ?></span>
            <div>
                <h2 class="bo-ge-section__title" id="ge-mods-t">Mods détectés</h2>
                <p class="bo-ge-section__lead">Mods chargés par les joueurs, et mods d’origine des objets qu’ils portent.</p>
            </div>
        </header>

        <?php if ($geItemMods !== []): ?>
        <div class="bo-ge-chips" aria-label="Mods d’origine des objets portés">
            <?php foreach ($geItemMods as $modName => $u): ?>
            <span class="bo-ge-chip" translate="no"><?= $h($modLabel((string) $modName)) ?> <small><?= $h($int($u['items'])) ?> objet(s) · <?= $h($int($u['players'])) ?> joueur(s)</small></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($geMods === []): ?>
        <p class="bo-ge-empty">Aucune liste de mods reçue. Elle est envoyée par les versions récentes du mod, à la connexion du joueur et quand ses mods changent.</p>
        <?php else: ?>
        <div class="bo-ge-tablebar">
            <label class="bo-ge-search">
                <?= $icon('search') ?>
                <input type="search" placeholder="Filtrer les mods" aria-label="Filtrer les mods" data-ge-filter="#ge-mods-table">
            </label>
            <label class="bo-ge-check"><input type="checkbox" data-ge-hide-dlc checked> Masquer les DLC</label>
        </div>
        <div class="bo-ge-table-wrap">
            <table class="bo-ge-table" id="ge-mods-table">
                <thead>
                    <tr>
                        <th scope="col">Mod</th>
                        <th scope="col" class="bo-ge-hide-sm">Dossier</th>
                        <th scope="col">Joueurs (<?= (int) $geDays ?> j)</th>
                        <th scope="col" class="bo-ge-hide-sm">Vu le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($geMods as $m): ?>
                    <?php $pct = $geModPlayers > 0 ? (int) round($m['players'] / $geModPlayers * 100) : 0; ?>
                    <tr data-ge-row data-ge-dlc="<?= $m['is_dlc'] ? '1' : '0' ?>"<?= $m['is_dlc'] ? ' hidden' : '' ?>>
                        <td>
                            <span class="bo-ge-modname" translate="no"><?= $h($m['name']) ?></span>
                            <?php if ($m['is_dlc']): ?><span class="bo-ge-tag">DLC</span><?php endif; ?>
                            <?php if ($m['workshop_id'] !== ''): ?>
                            <a class="bo-ge-ext" href="<?= $h('https://steamcommunity.com/sharedfiles/filedetails/?id=' . rawurlencode($m['workshop_id'])) ?>" target="_blank" rel="noopener noreferrer">Workshop <?= $icon('external') ?></a>
                            <?php endif; ?>
                        </td>
                        <td class="bo-ge-hide-sm"><code class="bo-ge-mono" translate="no"><?= $h($m['dir']) ?></code></td>
                        <td>
                            <span class="bo-ge-meter"><span class="bo-ge-meter__bar" aria-hidden="true"><span style="width:<?= max(0, min(100, $pct)) ?>%"></span></span><span class="bo-ge-meter__val"><?= $h($int($m['players'])) ?><?= $geModPlayers > 0 ? ' <small>' . $pct . ' %</small>' : '' ?></span></span>
                        </td>
                        <td class="bo-ge-hide-sm"><?= $h($fmtDate($m['last_seen'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-game-equipment.js')) ?>" defer></script>
