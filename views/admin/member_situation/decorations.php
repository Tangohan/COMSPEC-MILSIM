<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$awards = is_array($awards ?? null) ? $awards : [];
?>
<div class="bo-member-situation bo-member-situation--dossier">
    <header class="bo-dossier-hero">
        <div>
            <p class="bo-dossier-hero__kicker">Dossier individuel</p>
            <h2 class="bo-dossier-hero__title">Mes décorations</h2>
            <p class="bo-dossier-hero__lead">Ce que l’on vous a reconnu avoir fait — distinct de ce que vous savez faire (qualifications).</p>
        </div>
        <div class="bo-dossier-hero__stats"><div><strong><?= count($awards) ?></strong><span>Citations</span></div></div>
    </header>
    <?php if ($awards === []): ?>
        <section class="bo-doc-empty"><div><h3>Aucune décoration</h3><p>Les citations attribuées par l’encadrement apparaîtront ici.</p></div></section>
    <?php else: ?>
        <div class="bo-doc-grid bo-qual-grid">
            <?php foreach ($awards as $row): ?>
                <article class="bo-doc-card bo-qual-card def">
                    <div class="bo-doc-sheet bo-qual-card__head">
                        <div class="bo-qual-card__cat"><?= $h((string) ($row['decoration_grade'] ?? 'Décoration')) ?></div>
                        <h3 class="bo-qual-card__title"><?= $h((string) ($row['definition_name'] ?? '')) ?></h3>
                        <div class="bo-qual-card__meta">
                            <div>Attribuée<b><?= $h(date('d/m/Y', strtotime((string) ($row['awarded_at'] ?? 'now')) ?: time())) ?></b></div>
                            <div>Autorité<b><?= $h((string) ($row['authority'] ?? '—')) ?></b></div>
                        </div>
                        <?php if (trim((string) ($row['citation_text'] ?? '')) !== ''): ?>
                            <p class="bo-doc-sheet__level"><?= $h((string) $row['citation_text']) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
