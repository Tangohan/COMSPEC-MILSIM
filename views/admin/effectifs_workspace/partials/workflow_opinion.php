<?php
declare(strict_types=1);

/**
 * Avis du chef d'unité (moteur de circuits), sous le statut d'une demande.
 * Attend $circuits (subject_id => résumé de WorkflowEngine::summaries) et $workflowSubjectId (identifiant de la demande).
 */
$circuit = is_array($circuits[$workflowSubjectId ?? 0] ?? null) ? $circuits[$workflowSubjectId ?? 0] : null;
if ($circuit !== null):
    $opinion = (string) ($circuit['opinion'] ?? '');
    $assignee = trim((string) ($circuit['assignee_label'] ?? ''));
    $opinionComment = trim((string) ($circuit['opinion_comment'] ?? ''));
    ?>
    <span class="eff-sheets__meta">
        <?php if ($opinion === 'favorable'): ?>
            Avis favorable du chef d’unité<?= $assignee !== '' ? ' (' . htmlspecialchars($assignee, ENT_QUOTES, 'UTF-8') . ')' : '' ?>
        <?php elseif ($opinion === 'unfavorable'): ?>
            Avis défavorable du chef d’unité<?= $assignee !== '' ? ' (' . htmlspecialchars($assignee, ENT_QUOTES, 'UTF-8') . ')' : '' ?>
        <?php elseif ($opinion === 'skipped'): ?>
            Décidé sans l’avis du chef d’unité
        <?php elseif (($circuit['state'] ?? '') === \App\Services\Workflow\WorkflowEngine::STATE_OPINION): ?>
            Avis attendu du chef d’unité<?= $assignee !== '' ? ' : ' . htmlspecialchars($assignee, ENT_QUOTES, 'UTF-8') : '' ?>
        <?php endif; ?>
        <?php if ($opinionComment !== ''): ?>
            <br><span translate="no">« <?= htmlspecialchars($opinionComment, ENT_QUOTES, 'UTF-8') ?> »</span>
        <?php endif; ?>
    </span>
<?php endif; ?>
