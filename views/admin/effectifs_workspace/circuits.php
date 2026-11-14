<?php
declare(strict_types=1);

/**
 * Effectifs › Circuits de validation (EffectifsWorkspaceController::circuits, données WorkflowCircuitsController::pageData).
 * Un formulaire par type de demande ; fonctionne sans JavaScript.
 *
 * @var list<array{key: string, label: string, custom: bool, steps: list<array<string, mixed>>, default_steps: list<array<string, mixed>>}> $circuitSubjects
 * @var array<int, string> $circuitMembers
 */
use App\Services\Workflow\WorkflowCircuits;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrf = (string) ($csrfToken ?? '');
$members = is_array($circuitMembers ?? null) ? $circuitMembers : [];
$canManage = !empty($circuitCanManage) && !empty($circuitReady);
$max = (int) ($circuitMaxSteps ?? WorkflowCircuits::MAX_STEPS);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$kinds = [
    '' => '— Aucune étape —',
    WorkflowCircuits::KIND_COMMANDER => WorkflowCircuits::kindLabel(WorkflowCircuits::KIND_COMMANDER),
    WorkflowCircuits::KIND_UPPER => WorkflowCircuits::kindLabel(WorkflowCircuits::KIND_UPPER),
    WorkflowCircuits::KIND_USER => WorkflowCircuits::kindLabel(WorkflowCircuits::KIND_USER),
];
$describe = static function (array $steps) use ($members): string {
    if ($steps === []) {
        return 'Aucun avis : la demande va directement aux responsables.';
    }
    $parts = [];
    foreach ($steps as $step) {
        $kind = (string) ($step['kind'] ?? '');
        $parts[] = $kind === WorkflowCircuits::KIND_USER
            ? ($members[(int) ($step['user_id'] ?? 0)] ?? 'Personne désignée')
            : WorkflowCircuits::kindLabel($kind);
    }

    $parts[] = 'décision des responsables';

    return implode(' → ', $parts);
};
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Organisation</p>
    <h2 class="eff-page-title">Circuits de validation</h2>
    <p class="eff-page-lead">
        Choisissez qui donne son avis sur chaque type de demande, et dans quel ordre, avant la décision des responsables
        des effectifs. Chaque personne reçoit une tâche dans Mon service. Les avis sont visibles au moment de décider ;
        ils n’accordent aucun droit. Une étape sans titulaire (pas de chaîne de commandement saisie, échelon vide) est
        sautée.
    </p>
</section>

<?php if ($flashErr): ?><p class="bo-eff-settings__flash is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
<?php if ($flashOk): ?><p class="bo-eff-settings__flash" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
<?php if (empty($circuitReady)): ?>
    <p class="bo-eff-settings__flash is-bad" role="status">Les circuits personnalisés arrivent avec la prochaine mise à jour du site (étape workflow_circuits à lancer dans run-migrations). En attendant, le circuit par défaut s’applique.</p>
<?php endif; ?>

<div class="bo-eff-settings">
<?php foreach ($circuitSubjects as $subject): ?>
    <?php $steps = $subject['steps']; ?>
    <section class="bo-eff-settings__block" id="circuit-<?= $h($subject['key']) ?>" aria-labelledby="circuit-title-<?= $h($subject['key']) ?>">
        <h2 id="circuit-title-<?= $h($subject['key']) ?>"><?= $h($subject['label']) ?></h2>
        <p>
            <strong><?= $subject['custom'] ? 'Circuit de la communauté' : 'Circuit par défaut' ?></strong> :
            <?= $h($describe($steps)) ?>
        </p>
        <?php if ($canManage): ?>
            <form method="post" action="<?= $h(effectifs_workspace_url('circuits')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="subject" value="<?= $h($subject['key']) ?>">
                <div class="eff-rh-form__grid">
                    <?php for ($i = 0; $i < $max; $i++): ?>
                        <?php
                        $step = $steps[$i] ?? [];
                        $stepKind = (string) ($step['kind'] ?? '');
                        $stepUser = (int) ($step['user_id'] ?? 0);
                        $fid = 'circuit-' . $subject['key'] . '-' . $i;
                        ?>
                        <div class="eff-rh-field">
                            <label class="eff-rh-field__label" for="<?= $h($fid) ?>">Étape <?= $i + 1 ?></label>
                            <select name="kind[<?= $i ?>]" id="<?= $h($fid) ?>">
                                <?php foreach ($kinds as $value => $text): ?>
                                    <option value="<?= $h($value) ?>" <?= $value === $stepKind ? 'selected' : '' ?>><?= $h($text) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="eff-rh-field__label" for="<?= $h($fid) ?>-user">Personne désignée (si choisie)</label>
                            <select name="user[<?= $i ?>]" id="<?= $h($fid) ?>-user">
                                <option value="0">—</option>
                                <?php foreach ($members as $uid => $label): ?>
                                    <option value="<?= (int) $uid ?>" <?= $uid === $stepUser ? 'selected' : '' ?> translate="no"><?= $h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                </div>
                <p class="bo-eff-settings__actions">
                    <button type="submit" name="action" value="save" class="eff-rh-btn eff-rh-btn--primary">Enregistrer ce circuit</button>
                    <?php if ($subject['custom']): ?>
                        <button type="submit" name="action" value="reset" class="eff-rh-btn">Revenir au circuit par défaut</button>
                    <?php endif; ?>
                </p>
            </form>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>
<p class="eff-page-lead">Un circuit modifié s’applique aux demandes créées ensuite ; une demande déjà en cours garde ses étapes. « Échelon supérieur » désigne le chef qui suit, dans la chaîne de commandement, la dernière personne sollicitée.</p>
