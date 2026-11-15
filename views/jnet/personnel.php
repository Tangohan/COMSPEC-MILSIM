<?php
declare(strict_types=1);

/**
 * Annuaire du personnel (JnetPortalController::personnel) : registre filtrable et triable dans la page (recherche instantanée,
 * unité, grade, fonction, statut, regroupement, lignes ou cartes), lecture seule. Chaque membre est rendu ; le filtre de
 * l'adresse (?filtre=) marque ceux qui ne correspondent pas, pour que la page reste juste sans JavaScript.
 *
 * @var list<array<string, mixed>> $personnel
 * @var string $personnelFilter
 * @var list<array{key: string, label: string}> $personnelFilters
 * @var int $personnelTotal
 */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$personnel = is_array($personnel ?? null) ? array_values($personnel) : [];
$filter = trim((string) ($personnelFilter ?? 'all'));
$total = (int) ($personnelTotal ?? count($personnel));

// Valeurs distinctes et effectifs pour les listes de filtres.
$units = [];
$grades = [];
$functions = [];
foreach ($personnel as $p) {
    $unit = trim((string) ($p['unit'] ?? ''));
    $grade = trim((string) ($p['grade'] ?? ''));
    $fn = trim((string) ($p['function'] ?? ''));
    if ($unit !== '') {
        $units[$unit] = ($units[$unit] ?? 0) + 1;
    }
    if ($grade !== '') {
        $grades[$grade] = ['n' => ($grades[$grade]['n'] ?? 0) + 1, 'rank' => (int) ($p['grade_rank'] ?? 999)];
    }
    if ($fn !== '') {
        $functions[$fn] = ($functions[$fn] ?? 0) + 1;
    }
}
uksort($units, static fn (string $a, string $b): int => strnatcasecmp($a, $b));
uksort($functions, static fn (string $a, string $b): int => strnatcasecmp($a, $b));
uasort($grades, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);
$offCount = count(array_filter($personnel, static fn (array $p): bool => ($p['duty'] ?? '') === 'off'));
$shown = count(array_filter($personnel, static fn (array $p): bool => !empty($p['match'])));

// État de départ repris du filtre de l'adresse : « off » = indisponibles, un nom d'unité = cette unité, sinon une recherche.
$initUnit = '';
$initStatus = '';
$initQ = '';
if ($filter !== '' && strcasecmp($filter, 'all') !== 0) {
    if (strcasecmp($filter, 'off') === 0) {
        $initStatus = 'off';
    } else {
        foreach (array_keys($units) as $unitName) {
            if (mb_strtoupper($unitName) === mb_strtoupper($filter)) {
                $initUnit = $unitName;
                break;
            }
        }
        if ($initUnit === '') {
            $initQ = $filter;
        }
    }
}

