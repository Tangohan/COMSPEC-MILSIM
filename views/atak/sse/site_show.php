<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $site */
/** @var string $fiveLine */
/** @var array<string,string> $seizureCategories */
$rooms = is_array($site['rooms'] ?? null) ? $site['rooms'] : [];
$seizures = is_array($site['seizures'] ?? null) ? $site['seizures'] : [];
$closed = ($site['status'] ?? '') === 'cloture';
$checked = count(array_filter($rooms, static fn (array $r): bool => !empty($r['checked'])));
$pctRooms = $rooms !== [] ? (int) round(($checked / count($rooms)) * 100) : 0;
$pct = isset($site['exploitation_pct']) ? (int) $site['exploitation_pct'] : $pctRooms;
$zoneLabels = [
    'ROOM' => 'Pièce',
    'CACHE' => 'Cache',
    'COLLECTION_POINT' => 'Point de collecte',
    'ENTRY' => 'Accès',
    'EXTERIOR' => 'Extérieur',
    'VEHICLE' => 'Véhicule',
];
$custodyLabels = [
    'OBSERVED' => 'Observé',
    'MARKED' => 'Marqué',
    'COLLECTED' => 'Collecté',
    'PACKAGED' => 'Conditionné',
    'SEALED' => 'Scellé',
    'TRANSFERRED' => 'Transmis',
    'EXPLOITED' => 'Exploité',
];
$custodyNext = [
    'OBSERVED' => 'MARKED',
    'MARKED' => 'COLLECTED',
    'COLLECTED' => 'PACKAGED',
    'PACKAGED' => 'SEALED',
    'SEALED' => 'TRANSFERRED',
    'TRANSFERRED' => 'EXPLOITED',
    'EXPLOITED' => 'EXPLOITED',
];

