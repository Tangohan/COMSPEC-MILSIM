<?php
declare(strict_types=1);
/**
 * Sommaire des guides des qualifications (DocumentationController::qualificationGuides).
 *
 * @var array<string, array{file: string, title: string, hint: string}> $qualificationGuides
 */
$qualificationGuides = is_array($qualificationGuides ?? null) ? $qualificationGuides : [];
$guideSlug = '';
?>
<div class="site-docs">
    <div class="site-docs__shell site-docs__shell--refs">
        <?php require __DIR__ . '/_nav.php'; ?>

        <article class="site-docs__file-card">
            <header>
                <h1>Guides des qualifications</h1>
                <p class="site-docs__file-meta">Former, mesurer et délivrer les qualifications de la communauté avec ATHENA et le mod ATAK.</p>
            </header>
            <div class="site-docs__file-body site-docs__md">
                <p>Les qualifications des catalogues sont des qualifications de jeu inspirées de brevets réels, sans valeur réelle. Ces trois guides expliquent comment les faire vivre en jeu : ce que le site mesure seul, ce qu’un instructeur atteste, et comment l’assembler.</p>
                <ul>
                    <?php foreach ($qualificationGuides as $slug => $guide): ?>
                    <li>
                        <a href="<?= htmlspecialchars(url('documentation/qualifications/' . $slug), ENT_QUOTES, 'UTF-8') ?>"><strong><?= htmlspecialchars($guide['title'], ENT_QUOTES, 'UTF-8') ?></strong></a>
                        — <?= htmlspecialchars($guide['hint'], ENT_QUOTES, 'UTF-8') ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </article>
    </div>
</div>
