<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $persons */
$total = count($persons);
?>
<div class="breadcrumb">
    Athena / SSE / Renseignement /
    <strong>Personnes</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Fiches terrain // Identités</div>
        <h1>Personnes identifiées</h1>
        <p>
            Fiches terrain issues du scénario. Lecture et rattachement aux dossiers —
            distinctes des dossiers membres de la communauté.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Index personnes</strong>
        Réf. ATH-SSE-PERSONNES
    </div>
</div>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Fiches visibles</div>
        <div class="metric-value"><?= $h(str_pad((string) $total, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Registre terrain</div>
    </div>
    <div class="metric">
        <div class="metric-label">Source</div>
        <div class="metric-value">ATAK</div>
        <div class="metric-detail">Terminal / terrain</div>
    </div>
    <div class="metric">
        <div class="metric-label">Usage</div>
        <div class="metric-value">RP</div>
        <div class="metric-detail">Simulation scénario</div>
    </div>
    <div class="metric">
        <div class="metric-label">Horodatage</div>
        <div class="metric-value"><?= $h(date('H:i')) ?></div>
        <div class="metric-detail">Heure locale</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">02.01</span>
            Registre des personnes
        </div>
        <div class="panel-meta">Fiches terrain // lecture</div>
    </div>

    <?php if ($persons === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">—</div>
                <strong>Aucune fiche enregistrée</strong>
                <p>Les personnes contrôlées depuis le terminal apparaîtront ici.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Identité</th>
                    <th>Statut</th>
                    <th>Alias</th>
                    <th>Enregistrée</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($persons as $p): ?>
                    <tr>
                        <td>
                            <span class="record-name"><?= $h($p['display_name'] ?? '') ?></span>
                            <span class="record-sub">Fiche terrain</span>
                        </td>
                        <td><span class="badge"><?= $h($p['status_label'] ?? '') ?></span></td>
                        <td class="record-id"><?= $h($p['alias'] ?? '—') ?></td>
                        <td class="record-id"><?= $h($p['created_at'] ?? '') ?></td>
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
