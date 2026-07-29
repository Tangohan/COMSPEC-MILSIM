<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $cases */
/** @var array{status:string,classification:string} $filters */
/** @var array<string,string> $classifications */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
$total = count($cases);
$activeCount = 0;
foreach ($cases as $c) {
    $st = (string) ($c['status'] ?? '');
    if (in_array($st, ['ouvert', 'en_cours'], true)) {
        $activeCount++;
    }
}
$classBadge = static function (string $key): string {
    return match ($key) {
        'confidentiel' => 'badge badge--amber',
        'tres_restreint' => 'badge badge--red',
        'interne' => 'badge badge--gray',
        default => 'badge',
    };
};
$statusBadge = static function (string $key): string {
    return match ($key) {
        'clos', 'archive' => 'badge badge--gray',
        'en_cours' => 'badge badge--amber',
        default => 'badge',
    };
};
?>
<div class="breadcrumb">
    Athena / SSE / Renseignement /
    <strong>Dossiers</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Gestion des dossiers // Exploitation</div>
        <h1>Dossiers d’affaire</h1>
        <p>
            Consultation et exploitation des dossiers relevant du périmètre d’accès
            de la session active. Toutes les consultations et modifications sont journalisées.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Index des dossiers</strong>
        Réf. ATH-SSE-DOSSIERS
    </div>
</div>

<div class="security-notice">
    <div class="security-notice-code">SEC-04</div>
    <div>
        <strong>Information compartimentée</strong>
        <span>
            Les données affichées sont soumises au principe du besoin d’en connaître.
            Toute extraction, reproduction ou diffusion non autorisée est journalisée.
        </span>
    </div>
</div>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Dossiers visibles</div>
        <div class="metric-value"><?= $h(str_pad((string) $total, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Périmètre de session</div>
    </div>
    <div class="metric">
        <div class="metric-label">Actifs</div>
        <div class="metric-value"><?= $h(str_pad((string) $activeCount, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Ouverts / en cours</div>
    </div>
    <div class="metric">
        <div class="metric-label">Accès</div>
        <div class="metric-value"><?= $canManage ? 'Gest.' : 'Lect.' ?></div>
        <div class="metric-detail">Niveau de session</div>
    </div>
    <div class="metric">
        <div class="metric-label">Horodatage</div>
        <div class="metric-value"><?= $h(date('H:i')) ?></div>
        <div class="metric-detail">Heure locale</div>
    </div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/dossiers')) ?>">
    <div class="toolbar-field">
        <label for="status">Statut opérationnel</label>
        <select id="status" name="status">
            <option value="">Tous les statuts</option>
            <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="classification">Niveau de diffusion</label>
        <select id="classification" name="classification">
            <option value="">Toutes les classifications</option>
            <?php foreach ($classifications as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['classification'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions">
        <button class="btn" type="submit">Appliquer</button>
        <?php if ($canManage): ?>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dossiers/nouveau')) ?>">+ Nouveau dossier</a>
        <?php endif; ?>
    </div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.01</span>
            Registre des dossiers
        </div>
        <div class="panel-meta">Périmètre d’accès // session courante</div>
    </div>

    <?php if ($cases === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">—</div>
                <strong>Aucun enregistrement</strong>
                <p>
                    Aucun dossier ne correspond au périmètre d’accès de la session active
                    ou aux critères sélectionnés.
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Référence</th>
                    <th>Dossier</th>
                    <th>Classification</th>
                    <th>Statut</th>
                    <th>Contenu</th>
                    <th>Mise à jour</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($cases as $c):
                    $cnt = $caseCounts[(int) ($c['id'] ?? 0)] ?? ['persons' => 0, 'notes' => 0, 'evidence' => 0];
                    $stamp = (string) ($c['updated_at'] ?? $c['created_at'] ?? '');
                ?>
                    <tr>
                        <td><span class="record-id"><?= $h($c['reference_code']) ?></span></td>
                        <td>
                            <span class="record-name"><?= $h($c['title']) ?></span>
                            <span class="record-sub">Dossier d’affaire</span>
                        </td>
                        <td>
                            <span class="<?= $h($classBadge((string) ($c['classification'] ?? ''))) ?>">
                                <?= $h($c['classification_label']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?= $h($statusBadge((string) ($c['status'] ?? ''))) ?>">
                                <?= $h($c['status_label']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="sse-count-set">
                                <span class="sse-count" title="Personnes rattachées">
                                    <span class="sse-count-n"><?= (int) $cnt['persons'] ?></span> pers.
                                </span>
                                <span class="sse-count" title="Notes de dossier">
                                    <span class="sse-count-n"><?= (int) $cnt['notes'] ?></span> notes
                                </span>
                                <span class="sse-count" title="Pièces versées">
                                    <span class="sse-count-n"><?= (int) $cnt['evidence'] ?></span> pièces
                                </span>
                            </span>
                        </td>
                        <td class="record-id"><?= $h($stamp !== '' ? substr($stamp, 0, 16) : '—') ?></td>
                        <td>
                            <a class="link" href="<?= $h(url('atak/sse/dossiers/' . $c['id'])) ?>">Ouvrir →</a>
                        </td>
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
