<?php
declare(strict_types=1);

/**
 * Référentiel doctrinal — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. La page présente le choix du
 * référentiel puis, en lecture seule, les gabarits d’ordres, les échelons et les parcours
 * de formation correspondants.
 *
 * @var string $doctrineReferential
 * @var array<string, string> $doctrineReferentialLabels
 * @var array<string, string> $doctrineReferentialDescriptions
 * @var list<array<string, mixed>> $doctrineOrderFormats
 * @var list<array<string, mixed>> $doctrineEchelons
 * @var list<array<string, mixed>> $doctrineTraining
 * @var bool $doctrineCanEdit
 */

use App\Services\Doctrine\DoctrineReferential;
use App\Services\Doctrine\EchelonCatalog;
use App\Services\Doctrine\OrderFormatCatalog;
use App\Services\Doctrine\TrainingPipelineCatalog;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$referential = DoctrineReferential::sanitize($doctrineReferential ?? null);
$labels = is_array($doctrineReferentialLabels ?? null) ? $doctrineReferentialLabels : DoctrineReferential::labels();
$descriptions = is_array($doctrineReferentialDescriptions ?? null) ? $doctrineReferentialDescriptions : DoctrineReferential::descriptions();
$formats = is_array($doctrineOrderFormats ?? null) ? $doctrineOrderFormats : [];
$echelons = is_array($doctrineEchelons ?? null) ? $doctrineEchelons : [];
$training = is_array($doctrineTraining ?? null) ? $doctrineTraining : [];
$canEdit = (bool) ($doctrineCanEdit ?? false);

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');

$orders = array_values(array_filter($formats, static fn (array $f): bool => ($f['kind'] ?? '') === OrderFormatCatalog::KIND_ORDER));
$reports = array_values(array_filter($formats, static fn (array $f): bool => ($f['kind'] ?? '') === OrderFormatCatalog::KIND_REPORT));
$stages = array_values(array_filter($training, static fn (array $t): bool => ($t['type'] ?? '') === TrainingPipelineCatalog::TYPE_STAGE));
$qualifications = array_values(array_filter($training, static fn (array $t): bool => ($t['type'] ?? '') === TrainingPipelineCatalog::TYPE_QUALIFICATION));
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<div class="ath-note">
    <p class="ath-note__title">Portée du référentiel</p>
    <p class="ath-note__text">
        Le référentiel ne filtre que ce qui vous est <strong>proposé</strong> : trames de rédaction,
        échelons et parcours. Changer de référentiel ne modifie ni ne supprime aucun document,
        rôle ou formation déjà enregistré. Les effectifs et les durées de validité sont des
        références de départ, à ajuster selon les usages de votre unité.
    </p>
</div>

<form method="post" action="<?= $h(url('back-office/doctrine/referentiel')) ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Référentiel de la communauté</span>
        <span class="ath-form__hint">Actuel : <?= $h($labels[$referential] ?? $referential) ?></span>
    </div>
    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
    <div class="ath-choice-grid">
        <?php foreach (DoctrineReferential::keys() as $key): ?>
        <label class="ath-choice">
            <input type="radio" name="referential" value="<?= $h($key) ?>"<?= $referential === $key ? ' checked' : '' ?><?= $canEdit ? '' : ' disabled' ?>>
            <span class="ath-choice__body">
                <span class="ath-choice__name"><?= $h($labels[$key] ?? $key) ?></span>
                <span class="ath-choice__desc"><?= $h($descriptions[$key] ?? '') ?></span>
            </span>
        </label>
        <?php endforeach; ?>
    </div>
    <div class="ath-form__actions">
        <?php if ($canEdit): ?>
        <button type="submit" class="ath-btn ath-btn--solid">Enregistrer le référentiel</button>
        <?php else: ?>
        <span class="ath-field__help">Consultation seule : le changement de référentiel demande le droit d’administration de la communauté.</span>
        <?php endif; ?>
    </div>
</form>

