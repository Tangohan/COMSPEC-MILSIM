<?php
declare(strict_types=1);

/**
 * Effectifs › Tableaux de données (EffectifsWorkspaceController::tableaux) : constructeur à panneau latéral.
 * Fonctionne sans JavaScript (formulaire GET + boutons) ; back-office-tableaux.js ajoute l'aperçu en direct, le tri,
 * le filtre, le masquage et le redimensionnement des colonnes, la sélection et la copie, sans changer les données.
 *
 * @var array<string, array{label: string, hint: string, columns: array<string, array{label: string, type: string}>}> $dtAvailable
 * @var array<string, mixed>|null $dtDef
 * @var array{cols: list<array{key: string, label: string, type: string}>, rows: list<list<string>>, total: int}|null $dtResult
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$base = effectifs_workspace_url('tableaux');
$def = is_array($dtDef ?? null) ? $dtDef : null;
$saved = is_array($dtSaved ?? null) ? $dtSaved : null;
$canEditSaved = $saved !== null && $saved['owner_user_id'] === (int) $dtUserId;
$columns = $dtSource !== '' && isset($dtAvailable[$dtSource]) ? $dtAvailable[$dtSource]['columns'] : [];
$filters = $def['filters'] ?? [];
$activeFilters = count(array_filter($filters, static fn (array $f): bool => ($f['col'] ?? '') !== ''));
$filterRows = max(3, min(6, $activeFilters + 2));
while (count($filters) < $filterRows) {
    $filters[] = ['col' => '', 'op' => 'contains', 'value' => ''];
}
$group = $def['group'] ?? ['col' => '', 'agg_col' => '', 'agg_fn' => ''];
$sort = $def['sort'] ?? ['col' => '', 'dir' => 'asc'];
$numberCols = array_filter($columns, static fn (array $c): bool => $c['type'] === 'number');
$maxCols = \App\Services\BackOffice\Tables\DataTableQuery::MAX_COLUMNS;
$pickedCols = $def['columns'] ?? [];
/** Paramètres de la définition courante, pour les liens et les formulaires qui la reprennent. */
$params = [];
if ($def !== null) {
    $params = ['source' => $dtSource, 'cols' => $def['columns'], 'sort' => $sort['col'], 'dir' => $sort['dir'], 'group' => $group['col'], 'agg_col' => $group['agg_col'], 'agg_fn' => $group['agg_fn']];
    foreach ($def['filters'] as $i => $fl) {
        $params['f_col'][$i] = $fl['col'];
        $params['f_op'][$i] = $fl['op'];
        $params['f_val'][$i] = $fl['value'];
    }
}
$hidden = static function (array $params) use ($h): string {
    $out = '';
    foreach ($params as $k => $v) {
        foreach ((array) $v as $item) {
            $out .= '<input type="hidden" name="' . $h($k) . (is_array($v) ? '[]' : '') . '" value="' . $h($item) . '">';
        }
    }

    return $out;
};
/** Une somme a un sens pour un nombre brut ou un effectif, pas pour une moyenne ou un extrême. */
$sumable = static function (array $c) use ($group): bool {
    if ($c['type'] !== 'number') {
        return false;
    }

    return $group['col'] === '' || $c['key'] === \App\Services\BackOffice\Tables\DataTableQuery::COUNT_KEY || ($c['key'] === $group['agg_col'] && $group['agg_fn'] === 'sum');
};
$numOf = static function (string $v): ?float {
    $t = str_replace(',', '.', str_replace([' ', "\u{202f}", "\u{a0}"], '', $v));

    return is_numeric($t) ? (float) $t : null;
};
$csv = effectifs_workspace_url('tableaux/export.csv') . '?' . http_build_query($params);
$title = (string) ($saved['title'] ?? ($dtAvailable[$dtSource]['label'] ?? ''));
?>
<section class="dt" aria-labelledby="dt-title" data-dt data-dt-base="<?= $h($base) ?>">
    <header class="dt-head">
        <h2 id="dt-title">Tableaux de données</h2>
        <p>Choisissez une source, composez vos colonnes, filtrez, triez ou regroupez. Vous ne voyez que les sources ouvertes par vos droits, et un tableau partagé est toujours recalculé avec les vôtres.</p>
    </header>
    <?php if ($flashErr): ?><p class="dt-flash is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="dt-flash" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
    <?php if ($dtNotice !== ''): ?><p class="dt-flash is-bad" role="alert"><?= $h($dtNotice) ?></p><?php endif; ?>

    <?php if ($dtAvailable === []): ?>
        <div class="dt-empty">
            <b>Aucune source de données</b>
            <p>Aucune source n’est ouverte avec vos droits. Demandez l’accès aux effectifs, aux qualifications ou au matériel à un administrateur.</p>
        </div>
    <?php else: ?>
    <?php if ($dtList !== []): ?>
    <nav class="dt-saved" aria-label="Tableaux enregistrés">
        <span class="dt-saved__lbl">Enregistrés</span>
        <?php foreach ($dtList as $t): ?>
            <a href="<?= $h($base . '?t=' . $t['id']) ?>" class="dt-saved__it<?= $saved !== null && $saved['id'] === $t['id'] ? ' is-on' : '' ?>"<?= $saved !== null && $saved['id'] === $t['id'] ? ' aria-current="true"' : '' ?>>
                <?= $h($t['title']) ?>
                <?php if ($t['shared']): ?><span class="dt-tag">Partagé</span><?php endif; ?>
                <?php if (!$t['mine']): ?><span class="dt-tag">D’un collègue</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <div class="dt-layout">
        <form method="get" action="<?= $h($base) ?>" class="dt-side" id="dt-form" data-dt-form>
            <input type="hidden" name="run" value="1">
            <section class="dt-sec" aria-labelledby="dt-s1">
                <h3 id="dt-s1" class="dt-sec__t"><span class="dt-sec__n">1</span> Source</h3>
                <div class="dt-cards" role="radiogroup" aria-labelledby="dt-s1">
                    <?php foreach ($dtAvailable as $key => $src): ?>
                    <label class="dt-card">
                        <input type="radio" name="source" value="<?= $h($key) ?>"<?= $key === $dtSource ? ' checked' : '' ?> data-dt-source>
                        <span class="dt-card__b"><b><?= $h($src['label']) ?></b><small><?= $h($src['hint']) ?></small></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="dt-hint dt-nojs">Après avoir changé de source, cliquez sur « Afficher le tableau » : les colonnes proposées se mettent à jour.</p>
            </section>

            <section class="dt-sec" aria-labelledby="dt-s2">
                <h3 id="dt-s2" class="dt-sec__t"><span class="dt-sec__n">2</span> Colonnes <span class="dt-count" data-dt-colcount data-max="<?= (int) $maxCols ?>"><?= count($pickedCols) ?>/<?= (int) $maxCols ?></span></h3>
                <div class="dt-pills">
                    <?php foreach ($columns as $key => $col): ?>
                    <label class="dt-pill"><input type="checkbox" name="cols[]" value="<?= $h($key) ?>"<?= in_array($key, $pickedCols, true) ? ' checked' : '' ?> data-dt-col><span><?= $h($col['label']) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <p class="dt-hint"><?= (int) $maxCols ?> colonnes au plus.</p>
            </section>

            <section class="dt-sec" aria-labelledby="dt-s3">
                <h3 id="dt-s3" class="dt-sec__t"><span class="dt-sec__n">3</span> Filtres <?php if ($activeFilters > 0): ?><span class="dt-count is-on"><?= (int) $activeFilters ?> actif(s)</span><?php endif; ?></h3>
                <?php foreach (array_slice($filters, 0, 6) as $fl): ?>
                <div class="dt-frow<?= $fl['col'] !== '' ? ' is-set' : '' ?>">
                    <select name="f_col[]" aria-label="Colonne du filtre">
                        <option value="">Colonne…</option>
                        <?php foreach ($columns as $key => $col): ?><option value="<?= $h($key) ?>"<?= $fl['col'] === $key ? ' selected' : '' ?>><?= $h($col['label']) ?></option><?php endforeach; ?>
                    </select>
                    <select name="f_op[]" aria-label="Condition">
                        <?php foreach ($dtOps as $op => $label): ?><option value="<?= $h($op) ?>"<?= $fl['op'] === $op ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                    </select>
                    <input type="text" name="f_val[]" value="<?= $h($fl['value']) ?>" maxlength="100" aria-label="Valeur" placeholder="Valeur">
                </div>
                <?php endforeach; ?>
            </section>

            <section class="dt-sec" aria-labelledby="dt-s4">
                <h3 id="dt-s4" class="dt-sec__t"><span class="dt-sec__n">4</span> Tri et regroupement</h3>
                <div class="dt-grid2">
                    <label class="dt-f">Trier par
                        <select name="sort">
                            <option value="">—</option>
                            <?php foreach ($columns as $key => $col): ?><option value="<?= $h($key) ?>"<?= $sort['col'] === $key ? ' selected' : '' ?>><?= $h($col['label']) ?></option><?php endforeach; ?>
                            <option value="_count"<?= $sort['col'] === '_count' ? ' selected' : '' ?>>Effectif (si regroupé)</option>
                        </select>
                    </label>
                    <label class="dt-f">Ordre
                        <select name="dir"><option value="asc"<?= $sort['dir'] === 'asc' ? ' selected' : '' ?>>Croissant</option><option value="desc"<?= $sort['dir'] === 'desc' ? ' selected' : '' ?>>Décroissant</option></select>
                    </label>
                </div>
                <label class="dt-f">Regrouper par
                    <select name="group">
                        <option value="">Ne pas regrouper</option>
                        <?php foreach ($columns as $key => $col): ?><option value="<?= $h($key) ?>"<?= $group['col'] === $key ? ' selected' : '' ?>><?= $h($col['label']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <?php if ($numberCols !== []): ?>
                <div class="dt-grid2">
                    <label class="dt-f">Calculer
                        <select name="agg_fn"><option value="">—</option><?php foreach ($dtAggs as $fn => $label): ?><option value="<?= $h($fn) ?>"<?= $group['agg_fn'] === $fn ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?></select>
                    </label>
                    <label class="dt-f">sur
                        <select name="agg_col"><option value="">—</option><?php foreach ($numberCols as $key => $col): ?><option value="<?= $h($key) ?>"<?= $group['agg_col'] === $key ? ' selected' : '' ?>><?= $h($col['label']) ?></option><?php endforeach; ?></select>
                    </label>
                </div>
                <?php endif; ?>
            </section>

            <div class="dt-side__foot">
                <button type="submit" class="ath-btn ath-btn--solid dt-go">Afficher le tableau</button>
                <a class="dt-reset" href="<?= $h($base) ?>">Réinitialiser</a>
            </div>
        </form>

        <div class="dt-main" data-dt-main aria-live="polite">
        <?php if ($dtResult === null || $def === null): ?>
            <div class="dt-empty" data-dt-idle>
                <b>Prêt à construire</b>
                <p>Cochez au moins une colonne puis cliquez sur « Afficher le tableau ». Avec JavaScript, l’aperçu se met à jour à chaque réglage.</p>
            </div>
        <?php else: ?>
            <div class="dt-bar">
                <div class="dt-bar__ttl">
                    <h3><?= $h($title) ?></h3>
                    <span class="dt-count is-on" data-dt-rowcount data-total="<?= (int) $dtResult['total'] ?>"><?= (int) $dtResult['total'] ?> ligne(s)</span>
                </div>
                <label class="dt-search" hidden data-dt-js>
                    <span class="ath-sr-only">Filtrer les lignes affichées</span>
                    <input type="search" placeholder="Filtrer les lignes…" autocomplete="off" data-dt-filter>
                </label>
                <div class="dt-bar__acts">
                    <details class="dt-menu" data-dt-js hidden>
                        <summary class="ath-btn">Colonnes</summary>
                        <div class="dt-menu__pop" data-dt-colmenu>
                            <?php foreach ($dtResult['cols'] as $i => $c): ?>
                            <label class="dt-check"><input type="checkbox" checked data-dt-vis="<?= (int) $i ?>"> <?= $h($c['label']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <button type="button" class="ath-btn" data-dt-density data-dt-js hidden aria-pressed="false">Compact</button>
                    <button type="button" class="ath-btn" data-dt-copy data-dt-js hidden>Copier</button>
                    <details class="dt-menu">
                        <summary class="ath-btn">Exporter</summary>
                        <div class="dt-menu__pop">
                            <a class="dt-menu__it" href="<?= $h($csv) ?>">Exporter en CSV <small>Toutes les lignes, ouvrable dans un tableur.</small></a>
                            <form method="post" action="<?= $h(effectifs_workspace_url('tableaux/tableur')) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <?= $hidden($params) ?>
                                <button type="submit" class="dt-menu__it">Ouvrir dans un tableur <small>Formules et cellules modifiables.</small></button>
                            </form>
                        </div>
                    </details>
                    <a class="ath-btn ath-btn--solid" href="#dt-save"><?= $canEditSaved ? 'Mettre à jour' : 'Enregistrer' ?></a>
                </div>
            </div>
            <?php if ($dtTruncated): ?><p class="dt-flash is-bad">Source très volumineuse : seules les <?= (int) $dtMaxRows ?> premières lignes sont lues.</p><?php endif; ?>
            <?php if ($dtResult['rows'] === []): ?>
            <div class="dt-empty">
                <b>Aucune ligne ne correspond</b>
                <p>Assouplissez un filtre ou changez de source. Les colonnes et le tri sont conservés.</p>
            </div>
            <?php else: ?>
            <?php
            $shown = array_slice($dtResult['rows'], 0, 500);
            $sums = [];
            foreach ($dtResult['cols'] as $i => $c) {
                if ($sumable($c)) {
                    $t = 0.0;
                    foreach ($shown as $row) {
                        $t += $numOf((string) ($row[$i] ?? '')) ?? 0.0;
                    }
                    $sums[$i] = \App\Services\BackOffice\Tables\DataTableQuery::show($t);
                }
            }
            ?>
            <div class="dt-scroll" tabindex="0" role="region" aria-label="<?= $h('Tableau ' . $title) ?>">
                <table class="dt-grid" data-dt-table>
                    <thead>
                        <tr>
                            <th scope="col" class="dt-grid__n"><span class="ath-sr-only">Ligne</span></th>
                            <?php foreach ($dtResult['cols'] as $i => $c): ?>
                            <?php $isSorted = $c['key'] === $sort['col'] && $sort['col'] !== ''; ?>
                            <th scope="col" data-i="<?= (int) $i ?>"<?= isset($sums[$i]) ? ' data-sum="1"' : '' ?><?= $c['type'] === 'number' ? ' class="is-num" data-num="1"' : '' ?><?= $isSorted ? ' aria-sort="' . ($sort['dir'] === 'desc' ? 'descending' : 'ascending') . '"' : '' ?>>
                                <span class="dt-grid__h"><?= $h($c['label']) ?><span class="dt-grid__arrow" aria-hidden="true"><?= $isSorted ? ($sort['dir'] === 'desc' ? '▼' : '▲') : '' ?></span></span>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($shown as $r => $row): ?>
                        <tr>
                            <th scope="row" class="dt-grid__n"><?= $r + 1 ?></th>
                            <?php foreach ($row as $i => $cell): ?><td<?= $dtResult['cols'][$i]['type'] === 'number' ? ' class="is-num"' : '' ?>><?= $h($cell) ?></td><?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <?php if ($sums !== []): ?>
                    <tfoot>
                        <tr>
                            <th scope="row" class="dt-grid__n">Σ</th>
                            <?php foreach ($dtResult['cols'] as $i => $c): ?>
                            <td<?= $c['type'] === 'number' ? ' class="is-num"' : '' ?><?= isset($sums[$i]) ? ' data-foot="' . (int) $i . '"' : '' ?>><?= isset($sums[$i]) ? $h($sums[$i]) : ($i === 0 ? 'Total' : '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            <p class="dt-status" data-dt-status role="status"><?php if (count($dtResult['rows']) > 500): ?>Affichage des 500 premières lignes ; l’export CSV contient tout.<?php endif; ?></p>
            <?php endif; ?>

            <section class="dt-save" id="dt-save" aria-labelledby="dt-save-t">
                <h3 id="dt-save-t"><?= $canEditSaved ? 'Mettre à jour ce tableau' : 'Enregistrer et partager' ?></h3>
                <form method="post" action="<?= $h(effectifs_workspace_url('tableaux/enregistrer')) ?>" class="dt-save__form">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <?php if ($canEditSaved): ?><input type="hidden" name="id" value="<?= (int) $saved['id'] ?>"><?php endif; ?>
                    <?= $hidden($params) ?>
                    <label class="dt-f">Titre <input type="text" name="title" maxlength="80" value="<?= $h($saved['title'] ?? '') ?>" required></label>
                    <label class="dt-check"><input type="checkbox" name="shared" value="1"<?= !empty($saved['shared']) ? ' checked' : '' ?>> Partager avec la communauté <small>Chacun le voit avec ses propres droits.</small></label>
                    <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
                </form>
                <?php if ($canEditSaved): ?>
                <form method="post" action="<?= $h(effectifs_workspace_url('tableaux/' . $saved['id'] . '/supprimer')) ?>" class="dt-save__del">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="ath-btn dt-danger" data-dt-confirm="Supprimer ce tableau enregistré ?">Supprimer ce tableau enregistré</button>
                </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
<script defer src="<?= $h(asset_url('assets/js/back-office-tableaux.js')) ?>"></script>
