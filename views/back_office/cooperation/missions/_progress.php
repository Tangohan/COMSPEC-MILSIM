<?php
declare(strict_types=1);

/**
 * En-tête de progression commun aux pages d’une coopération (indicateur d’étapes, badge d’état,
 * prochaine action). Alimenté par CooperationProgress::compute() via $cooperationProgress.
 *
 * - $cooperationProgressShowAction (bool, défaut true) : affiche la prochaine action en ligne
 *   (la synthèse l’affiche dans un bloc dédié).
 */

$prog = is_array($cooperationProgress ?? null) ? $cooperationProgress : null;
if ($prog === null) {
    return;
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$showAction = ($cooperationProgressShowAction ?? true) !== false;
$next = is_array($prog['next_action'] ?? null) ? $prog['next_action'] : null;
$steps = is_array($prog['steps'] ?? null) ? $prog['steps'] : [];
?>
<section id="coop-progress" class="coop-progress" aria-labelledby="coop-progress-heading" data-coop-region>
    <div class="coop-progress__head">
        <div class="min-w-0">
            <p id="coop-progress-heading" class="coop-progress__title"><?= $h((string) $prog['heading']) ?></p>
            <?php if ((string) ($prog['next_label'] ?? '') !== ''): ?>
            <p class="coop-progress__next">Étape suivante : <?= $h((string) $prog['next_label']) ?></p>
            <?php endif; ?>
        </div>
        <?php
        $ui_badge_label = (string) ($prog['state']['label'] ?? '');
        $ui_badge_variant = (string) ($prog['state']['variant'] ?? 'neutral');
        require base_path('views/partials/ui/badge.php');
        ?>
    </div>
    <div class="coop-progress__steps<?= !empty($prog['cancelled']) ? ' is-cancelled' : '' ?><?= !empty($prog['suspended']) ? ' is-suspended' : '' ?>">
        <?php
        $steps = array_map(static fn (array $st): array => [
            'label' => (string) $st['label'],
            'done' => !empty($st['done']),
            'active' => !empty($st['active']),
        ], is_array($prog['steps'] ?? null) ? $prog['steps'] : []);
        require base_path('views/partials/ui/stepper.php');
        $steps = is_array($prog['steps'] ?? null) ? $prog['steps'] : [];
        ?>
    </div>
    <?php foreach ($steps as $st): ?>
        <?php if (!empty($st['active']) && (string) ($st['blocked_reason'] ?? '') !== ''): ?>
        <p class="coop-progress__blocked" role="note"><?= $h((string) $st['blocked_reason']) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($showAction && $next !== null): ?>
    <div class="coop-progress__action coop-progress__action--<?= $h((string) ($next['tone'] ?? 'emerald')) ?>">
        <div class="min-w-0">
            <p class="coop-progress__action-label"><?= !empty($next['actor_is_viewer']) ? 'À vous d’agir' : 'En attente de : ' . $h((string) $next['actor']) ?></p>
            <p class="coop-progress__action-text"><?= $h((string) $next['description']) ?></p>
        </div>
        <?php if ((string) ($next['href'] ?? '') !== ''): ?>
        <a class="coop-progress__action-btn" href="<?= $h((string) $next['href']) ?>"><?= $h((string) $next['label']) ?></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
