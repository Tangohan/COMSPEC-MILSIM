<?php

declare(strict_types=1);

/**
 * Photothèque de l'extranet JNET (PhotoLibraryController) : grille des photos terrain, filtres, recherche, tri, et le
 * lecteur plein écran (visages étiquetés à la main, masques, commentaires, étiquettes, versement SSE), construit par
 * public/assets/js/jnet_photos.js à partir du JSON de la photo ouverte. Repris par views/jnet/_bo_content.php dans la
 * coque views/jnet/_page.php.
 *
 * @var array{ready: bool, rows: list<array<string, mixed>>, total: int, page: int, pages: int, facets: array<string, list<string>>, canMaskHere: bool} $photos
 * @var array<string, string> $photoFilters
 * @var string $photoSort
 * @var array<string, mixed>|null $photoOpen
 * @var array{sse: bool, manager: bool} $photoViewer
 * @var string $photoCsrf
 * @var string $photoBase
 */

use App\Core\Csrf;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$photos = is_array($photos ?? null) ? $photos : ['ready' => false, 'rows' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'facets' => ['authors' => [], 'units' => [], 'albums' => [], 'labels' => []], 'canMaskHere' => false];
$f = is_array($photoFilters ?? null) ? $photoFilters : [];
$fv = static fn (string $k): string => (string) ($f[$k] ?? '');
$sort = (string) ($photoSort ?? 'recent');
$door = !empty($photoViewer['sse']);
$base = (string) ($photoBase ?? url('jnet/photos'));
$csrf = (string) ($photoCsrf ?? Csrf::token());
$facets = $photos['facets'];
$active = array_filter($f, static fn (string $v): bool => $v !== '');
$sorts = ['recent' => 'Plus récentes', 'ancien' => 'Plus anciennes', 'auteur' => 'Auteur', 'lieu' => 'Grille'];
$pageUrl = static function (int $page) use ($base, $f, $sort): string {
    $q = array_filter($f, static fn (string $v): bool => $v !== '') + ['tri' => $sort !== 'recent' ? $sort : '', 'page' => $page > 1 ? (string) $page : ''];

    return $base . ($q !== [] ? '?' . http_build_query(array_filter($q, static fn (string $v): bool => $v !== '')) : '');
};
$option = static function (string $value, string $label, string $current) use ($h): string {
    return '<option value="' . $h($value) . '"' . ($value === $current ? ' selected' : '') . '>' . $h($label) . '</option>';
};
$fmt = static function (string $when): string {
    $t = strtotime($when);

    return $t !== false ? date('d/m/Y H:i', $t) : '';
};
?>
<div class="ph" data-ph data-base="<?= $h($base) ?>" data-csrf="<?= $h($csrf) ?>" data-door="<?= $door ? '1' : '0' ?>" data-ready="<?= !empty($photos['ready']) ? '1' : '0' ?>">
    <svg class="ph-sprite" width="0" height="0" aria-hidden="true" focusable="false">
        <symbol id="ph-i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
        <symbol id="ph-i-prev" viewBox="0 0 24 24"><path d="m14 6-6 6 6 6"/></symbol>
        <symbol id="ph-i-next" viewBox="0 0 24 24"><path d="m10 6 6 6-6 6"/></symbol>
        <symbol id="ph-i-face" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M7 18c1-3 3-4 5-4s4 1 5 4"/></symbol>
        <symbol id="ph-i-mask" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 12h16M12 4v16M4 8h8M12 16h8"/></symbol>
        <symbol id="ph-i-note" viewBox="0 0 24 24"><path d="M5 5h14v10H10l-5 4z"/></symbol>
        <symbol id="ph-i-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></symbol>
        <symbol id="ph-i-img" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 5-4 4 3 3-2 4 3"/></symbol>
        <symbol id="ph-i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/></symbol>
    </svg>

    <form class="ph-bar" method="get" action="<?= $h($base) ?>" role="search" aria-label="Rechercher et filtrer les photos">
        <label class="ph-search">
            <svg class="ph-ic" aria-hidden="true"><use href="#ph-i-search"></use></svg>
            <span class="ja-sr">Rechercher</span>
            <input type="search" name="q" value="<?= $h($fv('q')) ?>" placeholder="Légende, auteur, album, étiquette…" maxlength="120" autocomplete="off">
        </label>
        <div class="ph-bar__sort">
            <label><span>Trier</span>
                <select name="tri"><?php foreach ($sorts as $k => $l): ?><?= $option($k, $l, $sort) ?><?php endforeach; ?></select>
            </label>
            <button class="ja-b1" type="submit">Appliquer</button>
        </div>
        <details class="ph-filters"<?= count($active) > 1 || (count($active) === 1 && !isset($active['q'])) ? ' open' : '' ?>>
            <summary>Filtres<?php $nf = count(array_diff_key($active, ['q' => 1])); ?><?= $nf > 0 ? ' <b>' . $nf . '</b>' : '' ?></summary>
            <div class="ph-filters__grid">
                <label><span>Album</span><select name="album"><option value="">Tous</option><?php foreach ($facets['albums'] as $a): ?><?= $option((string) $a, (string) $a, $fv('album')) ?><?php endforeach; ?></select></label>
                <label><span>Auteur</span><select name="author"><option value="">Tous</option><?php foreach ($facets['authors'] as $a): ?><?= $option((string) $a, (string) $a, $fv('author')) ?><?php endforeach; ?></select></label>
                <label><span>Unité</span><select name="unit"><option value="">Toutes</option><?php foreach ($facets['units'] as $a): ?><?= $option((string) $a, (string) $a, $fv('unit')) ?><?php endforeach; ?></select></label>
                <label><span>Du</span><input type="date" name="from" value="<?= $h($fv('from')) ?>"></label>
                <label><span>Au</span><input type="date" name="to" value="<?= $h($fv('to')) ?>"></label>
                <label><span>Lieu ou grille</span><input type="text" name="grid" value="<?= $h($fv('grid')) ?>" placeholder="ex. 045" maxlength="32" autocomplete="off"></label>
                <label><span>Étiquette</span>
                    <input type="text" name="label" value="<?= $h($fv('label')) ?>" list="ph-labels" placeholder="ex. checkpoint" maxlength="40" autocomplete="off">
                    <datalist id="ph-labels"><?php foreach ($facets['labels'] as $l): ?><option value="<?= $h((string) $l) ?>"><?php endforeach; ?></datalist>
                </label>
                <label><span>Personnes identifiées</span>
                    <select name="person" data-ph-person>
                        <?= $option('', 'Indifférent', $fv('person')) ?>
                        <?= $option('any', 'Au moins un visage étiqueté', $fv('person')) ?>
                        <?= $option('unknown', 'Visage inconnu', $fv('person')) ?>
                        <?php if (preg_match('/^[ms]\d+$/', $fv('person')) === 1): ?><?= $option($fv('person'), 'Une personne choisie', $fv('person')) ?><?php endif; ?>
                    </select>
                </label>
                <label class="ph-who" hidden data-ph-who><span>Chercher une personne</span>
                    <input type="text" placeholder="Nom ou indicatif" maxlength="60" autocomplete="off" data-ph-who-input>
                    <ul class="ph-suggest" data-ph-who-list hidden></ul>
                </label>
                <?php if ($door): ?>
                    <label><span>Statut SSE</span><select name="sse"><?= $option('', 'Indifférent', $fv('sse')) ?><?= $option('sse', 'Versées au dossier SSE', $fv('sse')) ?><?= $option('none', 'Hors dossier SSE', $fv('sse')) ?></select></label>
                <?php endif; ?>
            </div>
            <?php if ($active !== []): ?><a class="ph-reset" href="<?= $h($base) ?>">Réinitialiser les filtres</a><?php endif; ?>
        </details>
    </form>

    <?php if (empty($photos['ready'])): ?>
        <p class="ph-note" role="status">Les visages, masques, commentaires et étiquettes seront disponibles après la prochaine mise à jour de la base : en attendant, la photothèque se consulte seulement.</p>
    <?php endif; ?>

    <p class="ph-count" aria-live="polite"><b><?= (int) $photos['total'] ?></b> photo<?= $photos['total'] > 1 ? 's' : '' ?><?= $active !== [] ? ' pour ces critères' : '' ?></p>

    <?php if ($photos['rows'] === []): ?>
        <div class="ph-empty">
            <svg class="ph-ic ph-ic--lg" aria-hidden="true"><use href="#ph-i-img"></use></svg>
            <?php if ($active !== []): ?>
                <h2>Aucune photo ne correspond</h2>
                <p>Retirez un critère ou élargissez les dates.</p>
                <a class="ja-b2" href="<?= $h($base) ?>">Réinitialiser les filtres</a>
            <?php else: ?>
                <h2>Pas encore de photo</h2>
                <p>Les photos prises avec l’application Photo de l’ATAK (tablette, téléphone, casque) arrivent ici dès qu’elles sont envoyées.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="ph-grid" data-ph-grid>
            <?php foreach ($photos['rows'] as $p): ?>
                <li>
                    <a class="ph-card" href="<?= $h($p['href']) ?>" data-ph-open="<?= (int) $p['id'] ?>">
                        <span class="ph-card__img"><img src="<?= $h($p['thumb']) ?>" alt="<?= $h($p['title'] !== '' ? $p['title'] : 'Photo de ' . $p['author']) ?>" loading="lazy" decoding="async"></span>
                        <?php if (!empty($p['sse'])): ?><span class="ph-pill ph-pill--sse"><svg class="ph-ic" aria-hidden="true"><use href="#ph-i-lock"></use></svg>SSE</span><?php endif; ?>
                        <span class="ph-card__badges">
                            <?php if ($p['tags'] > 0): ?><span title="Visages étiquetés"><svg class="ph-ic" aria-hidden="true"><use href="#ph-i-face"></use></svg><?= (int) $p['tags'] ?></span><?php endif; ?>
                            <?php if ($p['comments'] > 0): ?><span title="Commentaires"><svg class="ph-ic" aria-hidden="true"><use href="#ph-i-note"></use></svg><?= (int) $p['comments'] ?></span><?php endif; ?>
                            <?php if ($p['masks'] > 0): ?><span title="Zones masquées"><svg class="ph-ic" aria-hidden="true"><use href="#ph-i-mask"></use></svg><?= (int) $p['masks'] ?></span><?php endif; ?>
                        </span>
                        <span class="ph-card__cap">
                            <b><?= $h($p['title'] !== '' ? $p['title'] : ($p['album'] !== '' ? $p['album'] : 'Photo terrain')) ?></b>
                            <small><?= $h($p['author']) ?><?= $p['grid'] !== '' ? ' · ' . $h($p['grid']) : '' ?> · <?= $h($fmt((string) $p['when'])) ?></small>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($photos['pages'] > 1): ?>
            <nav class="ph-pager" aria-label="Pages">
                <?php if ($photos['page'] > 1): ?><a class="ja-b2" href="<?= $h($pageUrl($photos['page'] - 1)) ?>">Précédente</a><?php endif; ?>
                <span>Page <?= (int) $photos['page'] ?> sur <?= (int) $photos['pages'] ?></span>
                <?php if ($photos['page'] < $photos['pages']): ?><a class="ja-b2" href="<?= $h($pageUrl($photos['page'] + 1)) ?>">Suivante</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <div class="ph-viewer" data-ph-viewer hidden role="dialog" aria-modal="true" aria-label="Photo"></div>
    <?php if (is_array($photoOpen ?? null)): ?>
        <script type="application/json" id="ph-open"><?= json_encode($photoOpen, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <?php endif; ?>
</div>
