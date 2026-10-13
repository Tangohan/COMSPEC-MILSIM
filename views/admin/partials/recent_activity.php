<?php
/** @var list<array<string, mixed>> $adminRecentActivity */
/** @var string|null $adminRecentActivityError */
/** @var string $adminRecentActivityMoreUrl */
$rows = $adminRecentActivity ?? [];
$error = $adminRecentActivityError ?? null;
$moreUrl = $adminRecentActivityMoreUrl ?? url('admin/audit');
$hr = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<section class="pa-card" aria-labelledby="recent-activity-heading">
    <div class="pa-card__head">
        <span class="pa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></span>
        <div>
            <p class="pa-card__kicker">Tout le site</p>
            <h2 id="recent-activity-heading" class="pa-card__title">Activité récente</h2>
        </div>
        <a class="pa-card__link" href="<?= $hr($moreUrl) ?>">Voir tout →</a>
    </div>
    <?php if ($error): ?>
        <p class="pa-flash pa-flash--err" style="margin:0;"><?= $hr((string) $error) ?></p>
    <?php elseif (empty($rows)): ?>
        <p class="pa-empty-state">Aucun événement récent.</p>
    <?php else: ?>
        <ul class="pa-feed">
            <?php foreach ($rows as $row): ?>
                <li>
                    <span class="pa-feed__title"><?= $hr(audit_action_label_fr((string) ($row['action'] ?? ''))) ?></span>
                    <time><?= $hr((string) ($row['created_at'] ?? '')) ?></time>
                    <span class="pa-feed__meta"><?= $hr((string) ($row['actor_email'] ?? ('#' . (string) ($row['user_id'] ?? '')))) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
