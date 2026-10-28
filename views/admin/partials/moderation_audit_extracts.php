<?php
declare(strict_types=1);
/* Extraits ciblés du journal : modération des contenus et création de communautés. */
$auditErr = $adminAuditModerationError ?? null;
$extractRows = [];
foreach ([['Contenus et modération', $adminAuditRecentContent ?? []], ['Communautés', $adminAuditRecentTenant ?? []]] as [$cat, $rows]) {
    foreach ($rows as $row) {
        $row['_cat'] = $cat;
        $extractRows[] = $row;
    }
}
usort($extractRows, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
$extractRows = array_slice($extractRows, 0, 8);
$hx = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<section class="pa-card" aria-labelledby="audit-extracts-heading">
    <div class="pa-card__head">
        <span class="pa-icon pa-icon--amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg></span>
        <div>
            <p class="pa-card__kicker">Journal d’audit</p>
            <h2 id="audit-extracts-heading" class="pa-card__title">Modération et communautés</h2>
        </div>
        <a class="pa-card__link" href="<?= $hx(url('admin/audit')) ?>">Journal complet →</a>
    </div>
    <?php if ($auditErr): ?>
        <p class="pa-flash pa-flash--err" style="margin:0;"><?= $hx((string) $auditErr) ?></p>
    <?php elseif ($extractRows === []): ?>
        <p class="pa-empty-state">Aucune action récente de modération ni de création de communauté.</p>
    <?php else: ?>
        <ul class="pa-feed">
            <?php foreach ($extractRows as $row): ?>
                <li>
                    <span class="pa-feed__title"><?= $hx(audit_action_label_fr((string) ($row['action'] ?? ''))) ?></span>
                    <time><?= $hx((string) ($row['created_at'] ?? '')) ?></time>
                    <span class="pa-feed__meta"><?= $hx((string) $row['_cat']) ?> · <?= $hx((string) ($row['actor_email'] ?? '—')) ?><?php if (!empty($row['tenant_name'])): ?> · <?= $hx((string) $row['tenant_name']) ?><?php endif; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
