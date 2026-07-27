<?php
declare(strict_types=1);

/**
 * Groupes opérationnels — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office.
 *
 * @var list<array<string, mixed>> $groups
 */

$groups = is_array($groups ?? null) ? $groups : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$success = \App\Core\Session::get('success');
$error = \App\Core\Session::get('error');
\App\Core\Session::forget('success');
\App\Core\Session::forget('error');

// Un groupe est public par défaut : l’absence de colonne vaut « visible ».
$publicComplete = 0;
$publicIncomplete = 0;
$hidden = 0;
foreach ($groups as $g) {
    $isPublic = !array_key_exists('show_on_public_page', $g) || (int) ($g['show_on_public_page'] ?? 0) === 1;
    if (!$isPublic) {
        $hidden++;
        continue;
    }
    if (trim((string) ($g['public_blurb'] ?? '')) !== '') {
        $publicComplete++;
    } else {
        $publicIncomplete++;
    }
}
$total = count($groups);
$pctOf = static fn (int $n): string => $total > 0 ? (string) (int) round($n / $total * 100) . '%' : '0%';
?>
<?php if ($error): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $success) ?></p>
<?php endif; ?>

<?php
$athKpis = [
    [
        'label' => 'GROUPES',
        'value' => (string) $total,
        'delta' => '',
        'tone' => '#1e4f80',
        'pct' => $total > 0 ? '100%' : '0%',
        'note' => 'dans l’organigramme',
    ],
    [
        'label' => 'VITRINE COMPLÈTE',
        'value' => (string) $publicComplete,
        'delta' => '',
        'tone' => '#0b8a5c',
        'pct' => $pctOf($publicComplete),
        'note' => 'présentation renseignée',
    ],
    [
        'label' => 'PRÉSENTATION À ÉCRIRE',
        'value' => (string) $publicIncomplete,
        'delta' => '',
        'tone' => $publicIncomplete === 0 ? '#0b8a5c' : '#c98a12',
        'pct' => $pctOf($publicIncomplete),
        'note' => 'visibles mais sans texte',
    ],
    [
        'label' => 'MASQUÉS',
        'value' => (string) $hidden,
        'delta' => '',
        'tone' => '#64748b',
        'pct' => $pctOf($hidden),
        'note' => 'hors page publique',
    ],
];
require base_path('views/partials/ath_kpis.php');
?>

<div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
    <a href="<?= $h(url('back-office/groups/create')) ?>" class="ath-btn ath-btn--solid">Créer un groupe</a>
    <a href="<?= $h(url('back-office/organisation/structure')) ?>" class="ath-btn">Hub structure</a>
</div>

<?php
$athTableTitle = 'Groupes';
$athTableCount = $total;
$athTableCols = ['GROUPE', 'CODE|m', 'PAGE PUBLIQUE|b'];
$athTableRows = [];
$athTableRowActions = [];
$athTableRowHrefs = [];
foreach ($groups as $g) {
    $id = (int) ($g['id'] ?? 0);
    $isPublic = !array_key_exists('show_on_public_page', $g) || (int) ($g['show_on_public_page'] ?? 0) === 1;
    $hasBlurb = trim((string) ($g['public_blurb'] ?? '')) !== '';

    if (!$isPublic) {
        $state = 'Masqué';
    } elseif ($hasBlurb) {
        $state = 'Visible';
    } else {
        $state = 'À compléter';
    }

    $athTableRows[] = [
        (string) ($g['name'] ?? '—'),
        (string) ($g['code'] ?? '—'),
        $state,
    ];
    $athTableRowHrefs[] = url('back-office/groups/' . $id);
    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $athTableRowActions[] = '<a href="' . $h(url('back-office/groups/' . $id)) . '" class="ath-row-action">Voir</a> '
        . '<a href="' . $h(url('back-office/groups/' . $id . '/edit')) . '" class="ath-row-action">Modifier</a>';
}
$athTableActionsLabel = 'FICHE';
$athTableFilters = [];
$athTableMinWidth = '900px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableFoot = $groups === []
    ? 'Aucun groupe enregistré.'
    : 'Un groupe visible sans présentation apparaît sur la page publique avec un encadré vide.';
require base_path('views/partials/ath_table.php');
