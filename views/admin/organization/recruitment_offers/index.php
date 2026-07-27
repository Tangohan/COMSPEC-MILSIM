<?php
declare(strict_types=1);

/**
 * Offres de recrutement publiées — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. Le registre est un tableau ; la
 * publication est traitée à part, car chaque brouillon porte deux options de diffusion
 * qui ne tiennent pas dans une cellule d’action.
 *
 * @var list<array<string,mixed>> $openings
 * @var string $statusFilter
 * @var array<string,string> $statusLabels
 * @var string $publicOffersVitrineUrl
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$openings = is_array($openings ?? null) ? $openings : [];
$statusFilter = (string) ($statusFilter ?? 'all');
$statusLabels = is_array($statusLabels ?? null) ? $statusLabels : [];
$publicOffersVitrineUrl = trim((string) ($publicOffersVitrineUrl ?? ''));
$hasVitrinePreview = $publicOffersVitrineUrl !== '';

$baseUrl = url('back-office/recruitment/offers');
$csrf = \App\Core\Csrf::token();

/** Un statut se lit comme un état : on le fait passer par la tonalité du tableau. */
$stateForStatus = static function (string $status) use ($statusLabels): string {
    return match ($status) {
        'published' => 'Publiée',
        'draft' => 'Brouillon',
        'closed' => 'Clos',
        'archived' => 'Archivée',
        default => $statusLabels[$status] ?? ($status !== '' ? ucfirst($status) : 'Autre état'),
    };
};

$drafts = [];
$publishedCount = 0;
$closedCount = 0;
foreach ($openings as $o) {
    $status = (string) ($o['status'] ?? '');
    if ($status === 'draft') {
        $drafts[] = $o;
    } elseif ($status === 'published') {
        $publishedCount++;
    } elseif ($status === 'closed' || $status === 'archived') {
        $closedCount++;
    }
}
$total = count($openings);
$pctOf = static fn (int $n): string => $total > 0 ? (string) (int) round($n / $total * 100) . '%' : '0%';

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<div class="ath-form__actions" style="border-top:0;margin:0 0 16px;padding-top:0;">
    <a href="<?= $h($baseUrl . '/create') ?>" class="ath-btn ath-btn--solid">Nouvelle offre</a>
    <a href="<?= $h(url('back-office/recruitment/reference-format')) ?>" class="ath-btn">Format des références</a>
    <?php if ($hasVitrinePreview): ?>
    <a href="<?= $h($publicOffersVitrineUrl) ?>" class="ath-btn" target="_blank" rel="noopener noreferrer">Ouvrir la vitrine ↗</a>
    <?php endif; ?>
</div>

<?php
$athKpis = [
    ['label' => 'OFFRES', 'value' => (string) $total, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $total > 0 ? '100%' : '0%', 'note' => 'toutes situations'],
    ['label' => 'PUBLIÉES', 'value' => (string) $publishedCount, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $pctOf($publishedCount), 'note' => 'visibles sur la vitrine'],
    ['label' => 'BROUILLONS', 'value' => (string) count($drafts), 'delta' => '', 'tone' => count($drafts) === 0 ? '#0b8a5c' : '#c98a12', 'pct' => $pctOf(count($drafts)), 'note' => 'en attente de publication'],
    ['label' => 'CLOSES', 'value' => (string) $closedCount, 'delta' => '', 'tone' => '#64748b', 'pct' => $pctOf($closedCount), 'note' => 'retirées de la vitrine'],
];
require base_path('views/partials/ath_kpis.php');
?>

<form method="get" action="<?= $h($baseUrl) ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Filtrer le registre</span>
        <span class="ath-form__hint">Les offres publiées apparaissent sur la vitrine de votre communauté.</span>
    </div>
    <div class="ath-form__grid">
        <label class="ath-field">
            <span class="ath-field__label">Statut</span>
            <select name="status" class="ath-field__select">
                <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Tous les statuts</option>
                <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= $h((string) $key) ?>"<?= $statusFilter === (string) $key ? ' selected' : '' ?>><?= $h((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn">Appliquer le filtre</button>
    </div>
</form>

