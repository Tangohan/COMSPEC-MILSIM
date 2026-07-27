<?php
declare(strict_types=1);

/**
 * Catégories de doctrine — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office.
 *
 * @var list<array<string, mixed>> $categories
 * @var string $filterType
 */

$categories = is_array($categories ?? null) ? $categories : [];
$filterType = (string) ($filterType ?? '');

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$success = \App\Core\Session::get('success');
$error = \App\Core\Session::get('error');
\App\Core\Session::forget('success');
\App\Core\Session::forget('error');

$typeOptions = [
    '' => 'Tous les types',
    'role' => 'Rôles',
    'user' => 'Utilisateurs',
    'organizational' => 'Organisation',
    'business' => 'Métier',
];
$typeLabel = static function (string $t) use ($typeOptions): string {
    if ($t === '') {
        return '—';
    }

    return $typeOptions[$t] ?? ucfirst($t);
};

$baseUrl = url('back-office/categories');
?>
<?php if ($error): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $success) ?></p>
<?php endif; ?>

<form method="get" action="<?= $h($baseUrl) ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Filtrer</span>
        <span class="ath-form__hint">Le classement sert aux référentiels de rôles, profils et domaines.</span>
    </div>
    <div class="ath-form__grid">
        <label class="ath-field">
            <span class="ath-field__label">Type</span>
            <select name="type" class="ath-field__select">
                <?php foreach ($typeOptions as $value => $label): ?>
                <option value="<?= $h((string) $value) ?>"<?= $filterType === (string) $value ? ' selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn">Appliquer le filtre</button>
        <a href="<?= $h(url('back-office/categories/create')) ?>" class="ath-btn ath-btn--solid">Créer une catégorie</a>
    </div>
</form>

<?php
$athTableTitle = 'Catégories';
$athTableCount = count($categories);
$athTableCols = ['NOM', 'TYPE', 'COULEUR|m', 'ORDRE|r'];
$athTableRows = [];
$athTableRowActions = [];
foreach ($categories as $c) {
    $id = (int) ($c['id'] ?? 0);
    $color = trim((string) ($c['color'] ?? ''));
    $athTableRows[] = [
        (string) ($c['name'] ?? '—'),
        $typeLabel((string) ($c['type'] ?? '')),
        $color !== '' ? $color : '—',
        (string) (int) ($c['display_order'] ?? 0),
    ];
    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $athTableRowActions[] = '<a href="' . $h(url('back-office/categories/' . $id . '/edit')) . '" class="ath-row-action">Modifier</a>';
}
$athTableActionsLabel = 'ÉDITION';
$athTableFilters = [];
$athTableMinWidth = '860px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = $categories === []
    ? 'Aucune catégorie trouvée pour ce filtre.'
    : 'L’ordre d’affichage détermine la position dans les listes de sélection.';
require base_path('views/partials/ath_table.php');
