<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $case */
/** @var list<array<string,mixed>> $people */
/** @var list<array<string,mixed>> $availablePeople */
/** @var list<array<string,mixed>> $notes */
/** @var list<array<string,mixed>> $evidence */
/** @var array<string,string> $classifications */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
/** @var bool $canExport */
$linkedIds = array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $people);
$classKey = (string) ($case['classification'] ?? '');
$classBadge = match ($classKey) {
    'confidentiel' => 'badge badge--amber',
    'tres_restreint' => 'badge badge--red',
    'interne' => 'badge badge--gray',
    default => 'badge',
};
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <strong><?= $h($case['reference_code']) ?></strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Dossier // <?= $h($case['reference_code']) ?></div>
        <h1><?= $h($case['title']) ?></h1>
        <p>
            <span class="<?= $h($classBadge) ?>"><?= $h($case['classification_label']) ?></span>
            &nbsp;
            <span class="badge"><?= $h($case['status_label']) ?></span>
            <?php if (!empty($case['has_unlock_code'])): ?>
                &nbsp;<span class="badge badge--gray">Code dossier défini</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Fiche dossier</strong>
        <?= $h($case['reference_code']) ?>
        <?php if ($canExport): ?>
            <div style="margin-top:.5rem">
                <a class="btn" href="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/pdf')) ?>">Exporter PDF</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.10</span>
            Mettre à jour
        </div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $case['id'])) ?>">
            <?= \App\Core\Csrf::field() ?>
            <label for="title">Intitulé</label>
            <input id="title" name="title" type="text" required value="<?= $h($case['title']) ?>">
            <div class="grid-2">
                <div>
                    <label for="classification">Classification</label>
                    <select id="classification" name="classification">
                        <?php foreach ($classifications as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= $case['classification'] === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <?php foreach ($statuses as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= $case['status'] === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label for="summary">Synthèse</label>
            <textarea id="summary" name="summary"><?= $h($case['summary'] ?? '') ?></textarea>
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </div>
</section>
<?php else: ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.10</span>
            Synthèse
        </div>
    </div>
    <div class="panel-body">
        <p><?= nl2br($h($case['summary'] ?? '—')) ?></p>
    </div>
</section>
<?php endif; ?>

<div class="grid-2">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">01.11</span>
                Personnes rattachées
            </div>
        </div>
        <div class="panel-body">
            <?php if ($people === []): ?>
                <p class="muted">Aucune personne liée.</p>
            <?php else: ?>
                <?php foreach ($people as $p): ?>
                    <div class="note-item">
                        <span class="record-name"><?= $h($p['display_name'] ?? '') ?></span>
                        <span class="record-sub"><?= $h($p['status_label'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/personnes')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="person_id">Rattacher une fiche</label>
                    <select id="person_id" name="person_id" required>
                        <option value="">Choisir…</option>
                        <?php foreach ($availablePeople as $p): ?>
                            <?php if (in_array((int) $p['id'], $linkedIds, true)) {
                                continue;
                            } ?>
                            <option value="<?= (int) $p['id'] ?>"><?= $h($p['display_name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn" type="submit">Rattacher</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">01.12</span>
                Preuves
            </div>
        </div>
        <div class="panel-body">
            <?php foreach ($evidence as $e): ?>
                <div class="ev-item">
                    <strong><?= $h($e['label']) ?></strong>
                    <?php if (!empty($e['caption'])): ?><div class="muted"><?= $h($e['caption']) ?></div><?php endif; ?>
                    <?php if (!empty($e['url'])): ?>
                        <div><a class="link" href="<?= $h($e['url']) ?>" target="_blank" rel="noopener">Voir l’image</a></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($evidence === []): ?><p class="muted">Aucune preuve.</p><?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/preuves')) ?>" enctype="multipart/form-data">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="label">Libellé</label>
                    <input id="label" name="label" type="text" required value="Preuve">
                    <label for="caption">Légende</label>
                    <input id="caption" name="caption" type="text">
                    <label for="image">Image (optionnel)</label>
                    <input id="image" name="image" type="file" accept="image/*">
                    <button class="btn" type="submit">Ajouter</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.13</span>
            Notes classifiées
        </div>
    </div>
    <div class="panel-body">
        <?php foreach ($notes as $n): ?>
            <div class="note-item">
                <span class="badge"><?= $h($n['classification_label']) ?></span>
                <span class="muted"> · <?= $h($n['author_label'] ?? 'Opérateur') ?>
                    <?php if (!empty($n['created_at'])): ?> · <?= $h($n['created_at']) ?><?php endif; ?></span>
                <p><?= nl2br($h($n['body'])) ?></p>
            </div>
        <?php endforeach; ?>
        <?php if ($notes === []): ?><p class="muted">Aucune note.</p><?php endif; ?>
        <?php if ($canManage): ?>
            <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/notes')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <label for="body">Nouvelle note</label>
                <textarea id="body" name="body" required></textarea>
                <label for="note_class">Classification de la note</label>
                <select id="note_class" name="classification">
                    <?php foreach ($classifications as $k => $lab): ?>
                        <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Ajouter la note</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<p style="margin-top:1rem"><a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">← Retour aux dossiers</a></p>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.05</span>
            Sites exploités
        </div>
        <div class="panel-meta">Rattachés à ce dossier</div>
    </div>

    <?php if (($caseSites ?? []) === []): ?>
        <div class="panel-body">
            <p class="muted">
                Aucun site rattaché. Un site ouvert depuis le terrain avec la référence
                de ce dossier y apparaîtra automatiquement.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Référence</th>
                    <th>Site</th>
                    <th>Statut</th>
                    <th>Fouille</th>
                    <th>Saisies</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($caseSites as $s):
                    $cnt = ($siteCounts ?? [])[(int) ($s['id'] ?? 0)] ?? ['rooms' => 0, 'rooms_checked' => 0, 'seizures' => 0];
                    $pct = $cnt['rooms'] > 0 ? (int) round(($cnt['rooms_checked'] / $cnt['rooms']) * 100) : 0;
                    $pctClass = $pct >= 100 ? 'is-good' : ($pct >= 50 ? 'is-fair' : '');
                ?>
                    <tr>
                        <td><span class="record-id"><?= $h($s['reference_code'] ?? '') ?></span></td>
                        <td>
                            <span class="record-name"><?= $h($s['name'] ?? '') ?></span>
                            <span class="record-sub"><?= $h($s['site_type_label'] ?? '') ?></span>
                        </td>
                        <td><span class="badge"><?= $h($s['status_label'] ?? '') ?></span></td>
                        <td>
                            <span class="sse-score-cell">
                                <span class="sse-gauge <?= $h($pctClass) ?>">
                                    <span style="width: <?= $h((string) $pct) ?>%"></span>
                                </span>
                                <span class="sse-sample-score"><?= (int) $cnt['rooms_checked'] ?>/<?= (int) $cnt['rooms'] ?></span>
                            </span>
                        </td>
                        <td class="record-id"><?= (int) $cnt['seizures'] ?></td>
                        <td>
                            <a class="link" href="<?= $h(url('atak/sse/sites/' . (int) ($s['id'] ?? 0))) ?>">Ouvrir &rarr;</a>
                        </td>
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
            <span class="panel-index">01.06</span>
            Produits de renseignement
        </div>
    </div>
    <div class="panel-body">
        <p>
            Flash et compte rendu initial, générés depuis les éléments déjà versés au
            dossier — personnes, relevés, verdicts, sites et saisies.
        </p>
        <a class="btn" href="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/compte-rendu')) ?>">
            Ouvrir le compte rendu
        </a>
    </div>
</section>

<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
