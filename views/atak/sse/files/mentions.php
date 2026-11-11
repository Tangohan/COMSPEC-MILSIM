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
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h((string) $csrfToken) . '"><input type="hidden" name="back" value="' . $h($back) . '">';
$tone = static fn (string $m): string => in_array($m, \App\Support\SseFileCatalog::MENTIONS_WANTED, true) ? 'r' : ($m === 'caution' ? 'a' : 'b');
?>
<div class="fx">
    <div class="fx-th">
        <div>
            <h1>Mentions aux fichiers</h1>
            <p>Mandats, avis de recherche, signalements, personnes disparues, « armé et dangereux » et consignes de prudence, posés sur une identité, un papier ou une fiche SEEK II. Les papiers volés ou perdus se déclarent sur le papier lui-même.</p>
        </div>
    </div>
    <div class="<?= $canManage ? 'fx-wk' : '' ?>">
        <?php if ($canManage): ?>
            <form class="fx-card" method="post" action="<?= $h(url('atak/sse/fichiers/mentions')) ?>"><?= $csrf ?>
                <div class="fx-ch"><h2>Poser une mention</h2></div>
                <div class="fx-fs">
                    <label class="fx-f"><span>Fiche</span><input class="fx-in m" name="target_ref" value="<?= $h($prefillRef ?? '') ?>" required autocomplete="off" placeholder="IDT-12, DOC-34 ou REC-000412"><small>La référence affichée en tête de la fiche, du papier ou du relevé SEEK II.</small></label>
                    <label class="fx-f"><span>Mention</span><select class="fx-in" name="mention"><?php foreach ($mentionLabels as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?></select></label>
                    <label class="fx-f"><span>Référence</span><input class="fx-in" name="reference" maxlength="64" placeholder="ex. WARRANT 26-1187"></label>
                    <label class="fx-f"><span>Note</span><input class="fx-in" name="note" maxlength="1000" placeholder="ex. porteur habituel d’une arme de poing"></label>
                </div>
                <div class="fx-ff"><button class="btn pri" type="submit">Poser la mention</button></div>
            </form>
        <?php endif; ?>
        <section class="fx-card">
            <div class="fx-ch"><h2><?= $onlyActive ? 'Mentions actives' : 'Toutes les mentions' ?></h2>
                <?php if ($onlyActive): ?><a href="<?= $h(url('atak/sse/fichiers/mentions') . $allQuery) ?>">Voir aussi les mentions levées</a><?php else: ?><a href="<?= $h(url('atak/sse/fichiers/mentions')) ?>">Mentions actives seulement</a><?php endif; ?>
            </div>
            <?php if ($mentions === []): ?>
                <div class="fx-nr"><b>AUCUNE MENTION</b><p>Les mentions posées par les responsables apparaissent ici et dans chaque réponse des fichiers.</p></div>
            <?php endif; ?>
            <?php foreach ($mentions as $m): ?>
                <div class="fx-mi" style="margin:10px 16px">
                    <div class="fx-row">
                        <span class="fx-hn"><span class="fx-flg <?= $tone((string) $m['mention']) ?>"><?= $h($m['mention_label']) ?></span>
                            <?php if ($m['target']['url'] !== null): ?><a href="<?= $h($m['target']['url']) ?>" translate="no"><?= $h($m['target']['label']) ?></a><?php else: ?><span translate="no"><?= $h($m['target']['label']) ?></span><?php endif; ?>
                            <?php if (!$m['active']): ?><span class="fx-flg">Levée</span><?php endif; ?></span>
                        <?php if ($m['target']['query'] !== null): ?><a class="btn sm" href="<?= $h($m['target']['query']) ?>">Passer aux fichiers</a><?php endif; ?>
                    </div>
                    <?php if (($m['note'] ?? '') !== ''): ?><p translate="no"><?= $h($m['note']) ?></p><?php endif; ?>
                    <small><?= ($m['reference'] ?? '') !== '' ? $h($m['reference']) . ' · ' : '' ?>posée le <?= $h(substr((string) $m['created_at'], 0, 16)) ?><?= $m['created_by'] !== '' ? ' par ' . $h($m['created_by']) : '' ?><?= $m['lifted_at'] !== null ? ' · levée le ' . $h(substr((string) $m['lifted_at'], 0, 16)) . ($m['lifted_by'] !== '' ? ' par ' . $h($m['lifted_by']) : '') : '' ?></small>
                    <?php if ($canManage && $m['active']): ?>
                        <form class="fx-inline" method="post" action="<?= $h(url('atak/sse/fichiers/mentions/' . (int) $m['id'] . '/lever')) ?>"><?= $csrf ?>
                            <label class="fx-f"><span>Motif de la levée</span><input class="fx-in" name="lift_note" maxlength="500" placeholder="ex. interpellé le 11/11, mandat exécuté"></label>
                            <button class="btn" type="submit">Lever</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
