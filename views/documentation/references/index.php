<?php
declare(strict_types=1);
/** @var array<string, array{rel: string, title: string, section: string, hint?: string}> $docEntries */
$docEntries = $docEntries ?? [];
$modOverviewHtml = (string) ($modOverviewHtml ?? '');
$bySection = [];
foreach ($docEntries as $key => $meta) {
    $sec = $meta['section'] ?? 'Mod Overwatch';
    $bySection[$sec] ??= [];
    $bySection[$sec][$key] = $meta;
}
?>
<div class="site-docs">
    <div class="site-docs__shell site-docs__shell--refs">
        <nav class="site-docs__refs-nav" aria-label="Navigation documentation">
            <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>" class="site-docs__refs-back">← Guide du portail</a>
            <a href="<?= htmlspecialchars(url('documentation/marqueurs'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-sky-800">Bibliothèque de marqueurs</a>
            <a href="<?= htmlspecialchars(url('atak/mod/guide'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-sky-800">Guide joueur Overwatch</a>
        </nav>

        <header class="site-docs__refs-hero">
            <p class="site-docs__refs-kicker">Développeurs &amp; intégrateurs</p>
            <h1>Documentation technique du mod</h1>
            <p class="site-docs__refs-lead">
                Architecture Arma du pack <strong>COMSPEC Overwatch</strong>, catalogue des mods / bibliothèques utilisés,
                et notes de compilation. Pour l’usage en mission, consultez le
                <a href="<?= htmlspecialchars(url('atak/mod/guide'), ENT_QUOTES, 'UTF-8') ?>">guide Overwatch</a>
                ou le <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>">guide du portail</a>.
            </p>
        </header>

        <?php if ($modOverviewHtml !== ''): ?>
        <article class="site-docs__file-card site-docs__refs-overview">
            <div class="site-docs__file-body site-docs__md">
                <?= $modOverviewHtml ?>
            </div>
        </article>
        <?php endif; ?>

        <div class="site-docs__refs-stack">
            <?php foreach ($bySection as $section => $items): ?>
            <section>
                <h2 class="site-docs__refs-section-title"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></h2>
                <ul class="site-docs__refs-grid">
                    <?php foreach ($items as $key => $meta): ?>
                    <li>
                        <a href="<?= htmlspecialchars(url('documentation/fichier/' . rawurlencode((string) $key)), ENT_QUOTES, 'UTF-8') ?>"
                           class="site-docs__refs-card">
                            <span class="site-docs__refs-card-title"><?= htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="site-docs__refs-card-hint"><?= htmlspecialchars((string) ($meta['hint'] ?? 'Ouvrir la fiche'), ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>

            <section>
                <h2 class="site-docs__refs-section-title">Ressources associées</h2>
                <ul class="site-docs__refs-grid">
                    <li>
                        <a href="<?= htmlspecialchars(url('documentation/marqueurs'), ENT_QUOTES, 'UTF-8') ?>" class="site-docs__refs-card">
                            <span class="site-docs__refs-card-title">Bibliothèque de marqueurs</span>
                            <span class="site-docs__refs-card-hint">Catalogue visuel des marqueurs carte</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= htmlspecialchars(url('atak/mod/guide'), ENT_QUOTES, 'UTF-8') ?>" class="site-docs__refs-card">
                            <span class="site-docs__refs-card-title">Guide joueur Overwatch</span>
                            <span class="site-docs__refs-card-hint">Installation, hub, realism — usage mission</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= htmlspecialchars(url('atak/mod'), ENT_QUOTES, 'UTF-8') ?>" class="site-docs__refs-card">
                            <span class="site-docs__refs-card-title">Téléchargement du pack</span>
                            <span class="site-docs__refs-card-hint">Page mod Overwatch sur le portail</span>
                        </a>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</div>
