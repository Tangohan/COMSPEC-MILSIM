<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $groups */
/** @var array<string,mixed> $filters */
/** @var array<string,string> $statuses */
$groups = is_array($groups ?? null) ? $groups : [];
$filters = is_array($filters ?? null) ? $filters : [];
$statuses = is_array($statuses ?? null) ? $statuses : [];
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique')) ?>">Exploitation numérique</a> / <strong>À exploiter</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire // File d’exploitation</div>
        <h1>Renseignement à exploiter</h1>
        <p>
            Voici les contenus scénarisés remontés depuis un support de mission, ou ajoutés par le chef de mission.
            Un paquet n’est pas une preuve : un fragment ou un élément douteux doit être corroboré
            avant d’entrer dans un dossier.
        </p>
    </div>
    <div class="page-reference"><strong>File</strong> ATH-SSE-LABNUM-DOMEX</div>
</div>
<form class="toolbar" method="get">
    <div class="toolbar-field">
        <label for="status">État</label>
        <select id="status" name="status">
            <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Filtrer</button></div>
</form>

<?php if ($groups === []): ?>
<section class="panel">
    <div class="empty-state">
        <div class="empty-state-inner">
            <div class="empty-symbol">DOM</div>
            <strong>Rien dans cette file</strong>
            <p>Les paquets apparaîtront lorsqu’un support de mission enverra un renseignement, lorsqu’un chef de mission en aura préparé un, ou lorsqu’il en aura ajouté un pendant la partie.</p>
        </div>
    </div>
</section>
<?php else: ?>
    <?php foreach ($groups as $group): ?>
        <?php
        $packets = is_array($group['packets'] ?? null) ? $group['packets'] : [];
        $decoy = 0;
        $frag = 0;
        foreach ($packets as $p) {
            if (!empty($p['is_decoy'])) {
                $decoy++;
            }
            if (!empty($p['is_fragment'])) {
                $frag++;
            }
        }
        $note = \App\Support\SseDomexContract::qualityNote($decoy, $frag, count($packets));
        ?>
        <section class="panel" style="margin-bottom:12px">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="panel-index">SUP</span>
                    <?= $h($group['support_label'] ?? 'Support') ?>
                </div>
                <div class="panel-meta"><?= $h($note) ?></div>
            </div>
            <div class="panel-body" style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                <div><span class="muted">Type</span><br><strong><?= $h($group['device_type_label'] ?: '—') ?></strong></div>
                <div><span class="muted">Origine</span><br><strong><?= $h($group['origin_label'] ?: '—') ?></strong></div>
                <div><span class="muted">Collecteur</span><br><strong><?= $h($group['collector_label'] ?: '—') ?></strong></div>
                <div><span class="muted">Lieu</span><br><strong><?= $h($group['grid_reference'] ?: '—') ?></strong></div>
                <?php if (!empty($group['owner_label'])): ?>
                    <div><span class="muted">Propriétaire apparent</span><br><strong><?= $h($group['owner_label']) ?></strong></div>
                <?php endif; ?>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Type</th><th>Contenu</th><th>Qualité</th><th>Confiance</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($packets as $p): ?>
                        <tr>
                            <td><?= $h($p['packet_type_label'] ?? '') ?><?php if (!empty($p['on_map'])): ?><br><span class="muted">Sur la carte</span><?php endif; ?></td>
                            <td><?= $h(mb_substr((string) ($p['body_text'] ?? ''), 0, 120)) ?><?= mb_strlen((string) ($p['body_text'] ?? '')) > 120 ? '…' : '' ?></td>
                            <td><?= $h($p['quality_label'] ?? '') ?></td>
                            <td><?= $h($p['confidence_label'] ?? '') ?></td>
                            <td><a class="btn-open" href="<?= $h(url('atak/sse/exploitation-numerique/a-exploiter/' . (int) ($p['id'] ?? 0))) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ((int) ($group['device_id'] ?? 0) > 0): ?>
                <div class="panel-body">
                    <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports/' . (int) $group['device_id'])) ?>">Voir la fiche du support</a>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