$byCategory = [];
foreach ($seizures as $s) {
    $lbl = (string) ($s['category_label'] ?? 'Autre');
    $byCategory[$lbl] = ($byCategory[$lbl] ?? 0) + (int) ($s['quantity'] ?? 1);
}
$roomLabels = [];
foreach ($rooms as $r) {
    $roomLabels[(int) ($r['id'] ?? 0)] = (string) ($r['label'] ?? '');
}
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/sites')) ?>">Sites</a> /
    <strong><?= $h($site['reference_code'] ?? '') ?></strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">
            Exploitation // <?= $h($site['site_type_label'] ?? '') ?>
        </div>
        <h1><?= $h($site['name'] ?? '') ?></h1>
        <p>
            <?= $h($site['reference_code'] ?? '') ?>
            <?php if (!empty($site['grid_reference'])): ?>
                · grille <?= $h($site['grid_reference']) ?>
            <?php endif; ?>
            <?php if (!empty($site['team_label'])): ?>
                · équipe <?= $h($site['team_label']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-reference">
        <strong><?= $h($site['status_label'] ?? '') ?></strong>
        <?php if (!empty($site['closed_at'])): ?>
            Clôturé le <?= $h(substr((string) $site['closed_at'], 0, 16)) ?>
        <?php else: ?>
            Ouvert par <?= $h($site['submitter_callsign'] ?? 'terrain') ?>
        <?php endif; ?>
    </div>
</div>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Pièces fouillées</div>
        <div class="metric-value"><?= (int) $checked ?>/<?= count($rooms) ?></div>
        <div class="metric-detail"><?= (int) $pctRooms ?> % de la checklist</div>
    </div>
    <div class="metric">
        <div class="metric-label">Exploitation</div>
        <div class="metric-value"><?= (int) $pct ?> %</div>
        <div class="metric-detail">Progression pondérée</div>
    </div>
    <div class="metric">
        <div class="metric-label">Saisies</div>
        <div class="metric-value"><?= $h(str_pad((string) count($seizures), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Objets versés</div>
    </div>
    <div class="metric">
        <div class="metric-label">Catégories</div>
        <div class="metric-value"><?= count($byCategory) ?></div>
        <div class="metric-detail">Natures distinctes</div>
    </div>
    <div class="metric">
        <div class="metric-label">Ouvert le</div>
        <div class="metric-value"><?= $h(substr((string) ($site['created_at'] ?? ''), 11, 5)) ?></div>
        <div class="metric-detail"><?= $h(substr((string) ($site['created_at'] ?? ''), 0, 10)) ?></div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">03.02</span>
            Checklist de fouille
        </div>
        <div class="panel-meta"><?= (int) $checked ?> sur <?= count($rooms) ?></div>
    </div>

    <?php if ($rooms === []): ?>
        <div class="panel-body"><p class="muted">Aucune pièce enregistrée pour ce site.</p></div>
    <?php else: ?>
        <ul class="sse-room-list">
            <?php foreach ($rooms as $r): ?>
                <li class="<?= !empty($r['checked']) ? 'is-checked' : '' ?>">
                    <span class="sse-room-mark" aria-hidden="true"><?= !empty($r['checked']) ? '✓' : '·' ?></span>
                    <span class="sse-room-label"><?= $h($r['label'] ?? '') ?></span>
                    <?php
                    $zt = strtoupper((string) ($r['zone_type'] ?? 'ROOM'));
                    $zl = $zoneLabels[$zt] ?? 'Pièce';
                    ?>
                    <span class="badge badge--gray"><?= $h($zl) ?></span>
                    <?php if (isset($r['exploitation_pct'])): ?>
                        <span class="sse-muted"><?= (int) $r['exploitation_pct'] ?> %</span>
                    <?php endif; ?>
                    <?php if (!empty($r['notes'])): ?>
                        <span class="sse-muted"><?= $h($r['notes']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($canManage) && !$closed): ?>
                        <form method="post"
                              action="<?= $h(url('atak/sse/sites/' . (int) ($site['id'] ?? 0) . '/pieces/' . (int) ($r['id'] ?? 0))) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="checked" value="<?= !empty($r['checked']) ? '0' : '1' ?>">
                            <button class="btn btn--ghost btn--sm" type="submit">
                                <?= !empty($r['checked']) ? 'Remettre en attente' : 'Marquer fouillée' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">03.03</span>
            Saisies versées
        </div>
        <div class="panel-meta">Procès-verbal de saisie</div>
    </div>

    <?php if ($seizures === []): ?>
        <div class="panel-body">
            <p class="muted">
                Aucune saisie. Les objets versés depuis le terrain apparaîtront ici,
                rattachés à la pièce où ils ont été trouvés.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Nature</th>
                    <th>Désignation</th>
                    <th>Quantité</th>
                    <th>Pièce</th>
                    <th>Possession</th>
                    <th>Versée le</th>
                    <?php if (!empty($canManage) && !$closed): ?>
                        <th></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($seizures as $s): ?>
                    <?php
                    $cs = strtoupper((string) ($s['custody_state'] ?? 'OBSERVED'));
                    $next = $custodyNext[$cs] ?? 'COLLECTED';
                    ?>
                    <tr>
                        <td><span class="badge"><?= $h($s['category_label'] ?? '') ?></span></td>
                        <td>
                            <span class="record-name"><?= $h($s['label'] ?? '') ?></span>
                            <?php if (!empty($s['notes'])): ?>
                                <span class="record-sub"><?= $h($s['notes']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($s['seal_code'])): ?>
                                <span class="record-sub">Scellé <?= $h($s['seal_code']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="record-id">×<?= (int) ($s['quantity'] ?? 1) ?></td>
                        <td><?= $h($roomLabels[(int) ($s['room_id'] ?? 0)] ?? '—') ?></td>
                        <td><span class="badge"><?= $h($custodyLabels[$cs] ?? $cs) ?></span></td>
                        <td class="record-id"><?= $h(substr((string) ($s['created_at'] ?? ''), 0, 16)) ?></td>
                        <?php if (!empty($canManage) && !$closed && $cs !== 'EXPLOITED'): ?>
                            <td>
                                <form method="post"
                                      action="<?= $h(url('atak/sse/sites/' . (int) ($site['id'] ?? 0) . '/saisies/' . (int) ($s['id'] ?? 0) . '/possession')) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="custody_state" value="<?= $h($next) ?>">
                                    <button class="btn btn--ghost btn--sm" type="submit">
                                        Passer à « <?= $h($custodyLabels[$next] ?? $next) ?> »
                                    </button>
                                </form>
                            </td>
                        <?php elseif (!empty($canManage) && !$closed): ?>
                            <td></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">03.04</span>
            Compte rendu de clôture
        </div>
        <div class="panel-meta">Cinq lignes</div>
    </div>
    <div class="panel-body">
        <?php if ($closed && !empty($site['summary'])): ?>
            <pre class="sse-report"><?= $h($site['summary']) ?></pre>
        <?php else: ?>
            <p class="sse-note">
                Proposition générée depuis l’état courant du dossier. Elle sera figée
                à la clôture, et reste modifiable avant.
            </p>
            <pre class="sse-report"><?= $h($fiveLine) ?></pre>

            <?php if (!empty($canManage)): ?>
                <form method="post" action="<?= $h(url('atak/sse/sites/' . (int) ($site['id'] ?? 0) . '/cloture')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="summary">Compte rendu (laisser vide pour reprendre la proposition)</label>
                    <textarea id="summary" name="summary" rows="6"></textarea>
                    <button class="btn" type="submit">Clôturer le site</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
