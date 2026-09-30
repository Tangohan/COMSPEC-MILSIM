<?php
declare(strict_types=1);
use App\Support\AdvancementCodes;
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$assignments = is_array($assignments ?? null) ? $assignments : [];
?>
<div class="bo-member-situation bo-member-situation--dossier">
    <header class="bo-dossier-hero">
        <div>
            <p class="bo-dossier-hero__kicker">Dossier individuel</p>
            <h2 class="bo-dossier-hero__title">Ma dotation</h2>
            <p class="bo-dossier-hero__lead">Matériel attribué à votre nom, avec numéro de série fictif et statut.</p>
        </div>
        <div class="bo-dossier-hero__stats"><div><strong><?= count($assignments) ?></strong><span>Articles</span></div></div>
    </header>
    <?php if ($assignments === []): ?>
        <section class="bo-doc-empty"><div><h3>Aucune dotation</h3><p>Les articles nominatifs apparaîtront ici.</p></div></section>
    <?php else: ?>
        <div class="bo-doc-grid bo-qual-grid">
            <?php foreach ($assignments as $row): ?>
                <article class="bo-doc-card bo-qual-card def">
                    <div class="bo-doc-sheet">
                        <div class="bo-qual-card__cat"><?= $h((string) ($row['category'] ?? 'Matériel')) ?></div>
                        <h3 class="bo-qual-card__title"><?= $h((string) ($row['definition_name'] ?? '')) ?></h3>
                        <div class="bo-qual-card__meta">
                            <div>N° de série<b><?= $h((string) ($row['serial_number'] ?? '—')) ?></b></div>
                            <div>Statut<b><?= $h(AdvancementCodes::equipmentLabel((string) ($row['status'] ?? ''))) ?></b></div>
                        </div>
                        <div class="bo-qual-card__valid">
                            <span class="dot"></span>
                            <span>Attribué le <?= $h(date('d/m/Y', strtotime((string) ($row['assigned_at'] ?? 'now')) ?: time())) ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
