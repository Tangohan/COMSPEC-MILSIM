<?php
declare(strict_types=1);
/** @var list<array{date: string, title: string, body: string, items: list<string>}> $changelogEntries */
$changelogEntries = is_array($changelogEntries ?? null) ? $changelogEntries : [];
?>
<article class="site-page">
    <header class="site-page__hero">
        <p class="hi-kicker text-emerald-400/90"><?= htmlspecialchars(__('site.changelog_kicker'), ENT_QUOTES, 'UTF-8') ?></p>
        <h1 class="hi-display hi-display-md mt-4 text-white"><?= htmlspecialchars(__('site.changelog_title'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="hi-body mt-5 max-w-2xl text-white/65"><?= htmlspecialchars(__('site.changelog_lead'), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <div class="site-changelog">
        <?php foreach ($changelogEntries as $entry): ?>
            <section class="site-changelog__entry">
                <p class="site-changelog__date"><?= htmlspecialchars((string) ($entry['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <h2><?= htmlspecialchars((string) ($entry['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars((string) ($entry['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($entry['items']) && is_array($entry['items'])): ?>
                    <ul>
                        <?php foreach ($entry['items'] as $item): ?>
                            <li><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</article>
