<?php
declare(strict_types=1);

/**
 * Sous-menu tiroir : tous les liens portail autorisés pour le profil (groupés).
 *
 * @var array<string, list<array{label: string, href: string, routePath: string, group: string}>> $dashNavFullGroups
 */
?>
<template id="dashTplFullNav">
    <?php if (empty($dashNavFullGroups)): ?>
        <p class="rounded-xl px-4 py-6 text-sm leading-relaxed text-slate-500">Aucun accès supplémentaire n’est listé pour votre profil. Si un module manque, contactez un responsable de l’unité.</p>
    <?php else: ?>
        <?php foreach ($dashNavFullGroups as $groupTitle => $items): ?>
            <?php if (!is_array($items) || $items === []) {
                continue;
            } ?>
            <p class="bg-white px-4 pb-1 pt-3 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400"><?= htmlspecialchars((string) $groupTitle) ?></p>
            <?php foreach ($items as $entry): ?>
                <?php if (!is_array($entry)) {
                    continue;
                } ?>
                <a href="<?= htmlspecialchars((string) ($entry['href'] ?? '#')) ?>"
                   class="block rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                   onclick="toggleMenu()"><?= htmlspecialchars((string) ($entry['label'] ?? '')) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</template>
