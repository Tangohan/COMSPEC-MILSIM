<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $device */
/** @var list<array<string,mixed>> $acquisitions */
/** @var list<array<string,mixed>> $artifacts */
/** @var list<array<string,mixed>> $findings */
/** @var array<string,string> $methods */
/** @var array<string,string> $profiles */
$id = (int) ($device['id'] ?? 0);
$type = (string) ($device['device_type'] ?? '');
$isPhone = in_array($type, ['telephone', 'tablette'], true);
$isComputer = in_array($type, ['ordinateur', 'disque_dur', 'ssd', 'image_disque'], true);
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports')) ?>">Supports</a> / <strong><?= $h($device['reference_code'] ?? '') ?></strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline"><?= $h($device['device_type_label'] ?? '') ?> // <?= $h($device['status_label'] ?? '') ?></div>
        <h1><?= $h(trim(($device['manufacturer'] ?? '') . ' ' . ($device['model'] ?? '')) ?: ($device['reference_code'] ?? 'Support')) ?></h1>
        <p><?= $h($device['reference_code'] ?? '') ?><?php if (!empty($device['mission_label'])): ?> · <?= $h($device['mission_label']) ?><?php endif; ?></p>
    </div>
    <div class="page-reference">
        <strong><?= $h($device['status_label'] ?? '') ?></strong>
        <?php if ($isPhone): ?><div><a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports/' . $id . '/telephone')) ?>">Vue extraction mobile</a></div><?php endif; ?>
        <?php if ($isComputer): ?><div><a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports/' . $id . '/ordinateur')) ?>">Vue ordinateur</a></div><?php endif; ?>
    </div>
</div>

<div class="metrics-grid">
    <div class="metric"><div class="metric-label">État</div><div class="metric-value" style="font-size:1rem"><?= $h($device['power_state_label'] ?? '—') ?></div><div class="metric-detail"><?= $h($device['locked_label'] ?? '') ?></div></div>
    <div class="metric"><div class="metric-label">Acquisitions</div><div class="metric-value"><?= count($acquisitions) ?></div><div class="metric-detail">Copies liées</div></div>
    <div class="metric"><div class="metric-label">Artefacts</div><div class="metric-value"><?= count($artifacts) ?></div><div class="metric-detail">Extrait visibles</div></div>
    <div class="metric"><div class="metric-label">Signaux</div><div class="metric-value"><?= count($findings) ?></div><div class="metric-detail">Propositions</div></div>
</div>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02.10</span> Identification</div></div>
    <div class="panel-body" style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div><span class="muted">Série</span><br><strong><?= $h($device['serial_number'] ?? '—') ?></strong></div>
        <div><span class="muted">Capacité</span><br><strong><?= $h($device['capacity_label'] ?? '—') ?></strong></div>
        <div><span class="muted">Lieu</span><br><strong><?= $h($device['discovery_place'] ?? '—') ?></strong></div>
        <div><span class="muted">Opérateur</span><br><strong><?= $h($device['seized_by_label'] ?? '—') ?></strong></div>
        <div><span class="muted">Profil</span><br><strong><?= $h($device['data_profile_label'] ?? '—') ?></strong></div>
        <div><span class="muted">Système</span><br><strong><?= $h($device['presumed_os'] ?? '—') ?></strong></div>
    </div>
    <?php if (!empty($device['observations'])): ?>
        <div class="panel-body"><p><?= nl2br($h($device['observations'])) ?></p></div>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02.11</span> Lancer une acquisition simulée</div></div>
    <form method="post" action="<?= $h(url('atak/sse/exploitation-numerique/supports/' . $id . '/acquisitions')) ?>" class="panel-body" style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
        <?= \App\Core\Csrf::field() ?>
        <label>Méthode
            <select name="method">
                <?php foreach ($methods as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= $k === 'logical' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Profil de données
            <select name="data_profile">
                <option value="">Conserver / automatique</option>
                <?php foreach ($profiles as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= ($device['data_profile'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Opérateur <input name="operator_label" type="text" value="<?= $h($device['seized_by_label'] ?? '') ?>"></label>
        <div class="toolbar-actions" style="align-self:end"><button class="btn" type="submit">Procéder à l’acquisition</button></div>
    </form>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02.12</span> Acquisitions</div></div>
    <?php if ($acquisitions === []): ?>
        <div class="panel-body"><p class="muted">Aucune acquisition pour ce support.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Référence</th><th>Méthode</th><th>Statut</th><th>Volume</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($acquisitions as $a): ?>
                <tr>
                    <td><?= $h($a['reference_code'] ?? '') ?></td>
                    <td><?= $h($a['method_label'] ?? '') ?></td>
                    <td><?= $h($a['status_label'] ?? '') ?></td>
                    <td><?= $h($a['volume_label'] ?? '—') ?></td>
                    <td><a class="btn-open" href="<?= $h(url('atak/sse/exploitation-numerique/acquisitions/' . (int) $a['id'])) ?>">Détail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02.13</span> Signaux analytiques</div></div>
    <div class="panel-body">
        <?php if ($findings === []): ?>
            <p class="muted">Aucun signal. Lancez une acquisition pour produire des propositions.</p>
        <?php else: ?>
            <?php foreach ($findings as $f): ?>
                <div class="iw-alert is-moderee" style="margin-bottom:8px">
                    <strong><?= $h($f['title'] ?? '') ?></strong>
                    <p><?= $h($f['detail'] ?? '') ?></p>
                    <em><?= $h($f['status_label'] ?? '') ?> · confiance <?= $h($f['confidence_label'] ?? '') ?></em>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
