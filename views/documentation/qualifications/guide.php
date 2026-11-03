<?php
declare(strict_types=1);
/**
 * Un guide des qualifications (DocumentationController::qualificationGuide) : Markdown de docs/guides/ déjà rendu,
 * dans la langue du lecteur.
 *
 * @var array<string, array{file: string, title: string, hint: string}> $qualificationGuides
 * @var string $guideSlug
 * @var string $docBodyHtml
 */
$qualificationGuides = is_array($qualificationGuides ?? null) ? $qualificationGuides : [];
$guideSlug = (string) ($guideSlug ?? '');
$guide = $qualificationGuides[$guideSlug] ?? ['title' => '', 'hint' => ''];
$docBodyHtml = (string) ($docBodyHtml ?? '');
?>
<div class="site-docs">
    <div class="site-docs__shell site-docs__shell--refs">
        <?php require __DIR__ . '/_nav.php'; ?>

        <article class="site-docs__file-card">
            <header>
                <h1><?= htmlspecialchars($guide['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="site-docs__file-meta"><?= htmlspecialchars($guide['hint'], ENT_QUOTES, 'UTF-8') ?></p>
            </header>
            <div class="site-docs__file-body site-docs__md" translate="no">
                <?= $docBodyHtml ?>
            </div>
        </article>
    </div>
</div>
