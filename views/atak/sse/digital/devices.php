<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $devices */
/** @var array<string,string> $statuses */
/** @var array<string,string> $types */
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique')) ?>">Exploitation numérique</a> / <strong>Supports</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire // Supports</div>
        <h1>Supports numériques</h1>
        <p>Fiches des matériels saisis : identification, état technique et traçabilité.</p>
    </div>
    <div class="page-reference"><strong>Vue // Registre</strong> Réf. ATH-SSE-LABNUM-SUP</div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/exploitation-numerique/supports')) ?>">
    <div class="toolbar-field">
        <label for="status">Statut</label>
        <select id="status" name="status">
            <option value="">Tous les statuts</option>
            <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="device_type">Type</label>
        <select id="device_type" name="device_type">
            <option value="">Tous les types</option>
            <?php foreach ($types as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['device_type'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Référence, modèle…">
    </div>
    <div class="toolbar-actions">
        <button class="btn" type="submit">Appliquer</button>
        <?php if (!empty($canManage)): ?>
            <a class="btn" href="<?= $h(url('atak/sse/exploitation-numerique/supports/nouveau')) ?>">Enregistrer</a>
        <?php endif; ?>
    </div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02.01</span> Registre des supports</div>
        <div class="panel-meta"><?= count($devices) ?> fiche(s)</div>
    </div>
    <?php if ($devices === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun support</strong><p>Enregistrez une saisie pour démarrer une exploitation.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Référence</th><th>Type</th><th>Modèle</th><th>Mission</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($devices as $d): ?>
                    <tr>
                        <td><?= $h($d['reference_code'] ?? '') ?></td>
                        <td><?= $h(trim((string) ($d['device_type_label'] ?? '')) ?: 'Inconnu') ?></td>
                        <td><?= $h(trim(($d['manufacturer'] ?? '') . ' ' . ($d['model'] ?? '')) ?: '—') ?></td>
                        <td><?= $h($d['mission_label'] ?? '—') ?></td>
                        <td><?= $h($d['status_label'] ?? '') ?></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/exploitation-numerique/supports/' . (int) $d['id'])) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
