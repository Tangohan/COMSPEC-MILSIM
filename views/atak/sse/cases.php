<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $cases */
/** @var array{status:string,classification:string} $filters */
/** @var array<string,string> $classifications */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
/** @var bool $caseLockEnabled */
/** @var bool $screensRedacted */
/** @var int $lockedForMe */
/** @var string $myClearance */
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
// Répartition par classification : c'est elle qu'on relit avant d'armer le verrou.
$byClass = [];
foreach ($cases as $c) {
    $k = (string) ($c['classification'] ?? 'encadrement');
    $byClass[$k] = ($byClass[$k] ?? 0) + 1;
}
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

<section class="panel sse-lock-panel <?= !empty($caseLockEnabled) ? 'is-armed' : '' ?>">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.00</span>
            Verrou d’ouverture par classification
        </div>
        <div class="panel-meta"><?= !empty($caseLockEnabled) ? 'ARMÉ' : 'DÉSARMÉ' ?></div>
    </div>
    <div class="panel-body">
        <?php if (!empty($caseLockEnabled)): ?>
            <p>
                Un dossier dont la classification dépasse l’habilitation du lecteur
                <strong>ne s’ouvre pas</strong> pour lui — ni la fiche, ni les personnes
                rattachées, ni les notes, ni les corrélations, ni le compte rendu.
            </p>
        <?php else: ?>
            <p>
                La classification <strong>signale sans fermer</strong> : elle s’affiche en
                badge et noircit les catégories concernées sur les versions expurgées, mais
                n’empêche aucune ouverture.
            </p>
            <p class="sse-note">
                Avant d’armer, relisez la colonne « Qui pourra encore l’ouvrir » ci-dessous.
                La classification n’a jamais filtré depuis la création du portail : les
                valeurs déjà posées ont été choisies sans conséquence, et les armer les
                transforme rétroactivement en décisions d’exclusion que personne n’a prises.
            </p>
        <?php endif; ?>

        <div class="sse-release-summary">
            <div>
                <div class="sse-block-title">Répartition actuelle</div>
                <p>
                    <?php
                    $parts = [];
                    foreach ($byClass as $ck => $n) {
                        $parts[] = $n . ' × ' . ($classifications[$ck] ?? $ck);
                    }
                    echo $parts === [] ? 'Aucun dossier.' : $h(implode(' · ', $parts));
                    ?>
                </p>
            </div>
            <div>
                <div class="sse-block-title">Effet sur vous</div>
                <p>
                    <?php if ((int) $lockedForMe === 0): ?>
                        Aucun de ces dossiers ne vous serait fermé
                        (habilitation : <?= $h(\App\Services\Sse\SseRedactionService::levelLabel($myClearance)) ?>).
                    <?php else: ?>
                        <strong><?= (int) $lockedForMe ?></strong> dossier<?= (int) $lockedForMe > 1 ? 's' : '' ?>
                        vous <?= (int) $lockedForMe > 1 ? 'seraient fermés' : 'serait fermé' ?>
                        (habilitation : <?= $h(\App\Services\Sse\SseRedactionService::levelLabel($myClearance)) ?>).
                    <?php endif; ?>
                </p>
                <p class="sse-muted">
                    Le portail ne peut mesurer l’effet que pour la session courante :
                    il ne peut pas parler à la place des habilitations des autres.
                </p>
            </div>
        </div>

        <?php if (!empty($canGrant)): ?>
            <form method="post" action="<?= $h(url('atak/sse/dossiers/verrou-classification')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="reglage" value="verrou">
                <input type="hidden" name="enable" value="<?= !empty($caseLockEnabled) ? '0' : '1' ?>">
                <button class="btn <?= !empty($caseLockEnabled) ? 'btn--ghost' : '' ?>" type="submit">
                    <?= !empty($caseLockEnabled) ? 'Désarmer le verrou' : 'Armer le verrou' ?>
                </button>
            </form>
        <?php else: ?>
            <p class="sse-muted">
                Seuls les détenteurs du droit d’octroi peuvent armer ce verrou : il ferme
                des dossiers à d’autres, ce n’est pas un réglage d’affichage.
            </p>
        <?php endif; ?>

        <hr class="sse-sep">

        <div class="sse-block-title">
            Caviardage des écrans de travail —
            <?= !empty($screensRedacted) ? 'ARMÉ' : 'DÉSARMÉ' ?>
        </div>
        <?php if (!empty($screensRedacted)): ?>
            <p>
                Le registre des personnes, la fiche dossier et les corrélations sont
                rabattus sur l’habilitation du lecteur, comme les documents de diffusion.
            </p>
        <?php else: ?>
            <p>
                Les documents de diffusion — compte rendu, PDF, version expurgée — sont
                <strong>toujours</strong> rabattus sur l’habilitation du lecteur : c’est
                leur objet. Les écrans de travail, eux, restent intégraux.
            </p>
            <p class="sse-note">
                Les armer retire des informations que la cellule utilise toute la séance.
                Selon la doctrine retenue pour les catégories, cela peut retirer les noms
                à ceux qui en ont besoin pour travailler. À armer une fois les
                habilitations réellement réparties.
            </p>
        <?php endif; ?>

        <?php if (!empty($canGrant)): ?>
            <form method="post" action="<?= $h(url('atak/sse/dossiers/verrou-classification')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="reglage" value="ecrans">
                <input type="hidden" name="enable" value="<?= !empty($screensRedacted) ? '0' : '1' ?>">
                <button class="btn <?= !empty($screensRedacted) ? 'btn--ghost' : '' ?>" type="submit">
                    <?= !empty($screensRedacted) ? 'Rendre les écrans intégraux' : 'Caviarder les écrans de travail' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</section>

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
                    <th>Qui pourra encore l’ouvrir</th>
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
                        <td class="sse-muted sse-who-opens">
                            <?= $h(\App\Services\Sse\SseClearanceService::whoCanOpen((string) ($c['classification'] ?? ''))) ?>
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
