<?php
declare(strict_types=1);
/**
 * Modal Repères (objectifs, résumé, durée, état, avancement) — ouvert depuis la topbar.
 *
 * @var list<string> $moduleObjectives
 * @var list<string> $lessonObjectives
 * @var string $lessonSummary
 * @var array<string, mixed> $lesson
 * @var array<string, mixed>|null $currentModule
 * @var bool $lessonAlreadyCompleted
 * @var int $progressPctDisplay
 * @var int $lessonsTotalCount
 * @var int $lessonsDoneCount
 * @var int $lessonsLeftCount
 * @var string $nextStepHumanLabel
 */
$moduleObjectives = $moduleObjectives ?? [];
$lessonObjectives = $lessonObjectives ?? [];
$lessonSummary = $lessonSummary ?? '';
$lesson = $lesson ?? [];
$currentModule = $currentModule ?? null;
$lessonAlreadyCompleted = (bool) ($lessonAlreadyCompleted ?? false);
$progressPctDisplay = (int) ($progressPctDisplay ?? 0);
$lessonsTotalCount = (int) ($lessonsTotalCount ?? 0);
$lessonsDoneCount = (int) ($lessonsDoneCount ?? 0);
$lessonsLeftCount = (int) ($lessonsLeftCount ?? 0);
$nextStepHumanLabel = $nextStepHumanLabel ?? '';
?>
<div id="lms-reperes-modal" class="lms-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="lms-reperes-modal-title" aria-hidden="true" data-lms-reperes-modal>
    <div class="lms-modal-overlay__backdrop" data-lms-reperes-close tabindex="-1" aria-hidden="true"></div>
    <div class="lms-modal-panel lms-modal-panel--reperes" role="document">
        <div class="lms-modal-panel__head">
            <h2 id="lms-reperes-modal-title" class="lms-modal-panel__title">Repères</h2>
            <button type="button" class="lms-modal-panel__close" data-lms-reperes-close aria-label="Fermer">×</button>
        </div>
        <div class="lms-modal-panel__body space-y-4">
            <?php if ($moduleObjectives !== []): ?>
            <div>
                <p class="mb-1.5 lms-sidebar-sublabel">Objectifs du module</p>
                <ul class="list-inside list-disc space-y-1 text-sm text-slate-700">
                    <?php foreach ($moduleObjectives as $mo): ?>
                    <li><?= htmlspecialchars($mo) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($lessonObjectives !== []): ?>
            <div class="rounded-lg border border-emerald-100 bg-emerald-50/50 p-3">
                <p class="mb-1.5 lms-sidebar-sublabel text-emerald-800">À l’issue de cette leçon</p>
                <ul class="list-inside list-disc space-y-1 text-sm text-slate-800">
                    <?php foreach ($lessonObjectives as $lo): ?>
                    <li><?= htmlspecialchars($lo) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php elseif ($lessonSummary !== ''): ?>
            <div>
                <p class="mb-1.5 lms-sidebar-sublabel">Résumé</p>
                <p class="text-sm leading-relaxed text-slate-700"><?= htmlspecialchars($lessonSummary) ?></p>
            </div>
            <?php endif; ?>
            <div class="lms-reperes-meta">
                <div class="lms-reperes-meta__row">
                    <p class="lms-sidebar-sublabel">Durée indicative</p>
                    <p class="lms-reperes-meta__value"><?= !empty($lesson['duration_minutes']) ? (int) $lesson['duration_minutes'] . ' min' : '—' ?></p>
                    <?php if ($currentModule && (int) ($currentModule['estimated_minutes'] ?? 0) > 0): ?>
                    <p class="mt-0.5 text-xs text-slate-500">Module (estimation) : <?= (int) $currentModule['estimated_minutes'] ?> min</p>
                    <?php endif; ?>
                </div>
                <div class="lms-reperes-meta__row">
                    <p class="lms-sidebar-sublabel">État</p>
                    <p class="lms-reperes-status <?= $lessonAlreadyCompleted ? 'lms-reperes-status--done' : 'lms-reperes-status--active' ?>"><?= $lessonAlreadyCompleted ? 'Terminée' : 'En cours' ?></p>
                </div>
                <div class="lms-reperes-meta__row">
                    <p class="lms-sidebar-sublabel">Avancement du parcours</p>
                    <p class="lms-reperes-progress-pct"><?= $progressPctDisplay ?> %</p>
                    <div class="lms-reperes-progress-track" role="progressbar" aria-valuenow="<?= $progressPctDisplay ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Avancement du parcours">
                        <span class="lms-reperes-progress-fill" style="width:<?= min(100, $progressPctDisplay) ?>%"></span>
                    </div>
                    <?php if ($lessonsTotalCount > 0): ?>
                    <p class="lms-reperes-remain">
                        <?php if ($lessonsLeftCount === 0): ?>
                        Parcours terminé — <?= $lessonsDoneCount ?> leçon<?= $lessonsDoneCount > 1 ? 's' : '' ?> validée<?= $lessonsDoneCount > 1 ? 's' : '' ?>.
                        <?php else: ?>
                        <?= $lessonsDoneCount ?> / <?= $lessonsTotalCount ?> leçon<?= $lessonsTotalCount > 1 ? 's' : '' ?> ·
                        il vous reste <?= $lessonsLeftCount ?> leçon<?= $lessonsLeftCount > 1 ? 's' : '' ?>.
                        <?php endif; ?>
                    </p>
                    <?php if ($nextStepHumanLabel !== '' && $lessonsLeftCount > 0): ?>
                    <p class="lms-reperes-next">Prochaine étape : <?= htmlspecialchars($nextStepHumanLabel) ?></p>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  var modal = document.getElementById('lms-reperes-modal');
  if (!modal) return;
  var openBtn = document.querySelector('[data-lms-reperes-open]');
  function openModal() {
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('lms-modal-open');
  }
  function closeModal() {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('lms-modal-open');
  }
  if (openBtn) {
    openBtn.addEventListener('click', function (e) {
      e.preventDefault();
      openModal();
    });
  }
  modal.querySelectorAll('[data-lms-reperes-close]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeModal();
    }
  });
})();
</script>
