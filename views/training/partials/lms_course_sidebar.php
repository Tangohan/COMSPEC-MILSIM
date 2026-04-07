<?php
declare(strict_types=1);
/** @var array<string, mixed> $course */
/** @var array<string, mixed>|null $enrollment */
/** @var float|int $progressPercent */
/** @var int|null $currentLessonId */
/** @var string $lmsBase */
$lmsBase = $lmsBase ?? url('');
$course = $course ?? [];
$enrollment = $enrollment ?? null;
$progressPercent = (float) ($progressPercent ?? 0);
$currentLessonId = isset($currentLessonId) ? (int) $currentLessonId : null;
$lmsHideEchangesSidebarLink = !empty($lmsHideEchangesSidebarLink);
$canWithdrawEnrollment = !empty($canWithdrawEnrollment);
/** Ressources de la leçon courante (vue leçon uniquement ; sinon tableau vide). */
if (!isset($lessonResources)) {
    $lessonResources = (isset($resources) && is_array($resources)) ? $resources : [];
}
$modules = $course['modules'] ?? [];
$courseSlug = (string) ($course['slug'] ?? '');
$code = (string) ($course['course_code'] ?? '');
if ($code === '') {
    $code = 'F-' . (int) ($course['id'] ?? 0);
}
?>
<aside class="lms-dark-panel text-white p-6 lg:p-8 flex flex-col lg:sticky lg:top-0 lg:max-h-screen lg:overflow-y-auto">
    <div class="pb-6 border-b border-white/10">
        <a href="<?= htmlspecialchars($lmsBase) ?>/formations" class="text-[10px] font-black uppercase tracking-widest text-emerald-400 hover:text-white">← Catalogue</a>
        <p class="text-[9px] font-black tracking-[0.35em] uppercase text-white/40 mt-4 mb-1">Parcours</p>
        <h1 class="text-lg font-black tracking-tight uppercase leading-tight"><?= htmlspecialchars((string) ($course['title'] ?? '')) ?></h1>
        <p class="text-[10px] font-mono text-emerald-400/90 mt-2"><?= htmlspecialchars($code) ?></p>
        <?php if ($enrollment): ?>
        <div class="mt-4">
            <div class="flex justify-between text-[10px] font-black uppercase text-white/50 mb-1">
                <span>Progression</span>
                <span><?= (int) round($progressPercent) ?> %</span>
            </div>
            <div class="lms-progress-bar h-2 bg-white/10 rounded-full overflow-hidden">
                <span style="width: <?= min(100, max(0, $progressPercent)) ?>%"></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <nav class="pt-6 space-y-6 flex-1">
        <?php foreach ($modules as $mod):
            $lessons = $mod['lessons'] ?? [];
            $quizzes = $mod['quizzes'] ?? [];
        ?>
        <div>
            <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 mb-2"><?= htmlspecialchars((string) ($mod['title'] ?? 'Module')) ?></p>
            <?php if (!empty($mod['subtitle'])): ?>
            <p class="text-[9px] text-white/45 font-medium normal-case mb-2 leading-snug -mt-1"><?= htmlspecialchars((string) $mod['subtitle']) ?></p>
            <?php endif; ?>
            <ul class="space-y-1">
                <?php foreach ($lessons as $les):
                    $lid = (int) ($les['id'] ?? 0);
                    $isCurrent = $currentLessonId !== null && $lid === $currentLessonId;
                    $href = $enrollment
                        ? htmlspecialchars($lmsBase) . '/formations/lesson/' . $lid . '?enrollment_id=' . (int) $enrollment['id']
                        : '#';
                    $lesSum = trim((string) ($les['summary'] ?? ''));
                ?>
                <li>
                    <?php if ($enrollment): ?>
                    <a href="<?= $href ?>" title="<?= $lesSum !== '' ? htmlspecialchars($lesSum) : '' ?>" class="block rounded-xl px-3 py-2 text-[11px] font-semibold uppercase tracking-wide border <?= $isCurrent ? 'lms-active-nav border' : 'border-transparent text-white/70 hover:bg-white/5' ?>">
                        <span class="block leading-snug"><?= htmlspecialchars((string) ($les['title'] ?? '')) ?></span>
                        <?php if ($lesSum !== ''): ?>
                        <span class="block mt-0.5 text-[9px] font-normal normal-case text-white/45 leading-tight line-clamp-2"><?= htmlspecialchars($lesSum) ?></span>
                        <?php endif; ?>
                    </a>
                    <?php else: ?>
                    <span class="block text-[11px] text-white/35 px-3 py-2"><?= htmlspecialchars((string) ($les['title'] ?? '')) ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
                <?php foreach ($quizzes as $qz):
                    $qid = (int) ($qz['id'] ?? 0);
                    $qtitle = (string) ($qz['title'] ?? 'Quiz');
                ?>
                <li>
                    <?php if ($enrollment && $qid > 0): ?>
                    <form method="post" action="<?= htmlspecialchars($lmsBase) ?>/formations/quiz/start" class="inline">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="quiz_id" value="<?= $qid ?>">
                        <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
                        <button type="submit" class="w-full text-left rounded-xl px-3 py-2 text-[11px] font-semibold uppercase tracking-wide border border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/10">
                            <?= htmlspecialchars($qtitle) ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="block text-[11px] text-white/35 px-3 py-2"><?= htmlspecialchars($qtitle) ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($lessonResources)): ?>
        <div class="pt-2 border-t border-white/10" aria-label="Ressources de la leçon">
            <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 mb-2">Ressources</p>
            <p class="text-[9px] text-white/45 font-medium normal-case mb-2 leading-snug -mt-1">Documents et liens utiles pour cette leçon</p>
            <ul class="space-y-1">
                <?php foreach ($lessonResources as $r):
                    $resId = (int) ($r['id'] ?? 0);
                    $resTitle = trim((string) ($r['title'] ?? ''));
                    if ($resId < 1 || $resTitle === '') {
                        continue;
                    }
                    $openBlank = false;
                    if (($r['resource_type'] ?? '') === 'library_document' && !empty($r['document_id'])) {
                        $resHref = url('api/training/resource/' . $resId . '/document?inline=1');
                        $openBlank = true;
                    } elseif (!empty($r['file_path'])) {
                        $resHref = url('api/training/resource/' . $resId . '/download');
                    } elseif (!empty($r['external_url'])) {
                        $extH = training_lms_resource_external_href((string) $r['external_url']);
                        if ($extH !== null) {
                            $resHref = $extH;
                            $openBlank = true;
                        } else {
                            $resHref = '';
                        }
                    } else {
                        $resHref = '';
                    }
                ?>
                <li>
                    <?php if ($resHref !== ''): ?>
                    <a href="<?= htmlspecialchars($resHref, ENT_QUOTES, 'UTF-8') ?>"<?= $openBlank ? ' target="_blank" rel="noopener noreferrer"' : '' ?> class="block rounded-xl px-3 py-2 text-[11px] font-semibold uppercase tracking-wide border border-emerald-500/30 text-emerald-200/95 hover:bg-emerald-500/10 leading-snug">
                        <?= htmlspecialchars($resTitle) ?>
                    </a>
                    <?php else: ?>
                    <span class="block rounded-xl px-3 py-2 text-[11px] text-white/45 leading-snug"><?= htmlspecialchars($resTitle) ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </nav>

    <?php if ($enrollment && $courseSlug !== '' && !$lmsHideEchangesSidebarLink): ?>
    <div class="mt-6 pt-4 border-t border-white/10">
        <a href="<?= htmlspecialchars($lmsBase) ?>/formations/<?= rawurlencode($courseSlug) ?>/echanges" class="block rounded-xl px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-emerald-300/95 border border-emerald-500/25 hover:bg-emerald-500/10">Avis &amp; échanges</a>
        <p class="text-[9px] text-white/35 mt-2 leading-snug">Note, questions et commentaires — fin de parcours</p>
    </div>
    <?php endif; ?>

    <?php if ($enrollment && $canWithdrawEnrollment && $courseSlug !== ''): ?>
    <div class="mt-6 pt-4 border-t border-white/10">
        <form method="post" action="<?= htmlspecialchars($lmsBase) ?>/formations/inscription/annuler" class="space-y-2" onsubmit="return confirm('Annuler votre inscription à ce parcours ? Vous pourrez vous réinscrire depuis le catalogue si les conditions le permettent.');">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
            <input type="hidden" name="return_path" value="<?= htmlspecialchars('formations/' . $courseSlug, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="w-full rounded-xl border border-rose-500/35 bg-rose-500/10 px-3 py-2.5 text-left text-[10px] font-black uppercase tracking-widest text-rose-200 hover:bg-rose-500/20">
                Annuler mon inscription
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="mt-8 pt-6 border-t border-white/10">
        <a href="<?= htmlspecialchars($lmsBase) ?>/dashboard" class="text-[10px] font-black uppercase tracking-widest text-white/40 hover:text-white">Dashboard</a>
    </div>
</aside>
