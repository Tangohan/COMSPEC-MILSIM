<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sections = is_array($sections ?? null) ? $sections : [];
?>
<section class="jnet-panel">
    <div class="jnet-panel__head">
        <h2>Bibliothèque d’unité</h2>
        <div class="jnet-mail__actions">
            <a class="jnet-btn" href="<?= $h((string) ($athenaDocs ?? url('documents'))) ?>">Documents Athena</a>
            <a class="jnet-btn" href="<?= $h((string) ($sseGuide ?? url('atak/sse/guide'))) ?>">Guide SSE</a>
        </div>
    </div>
    <div class="jnet-panel__body jnet-home-grid">
        <?php foreach ($sections as $sec): ?>
            <div class="jnet-panel">
                <div class="jnet-panel__head"><h2><?= $h((string) ($sec['label'] ?? '')) ?></h2></div>
                <div class="jnet-panel__body">
                    <ul class="jnet-bullet">
                        <?php foreach (($sec['items'] ?? []) as $item): ?>
                            <li><?= $h((string) $item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
