<?php
declare(strict_types=1);

/**
 * Avis donnés sur une demande (moteur de circuits), sous son statut : chef d'unité, échelon supérieur ou personne
 * désignée par le circuit de la communauté.
 * Attend $circuits (subject_id => résumé de WorkflowEngine::summaries) et $workflowSubjectId (identifiant de la demande).
 */
$circuit = is_array($circuits[$workflowSubjectId ?? 0] ?? null) ? $circuits[$workflowSubjectId ?? 0] : null;
if ($circuit !== null):
    $wfH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $wfOpinions = is_array($circuit['opinions'] ?? null) ? $circuit['opinions'] : [];
    $wfAssignee = trim((string) ($circuit['assignee_label'] ?? ''));
    $wfPending = ($circuit['state'] ?? '') === \App\Services\Workflow\WorkflowEngine::STATE_OPINION;
    $wfWho = static function (string $role, string $label, bool $favorable) use ($wfH): string {
        $named = $label !== '' ? ' (' . $wfH($label) . ')' : '';
        if ($role === 'user') {
            return ($favorable ? 'Avis favorable' : 'Avis défavorable') . ($label !== '' ? ' : ' . $wfH($label) : '');
        }
        if ($role === 'upper') {
            return ($favorable ? 'Avis favorable de l’échelon supérieur' : 'Avis défavorable de l’échelon supérieur') . $named;
        }

        return ($favorable ? 'Avis favorable du chef d’unité' : 'Avis défavorable du chef d’unité') . $named;
    };
    if ($wfOpinions !== [] || $wfPending || ($circuit['opinion'] ?? '') === 'skipped'):
    ?>
    <span class="eff-sheets__meta">
        <?php foreach ($wfOpinions as $wfI => $wfOp): ?>
            <?= $wfI > 0 ? '<br>' : '' ?><?= $wfWho((string) ($wfOp['role'] ?? ''), trim((string) ($wfOp['label'] ?? '')), ($wfOp['opinion'] ?? '') === 'favorable') ?>
            <?php if (trim((string) ($wfOp['comment'] ?? '')) !== ''): ?>
                <br><span translate="no">« <?= $wfH(trim((string) $wfOp['comment'])) ?> »</span>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($wfPending): ?>
            <?= $wfOpinions !== [] ? '<br>' : '' ?><?= match ((string) ($circuit['assignee_role'] ?? '')) { 'upper' => 'Avis attendu de l’échelon supérieur', 'user' => 'Avis attendu', default => 'Avis attendu du chef d’unité' } ?><?= $wfAssignee !== '' ? ' : ' . $wfH($wfAssignee) : '' ?>
        <?php elseif (($circuit['opinion'] ?? '') === 'skipped'): ?>
            <?= $wfOpinions !== [] ? '<br>' : '' ?>Décidé sans attendre l’avis demandé
        <?php endif; ?>
    </span>
    <?php endif; ?>
<?php endif; ?>
