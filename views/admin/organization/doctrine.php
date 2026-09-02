<?php
declare(strict_types=1);

/**
 * Doctrine & SOP versionnées — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office ; cette vue produit le
 * formulaire de création, les indicateurs, puis un tableau des versions publiées.
 *
 * @var list<array<string, mixed>> $doctrine_documents
 */

$documents = is_array($doctrine_documents ?? null) ? $doctrine_documents : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$typeLabel = static function (string $t): string {
    return match ($t) {
        'sop' => 'SOP',
        'checklist' => 'Checklist',
        'report_format' => 'Format de rapport',
        default => $t !== '' ? ucfirst(str_replace('_', ' ', $t)) : 'Document',
    };
};

$statusLabel = static function (string $s): string {
    return match ($s) {
        'active', 'published' => 'Active',
        'draft' => 'Brouillon',
        'archived' => 'Archivée',
        'superseded' => 'Remplacée',
        default => $s !== '' ? ucfirst($s) : 'Brouillon',
    };
};

$fmtDate = static function (mixed $raw): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return '—';
    }
    $t = strtotime($s);

    return $t ? date('d/m/Y', $t) : '—';
};

// Aplatissement document → versions, pour un tableau unique lisible.
$versionRows = [];
$activeCount = 0;
$draftCount = 0;
$ackTotal = 0;
foreach ($documents as $document) {
    $versions = is_array($document['versions'] ?? null) ? $document['versions'] : [];
    $currentId = (int) ($document['current_version_id'] ?? 0);
    foreach ($versions as $version) {
        $vid = (int) ($version['id'] ?? 0);
        $isActive = $currentId === $vid && $vid > 0;
        $status = (string) ($version['status'] ?? 'draft');
        if ($isActive) {
            $activeCount++;
        } elseif ($status === 'draft') {
            $draftCount++;
        }
        $ackTotal += (int) ($version['ack_count'] ?? 0);
        $versionRows[] = [
            'document_title' => (string) ($document['title'] ?? ''),
            'document_type' => (string) ($document['document_type'] ?? 'sop'),
            'version_id' => $vid,
            'version_label' => (string) ($version['version_label'] ?? ''),
            'status' => $status,
            'ack_count' => (int) ($version['ack_count'] ?? 0),
            'effective_at' => $version['effective_at'] ?? null,
            'is_active' => $isActive,
        ];
    }
}
$totalVersions = count($versionRows);
$pctOf = static fn (int $n): string => $totalVersions > 0 ? (string) (int) round($n / $totalVersions * 100) . '%' : '0%';

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<?php
$athKpis = [
    [
        'label' => 'DOCUMENTS',
        'value' => (string) count($documents),
        'delta' => '',
        'tone' => '#1e4f80',
        'pct' => count($documents) > 0 ? '100%' : '0%',
        'note' => 'SOP, checklists, formats',
    ],
    [
        'label' => 'VERSIONS ACTIVES',
        'value' => (string) $activeCount,
        'delta' => '',
        'tone' => $activeCount > 0 ? '#0b8a5c' : '#c98a12',
        'pct' => $pctOf($activeCount),
        'note' => 'en vigueur aujourd’hui',
    ],
    [
        'label' => 'BROUILLONS',
        'value' => (string) $draftCount,
        'delta' => '',
        'tone' => $draftCount === 0 ? '#0b8a5c' : '#c98a12',
        'pct' => $pctOf($draftCount),
        'note' => 'en attente d’activation',
    ],
    [
        'label' => 'ACCUSÉS DE LECTURE',
        'value' => (string) $ackTotal,
        'delta' => '',
        'tone' => '#0b8a5c',
        'pct' => $ackTotal > 0 ? '100%' : '0%',
        'note' => 'toutes versions confondues',
    ],
];
require base_path('views/partials/ath_kpis.php');
?>

<form method="post" action="<?= $h(url('back-office/doctrine')) ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Nouveau document doctrine</span>
        <span class="ath-form__hint">La première version est créée en brouillon : elle n’entre en vigueur qu’après activation.</span>
    </div>
    <?= \App\Core\Csrf::field() ?>
    <div class="ath-form__grid">
        <label class="ath-field">
            <span class="ath-field__label">Titre</span>
            <input type="text" name="title" maxlength="180" required class="ath-field__input">
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Type</span>
            <select name="document_type" class="ath-field__select">
                <option value="sop">SOP</option>
                <option value="checklist">Checklist</option>
                <option value="report_format">Format de rapport</option>
            </select>
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Date d’effet</span>
            <input type="date" name="effective_at" class="ath-field__input">
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Version</span>
            <input type="text" name="version_label" value="1.0.0" maxlength="20" class="ath-field__input">
            <span class="ath-field__help">Format libre, par exemple 1.0.0 ou 2026-A.</span>
        </label>
    </div>
    <div class="ath-form__grid ath-form__grid--wide writing-assistant-layout" style="margin-top:14px;">
        <label class="ath-field">
            <span class="ath-field__label">Corps du document</span>
            <textarea id="doctrine-content-markdown" name="content_markdown" rows="12" required class="ath-field__textarea" placeholder="Objet&#10;&#10;Procédure&#10;- Étape 1&#10;- Étape 2"></textarea>
            <span class="ath-field__help">Rédigez le texte. L’assistant à droite insère une formule à l’endroit du curseur.</span>
        </label>
        <?php
        $assistantTarget = 'doctrine-content-markdown';
        $assistantInsertMode = 'markdown';
        $assistantLocked = false;
        require base_path('views/partials/writing_assistant.php');
        ?>
    </div>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid">Créer le document</button>
    </div>
</form>

<?php
$csrf = \App\Core\Csrf::token();

$athTableTitle = 'Versions publiées';
$athTableCount = $totalVersions;
$athTableCols = ['DOCUMENT', 'TYPE', 'VERSION|m', 'DATE D’EFFET|m', 'ACCUSÉS|r', 'ÉTAT|b'];
$athTableRows = [];
$athTableRowActions = [];
foreach ($versionRows as $row) {
    $athTableRows[] = [
        $row['document_title'] !== '' ? $row['document_title'] : '—',
        $typeLabel($row['document_type']),
        $row['version_label'] !== '' ? $row['version_label'] : '—',
        $fmtDate($row['effective_at']),
        (string) $row['ack_count'],
        $row['is_active'] ? 'Active' : $statusLabel($row['status']),
    ];

    // Balisage d’action construit ici, échappements compris (cf. contrat de ath_table.php).
    if ($row['is_active']) {
        $athTableRowActions[] = '<button type="button" class="ath-row-action" disabled>En vigueur</button>';
    } else {
        $activateUrl = url('back-office/doctrine/versions/' . $row['version_id'] . '/activate');
        $athTableRowActions[] = '<form method="post" action="' . $h($activateUrl) . '"'
            . ' onsubmit="return confirm(\'Activer cette version ? Elle remplacera la version en vigueur pour toute la communauté.\');">'
            . '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
            . '<button type="submit" class="ath-row-action ath-row-action--accent">Activer</button>'
            . '</form>';
    }
}
$athTableActionsLabel = 'MISE EN VIGUEUR';
$athTableFilters = [];
$athTableMinWidth = '1120px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableFoot = $versionRows === []
    ? 'Aucun document doctrine pour le moment : créez-en un avec le formulaire ci-dessus.'
    : 'Une seule version est en vigueur par document ; les accusés de lecture sont comptés par version.';
require base_path('views/partials/ath_table.php');
