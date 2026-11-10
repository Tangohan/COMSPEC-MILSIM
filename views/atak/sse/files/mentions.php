<?php
declare(strict_types=1);

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $mentions */
/** @var bool $onlyActive */
$filesTab = 'mentions';
$canManage = !empty($filesCanManage);
require __DIR__ . '/_nav.php';
$allQuery = '?' . http_build_query(['etat' => 'toutes']);
$back = 'atak/sse/fichiers/mentions' . ($onlyActive ? '' : $allQuery);
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/fichiers')) ?>">Passage aux fichiers</a> / <strong>Mentions</strong></div>

<div class="page-heading">
    <div>
        <h1>Mentions aux fichiers</h1>
        <p>Mandats, avis de recherche, signalements, personnes disparues, « armé et dangereux » et consignes de prudence, posés sur une identité, un papier ou une fiche SEEK II. Les papiers volés ou perdus se déclarent sur le papier lui-même.</p>
    </div>
    <div class="page-reference"><strong>SIMULATION</strong> Données fictives</div>
</div>

<?php if ($canManage): ?>
    <section class="panel">
        <div class="panel-header"><div class="panel-title">Poser une mention</div><div class="panel-meta">Responsables du registre</div></div>
        <div class="panel-body">
            <form class="fq-form" method="post" action="<?= $h(url('atak/sse/fichiers/mentions')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h((string) $csrfToken) ?>">
                <input type="hidden" name="back" value="<?= $h($back) ?>">
                <div><label for="fq-ref">Fiche (IDT-…, DOC-… ou REC-…)</label><input id="fq-ref" name="target_ref" type="text" value="<?= $h($prefillRef ?? '') ?>" required autocomplete="off"></div>
                <div>
                    <label for="fq-mention">Mention</label>
                    <select id="fq-mention" name="mention">
                        <?php foreach ($mentionLabels as $k => $label): ?>
                            <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label for="fq-reference">Référence</label><input id="fq-reference" name="reference" type="text" maxlength="64" placeholder="N° de mandat, d’avis…"></div>
                <div><label for="fq-note">Note</label><input id="fq-note" name="note" type="text" maxlength="1000"></div>
                <div class="fq-form__submit"><button class="btn" type="submit">Poser la mention</button></div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><?= $onlyActive ? 'Mentions actives' : 'Toutes les mentions' ?></div>
        <div class="panel-meta">
            <?php if ($onlyActive): ?>
                <a class="link" href="<?= $h(url('atak/sse/fichiers/mentions') . $allQuery) ?>">Voir aussi les mentions levées</a>
            <?php else: ?>
                <a class="link" href="<?= $h(url('atak/sse/fichiers/mentions')) ?>">Mentions actives seulement</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($mentions === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucune mention</strong><p>Les mentions posées par les responsables apparaissent ici et dans chaque réponse des fichiers.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Mention</th><th>Fiche</th><th>Référence</th><th>Note</th><th>Posée</th><th>Levée</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($mentions as $m): ?>
                    <tr>
                        <td><strong><?= $h($m['mention_label']) ?></strong> <span class="fq-code"><?= $h($m['mention']) ?></span></td>
                        <td>
                            <?php if ($m['target']['url'] !== null): ?><a class="link" href="<?= $h($m['target']['url']) ?>" translate="no"><?= $h($m['target']['label']) ?></a><?php else: ?><span translate="no"><?= $h($m['target']['label']) ?></span><?php endif; ?>
                            <?php if ($m['target']['query'] !== null): ?> · <a class="link" href="<?= $h($m['target']['query']) ?>">Passer aux fichiers</a><?php endif; ?>
                        </td>
                        <td translate="no"><?= $h($m['reference'] ?? '—') ?></td>
                        <td translate="no"><?= $h($m['note'] ?? '—') ?></td>
                        <td><?= $h($m['created_at']) ?><?php if ($m['created_by'] !== ''): ?><div class="fq-code" translate="no"><?= $h($m['created_by']) ?></div><?php endif; ?></td>
                        <td><?= $h($m['lifted_at'] ?? '—') ?><?php if ($m['lifted_by'] !== ''): ?><div class="fq-code" translate="no"><?= $h($m['lifted_by']) ?></div><?php endif; ?></td>
                        <td>
                            <?php if ($canManage && $m['active']): ?>
                                <form class="fq-actions" method="post" action="<?= $h(url('atak/sse/fichiers/mentions/' . (int) $m['id'] . '/lever')) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h((string) $csrfToken) ?>">
                                    <input type="hidden" name="back" value="<?= $h($back) ?>">
                                    <input type="text" name="lift_note" maxlength="500" placeholder="Motif de la levée" aria-label="Motif de la levée">
                                    <button class="btn btn--ghost" type="submit">Lever</button>
                                </form>
                            <?php endif; ?>
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
require dirname(__DIR__) . '/_layout.php';
