<?php
declare(strict_types=1);
/**
 * Parcours du dossier : ce qui est fait, ce qui manque, et l'écran où le faire.
 *
 * @var array{complete: bool, done: int, total: int, steps: list<array<string,mixed>>}|null $caseProgress
 * @var array<string,mixed>|null $originInterestCase
 * @var bool $canManage
 * @var callable $h
 */
$caseProgress = is_array($caseProgress ?? null) ? $caseProgress : null;
$originInterestCase = is_array($originInterestCase ?? null) ? $originInterestCase : null;
$canManage = (bool) ($canManage ?? false);
if ($caseProgress === null) {
    return;
}
$doneCount = (int) $caseProgress['done'];
?>
<section class="panel sse-course">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.00</span>
            Où en est ce dossier
        </div>
        <div class="panel-header__end">
            <div class="panel-meta">
                <?= $doneCount ?> étape<?= $doneCount > 1 ? 's' : '' ?> sur <?= (int) $caseProgress['total'] ?> engagée<?= $doneCount > 1 ? 's' : '' ?>
                <?php if (isset($caseProgress['score'])): ?>
                    · complétude <?= (int) $caseProgress['score'] ?>/100
                <?php endif; ?>
            </div>
            <?php $sectionKey = '01.00'; require __DIR__ . '/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <p class="sse-course__verdict <?= $caseProgress['complete'] ? 'is-ok' : 'is-blocked' ?>">
            <?php if ($caseProgress['complete']): ?>
                <strong>Dossier exploitable</strong>
                <span>Au moins une identité y est rattachée : il désigne quelqu’un et peut être transmis.</span>
            <?php else: ?>
                <strong>Dossier incomplet</strong>
                <span>Aucune identité n’y est rattachée : en l’état, le dossier ne désigne personne.</span>
            <?php endif; ?>
        </p>

        <?php if ($originInterestCase !== null): ?>
            <p class="sse-course__origin">
                Constitué à partir du dossier d’intérêt
                <a class="link" href="<?= $h(url('atak/sse/interet/' . (int) $originInterestCase['id'])) ?>">
                    <?= $h($originInterestCase['reference_code'] ?? '') ?>
                </a>
                — <?= $h($originInterestCase['temporary_designation'] ?? '') ?>.
            </p>
        <?php endif; ?>

        <ol class="sse-course__steps">
            <?php foreach ($caseProgress['steps'] as $i => $step): ?>
                <?php $count = (int) ($step['count'] ?? 0); ?>
                <li class="sse-course__step<?= !empty($step['done']) ? ' is-done' : '' ?><?= empty($step['done']) && !empty($step['required']) ? ' is-required' : '' ?>">
                    <span class="sse-course__num"><?= $i + 1 ?></span>
                    <div class="sse-course__body">
                        <strong><?= $h($step['label'] ?? '') ?></strong>
                        <span><?= $h($step['hint'] ?? '') ?></span>
                        <em>
                            <?php if ($count > 0): ?>
                                <?= $count ?><?php if (trim((string) ($step['unit'] ?? '')) !== ''): ?> <?= $h($step['unit']) ?><?= $count > 1 ? 's' : '' ?><?php else: ?> élément<?= $count > 1 ? 's' : '' ?><?php endif; ?>
                            <?php elseif (!empty($step['required'])): ?>
                                Indispensable — rien pour l’instant
                            <?php else: ?>
                                Rien pour l’instant
                            <?php endif; ?>
                        </em>
                    </div>
                    <?php if ($canManage): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= $h((string) ($step['href'] ?? '')) ?>"><?= $h($step['action'] ?? '') ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (!empty($caseProgress['digest']['summary']) && ($caseProgress['digest']['changes'] ?? []) !== []): ?>
            <p class="sse-course__digest muted">Depuis le dernier calcul : <?= $h($caseProgress['digest']['summary']) ?></p>
        <?php endif; ?>
        <?php if (!empty($caseProgress['pending_suggestions'])): ?>
            <p class="sse-course__digest">
                <a class="link" href="<?= $h(url('atak/sse/rapprochements') . '?case_id=' . (int) ($case['id'] ?? 0)) ?>">
                    <?= (int) $caseProgress['pending_suggestions'] ?> rapprochement(s) moteur à valider
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>
