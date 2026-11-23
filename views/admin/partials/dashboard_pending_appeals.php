<?php

declare(strict_types=1);

$escapeAppealList = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div data-dashboard-appeals-results>
    <?php if ($pendingAppeals === null): ?>
        <p>La file de contestations est momentanément indisponible.</p>
    <?php elseif ($pendingAppeals === []): ?>
        <p>Aucune contestation en attente.</p>
    <?php else: ?>
        <div class="ph-dialog__links">
            <?php foreach ($pendingAppeals as $appeal): ?>
                <?php $id = (int) $appeal['ban_id']; ?>
                <a href="<?= $escapeAppealList(!empty($appeal['reference']) ? url('admin/sanctions/dossiers/' . $id) : url('admin/sanctions') . '?contestation=' . $id . '#contestation-' . $id) ?>">
                    <strong><?= !empty($appeal['reference']) ? $escapeAppealList((string) $appeal['reference']) . ' · ' : '' ?><?= $escapeAppealList((string) ($appeal['display_label'] ?: $appeal['email_norm'])) ?> · Décision n° <?= $id ?></strong>
                    <span>Contestation reçue le <?= $escapeAppealList((string) $appeal['created_at']) ?> UTC</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
