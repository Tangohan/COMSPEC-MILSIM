<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $interestCases */
/** @var array{status:string,q:string} $filters */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
?>
<div class="breadcrumb">Athena / SSE / <strong>Dossiers d’intérêt</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Pilotage // Investigation préparatoire</div>
        <h1>Dossiers d’intérêt</h1>
        <p>Qualification, collecte et consolidation des sujets d’intérêt. Une hypothèse de travail n’est jamais une identité confirmée.</p>
    </div>
    <div class="page-reference"><strong>Vue // File opérateur</strong>Réf. ATH-SSE-INTERET</div>
</div>
<div class="security-notice">
    <div class="security-notice-code">HITL</div>
    <div>
        <strong>Validation humaine obligatoire</strong>
        <span>Les propositions automatiques aident l’analyse. Elles ne valent ni identification, ni validation, ni preuve.</span>
    </div>
</div>
<form class="toolbar" method="get">
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Référence, désignation, alias">
    </div>
    <div class="toolbar-field">
        <label for="status">État de traitement</label>
        <select id="status" name="status">
            <option value="">Tous les états</option>
            <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= $h($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions">
        <button class="btn btn--ghost" type="submit">Filtrer</button>
        <?php if ($canManage): ?>
            <a class="btn" href="<?= $h(url('atak/sse/interet/nouveau')) ?>">Ouvrir un dossier d’intérêt</a>
        <?php endif; ?>
    </div>
</form>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">P.01</span> File dossiers d’intérêt</div>
        <div class="panel-meta"><?= count($interestCases) ?> résultat<?= count($interestCases) > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($interestCases === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">DI</div>
                <strong>Aucun dossier d’intérêt</strong>
                <p>Ouvrez un dossier préparatoire à partir d’un signalement, sans conclure prématurément à une identité.</p>
                <?php if ($canManage): ?>
                    <a class="btn" href="<?= $h(url('atak/sse/interet/nouveau')) ?>">Ouvrir un dossier d’intérêt</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Référence</th>
                    <th>Sujet d’intérêt</th>
                    <th>État</th>
                    <th>Confiance</th>
                    <th>Priorité</th>
                    <th>Dernière activité</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($interestCases as $case): ?>
                    <tr>
                        <td class="record-id"><?= $h($case['reference_code'] ?? '') ?></td>
                        <td>
                            <span class="record-name"><?= $h($case['temporary_designation'] ?? '') ?></span>
                            <span class="record-sub"><?= $h(($case['suspected_alias'] ?? '') ?: 'Alias non renseigné') ?></span>
                        </td>
                        <td><span class="badge"><?= $h($case['status_label'] ?? '') ?></span></td>
                        <td><?= $h($case['confidence_label'] ?? '') ?></td>
                        <td>
                            <span class="badge <?= ($case['interest_level'] ?? '') === 'critique' ? 'badge--red' : (($case['interest_level'] ?? '') === 'prioritaire' ? 'badge--amber' : '') ?>">
                                <?= $h($case['interest_label'] ?? '') ?>
                            </span>
                        </td>
                        <td class="record-id"><?= $h(substr((string) ($case['updated_at'] ?? ''), 0, 16)) ?></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/interet/' . (int) ($case['id'] ?? 0))) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
