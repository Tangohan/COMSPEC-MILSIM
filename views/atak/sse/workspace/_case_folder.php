<?php
declare(strict_types=1);
/** @var array<string,mixed> $folder */
/** @var array<string,string> $lifecycleOptions */
/** @var callable $h */
/** @var bool $canManage */
/** @var string $csrfToken */
$header = is_array($folder['header'] ?? null) ? $folder['header'] : [];
$people = is_array($folder['entities'] ?? null) ? $folder['entities'] : [];
$notes = is_array($folder['notes'] ?? null) ? $folder['notes'] : [];
$evidence = is_array($folder['evidence'] ?? null) ? $folder['evidence'] : [];
$caseTimeline = is_array($folder['timeline'] ?? null) ? $folder['timeline'] : [];
$caseRelations = is_array($folder['relations'] ?? null) ? $folder['relations'] : [];
$audit = is_array($folder['audit'] ?? null) ? $folder['audit'] : [];
$caseId = (int) ($header['id'] ?? 0);
?>
<header class="iw-folder-head">
    <div>
        <span class="iw-intel-kicker">Chemise numérique</span>
        <h2><?= $h(trim((string) ($header['reference_code'] ?? '') . ' — ' . (string) ($header['title'] ?? ''))) ?></h2>
        <p><?= $h((string) ($header['summary'] ?? 'Pas de synthèse.')) ?></p>
        <div class="iw-folder-meta">
            <span><?= $h((string) ($header['lifecycle_label'] ?? '')) ?></span>
            <span><?= $h((string) ($header['classification'] ?? '')) ?></span>
            <span>Priorité <?= $h((string) ($header['priority'] ?? 'normale')) ?></span>
            <?php if (!empty($header['confidence_note'])): ?>
                <span>Confiance <?= $h((string) $header['confidence_note']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="iw-folder-actions">
        <a class="iw-btn" href="<?= $h((string) ($header['href'] ?? '#')) ?>">Fiche complète</a>
        <a class="iw-btn iw-btn--ghost" href="<?= $h(url('atak/sse/workspace')) ?>">Quitter la chemise</a>
    </div>
</header>

<?php if ($canManage): ?>
<form class="iw-folder-meta-form" method="post" action="<?= $h(url('atak/sse/workspace/dossiers/' . $caseId . '/meta')) ?>">
    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
    <label>État
        <select name="lifecycle_status">
            <?php foreach ($lifecycleOptions as $val => $lab): ?>
                <option value="<?= $h((string) $val) ?>" <?= ((string) ($header['lifecycle_status'] ?? '') === (string) $val) ? 'selected' : '' ?>><?= $h((string) $lab) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Priorité
        <select name="priority">
            <?php foreach (['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'critique' => 'Critique'] as $pv => $pl): ?>
                <option value="<?= $h($pv) ?>" <?= ((string) ($header['priority'] ?? '') === $pv) ? 'selected' : '' ?>><?= $h($pl) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Unité productrice
        <input type="text" name="producing_unit" value="<?= $h((string) ($header['producing_unit'] ?? '')) ?>" maxlength="120">
    </label>
    <label>Note de confiance
        <input type="text" name="confidence_note" value="<?= $h((string) ($header['confidence_note'] ?? '')) ?>" maxlength="8" placeholder="B2">
    </label>
    <button type="submit" class="iw-btn iw-btn--solid">Enregistrer</button>
</form>
<?php endif; ?>

<div class="iw-folder-sections">
    <section>
        <h3>Entités (<?= count($people) ?>)</h3>
        <ul class="iw-intel-list iw-intel-list--compact">
            <?php foreach ($people as $p): ?>
                <?php if (!is_array($p)) {
                    continue;
                } ?>
                <li>
                    <a href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                        <strong><?= $h((string) ($p['display_name'] ?? '')) ?></strong>
                        <em><?= $h(trim((string) ($p['identity_tier_label'] ?? '') . ' ' . (string) ($p['confidence_code'] ?? ''))) ?></em>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if ($people === []): ?><li class="iw-intel-empty">Aucune personne rattachée.</li><?php endif; ?>
        </ul>
    </section>
    <section>
        <h3>Pièces (<?= count($evidence) ?>)</h3>
        <ul class="iw-intel-list iw-intel-list--compact">
            <?php foreach ($evidence as $ev): ?>
                <?php if (!is_array($ev)) {
                    continue;
                } ?>
                <li>
                    <strong><?= $h((string) ($ev['title'] ?? $ev['label'] ?? 'Pièce')) ?></strong>
                    <em><?= $h((string) ($ev['note'] ?? $ev['description'] ?? '')) ?></em>
                </li>
            <?php endforeach; ?>
            <?php if ($evidence === []): ?><li class="iw-intel-empty">Aucune pièce.</li><?php endif; ?>
        </ul>
    </section>
    <section>
        <h3>Notes (<?= count($notes) ?>)</h3>
        <ul class="iw-intel-list iw-intel-list--compact">
            <?php foreach ($notes as $n): ?>
                <?php if (!is_array($n)) {
                    continue;
                } ?>
                <li>
                    <strong><?= $h((string) ($n['author_label'] ?? 'Note')) ?></strong>
                    <em><?= $h(mb_substr((string) ($n['body'] ?? $n['content'] ?? ''), 0, 180)) ?></em>
                </li>
            <?php endforeach; ?>
            <?php if ($notes === []): ?><li class="iw-intel-empty">Aucune note.</li><?php endif; ?>
        </ul>
    </section>
    <section>
        <h3>Relations</h3>
        <ul class="iw-intel-list iw-intel-list--compact">
            <?php foreach ($caseRelations as $rel): ?>
                <?php if (!is_array($rel)) {
                    continue;
                } ?>
                <li>
                    <span class="iw-intel-kicker"><?= $h((string) ($rel['status'] ?? '') === 'proposed' ? 'Proposées' : 'Confirmées') ?></span>
                    <strong><?= $h((string) ($rel['from_type'] ?? '')) ?> #<?= (int) ($rel['from_id'] ?? 0) ?> → <?= $h((string) ($rel['relation'] ?? '')) ?></strong>
                </li>
            <?php endforeach; ?>
            <?php if ($caseRelations === []): ?><li class="iw-intel-empty">Aucune relation.</li><?php endif; ?>
        </ul>
    </section>
    <section>
        <h3>Chronologie dossier</h3>
        <ol class="iw-intel-timeline">
            <?php foreach (array_slice($caseTimeline, 0, 12) as $ev): ?>
                <?php if (!is_array($ev)) {
                    continue;
                } ?>
                <li>
                    <time><?= $h((string) ($ev['event_time'] ?? '')) ?></time>
                    <div>
                        <span class="iw-intel-badge"><?= $h((string) ($ev['event_type_label'] ?? '')) ?></span>
                        <p><?= $h((string) ($ev['summary'] ?? '')) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if ($caseTimeline === []): ?><li class="iw-intel-empty">Aucun événement indexé.</li><?php endif; ?>
        </ol>
    </section>
    <section>
        <h3>Journal</h3>
        <ul class="iw-intel-list iw-intel-list--compact">
            <?php foreach ($audit as $a): ?>
                <?php if (!is_array($a)) {
                    continue;
                } ?>
                <li>
                    <strong><?= $h((string) ($a['actor_label'] ?? '')) ?> — <?= $h((string) ($a['action'] ?? '')) ?></strong>
                    <em><?= $h((string) ($a['reason'] ?? $a['created_at'] ?? '')) ?></em>
                </li>
            <?php endforeach; ?>
            <?php if ($audit === []): ?><li class="iw-intel-empty">Pas encore d’entrées d’audit.</li><?php endif; ?>
        </ul>
    </section>
</div>

<?php
$folderCycleCounts = is_array(($folder['cycle']['counts'] ?? null)) ? $folder['cycle']['counts'] : [];
?>
<section style="margin-top:1rem">
    <h3>Cycle de renseignement</h3>
    <p class="muted">
        <?= (int) ($folderCycleCounts['requirements'] ?? 0) ?> exigence(s) ouverte(s) ·
        <?= (int) ($folderCycleCounts['taskings_open'] ?? 0) ?> ordre(s) ·
        <?= (int) ($folderCycleCounts['products_pending'] ?? 0) ?> produit(s) en cours.
        <a class="link" href="#cycle" data-iw-tab-link="cycle">Ouvrir l’onglet Cycle</a>
    </p>
</section>
