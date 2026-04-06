<?php
declare(strict_types=1);
/** @var array<string, array{rel: string, title: string, section: string}> $docEntries */
$docEntries = $docEntries ?? [];
$bySection = [];
foreach ($docEntries as $key => $meta) {
    $sec = $meta['section'] ?? 'Autre';
    $bySection[$sec] ??= [];
    $bySection[$sec][$key] = $meta;
}
?>
<div class="site-docs">
    <div class="site-docs__shell site-docs__shell--refs">
        <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>" class="site-docs__refs-back">← Guide du portail</a>

        <header class="site-docs__refs-hero">
            <p class="site-docs__refs-kicker">Références</p>
            <h1>Textes techniques &amp; inventaires</h1>
            <p class="site-docs__refs-lead">
                Liste réservée à l’équipe : fiches détaillées (routes, modules, inventaire). Pour le fonctionnement général du site, utilisez le
                <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>">guide utilisateur</a>.
            </p>
        </header>

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
                            <span class="site-docs__refs-card-hint">Ouvrir la fiche source</span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>