<?php
$athKpis = [
    ['label' => 'GABARITS D’ORDRE', 'value' => (string) count($orders), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'trames de rédaction'],
    ['label' => 'COMPTES RENDUS', 'value' => (string) count($reports), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'et demandes'],
    ['label' => 'ÉCHELONS', 'value' => (string) count($echelons), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'de l’équipe au régiment'],
    ['label' => 'ÉTAPES DE FORMATION', 'value' => (string) count($stages), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'parcours qualifiant'],
    ['label' => 'À ENTRETENIR', 'value' => (string) count($qualifications), 'delta' => '', 'tone' => '#c98a12', 'pct' => '100%', 'note' => 'qualifications à revalider'],
];
require base_path('views/partials/ath_kpis.php');
?>

<h2 class="ath-section-title">Ordres</h2>
<?php if ($orders === []): ?>
<div class="ath-card" style="padding:16px 18px;"><p class="ath-panel__lead" style="margin:0;">Aucun gabarit d’ordre pour ce référentiel.</p></div>
<?php else: ?>
<div class="ath-rise">
    <?php foreach ($orders as $format): ?>
    <details class="ath-disclosure">
        <summary>
            <span>
                <span class="ath-tag ath-tag--info"><?= $h(DoctrineReferential::originLabel((string) ($format['origin'] ?? ''))) ?></span>
                <?= $h((string) ($format['code'] ?? '')) ?> — <?= $h((string) ($format['label'] ?? '')) ?>
                <span class="ath-disclosure__count">(<?= OrderFormatCatalog::fieldCount($format) ?> champs)</span>
            </span>
            <span aria-hidden="true">▼</span>
        </summary>
        <div style="padding:11px 13px 13px;">
            <p class="ath-panel__lead" style="margin:0 0 4px;"><?= $h((string) ($format['purpose'] ?? '')) ?></p>
            <p class="ath-field__help" style="margin:0 0 10px;">Émis par : <?= $h((string) ($format['issued_by'] ?? '—')) ?></p>
            <?php foreach ($format['sections'] ?? [] as $section): ?>
            <p class="ath-field__label" style="margin-top:11px;"><?= $h((string) ($section['title'] ?? '')) ?></p>
            <ol style="margin:5px 0 0;padding-left:18px;font-size:11.5px;line-height:1.7;">
                <?php foreach ($section['fields'] ?? [] as $field): ?>
                <li><?= $h((string) $field) ?></li>
                <?php endforeach; ?>
            </ol>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="ath-section-title">Comptes rendus et demandes</h2>
<?php if ($reports === []): ?>
<div class="ath-card" style="padding:16px 18px;"><p class="ath-panel__lead" style="margin:0;">Aucun compte rendu pour ce référentiel.</p></div>
<?php else: ?>
<div class="ath-rise">
    <?php foreach ($reports as $format): ?>
    <details class="ath-disclosure">
        <summary>
            <span>
                <span class="ath-tag ath-tag--info"><?= $h(DoctrineReferential::originLabel((string) ($format['origin'] ?? ''))) ?></span>
                <?= $h((string) ($format['code'] ?? '')) ?> — <?= $h((string) ($format['label'] ?? '')) ?>
                <span class="ath-disclosure__count">(<?= OrderFormatCatalog::fieldCount($format) ?> champs)</span>
            </span>
            <span aria-hidden="true">▼</span>
        </summary>
        <div style="padding:11px 13px 13px;">
            <p class="ath-panel__lead" style="margin:0 0 4px;"><?= $h((string) ($format['purpose'] ?? '')) ?></p>
            <p class="ath-field__help" style="margin:0 0 10px;">Émis par : <?= $h((string) ($format['issued_by'] ?? '—')) ?></p>
            <?php foreach ($format['sections'] ?? [] as $section): ?>
            <p class="ath-field__label" style="margin-top:11px;"><?= $h((string) ($section['title'] ?? '')) ?></p>
            <ol style="margin:5px 0 0;padding-left:18px;font-size:11.5px;line-height:1.7;">
                <?php foreach ($section['fields'] ?? [] as $field): ?>
                <li><?= $h((string) $field) ?></li>
                <?php endforeach; ?>
            </ol>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="ath-section-title">Échelons de commandement</h2>