<?php
$athTableTitle = 'Registre des offres';
$athTableCount = $total;
$athTableCols = ['RÉFÉRENCE|m', 'INTITULÉ', 'UNITÉ', 'ÉTAT|b'];
$athTableRows = [];
$athTableRowActions = [];
$athTableRowHrefs = [];
foreach ($openings as $o) {
    $offerId = (int) ($o['id'] ?? 0);
    $status = (string) ($o['status'] ?? '');
    $editUrl = $baseUrl . '/' . $offerId . '/edit';
    $athTableRows[] = [
        (string) ($o['reference_public'] ?? '—'),
        (string) ($o['title'] ?? '—'),
        (string) ($o['unit_name'] ?? '—'),
        $stateForStatus($status),
    ];
    $athTableRowHrefs[] = $editUrl;

    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    $action = '<a href="' . $h($editUrl) . '" class="ath-row-action">Modifier</a>';
    if ($status === 'published') {
        $action .= ' <form method="post" action="' . $h($baseUrl . '/' . $offerId . '/close') . '"'
            . ' onsubmit="return confirm(\'Clôturer cette offre ? Elle disparaîtra de la vitrine publique.\');">'
            . '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
            . '<button type="submit" class="ath-row-action ath-row-action--danger">Clôturer</button>'
            . '</form>';
    } elseif ($status === 'draft') {
        $action .= ' <button type="button" class="ath-row-action" disabled>À publier ci-dessous</button>';
    }
    $athTableRowActions[] = $action;
}
$athTableActionsLabel = 'ACTIONS';
$athTableFilters = [];
$athTableMinWidth = '1120px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableFoot = $openings === []
    ? 'Aucune offre pour ce filtre.'
    : 'Cliquez une ligne pour ouvrir l’offre. Seules les offres publiées apparaissent sur la vitrine.';
require base_path('views/partials/ath_table.php');
?>

<?php if ($drafts !== []): ?>
<h2 class="ath-section-title">Publication des brouillons</h2>
<div class="ath-stack">
    <?php foreach ($drafts as $draft): ?>
        <?php $draftId = (int) ($draft['id'] ?? 0); ?>
    <article class="ath-item ath-rise">
        <div class="ath-item__head">
            <div style="min-width:0;">
                <p class="ath-item__name"><?= $h((string) ($draft['title'] ?? 'Offre sans titre')) ?></p>
                <p class="ath-item__meta">
                    Référence <span class="ath-mono"><?= $h((string) ($draft['reference_public'] ?? '—')) ?></span>
                    · <?= $h((string) ($draft['unit_name'] ?? 'Unité non précisée')) ?>
                </p>
            </div>
            <span class="ath-tag ath-tag--warn">Brouillon</span>
        </div>
        <form method="post" action="<?= $h($baseUrl . '/' . $draftId . '/publish') ?>" style="margin-top:12px;">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <span class="ath-field__label">Diffusion à la publication</span>
            <div class="ath-check-grid" style="margin-top:7px;">
                <label class="ath-check">
                    <input type="checkbox" name="forum_annonce_generale" value="1">
                    <span>Annonce dans le forum général — visible par toute la communauté</span>
                </label>
                <label class="ath-check">
                    <input type="checkbox" name="forum_annonce_organisation" value="1">
                    <span>Annonce dans l’espace de l’organisation — membres et encadrement</span>
                </label>
            </div>
            <div class="ath-item__actions">
                <button type="submit" class="ath-btn ath-btn--solid">Publier l’offre</button>
                <a href="<?= $h($baseUrl . '/' . $draftId . '/edit') ?>" class="ath-btn">Relire avant publication</a>
            </div>
        </form>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($hasVitrinePreview): ?>
<h2 class="ath-section-title">Aperçu de la vitrine</h2>
<div class="ath-card ath-rise" style="padding:0;overflow:hidden;">
    <iframe
        title="Aperçu de la vitrine publique des offres"
        src="<?= $h($publicOffersVitrineUrl) ?>"
        style="width:100%;height:640px;border:0;background:#fff;display:block;"
        loading="lazy"
        referrerpolicy="same-origin"
    ></iframe>
</div>
<p class="ath-field__help" style="margin-top:8px;">
    L’aperçu reprend la page publique de votre communauté, positionnée sur le tableau des offres.
    Les modifications publiées s’y reflètent après rechargement.
</p>
<?php endif; ?>
