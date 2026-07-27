<?php
declare(strict_types=1);

/**
 * Équipes tactiques — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office.
 *
 * @var list<array<string, mixed>> $teams
 */

$teams = is_array($teams ?? null) ? $teams : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$success = \App\Core\Session::get('success');
$error = \App\Core\Session::get('error');
\App\Core\Session::forget('success');
\App\Core\Session::forget('error');
?>
<?php if ($error): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $success) ?></p>
<?php endif; ?>

<div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
    <a href="<?= $h(url('back-office/teams/create')) ?>" class="ath-btn ath-btn--solid">Créer une équipe</a>
    <a href="<?= $h(url('back-office/groups')) ?>" class="ath-btn">Groupes</a>
</div>

<?php
$athTableTitle = 'Équipes';
$athTableCount = count($teams);
$athTableCols = ['ÉQUIPE', 'CODE|m', 'IDENTIFIANT|m'];
$athTableRows = [];
$athTableRowActions = [];
$athTableRowHrefs = [];
foreach ($teams as $t) {
    $id = (int) ($t['id'] ?? 0);
    $athTableRows[] = [
        (string) ($t['name'] ?? '—'),
        (string) ($t['code'] ?? '—'),
        (string) ($t['slug'] ?? '—'),
    ];
    $athTableRowHrefs[] = url('back-office/teams/' . $id);
    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $athTableRowActions[] = '<a href="' . $h(url('back-office/teams/' . $id)) . '" class="ath-row-action">Voir</a> '
        . '<a href="' . $h(url('back-office/teams/' . $id . '/edit')) . '" class="ath-row-action">Modifier</a>';
}
$athTableActionsLabel = 'FICHE';
$athTableFilters = [];
$athTableMinWidth = '860px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableFoot = $teams === []
    ? 'Aucune équipe enregistrée.'
    : 'Le code opérationnel est celui utilisé sur le terrain ; l’identifiant sert aux liens du portail.';
require base_path('views/partials/ath_table.php');