<?php
$athTableTitle = 'Articulation et effectifs de référence';
$athTableCount = count($echelons);
$athTableCols = ['ÉCHELON', 'ORIGINE|b', 'COMMANDÉ PAR', 'EFFECTIF|r', 'COMPOSITION'];
$athTableRows = [];
foreach ($echelons as $echelon) {
    $athTableRows[] = [
        (string) ($echelon['label'] ?? '—'),
        DoctrineReferential::originLabel((string) ($echelon['origin'] ?? '')),
        (string) ($echelon['commanded_by'] ?? '—'),
        EchelonCatalog::strengthLabel($echelon),
        (string) ($echelon['composition'] ?? '—'),
    ];
}
$athTableFilters = [];
$athTableMinWidth = '1180px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableRowActions = null;
$athTableFoot = $echelons === []
    ? 'Aucun échelon pour ce référentiel.'
    : 'Les effectifs sont indicatifs : ils situent l’échelon, ils ne conditionnent rien dans l’application.';
require base_path('views/partials/ath_table.php');
?>

<h2 class="ath-section-title">Fonctions attendues par échelon</h2>
<div class="ath-rise">
    <?php foreach ($echelons as $echelon): ?>
    <details class="ath-disclosure">
        <summary>
            <span>
                <span class="ath-tag ath-tag--info"><?= $h(DoctrineReferential::originLabel((string) ($echelon['origin'] ?? ''))) ?></span>
                <?= $h((string) ($echelon['label'] ?? '')) ?>
                <span class="ath-disclosure__count">(<?= count($echelon['functions'] ?? []) ?> fonctions)</span>
            </span>
            <span aria-hidden="true">▼</span>
        </summary>
        <ul class="ath-disclosure__list">
            <?php foreach ($echelon['functions'] ?? [] as $function): ?>
            <li><?= $h((string) $function) ?></li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php endforeach; ?>
</div>

<h2 class="ath-section-title">Parcours de formation</h2>

<?php
$athTableTitle = 'Étapes du parcours';
$athTableCount = count($stages);
$athTableCols = ['ÉTAPE|m', 'INTITULÉ', 'ORIGINE|b', 'PRÉREQUIS', 'OBJET'];
$athTableRows = [];
foreach ($stages as $entry) {
    $athTableRows[] = [
        (string) ($entry['code'] ?? '—'),
        (string) ($entry['label'] ?? '—'),
        DoctrineReferential::originLabel((string) ($entry['origin'] ?? '')),
        (string) ($entry['prerequisite'] ?? 'Aucun'),
        (string) ($entry['purpose'] ?? '—'),
    ];
}
$athTableMinWidth = '1320px';
$athTableFoot = $stages === []
    ? 'Aucune étape pour ce référentiel.'
    : 'Franchies une fois : elles n’ont pas d’échéance.';
require base_path('views/partials/ath_table.php');

$athTableTitle = 'Qualifications à entretenir';
$athTableCount = count($qualifications);
$athTableCols = ['QUALIFICATION|m', 'INTITULÉ', 'ORIGINE|b', 'VALIDITÉ|r', 'RECYCLAGE'];
$athTableRows = [];
foreach ($qualifications as $entry) {
    $athTableRows[] = [
        (string) ($entry['code'] ?? '—'),
        (string) ($entry['label'] ?? '—'),
        DoctrineReferential::originLabel((string) ($entry['origin'] ?? '')),
        TrainingPipelineCatalog::validityLabel($entry),
        (string) ($entry['recycling'] ?? '—'),
    ];
}
$athTableMinWidth = '1220px';
$athTableFoot = $qualifications === []
    ? 'Aucune qualification à entretenir pour ce référentiel.'
    : 'Durées indicatives : fixez les vôtres dans le référentiel des compétences.';
require base_path('views/partials/ath_table.php');
