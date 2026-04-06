<?php
declare(strict_types=1);
/** @var array<string, mixed> $enrollment */
/** @var bool $lessonAlreadyCompleted */
$enrSlug = (string) ($enrollment['course_slug'] ?? '');
$formationUrl = $enrSlug !== '' ? url('formations/' . $enrSlug) : url('formations');
$currentModule = $currentModule ?? null;
$midNav = $currentModule ? (int) ($currentModule['id'] ?? 0) : 0;
$enrId = (int) $enrollment['id'];
$bilanModuleUrl = ($midNav > 0) ? url('formations/bilan-module?enrollment_id=' . $enrId . '&module_id=' . $midNav) : '';
?>
                <div class="lms-lesson-toolbar">
                    <div class="lms-lesson-toolbar__main">
                        <?php if ($lessonAlreadyCompleted): ?>
                        <span class="lms-lesson-badge-validated">Leçon validée</span>
                        <?php endif; ?>
                    </div>
                    <div class="lms-lesson-toolbar__actions">
                        <a href="<?= htmlspecialchars($formationUrl) ?>" class="lms-btn lms-btn--secondary">Retour à la formation</a>
                        <?php if ($bilanModuleUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($bilanModuleUrl) ?>" class="lms-btn lms-btn--emerald">Synthèse du module</a>
                        <?php endif; ?>
                    </div>
                </div>
