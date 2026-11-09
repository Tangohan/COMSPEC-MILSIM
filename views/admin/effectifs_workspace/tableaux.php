<?php
declare(strict_types=1);

/**
 * Effectifs › Tableaux de données (EffectifsWorkspaceController::tableaux) : constructeur sans JavaScript.
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
while (count($filters) < 3) {
    $filters[] = ['col' => '', 'op' => 'contains', 'value' => ''];
}
$group = $def['group'] ?? ['col' => '', 'agg_col' => '', 'agg_fn' => ''];
$sort = $def['sort'] ?? ['col' => '', 'dir' => 'asc'];
$numberCols = array_filter($columns, static fn (array $c): bool => $c['type'] === 'number');
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
?>
<section class="eff-nc eff-dt" aria-labelledby="eff-dt-title">
    <header class="eff-nc__head">
        <div>
            <h2 id="eff-dt-title">Tableaux de données</h2>
            <p>Choisissez ce que vous voulez voir : une source, les colonnes, des filtres, un tri, un regroupement. Vous ne voyez que les sources auxquelles vos droits donnent accès, et un tableau partagé est toujours recalculé avec vos droits.</p>
        </div>
    </header>
    <?php if ($flashErr): ?><p class="eff-nc__flash is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="eff-nc__flash" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
    <?php if ($dtNotice !== ''): ?><p class="eff-nc__flash is-bad" role="alert"><?= $h($dtNotice) ?></p><?php endif; ?>

    <?php if ($dtAvailable === []): ?>
        <p class="eff-nc__empty">Aucune source de données n’est ouverte avec vos droits.</p>
    <?php else: ?>
    <?php if ($dtList !== []): ?>
    <nav class="eff-dt__saved" aria-label="Tableaux enregistrés">
        <b>Enregistrés</b>
        <?php foreach ($dtList as $t): ?>
            <a href="<?= $h($base . '?t=' . $t['id']) ?>" class="<?= $saved !== null && $saved['id'] === $t['id'] ? 'is-on' : '' ?>"><?= $h($t['title']) ?><?= $t['shared'] ? ' · partagé' : '' ?><?= $t['mine'] ? '' : ' · d’un collègue' ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <form method="get" action="<?= $h($base) ?>" class="eff-dt__builder">
        <input type="hidden" name="run" value="1">
        <fieldset>
            <legend>1. Source</legend>
            <label>Données
                <select name="source">
                    <?php foreach ($dtAvailable as $key => $src): ?><option value="<?= $h($key) ?>"<?= $key === $dtSource ? ' selected' : '' ?>><?= $h($src['label']) ?> — <?= $h($src['hint']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <p class="eff-nc__hint">Après avoir changé de source, cliquez sur « Afficher le tableau » : les colonnes proposées se mettent à jour.</p>
        </fieldset>
        <fieldset>
            <legend>2. Colonnes</legend>
            <?php foreach ($columns as $key => $col): ?>
                <label class="eff-nc__check"><input type="checkbox" name="cols[]" value="<?= $h($key) ?>"<?= in_array($key, $def['columns'] ?? [], true) ? ' checked' : '' ?>> <?= $h($col['label']) ?></label>
            <?php endforeach; ?>
            <p class="eff-nc__hint">12 colonnes au plus.</p>
        </fieldset>
        <fieldset>
            <legend>3. Filtres</legend>
            <?php foreach (array_slice($filters, 0, 6) as $fl): ?>
            <div class="eff-dt__row">
                <select name="f_col[]" aria-label="Colonne du filtre">
                    <option value="">—</option>
                    <?php foreach ($columns as $key => $col): ?><option value="<?= $h($key) ?>"<?= $fl['col'] === $key ? ' selected' : '' ?>><?= $h($col['label']) ?></option><?php endforeach; ?>
                </select>
                <select name="f_op[]" aria-label="Condition">
                    <?php foreach ($dtOps as $op => $label): ?><option value="<?= $h($op) ?>"<?= $fl['op'] === $op ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                </select>
                <input type="text" name="f_val[]" value="<?= $h($fl['value']) ?>" maxlength="100" aria-label="Valeur" placeholder="Valeur">
            </div>
            <?php endforeach; ?>
        </fieldset>
        <fieldset>
            <legend>4. Tri et regroupement</legend>
            <label>Trier par
                <select name="sort">
                    <option value="">—</option>
                    <?php foreach ($columns as $key => $col): ?><option value="<?= $h($key) ?>"<?= $sort['col'] === $key ? ' selected' : '' ?>><?= $h($col['label']) ?></option><?php endforeach; ?>
                    <option value="_count"<?= $sort['col'] === '_count' ? ' selected' : '' ?>>Effectif (si regroupé)</option>
                </select>
            </label>
            <label>Ordre
                <select name="dir"><option value="asc"<?= $sort['dir'] === 'asc' ? ' selected' : '' ?>>Croissant</option><option value="desc"<?= $sort['dir'] === 'desc' ? ' selected' : '' ?>>Décroissant</option></select>
            </label>
            <label>Regrouper par
                <select name="group">
                    <option value="">Ne pas regrouper</option>
                    <?php foreach ($columns as $key => $col): ?><option value="<?= $h($key) ?>"<?= $group['col'] === $key ? ' selected' : '' ?>><?= $h($col['label']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php if ($numberCols !== []): ?>
            <label>Calculer
                <select name="agg_fn"><option value="">—</option><?php foreach ($dtAggs as $fn => $label): ?><option value="<?= $h($fn) ?>"<?= $group['agg_fn'] === $fn ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?></select>
            </label>
            <label>sur
                <select name="agg_col"><option value="">—</option><?php foreach ($numberCols as $key => $col): ?><option value="<?= $h($key) ?>"<?= $group['agg_col'] === $key ? ' selected' : '' ?>><?= $h($col['label']) ?></option><?php endforeach; ?></select>
            </label>
            <?php endif; ?>
        </fieldset>
        <button type="submit" class="eff-nc__btn">Afficher le tableau</button>
    </form>

    <?php if ($dtResult !== null && $def !== null): ?>
    <section class="eff-dt__result" aria-live="polite">
        <h3><?= $h($saved['title'] ?? $dtAvailable[$dtSource]['label']) ?> <small><?= (int) $dtResult['total'] ?> ligne(s)</small></h3>
        <?php if ($dtTruncated): ?><p class="eff-nc__flash is-bad">Source très volumineuse : seules les <?= (int) $dtMaxRows ?> premières lignes sont lues.</p><?php endif; ?>
        <?php if ($dtResult['rows'] === []): ?>
            <p class="eff-nc__empty">Aucune ligne ne correspond.</p>
        <?php else: ?>
        <div class="eff-nc__tablewrap">
            <table class="eff-nc__table">
                <thead><tr><?php foreach ($dtResult['cols'] as $c): ?><th scope="col"<?= $c['type'] === 'number' ? ' class="is-num"' : '' ?>><?= $h($c['label']) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach (array_slice($dtResult['rows'], 0, 500) as $row): ?>
                    <tr><?php foreach ($row as $i => $cell): ?><td<?= $dtResult['cols'][$i]['type'] === 'number' ? ' class="is-num"' : '' ?>><?= $h($cell) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($dtResult['rows']) > 500): ?><p class="eff-nc__hint">Affichage des 500 premières lignes ; l’export CSV contient tout.</p><?php endif; ?>
        <?php endif; ?>

        <div class="eff-dt__actions">
            <a class="eff-nc__btn eff-nc__btn--ghost" href="<?= $h(effectifs_workspace_url('tableaux/export.csv') . '?' . http_build_query($params)) ?>">Exporter en CSV</a>
            <form method="post" action="<?= $h(effectifs_workspace_url('tableaux/tableur')) ?>" class="eff-dt__inline">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <?= $hidden($params) ?>
                <button type="submit" class="eff-nc__btn eff-nc__btn--ghost">Ouvrir dans un tableur (formules, cellules)</button>
            </form>
        </div>
        <form method="post" action="<?= $h(effectifs_workspace_url('tableaux/enregistrer')) ?>" class="eff-nc__form eff-nc__panel">
            <h3><?= $canEditSaved ? 'Mettre à jour ce tableau' : 'Enregistrer ce tableau' ?></h3>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <?php if ($canEditSaved): ?><input type="hidden" name="id" value="<?= (int) $saved['id'] ?>"><?php endif; ?>
            <?= $hidden($params) ?>
            <label>Titre <input type="text" name="title" maxlength="80" value="<?= $h($saved['title'] ?? '') ?>" required></label>
            <label class="eff-nc__check"><input type="checkbox" name="shared" value="1"<?= !empty($saved['shared']) ? ' checked' : '' ?>> Partager avec la communauté (chacun le voit avec ses propres droits)</label>
            <button type="submit" class="eff-nc__btn">Enregistrer</button>
        </form>
        <?php if ($canEditSaved): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('tableaux/' . $saved['id'] . '/supprimer')) ?>" class="eff-dt__inline">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <button type="submit" class="eff-nc__btn eff-nc__btn--warn">Supprimer ce tableau enregistré</button>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php endif; ?>
</section>