$option = static fn (string $value, string $label, int $n = -1, string $selected = ''): string => '<option value="' . $h($value) . '"' . ($selected !== '' && $selected === $value ? ' selected' : '') . '>' . $h($label) . ($n >= 0 ? ' (' . $n . ')' : '') . '</option>';
$select = static fn (string $name, string $label, string $all, string $options): string => '<label class="pe-sel"><span class="ja-sr">' . $h($label) . '</span><select data-pe-' . $name . ' aria-label="' . $h($label) . '"><option value="">' . $h($all) . '</option>' . $options . '</select></label>';
$face = static function (array $p) use ($h): string {
    $photo = $p['photo'] ?? null;

    return is_string($photo) && $photo !== ''
        ? '<img src="' . $h($photo) . '" alt="" loading="lazy" decoding="async">'
        : '<span>' . $h((string) ($p['initials'] ?? '?')) . '</span>';
};
?>
<section class="pe" data-pe data-init-unit="<?= $h($initUnit) ?>" data-init-status="<?= $h($initStatus) ?>" data-init-q="<?= $h($initQ) ?>" aria-label="Annuaire du personnel">
    <?php if ($personnel === []): ?>
        <div class="pe-empty">
            <p><strong>Aucun membre actif dans l’annuaire.</strong></p>
            <p>Les membres apparaissent ici dès que leur compte est actif dans la communauté.</p>
        </div>
    <?php else: ?>
        <div class="pe-bar" role="search" aria-label="Filtrer l’annuaire">
            <label class="ja-fi pe-q">
                <svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <span class="ja-sr">Rechercher un membre</span>
                <input type="search" placeholder="Nom, indicatif, fonction, unité" autocomplete="off" data-pe-q value="<?= $h($initQ) ?>">
                <kbd aria-hidden="true">/</kbd>
            </label>
            <?= $select('unit', 'Unité', 'Toutes les unités', implode('', array_map(static fn (string $u, int $n): string => $option($u, $u, $n, $initUnit), array_keys($units), $units))) ?>
            <?= $select('grade', 'Grade', 'Tous les grades', implode('', array_map(static fn (string $g, array $meta): string => $option($g, $g, $meta['n']), array_keys($grades), $grades))) ?>
            <?= $select('fn', 'Fonction', 'Toutes les fonctions', implode('', array_map(static fn (string $f, int $n): string => $option($f, $f, $n), array_keys($functions), $functions))) ?>
            <label class="pe-sel"><span class="ja-sr">Statut</span>
                <select data-pe-status aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    <option value="on">En service (<?= $total - $offCount ?>)</option>
                    <option value="off"<?= $initStatus === 'off' ? ' selected' : '' ?>>Indisponibles (<?= $offCount ?>)</option>
                </select>
            </label>
            <label class="pe-sel"><span class="ja-sr">Trier par</span>
                <select data-pe-sort aria-label="Trier par">
                    <option value="name">Trier : nom</option>
                    <option value="grade">Trier : grade</option>
                    <option value="unit">Trier : unité</option>
                </select>
            </label>
            <div class="pe-seg" role="group" aria-label="Regrouper">
                <button type="button" data-pe-group="" aria-pressed="true">Sans groupe</button>
                <button type="button" data-pe-group="unit" aria-pressed="false">Par unité</button>
                <button type="button" data-pe-group="grade" aria-pressed="false">Par grade</button>
            </div>
            <div class="pe-seg" role="group" aria-label="Affichage">
                <button type="button" data-pe-view="list" aria-pressed="true">Lignes</button>
                <button type="button" data-pe-view="cards" aria-pressed="false">Cartes</button>
            </div>
        </div>

        <p class="pe-count" role="status" aria-live="polite">
            <span><b data-pe-n><?= $shown ?></b> sur <?= $total ?> <?= $total > 1 ? 'membres' : 'membre' ?></span>
            <button type="button" class="pe-reset" data-pe-reset hidden>Réinitialiser les filtres</button>
        </p>

        <div class="pe-cols" aria-hidden="true"><span>Membre</span><span>Grade</span><span>Fonction</span><span>Unité</span><span>Statut</span></div>
        <ul class="pe-list" data-pe-list>
            <?php foreach ($personnel as $p): ?>
                <?php
                $name = (string) ($p['name'] ?? '');
                $callsign = trim((string) ($p['callsign'] ?? ''));
                $grade = trim((string) ($p['grade'] ?? ''));
                $unit = trim((string) ($p['unit'] ?? ''));
                $fn = trim((string) ($p['function'] ?? ''));
                $isOff = ($p['duty'] ?? '') === 'off';
                $fil = is_array($p['fil'] ?? null) ? $p['fil'] : null;
                $chain = $fil !== null ? implode(' › ', array_map('strval', (array) $fil['chain'])) : '';
                $search = mb_strtolower(implode(' ', array_filter([$name, $callsign, $grade, $unit, $fn, $chain, (string) ($p['role'] ?? '')])));
                ?>
                <li class="pe-item" data-pe-item data-name="<?= $h($name) ?>" data-grade="<?= $h($grade) ?>" data-rank="<?= (int) ($p['grade_rank'] ?? 999) ?>" data-unit="<?= $h($unit) ?>" data-chain="<?= $h($chain) ?>" data-fn="<?= $h($fn) ?>" data-duty="<?= $isOff ? 'off' : 'on' ?>" data-q="<?= $h($search) ?>"<?= empty($p['match']) ? ' hidden' : '' ?>>
                    <a class="pe-open" href="<?= $h((string) ($p['href'] ?? '#')) ?>"><span class="ja-sr">Ouvrir la fiche de <?= $h($name) ?></span></a>
                    <span class="pe-av" aria-hidden="true"><?= $face($p) ?></span>
                    <span class="pe-id"><b><?= $h($name) ?></b><?php if ($callsign !== '' && strcasecmp($callsign, $name) !== 0): ?><small class="ja-mono"><?= $h($callsign) ?></small><?php elseif ($callsign !== '' || $name !== ''): ?><small class="pe-noname">Nom non renseigné</small><?php endif; ?></span>
                    <span class="pe-grade"><?= $grade !== '' ? $h($grade) : '<i class="pe-none">—</i>' ?></span>
                    <span class="pe-fn"><?= $fn !== '' ? $h($fn) : '<i class="pe-none">—</i>' ?></span>
                    <span class="pe-unit">
                        <?php if ($unit === ''): ?><i class="pe-none">—</i>
                        <?php elseif ($fil !== null): ?><a href="<?= $h((string) $fil['href']) ?>"><?= $h($unit) ?></a><?php if ($chain !== ''): ?><small><?= $h($chain) ?></small><?php endif; ?>
                        <?php else: ?><span><?= $h($unit) ?></span><?php endif; ?>
                    </span>
                    <span class="pe-st pe-st--<?= $isOff ? 'off' : 'on' ?>"><?= $isOff ? 'Indisponible' : 'En service' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="pe-nomatch" data-pe-none hidden>
            <p><strong>Aucun membre ne correspond.</strong></p>
            <p>Essayez un autre nom, ou retirez un filtre.</p>
            <p><button type="button" class="ja-b2 sm" data-pe-reset>Réinitialiser les filtres</button></p>
        </div>
    <?php endif; ?>
</section>
