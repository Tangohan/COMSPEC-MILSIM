<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sections = is_array($sections ?? null) ? $sections : [];
$articles = is_array($articles ?? null) ? $articles : [];
$trainings = is_array($trainings ?? null) ? $trainings : [];
$hasAnything = $sections !== [] || $articles !== [] || $trainings !== [];
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Bibliothèque d’unité</h2>
        <div class="jnet-mail__actions">
            <a class="jnet-btn" href="<?= $h((string) ($athenaDocs ?? url('documents'))) ?>">Tous les documents</a>
            <a class="jnet-btn" href="<?= $h((string) ($athenaArticles ?? url('articles'))) ?>">Articles</a>
            <a class="jnet-btn" href="<?= $h((string) ($sseGuide ?? url('atak/sse/guide'))) ?>">Guide SSE</a>
        </div>
    </div>
    <div class="jnet-panel__body">
        <?php if (!$hasAnything): ?>
            <div class="jnet-empty">
                <p>Aucun document, article ou parcours n’est encore publié pour l’unité.</p>
                <p>Dès qu’un texte est mis à disposition, il apparaît ici et peut s’ouvrir directement.</p>
            </div>
        <?php endif; ?>

        <?php if ($articles !== []): ?>
            <h3 class="jnet-section-title">Articles publiés</h3>
            <ul class="jnet-linklist">
                <?php foreach ($articles as $article): ?>
                    <li>
                        <a href="<?= $h((string) ($article['href'] ?? '#')) ?>">
                            <strong><?= $h((string) ($article['title'] ?? '')) ?></strong>
                            <?php if (trim((string) ($article['excerpt'] ?? '')) !== ''): ?>
                                <span><?= $h((string) $article['excerpt']) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php foreach ($sections as $sec): ?>
            <h3 class="jnet-section-title"><?= $h((string) ($sec['label'] ?? 'Documents')) ?></h3>
            <ul class="jnet-linklist">
                <?php foreach (($sec['items'] ?? []) as $item): ?>
                    <?php if (is_array($item)): ?>
                        <li>
                            <a href="<?= $h((string) ($item['href'] ?? '#')) ?>">
                                <strong><?= $h((string) ($item['title'] ?? '')) ?></strong>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>

        <?php if ($trainings !== []): ?>
            <h3 class="jnet-section-title">Parcours de formation</h3>
            <ul class="jnet-linklist">
                <?php foreach ($trainings as $course): ?>
                    <li>
                        <a href="<?= $h((string) ($course['href'] ?? url('formations'))) ?>">
                            <strong><?= $h((string) ($course['title'] ?? '')) ?></strong>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
