<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $findings */
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique')) ?>">Exploitation numérique</a> / <strong>Analyses</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire // Moteur d’analyse</div>
        <h1>Analyses et signaux</h1>
        <p>Détections automatiques uniquement. Aucune conclusion n’est consolidée sans validation humaine.</p>
    </div>
    <div class="page-reference"><strong>File // Signaux</strong> ATH-SSE-LABNUM-ANA</div>
</div>
<form class="toolbar" method="get">
    <div class="toolbar-field"><label for="status">Statut</label>
        <select id="status" name="status">
            <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
            <option value="" <?= ($filters['status'] ?? '') === '' ? 'selected' : '' ?>>Tous</option>
        </select>
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Filtrer</button></div>
</form>
<section class="panel">
    <?php if ($findings === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun signal</strong><p>Les propositions apparaîtront après une acquisition.</p></div></div>
    <?php else: ?>
        <?php foreach ($findings as $f): ?>
            <div class="panel-body" style="border-bottom:1px solid rgba(255,255,255,.06)">
                <strong><?= $h($f['title'] ?? '') ?></strong>
                <p><?= $h($f['detail'] ?? '') ?></p>
                <?php if (!empty($f['factors']) && is_array($f['factors'])): ?>
                    <ul><?php foreach ($f['factors'] as $factor): ?><li><?= $h(is_string($factor) ? $factor : json_encode($factor)) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (!empty($f['proposed_relation']) && is_array($f['proposed_relation'])): ?>
                    <p><em>Proposition :
                        <?= $h($f['proposed_relation']['from_label'] ?? '') ?>
                        <?= $h($f['proposed_relation']['relation'] ?? '') ?>
                        <?= $h($f['proposed_relation']['to_label'] ?? '') ?>
                    </em></p>
                <?php endif; ?>
                <p class="muted"><?= $h($f['status_label'] ?? '') ?> · confiance <?= $h($f['confidence_label'] ?? '') ?><?php if (!empty($f['score_pct'])): ?> · score indicatif <?= (int) $f['score_pct'] ?> %<?php endif; ?></p>
                <?php if (!empty($canManage) && ($f['status'] ?? '') === 'to_review'): ?>
                    <form method="post" action="<?= $h(url('atak/sse/exploitation-numerique/analyses/' . (int) $f['id'] . '/decision')) ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin-top:8px">
                        <?= \App\Core\Csrf::field() ?>
                        <label>Décision
                            <select name="status">
                                <option value="accepted">Accepter la proposition</option>
                                <option value="rejected">Rejeter</option>
                                <option value="needs_collection">Demander une collecte</option>
                            </select>
                        </label>
                        <label>Motif <input name="review_comment" type="text" style="min-width:220px"></label>
                        <button class="btn" type="submit">Enregistrer</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
