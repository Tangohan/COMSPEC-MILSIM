<?php
$equipmentClass = $equipmentClass ?? null;
$linkedDocuments = $linkedDocuments ?? [];
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!$equipmentClass) {
    echo '<p>Classe non trouvée.</p>';
    return;
}
$hubBack = url('equipment') . '?tab=fiches&fiche=' . (int) ($equipmentClass['id'] ?? 0);
?>
<div class="eq-hub">
    <header class="eq-hub__banner">
        <div class="eq-hub__banner-copy">
            <p class="eq-hub__kicker"><a href="<?= $h(url('equipment') . '?tab=fiches') ?>">Équipement</a> · Fiche matériel</p>
            <h1><?= $h($equipmentClass['name'] ?? '') ?></h1>
            <?php if (!empty($equipmentClass['category'])): ?>
            <p class="eq-hub__count"><?= $h($equipmentClass['category']) ?></p>
            <?php endif; ?>
        </div>
        <div class="eq-hub__banner-actions">
            <a class="eq-hub__btn eq-hub__btn--ghost-light" href="<?= $h($hubBack) ?>">Ouvrir dans le catalogue</a>
        </div>
    </header>
    <div class="eq-hub__body">
        <?php if (!empty($equipmentClass['cover_url'])): ?>
        <div class="eq-hub__media eq-hub__media--portrait" style="max-width:16rem;margin-bottom:1rem;">
            <img class="eq-hub__img is-loaded" src="<?= $h($equipmentClass['cover_url']) ?>" alt="">
        </div>
        <?php endif; ?>
        <?php if (!empty($equipmentClass['description'])): ?>
        <p class="eq-hub__qv-desc"><?= nl2br($h($equipmentClass['description'])) ?></p>
        <?php else: ?>
        <p class="eq-hub__empty">Aucune description pour le moment.</p>
        <?php endif; ?>

        <section style="margin-top:1.5rem;">
            <h2 class="eq-hub__grid-title">Documentation</h2>
            <?php if (empty($linkedDocuments)): ?>
            <p class="eq-hub__empty">Aucun document associé.</p>
            <?php else: ?>
            <ul class="eq-hub__docs">
                <?php foreach ($linkedDocuments as $doc): ?>
                <li><a href="<?= $h(url('documents/' . ($doc['slug'] ?? ''))) ?>"><?= $h($doc['title'] ?? '') ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
