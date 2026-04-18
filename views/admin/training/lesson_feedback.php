<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $courses */
/** @var list<array<string, mixed>> $lessonFeedbackRows */
/** @var int $selectedCourseId */
/** @var array{count:int,avg_difficulty:float,avg_clarity:float,avg_utility:float} $lessonFeedbackStats */

require base_path('views/admin/training/partials/command_shell_open.php');
?>
<section class="space-y-6">
    <header class="space-y-1">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Feedback post-leçon</h1>
        <p class="text-sm text-slate-600">Retours standardisés saisis en fin de leçon (difficulté, clarté, utilité), exploitables pour les révisions pédagogiques.</p>
    </header>

    <form method="get" action="<?= htmlspecialchars(training_lms_admin_url('feedback'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label for="course_id" class="block text-xs font-bold uppercase tracking-wide text-slate-600">Filtrer par parcours</label>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <select id="course_id" name="course_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                <option value="0">Tous les parcours</option>
                <?php foreach ($courses as $course): ?>
                <?php $cid = (int) ($course['id'] ?? 0); ?>
                <option value="<?= $cid ?>" <?= $selectedCourseId === $cid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($course['title'] ?? 'Parcours #' . $cid)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Appliquer</button>
            <a href="<?= htmlspecialchars(training_lms_admin_url('feedback'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-slate-700 hover:underline">Réinitialiser</a>
        </div>
    </form>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Feedbacks</p>
            <p class="mt-2 text-2xl font-black text-slate-900"><?= (int) ($lessonFeedbackStats['count'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Difficulté moyenne</p>
            <p class="mt-2 text-2xl font-black text-slate-900"><?= number_format((float) ($lessonFeedbackStats['avg_difficulty'] ?? 0), 2, ',', ' ') ?>/5</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Clarté moyenne</p>
            <p class="mt-2 text-2xl font-black text-slate-900"><?= number_format((float) ($lessonFeedbackStats['avg_clarity'] ?? 0), 2, ',', ' ') ?>/5</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Utilité moyenne</p>
            <p class="mt-2 text-2xl font-black text-slate-900"><?= number_format((float) ($lessonFeedbackStats['avg_utility'] ?? 0), 2, ',', ' ') ?>/5</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Apprenant</th>
                    <th class="px-4 py-3 text-left">Parcours / module / leçon</th>
                    <th class="px-4 py-3 text-center">Difficulté</th>
                    <th class="px-4 py-3 text-center">Clarté</th>
                    <th class="px-4 py-3 text-center">Utilité</th>
                    <th class="px-4 py-3 text-left">Commentaire</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($lessonFeedbackRows === []): ?>
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-slate-500">Aucun feedback post-leçon enregistré pour ce filtre.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($lessonFeedbackRows as $row): ?>
                <tr class="align-top">
                    <td class="px-4 py-3 whitespace-nowrap text-slate-700"><?= htmlspecialchars((string) ($row['updated_at'] ?: $row['created_at'] ?? '')) ?></td>
                    <td class="px-4 py-3 text-slate-700">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($row['learner_name'] ?? '')) ?></p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($row['learner_email'] ?? '')) ?></p>
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($row['course_title'] ?? '')) ?></p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($row['module_title'] ?? '')) ?></p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($row['lesson_title'] ?? '')) ?></p>
                    </td>
                    <td class="px-4 py-3 text-center font-semibold text-slate-900"><?= (int) ($row['difficulty_rating'] ?? 0) ?>/5</td>
                    <td class="px-4 py-3 text-center font-semibold text-slate-900"><?= (int) ($row['clarity_rating'] ?? 0) ?>/5</td>
                    <td class="px-4 py-3 text-center font-semibold text-slate-900"><?= (int) ($row['utility_rating'] ?? 0) ?>/5</td>
                    <td class="px-4 py-3 text-slate-700"><?= nl2br(htmlspecialchars((string) ($row['comment'] ?? '—'))) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>

