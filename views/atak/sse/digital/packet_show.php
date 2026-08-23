<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $packet */
/** @var list<array<string,mixed>> $cases */
$packet = is_array($packet ?? null) ? $packet : [];
$cases = is_array($cases ?? null) ? $cases : [];
$id = (int) ($packet['id'] ?? 0);
$status = (string) ($packet['status'] ?? '');
$canAct = !empty($canManage) && $status === 'a_exploiter';
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/a-exploiter')) ?>">À exploiter</a> /
    <strong><?= $h($packet['title'] ?? 'Paquet') ?></strong>
</div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline"><?= $h($packet['packet_type_label'] ?? '') ?> // <?= $h($packet['status_label'] ?? '') ?></div>
        <h1><?= $h($packet['title'] ?? 'Renseignement') ?></h1>
        <p>
            Support <?= $h($packet['support_label'] ?? '—') ?>
            <?php if (!empty($packet['origin_label'])): ?> · <?= $h($packet['origin_label']) ?><?php endif; ?>
        </p>
    </div>
    <div class="page-reference">
        <strong><?= $h($packet['confidence_label'] ?? 'Non évalué') ?></strong>
        <div><?= $h($packet['quality_label'] ?? '') ?></div>
    </div>
</div>

<?php if (!empty($packet['is_decoy']) || !empty($packet['is_fragment'])): ?>
<section class="panel" style="margin-bottom:10px">
    <div class="panel-body">
        <p>
            <?php if (!empty($packet['is_fragment'])): ?>
                <strong>Fragment.</strong> Le texte est incomplet : un croisement avec une autre source est nécessaire avant toute conclusion.
            <?php endif; ?>
            <?php if (!empty($packet['is_decoy'])): ?>
                <strong>À corroborer.</strong> Ce contenu peut être trompeur. Ne le présentez pas comme un fait confirmé.
            <?php endif; ?>
        </p>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Contenu</div></div>
    <div class="panel-body">
        <p style="white-space:pre-wrap"><?= $h($packet['body_text'] ?? '') ?></p>
    </div>
    <div class="panel-body" style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
        <div><span class="muted">Canal</span><br><strong><?= $h($packet['channel_label'] ?? '—') ?></strong></div>
        <div><span class="muted">Révélation</span><br><strong><?= $h($packet['reveal_label'] ?? '—') ?></strong></div>
        <div><span class="muted">Collecteur</span><br><strong><?= $h($packet['collector_label'] ?? '—') ?></strong></div>
        <div><span class="muted">Lieu</span><br><strong><?= $h($packet['grid_reference'] ?? '—') ?></strong></div>
        <?php if (!empty($packet['on_map'])): ?>
            <div><span class="muted">Carte</span><br><strong>Visible sur la carte du bureau</strong></div>
        <?php elseif (!empty($packet['has_coordinates'])): ?>
            <div><span class="muted">Carte</span><br><strong>Coordonnées enregistrées — le point s’affichera si vous rattachez ce renseignement à un dossier</strong></div>
        <?php endif; ?>
        <?php if (!empty($packet['occurred_at_label'])): ?>
            <div><span class="muted">Horaire de mission</span><br><strong><?= $h($packet['occurred_at_label']) ?></strong></div>
        <?php endif; ?>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Entités mentionnées</div></div>
    <?php if (($packet['linked_entities'] ?? []) === []): ?>
        <div class="panel-body"><p class="muted">Aucune entité n’est rattachée à ce paquet.</p></div>
    <?php else: ?>
        <div class="panel-body" style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($packet['linked_entities'] as $entity): ?>
                <span class="badge"><?= $h($entity['label'] ?? '') ?> · <?= $h($entity['kind_label'] ?? '') ?></span>
            <?php endforeach; ?>
        </div>
        <div class="panel-body">
            <p class="muted">Ces mentions sont des propositions. Elles n’ouvrent pas un lien dans la toile tant qu’un analyste ne les a pas validées.</p>
        </div>
    <?php endif; ?>
</section>

<?php if ($canAct): ?>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">03</span> Décision</div></div>
    <form method="post" action="<?= $h(url('atak/sse/exploitation-numerique/a-exploiter/' . $id . '/decision')) ?>" class="panel-body" style="display:grid;gap:12px">
        <?= \App\Core\Csrf::field() ?>
        <label>Action
            <select name="decision" required>
                <option value="rattache">Rattacher au dossier</option>
                <option value="ecarte">Écarter (sans suite)</option>
            </select>
        </label>
        <label>Dossier (obligatoire pour rattacher)
            <select name="case_id">
                <option value="">Choisir un dossier</option>
                <?php foreach ($cases as $c): ?>
                    <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= $h(trim(($c['reference_code'] ?? '') . ' — ' . ($c['title'] ?? ''), ' —')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="muted">Rattacher le paquet au dossier ne confirme pas les entités mentionnées. Un point carte n’apparaît pour un renseignement de mission qu’après cette décision. Un point posé par le chef de mission est déjà visible sur la carte du bureau.</p>
        <div class="toolbar-actions"><button class="btn" type="submit">Enregistrer la décision</button></div>
    </form>
</section>
<?php elseif ($status !== 'a_exploiter'): ?>
<section class="panel" style="margin-top:10px">
    <div class="panel-body"><p class="muted">Ce paquet n’est plus dans la file d’exploitation (<?= $h($packet['status_label'] ?? '') ?>).</p></div>
</section>
<?php endif; ?>

<?php if ((int) ($packet['device_id'] ?? 0) > 0): ?>
    <p style="margin-top:12px"><a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports/' . (int) $packet['device_id'])) ?>">Retour au support</a></p>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
