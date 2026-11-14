<?php
declare(strict_types=1);

/**
 * Effectifs › Circuits de validation (EffectifsWorkspaceController::circuits, données WorkflowCircuitsController::pageData).
 * Une carte par type de demande : le circuit en schéma (étapes puis décision), et le formulaire pour le changer dans un
 * volet repliable. Fonctionne sans JavaScript ; le choix d'une personne n'apparaît que pour l'étape « Personne
 * désignée » et les étapes vides au-delà de la première sont masquées (CSS :has, tout reste visible sans lui).
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
    WorkflowCircuits::KIND_COMMANDER => WorkflowCircuits::kindLabel(WorkflowCircuits::KIND_COMMANDER),
    WorkflowCircuits::KIND_UPPER => WorkflowCircuits::kindLabel(WorkflowCircuits::KIND_UPPER),
    WorkflowCircuits::KIND_USER => WorkflowCircuits::kindLabel(WorkflowCircuits::KIND_USER),
];
/** Qui tient l'étape, en clair : le nom de la personne désignée, sinon le rôle. */
$stepWho = static function (array $step) use ($members): string {
    $kind = (string) ($step['kind'] ?? '');

    return $kind === WorkflowCircuits::KIND_USER
        ? ($members[(int) ($step['user_id'] ?? 0)] ?? 'Personne désignée')
        : WorkflowCircuits::kindLabel($kind);
};
$stepNote = static fn (array $step): string => match ((string) ($step['kind'] ?? '')) {
    WorkflowCircuits::KIND_COMMANDER => 'Chef de l’unité où sert le membre',
    WorkflowCircuits::KIND_UPPER => 'Chef au-dessus de l’étape précédente',
    default => 'Personne désignée',
};
?>
<section class="eff-rh-hero circ-hero">
    <h2 class="eff-page-title">Circuits de validation</h2>
    <p class="eff-page-lead">
        Pour chaque type de demande, choisissez qui donne son avis avant la décision des responsables des effectifs.
    </p>
</section>

<?php if ($flashErr): ?><p class="bo-eff-settings__flash is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
<?php if ($flashOk): ?><p class="bo-eff-settings__flash" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
<?php if (empty($circuitReady)): ?>
    <p class="bo-eff-settings__flash is-bad" role="status">Les circuits personnalisés arrivent avec la prochaine mise à jour du site (étape workflow_circuits à lancer dans run-migrations). En attendant, le circuit par défaut s’applique.</p>
<?php endif; ?>

<div class="circ-grid">
<?php foreach ($circuitSubjects as $subject): ?>
    <?php
    $key = (string) $subject['key'];
    $steps = $subject['steps'];
    $titleId = 'circuit-title-' . $key;
    ?>
    <section class="circ-card" id="circuit-<?= $h($key) ?>" aria-labelledby="<?= $h($titleId) ?>">
        <header class="circ-card__head">
            <h3 class="circ-card__title" id="<?= $h($titleId) ?>"><?= $h($subject['label']) ?></h3>
            <span class="circ-badge<?= $subject['custom'] ? ' is-custom' : '' ?>"><?= $subject['custom'] ? 'Personnalisé' : 'Par défaut' ?></span>
        </header>

        <ol class="circ-flow" aria-label="Étapes du circuit">
            <?php foreach ($steps as $i => $step): ?>
                <li class="circ-flow__step">
                    <span class="circ-flow__num" aria-hidden="true"><?= $i + 1 ?></span>
                    <span class="circ-flow__text">
                        <span class="circ-flow__who"<?= (string) ($step['kind'] ?? '') === WorkflowCircuits::KIND_USER ? ' translate="no"' : '' ?>><?= $h($stepWho($step)) ?></span>
                        <span class="circ-flow__note">Avis · <?= $h($stepNote($step)) ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
            <li class="circ-flow__step circ-flow__step--end">
                <span class="circ-flow__num" aria-hidden="true">✓</span>
                <span class="circ-flow__text">
                    <span class="circ-flow__who">Décision des responsables</span>
                    <span class="circ-flow__note"><?= $steps === [] ? 'Directement, sans avis' : 'Avec les avis sous les yeux' ?></span>
                </span>
            </li>
        </ol>

        <?php if ($canManage): ?>
            <details class="circ-edit">
                <summary class="circ-edit__toggle">Modifier les étapes</summary>
                <form method="post" action="<?= $h(effectifs_workspace_url('circuits')) ?>" class="circ-edit__form">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="subject" value="<?= $h($key) ?>">
                    <?php for ($i = 0; $i < $max; $i++): ?>
                        <?php
                        $step = $steps[$i] ?? [];
                        $stepKind = (string) ($step['kind'] ?? '');
                        $stepUser = (int) ($step['user_id'] ?? 0);
                        $fid = 'circuit-' . $key . '-' . $i;
                        ?>
                        <div class="circ-row">
                            <label class="circ-row__num" for="<?= $h($fid) ?>"><span class="circ-sr">Étape </span><?= $i + 1 ?></label>
                            <select class="circ-row__kind" name="kind[<?= $i ?>]" id="<?= $h($fid) ?>">
                                <option value=""<?= $stepKind === '' ? ' selected' : '' ?>><?= $i === 0 ? 'Aucun avis' : 'Pas d’autre étape' ?></option>
                                <?php foreach ($kinds as $value => $text): ?>
                                    <option value="<?= $h($value) ?>"<?= $value === $stepKind ? ' selected' : '' ?>><?= $h($text) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="circ-row__who">
                                <label class="circ-sr" for="<?= $h($fid) ?>-user">Personne désignée pour l’étape <?= $i + 1 ?></label>
                                <select name="user[<?= $i ?>]" id="<?= $h($fid) ?>-user">
                                    <option value="0">Choisir la personne…</option>
                                    <?php foreach ($members as $uid => $label): ?>
                                        <option value="<?= (int) $uid ?>"<?= $uid === $stepUser ? ' selected' : '' ?> translate="no"><?= $h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </span>
                        </div>
                    <?php endfor; ?>
                    <div class="circ-edit__actions">
                        <button type="submit" name="action" value="save" class="eff-rh-btn eff-rh-btn--primary">Enregistrer</button>
                        <?php if ($subject['custom']): ?>
                            <button type="submit" name="action" value="reset" class="eff-rh-btn">Revenir au circuit par défaut</button>
                        <?php endif; ?>
                    </div>
                </form>
            </details>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>

<aside class="circ-notes" aria-label="Bon à savoir">
    <h3 class="circ-notes__title">Bon à savoir</h3>
    <ul>
        <li>Chaque personne sollicitée reçoit une tâche dans Mon service. Son avis est montré au moment de décider, il n’accorde aucun droit.</li>
        <li>Une étape sans titulaire est sautée : pas de chaîne de commandement saisie, ou pas d’échelon au-dessus.</li>
        <li>« Échelon supérieur » : le chef qui suit, dans la chaîne de commandement, la dernière personne sollicitée.</li>
        <li>Un circuit modifié vaut pour les demandes créées ensuite. Une demande en cours garde ses étapes.</li>
    </ul>
</aside>
